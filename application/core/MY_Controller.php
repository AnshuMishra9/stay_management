<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
}

/**
 * Authentication and role boundary for every protected page.
 *
 * Only the user id is treated as session identity. The full user and tenant
 * state is reloaded for every request so account/tenant revocations take
 * effect without waiting for the file session to expire.
 */
class Secure_Controller extends MY_Controller
{
    /** @var object */
    protected $auth_user;

    /** @var int|null */
    protected $current_tenant_id;

    /** @var object|null */
    protected $current_tenant;

    /** @var int|null */
    protected $current_property_id;

    /** @var object|null */
    protected $current_property;

    /** @var string */
    protected $property_context_token = '';

    /** @var array */
    protected $accessible_properties = array();

    /** @var bool */
    protected $property_context_revoked = FALSE;

    public function __construct()
    {
        parent::__construct();

        $this->load->model('User_model');
        $this->load->model('Tenant_model');
        $this->load->model('Property_model');

        $user_id = (int) $this->session->userdata('user_id');
        if ( ! $this->session->userdata('logged_in') || $user_id < 1) {
            $this->invalidate_login();
        }

        $user = $this->User_model->get_active_context_by_id($user_id);
        if ( ! $user) {
            $this->invalidate_login();
        }

        $this->auth_user = $user;
        $this->current_tenant_id = $user->tenant_id === NULL
            ? NULL
            : (int) $user->tenant_id;
        $this->current_tenant = $this->current_tenant_id
            ? $this->Tenant_model->get_by_id($this->current_tenant_id)
            : NULL;

        // Keep legacy session consumers working, while refreshing convenience
        // values from the authoritative database row.
        $session_data = array(
            'logged_in' => TRUE,
            'user_id'   => (int) $user->id,
            'mobile_no' => $user->mobile_no,
            'role'      => $user->role,
        );
        if ( ! $this->session->userdata('session_write_token')) {
            $session_data['session_write_token'] = $this->new_session_token();
        }
        $this->session->set_userdata($session_data);

        // Management/access pages do not require a property, but the shared
        // navbar still needs an authoritative selector and current context.
        $this->accessible_properties = $this->Property_model
            ->list_authorized_for_user($this->auth_user, TRUE);
        $active_property_id = (int) $this->session->userdata('active_property_id');
        if ($active_property_id > 0) {
            $active_property = $this->Property_model
                ->get_authorized_for_user($active_property_id, $this->auth_user, TRUE);
            if ($active_property) {
                $this->current_property_id = (int) $active_property->id;
                $this->current_property = $active_property;
                $this->property_context_token = (string) $this->session
                    ->userdata('property_context_token');
                if ($this->property_context_token === '') {
                    $this->property_context_token = $this->activate_property_context(
                        $this->current_property_id
                    );
                }
            } else {
                $this->clear_property_context();
                $this->property_context_revoked = TRUE;
                $this->session->set_flashdata('property_context_switched', $active_property_id);
            }
        }

        $this->load->vars(array(
            'auth_user'           => $this->auth_user,
            'current_tenant_id'   => $this->current_tenant_id,
            'current_tenant'      => $this->current_tenant,
            'current_property_id' => $this->current_property_id,
            'current_property'    => $this->current_property,
            'property_context_token' => $this->property_context_token,
            'accessible_properties' => $this->accessible_properties,
            'session_write_token' => $this->session->userdata('session_write_token'),
            'property_context_switched' => $this->session->flashdata('property_context_switched'),
        ));
    }

    /** Require one of the supplied role constants. */
    protected function require_role($roles)
    {
        $roles = is_array($roles) ? $roles : array($roles);
        if (in_array($this->auth_user->role, $roles, TRUE)) {
            return TRUE;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status'  => FALSE,
                    'code'    => 'role_forbidden',
                    'message' => 'You do not have permission to perform this action.',
                )));
            return FALSE;
        }

        show_error('You do not have permission to access this page.', 403, 'Access denied');
        return FALSE;
    }

    /** Session-bound CSRF token for new account/property management writes. */
    protected function require_session_write_token()
    {
        $expected = (string) $this->session->userdata('session_write_token');
        $received = (string) $this->input->post('session_write_token');
        if ($received === '' && isset($_SERVER['HTTP_X_SESSION_WRITE_TOKEN'])) {
            $received = (string) $_SERVER['HTTP_X_SESSION_WRITE_TOKEN'];
        }

        if ($expected !== '' && $received !== '' && hash_equals($expected, $received)) {
            return TRUE;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status'  => FALSE,
                    'code'    => 'invalid_write_token',
                    'message' => 'This form has expired. Reload the page and try again.',
                )));
            return FALSE;
        }

        show_error('This form has expired. Reload the page and try again.', 403, 'Invalid form token');
        return FALSE;
    }

    /** Set one session-wide property context and invalidate stale tab tokens. */
    protected function activate_property_context($property_id)
    {
        $token = $this->new_session_token();
        $this->session->set_userdata(array(
            'active_property_id'     => (int) $property_id,
            'property_context_token' => $token,
        ));
        // This upload token belongs to a rendered operational form. Rotating
        // property context must make old forms unusable.
        $this->session->unset_userdata('customer_write_token');
        return $token;
    }

    /** Clear a property context after access is revoked/deactivated. */
    protected function clear_property_context()
    {
        $this->session->unset_userdata('active_property_id');
        $this->session->set_userdata('property_context_token', $this->new_session_token());
        $this->session->unset_userdata('customer_write_token');
    }

    /** Require POST for state-changing management endpoints. */
    protected function require_post()
    {
        if (strtoupper($this->input->method(TRUE)) === 'POST') {
            return TRUE;
        }
        show_error('Method not allowed.', 405, 'Method not allowed');
        return FALSE;
    }

    protected function new_session_token()
    {
        return bin2hex(random_bytes(32));
    }

    private function invalidate_login()
    {
        $this->session->sess_destroy();
        redirect('login');
        exit;
    }
}

/** Active-property boundary for all existing hotel-operation controllers. */
class Property_Controller extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct();

        $property_id = (int) $this->session->userdata('active_property_id');
        if ($property_id < 1) {
            $this->redirect_for_missing_property($this->property_context_revoked);
        }

        $property = $this->current_property_id === $property_id
            ? $this->current_property
            : $this->Property_model->get_authorized_for_user($property_id, $this->auth_user, TRUE);
        if ( ! $property) {
            $this->clear_property_context();
            $this->redirect_for_missing_property(TRUE);
        }

        $this->current_property_id = (int) $property->id;
        $this->current_property = $property;
        // A super admin has no home tenant. Inside operational screens the
        // selected property's tenant is the only valid data boundary.
        $this->current_tenant_id = (int) $property->tenant_id;

        $token = (string) $this->session->userdata('property_context_token');
        if ($token === '') {
            $token = $this->activate_property_context($this->current_property_id);
        }
        $this->property_context_token = $token;

        $this->load->vars(array(
            'current_tenant_id'      => $this->current_tenant_id,
            'current_property_id'    => $this->current_property_id,
            'current_property'       => $this->current_property,
            'property_context_token' => $this->property_context_token,
            'accessible_properties'  => $this->accessible_properties,
        ));
    }

    /**
     * Fail closed when an AJAX request or write came from a stale browser tab.
     * Controllers call this before performing a scoped read/write.
     */
    protected function require_property_context_token()
    {
        $expected = (string) $this->property_context_token;
        $received = (string) $this->input->post('property_context_token');
        if ($received === '' && isset($_SERVER['HTTP_X_PROPERTY_CONTEXT_TOKEN'])) {
            $received = (string) $_SERVER['HTTP_X_PROPERTY_CONTEXT_TOKEN'];
        }

        if ($expected !== '' && $received !== '' && hash_equals($expected, $received)) {
            return TRUE;
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(409)
                ->set_header('Cache-Control: private, no-store, max-age=0')
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status'   => FALSE,
                    'code'     => 'property_context_changed',
                    'message'  => 'The active property changed. Reload this page before continuing.',
                    'redirect' => site_url('inventory'),
                )));
            return FALSE;
        }

        show_error(
            'The active property changed. Reload the page before continuing.',
            409,
            'Property context changed'
        );
        return FALSE;
    }

    private function redirect_for_missing_property($revoked)
    {
        $role = $this->auth_user->role;
        if (empty($this->accessible_properties)) {
            $target = $role === User_model::ROLE_USER
                ? 'access/no-properties'
                : 'properties';
        } else {
            $target = 'properties/select';
        }

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_status_header(409)
                ->set_header('Cache-Control: private, no-store, max-age=0')
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status'   => FALSE,
                    'code'     => $revoked ? 'property_access_revoked' : 'property_context_required',
                    'message'  => $revoked
                        ? 'Access to the active property is no longer available.'
                        : 'Choose a property before continuing.',
                    'redirect' => site_url($target),
                )));
            exit;
        }

        if ($revoked) {
            $this->session->set_flashdata('access_msg', array(
                'type' => 'danger',
                'text' => 'Access to the previous property is no longer available. Choose another property.',
            ));
        }
        redirect($target);
        exit;
    }
}

// CodeIgniter loads MY_Controller only; load the second-level base explicitly.
require_once(APPPATH.'core/Ops_Controller.php');


