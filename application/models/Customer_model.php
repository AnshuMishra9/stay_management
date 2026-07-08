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

    /** Columns shown in the list grid (kept lean — no Aadhar/PAN here). */
    protected $list_columns = array(
        'id', 'customer_code', 'customer_name', 'owner_name', 'phone', 'alt_phone',
        'email', 'customer_type', 'city', 'district', 'state', 'country', 'is_active',
        'booking_status', 'checked_in_at',
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
        // Booking lifecycle stage (enquiry / confirmed / checked_in / …).
        if ( ! empty($filters['booking_status'])) {
            $this->db->where('booking_status', $filters['booking_status']);
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
     * Booking-centric list — one row per customer, focused on their booking /
     * stay details and filtered by booking-specific criteria. Left-joined to
     * booking_channels so the channel NAME (not just its id) is available.
     *
     * @param  array $filters  Keys: q, booking_status, booking_channel_id,
     *                         checkin_from, checkin_to, checkout_from, checkout_to.
     * @return array
     */
    public function get_bookings(array $filters = array())
    {
        $this->db
            ->select('c.id, c.customer_code, c.customer_name, c.phone,
                      c.guest_name, c.guest_mobile_no, c.booking_status,
                      c.booking_channel_id, bc.channel_name,
                      c.scheduled_check_in_date, c.scheduled_check_out_date,
                      c.checked_in_at, c.checked_out_at, c.length_of_stay,
                      c.total_guest, c.total_amount, c.amount_paid, c.remaining_amount')
            ->from($this->table.' c')
            ->join('booking_channels bc', 'bc.channel_id = c.booking_channel_id', 'left');

        // Free-text search across customer name / guest name / customer code.
        if (isset($filters['q']) && $filters['q'] !== '') {
            $this->db->group_start()
                ->like('c.customer_name', $filters['q'])
                ->or_like('c.guest_name', $filters['q'])
                ->or_like('c.customer_code', $filters['q'])
                ->group_end();
        }
        if ( ! empty($filters['booking_status'])) {
            $this->db->where('c.booking_status', $filters['booking_status']);
        }
        if ( ! empty($filters['booking_channel_id'])) {
            $this->db->where('c.booking_channel_id', (int) $filters['booking_channel_id']);
        }
        // Scheduled check-in / check-out DATE ranges (inclusive).
        if ( ! empty($filters['checkin_from']))  { $this->db->where('c.scheduled_check_in_date >=',  $filters['checkin_from']); }
        if ( ! empty($filters['checkin_to']))    { $this->db->where('c.scheduled_check_in_date <=',  $filters['checkin_to']); }
        if ( ! empty($filters['checkout_from'])) { $this->db->where('c.scheduled_check_out_date >=', $filters['checkout_from']); }
        if ( ! empty($filters['checkout_to']))   { $this->db->where('c.scheduled_check_out_date <=', $filters['checkout_to']); }

        // Dated bookings first (newest arrival), un-dated rows last.
        return $this->db
            ->order_by('c.scheduled_check_in_date IS NULL ASC, c.scheduled_check_in_date DESC, c.id DESC', '', FALSE)
            ->get()
            ->result();
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
        $row = $this->db
            ->select('customer_code')
            ->like('customer_code', self::CODE_PREFIX, 'after')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row();

        $next = 1;
        if ($row && preg_match('/(\d+)$/', $row->customer_code, $m)) {
            $next = (int) $m[1] + 1;
        }

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
