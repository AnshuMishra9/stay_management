<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * RoomMaster (Dashboard)
 *
 * Protected page — extends Secure_Controller, so an authenticated session is
 * required. Direct URL access without logging in redirects to the login page.
 */
class RoomMaster extends Secure_Controller
{
    public function index()
    {
        $data = array(
            'mobile_no' => $this->session->userdata('mobile_no'),
        );

        $this->load->view('room_master', $data);
    }
}
