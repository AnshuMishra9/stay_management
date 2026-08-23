<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Room-category setup available to every user authorized for the property. */
class RoomCategories extends Property_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_category_model');
    }

    public function index()
    {
        $this->load->view('room_categories/list', array(
            'categories' => $this->Room_category_model->get_all($this->current_property_id),
            'flash' => $this->session->flashdata('category_msg'),
        ));
    }

    public function form($id = NULL)
    {
        $category = NULL;
        if ($id !== NULL) {
            $category = $this->Room_category_model->get_by_id($this->current_property_id, $id);
            if ( ! $category) {
                show_404();
                return;
            }
        }
        $this->load->view('room_categories/form', array(
            'category' => $category,
            'taxes' => $this->Room_category_model->taxes(),
        ));
    }

    public function save()
    {
        if ( ! $this->require_post() || ! $this->require_property_context_token()) {
            return;
        }
        $id = (int) $this->input->post('category_id');
        $existing = $id
            ? $this->Room_category_model->get_by_id($this->current_property_id, $id)
            : NULL;
        if ($id && ! $existing) {
            show_404();
            return;
        }

        $this->load->library('form_validation');
        $this->form_validation->set_rules(
            'category_name',
            'Category Name',
            'required|trim|max_length[100]|callback_unique_category_name['.$id.']'
        );
        $this->form_validation->set_rules(
            'short_code',
            'Short Code',
            'trim|max_length[20]|callback_unique_category_short_code['.$id.']'
        );
        $this->form_validation->set_rules('max_adults', 'Max Adults', 'trim|integer|greater_than_equal_to[0]');
        $this->form_validation->set_rules('max_children', 'Max Children', 'trim|integer|greater_than_equal_to[0]');
        $this->form_validation->set_rules('bed_count', 'Bed Count', 'trim|integer|greater_than_equal_to[0]');
        $this->form_validation->set_rules('display_order', 'Display Order', 'trim|integer|greater_than_equal_to[0]');
        if ($this->form_validation->run() === FALSE) {
            $this->load->view('room_categories/form', array(
                'category' => $existing,
                'taxes' => $this->Room_category_model->taxes(),
            ));
            return;
        }

        $short_code = trim((string) $this->input->post('short_code', TRUE));
        $data = array(
            'category_name' => trim((string) $this->input->post('category_name', TRUE)),
            'short_code' => $short_code === '' ? NULL : $short_code,
            'description' => $this->input->post('description', TRUE),
            'max_adults' => $this->_int_or_null($this->input->post('max_adults')),
            'max_children' => $this->_int_or_null($this->input->post('max_children')),
            'room_size' => $this->input->post('room_size', TRUE),
            'room_size_unit' => $this->input->post('room_size_unit', TRUE) ?: 'sq.ft',
            'bed_type' => $this->input->post('bed_type', TRUE),
            'bed_count' => $this->_int_or_null($this->input->post('bed_count')),
            'smoking_allowed' => $this->input->post('smoking_allowed') ? 1 : 0,
            'default_tax_id' => $this->_int_or_null($this->input->post('default_tax_id')),
            'default_sac_code' => $this->input->post('default_sac_code', TRUE),
            'display_order' => (int) $this->input->post('display_order'),
            'status' => $this->input->post('status') ? 1 : 0,
        );

        if ($id) {
            $this->Room_category_model->update($this->current_property_id, $id, $data);
            $message = 'Room category updated successfully.';
        } else {
            $this->Room_category_model->insert($this->current_property_id, $data);
            $message = 'Room category created successfully.';
        }
        $this->session->set_flashdata('category_msg', array('type' => 'success', 'text' => $message));
        redirect('room-categories');
    }

    public function delete($id = NULL)
    {
        if ( ! $this->require_post() || ! $this->require_property_context_token()) {
            return;
        }
        $category = $id
            ? $this->Room_category_model->get_by_id($this->current_property_id, $id)
            : NULL;
        if ( ! $category) {
            show_404();
            return;
        }
        $result = $this->Room_category_model->delete_or_deactivate(
            $this->current_property_id,
            $id
        );
        $message = 'Room category was deactivated. No data was deleted.';
        $this->session->set_flashdata('category_msg', array(
            'type' => $result ? 'success' : 'danger',
            'text' => $result ? $message : 'Room category could not be removed.',
        ));
        redirect('room-categories');
    }

    public function unique_category_name($name, $except_id)
    {
        if ($this->Room_category_model->name_exists(
            $this->current_property_id,
            trim($name),
            (int) $except_id
        )) {
            $this->form_validation->set_message(
                'unique_category_name',
                'This category name already exists in the selected property.'
            );
            return FALSE;
        }
        return TRUE;
    }

    public function unique_category_short_code($short_code, $except_id)
    {
        if ($this->Room_category_model->short_code_exists(
            $this->current_property_id,
            $short_code,
            (int) $except_id
        )) {
            $this->form_validation->set_message(
                'unique_category_short_code',
                'This short code already exists in the selected property.'
            );
            return FALSE;
        }
        return TRUE;
    }

    private function _int_or_null($value)
    {
        return ($value === NULL || $value === '') ? NULL : (int) $value;
    }
}
