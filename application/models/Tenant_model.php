<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Plant/account persistence (ex-tenants). Each admin owns exactly one plant.
 *  DB columns are dream-aligned (plants.plant_id/plant_name/plant_status/
 *  plant_ad_by/plant_ad_dt); SELECT aliases keep app-facing names stable. */
class Tenant_model extends CI_Model
{
    protected $table = 'plants';

    public function get_by_id($tenant_id, $active_only = FALSE)
    {
        $this->db->select('plant_id AS id, plant_name AS name, plant_status AS is_active, updated_at');
        $this->db->where('plant_id', (int) $tenant_id);
        if ($active_only) {
            $this->db->where('plant_status', 1);
        }
        return $this->db->limit(1)->get($this->table)->row();
    }

    /** Active plants paired with their active admin, for support selectors. */
    public function list_active_admin_tenants()
    {
        return $this->db
            ->select('t.plant_id AS id, t.plant_name AS name, a.user_id AS admin_id, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' t')
            ->join('users a', 'a.fk_plant = t.plant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'inner')
            ->where('t.plant_status', 1)
            ->where('a.status', 1)
            ->order_by('t.plant_name', 'ASC')
            ->order_by('a.name', 'ASC')
            ->get()->result();
    }

    public function get_active_admin_tenant($tenant_id)
    {
        return $this->db
            ->select('t.plant_id AS id, t.plant_name AS name, a.user_id AS admin_id, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' t')
            ->join('users a', 'a.fk_plant = t.plant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'inner')
            ->where('t.plant_id', (int) $tenant_id)
            ->where('t.plant_status', 1)
            ->where('a.status', 1)
            ->limit(1)
            ->get()->row();
    }

    /** Plant paired with its owner admin, including inactive support cases. */
    public function get_admin_tenant($tenant_id)
    {
        return $this->db
            ->select('t.plant_id AS id, t.plant_name AS name, t.plant_status AS is_active,
                      a.user_id AS admin_id, a.name AS admin_name,
                      a.mobile_no AS admin_mobile, a.status AS admin_is_active')
            ->from($this->table.' t')
            ->join('users a', 'a.fk_plant = t.plant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'inner')
            ->where('t.plant_id', (int) $tenant_id)
            ->limit(1)
            ->get()->row();
    }

    public function insert(array $data)
    {
        if (array_key_exists('name', $data)) { $data['plant_name'] = $data['name']; unset($data['name']); }
        if (array_key_exists('is_active', $data)) { $data['plant_status'] = $data['is_active']; unset($data['is_active']); }
        if (array_key_exists('created_by', $data)) { $data['plant_ad_by'] = $data['created_by']; unset($data['created_by']); }
        $data['plant_ad_dt'] = date('Y-m-d H:i:s');
        unset($data['created_at']);
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update_name($tenant_id, $name)
    {
        return $this->db
            ->where('plant_id', (int) $tenant_id)
            ->update($this->table, array(
                'plant_name' => $name,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    public function set_active($tenant_id, $is_active)
    {
        return $this->db
            ->where('plant_id', (int) $tenant_id)
            ->update($this->table, array(
                'plant_status' => $is_active ? 1 : 0,
                'updated_at'   => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * A plant/admin pair is hard-deleteable only before it accumulates data,
     * child users, properties, or OTP/audit references.
     */
    public function is_empty_for_admin($tenant_id, $admin_id)
    {
        $tenant_id = (int) $tenant_id;
        $admin_id = (int) $admin_id;

        if ($this->db
            ->where('fk_plant', $tenant_id)
            ->where('user_id !=', $admin_id)
            ->count_all_results('users') > 0
        ) {
            return FALSE;
        }

        $plant_tables = array(
            'properties', 'customers', 'room_categories', 'rooms',
            'booking_details', 'customer_identities',
        );
        foreach ($plant_tables as $table) {
            if (
                $this->db->table_exists($table)
                && $this->db->field_exists('fk_plant', $table)
                && $this->db->where('fk_plant', $tenant_id)->count_all_results($table) > 0
            ) {
                return FALSE;
            }
        }

        return $this->db
            ->where('user_id', $admin_id)
            ->count_all_results('mobile_otp') === 0;
    }

    /**
     * Soft-delete a plant: neither the plant nor its owner admin is ever
     * removed. Both are deactivated so the plant disappears everywhere while
     * all data stays in the database.
     */
    public function delete_empty_admin_tenant($tenant_id, $admin_id)
    {
        $this->db->trans_begin();
        $this->set_active($tenant_id, 0);
        $this->db->where('user_id', (int) $admin_id)
                 ->where('role', User_model::ROLE_ADMIN)
                 ->update('users', array('status' => 0, 'updated_at' => date('Y-m-d H:i:s')));

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }
        return (bool) $this->db->trans_commit();
    }
}


