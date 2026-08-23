<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customer_model
 *
 * All database access for the Customers Master. Uses CodeIgniter Query
 * Builder throughout (escaped/prepared) — no raw SQL.
 */
class Customer_model extends CI_Model
{
    /** @var string */
    protected $table = 'customers';

    /** Prefix used when auto-generating the human-facing customer code. */
    const CODE_PREFIX = 'CUST';

    /** Columns shown in the list grid (customer info only — bookings live in
     *  `booking_details`, so no booking columns here). Aliased from the
     *  dream-style columns (pin_code/status) to app-facing names. */
    protected $list_columns = array(
        'id', 'customer_code', 'customer_name', 'phone',
        'pin_code AS pincode', 'country', 'status AS is_active',
    );

    /** Translate app-facing keys to dream-style columns before writing. */
    private function translate(array $data)
    {
        if (array_key_exists('tenant_id', $data)) { $data['fk_plant'] = $data['tenant_id']; unset($data['tenant_id']); }
        if (array_key_exists('is_active', $data)) { $data['status'] = $data['is_active']; unset($data['is_active']); }
        if (array_key_exists('pincode', $data)) { $data['pin_code'] = $data['pincode']; unset($data['pincode']); }
        return $data;
    }

    /** booking_details row with app-facing names aliased from dream columns. */
    private function booking_columns($prefix = 'b')
    {
        return "$prefix.id, $prefix.fk_plant AS tenant_id, $prefix.property_id,
                $prefix.booking_number, $prefix.customer_id, $prefix.booking_channel_id,
                $prefix.sd_id AS status_id, $prefix.property_name,
                $prefix.scheduled_check_in_date, $prefix.scheduled_check_out_date,
                $prefix.length_of_stay, $prefix.checked_in_at, $prefix.checked_out_at,
                $prefix.total_guest, $prefix.room_category_id, $prefix.room_id,
                $prefix.room_quantity, $prefix.total_unit, $prefix.total_amount,
                $prefix.amount_paid, $prefix.remaining_amount,
                $prefix.created_at, $prefix.updated_at";
    }

    // ---------------------------------------------------------------------
    //  Reads
    // ---------------------------------------------------------------------

    /**
     * Return customers matching the supplied filters (AND logic).
     *
     * @param  array $filters  Keys: customer_code, name, phone, city,
     *                         district, state, status.
     * @return array           Array of row objects (list columns only).
     */
    public function get_filtered($tenant_id, array $filters = array())
    {
        $tenant_id = (int) $tenant_id;
        if ($tenant_id <= 0) {
            return array();
        }

        $this->db
            ->select(implode(',', $this->list_columns))
            ->where('fk_plant', $tenant_id);

        // Partial (LIKE) text filters.
        $like_map = array(
            'customer_code' => 'customer_code',
            'name'          => 'customer_name',
            'phone'         => 'phone',
        );
        foreach ($like_map as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $this->db->like($column, $filters[$key]);
            }
        }

        // Status filter: '' => Active only (soft-deleted stay hidden), '0' =>
        // inactive only, 'all' => everything.
        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        if ($status !== 'all') {
            $this->db->where('status', $status === '0' ? 0 : 1);
        }

        return $this->db
            ->order_by('id', 'DESC')
            ->get($this->table)
            ->result();
    }

    /**
     * Fetch a single customer (all columns) by primary key.
     *
     * @param  int $id
     * @return object|null
     */
    public function get_by_id($tenant_id, $id)
    {
        if ((int) $id <= 0 || (int) $tenant_id <= 0) {
            return NULL;
        }
        return $this->db
            ->select('id, fk_plant AS tenant_id, customer_code, customer_name, phone,
                      pin_code AS pincode, country, status AS is_active,
                      created_at, updated_at')
            ->where('id', (int) $id)
            ->where('fk_plant', (int) $tenant_id)
            ->limit(1)
            ->get($this->table)
            ->row();
    }

    /**
     * Find a customer by their mobile number — used by the Booking form so
     * typing a known mobile pulls up that customer's saved details.
     *
     * @param  string $phone
     * @return object|null
     */
    public function get_by_phone($tenant_id, $phone)
    {
        $phone = trim((string) $phone);
        if ($phone === '' || (int) $tenant_id <= 0) {
            return NULL;
        }
        return $this->db
            ->select('id, fk_plant AS tenant_id, customer_code, customer_name, phone,
                      pin_code AS pincode, country, status AS is_active, created_at')
            ->where('phone', $phone)
            ->where('fk_plant', (int) $tenant_id)
            ->where('status', 1)
            ->order_by('id', 'DESC')->limit(1)
            ->get($this->table)
            ->row();
    }

    /**
     * Distinct non-empty values of a column, for building filter dropdowns.
     *
     * @param  string $column  Whitelisted column name.
     * @return array
     */
    public function distinct_values($tenant_id, $column)
    {
        // Whitelist to keep the identifier safe.
        $allowed = array('country');
        if ( ! in_array($column, $allowed, TRUE) || (int) $tenant_id <= 0) {
            return array();
        }

        $rows = $this->db
            ->distinct()
            ->select($column)
            ->where('fk_plant', (int) $tenant_id)
            ->where('status', 1)
            ->where($column.' !=', '')
            ->where($column.' IS NOT NULL')
            ->order_by($column, 'ASC')
            ->get($this->table)
            ->result();

        return array_map(function ($r) use ($column) {
            return $r->$column;
        }, $rows);
    }

    /**
     * All active states from the `state_details` master, alphabetically.
     * Used to populate the searchable State picker on the customer form.
     *
     * @return array  Array of state-name strings.
     */
    public function all_states()
    {
        if ( ! $this->db->table_exists('state_details')) {
            return array();
        }

        $rows = $this->db
            ->select('state_name')
            ->where('state_status', 1)
            ->order_by('state_name', 'ASC')
            ->get('state_details')
            ->result();

        return array_map(function ($r) { return $r->state_name; }, $rows);
    }

    /**
     * Active booking channels (Walk-in, MMT, Booking.com…) for the dropdown.
     * @return array of {channel_id, channel_name, channel_category}
     */
    public function booking_channels()
    {
        if ( ! $this->db->table_exists('booking_channels')) {
            return array();
        }
        return $this->db
            ->select('channel_id, channel_name, channel_category')
            ->where('status', 1)
            ->order_by('channel_name', 'ASC')
            ->get('booking_channels')->result();
    }

    /**
     * Active room categories for the "Room Category" dropdown.
     * @return array of {category_id, category_name}
     */
    public function room_categories($property_id)
    {
        if ( ! $this->db->table_exists('room_categories') || (int) $property_id <= 0) {
            return array();
        }
        return $this->db
            ->select('category_id, category_name')
            ->where('property_id', (int) $property_id)
            ->where('status', 1)
            ->order_by('display_order', 'ASC')->order_by('category_name', 'ASC')
            ->get('room_categories')->result();
    }

    public function room_category_belongs_to_property($property_id, $category_id)
    {
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('category_id', (int) $category_id)
            ->where('status', 1)
            ->count_all_results('room_categories') === 1;
    }

    public function active_booking_channel_exists($channel_id)
    {
        return $this->db
            ->where('channel_id', (int) $channel_id)
            ->where('status', 1)
            ->count_all_results('booking_channels') === 1;
    }

    /**
     * Active rooms available for the requested stay [check-in, check-out).
     *
     * A room is excluded only when another Room Booked / Checked In booking
     * overlaps the requested nights. This allows the same physical room to be
     * selected for a non-overlapping past or future stay. The current booking
     * is excluded while editing so its allotted room remains selectable.
     *
     * When the manual form has no API-supplied stay range, availability is
     * evaluated for the current night (today through tomorrow).
     *
     * @param  int|null $current_booking_id booking being edited (excluded)
     * @param  string|null $check_in         Y-m-d (inclusive)
     * @param  string|null $check_out        Y-m-d (exclusive)
     * @return array of {id, room_no, category_id, selling_price}
     */
    public function available_rooms($tenant_id, $property_id, $current_booking_id = NULL, $check_in = NULL, $check_out = NULL)
    {
        $property_id = (int) $property_id;
        $tenant_id = (int) $tenant_id;
        if ( ! $this->db->table_exists('rooms') || $property_id <= 0 || $tenant_id <= 0) {
            return array();
        }

        $rooms = $this->db
            ->select('r.id, r.room_no, r.category_id, r.selling_price')
            ->from('rooms r')
            ->join('properties p', 'p.property_id = r.property_id', 'inner')
            ->where('r.property_id', $property_id)
            ->where('p.fk_plant', $tenant_id)
            ->where('r.status', 1)
            ->order_by('r.room_no', 'ASC')
            ->get()->result();

        $start = is_string($check_in) ? DateTime::createFromFormat('!Y-m-d', $check_in) : FALSE;
        $end = is_string($check_out) ? DateTime::createFromFormat('!Y-m-d', $check_out) : FALSE;
        if (
            ! $start || $start->format('Y-m-d') !== $check_in
            || ! $end || $end->format('Y-m-d') !== $check_out
            || $check_out <= $check_in
        ) {
            $check_in = date('Y-m-d');
            $check_out = date('Y-m-d', strtotime('+1 day'));
        }

        // Fetch other live room holds and resolve their effective stay ranges
        // exactly like Inventory_model: scheduled dates first, actual stamps
        // as fallback, and an open end for a checked-in guest without checkout.
        $this->db
            ->select('bd.id, bd.room_id, bd.scheduled_check_in_date AS cin,
                      bd.scheduled_check_out_date AS cout,
                      bd.checked_in_at, bd.checked_out_at, sm.status_code')
            ->from('booking_details bd')
            ->join('status_details sm', 'sm.sd_id = bd.sd_id', 'inner')
            ->where('bd.fk_plant', $tenant_id)
            ->where('bd.property_id', $property_id)
            ->where('bd.room_id IS NOT NULL')
            ->where_in('sm.status_code', array('room_booked', 'checked_in'));
        if ($current_booking_id) {
            $this->db->where('bd.id !=', (int) $current_booking_id);
        }

        $taken = array();
        foreach ($this->db->get()->result() as $booking) {
            $cin = $booking->cin
                ?: ($booking->checked_in_at ? substr($booking->checked_in_at, 0, 10) : NULL);

            // An undated live hold cannot safely be offered for a dated stay.
            if ( ! $cin) {
                $taken[(int) $booking->room_id] = TRUE;
                continue;
            }

            if ($booking->cout) {
                $cout = $booking->cout;
            } elseif ($booking->checked_out_at) {
                $cout = substr($booking->checked_out_at, 0, 10);
            } elseif ($booking->status_code === 'checked_in') {
                $cout = NULL;
            } else {
                $cout = date('Y-m-d', strtotime($cin.' +1 day'));
            }

            // Half-open date ranges overlap when each starts before the other ends.
            if ($cin < $check_out && ($cout === NULL || $cout > $check_in)) {
                $taken[(int) $booking->room_id] = TRUE;
            }
        }

        return array_values(array_filter($rooms, function ($room) use ($taken) {
            return ! isset($taken[(int) $room->id]);
        }));
    }

    /** Server-side guard against overlapping room allotments. */
    public function is_room_available($tenant_id, $property_id, $room_id, $check_in, $check_out, $current_booking_id = NULL)
    {
        if ( ! $room_id) {
            return TRUE;
        }
        foreach ($this->available_rooms($tenant_id, $property_id, $current_booking_id, $check_in, $check_out) as $room) {
            if ((int) $room->id === (int) $room_id) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Lock active room rows in a stable order for an atomic availability check.
     * Every booking writer that uses this protocol serializes on the physical
     * room before checking and inserting an overlapping stay.
     *
     * @return array locked active room ids
     */
    public function lock_rooms_for_booking($tenant_id, $property_id, array $room_ids)
    {
        $property_id = (int) $property_id;
        $tenant_id = (int) $tenant_id;
        $room_ids = array_values(array_unique(array_filter(array_map('intval', $room_ids))));
        sort($room_ids, SORT_NUMERIC);
        if ($property_id <= 0 || $tenant_id <= 0 || empty($room_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($room_ids), '?'));
        $params = array_merge(array($property_id, $tenant_id), $room_ids);
        $rows = $this->db->query(
            'SELECT r.id FROM rooms r '
            .'JOIN properties p ON p.property_id = r.property_id '
            .'WHERE r.property_id = ? AND p.fk_plant = ? AND r.status = 1 '
            .'AND r.id IN ('.$placeholders.') ORDER BY r.id FOR UPDATE',
            $params
        )->result();

        return array_map(function ($room) { return (int) $room->id; }, $rows);
    }

    /**
     * Locking/current-read version of the overlap guard. Call only after the
     * target room row has been locked inside the same transaction.
     */
    public function is_room_available_for_update($tenant_id, $property_id, $room_id, $check_in, $check_out, $current_booking_id = NULL)
    {
        $property_id = (int) $property_id;
        $tenant_id = (int) $tenant_id;
        $room_id = (int) $room_id;
        if ($property_id <= 0 || $tenant_id <= 0 || ! $room_id || ! $this->_valid_stay_range($check_in, $check_out)) {
            return FALSE;
        }

        $sql = 'SELECT bd.id, bd.scheduled_check_in_date AS cin,
                       bd.scheduled_check_out_date AS cout,
                       bd.checked_in_at, bd.checked_out_at, sm.status_code
                  FROM booking_details bd
                  JOIN status_details sm ON sm.sd_id = bd.sd_id
                 WHERE bd.room_id = ?
                   AND bd.property_id = ?
                   AND bd.fk_plant = ?
                   AND sm.status_code IN (\'room_booked\', \'checked_in\')';
        $params = array($room_id, $property_id, $tenant_id);
        if ($current_booking_id) {
            $sql .= ' AND bd.id != ?';
            $params[] = (int) $current_booking_id;
        }
        $sql .= ' FOR UPDATE';

        foreach ($this->db->query($sql, $params)->result() as $booking) {
            $cin = $booking->cin
                ?: ($booking->checked_in_at ? substr($booking->checked_in_at, 0, 10) : NULL);
            if ( ! $cin) {
                return FALSE; // undated live holds are conservatively blocking
            }

            if ($booking->cout) {
                $cout = $booking->cout;
            } elseif ($booking->checked_out_at) {
                $cout = substr($booking->checked_out_at, 0, 10);
            } elseif ($booking->status_code === 'checked_in') {
                $cout = NULL;
            } else {
                $cout = date('Y-m-d', strtotime($cin.' +1 day'));
            }

            // Half-open intervals overlap only when both strict comparisons hold.
            if ($cin < $check_out && ($cout === NULL || $cout > $check_in)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /** Serialize tenant-scoped customer code and phone creation checks. */
    public function lock_customer_creation_sequence($tenant_id)
    {
        $tenant_id = (int) $tenant_id;
        if ($tenant_id <= 0) {
            return FALSE;
        }

        return (bool) $this->db->query(
            'SELECT plant_id FROM plants WHERE plant_id = ? AND plant_status = 1 LIMIT 1 FOR UPDATE',
            array($tenant_id)
        )->row();
    }

    /** Serialize MAX+1 customer/booking code generation for booking creates. */
    public function lock_booking_creation_sequence($tenant_id, $property_id)
    {
        $tenant_id = (int) $tenant_id;
        $property_id = (int) $property_id;
        if ($property_id <= 0 || $tenant_id <= 0) {
            return FALSE;
        }

        // Customer codes are tenant-scoped while booking numbers are
        // property-scoped. Lock in the same broad-to-narrow order for every
        // create so two properties in one tenant cannot generate the same
        // customer code concurrently.
        if ( ! $this->lock_customer_creation_sequence($tenant_id)) {
            return FALSE;
        }

        return (bool) $this->db->query(
            'SELECT property_id FROM properties WHERE property_id = ? AND fk_plant = ? AND status = 1 LIMIT 1 FOR UPDATE',
            array($property_id, $tenant_id)
        )->row();
    }

    /** Validate a strict real-date [check-in, check-out) range. */
    private function _valid_stay_range($check_in, $check_out)
    {
        $start = is_string($check_in) ? DateTime::createFromFormat('!Y-m-d', $check_in) : FALSE;
        $end = is_string($check_out) ? DateTime::createFromFormat('!Y-m-d', $check_out) : FALSE;
        return $start && $start->format('Y-m-d') === $check_in
            && $end && $end->format('Y-m-d') === $check_out
            && $check_out > $check_in;
    }

    /** Return the category of an active room, or NULL for an invalid room. */
    public function room_category_for_room($property_id, $room_id)
    {
        $room = $this->db
            ->select('category_id')
            ->where('id', (int) $room_id)
            ->where('property_id', (int) $property_id)
            ->where('status', 1)
            ->get('rooms')
            ->row();

        return $room && $room->category_id !== NULL
            ? (int) $room->category_id
            : NULL;
    }

    /**
     * Booking list filtered to a single status (status_master.status_code).
     * Used by BOTH the "Booking Details" page (room_booked, the default) and
     * the "Check-in Details" page (checked_in). Columns shown: booking no,
     * customer name, allotted room no + that room's category, and the status.
     *
     * @param  array $filters  Keys: status (status_code; defaults to
     *                         'room_booked'), booking_no, customer_name,
     *                         room_no, room_category (all partial/LIKE), date
     *                         (exact actual check-in/check-out date).
     * @return array  rows: id, booking_number, customer_id, customer_code,
     *                customer_name, allotted_room_no, room_category, stay_date,
     *                status_name.
     */
    public function get_bookings($tenant_id, $property_id, array $filters = array())
    {
        $property_id = (int) $property_id;
        $tenant_id = (int) $tenant_id;
        if ($property_id <= 0 || $tenant_id <= 0) {
            return array();
        }
        $status = ! empty($filters['status']) ? $filters['status'] : 'room_booked';
        $date_column = NULL;
        if ($status === 'checked_in') {
            $date_column = 'b.checked_in_at';
        } elseif ($status === 'checked_out') {
            $date_column = 'b.checked_out_at';
        }

        $this->db
            ->select('b.id, b.booking_number, b.customer_id,
                      c.customer_code, c.customer_name,
                      b.room_id, r.room_no AS allotted_room_no,
                      sm.sd_name AS status_name, sm.status_code')
            ->select($date_column ? $date_column.' AS stay_date' : 'NULL AS stay_date', FALSE)
            // Room Category: the allotted room's category when a room is assigned,
            // otherwise the category the booking itself booked (room may be pending).
            ->select('COALESCE(r_cat.category_name, b_cat.category_name) AS room_category', FALSE)
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id AND c.fk_plant = b.fk_plant', 'inner')
            ->join('status_details sm', 'sm.sd_id = b.sd_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id AND r.property_id = b.property_id', 'left')
            ->join('room_categories r_cat', 'r_cat.category_id = r.category_id AND r_cat.property_id = b.property_id', 'left')
            ->join('room_categories b_cat', 'b_cat.category_id = b.room_category_id AND b_cat.property_id = b.property_id', 'left')
            ->where('b.property_id', $property_id)
            ->where('b.fk_plant', $tenant_id);

        // Filter to a single status (defaults to "Room booked").
        $this->db->where('sm.status_code', $status);

        // Per-column LIKE filters.
        if ( ! empty($filters['booking_no']))    { $this->db->like('b.booking_number', $filters['booking_no']); }
        if ( ! empty($filters['customer_name'])) { $this->db->like('c.customer_name', $filters['customer_name']); }
        if ( ! empty($filters['room_no']))       { $this->db->like('r.room_no', $filters['room_no']); }
        // Room Category matches the displayed value (allotted room's category,
        // else the booked category) — filter on the same COALESCE expression.
        if ( ! empty($filters['room_category'])) {
            $needle = $this->db->escape('%'.$filters['room_category'].'%');
            $this->db->where("COALESCE(r_cat.category_name, b_cat.category_name) LIKE $needle", NULL, FALSE);
        }
        // Match the complete calendar day without applying DATE() to the DB
        // column, so the query can still use an index when one is available.
        if ($date_column && ! empty($filters['date'])) {
            $date = $filters['date'];
            $next_date = date('Y-m-d', strtotime($date.' +1 day'));
            $this->db
                ->where($date_column.' >=', $date.' 00:00:00')
                ->where($date_column.' <', $next_date.' 00:00:00');
        }

        return $this->db
            ->order_by('b.id', 'DESC')
            ->get()
            ->result();
    }

    /**
     * All active booking statuses from status_master (for the form dropdown /
     * badges). status_master is the single source of truth for statuses.
     *
     * @return array of {status_id, status_code, status_name}
     */
    public function all_statuses()
    {
        if ( ! $this->db->table_exists('status_details')) {
            return array();
        }
        return $this->db
            ->select('sd_id AS status_id, status_code, sd_name AS status_name')
            ->where('sd_status', 1)
            ->order_by('display_order', 'ASC')
            ->get('status_details')
            ->result();
    }

    /** status_code for a given status_id (drives the check-in/out auto-stamp). */
    public function status_code($status_id)
    {
        $row = $this->db
            ->select('status_code')
            ->where('sd_id', (int) $status_id)
            ->limit(1)
            ->get('status_details')
            ->row();
        return $row ? $row->status_code : NULL;
    }

    /** status_id for a given status_code (e.g. the default 'room_booked'). */
    public function status_id_by_code($code)
    {
        $row = $this->db
            ->select('sd_id AS status_id')
            ->where('status_code', $code)
            ->limit(1)
            ->get('status_details')
            ->row();
        return $row ? (int) $row->status_id : NULL;
    }

    /**
     * The (latest) booking row for a customer, or NULL. Used to prefill the
     * booking section when editing an existing booking.
     */
    public function get_booking($tenant_id, $property_id, $booking_id)
    {
        if ((int) $booking_id <= 0 || (int) $property_id <= 0 || (int) $tenant_id <= 0) {
            return NULL;
        }
        return $this->db
            ->select($this->booking_columns())
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id AND c.fk_plant = b.fk_plant', 'inner')
            ->where('b.id', (int) $booking_id)
            ->where('b.property_id', (int) $property_id)
            ->where('b.fk_plant', (int) $tenant_id)
            ->limit(1)
            ->get()->row();
    }

    /** Lock and return one booking row inside the caller's transaction. */
    public function get_booking_for_update($tenant_id, $property_id, $booking_id)
    {
        if ((int) $booking_id <= 0 || (int) $property_id <= 0 || (int) $tenant_id <= 0) {
            return NULL;
        }
        return $this->db->query(
            'SELECT '.$this->booking_columns().' FROM booking_details b '
            .'WHERE b.id = ? AND b.property_id = ? AND b.fk_plant = ? LIMIT 1 FOR UPDATE',
            array((int) $booking_id, (int) $property_id, (int) $tenant_id)
        )->row();
    }

    /**
     * Full booking detail for the View modal: the booking row joined to its
     * customer, allotted room + that room's category, status (status_master)
     * and channel. Identity proofs are fetched separately by the caller.
     *
     * @param  int $booking_id
     * @return object|null
     */
    public function get_booking_detail($tenant_id, $property_id, $booking_id)
    {
        if ((int) $booking_id <= 0 || (int) $property_id <= 0 || (int) $tenant_id <= 0) {
            return NULL;
        }
        return $this->db
            ->select($this->booking_columns().',
                      c.customer_code, c.customer_name, c.phone, c.pin_code AS pincode, c.country,
                      r.room_no AS allotted_room_no,
                      sm.sd_name AS status_name, sm.status_code, bc.channel_name')
            ->select('COALESCE(r_cat.category_name, b_cat.category_name) AS room_category', FALSE)
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id AND c.fk_plant = b.fk_plant', 'inner')
            ->join('status_details sm', 'sm.sd_id = b.sd_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id AND r.property_id = b.property_id', 'left')
            ->join('room_categories r_cat', 'r_cat.category_id = r.category_id AND r_cat.property_id = b.property_id', 'left')
            ->join('room_categories b_cat', 'b_cat.category_id = b.room_category_id AND b_cat.property_id = b.property_id', 'left')
            ->join('booking_channels bc', 'bc.channel_id = b.booking_channel_id', 'left')
            ->where('b.id', (int) $booking_id)
            ->where('b.property_id', (int) $property_id)
            ->where('b.fk_plant', (int) $tenant_id)
            ->limit(1)
            ->get()->row();
    }

    /** Next human-facing booking number, e.g. BKG00007 (max suffix + 1). */
    public function next_booking_number($tenant_id, $property_id)
    {
        $row  = $this->db->query(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(booking_number, 4) AS UNSIGNED)), 0) AS maxn '
            .'FROM booking_details WHERE property_id = ? AND fk_plant = ?',
            array((int) $property_id, (int) $tenant_id)
        )->row();
        $next = ((int) ($row ? $row->maxn : 0)) + 1;
        return 'BKG'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a NEW booking for a customer (a customer can have many).
     *
     * @param  int   $customer_id
     * @param  array $data  booking columns
     * @return int   new booking id
     */
    public function create_booking($tenant_id, $property_id, $customer_id, array $data)
    {
        $customer = $this->get_by_id($tenant_id, $customer_id);
        if ( ! $customer || (int) $property_id <= 0) {
            return 0;
        }
        // App-facing keys -> dream-style columns
        if (array_key_exists('status_id', $data)) { $data['sd_id'] = $data['status_id']; unset($data['status_id']); }
        if (array_key_exists('tenant_id', $data)) { $data['fk_plant'] = $data['tenant_id']; unset($data['tenant_id']); }
        unset($data['property_id'], $data['customer_id'], $data['booking_number'], $data['id']);
        $data['customer_id']    = (int) $customer_id;
        $data['fk_plant']       = (int) $tenant_id;
        $data['property_id']    = (int) $property_id;
        $data['booking_number'] = $this->next_booking_number($tenant_id, $property_id);
        $data['created_at']     = date('Y-m-d H:i:s');
        $this->db->insert('booking_details', $data);
        return (int) $this->db->insert_id();
    }

    /** Update an existing booking (booking_number / customer stay put). */
    public function update_booking($tenant_id, $property_id, $booking_id, array $data)
    {
        if (array_key_exists('status_id', $data)) { $data['sd_id'] = $data['status_id']; unset($data['status_id']); }
        unset($data['booking_number'], $data['customer_id'], $data['tenant_id'], $data['fk_plant'], $data['property_id'], $data['id']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $booking_id)
            ->where('property_id', (int) $property_id)
            ->where('fk_plant', (int) $tenant_id)
            ->update('booking_details', $data);
    }

    // ---------------------------------------------------------------------
    //  Code generation
    // ---------------------------------------------------------------------

    /**
     * Generate the next sequential customer code, e.g. CUST00007.
     * Based on the highest existing numeric suffix (gap-tolerant).
     *
     * @return string
     */
    public function next_code($tenant_id)
    {
        if ((int) $tenant_id <= 0) {
            return self::CODE_PREFIX.'00001';
        }
        // Highest numeric suffix + 1 (gap-tolerant AND independent of insert
        // order — codes like CUST00018 may belong to a lower-id row).
        $row  = $this->db->query(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, '.(strlen(self::CODE_PREFIX) + 1).') AS UNSIGNED)), 0) AS maxn '
            .'FROM '.$this->table.' WHERE fk_plant = ? AND customer_code LIKE '.$this->db->escape(self::CODE_PREFIX.'%'),
            array((int) $tenant_id)
        )->row();
        $next = ((int) ($row ? $row->maxn : 0)) + 1;

        return self::CODE_PREFIX.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------------
    //  Writes
    // ---------------------------------------------------------------------

    /**
     * Insert a new customer.
     *
     * @param  array $data
     * @return int   New row id.
     */
    public function insert($tenant_id, array $data)
    {
        if ((int) $tenant_id <= 0) {
            return 0;
        }
        $data = $this->translate($data);
        unset($data['id']);
        $data['fk_plant'] = (int) $tenant_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    /**
     * Update an existing customer.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function update($tenant_id, $id, array $data)
    {
        $data = $this->translate($data);
        unset($data['id']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $id)
            ->where('fk_plant', (int) $tenant_id)
            ->update($this->table, $data);
    }

    /**
     * Soft-delete a customer: the row is never removed, only deactivated.
     * History (bookings/identities) stays fully intact in the database.
     */
    public function delete($tenant_id, $id)
    {
        return $this->db->update($this->table, array(
            'status'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ), array(
            'id' => (int) $id,
            'fk_plant' => (int) $tenant_id,
        ));
    }

    /** A shared customer with any booking or identity record is history-bearing. */
    public function has_history($tenant_id, $id)
    {
        $id = (int) $id;
        $tenant_id = (int) $tenant_id;
        if ($id <= 0 || $tenant_id <= 0) {
            return FALSE;
        }

        $booking = $this->db
            ->select('id')
            ->where('customer_id', $id)
            ->where('fk_plant', $tenant_id)
            ->limit(1)
            ->get('booking_details')
            ->row();
        if ($booking) {
            return TRUE;
        }

        return (bool) $this->db
            ->select('id')
            ->where('customer_id', $id)
            ->where('fk_plant', $tenant_id)
            ->limit(1)
            ->get('customer_identities')
            ->row();
    }

    // ---------------------------------------------------------------------
    //  Identity proofs  (customer_identities — one customer -> many)
    // ---------------------------------------------------------------------

    /**
     * All identity-proof rows for a customer (Aadhar / PAN / Passport / …).
     *
     * @param  int      $customer_id
     * @param  int|null $booking_id NULL means customer-level documents only
     * @return array of identity rows including front/back document paths
     */
    public function get_identities($tenant_id, $property_id, $customer_id, $booking_id = NULL)
    {
        $this->db
            ->select('ci.id, ci.customer_id, ci.booking_id, ci.fk_plant AS tenant_id, ci.property_id,
                      ci.identity_type, ci.identity_number, ci.document_path, ci.document_path_2')
            ->from('customer_identities ci')
            ->join($this->table.' c', 'c.id = ci.customer_id AND c.fk_plant = ci.fk_plant', 'inner')
            ->where('ci.customer_id', (int) $customer_id)
            ->where('ci.fk_plant', (int) $tenant_id)
            ->where('ci.property_id', (int) $property_id)
            ->where('ci.status', 1);
        if ($booking_id === NULL) {
            $this->db->where('ci.booking_id IS NULL', NULL, FALSE);
        } else {
            $this->db->where('ci.booking_id', (int) $booking_id);
        }
        return $this->db
            ->order_by('ci.id', 'ASC')
            ->get()
            ->result();
    }

    /** A single identity row (used by the secure document stream). */
    public function get_identity($tenant_id, $property_id, $id)
    {
        return $this->db
            ->select('ci.*')
            ->from('customer_identities ci')
            ->join($this->table.' c', 'c.id = ci.customer_id AND c.fk_plant = ci.fk_plant', 'inner')
            ->where('ci.id', (int) $id)
            ->where('ci.fk_plant', (int) $tenant_id)
            ->where('ci.property_id', (int) $property_id)
            ->where('ci.status', 1)
            ->limit(1)
            ->get()
            ->row();
    }

    /** Insert one identity row; returns its new id. */
    public function insert_identity($tenant_id, $property_id, array $data)
    {
        $customer_id = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        if ( ! $this->get_by_id($tenant_id, $customer_id) || (int) $property_id <= 0) {
            return 0;
        }
        $data = $this->translate($data);
        unset($data['property_id'], $data['id']);
        $data['fk_plant'] = (int) $tenant_id;
        $data['property_id'] = (int) $property_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('customer_identities', $data);
        return (int) $this->db->insert_id();
    }

    /** Update one identity row (scoped to its customer for safety). */
    public function update_identity($tenant_id, $property_id, $id, $customer_id, array $data)
    {
        $data = $this->translate($data);
        unset($data['id'], $data['customer_id'], $data['property_id'], $data['booking_id']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $id)
            ->where('customer_id', (int) $customer_id)
            ->where('fk_plant', (int) $tenant_id)
            ->where('property_id', (int) $property_id)
            ->update('customer_identities', $data);
    }

    /**
     * Identity rows for a customer that are NOT in the kept-id list — i.e. the
     * ones removed on the form. Returned so the caller can delete their files
     * before the rows go.
     *
     * @param  int      $customer_id
     * @param  array    $keep_ids
     * @param  int|null $booking_id NULL scopes removal to customer-level rows
     * @return array
     */
    /**
     * Identity rows for a customer that are NOT in the kept-id list — i.e. the
     * ones removed on the form (active rows only). Returned so the caller can
     * soft-delete them; files are never removed from disk.
     */
    public function identities_to_remove($tenant_id, $property_id, $customer_id, array $keep_ids, $booking_id = NULL)
    {
        $this->db
            ->where('customer_id', (int) $customer_id)
            ->where('fk_plant', (int) $tenant_id)
            ->where('property_id', (int) $property_id)
            ->where('status', 1);
        if ($booking_id === NULL) {
            $this->db->where('booking_id IS NULL', NULL, FALSE);
        } else {
            $this->db->where('booking_id', (int) $booking_id);
        }
        $keep = array_filter(array_map('intval', $keep_ids));
        if ($keep) {
            $this->db->where_not_in('id', $keep);
        }
        return $this->db->get('customer_identities')->result();
    }

    /**
     * Soft-delete an identity row (status = 0). The row and its document file
     * are never removed from the database/disk.
     */
    public function delete_identity($tenant_id, $property_id, $id, $customer_id)
    {
        return $this->db->update('customer_identities', array(
            'status'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ), array(
            'id'          => (int) $id,
            'customer_id' => (int) $customer_id,
            'fk_plant'    => (int) $tenant_id,
            'property_id' => (int) $property_id,
        ));
    }
}
