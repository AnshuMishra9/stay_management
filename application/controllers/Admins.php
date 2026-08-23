<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Super-admin management of plants (tenant-owner accounts). */
class Admins extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Tenant_model');
    }

    public function index()
    {
        if ( ! $this->require_role(User_model::ROLE_SUPER_ADMIN)) { return; }
        $status = $this->input->get('status');
        $this->load->view('admins/list', array(
            'admins' => $this->User_model->list_admins($status),
            'status' => $status,
            'flash'  => $this->session->flashdata('admin_msg'),
        ));
    }

    public function form($id = NULL)
    {
        if ( ! $this->require_role(User_model::ROLE_SUPER_ADMIN)) { return; }
        $admin = $id ? $this->User_model->get_admin($id) : NULL;
        if ($id && ! $admin) { show_404(); return; }
        $this->render_form($admin, array());
    }

    public function save()
    {
        if (
            ! $this->require_role(User_model::ROLE_SUPER_ADMIN)
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }

        $id = (int) $this->input->post('id');
        $admin = $id ? $this->User_model->get_admin($id) : NULL;
        if ($id && ! $admin) { show_404(); return; }

        $name = trim((string) $this->input->post('name', TRUE));
        $mobile = trim((string) $this->input->post('mobile_no', TRUE));
        $tenant_name = trim((string) $this->input->post('tenant_name', TRUE));
        $is_active = $this->input->post('is_active') ? 1 : 0;
        $errors = $this->validate($name, $mobile, $tenant_name, $id);
        if ($errors) {
            $draft = (object) array(
                'id' => $id, 'name' => $name, 'mobile_no' => $mobile,
                'tenant_name' => $tenant_name, 'is_active' => $is_active,
            );
            if ($admin) { $draft->tenant_id = $admin->tenant_id; }
            $this->render_form($draft, $errors);
            return;
        }

        $this->db->trans_begin();
        if ($admin) {
            $this->User_model->update_account($admin->id, array(
                'name' => $name, 'mobile_no' => $mobile, 'is_active' => $is_active,
            ));
            $this->Tenant_model->update_name($admin->tenant_id, $tenant_name);
            $message = 'Plant updated successfully.';
        } else {
            $tenant_id = $this->Tenant_model->insert(array(
                'name'       => $tenant_name,
                'is_active'  => 1,
                'created_by' => (int) $this->auth_user->id,
            ));
            $admin_id = $tenant_id ? $this->User_model->insert(array(
                'name'       => $name,
                'mobile_no'  => $mobile,
                'role'       => User_model::ROLE_ADMIN,
                'tenant_id'  => $tenant_id,
                'is_active'  => $is_active,
                'created_by' => (int) $this->auth_user->id,
            )) : 0;
            if ( ! $tenant_id || ! $admin_id) {
                $this->db->trans_rollback();
                $this->render_form((object) array(
                    'id' => 0, 'name' => $name, 'mobile_no' => $mobile,
                    'tenant_name' => $tenant_name, 'is_active' => $is_active,
                ), array('Plant could not be created.'));
                return;
            }
            $message = 'Plant created. The plant admin may now create properties.';
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            show_error('The plant could not be saved.', 500);
            return;
        }
        if ( ! $this->db->trans_commit()) {
            show_error('The plant could not be saved.', 500);
            return;
        }
        $this->session->set_flashdata('admin_msg', array('type' => 'success', 'text' => $message));
        redirect('admins');
    }

    public function status($id = NULL)
    {
        if (
            ! $this->require_role(User_model::ROLE_SUPER_ADMIN)
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }
        $admin = $id ? $this->User_model->get_admin($id) : NULL;
        if ( ! $admin) { show_404(); return; }
        $active = $this->input->post('is_active') ? 1 : 0;
        $this->User_model->set_active($admin->id, $active);
        $this->session->set_flashdata('admin_msg', array(
            'type' => 'success',
            'text' => $active
                ? 'Plant activated.'
                : 'Plant deactivated. Its normal users can no longer sign in.',
        ));
        redirect('admins');
    }

    public function delete($id = NULL)
    {
        if (
            ! $this->require_role(User_model::ROLE_SUPER_ADMIN)
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }
        $admin = $id ? $this->User_model->get_admin($id) : NULL;
        if ( ! $admin) { show_404(); return; }

        // Soft-delete: plant and its owner admin are never removed.
        if ($this->Tenant_model->delete_empty_admin_tenant($admin->tenant_id, $admin->id)) {
            $text = 'Plant "'.$admin->name.'" was deactivated. No data was deleted.';
        } else {
            $text = 'The plant could not be deactivated.';
        }
        $this->session->set_flashdata('admin_msg', array('type' => 'success', 'text' => $text));
        redirect('admins');
    }

    private function validate($name, $mobile, $tenant_name, $except_id)
    {
        $errors = array();
        if ($name === '' || strlen($name) > 150) {
            $errors[] = 'Owner name is required and must be 150 characters or fewer.';
        }
        if ( ! preg_match('/^[0-9]{10,15}$/', $mobile)) {
            $errors[] = 'Enter a valid 10 to 15 digit mobile number.';
        } elseif ($this->User_model->mobile_exists($mobile, $except_id)) {
            $errors[] = 'That mobile number already belongs to another account.';
        }
        if ($tenant_name === '' || strlen($tenant_name) > 150) {
            $errors[] = 'Plant name is required and must be 150 characters or fewer.';
        }
        return $errors;
    }

    private function render_form($admin, array $errors)
    {
        $this->load->view('admins/form', array('admin' => $admin, 'errors' => $errors));
    }
}
