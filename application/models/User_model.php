<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Users, roles, and user-to-property assignment persistence. */
class User_model extends CI_Model
{
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN       = 'admin';
    const ROLE_USER        = 'user';

    /** @var string */
    protected $table = 'users';

    public static function roles()
    {
        return array(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_USER);
    }

    /** Active login identity, including active tenant/admin eligibility. */
    public function get_active_by_mobile($mobile_no)
    {
        $row = $this->db
            ->select('u.*, t.name AS tenant_name, t.is_active AS tenant_is_active')
            ->from($this->table.' u')
            ->join('tenants t', 't.id = u.tenant_id', 'left')
            ->where('u.mobile_no', $mobile_no)
            ->where('u.is_active', 1)
            ->limit(1)
            ->get()->row();

        return $this->eligible_context($row);
    }

    /** Reload one active session identity from the database. */
    public function get_active_context_by_id($user_id)
    {
        $row = $this->db
            ->select('u.*, t.name AS tenant_name, t.is_active AS tenant_is_active')
            ->from($this->table.' u')
            ->join('tenants t', 't.id = u.tenant_id', 'left')
            ->where('u.id', (int) $user_id)
            ->where('u.is_active', 1)
            ->limit(1)
            ->get()->row();

        return $this->eligible_context($row);
    }

    public function get_by_id($user_id)
    {
        return $this->db
            ->where('id', (int) $user_id)
            ->limit(1)
            ->get($this->table)->row();
    }

    public function get_admin($user_id)
    {
        return $this->db
            ->select('u.*, t.name AS tenant_name, t.is_active AS tenant_is_active')
            ->from($this->table.' u')
            ->join('tenants t', 't.id = u.tenant_id', 'inner')
            ->where('u.id', (int) $user_id)
            ->where('u.role', self::ROLE_ADMIN)
            ->limit(1)
            ->get()->row();
    }

    public function get_managed_user($user_id)
    {
        return $this->db
            ->select('u.*, t.name AS tenant_name, a.name AS admin_name')
            ->from($this->table.' u')
            ->join('tenants t', 't.id = u.tenant_id', 'inner')
            ->join($this->table.' a', 'a.tenant_id = u.tenant_id AND a.role = '.$this->db->escape(self::ROLE_ADMIN), 'left')
            ->where('u.id', (int) $user_id)
            ->where('u.role', self::ROLE_USER)
            ->limit(1)
            ->get()->row();
    }

    /** Admin-account list used only by super-admin management. */
    public function list_admins()
    {
        return $this->db
            ->select('u.*, t.name AS tenant_name, t.is_active AS tenant_is_active')
            ->select('(SELECT COUNT(*) FROM properties p WHERE p.tenant_id = u.tenant_id) AS property_count', FALSE)
            ->select('(SELECT COUNT(*) FROM users child WHERE child.tenant_id = u.tenant_id AND child.role = '.$this->db->escape(self::ROLE_USER).') AS user_count', FALSE)
            ->from($this->table.' u')
            ->join('tenants t', 't.id = u.tenant_id', 'inner')
            ->where('u.role', self::ROLE_ADMIN)
            ->order_by('u.id', 'DESC')
            ->get()->result();
    }

    /** Normal-user list, optionally constrained to one tenant. */
    public function list_users($tenant_id = NULL)
    {
        $this->db
            ->select('u.*, t.name AS tenant_name, a.name AS admin_name')
            ->select('(SELECT COUNT(*) FROM user_property_access upa WHERE upa.user_id = u.id) AS property_count', FALSE)
            ->from($this->table.' u')
            ->join('tenants t', 't.id = u.tenant_id', 'inner')
            ->join($this->table.' a', 'a.tenant_id = u.tenant_id AND a.role = '.$this->db->escape(self::ROLE_ADMIN), 'left')
            ->where('u.role', self::ROLE_USER);
        if ($tenant_id !== NULL) {
            $this->db->where('u.tenant_id', (int) $tenant_id);
        }
        return $this->db->order_by('u.id', 'DESC')->get()->result();
    }

    public function mobile_exists($mobile_no, $except_user_id = NULL)
    {
        $this->db->where('mobile_no', $mobile_no);
        if ($except_user_id) {
            $this->db->where('id !=', (int) $except_user_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function insert(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update_account($user_id, array $data)
    {
        unset($data['id'], $data['role'], $data['tenant_id'], $data['created_by'], $data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $user_id)
            ->update($this->table, $data);
    }

    public function set_active($user_id, $is_active)
    {
        return $this->db
            ->where('id', (int) $user_id)
            ->update($this->table, array(
                'is_active' => $is_active ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    public function update_last_login($user_id)
    {
        return $this->db
            ->where('id', (int) $user_id)
            ->update($this->table, array('last_login' => date('Y-m-d H:i:s')));
    }

    public function assigned_property_ids($user_id, $tenant_id)
    {
        $rows = $this->db
            ->select('property_id')
            ->where('user_id', (int) $user_id)
            ->where('tenant_id', (int) $tenant_id)
            ->order_by('property_id', 'ASC')
            ->get('user_property_access')->result();
        return array_map(function ($row) { return (int) $row->property_id; }, $rows);
    }

    /** Replace all assignments after the caller validates tenant ownership. */
    public function replace_property_access($user_id, $tenant_id, array $property_ids, $assigned_by)
    {
        $user_id = (int) $user_id;
        $tenant_id = (int) $tenant_id;
        $property_ids = array_values(array_unique(array_filter(array_map('intval', $property_ids))));

        $this->db->where('user_id', $user_id)->delete('user_property_access');
        foreach ($property_ids as $property_id) {
            $this->db->insert('user_property_access', array(
                'tenant_id'  => $tenant_id,
                'user_id'    => $user_id,
                'property_id' => $property_id,
                'assigned_by' => (int) $assigned_by,
                'created_at'  => date('Y-m-d H:i:s'),
            ));
        }
        return $this->db->trans_status() !== FALSE;
    }

    /** Hard-delete a new/unused normal user; preserve referenced users. */
    public function delete_user_if_empty($user_id)
    {
        $user = $this->get_managed_user($user_id);
        if ( ! $user || $this->has_audit_references((int) $user_id)) {
            return FALSE;
        }
        return $this->db->where('id', (int) $user_id)->delete($this->table);
    }

    /** Whether an account is referenced outside its revocable access rows. */
    public function has_audit_references($user_id)
    {
        $checks = array(
            array('otp_requests', 'user_id'),
            array('users', 'created_by'),
            array('tenants', 'created_by'),
            array('properties', 'created_by'),
            array('user_property_access', 'assigned_by'),
        );
        foreach ($checks as $check) {
            if (
                $this->db->table_exists($check[0])
                && $this->db->field_exists($check[1], $check[0])
                && $this->db->where($check[1], (int) $user_id)->count_all_results($check[0]) > 0
            ) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /** Enforce tenant and owner-admin state for an otherwise active row. */
    private function eligible_context($row)
    {
        if ( ! $row || ! in_array($row->role, self::roles(), TRUE)) {
            return NULL;
        }
        if ($row->role === self::ROLE_SUPER_ADMIN) {
            return $row->tenant_id === NULL ? $row : NULL;
        }
        if ($row->tenant_id === NULL || (int) $row->tenant_is_active !== 1) {
            return NULL;
        }
        if ($row->role === self::ROLE_USER && ! $this->tenant_has_active_admin($row->tenant_id)) {
            return NULL;
        }
        return $row;
    }

    private function tenant_has_active_admin($tenant_id)
    {
        return $this->db
            ->where('tenant_id', (int) $tenant_id)
            ->where('role', self::ROLE_ADMIN)
            ->where('is_active', 1)
            ->count_all_results($this->table) > 0;
    }
}
