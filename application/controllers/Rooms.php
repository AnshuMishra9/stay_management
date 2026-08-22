<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Property-scoped Room Master. */
class Rooms extends Property_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_model');
    }

    public function index()
    {
        $data = array(
            'categories' => $this->Room_model->all_categories($this->current_property_id),
            'floors' => $this->Room_model->distinct_values($this->current_property_id, 'floor_no'),
            'hk_statuses' => $this->_housekeeping_options(),
            'flash' => $this->session->flashdata('room_msg'),
        );
        $this->load->view('rooms/list', $data);
    }

    public function form($id = NULL)
    {
        $room = NULL;
        if ($id !== NULL) {
            $room = $this->Room_model->get_by_id($this->current_property_id, $id);
            if ( ! $room) {
                show_404();
                return;
            }
        }
        $this->load->view('rooms/form', $this->_form_data($room));
    }

    public function list_ajax()
    {
        if ( ! $this->require_property_context_token()) {
            return;
        }

        $filters = array(
            'room_no' => $this->input->get('room_no'),
            'category_id' => $this->input->get('category_id'),
            'floor_no' => $this->input->get('floor_no'),
            'housekeeping_status' => $this->input->get('housekeeping_status'),
            'status' => $this->input->get('status'),
        );
        return $this->_json(array(
            'status' => TRUE,
            'data' => $this->Room_model->get_filtered($this->current_property_id, $filters),
        ));
    }

    public function view($id = NULL)
    {
        if ( ! $this->require_property_context_token()) {
            return;
        }

        $room = $id ? $this->Room_model->get_by_id($this->current_property_id, $id) : NULL;
        if ( ! $room) {
            return $this->_json(array('status' => FALSE, 'message' => 'Room not found.'), 404);
        }
        return $this->_json(array('status' => TRUE, 'data' => $room));
    }

    public function save()
    {
        if ( ! $this->require_post() || ! $this->require_property_context_token()) {
            return;
        }

        $id = (int) $this->input->post('id');
        $is_edit = $id > 0;
        $existing = $is_edit
            ? $this->Room_model->get_by_id($this->current_property_id, $id)
            : NULL;
        if ($is_edit && ! $existing) {
            show_404();
            return;
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules(
            'room_no', 'Room Name / Number',
            'required|trim|max_length[30]|callback_unique_room_no['.$id.']'
        );
        $this->form_validation->set_rules(
            'category_id', 'Category', 'trim|callback_valid_room_category'
        );
        $this->form_validation->set_rules(
            'selling_price', 'Price', 'trim|numeric|greater_than_equal_to[0]'
        );
        $this->form_validation->set_message('greater_than_equal_to', 'The {field} cannot be negative.');

        if ($this->form_validation->run() === FALSE) {
            $this->load->view('rooms/form', $this->_form_data($existing));
            return;
        }

        $code = $is_edit
            ? $existing->room_code
            : $this->Room_model->next_code($this->current_property_id);
        $mobile = $this->auth_user->mobile_no;
        $category_id = $this->input->post('category_id', TRUE);
        $category_id = is_scalar($category_id) ? trim((string) $category_id) : '';
        $data = array(
            'room_no' => $this->input->post('room_no', TRUE),
            'category_id' => ($category_id === NULL || $category_id === '')
                ? NULL
                : (int) $category_id,
            'floor_no' => $this->input->post('floor_no', TRUE),
            'description' => $this->input->post('description', TRUE),
            'remarks' => $this->input->post('remarks', TRUE),
            'extra_bed_allowed' => $this->input->post('extra_bed_allowed') ? 1 : 0,
            'selling_price' => $this->_num_or_null($this->input->post('selling_price')),
            'housekeeping_status' => $this->input->post('housekeeping_status', TRUE),
            'is_active' => $this->input->post('is_active') ? 1 : 0,
        );

        if ($is_edit) {
            $data['updated_by'] = $mobile;
            $this->Room_model->update($this->current_property_id, $id, $data);
            $message = 'Room "'.$data['room_no'].'" updated successfully.';
        } else {
            $data['room_code'] = $code;
            $data['created_by'] = $mobile;
            $this->Room_model->insert($this->current_property_id, $data);
            $message = 'Room "'.$data['room_no'].'" ('.$code.') added successfully.';
        }
        $this->session->set_flashdata('room_msg', array('type' => 'success', 'text' => $message));
        redirect('rooms');
    }

    public function delete($id = NULL)
    {
        if ( ! $this->require_post() || ! $this->require_property_context_token()) {
            return;
        }
        $room = $id ? $this->Room_model->get_by_id($this->current_property_id, $id) : NULL;
        if ( ! $room) {
            return $this->_json(array('status' => FALSE, 'message' => 'Room not found.'), 404);
        }
        $result = $this->Room_model->delete_or_deactivate($this->current_property_id, $id);
        if ( ! $result) {
            return $this->_json(array('status' => FALSE, 'message' => 'Room could not be removed.'), 409);
        }
        $message = $result === 'deleted'
            ? 'Room "'.$room->room_no.'" deleted.'
            : 'Room "'.$room->room_no.'" has booking history and was deactivated.';
        return $this->_json(array('status' => TRUE, 'message' => $message, 'action' => $result));
    }

    public function unique_room_no($room_no, $except_id)
    {
        if ($this->Room_model->room_no_exists(
            $this->current_property_id,
            trim($room_no),
            (int) $except_id
        )) {
            $this->form_validation->set_message('unique_room_no', 'This Room Number already exists in the selected property.');
            return FALSE;
        }
        return TRUE;
    }

    public function valid_room_category($category_id)
    {
        // A category is optional: Room Master must be usable immediately for
        // a new property, even before any category master data exists.
        if ($category_id === NULL || (is_scalar($category_id) && trim((string) $category_id) === '')) {
            return TRUE;
        }
        if ( ! is_scalar($category_id) || ! ctype_digit((string) $category_id) || (int) $category_id <= 0) {
            $this->form_validation->set_message('valid_room_category', 'Select a valid category or leave it empty.');
            return FALSE;
        }
        if ( ! $this->Room_model->category_belongs_to_property(
            $this->current_property_id,
            $category_id,
            TRUE
        )) {
            $this->form_validation->set_message('valid_room_category', 'Select an active category from the current property.');
            return FALSE;
        }
        return TRUE;
    }

    private function _form_data($room)
    {
        return array(
            'room' => $room,
            'next_code' => $room
                ? $room->room_code
                : $this->Room_model->next_code($this->current_property_id),
            'categories' => $this->Room_model->all_categories($this->current_property_id),
            'hk_opts' => $this->_housekeeping_options(),
        );
    }

    private function _json($payload, $status = 200)
    {
        return $this->output
            ->set_status_header((int) $status)
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

    private function _num_or_null($value)
    {
        return ($value === NULL || $value === '' || ! is_numeric($value)) ? NULL : $value;
    }

    private function _housekeeping_options()
    {
        return array('Available', 'Not Available');
    }
}
