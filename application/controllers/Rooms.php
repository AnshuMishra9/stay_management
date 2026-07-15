<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rooms (Room Manager / Room Master)
 *
 * Protected module (extends Secure_Controller) — an authenticated session is
 * required for every action. Built to mirror the Customers Master.
 *
 *   index()        -> renders the list grid page
 *   list_ajax()    -> [AJAX] JSON of filtered rooms (live filtering)
 *   view($id)      -> [AJAX] JSON of one room (all fields)
 *   form($id=null) -> renders the Add / Edit form (prefilled when editing)
 *   save()         -> [POST] insert or update
 *   delete($id)    -> [AJAX/POST] delete row
 */
class Rooms extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_model');
    }

    // ---------------------------------------------------------------------
    //  Pages
    // ---------------------------------------------------------------------

    /**
     * Room Master list page.
     */
    public function index()
    {
        $data = array(
            'categories'  => $this->Room_model->all_categories(),
            'floors'      => $this->Room_model->distinct_values('floor_no'),
            'hk_statuses' => $this->_housekeeping_options(),
            'flash'       => $this->session->flashdata('room_msg'),
        );
        $this->load->view('rooms/list', $data);
    }

    /**
     * Add / Edit form. Pass an id to edit; omit to add.
     */
    public function form($id = NULL)
    {
        $room = NULL;

        if ($id !== NULL) {
            $room = $this->Room_model->get_by_id($id);
            if ( ! $room) {
                show_404();
                return;
            }
        }

        $this->load->view('rooms/form', $this->_form_data($room));
    }

    // ---------------------------------------------------------------------
    //  AJAX / JSON endpoints
    // ---------------------------------------------------------------------

    /**
     * Return filtered rooms as JSON (consumed by the live filter UI).
     */
    public function list_ajax()
    {
        $filters = array(
            'room_no'             => $this->input->get('room_no'),
            'category_id'         => $this->input->get('category_id'),
            'floor_no'            => $this->input->get('floor_no'),
            'housekeeping_status' => $this->input->get('housekeeping_status'),
            'status'              => $this->input->get('status'),
        );

        $rows = $this->Room_model->get_filtered($filters);

        return $this->_json(array('status' => TRUE, 'data' => $rows));
    }

    /**
     * Return one room's full detail (all fields + amenities + image URL).
     */
    public function view($id = NULL)
    {
        $room = $id ? $this->Room_model->get_by_id($id) : NULL;
        if ( ! $room) {
            return $this->_json(array('status' => FALSE, 'message' => 'Room not found.'));
        }

        return $this->_json(array('status' => TRUE, 'data' => $room));
    }

    // ---------------------------------------------------------------------
    //  Writes
    // ---------------------------------------------------------------------

    /**
     * Insert or update a room (multipart form submit).
     */
    public function save()
    {
        $id       = (int) $this->input->post('id');
        $is_edit  = $id > 0;
        $existing = $is_edit ? $this->Room_model->get_by_id($id) : NULL;

        if ($is_edit && ! $existing) {
            show_404();
            return;
        }

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('room_no', 'Room Name / Number',
            'required|trim|max_length[30]|callback_unique_room_no['.$id.']');
        $this->form_validation->set_rules('category_id', 'Category', 'required|integer');
        $this->form_validation->set_rules('selling_price', 'Price', 'trim|numeric|greater_than_equal_to[0]');

        // Friendlier messages (match Customer Master tone).
        $this->form_validation->set_message('greater_than_equal_to', 'The {field} cannot be negative.');

        if ($this->form_validation->run() === FALSE) {
            $this->load->view('rooms/form', $this->_form_data($existing));
            return;
        }

        // Immutable code: keep on edit, generate fresh on add (never trust POST).
        $code = $is_edit ? $existing->room_code : $this->Room_model->next_code();
        $user = $this->session->userdata('mobile_no');

        // --- Scalar fields ----------------------------------------------
        $data = array(
            'room_no'             => $this->input->post('room_no', TRUE),   // "Room Name / Number"
            'category_id'         => (int) $this->input->post('category_id') ?: NULL,
            'floor_no'            => $this->input->post('floor_no', TRUE),
            'description'         => $this->input->post('description', TRUE),
            'remarks'             => $this->input->post('remarks', TRUE),
            'extra_bed_allowed'   => $this->input->post('extra_bed_allowed') ? 1 : 0,
            'selling_price'       => $this->_num_or_null($this->input->post('selling_price')),
            'housekeeping_status' => $this->input->post('housekeeping_status', TRUE),
            'is_active'           => $this->input->post('is_active') ? 1 : 0,
        );

        // --- Persist -----------------------------------------------------
        if ($is_edit) {
            $data['updated_by'] = $user;
            $this->Room_model->update($id, $data);
            $msg = 'Room "'.$data['room_no'].'" updated successfully.';
        } else {
            $data['room_code']  = $code;
            $data['created_by'] = $user;
            $this->Room_model->insert($data);
            $msg = 'Room "'.$data['room_no'].'" ('.$code.') added successfully.';
        }

        $this->session->set_flashdata('room_msg', array('type' => 'success', 'text' => $msg));
        redirect('rooms');
    }

    /**
     * Delete a room.
     */
    public function delete($id = NULL)
    {
        $room = $id ? $this->Room_model->get_by_id($id) : NULL;
        if ( ! $room) {
            return $this->_json(array('status' => FALSE, 'message' => 'Room not found.'));
        }

        $this->Room_model->delete($id);

        return $this->_json(array(
            'status'  => TRUE,
            'message' => 'Room "'.$room->room_no.'" deleted.',
        ));
    }

    // ---------------------------------------------------------------------
    //  Validation callbacks
    // ---------------------------------------------------------------------

    /**
     * Ensure the room number is unique (excluding the row being edited).
     */
    public function unique_room_no($room_no, $except_id)
    {
        if ($this->Room_model->room_no_exists(trim($room_no), (int) $except_id)) {
            $this->form_validation->set_message('unique_room_no', 'This Room Number already exists.');
            return FALSE;
        }
        return TRUE;
    }

    // ---------------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------------

    /**
     * Shared data bundle for the Add/Edit form view.
     */
    private function _form_data($room)
    {
        return array(
            'room'       => $room,
            'next_code'  => $room ? $room->room_code : $this->Room_model->next_code(),
            'categories' => $this->Room_model->all_categories(),
            'hk_opts'    => $this->_housekeeping_options(),
        );
    }

    /** JSON output helper. */
    private function _json($payload)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

    /** Cast to numeric string, or NULL when blank. */
    private function _num_or_null($v)
    {
        return ($v === NULL || $v === '' || ! is_numeric($v)) ? NULL : $v;
    }

    // ---- Static dropdown option lists (extend as needed) ----

    private function _housekeeping_options()
    {
        return array('Available', 'Not Available');
    }
}
