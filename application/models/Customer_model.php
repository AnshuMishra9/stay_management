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
        'id', 'customer_code', 'customer_name', 'owner_name', 'phone', 'alt_phone',
        'email', 'customer_type', 'city', 'district', 'state', 'country', 'is_active',
    );

    // ---------------------------------------------------------------------
    //  Reads
    // ---------------------------------------------------------------------

    /**
     * Return customers matching the supplied filters (AND logic).
     *
     * @param  array $filters  Keys: customer_code, name, owner, phone, city,
     *                         district, state, customer_type, status.
     * @return array           Array of row objects (list columns only).
     */
    public function get_filtered(array $filters = array())
    {
        $this->db->select(implode(',', $this->list_columns));

        // Partial (LIKE) text filters.
        $like_map = array(
            'customer_code' => 'customer_code',
            'name'          => 'customer_name',
            'owner'         => 'owner_name',
            'phone'         => 'phone',
            'city'          => 'city',
            'district'      => 'district',
        );
        foreach ($like_map as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $this->db->like($column, $filters[$key]);
            }
        }

        // Exact-match filters.
        if ( ! empty($filters['state'])) {
            $this->db->where('state', $filters['state']);
        }
        if ( ! empty($filters['customer_type'])) {
            $this->db->where('customer_type', $filters['customer_type']);
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
     * Distinct non-empty values of a column, for building filter dropdowns.
     *
     * @param  string $column  Whitelisted column name.
     * @return array
     */
    public function distinct_values($column)
    {
        // Whitelist to keep the identifier safe.
        $allowed = array('customer_type', 'state', 'country', 'city', 'district');
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
     * Booking-centric list — reads from the normalized `booking_details` table
     * (one customer -> many bookings), joined to `customers` so we know WHOSE
     * booking it is, and to `booking_channels` for the channel NAME.
     *
     * @param  array $filters  Keys: q, booking_status, booking_channel_id,
     *                         checkin_from, checkin_to, checkout_from, checkout_to.
     * @return array  rows with `id` (booking id), `booking_number`, `customer_id`,
     *                `customer_code`, `customer_name`, + booking columns.
     */
    public function get_bookings(array $filters = array())
    {
        $this->db
            ->select('b.id, b.booking_number, b.customer_id,
                      c.customer_code, c.customer_name, c.phone,
                      b.guest_name, b.guest_mobile_no, b.booking_status,
                      b.booking_channel_id, bc.channel_name,
                      b.scheduled_check_in_date, b.scheduled_check_out_date,
                      b.checked_in_at, b.checked_out_at, b.length_of_stay,
                      b.total_guest, b.total_amount, b.amount_paid, b.remaining_amount')
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id', 'inner')
            ->join('booking_channels bc', 'bc.channel_id = b.booking_channel_id', 'left');

        // Free-text search across booking number / customer / guest.
        if (isset($filters['q']) && $filters['q'] !== '') {
            $this->db->group_start()
                ->like('b.booking_number', $filters['q'])
                ->or_like('c.customer_name', $filters['q'])
                ->or_like('b.guest_name', $filters['q'])
                ->or_like('c.customer_code', $filters['q'])
                ->group_end();
        }
        if ( ! empty($filters['booking_status'])) {
            $this->db->where('b.booking_status', $filters['booking_status']);
        }
        if ( ! empty($filters['booking_channel_id'])) {
            $this->db->where('b.booking_channel_id', (int) $filters['booking_channel_id']);
        }
        // Scheduled check-in / check-out DATE ranges (inclusive).
        if ( ! empty($filters['checkin_from']))  { $this->db->where('b.scheduled_check_in_date >=',  $filters['checkin_from']); }
        if ( ! empty($filters['checkin_to']))    { $this->db->where('b.scheduled_check_in_date <=',  $filters['checkin_to']); }
        if ( ! empty($filters['checkout_from'])) { $this->db->where('b.scheduled_check_out_date >=', $filters['checkout_from']); }
        if ( ! empty($filters['checkout_to']))   { $this->db->where('b.scheduled_check_out_date <=', $filters['checkout_to']); }

        // Dated bookings first (newest arrival), un-dated rows last.
        return $this->db
            ->order_by('b.scheduled_check_in_date IS NULL ASC, b.scheduled_check_in_date DESC, b.id DESC', '', FALSE)
            ->get()
            ->result();
    }

    /**
     * The (latest) booking row for a customer, or NULL. Used to prefill the
     * booking section of the Add/Edit customer form.
     */
    public function booking_for_customer($customer_id)
    {
        return $this->db
            ->where('customer_id', (int) $customer_id)
            ->order_by('id', 'DESC')->limit(1)
            ->get('booking_details')->row();
    }

    /** Next human-facing booking number, e.g. BKG00007 (max suffix + 1). */
    public function next_booking_number()
    {
        $row  = $this->db->query('SELECT COALESCE(MAX(CAST(SUBSTRING(booking_number, 4) AS UNSIGNED)), 0) AS maxn FROM booking_details')->row();
        $next = ((int) ($row ? $row->maxn : 0)) + 1;
        return 'BKG'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Upsert a customer's booking into booking_details: update their existing
     * (latest) booking if any, otherwise insert a new one with a booking_number.
     *
     * @param  int   $customer_id
     * @param  array $data   booking columns (no id / customer_id / booking_number)
     * @return int   booking id
     */
    public function save_booking($customer_id, array $data)
    {
        $data['customer_id'] = (int) $customer_id;
        $existing = $this->db
            ->select('id')->where('customer_id', (int) $customer_id)
            ->order_by('id', 'DESC')->limit(1)
            ->get('booking_details')->row();

        if ($existing) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $existing->id)->update('booking_details', $data);
            return (int) $existing->id;
        }

        $data['booking_number'] = $this->next_booking_number();
        $data['created_at']     = date('Y-m-d H:i:s');
        $this->db->insert('booking_details', $data);
        return (int) $this->db->insert_id();
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
}
