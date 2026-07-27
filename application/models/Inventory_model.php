<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Inventory_model
 *
 * Derives room AVAILABILITY per date from the physical rooms and the active
 * bookings. There is no manually-set inventory — availability is computed:
 *
 *     available(category, date) = active rooms in category
 *                               − rooms occupied by active bookings that night
 *
 * A booking occupies a room on night D when D is within [check-in, check-out)
 * and its status is "occupying" (Room booked / Checked in). A booking with an
 * allotted room (room_id) consumes 1 room of that room's category; otherwise it
 * consumes its room_quantity of its booked category. Cancelled / No-show /
 * Checked-out bookings free their rooms.
 */
class Inventory_model extends CI_Model
{
    /** Statuses (status_master.status_code) that hold a room. */
    protected $occupying = array('room_booked', 'checked_in');

    /**
     * Active room categories with their count of active physical rooms.
     * Only categories that actually have rooms are returned.
     *
     * @return array of {category_id, category_name, total_rooms}
     */
    public function categories_with_totals()
    {
        return $this->db
            ->select('rc.category_id, rc.category_name, COUNT(r.id) AS total_rooms', FALSE)
            ->from('room_categories rc')
            ->join('rooms r', 'r.category_id = rc.category_id AND r.is_active = 1', 'left')
            ->where('rc.status', 1)
            ->group_by('rc.category_id')
            ->having('COUNT(r.id) > 0')
            ->order_by('rc.display_order', 'ASC')
            ->order_by('rc.category_name', 'ASC')
            ->get()->result();
    }

    /**
     * All active rooms with their category name.
     * Returns individual rooms sorted by room number.
     *
     * @param array $filters - optional filters: room_no, category_id
     * @return array of {id, room_no, category_id, category_name}
     */
    public function all_rooms_with_category($filters = array())
    {
        $this->db
            ->select('r.id, r.room_no, r.category_id, rc.category_name')
            ->from('rooms r')
            ->join('room_categories rc', 'rc.category_id = r.category_id', 'left')
            ->where('r.is_active', 1);

        // Filter by room number/name
        if (!empty($filters['room_no'])) {
            $this->db->like('r.room_no', $filters['room_no']);
        }

        // Filter by category
        if (!empty($filters['category_id'])) {
            $this->db->where('r.category_id', (int) $filters['category_id']);
        }

        return $this->db
            ->order_by('r.room_no', 'ASC')
            ->get()->result();
    }

    /**
     * Get all active room categories for filter dropdown.
     *
     * @return array of {category_id, category_name}
     */
    public function get_all_categories()
    {
        return $this->db
            ->select('category_id, category_name')
            ->from('room_categories')
            ->where('status', 1)
            ->order_by('display_order', 'ASC')
            ->order_by('category_name', 'ASC')
            ->get()->result();
    }

    /**
     * All occupying (Room booked / Checked in) bookings. The per-night overlap
     * and effective stay range are resolved by the caller in PHP.
     *
     * The allotted-room join is guarded by is_active = 1 so it stays consistent
     * with categories_with_totals() (which counts active rooms only): a booking
     * on a de-activated room resolves to a NULL category and is skipped.
     *
     * @return array of {room_id, room_category_id, room_quantity, total_unit,
     *                   cin, cout, checked_in_at, checked_out_at, status_code, room_cat}
     */
    public function occupying_bookings()
    {
        return $this->db
            ->select('b.room_id, b.room_category_id, b.room_quantity, b.total_unit,
                      b.scheduled_check_in_date AS cin, b.scheduled_check_out_date AS cout,
                      b.checked_in_at, b.checked_out_at, sm.status_code,
                      r.category_id AS room_cat')
            ->from('booking_details b')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id AND r.is_active = 1', 'left')
            ->where_in('sm.status_code', $this->occupying)
            ->get()->result();
    }

    /**
     * Build the availability matrix for a $days window starting at $start.
     * Returns availability per individual room instead of per category.
     *
     * @param  string $start  Y-m-d
     * @param  int    $days
     * @param  array  $filters - optional filters: room_no, category_id
     * @return array {
     *     dates:        [Y-m-d, …],
     *     rooms:        [ {id, room_no, category_id, category_name, avail:{date=>n}, booked:{date=>n}}, … ],
     *     avail_totals: {date=>n},   booked_totals:{date=>n},
     *     total_rooms:  int
     * }
     */
    public function availability($start, $days, $filters = array())
    {
        $t0 = strtotime($start);
        $dates = array();
        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('Y-m-d', strtotime('+'.$i.' day', $t0));
        }

        $rooms = $this->all_rooms_with_category($filters);

        // occ[room_id][date] = 1 if occupied, 0 if available
        $occ = array();
        foreach ($rooms as $r) {
            $occ[(int) $r->id] = array_fill_keys($dates, 0);
        }

        foreach ($this->occupying_bookings() as $b) {
            $room_id = $b->room_id;
            if (!$room_id || !isset($occ[$room_id])) {
                continue;   // no room assigned or room not active
            }

            // Effective stay range: scheduled dates win; otherwise fall back to
            // the actual check-in/out timestamps, so a checked-in guest with no
            // scheduled dates still occupies their room from check-in until they
            // check out (open-ended while still in-house).
            $cin = $b->cin ?: ($b->checked_in_at ? substr($b->checked_in_at, 0, 10) : NULL);
            if ( ! $cin) {
                continue;   // no date at all -> can't place on the calendar
            }
            if ($b->cout) {
                $cout = $b->cout;
            } elseif ($b->checked_out_at) {
                $cout = substr($b->checked_out_at, 0, 10);
            } elseif ($b->status_code === 'checked_in') {
                $cout = NULL;   // still in-house, no departure -> occupies onward
            } else {
                $cout = date('Y-m-d', strtotime($cin.' +1 day'));   // single night
            }

            foreach ($dates as $dt) {
                if ($dt >= $cin && ($cout === NULL || $dt < $cout)) {
                    $occ[$room_id][$dt] = 1;
                }
            }
        }

        $rooms_data    = array();
        $avail_totals  = array_fill_keys($dates, 0);
        $booked_totals = array_fill_keys($dates, 0);
        $total_rooms   = 0;

        foreach ($rooms as $r) {
            $rid     = (int) $r->id;
            $room_no = $r->room_no;
            $cat_id  = $r->category_id;
            $cat_name = $r->category_name;

            $avail = array();
            $booked = array();
            $occupied_count = 0;

            foreach ($dates as $dt) {
                $bk = $occ[$rid][$dt];
                $av = 1 - $bk;  // 1 available if not occupied, 0 if occupied
                $avail[$dt]  = $av;
                $booked[$dt] = $bk;
                $avail_totals[$dt]  += $av;
                $booked_totals[$dt] += $bk;
                $occupied_count += $bk;
            }

            $total_rooms += 1;

            $rooms_data[] = array(
                'id'        => $rid,
                'room_no'   => $room_no,
                'category_id' => $cat_id,
                'category_name' => $cat_name,
                'avail'     => $avail,
                'booked'    => $booked,
                'occupied_count' => $occupied_count,
            );
        }

        return array(
            'dates'         => $dates,
            'rooms'         => $rooms_data,
            'avail_totals'  => $avail_totals,
            'booked_totals' => $booked_totals,
            'total_rooms'   => $total_rooms,
        );
    }
}
