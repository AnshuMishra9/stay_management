<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Normal-user management for admins and super-admin support. */
class Users extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Property_model');
    }

    public function index()
    {
        if ( ! $this->require_management_role()) { return; }
        $tenant_id = NULL;
        if ($this->auth_user->role === User_model::ROLE_ADMIN) {
            $tenant_id = (int) $this->auth_user->tenant_id;
        } elseif ($this->input->get('tenant_id') !== NULL && $this->input->get('tenant_id') !== '') {
            $tenant_id = (int) $this->input->get('tenant_id');
        }
        $this->load->view('users/list', array(
            'users'             => $this->User_model->list_users($tenant_id),
            'tenant_id'         => $tenant_id,
            'admin_tenants'     => $this->Tenant_model->list_active_admin_tenants(),
            'flash'             => $this->session->flashdata('user_msg'),
        ));
    }

    public function form($id = NULL)
    {
        if ( ! $this->require_management_role()) { return; }
        $user = $id ? $this->User_model->get_managed_user($id) : NULL;
        if ($id && ( ! $user || ! $this->can_manage_user($user))) { show_404(); return; }
        $tenant_id = $user
            ? (int) $user->tenant_id
            : $this->requested_tenant_id();
        $this->render_form($user, $tenant_id, array());
    }

    public function save()
    {
        if (
            ! $this->require_management_role()
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }

        $id = (int) $this->input->post('id');
        $user = $id ? $this->User_model->get_managed_user($id) : NULL;
        if ($id && ( ! $user || ! $this->can_manage_user($user))) { show_404(); return; }
        $tenant_id = $user ? (int) $user->tenant_id : $this->requested_tenant_id();
        $tenant = $user && $this->auth_user->role === User_model::ROLE_SUPER_ADMIN
            ? $this->Tenant_model->get_admin_tenant($tenant_id)
            : $this->Tenant_model->get_active_admin_tenant($tenant_id);

        $name = trim((string) $this->input->post('name', TRUE));
        $mobile = trim((string) $this->input->post('mobile_no', TRUE));
        $is_active = $this->input->post('is_active') ? 1 : 0;
        $property_ids = (array) $this->input->post('property_ids');
        $property_ids = array_values(array_unique(array_filter(array_map('intval', $property_ids))));
        $errors = array();
        if ( ! $tenant) {
            $errors[] = 'Choose an active admin account before creating a user.';
        }
        if ($name === '' || strlen($name) > 150) {
            $errors[] = 'User name is required and must be 150 characters or fewer.';
        }
        if ( ! preg_match('/^[0-9]{10,15}$/', $mobile)) {
            $errors[] = 'Enter a valid 10 to 15 digit mobile number.';
        } elseif ($this->User_model->mobile_exists($mobile, $id)) {
            $errors[] = 'That mobile number already belongs to another account.';
        }
        if (
            ! empty($property_ids)
            && $tenant_id
            && ! $this->Property_model->active_ids_belong_to_tenant($property_ids, $tenant_id)
        ) {
            $errors[] = 'Every assignment must be an active property in the selected admin account.';
        }
        if ($errors) {
            $draft = (object) array(
                'id' => $id, 'tenant_id' => $tenant_id, 'name' => $name,
                'mobile_no' => $mobile, 'is_active' => $is_active,
                'tenant_name' => $user ? $user->tenant_name : ($tenant ? $tenant->name : ''),
                'admin_name' => $user ? $user->admin_name : ($tenant ? $tenant->admin_name : ''),
            );
            $this->render_form($draft, $tenant_id, $errors, $property_ids);
            return;
        }

        $this->db->trans_begin();
        if ($user) {
            $this->User_model->update_account($user->id, array(
                'name' => $name, 'mobile_no' => $mobile, 'is_active' => $is_active,
            ));
            $user_id = (int) $user->id;
            $message = 'User account updated successfully.';
        } else {
            $user_id = $this->User_model->insert(array(
                'name'       => $name,
                'mobile_no'  => $mobile,
                'role'       => User_model::ROLE_USER,
                'tenant_id'  => $tenant_id,
                'is_active'  => $is_active,
                'created_by' => (int) $this->auth_user->id,
            ));
            $message = 'User account created successfully.';
        }
        if ($user_id) {
            $this->User_model->replace_property_access(
                $user_id,
                $tenant_id,
                $property_ids,
                (int) $this->auth_user->id
            );
        }

        if ($this->db->trans_status() === FALSE || ! $user_id) {
            $this->db->trans_rollback();
            show_error('The user account could not be saved.', 500);
            return;
        }
        if ( ! $this->db->trans_commit()) {
            show_error('The user account could not be saved.', 500);
            return;
        }
        $this->session->set_flashdata('user_msg', array('type' => 'success', 'text' => $message));
        redirect('users'.($this->auth_user->role === User_model::ROLE_SUPER_ADMIN ? '?tenant_id='.$tenant_id : ''));
    }

    public function status($id = NULL)
    {
        if (
            ! $this->require_management_role()
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }
        $user = $id ? $this->User_model->get_managed_user($id) : NULL;
        if ( ! $user || ! $this->can_manage_user($user)) { show_404(); return; }
        $active = $this->input->post('is_active') ? 1 : 0;
        $this->User_model->set_active($user->id, $active);
        $this->session->set_flashdata('user_msg', array(
            'type' => 'success', 'text' => $active ? 'User activated.' : 'User deactivated.',
        ));
        redirect('users');
    }

    public function delete($id = NULL)
    {
        if (
            ! $this->require_management_role()
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }
        $user = $id ? $this->User_model->get_managed_user($id) : NULL;
        if ( ! $user || ! $this->can_manage_user($user)) { show_404(); return; }
        if ($this->User_model->delete_user_if_empty($user->id)) {
            $text = 'Unused user account deleted.';
        } else {
            $this->User_model->set_active($user->id, 0);
            $text = 'This user has login or audit history, so it was deactivated instead of deleted.';
        }
        $this->session->set_flashdata('user_msg', array('type' => 'success', 'text' => $text));
        redirect('users');
    }

    private function require_management_role()
    {
        return $this->require_role(array(
            User_model::ROLE_SUPER_ADMIN,
            User_model::ROLE_ADMIN,
        ));
    }

    private function can_manage_user($user)
    {
        return $this->auth_user->role === User_model::ROLE_SUPER_ADMIN
            || (
                $this->auth_user->role === User_model::ROLE_ADMIN
                && (int) $user->tenant_id === (int) $this->auth_user->tenant_id
            );
    }

    private function requested_tenant_id()
    {
        if ($this->auth_user->role === User_model::ROLE_ADMIN) {
            return (int) $this->auth_user->tenant_id;
        }
        $value = $this->input->post('tenant_id');
        if ($value === NULL || $value === '') { $value = $this->input->get('tenant_id'); }
        return (int) $value;
    }

    private function render_form($user, $tenant_id, array $errors, $selected_ids = NULL)
    {
        if ($selected_ids === NULL) {
            $selected_ids = $user
                ? $this->User_model->assigned_property_ids($user->id, $tenant_id)
                : array();
        }
        $this->load->view('users/form', array(
            'user'           => $user,
            'tenant_id'      => (int) $tenant_id,
            'errors'         => $errors,
            'selected_ids'   => $selected_ids,
            'properties'     => $tenant_id
                ? $this->Property_model->list_active_for_tenant($tenant_id)
                : array(),
            'admin_tenants'  => $this->auth_user->role === User_model::ROLE_SUPER_ADMIN
                ? $this->Tenant_model->list_active_admin_tenants()
                : array(),
        ));
    }
}
