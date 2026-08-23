<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Room_category_model extends CI_Model
{
    protected $table = 'room_categories';

    public function get_all($property_id)
    {
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('status', 1)
            ->order_by('display_order', 'ASC')
            ->order_by('category_name', 'ASC')
            ->get($this->table)->result();
    }

    public function get_by_id($property_id, $id)
    {
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('category_id', (int) $id)
            ->limit(1)->get($this->table)->row();
    }

    public function name_exists($property_id, $name, $except_id = NULL)
    {
        $this->db
            ->where('property_id', (int) $property_id)
            ->where('category_name', $name)
            ->where('status', 1);
        if ($except_id) {
            $this->db->where('category_id !=', (int) $except_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function short_code_exists($property_id, $short_code, $except_id = NULL)
    {
        $short_code = trim((string) $short_code);
        if ($short_code === '') {
            return FALSE;
        }
        $this->db
            ->where('property_id', (int) $property_id)
            ->where('short_code', $short_code)
            ->where('status', 1);
        if ($except_id) {
            $this->db->where('category_id !=', (int) $except_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function taxes()
    {
        return $this->db
            ->select('id AS tax_id, tax_name, rate AS tax_percentage')
            ->where('status', 1)
            ->order_by('rate', 'ASC')
            ->get('gst_rates')->result();
    }

    public function insert($property_id, array $data)
    {
        $data['property_id'] = (int) $property_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update($property_id, $id, array $data)
    {
        unset($data['property_id'], $data['category_id']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('category_id', (int) $id)
            ->update($this->table, $data);
    }

    public function has_history($property_id, $id)
    {
        if ($this->db
            ->where('property_id', (int) $property_id)
            ->where('category_id', (int) $id)
            ->count_all_results('rooms') > 0) {
            return TRUE;
        }
        return $this->db
            ->where('property_id', (int) $property_id)
            ->where('room_category_id', (int) $id)
            ->count_all_results('booking_details') > 0;
    }

    /**
     * Soft-delete a room category: the row is never removed, only deactivated.
     * Rooms/booking history stays fully intact in the database.
     */
    public function delete_or_deactivate($property_id, $id)
    {
        return $this->update($property_id, $id, array('status' => 0))
            ? 'deactivated'
            : FALSE;
    }
}


