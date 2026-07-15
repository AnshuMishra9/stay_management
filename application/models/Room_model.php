<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Room_model
 *
 * All database access for the Room Manager (Room Master). Uses CodeIgniter
 * Query Builder throughout (escaped/prepared) — no raw SQL. Mirrors the
 * Customer_model conventions (next_code, get_filtered, get_by_id, writes) and
 * adds the room-specific reads: categories, taxes, amenities (many-to-many).
 */
class Room_model extends CI_Model
{
    /** @var string */
    protected $table = 'rooms';

    /** Prefix used when auto-generating the human-facing room code. */
    const CODE_PREFIX = 'ROOM';

    /** Columns shown in the list grid (kept lean — joined with category name). */
    protected $list_columns = array(
        'r.id', 'r.room_code', 'r.room_no', 'r.category_id',
        'c.category_name', 'r.floor_no', 'r.selling_price',
        'r.housekeeping_status', 'r.is_active', 'r.created_by', 'r.created_at',
    );

    // ---------------------------------------------------------------------
    //  Reads
    // ---------------------------------------------------------------------

    /**
     * Return rooms matching the supplied filters (AND logic), joined with the
     * category name.
     *
     * @param  array $filters  Keys: room_no, category_id, floor_no,
     *                         housekeeping_status, status.
     * @return array           Array of row objects (list columns only).
     */
    public function get_filtered(array $filters = array())
    {
        $this->db
            ->select(implode(',', $this->list_columns))
            ->from($this->table.' r')
            ->join('room_categories c', 'c.category_id = r.category_id', 'left');

        // Partial (LIKE) text filters.
        $like_map = array(
            'room_no'   => 'r.room_no',
            'floor_no'  => 'r.floor_no',
        );
        foreach ($like_map as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $this->db->like($column, $filters[$key]);
            }
        }

        // Exact-match filters.
        if ( ! empty($filters['category_id'])) {
            $this->db->where('r.category_id', (int) $filters['category_id']);
        }
        if ( ! empty($filters['housekeeping_status'])) {
            $this->db->where('r.housekeeping_status', $filters['housekeeping_status']);
        }

        // Status filter: '1' active, '0' inactive, '' or 'all' => no filter.
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
            $this->db->where('r.is_active', (int) $filters['status']);
        }

        return $this->db
            ->order_by('r.id', 'DESC')
            ->get()
            ->result();
    }

    /**
     * Fetch a single room (all columns + category name) by primary key.
     *
     * @param  int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        return $this->db
            ->select('r.*, c.category_name')
            ->from($this->table.' r')
            ->join('room_categories c', 'c.category_id = r.category_id', 'left')
            ->where('r.id', (int) $id)
            ->limit(1)
            ->get()
            ->row();
    }

    /** Active categories for dropdowns / filters. */
    public function all_categories()
    {
        return $this->db
            ->where('status', 1)
            ->order_by('display_order', 'ASC')
            ->order_by('category_name', 'ASC')
            ->get('room_categories')
            ->result();
    }

    /**
     * Distinct non-empty values of a whitelisted column, for filter dropdowns.
     *
     * @param  string $column
     * @return array
     */
    public function distinct_values($column)
    {
        $allowed = array('floor_no', 'housekeeping_status');
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
     * Is a room_no already taken (optionally excluding one id, for edits)?
     *
     * @param  string   $room_no
     * @param  int|null $except_id
     * @return bool
     */
    public function room_no_exists($room_no, $except_id = NULL)
    {
        $this->db->where('room_no', $room_no);
        if ($except_id) {
            $this->db->where('id !=', (int) $except_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    // ---------------------------------------------------------------------
    //  Code generation
    // ---------------------------------------------------------------------

    /**
     * Generate the next sequential room code, e.g. ROOM00007.
     * Based on the highest existing numeric suffix (gap-tolerant).
     *
     * @return string
     */
    public function next_code()
    {
        $row = $this->db
            ->select('room_code')
            ->like('room_code', self::CODE_PREFIX, 'after')
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row();

        $next = 1;
        if ($row && preg_match('/(\d+)$/', $row->room_code, $m)) {
            $next = (int) $m[1] + 1;
        }

        return self::CODE_PREFIX.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------------
    //  Writes  (all wrapped in a transaction by the caller of save())
    // ---------------------------------------------------------------------

    /**
     * Insert a new room.
     *
     * @param  array $data  Room row columns.
     * @return int   New room id.
     */
    public function insert(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    /**
     * Update an existing room.
     *
     * @param  int   $id
     * @param  array $data
     * @return bool
     */
    public function update($id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->where('id', (int) $id)->update($this->table, $data);
    }

    /**
     * Delete a room row.
     *
     * @param  int $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->db->delete($this->table, array('id' => (int) $id));
    }
}
