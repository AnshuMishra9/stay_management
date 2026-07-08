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
        'r.id', 'r.room_code', 'r.room_no', 'r.room_name', 'r.category_id',
        'c.category_name', 'r.floor_no', 'r.wing', 'r.bed_type', 'r.max_adults',
        'r.max_children', 'r.selling_price', 'r.housekeeping_status',
        'r.room_condition', 'r.smoking', 'r.is_active', 'r.created_by', 'r.created_at',
    );

    // ---------------------------------------------------------------------
    //  Reads
    // ---------------------------------------------------------------------

    /**
     * Return rooms matching the supplied filters (AND logic), joined with the
     * category name.
     *
     * @param  array $filters  Keys: room_no, room_name, category_id, floor_no,
     *                         wing, housekeeping_status, room_condition,
     *                         smoking, status.
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
            'room_name' => 'r.room_name',
            'floor_no'  => 'r.floor_no',
            'wing'      => 'r.wing',
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
        if ( ! empty($filters['room_condition'])) {
            $this->db->where('r.room_condition', $filters['room_condition']);
        }
        if (isset($filters['smoking']) && $filters['smoking'] !== '') {
            $this->db->where('r.smoking', (int) $filters['smoking']);
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
     * Fetch a single room (all columns + category/tax names + amenity ids)
     * by primary key.
     *
     * @param  int $id
     * @return object|null
     */
    public function get_by_id($id)
    {
        $room = $this->db
            ->select('r.*, c.category_name, t.tax_name, t.tax_percentage')
            ->from($this->table.' r')
            ->join('room_categories c', 'c.category_id = r.category_id', 'left')
            ->join('taxes t', 't.tax_id = r.tax_id', 'left')
            ->where('r.id', (int) $id)
            ->limit(1)
            ->get()
            ->row();

        if ($room) {
            $room->amenity_ids = $this->room_amenity_ids($room->id);
        }
        return $room;
    }

    /**
     * IDs of amenities linked to a room.
     *
     * @param  int $room_id
     * @return array  Array of int amenity ids.
     */
    public function room_amenity_ids($room_id)
    {
        $rows = $this->db
            ->select('amenity_id')
            ->where('room_id', (int) $room_id)
            ->get('room_amenities')
            ->result();

        return array_map(function ($r) { return (int) $r->amenity_id; }, $rows);
    }

    /**
     * Amenity names linked to a room (for the detail modal).
     *
     * @param  int $room_id
     * @return array  Array of {amenity_name, icon} objects.
     */
    public function room_amenities($room_id)
    {
        return $this->db
            ->select('a.amenity_name, a.icon')
            ->from('room_amenities ra')
            ->join('amenities a', 'a.amenity_id = ra.amenity_id')
            ->where('ra.room_id', (int) $room_id)
            ->order_by('a.amenity_name', 'ASC')
            ->get()
            ->result();
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

    /** Active taxes for the pricing dropdown. */
    public function all_taxes()
    {
        return $this->db
            ->where('status', 1)
            ->order_by('tax_percentage', 'ASC')
            ->get('taxes')
            ->result();
    }

    /** Active amenities for the checkbox grid. */
    public function all_amenities()
    {
        return $this->db
            ->where('status', 1)
            ->order_by('amenity_name', 'ASC')
            ->get('amenities')
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
        $allowed = array('floor_no', 'wing', 'housekeeping_status', 'room_condition');
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
     * Insert a new room + its amenity links (single transaction).
     *
     * @param  array $data          Room row columns.
     * @param  array $amenity_ids   Selected amenity ids.
     * @return int   New room id.
     */
    public function insert(array $data, array $amenity_ids = array())
    {
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->db->trans_start();
        $this->db->insert($this->table, $data);
        $id = (int) $this->db->insert_id();
        $this->_sync_amenities($id, $amenity_ids);
        $this->db->trans_complete();

        return $id;
    }

    /**
     * Update an existing room + re-sync its amenity links (single transaction).
     *
     * @param  int   $id
     * @param  array $data
     * @param  array $amenity_ids
     * @return bool  Transaction success.
     */
    public function update($id, array $data, array $amenity_ids = array())
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $this->db->trans_start();
        $this->db->where('id', (int) $id)->update($this->table, $data);
        $this->_sync_amenities((int) $id, $amenity_ids);
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    /**
     * Delete a room row. room_amenities rows cascade via FK.
     *
     * @param  int $id
     * @return bool
     */
    public function delete($id)
    {
        return $this->db->delete($this->table, array('id' => (int) $id));
    }

    /**
     * Replace a room's amenity links with the supplied set.
     *
     * @param int   $room_id
     * @param array $amenity_ids
     */
    private function _sync_amenities($room_id, array $amenity_ids)
    {
        $this->db->delete('room_amenities', array('room_id' => $room_id));

        $rows = array();
        foreach (array_unique(array_map('intval', $amenity_ids)) as $aid) {
            if ($aid > 0) {
                $rows[] = array('room_id' => $room_id, 'amenity_id' => $aid);
            }
        }
        if ($rows) {
            $this->db->insert_batch('room_amenities', $rows);
        }
    }
}
