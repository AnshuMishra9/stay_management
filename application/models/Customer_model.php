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
     * Rooms available to allot on a booking: active rooms NOT already
     * allotted to another booking. When editing a booking, the room
     * currently on THAT booking is kept in the list (so it stays selectable).
     *
     * @param  int|null $current_booking_id  booking being edited (excluded)
     * @return array of {id, room_no}
     */
    public function available_rooms($current_booking_id = NULL)
    {
        if ( ! $this->db->table_exists('rooms')) {
            return array();
        }

        // room_ids still held by OTHER active bookings. A room is only "taken"
        // while its booking is live (room_booked / checked_in); once the booking
        // is checked_out / cancelled / no_show the room returns to inventory.
        $this->db->select('bd.room_id')->from('booking_details bd')
            ->join('status_master sm', 'sm.status_id = bd.status_id', 'inner')
            ->where('bd.room_id IS NOT NULL')
            ->where_not_in('sm.status_code', array('checked_out', 'cancelled', 'no_show'));
        if ($current_booking_id) {
            $this->db->where('bd.id !=', (int) $current_booking_id);
        }
        $taken = array_map(function ($r) { return (int) $r->room_id; },
                           $this->db->get()->result());

        $this->db->select('id, room_no')->from('rooms')->where('is_active', 1);
        if ($taken) {
            $this->db->where_not_in('id', $taken);
        }
        return $this->db->order_by('room_no', 'ASC')->get()->result();
    }

    /**
     * Booking Details list — ONLY bookings whose status is "Room booked"
     * (status_master.status_code = 'room_booked'). Columns shown: booking no,
     * customer name, allotted room no + that room's category, and the status.
     *
     * @param  array $filters  Keys: q (free-text on booking no / customer).
     * @return array  rows: id, booking_number, customer_id, customer_code,
     *                customer_name, allotted_room_no, room_category, status_name.
     */
    public function get_bookings(array $filters = array())
    {
        $this->db
            ->select('b.id, b.booking_number, b.customer_id,
                      c.customer_code, c.customer_name,
                      b.room_id, r.room_no AS allotted_room_no,
                      rc.category_name AS room_category,
                      sm.status_name, sm.status_code')
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id', 'inner')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id', 'left')
            ->join('room_categories rc', 'rc.category_id = r.category_id', 'left');

        // Fixed: this page only lists "Room booked" bookings.
        $this->db->where('sm.status_code', 'room_booked');

        // Free-text search across booking number / customer.
        if (isset($filters['q']) && $filters['q'] !== '') {
            $this->db->group_start()
                ->like('b.booking_number', $filters['q'])
                ->or_like('c.customer_name', $filters['q'])
                ->or_like('c.customer_code', $filters['q'])
                ->group_end();
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
                      r.room_no AS allotted_room_no, rc.category_name AS room_category,
                      sm.status_name, sm.status_code, bc.channel_name')
            ->from('booking_details b')
            ->join($this->table.' c', 'c.id = b.customer_id', 'inner')
            ->join('status_master sm', 'sm.status_id = b.status_id', 'inner')
            ->join('rooms r', 'r.id = b.room_id', 'left')
            ->join('room_categories rc', 'rc.category_id = r.category_id', 'left')
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
     * @param  int $customer_id
     * @return array of {id, identity_type, identity_number, document_path}
     */
    public function get_identities($customer_id)
    {
        return $this->db
            ->select('id, identity_type, identity_number, document_path')
            ->where('customer_id', (int) $customer_id)
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
     * @param  int   $customer_id
     * @param  array $keep_ids
     * @return array
     */
    public function identities_to_remove($customer_id, array $keep_ids)
    {
        $this->db->where('customer_id', (int) $customer_id);
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
