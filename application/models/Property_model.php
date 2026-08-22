<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Property persistence plus the single authoritative access predicate. */
class Property_model extends CI_Model
{
    protected $table = 'properties';

    /** Active/all properties an authenticated role may operate. */
    public function list_authorized_for_user($user, $active_only = TRUE)
    {
        $this->authorized_query($user);
        if ($active_only) {
            $this->db->where('p.is_active', 1);
        }
        return $this->db
            ->order_by('t.name', 'ASC')
            ->order_by('p.property_name', 'ASC')
            ->get()->result();
    }

    public function count_authorized_for_user($user, $active_only = TRUE)
    {
        return count($this->list_authorized_for_user($user, $active_only));
    }

    /** One operational property, fail-closed to the caller's role/tenant. */
    public function get_authorized_for_user($property_id, $user, $active_only = TRUE)
    {
        $this->authorized_query($user);
        $this->db->where('p.id', (int) $property_id);
        if ($active_only) {
            $this->db->where('p.is_active', 1);
        }
        return $this->db->limit(1)->get()->row();
    }

    /** Management list: super sees all; admin sees only its tenant. */
    public function list_for_management($user)
    {
        if ($user->role === User_model::ROLE_SUPER_ADMIN) {
            return $this->db
                ->select('p.*, t.name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
                ->from($this->table.' p')
                ->join('tenants t', 't.id = p.tenant_id', 'inner')
                ->join('users a', 'a.tenant_id = p.tenant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
                ->order_by('p.id', 'DESC')->get()->result();
        }
        if ($user->role !== User_model::ROLE_ADMIN || ! $user->tenant_id) {
            return array();
        }
        return $this->db
            ->select('p.*, t.name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' p')
            ->join('tenants t', 't.id = p.tenant_id', 'inner')
            ->join('users a', 'a.tenant_id = p.tenant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
            ->where('p.tenant_id', (int) $user->tenant_id)
            ->order_by('p.id', 'DESC')->get()->result();
    }

    public function get_for_management($property_id, $user)
    {
        $this->db
            ->select('p.*, t.name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' p')
            ->join('tenants t', 't.id = p.tenant_id', 'inner')
            ->join('users a', 'a.tenant_id = p.tenant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
            ->where('p.id', (int) $property_id);
        if ($user->role === User_model::ROLE_ADMIN) {
            $this->db->where('p.tenant_id', (int) $user->tenant_id);
        } elseif ($user->role !== User_model::ROLE_SUPER_ADMIN) {
            return NULL;
        }
        return $this->db->limit(1)->get()->row();
    }

    public function list_active_for_tenant($tenant_id)
    {
        return $this->db
            ->select('p.*, t.name AS tenant_name')
            ->from($this->table.' p')
            ->join('tenants t', 't.id = p.tenant_id', 'inner')
            ->where('p.tenant_id', (int) $tenant_id)
            ->where('p.is_active', 1)
            ->where('t.is_active', 1)
            ->order_by('p.property_name', 'ASC')
            ->get()->result();
    }

    public function active_ids_belong_to_tenant(array $property_ids, $tenant_id)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $property_ids))));
        if (empty($ids)) {
            return FALSE;
        }
        $count = $this->db
            ->where('tenant_id', (int) $tenant_id)
            ->where('is_active', 1)
            ->where_in('id', $ids)
            ->count_all_results($this->table);
        return $count === count($ids);
    }

    public function has_any_active_id_for_tenant(array $property_ids, $tenant_id)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $property_ids))));
        if (empty($ids)) {
            return FALSE;
        }
        return $this->db
            ->where('tenant_id', (int) $tenant_id)
            ->where('is_active', 1)
            ->where_in('id', $ids)
            ->count_all_results($this->table) > 0;
    }

    public function code_exists($tenant_id, $property_code, $except_property_id = NULL)
    {
        $this->db
            ->where('tenant_id', (int) $tenant_id)
            ->where('property_code', $property_code);
        if ($except_property_id) {
            $this->db->where('id !=', (int) $except_property_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function insert(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update_property($property_id, array $data)
    {
        unset($data['id'], $data['tenant_id'], $data['created_by'], $data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('id', (int) $property_id)
            ->update($this->table, $data);
    }

    public function set_active($property_id, $is_active)
    {
        return $this->db
            ->where('id', (int) $property_id)
            ->update($this->table, array(
                'is_active'  => $is_active ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    /** Hard-delete only an unused property; assignment rows cascade. */
    public function delete_if_empty($property_id)
    {
        $property_id = (int) $property_id;
        $business_tables = array(
            'rooms', 'room_categories', 'booking_details',
            'customers', 'customer_identities',
        );
        foreach ($business_tables as $table) {
            if (
                $this->db->table_exists($table)
                && $this->db->field_exists('property_id', $table)
                && $this->db->where('property_id', $property_id)->count_all_results($table) > 0
            ) {
                return FALSE;
            }
        }
        return $this->db->where('id', $property_id)->delete($this->table);
    }

    /** Build a reusable role-aware property query. */
    private function authorized_query($user)
    {
        $this->db
            ->select('p.id, p.tenant_id, p.property_code, p.property_name, p.is_active,
                      t.name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' p')
            ->join('tenants t', 't.id = p.tenant_id', 'inner')
            ->join('users a', 'a.tenant_id = p.tenant_id AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
            ->where('t.is_active', 1);

        if ($user->role === User_model::ROLE_SUPER_ADMIN) {
            return;
        }
        if ($user->role === User_model::ROLE_ADMIN) {
            $this->db->where('p.tenant_id', (int) $user->tenant_id);
            return;
        }
        if ($user->role === User_model::ROLE_USER) {
            $this->db
                ->join(
                    'user_property_access upa',
                    'upa.property_id = p.id AND upa.tenant_id = p.tenant_id AND upa.user_id = '.(int) $user->id,
                    'inner'
                )
                ->where('p.tenant_id', (int) $user->tenant_id);
            return;
        }

        // Unknown roles never receive a property row.
        $this->db->where('1 = 0', NULL, FALSE);
    }
}
