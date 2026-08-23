<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Users, roles, and user-to-property assignment persistence.
 *  DB columns are dream-aligned (users.user_id/fk_plant/status/added_by,
 *  plants.plant_id/plant_name/plant_status); SELECT aliases keep the
 *  application-facing names (id/tenant_id/is_active) stable downstream. */
class User_model extends CI_Model
{
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_ADMIN       = 'admin';
    const ROLE_USER        = 'user';

    /** @var string */
    protected $table = 'users';

    /** users row with app-facing names aliased from dream-style columns. */
    private function user_columns($prefix = 'u')
    {
        return "$prefix.user_id AS id, $prefix.name, $prefix.mobile_no, $prefix.role,
                $prefix.fk_plant AS tenant_id, $prefix.status AS is_active,
                $prefix.last_login, $prefix.added_by AS created_by,
                $prefix.created_at, $prefix.updated_at";
    }

    public static function roles()
    {
        return array(self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_USER);
    }

    /** Active login identity, including active plant/admin eligibility. */
    public function get_active_by_mobile($mobile_no)
    {
        $row = $this->db
            ->select($this->user_columns().', t.plant_name AS tenant_name, t.plant_status AS tenant_is_active')
            ->from($this->table.' u')
            ->join('plants t', 't.plant_id = u.fk_plant', 'left')
            ->where('u.mobile_no', $mobile_no)
            ->where('u.status', 1)
            ->limit(1)
            ->get()->row();

        return $this->eligible_context($row);
    }

    /** Reload one active session identity from the database. */
    public function get_active_context_by_id($user_id)
    {
        $row = $this->db
            ->select($this->user_columns().', t.plant_name AS tenant_name, t.plant_status AS tenant_is_active')
            ->from($this->table.' u')
            ->join('plants t', 't.plant_id = u.fk_plant', 'left')
            ->where('u.user_id', (int) $user_id)
            ->where('u.status', 1)
            ->limit(1)
            ->get()->row();

        return $this->eligible_context($row);
    }

    public function get_by_id($user_id)
    {
        $row = $this->db
            ->select($this->user_columns())
            ->where('user_id', (int) $user_id)
            ->limit(1)
            ->get($this->table)->row();
        return $row;
    }

    public function get_admin($user_id)
    {
        return $this->db
            ->select($this->user_columns('u').', t.plant_name AS tenant_name, t.plant_status AS tenant_is_active')
            ->from($this->table.' u')
            ->join('plants t', 't.plant_id = u.fk_plant', 'inner')
            ->where('u.user_id', (int) $user_id)
            ->where('u.role', self::ROLE_ADMIN)
            ->limit(1)
            ->get()->row();
    }

    public function get_managed_user($user_id)
    {
        return $this->db
            ->select($this->user_columns('u').', t.plant_name AS tenant_name, a.name AS admin_name')
            ->from($this->table.' u')
            ->join('plants t', 't.plant_id = u.fk_plant', 'inner')
            ->join($this->table.' a', 'a.fk_plant = u.fk_plant AND a.role = '.$this->db->escape(self::ROLE_ADMIN), 'left')
            ->where('u.user_id', (int) $user_id)
            ->where('u.role', self::ROLE_USER)
            ->limit(1)
            ->get()->row();
    }

    /** Plant/admin-account list used only by super-admin management.
     *  $status: NULL/'' => active only, '0' => inactive, 'all' => both. */
    public function list_admins($status = NULL)
    {
        $this->db
            ->select($this->user_columns('u').', t.plant_name AS tenant_name, t.plant_status AS tenant_is_active')
            ->select('(SELECT COUNT(*) FROM properties p WHERE p.fk_plant = u.fk_plant) AS property_count', FALSE)
            ->select('(SELECT COUNT(*) FROM users child WHERE child.fk_plant = u.fk_plant AND child.role = '.$this->db->escape(self::ROLE_USER).') AS user_count', FALSE)
            ->from($this->table.' u')
            ->join('plants t', 't.plant_id = u.fk_plant', 'inner')
            ->where('u.role', self::ROLE_ADMIN);
        $this->status_filter($status, 'u');
        return $this->db->order_by('u.user_id', 'DESC')->get()->result();
    }

    /** Normal-user list, optionally constrained to one plant. */
    public function list_users($tenant_id = NULL, $status = NULL)
    {
        $this->db
            ->select($this->user_columns('u').', t.plant_name AS tenant_name, a.name AS admin_name')
            ->select('(SELECT COUNT(*) FROM user_property_access upa WHERE upa.user_id = u.user_id) AS property_count', FALSE)
            ->from($this->table.' u')
            ->join('plants t', 't.plant_id = u.fk_plant', 'inner')
            ->join($this->table.' a', 'a.fk_plant = u.fk_plant AND a.role = '.$this->db->escape(self::ROLE_ADMIN), 'left')
            ->where('u.role', self::ROLE_USER);
        if ($tenant_id !== NULL) {
            $this->db->where('u.fk_plant', (int) $tenant_id);
        }
        $this->status_filter($status, 'u');
        return $this->db->order_by('u.user_id', 'DESC')->get()->result();
    }

    /** Shared active/inactive visibility rule: deleted rows stay hidden. */
    private function status_filter($status, $prefix)
    {
        if ($status === 'all') {
            return;
        }
        $this->db->where($prefix.'.status', $status === '0' ? 0 : 1);
    }

    public function mobile_exists($mobile_no, $except_user_id = NULL)
    {
        $this->db->where('mobile_no', $mobile_no)->where('status', 1);
        if ($except_user_id) {
            $this->db->where('user_id !=', (int) $except_user_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    /** Translate app-facing keys to dream-style columns before writing. */
    private function translate(array $data)
    {
        if (array_key_exists('tenant_id', $data)) { $data['fk_plant'] = $data['tenant_id']; unset($data['tenant_id']); }
        if (array_key_exists('is_active', $data)) { $data['status'] = $data['is_active']; unset($data['is_active']); }
        if (array_key_exists('created_by', $data)) { $data['added_by'] = $data['created_by']; unset($data['created_by']); }
        return $data;
    }

    public function insert(array $data)
    {
        $data = $this->translate($data);
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update_account($user_id, array $data)
    {
        $data = $this->translate($data);
        unset($data['user_id'], $data['id'], $data['role'], $data['fk_plant'], $data['added_by'], $data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('user_id', (int) $user_id)
            ->update($this->table, $data);
    }

    public function set_active($user_id, $is_active)
    {
        return $this->db
            ->where('user_id', (int) $user_id)
            ->update($this->table, array(
                'status'     => $is_active ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    public function update_last_login($user_id)
    {
        return $this->db
            ->where('user_id', (int) $user_id)
            ->update($this->table, array('last_login' => date('Y-m-d H:i:s')));
    }

    public function assigned_property_ids($user_id, $tenant_id)
    {
        $rows = $this->db
            ->select('property_id')
            ->where('user_id', (int) $user_id)
            ->where('fk_plant', (int) $tenant_id)
            ->order_by('property_id', 'ASC')
            ->get('user_property_access')->result();
        return array_map(function ($row) { return (int) $row->property_id; }, $rows);
    }

    /** Replace all assignments after the caller validates plant ownership. */
    public function replace_property_access($user_id, $tenant_id, array $property_ids, $assigned_by)
    {
        $user_id = (int) $user_id;
        $tenant_id = (int) $tenant_id;
        $property_ids = array_values(array_unique(array_filter(array_map('intval', $property_ids))));

        $this->db->where('user_id', $user_id)->delete('user_property_access');
        foreach ($property_ids as $property_id) {
            $this->db->insert('user_property_access', array(
                'fk_plant'   => $tenant_id,
                'user_id'    => $user_id,
                'property_id' => $property_id,
                'assigned_by' => (int) $assigned_by,
                'created_at'  => date('Y-m-d H:i:s'),
            ));
        }
        return $this->db->trans_status() !== FALSE;
    }

    /**
     * Soft-delete a user: the row is never removed, only deactivated.
     * Audit references (OTP logins, created records) stay fully intact.
     */
    public function delete_user_if_empty($user_id)
    {
        return $this->set_active($user_id, 0);
    }

    /** Whether an account is referenced outside its revocable access rows. */
    public function has_audit_references($user_id)
    {
        $checks = array(
            array('mobile_otp', 'user_id'),
            array('users', 'added_by'),
            array('plants', 'plant_ad_by'),
            array('properties', 'added_by'),
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

    /** Enforce plant and owner-admin state for an otherwise active row. */
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
        if ($row->role === self::ROLE_USER && ! $this->plant_has_active_admin($row->tenant_id)) {
            return NULL;
        }
        return $row;
    }

    private function plant_has_active_admin($tenant_id)
    {
        return $this->db
            ->where('fk_plant', (int) $tenant_id)
            ->where('role', self::ROLE_ADMIN)
            ->where('status', 1)
            ->count_all_results($this->table) > 0;
    }
}


