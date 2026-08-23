<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Property persistence plus the single authoritative access predicate.
 *  DB columns are dream-aligned (properties.property_id/fk_plant/status/
 *  added_by); SELECT aliases keep app-facing names stable downstream. */
class Property_model extends CI_Model
{
    protected $table = 'properties';

    private function property_columns($prefix = 'p')
    {
        return "$prefix.property_id AS id, $prefix.fk_plant AS tenant_id,
                $prefix.property_code, $prefix.property_name,
                $prefix.status AS is_active, $prefix.added_by AS created_by";
    }

    /** Active/all properties an authenticated role may operate. */
    public function list_authorized_for_user($user, $active_only = TRUE)
    {
        $this->authorized_query($user);
        if ($active_only) {
            $this->db->where('p.status', 1);
        }
        return $this->db
            ->order_by('t.plant_name', 'ASC')
            ->order_by('p.property_name', 'ASC')
            ->get()->result();
    }

    public function count_authorized_for_user($user, $active_only = TRUE)
    {
        return count($this->list_authorized_for_user($user, $active_only));
    }

    /** One operational property, fail-closed to the caller's role/plant. */
    public function get_authorized_for_user($property_id, $user, $active_only = TRUE)
    {
        $this->authorized_query($user);
        $this->db->where('p.property_id', (int) $property_id);
        if ($active_only) {
            $this->db->where('p.status', 1);
        }
        return $this->db->limit(1)->get()->row();
    }

    /** Management list: super sees all; admin sees only its plant.
     *  $status: NULL/'' => active only, '0' => inactive, 'all' => both. */
    public function list_for_management($user, $status = NULL)
    {
        if ($status !== 'all') {
            $this->db->where('p.status', $status === '0' ? 0 : 1);
        }
        if ($user->role === User_model::ROLE_SUPER_ADMIN) {
            return $this->db
                ->select($this->property_columns().', t.plant_name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
                ->from($this->table.' p')
                ->join('plants t', 't.plant_id = p.fk_plant', 'inner')
                ->join('users a', 'a.fk_plant = p.fk_plant AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
                ->order_by('p.property_id', 'DESC')->get()->result();
        }
        if ($user->role !== User_model::ROLE_ADMIN || ! $user->tenant_id) {
            return array();
        }
        return $this->db
            ->select($this->property_columns().', t.plant_name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' p')
            ->join('plants t', 't.plant_id = p.fk_plant', 'inner')
            ->join('users a', 'a.fk_plant = p.fk_plant AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
            ->where('p.fk_plant', (int) $user->tenant_id)
            ->order_by('p.property_id', 'DESC')->get()->result();
    }

    public function get_for_management($property_id, $user)
    {
        $this->db
            ->select($this->property_columns().', t.plant_name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' p')
            ->join('plants t', 't.plant_id = p.fk_plant', 'inner')
            ->join('users a', 'a.fk_plant = p.fk_plant AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
            ->where('p.property_id', (int) $property_id);
        if ($user->role === User_model::ROLE_ADMIN) {
            $this->db->where('p.fk_plant', (int) $user->tenant_id);
        } elseif ($user->role !== User_model::ROLE_SUPER_ADMIN) {
            return NULL;
        }
        return $this->db->limit(1)->get()->row();
    }

    public function list_active_for_tenant($tenant_id)
    {
        return $this->db
            ->select($this->property_columns().', t.plant_name AS tenant_name')
            ->from($this->table.' p')
            ->join('plants t', 't.plant_id = p.fk_plant', 'inner')
            ->where('p.fk_plant', (int) $tenant_id)
            ->where('p.status', 1)
            ->where('t.plant_status', 1)
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
            ->where('fk_plant', (int) $tenant_id)
            ->where('status', 1)
            ->where_in('property_id', $ids)
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
            ->where('fk_plant', (int) $tenant_id)
            ->where('status', 1)
            ->where_in('property_id', $ids)
            ->count_all_results($this->table) > 0;
    }

    public function code_exists($tenant_id, $property_code, $except_property_id = NULL)
    {
        $this->db
            ->where('fk_plant', (int) $tenant_id)
            ->where('property_code', $property_code)
            ->where('status', 1);
        if ($except_property_id) {
            $this->db->where('property_id !=', (int) $except_property_id);
        }
        return $this->db->count_all_results($this->table) > 0;
    }

    public function insert(array $data)
    {
        if (array_key_exists('tenant_id', $data)) { $data['fk_plant'] = $data['tenant_id']; unset($data['tenant_id']); }
        if (array_key_exists('is_active', $data)) { $data['status'] = $data['is_active']; unset($data['is_active']); }
        if (array_key_exists('created_by', $data)) { $data['added_by'] = $data['created_by']; unset($data['created_by']); }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function update_property($property_id, array $data)
    {
        if (array_key_exists('tenant_id', $data)) { $data['fk_plant'] = $data['tenant_id']; unset($data['tenant_id']); }
        if (array_key_exists('is_active', $data)) { $data['status'] = $data['is_active']; unset($data['is_active']); }
        if (array_key_exists('created_by', $data)) { $data['added_by'] = $data['created_by']; unset($data['created_by']); }
        unset($data['property_id'], $data['id'], $data['added_by'], $data['created_at']);
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('property_id', (int) $property_id)
            ->update($this->table, $data);
    }

    public function set_active($property_id, $is_active)
    {
        return $this->db
            ->where('property_id', (int) $property_id)
            ->update($this->table, array(
                'status'     => $is_active ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * Soft-delete a property: the row is never removed, only deactivated.
     * All related data (rooms/bookings/customers) stays intact.
     */
    public function delete_if_empty($property_id)
    {
        return $this->set_active($property_id, 0) ? 'deactivated' : FALSE;
    }

    /** Build a reusable role-aware property query. */
    private function authorized_query($user)
    {
        $this->db
            ->select($this->property_columns().',
                      t.plant_name AS tenant_name, a.name AS admin_name, a.mobile_no AS admin_mobile')
            ->from($this->table.' p')
            ->join('plants t', 't.plant_id = p.fk_plant', 'inner')
            ->join('users a', 'a.fk_plant = p.fk_plant AND a.role = '.$this->db->escape(User_model::ROLE_ADMIN), 'left')
            ->where('t.plant_status', 1);

        if ($user->role === User_model::ROLE_SUPER_ADMIN) {
            return;
        }
        if ($user->role === User_model::ROLE_ADMIN) {
            $this->db->where('p.fk_plant', (int) $user->tenant_id);
            return;
        }
        if ($user->role === User_model::ROLE_USER) {
            $this->db
                ->join(
                    'user_property_access upa',
                    'upa.property_id = p.property_id AND upa.fk_plant = p.fk_plant AND upa.user_id = '.(int) $user->id,
                    'inner'
                )
                ->where('p.fk_plant', (int) $user->tenant_id);
            return;
        }

        // Unknown roles never receive a property row.
        $this->db->where('1 = 0', NULL, FALSE);
    }
}


