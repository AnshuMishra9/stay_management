<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Authenticated access-state pages that do not require a property context. */
class Access extends Secure_Controller
{
    public function no_properties()
    {
        $this->load->model('Property_model');
        $properties = $this->Property_model->list_authorized_for_user($this->auth_user, TRUE);
        if ( ! empty($properties)) {
            redirect('properties/select');
            return;
        }
        if ($this->auth_user->role !== User_model::ROLE_USER) {
            redirect('properties');
            return;
        }
        $this->output->set_status_header(403);
        $this->load->view('access/no_properties');
    }

    public function forbidden()
    {
        $this->output->set_status_header(403);
        $this->load->view('access/forbidden');
    }
}
