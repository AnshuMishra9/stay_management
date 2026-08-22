<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Tenant/account persistence. Each admin owns exactly one tenant in v1. */
class Tenant_model extends CI_Model
{
    protected $table = 'tenants';

    public function get_by_id($tenant_id, $active_only = FALSE)
    {
        $this->db->where('id', (int) $tenant_id);
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        return $this->db->limit(1)->get($this->table)->row();
    }

    /** Active tenants paired with their active admin, for support selectors. */
    public function list_active_admin_tenants()
    {
        return $this->db
            ->select('t.id, t.name, a.id AS admin_id, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' t')
            ->join('users a', 'a.tenant_id = t.id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'inner')
            ->where('t.is_active', 1)
            ->where('a.is_active', 1)
            ->order_by('t.name', 'ASC')
            ->order_by('a.name', 'ASC')
            ->get()->result();
    }

    public function get_active_admin_tenant($tenant_id)
    {
        return $this->db
            ->select('t.id, t.name, a.id AS admin_id, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' t')
            ->join('users a', 'a.tenant_id = t.id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'inner')
            ->where('t.id', (int) $tenant_id)
            ->where('t.is_active', 1)
            ->where('a.is_active', 1)
            ->limit(1)
            ->get()->row();
    }

    /** Tenant paired with its owner admin, including inactive support cases. */
    public function get_admin_tenant($tenant_id)
    {
        return $this->db
            ->select('t.id, t.name, t.is_active, a.id AS admin_id, a.name AS admin_name,
                      a.mobile_no AS admin_mobile, a.is_active AS admin_is_active')
            ->from($this->table.' t')
            ->join('users a', 'a.tenant_id = t.id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'inner')
            ->where('t.id', (int) $tenant_id)
            ->limit(1)
            ->get()->row();
    }

    public function insert(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update_name($tenant_id, $name)
    {
        return $this->db
            ->where('id', (int) $tenant_id)
            ->update($this->table, array(
                'name'       => $name,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    public function set_active($tenant_id, $is_active)
    {
        return $this->db
            ->where('id', (int) $tenant_id)
            ->update($this->table, array(
                'is_active'  => $is_active ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * A tenant/admin pair is hard-deleteable only before it accumulates data,
     * child users, properties, or OTP/audit references.
     */
    public function is_empty_for_admin($tenant_id, $admin_id)
    {
        $tenant_id = (int) $tenant_id;
        $admin_id = (int) $admin_id;

        if ($this->db
            ->where('tenant_id', $tenant_id)
            ->where('id !=', $admin_id)
            ->count_all_results('users') > 0
        ) {
            return FALSE;
        }

        $tenant_tables = array(
            'properties', 'customers', 'room_categories', 'rooms',
            'booking_details', 'customer_identities',
        );
        foreach ($tenant_tables as $table) {
            if (
                $this->db->table_exists($table)
                && $this->db->field_exists('tenant_id', $table)
                && $this->db->where('tenant_id', $tenant_id)->count_all_results($table) > 0
            ) {
                return FALSE;
            }
        }

        return $this->db
            ->where('user_id', $admin_id)
            ->count_all_results('otp_requests') === 0;
    }

    /** Delete an already-proven-empty tenant and its sole admin atomically. */
    public function delete_empty_admin_tenant($tenant_id, $admin_id)
    {
        if ( ! $this->is_empty_for_admin($tenant_id, $admin_id)) {
            return FALSE;
        }

        $this->db->trans_begin();
        $this->db->where('user_id', (int) $admin_id)->delete('user_property_access');
        $this->db->where('id', (int) $admin_id)->where('role', User_model::ROLE_ADMIN)->delete('users');
        $this->db->where('id', (int) $tenant_id)->delete($this->table);

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return FALSE;
        }
        return (bool) $this->db->trans_commit();
    }
}
