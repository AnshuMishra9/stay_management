<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Property-scoped persistence for Room Master. */
class Room_model extends CI_Model
{
    protected $table = 'rooms';
    const CODE_PREFIX = 'ROOM';

    protected $list_columns = array(
        'r.id', 'r.property_id', 'r.room_code', 'r.room_no', 'r.category_id',
        'c.category_name', 'r.floor_no', 'r.selling_price',
        'r.housekeeping_status', 'r.status AS is_active', 'r.added_by AS created_by', 'r.created_at',
    );

    /** Translate app-facing keys to dream-style columns before writing. */
    private function translate(array $data)
    {
        if (array_key_exists('is_active', $data)) { $data['status'] = $data['is_active']; unset($data['is_active']); }
        if (array_key_exists('created_by', $data)) { $data['added_by'] = $data['created_by']; unset($data['created_by']); }
        return $data;
    }

    public function get_filtered($property_id, array $filters = array())
    {
        $this->db
            ->select(implode(',', $this->list_columns))
            ->from($this->table.' r')
            ->join('room_categories c', 'c.category_id = r.category_id AND c.property_id = r.property_id', 'left')
            ->where('r.property_id', (int) $property_id);

        foreach (array('room_no' => 'r.room_no', 'floor_no' => 'r.floor_no') as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $this->db->like($column, $filters[$key]);
            }
        }
        if ( ! empty($filters['category_id'])) {
            $this->db->where('r.category_id', (int) $filters['category_id']);
        }
        if ( ! empty($filters['housekeeping_status'])) {
            $this->db->where('r.housekeeping_status', $filters['housekeeping_status']);
        }
        // Status filter: '' => active only (deactivated rooms stay hidden), '0' =>
        // inactive only, 'all' => everything.
        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        if ($status !== 'all') {
            $this->db->where('r.status', $status === '0' ? 0 : 1);
        }
        return $this->db->order_by('r.id', 'DESC')->get()->result();
    }

    public function get_by_id($property_id, $id)
    {
        return $this->db
            ->select('r.*, r.status AS is_active, r.added_by AS created_by, c.category_name')
            ->from($this->table.' r')
            ->join('room_categories c', 'c.category_id = r.category_id AND c.property_id = r.property_id', 'left')
            ->where('r.property_id', (int) $property_id)
            ->where('r.id', (int) $id)
            ->limit(1)->get()->row();
    }

    public function all_categories($property_id, $active_only = TRUE)
    {
        $this->db->where('property_id', (int) $property_id);
        if ($active_only) {
            $this->db->where('status', 1);
        }
        return $this->db
            ->order_by('display_order', 'ASC')
            ->order_by('category_name', 'ASC')
            ->get('room_categories')->result();
    }

    public function category_belongs_to_property($property_id, $category_id, $active_only = TRUE)
    {
        $this->db
            ->where('property_id', (int) $property_id)
            ->where('category_id', (int) $category_id);
        if ($active_only) {
            $this->db->where('status', 1);
        }
        return $this->db->count_all_results('room_categories') === 1;
    }

    public function distinct_values($property_id, $column)
    {
        if ( ! in_array($column, array('floor_no', 'housekeeping_status'), TRUE)) {
            return array();
        }
        $rows = $this->db
            ->distinct()->select($column)
            ->where('property_id', (int) $property_id)
            ->where($column.' !=', '')
            ->where($column.' IS NOT NULL', NULL, FALSE)
            ->order_by($column, 'ASC')
            ->get($this->table)->result();
        return array_map(function ($row) use ($column) { return $row->$column; }, $rows);
    }

    public function room_no_exists($property_id, $room_no, $except_id = NULL)
    {
        $this->db->where('property_id', (int) $property_id)->where('room_no', $room_no)->where('status', 1);
        if ($except_id) {
            $this->db->where('id !=', (int) $except_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function next_code($property_id)
    {
        $row = $this->db
            ->select('room_code')
            ->where('property_id', (int) $property_id)
            ->like('room_code', self::CODE_PREFIX, 'after')
            ->order_by('CAST(SUBSTRING(room_code, 5) AS UNSIGNED)', 'DESC', FALSE)
            ->limit(1)->get($this->table)->row();
        $next = 1;
        if ($row && preg_match('/(\d+)$/', $row->room_code, $match)) {
            $next = (int) $match[1] + 1;
        }
        return self::CODE_PREFIX.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function insert($property_id, array $data)
    {
        $data = $this->translate($data);
        $data['property_id'] = (int) $property_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update($property_id, $id, array $data)
    {
        $data = $this->translate($data);
        unset($data['property_id'], $data['id']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('id', (int) $id)
            ->update($this->table, $data);
    }

    public function has_booking_history($property_id, $id)
    {
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('room_id', (int) $id)
            ->count_all_results('booking_details') > 0;
    }

    /**
     * Soft-delete a room: the row is never removed, only deactivated.
     * Booking history stays fully intact in the database.
     */
    public function delete_or_deactivate($property_id, $id)
    {
        $ok = $this->update($property_id, $id, array('is_active' => 0));
        return $ok ? 'deactivated' : FALSE;
    }
}


