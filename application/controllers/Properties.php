<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Property management plus the session-wide active-property selector. */
class Properties extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Property_model');
    }

    public function index()
    {
        if ( ! $this->require_management_role()) { return; }
        $status = $this->input->get('status');
        $this->load->view('properties/list', array(
            'properties' => $this->Property_model->list_for_management($this->auth_user, $status),
            'status'     => $status,
            'flash'      => $this->session->flashdata('property_msg')
                ?: $this->session->flashdata('access_msg'),
        ));
    }

    public function form($id = NULL)
    {
        if ( ! $this->require_management_role()) { return; }
        $property = $id ? $this->Property_model->get_for_management($id, $this->auth_user) : NULL;
        if ($id && ! $property) { show_404(); return; }
        $this->render_form($property, array());
    }

    public function save()
    {
        if (
            ! $this->require_management_role()
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }

        $id = (int) $this->input->post('id');
        $property = $id ? $this->Property_model->get_for_management($id, $this->auth_user) : NULL;
        if ($id && ! $property) { show_404(); return; }

        if ($property) {
            $tenant_id = (int) $property->tenant_id; // immutable ownership
        } elseif ($this->auth_user->role === User_model::ROLE_ADMIN) {
            $tenant_id = (int) $this->auth_user->tenant_id;
        } else {
            $tenant_id = (int) $this->input->post('tenant_id');
        }

        $tenant = $this->Tenant_model->get_active_admin_tenant($tenant_id);
        $name = trim((string) $this->input->post('property_name', TRUE));
        $code = strtoupper(trim((string) $this->input->post('property_code', TRUE)));
        $is_active = $this->input->post('is_active') ? 1 : 0;
        $errors = array();
        if ( ! $tenant && ! $property) {
            $errors[] = 'Choose an active plant for this property.';
        }
        if ($name === '' || strlen($name) > 150) {
            $errors[] = 'Property name is required and must be 150 characters or fewer.';
        }
        if ( ! preg_match('/^[A-Z0-9_-]{2,30}$/', $code)) {
            $errors[] = 'Property code must be 2 to 30 letters, numbers, underscores, or hyphens.';
        } elseif ($tenant_id && $this->Property_model->code_exists($tenant_id, $code, $id)) {
            $errors[] = 'That property code already exists in this plant.';
        }
        if ($errors) {
            $draft = (object) array(
                'id' => $id, 'tenant_id' => $tenant_id, 'property_name' => $name,
                'property_code' => $code, 'is_active' => $is_active,
                'tenant_name' => $property ? $property->tenant_name : ($tenant ? $tenant->name : ''),
                'admin_name' => $property ? $property->admin_name : ($tenant ? $tenant->admin_name : ''),
            );
            $this->render_form($draft, $errors);
            return;
        }

        if ($property) {
            $this->Property_model->update_property($property->id, array(
                'property_name' => $name,
                'property_code' => $code,
                'is_active'     => $is_active,
            ));
            $property_id = (int) $property->id;
            $message = 'Property updated successfully.';
        } else {
            $property_id = $this->Property_model->insert(array(
                'tenant_id'    => $tenant_id,
                'property_code' => $code,
                'property_name' => $name,
                'is_active'     => $is_active,
                'created_by'    => (int) $this->auth_user->id,
            ));
            if ( ! $property_id) {
                show_error('The property could not be created.', 500);
                return;
            }
            $message = 'Property created successfully.';
        }

        // When an admin creates its first active property there is no stale
        // context to preserve, so enter it directly and keep onboarding smooth.
        if (
            ! $property
            && $is_active
            && $this->auth_user->role === User_model::ROLE_ADMIN
            && ! $this->session->userdata('active_property_id')
            && $this->Property_model->count_authorized_for_user($this->auth_user, TRUE) === 1
        ) {
            $this->activate_property_context($property_id);
            $this->remember_property($property_id);
            $this->session->set_flashdata('property_context_switched', (int) $property_id);
            $this->session->set_flashdata('inventory_msg', array('type' => 'success', 'text' => $message));
            redirect('inventory');
            return;
        }

        if ( ! $is_active && (int) $this->session->userdata('active_property_id') === $property_id) {
            $this->clear_property_context();
            $this->session->set_flashdata('property_context_switched', $property_id);
        }
        $this->session->set_flashdata('property_msg', array('type' => 'success', 'text' => $message));
        redirect('properties');
    }

    public function status($id = NULL)
    {
        if (
            ! $this->require_management_role()
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }
        $property = $id ? $this->Property_model->get_for_management($id, $this->auth_user) : NULL;
        if ( ! $property) { show_404(); return; }
        $active = $this->input->post('is_active') ? 1 : 0;
        $this->Property_model->set_active($property->id, $active);
        if ( ! $active && (int) $this->session->userdata('active_property_id') === (int) $property->id) {
            $this->clear_property_context();
            $this->session->set_flashdata('property_context_switched', (int) $property->id);
        }
        $this->session->set_flashdata('property_msg', array(
            'type' => 'success',
            'text' => $active ? 'Property activated.' : 'Property deactivated. Operational access is now blocked.',
        ));
        redirect('properties');
    }

    public function delete($id = NULL)
    {
        if (
            ! $this->require_management_role()
            || ! $this->require_post()
            || ! $this->require_session_write_token()
        ) { return; }
        $property = $id ? $this->Property_model->get_for_management($id, $this->auth_user) : NULL;
        if ( ! $property) { show_404(); return; }
        // Soft-delete: the property row is never removed from the database.
        $this->Property_model->set_active($property->id, 0);
        $text = 'Property "'.$property->property_name.'" was deactivated. No data was deleted.';
        if ((int) $this->session->userdata('active_property_id') === (int) $property->id) {
            $this->clear_property_context();
            $this->session->set_flashdata('property_context_switched', (int) $property->id);
        }
        $this->session->set_flashdata('property_msg', array('type' => 'success', 'text' => $text));
        redirect('properties');
    }

    /** Standalone selector used whenever more than one property is available. */
    public function select_property()
    {
        $properties = $this->Property_model->list_authorized_for_user($this->auth_user, TRUE);
        if (empty($properties)) {
            redirect($this->auth_user->role === User_model::ROLE_USER
                ? 'access/no-properties'
                : 'properties');
            return;
        }
        $this->load->view('properties/select', array(
            'properties' => $properties,
            'flash'      => $this->session->flashdata('access_msg'),
        ));
    }

    /** Validate and rotate the one session-wide active property. */
    public function switch_property()
    {
        if ( ! $this->require_post() || ! $this->require_session_write_token()) { return; }
        $property_id = (int) $this->input->post('property_id');
        $property = $this->Property_model->get_authorized_for_user(
            $property_id,
            $this->auth_user,
            TRUE
        );
        if ( ! $property) {
            show_404();
            return;
        }
        $this->activate_property_context($property->id);
        $this->remember_property($property->id);
        $this->session->set_flashdata('property_context_switched', (int) $property->id);
        $this->session->set_flashdata('inventory_msg', array(
            'type' => 'success', 'text' => 'Active property changed to '.$property->property_name.'.',
        ));
        redirect('inventory');
    }

    private function require_management_role()
    {
        return $this->require_role(array(
            User_model::ROLE_SUPER_ADMIN,
            User_model::ROLE_ADMIN,
        ));
    }

    private function remember_property($property_id)
    {
        $this->input->set_cookie(array(
            'name'     => 'stay_last_property',
            'value'    => (string) (int) $property_id,
            'expire'   => 90 * 24 * 60 * 60,
            'secure'   => (bool) config_item('cookie_secure'),
            'httponly' => TRUE,
            'samesite' => 'Lax',
        ));
    }

    private function render_form($property, array $errors)
    {
        $tenants = $this->auth_user->role === User_model::ROLE_SUPER_ADMIN
            ? $this->Tenant_model->list_active_admin_tenants()
            : array();
        $this->load->view('properties/form', array(
            'property' => $property, 'errors' => $errors, 'tenants' => $tenants,
        ));
    }
}
