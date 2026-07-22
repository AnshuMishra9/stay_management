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
     *
     * @param  string $start  Y-m-d
     * @param  int    $days
     * @return array {
     *     dates:        [Y-m-d, …],
     *     rows:         [ {name, total, avail:{date=>n}, booked:{date=>n}}, … ],
     *     avail_totals: {date=>n},   booked_totals:{date=>n},
     *     total_rooms:  int
     * }
     */
    public function availability($start, $days)
    {
        $t0 = strtotime($start);
        $dates = array();
        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('Y-m-d', strtotime('+'.$i.' day', $t0));
        }

        $cats = $this->categories_with_totals();

        // occ[category_id][date] = rooms occupied that night.
        $occ = array();
        foreach ($cats as $c) {
            $occ[(int) $c->category_id] = array_fill_keys($dates, 0);
        }

        foreach ($this->occupying_bookings() as $b) {
            $cat = $b->room_id ? (int) $b->room_cat : (int) $b->room_category_id;
            if ($cat === 0 || ! isset($occ[$cat])) {
                continue;   // no category, or a category with no active rooms
            }
            $qty = $b->room_id ? 1 : (int) ($b->room_quantity ?: ($b->total_unit ?: 1));

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
                    $occ[$cat][$dt] += $qty;
                }
            }
        }

        $rows          = array();
        $avail_totals  = array_fill_keys($dates, 0);
        $booked_totals = array_fill_keys($dates, 0);
        $total_rooms   = 0;

        foreach ($cats as $c) {
            $cid   = (int) $c->category_id;
            $total = (int) $c->total_rooms;
            $avail = array();
            $booked = array();
            foreach ($dates as $dt) {
                $bk = $occ[$cid][$dt];
                $av = max(0, $total - $bk);
                $avail[$dt]  = $av;
                $booked[$dt] = $bk;
                $avail_totals[$dt]  += $av;
                $booked_totals[$dt] += $bk;
            }
            $total_rooms += $total;
            $rows[] = array(
                'name'   => $c->category_name,
                'total'  => $total,
                'avail'  => $avail,
                'booked' => $booked,
            );
        }

        return array(
            'dates'         => $dates,
            'rows'          => $rows,
            'avail_totals'  => $avail_totals,
            'booked_totals' => $booked_totals,
            'total_rooms'   => $total_rooms,
        );
    }
}
