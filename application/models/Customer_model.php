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
     *  `booking_details`, so no booking columns here). */
    protected $list_columns = array(
        'id', 'customer_code', 'customer_name', 'phone',
        'pincode', 'country', 'is_active',
    );

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
    public function get_filtered(array $filters = array())
    {
        $this->db->select(implode(',', $this->list_columns));

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

        // Status filter: '1' active, '0' inactive, '' or 'all' => no filter.
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
            $this->db->where('is_active', (int) $filters['status']);
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
    public function get_by_id($id)
    {
        return $this->db
            ->where('id', (int) $id)
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
    public function get_by_phone($phone)
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return NULL;
        }
        return $this->db
            ->where('phone', $phone)
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
    public function distinct_values($column)
    {
        // Whitelist to keep the identifier safe.
        $allowed = array('country');
        if ( ! in_array($column, $allowed, TRUE)) {
            return array();
        }

        $rows = $this->db
            ->distinct()
            ->select($column)
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
    public function room_categories()
    {
        if ( ! $this->db->table_exists('room_categories')) {
            return array();
        }
        return $this->db
            ->select('category_id, category_name')
            ->where('status', 1)
            ->order_by('display_order', 'ASC')->order_by('category_name', 'ASC')
            ->get('room_categories')->result();
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
    public function available_rooms($current_booking_id = NULL, $check_in = NULL, $check_out = NULL)
    {
        if ( ! $this->db->table_exists('rooms')) {
            return array();
        }

        $rooms = $this->db
            ->select('id, room_no, category_id, selling_price')
            ->from('rooms')
            ->where('is_active', 1)
            ->order_by('room_no', 'ASC')
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
            ->join('status_master sm', 'sm.status_id = bd.status_id', 'inner')
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
    public function is_room_available($room_id, $check_in, $check_out, $current_booking_id = NULL)
    {
        if ( ! $room_id) {
            return TRUE;
        }
        foreach ($this->available_rooms($current_booking_id, $check_in, $check_out) as $room) {
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
    public function lock_rooms_for_booking(array $room_ids)
    {
        $room_ids = array_values(array_unique(array_filter(array_map('intval', $room_ids))));
        sort($room_ids, SORT_NUMERIC);
        if (empty($room_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($room_ids), '?'));
        $rows = $this->db->query(
            'SELECT id FROM rooms WHERE is_active = 1 AND id IN ('.$placeholders.') ORDER BY id FOR UPDATE',
            $room_ids
        )->result();

        return array_map(function ($room) { return (int) $room->id; }, $rows);
    }

    /**
     * Locking/current-read version of the overlap guard. Call only after the
     * target room row has been locked inside the same transaction.
     */
    public function is_room_available_for_update($room_id, $check_in, $check_out, $current_booking_id = NULL)
    {
        $room_id = (int) $room_id;
        if ( ! $room_id || ! $this->_valid_stay_range($check_in, $check_out)) {
            return FALSE;
        }

        $sql = 'SELECT bd.id, bd.scheduled_check_in_date AS cin,
                       bd.scheduled_check_out_date AS cout,
                       bd.checked_in_at, bd.checked_out_at, sm.status_code
                  FROM booking_details bd
                  JOIN status_master sm ON sm.status_id = bd.status_id
                 WHERE bd.room_id = ?
                   AND sm.status_code IN (\'room_booked\', \'checked_in\')';
        $params = array($room_id);
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

    /** Serialize MAX+1 customer/booking code generation for booking creates. */
    public function lock_booking_creation_sequence()
    {
        return (bool) $this->db->query(
            'SELECT status_id FROM status_master WHERE status_code = \'room_booked\' LIMIT 1 FOR UPDATE'
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
    public function room_category_for_room($room_id)
    {
        $room = $this->db
            ->select('category_id')
            ->where('id', (int) $room_id)
            ->where('is_active', 1)
            ->get('rooms')
            ->row();

        return $room ? (int) $room->category_id : NULL;
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
    public function get_bookings(array $filters = array())
    {
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
                      sm.status_name, sm.status_code')
            ->select($date_column ? $date_column.' AS stay_date' : 'NULL AS stay_date', FALSE)
            // Room Category: the allotted room's category when a room is assigned,
            // otherwise the category the booking itself booked (room may be pending).
            ->select('COALESCE(r_cat.category_name, b_cat.category_name) AS room_category', FALSE)
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id', 'inner')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id', 'left')
            ->join('room_categories r_cat', 'r_cat.category_id = r.category_id', 'left')
            ->join('room_categories b_cat', 'b_cat.category_id = b.room_category_id', 'left');

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
        if ( ! $this->db->table_exists('status_master')) {
            return array();
        }
        return $this->db
            ->select('status_id, status_code, status_name')
            ->where('is_active', 1)
            ->order_by('display_order', 'ASC')
            ->get('status_master')
            ->result();
    }

    /** status_code for a given status_id (drives the check-in/out auto-stamp). */
    public function status_code($status_id)
    {
        $row = $this->db
            ->select('status_code')
            ->where('status_id', (int) $status_id)
            ->limit(1)
            ->get('status_master')
            ->row();
        return $row ? $row->status_code : NULL;
    }

    /** status_id for a given status_code (e.g. the default 'room_booked'). */
    public function status_id_by_code($code)
    {
        $row = $this->db
            ->select('status_id')
            ->where('status_code', $code)
            ->limit(1)
            ->get('status_master')
            ->row();
        return $row ? (int) $row->status_id : NULL;
    }

    /**
     * The (latest) booking row for a customer, or NULL. Used to prefill the
     * booking section when editing an existing booking.
     */
    public function get_booking($booking_id)
    {
        return $this->db
            ->where('id', (int) $booking_id)
            ->limit(1)
            ->get('booking_details')->row();
    }

    /** Lock and return one booking row inside the caller's transaction. */
    public function get_booking_for_update($booking_id)
    {
        return $this->db->query(
            'SELECT * FROM booking_details WHERE id = ? LIMIT 1 FOR UPDATE',
            array((int) $booking_id)
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
    public function get_booking_detail($booking_id)
    {
        return $this->db
            ->select('b.*,
                      c.customer_code, c.customer_name, c.phone, c.pincode, c.country,
                      r.room_no AS allotted_room_no,
                      sm.status_name, sm.status_code, bc.channel_name')
            ->select('COALESCE(r_cat.category_name, b_cat.category_name) AS room_category', FALSE)
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id', 'inner')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id', 'left')
            ->join('room_categories r_cat', 'r_cat.category_id = r.category_id', 'left')
            ->join('room_categories b_cat', 'b_cat.category_id = b.room_category_id', 'left')
            ->join('booking_channels bc', 'bc.channel_id = b.booking_channel_id', 'left')
            ->where('b.id', (int) $booking_id)
            ->limit(1)
            ->get()->row();
    }

    /** Next human-facing booking number, e.g. BKG00007 (max suffix + 1). */
    public function next_booking_number()
    {
        $row  = $this->db->query('SELECT COALESCE(MAX(CAST(SUBSTRING(booking_number, 4) AS UNSIGNED)), 0) AS maxn FROM booking_details')->row();
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
    public function create_booking($customer_id, array $data)
    {
        $data['customer_id']    = (int) $customer_id;
        $data['booking_number'] = $this->next_booking_number();
        $data['created_at']     = date('Y-m-d H:i:s');
        $this->db->insert('booking_details', $data);
        return (int) $this->db->insert_id();
    }

    /** Update an existing booking (booking_number / customer stay put). */
    public function update_booking($booking_id, array $data)
    {
        unset($data['booking_number'], $data['customer_id'], $data['id']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', (int) $booking_id)->update('booking_details', $data);
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
    public function next_code()
    {
        // Highest numeric suffix + 1 (gap-tolerant AND independent of insert
        // order — codes like CUST00018 may belong to a lower-id row).
        $row  = $this->db->query(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(customer_code, '.(strlen(self::CODE_PREFIX) + 1).') AS UNSIGNED)), 0) AS maxn '
            .'FROM '.$this->table.' WHERE customer_code LIKE '.$this->db->escape(self::CODE_PREFIX.'%')
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
    public function insert(array $data)
    {
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
    public function update($id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $id)
            ->update($this->table, $data);
    }

    /**
     * Delete a customer row.
     *
     * @param  int $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->db->delete($this->table, array('id' => (int) $id));
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
    public function get_identities($customer_id, $booking_id = NULL)
    {
        $this->db
            ->select('id, customer_id, booking_id, identity_type, identity_number, document_path, document_path_2')
            ->where('customer_id', (int) $customer_id);
        if ($booking_id === NULL) {
            $this->db->where('booking_id IS NULL', NULL, FALSE);
        } else {
            $this->db->where('booking_id', (int) $booking_id);
        }
        return $this->db
            ->order_by('id', 'ASC')
            ->get('customer_identities')
            ->result();
    }

    /** A single identity row (used by the secure document stream). */
    public function get_identity($id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->limit(1)
            ->get('customer_identities')
            ->row();
    }

    /** Insert one identity row; returns its new id. */
    public function insert_identity(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('customer_identities', $data);
        return (int) $this->db->insert_id();
    }

    /** Update one identity row (scoped to its customer for safety). */
    public function update_identity($id, $customer_id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $id)
            ->where('customer_id', (int) $customer_id)
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
    public function identities_to_remove($customer_id, array $keep_ids, $booking_id = NULL)
    {
        $this->db->where('customer_id', (int) $customer_id);
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

    /** Delete an identity row by id (scoped to its customer). */
    public function delete_identity($id, $customer_id)
    {
        return $this->db->delete('customer_identities', array(
            'id'          => (int) $id,
            'customer_id' => (int) $customer_id,
        ));
    }
}
