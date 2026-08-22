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
 * and its status is "occupying" (Room booked / Checked in). Completed stays
 * remain visible on their historical nights as Checked out. A booking with an
 * allotted room (room_id) consumes 1 room of that room's category; otherwise it
 * consumes its room_quantity of its booked category. Cancelled / No-show
 * bookings do not appear, and checked-out rooms are free from the checkout date.
 */
class Inventory_model extends CI_Model
{
    /** Booking statuses (status_master.status_code) shown in inventory. */
    protected $occupying = array('room_booked', 'checked_in', 'checked_out');

    /**
     * Active room categories with their count of active physical rooms.
     * Only categories that actually have rooms are returned.
     *
     * @return array of {category_id, category_name, total_rooms}
     */
    public function categories_with_totals($property_id)
    {
        return $this->db
            ->select('rc.category_id, rc.category_name, COUNT(r.id) AS total_rooms', FALSE)
            ->from('room_categories rc')
            ->join('rooms r', 'r.category_id = rc.category_id AND r.property_id = rc.property_id AND r.is_active = 1', 'left')
            ->where('rc.property_id', (int) $property_id)
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
    public function all_rooms_with_category($property_id, $filters = array())
    {
        $this->db
            ->select('r.id, r.room_no, r.category_id, rc.category_name')
            ->from('rooms r')
            ->join('room_categories rc', 'rc.category_id = r.category_id AND rc.property_id = r.property_id', 'left')
            ->where('r.property_id', (int) $property_id)
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
    public function get_all_categories($property_id)
    {
        return $this->db
            ->select('category_id, category_name')
            ->from('room_categories')
            ->where('property_id', (int) $property_id)
            ->where('status', 1)
            ->order_by('display_order', 'ASC')
            ->order_by('category_name', 'ASC')
            ->get()->result();
    }

    /**
     * All inventory-visible bookings. The per-night overlap and effective stay
     * range are resolved by the caller in PHP.
     *
     * The allotted-room join is guarded by is_active = 1 so it stays consistent
     * with categories_with_totals() (which counts active rooms only): a booking
     * on a de-activated room resolves to a NULL category and is skipped.
     *
     * @return array of {booking_id, room_id, room_category_id, room_quantity, total_unit,
     *                   cin, cout, checked_in_at, checked_out_at, status_code, room_cat}
     */
    public function occupying_bookings($property_id)
    {
        return $this->db
            ->select('b.id AS booking_id, b.room_id, b.room_category_id, b.room_quantity, b.total_unit,
                      b.scheduled_check_in_date AS cin, b.scheduled_check_out_date AS cout,
                      b.checked_in_at, b.checked_out_at, sm.status_code,
                      r.category_id AS room_cat')
            ->from('booking_details b')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id AND r.property_id = b.property_id AND r.is_active = 1', 'left')
            ->where('b.property_id', (int) $property_id)
            ->where_in('sm.status_code', $this->occupying)
            ->get()->result();
    }

    /** True only for a workflow booking assigned to an active inventory room. */
    public function booking_is_inventory_visible($property_id, $booking_id)
    {
        return $this->db
            ->from('booking_details b')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id AND r.property_id = b.property_id AND r.is_active = 1', 'inner')
            ->where('b.property_id', (int) $property_id)
            ->where('b.id', (int) $booking_id)
            ->where_in('sm.status_code', $this->occupying)
            ->count_all_results() > 0;
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
    public function availability($property_id, $start, $days, $filters = array())
    {
        $t0 = strtotime($start);
        $dates = array();
        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('Y-m-d', strtotime('+'.$i.' day', $t0));
        }

        $rooms = $this->all_rooms_with_category($property_id, $filters);

        // state[room_id][date] distinguishes a reservation from an in-house guest.
        // booking_state mirrors the winning status so occupied calendar cells can
        // open the exact booking without changing the availability calculation.
        $state = array();
        $booking_state = array();
        foreach ($rooms as $r) {
            $state[(int) $r->id] = array_fill_keys($dates, 'available');
            $booking_state[(int) $r->id] = array_fill_keys($dates, NULL);
        }

        $status_priority = array(
            'available'   => 0,
            'checked_out' => 1,
            'room_booked' => 2,
            'checked_in'  => 3,
        );

        foreach ($this->occupying_bookings($property_id) as $b) {
            $room_id = $b->room_id;
            if (!$room_id || !isset($state[$room_id])) {
                continue;   // no room assigned or room not active
            }

            // Effective stay range: scheduled dates win; otherwise fall back to
            // the actual check-in/out timestamps, so a checked-in guest with no
            // scheduled dates still occupies their room from check-in until they
            // check out (open-ended while still in-house).
            $cin = $b->cin ?: ($b->checked_in_at ? substr($b->checked_in_at, 0, 10) : NULL);
            if ( ! $cin) {
                // Match the booking guard: an undated live room hold is unsafe
                // to offer, so show it as booked throughout the visible window.
                if (in_array($b->status_code, array('room_booked', 'checked_in'), TRUE)) {
                    foreach ($dates as $dt) {
                        $current_booking_id = (int) $booking_state[$room_id][$dt];
                        if ($status_priority[$b->status_code] > $status_priority[$state[$room_id][$dt]]
                            || ($status_priority[$b->status_code] === $status_priority[$state[$room_id][$dt]]
                                && (int) $b->booking_id > $current_booking_id)) {
                            $state[$room_id][$dt] = $b->status_code;
                            $booking_state[$room_id][$dt] = (int) $b->booking_id;
                        }
                    }
                }
                continue;
            }

            // Scheduled checkout is the exclusive boundary for an active stay:
            // a booking for the 11th checks out on the 12th, so the 12th night
            // can be sold again. Completed history may use its actual checkout.
            if ($b->status_code === 'checked_out' && $b->checked_out_at) {
                $cout = substr($b->checked_out_at, 0, 10);
            } elseif ($b->cout) {
                $cout = $b->cout;
            } elseif ($b->checked_out_at) {
                $cout = substr($b->checked_out_at, 0, 10);
            } elseif ($b->status_code === 'checked_in') {
                $cout = NULL;
            } else {
                $cout = date('Y-m-d', strtotime($cin.' +1 day'));   // single night
            }

            foreach ($dates as $dt) {
                if ($dt >= $cin && ($cout === NULL || $dt < $cout)) {
                    // An active stay takes precedence over completed history if
                    // records overlap for the same room and date.
                    $current_booking_id = (int) $booking_state[$room_id][$dt];
                    if ($status_priority[$b->status_code] > $status_priority[$state[$room_id][$dt]]
                        || ($status_priority[$b->status_code] === $status_priority[$state[$room_id][$dt]]
                            && (int) $b->booking_id > $current_booking_id)) {
                        $state[$room_id][$dt] = $b->status_code;
                        $booking_state[$room_id][$dt] = (int) $b->booking_id;
                    }
                }
            }
        }

        $rooms_data    = array();
        $avail_totals  = array_fill_keys($dates, 0);
        $booked_totals = array_fill_keys($dates, 0);
        $checked_in_totals = array_fill_keys($dates, 0);
        $checked_out_totals = array_fill_keys($dates, 0);
        $total_rooms   = 0;

        foreach ($rooms as $r) {
            $rid     = (int) $r->id;
            $room_no = $r->room_no;
            $cat_id  = $r->category_id;
            $cat_name = $r->category_name;

            $avail = array();
            $booked = array();
            $room_status = array();
            $room_booking_id = array();
            $occupied_count = 0;

            foreach ($dates as $dt) {
                $current_status = $state[$rid][$dt];
                $bk = $current_status === 'available' ? 0 : 1;
                $av = 1 - $bk;  // 1 available if not occupied, 0 if occupied
                $avail[$dt]  = $av;
                $booked[$dt] = $bk;
                $room_status[$dt] = $current_status;
                $room_booking_id[$dt] = $booking_state[$rid][$dt];
                $avail_totals[$dt]  += $av;
                if ($current_status === 'room_booked') {
                    $booked_totals[$dt]++;
                } elseif ($current_status === 'checked_in') {
                    $checked_in_totals[$dt]++;
                } elseif ($current_status === 'checked_out') {
                    $checked_out_totals[$dt]++;
                }
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
                'status'    => $room_status,
                'booking_id' => $room_booking_id,
                'occupied_count' => $occupied_count,
            );
        }

        return array(
            'dates'         => $dates,
            'rooms'         => $rooms_data,
            'avail_totals'  => $avail_totals,
            'booked_totals' => $booked_totals,
            'checked_in_totals' => $checked_in_totals,
            'checked_out_totals' => $checked_out_totals,
            'total_rooms'   => $total_rooms,
        );
    }
}
