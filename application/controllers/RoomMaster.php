<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class RoomMaster extends Property_Controller
{
    public function index()
    {
        $data = array(
            'mobile_no' => $this->session->userdata('mobile_no'),
        );

        $this->load->view('room_master', $data);
    }
}
