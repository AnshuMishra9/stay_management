<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rooms (Room Manager / Room Master)
 *
 * Protected module (extends Secure_Controller) — an authenticated session is
 * required for every action, including document streaming. Built to mirror the
 * Customers Master (same architecture, UI shell and secure-upload pattern).
 *
 *   index()        -> renders the list grid page
 *   list_ajax()    -> [AJAX] JSON of filtered rooms (live filtering)
 *   view($id)      -> [AJAX] JSON of one room, all fields + amenities + image URL
 *   form($id=null) -> renders the Add / Edit form (prefilled when editing)
 *   save()         -> [POST] insert or update + amenities + secure image upload
 *   delete($id)    -> [AJAX/POST] delete row + remove the room's files
 *   file($id)      -> streams the room image from outside the web root
 */
class Rooms extends Secure_Controller
{
    /** Allowed upload extensions / size (KB). */
    const UPLOAD_TYPES  = 'jpg|jpeg|png|pdf';
    const UPLOAD_MAX_KB = 4096;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_model');
        $this->load->helper('file');
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
            'wings'       => $this->Room_model->distinct_values('wing'),
            'hk_statuses' => $this->_housekeeping_options(),
            'conditions'  => $this->_condition_options(),
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
            'room_name'           => $this->input->get('room_name'),
            'category_id'         => $this->input->get('category_id'),
            'floor_no'            => $this->input->get('floor_no'),
            'wing'                => $this->input->get('wing'),
            'housekeeping_status' => $this->input->get('housekeeping_status'),
            'room_condition'      => $this->input->get('room_condition'),
            'smoking'             => $this->input->get('smoking'),
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

        $room->amenities = $this->Room_model->room_amenities($room->id);
        $room->image_url = $room->image_path
            ? site_url('rooms/file/'.$room->id) : NULL;

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
        $this->form_validation->set_rules('room_no', 'Room Number',
            'required|trim|max_length[30]|regex_match[/^\S+$/]|callback_unique_room_no['.$id.']');
        $this->form_validation->set_rules('room_name', 'Room Name', 'trim|max_length[150]');
        $this->form_validation->set_rules('category_id', 'Category', 'required|integer');
        $this->form_validation->set_rules('selling_price', 'Selling Price', 'trim|numeric|greater_than_equal_to[0]');
        $this->form_validation->set_rules('base_price', 'Base Price', 'trim|numeric|greater_than_equal_to[0]');

        // Friendlier messages (match Customer Master tone).
        $this->form_validation->set_message('regex_match', 'The {field} cannot contain spaces.');
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
            'room_no'             => $this->input->post('room_no', TRUE),
            'room_name'           => $this->input->post('room_name', TRUE),
            'category_id'         => (int) $this->input->post('category_id') ?: NULL,
            'floor_no'            => $this->input->post('floor_no', TRUE),
            'wing'                => $this->input->post('wing', TRUE),
            'room_size'           => $this->input->post('room_size', TRUE),
            'room_size_unit'      => $this->input->post('room_size_unit', TRUE),
            'description'         => $this->input->post('description', TRUE),
            'remarks'             => $this->input->post('remarks', TRUE),

            'max_adults'          => $this->_int_or_null($this->input->post('max_adults')),
            'max_children'        => $this->_int_or_null($this->input->post('max_children')),
            'bed_type'            => $this->input->post('bed_type', TRUE),
            'bed_count'           => $this->_int_or_null($this->input->post('bed_count')),
            'bed_size'            => $this->input->post('bed_size', TRUE),
            'extra_bed_allowed'   => $this->input->post('extra_bed_allowed') ? 1 : 0,
            'accessible_room'     => $this->input->post('accessible_room') ? 1 : 0,
            'connected_room'      => $this->input->post('connected_room', TRUE),
            'smoking'             => $this->input->post('smoking') ? 1 : 0,
            'balcony'             => $this->input->post('balcony') ? 1 : 0,
            'window_view'         => $this->input->post('window_view', TRUE),

            'base_price'          => $this->_num_or_null($this->input->post('base_price')),
            'selling_price'       => $this->_num_or_null($this->input->post('selling_price')),
            'tax_id'              => (int) $this->input->post('tax_id') ?: NULL,
            'sac_code'            => $this->input->post('sac_code', TRUE),
            'extra_person_charge' => $this->_num_or_null($this->input->post('extra_person_charge')),
            'child_charge'        => $this->_num_or_null($this->input->post('child_charge')),
            'effective_from'      => $this->_date_or_null($this->input->post('effective_from')),
            'effective_to'        => $this->_date_or_null($this->input->post('effective_to')),

            'housekeeping_status' => $this->input->post('housekeeping_status', TRUE),
            'room_condition'      => $this->input->post('room_condition', TRUE),
            'room_phone'          => $this->input->post('room_phone', TRUE),
            'is_active'           => $this->input->post('is_active') ? 1 : 0,
        );

        $amenity_ids = $this->input->post('amenities');
        $amenity_ids = is_array($amenity_ids) ? $amenity_ids : array();

        // --- Image upload (replace old file if a new one is provided) ----
        $upload_error = NULL;
        $image_path   = $this->_handle_upload('room_image', $code, 'room_image',
                            $existing ? $existing->image_path : NULL, $upload_error);

        if ($upload_error !== NULL) {
            $this->session->set_flashdata('room_msg', array('type' => 'danger', 'text' => $upload_error));
            redirect($is_edit ? 'rooms/form/'.$id : 'rooms/form');
            return;
        }
        if ($image_path !== NULL) { $data['image_path'] = $image_path; }

        // --- Persist -----------------------------------------------------
        if ($is_edit) {
            $data['updated_by'] = $user;
            $this->Room_model->update($id, $data, $amenity_ids);
            $msg = 'Room "'.$data['room_no'].'" updated successfully.';
        } else {
            $data['room_code']  = $code;
            $data['created_by'] = $user;
            $this->Room_model->insert($data, $amenity_ids);
            $msg = 'Room "'.$data['room_no'].'" ('.$code.') added successfully.';
        }

        $this->session->set_flashdata('room_msg', array('type' => 'success', 'text' => $msg));
        redirect('rooms');
    }

    /**
     * Delete a room and remove its uploaded image/folder.
     */
    public function delete($id = NULL)
    {
        $room = $id ? $this->Room_model->get_by_id($id) : NULL;
        if ( ! $room) {
            return $this->_json(array('status' => FALSE, 'message' => 'Room not found.'));
        }

        // Remove the whole per-room upload folder (files + directory).
        $dir = SECURE_UPLOAD_PATH.'rooms'.DIRECTORY_SEPARATOR.$room->room_code;
        if (is_dir($dir)) {
            delete_files($dir, TRUE);   // recursive contents
            @rmdir($dir);
        }

        $this->Room_model->delete($id);   // room_amenities cascade via FK

        return $this->_json(array(
            'status'  => TRUE,
            'message' => 'Room "'.$room->room_no.'" deleted.',
        ));
    }

    // ---------------------------------------------------------------------
    //  Secure document streaming
    // ---------------------------------------------------------------------

    /**
     * Stream the uploaded room image. Files live outside the public tree;
     * access is only possible here, behind the auth guard.
     *
     * @param int $id
     */
    public function file($id = NULL)
    {
        $room = $id ? $this->Room_model->get_by_id($id) : NULL;
        if ( ! $room || empty($room->image_path)) { show_404(); return; }

        // Resolve + confine the path to the secure base (defence in depth).
        $rel = str_replace(array('\\', '..'), array('/', ''), $room->image_path);
        $abs = SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $rel);

        if ( ! is_file($abs)) { show_404(); return; }

        $mimes = array(
            'pdf' => 'application/pdf', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        );
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Disposition: inline; filename="'.basename($abs).'"')
            ->set_header('Content-Length: '.filesize($abs))
            ->set_output(file_get_contents($abs));
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
            'room'           => $room,
            'next_code'      => $room ? $room->room_code : $this->Room_model->next_code(),
            'categories'     => $this->Room_model->all_categories(),
            'taxes'          => $this->Room_model->all_taxes(),
            'amenities'      => $this->Room_model->all_amenities(),
            'selected_amen'  => $room ? $room->amenity_ids : array(),
            'bed_opts'       => $this->_bed_type_options(),
            'unit_opts'      => $this->_size_unit_options(),
            'view_opts'      => $this->_window_view_options(),
            'hk_opts'        => $this->_housekeeping_options(),
            'cond_opts'      => $this->_condition_options(),
        );
    }

    /**
     * Upload the room image into the room's secure folder, replacing any
     * previous file. Returns the DB-relative path, or NULL when no new file
     * was submitted. Sets $error on failure.
     */
    private function _handle_upload($field, $code, $base_name, $old_path, &$error)
    {
        if (empty($_FILES[$field]['name'])) {
            return NULL; // nothing uploaded
        }

        $dir = SECURE_UPLOAD_PATH.'rooms'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR;
        if ( ! is_dir($dir) && ! @mkdir($dir, 0755, TRUE)) {
            $error = 'Could not create the upload folder. Check permissions.';
            return NULL;
        }

        // Remove any previous file(s) for this slot (any extension).
        foreach (glob($dir.$base_name.'.*') as $prev) {
            @unlink($prev);
        }
        if ($old_path) {
            $old_abs = SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $old_path);
            if (is_file($old_abs)) { @unlink($old_abs); }
        }

        $config = array(
            'upload_path'   => $dir,
            'allowed_types' => self::UPLOAD_TYPES,
            'max_size'      => self::UPLOAD_MAX_KB,
            'file_name'     => $base_name,
            'overwrite'     => TRUE,
        );

        $this->load->library('upload');
        $this->upload->initialize($config);

        if ( ! $this->upload->do_upload($field)) {
            $error = strip_tags($this->upload->display_errors('', ''));
            return NULL;
        }

        $info = $this->upload->data();
        return 'rooms/'.$code.'/'.$info['file_name'];
    }

    /** JSON output helper. */
    private function _json($payload)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

    /** Cast to int, or NULL when blank (keeps optional numeric columns clean). */
    private function _int_or_null($v)
    {
        return ($v === NULL || $v === '') ? NULL : (int) $v;
    }

    /** Cast to numeric string, or NULL when blank. */
    private function _num_or_null($v)
    {
        return ($v === NULL || $v === '' || ! is_numeric($v)) ? NULL : $v;
    }

    /** Return a valid Y-m-d date, or NULL. */
    private function _date_or_null($v)
    {
        return ($v === NULL || $v === '') ? NULL : $v;
    }

    // ---- Static dropdown option lists (extend as needed) ----

    private function _bed_type_options()
    {
        return array('Single', 'Twin', 'Double', 'Queen', 'King', 'Sofa Bed', 'Bunk Bed');
    }

    private function _size_unit_options()
    {
        return array('sq.ft', 'sq.m');
    }

    private function _window_view_options()
    {
        return array('City', 'Garden', 'Pool', 'Sea', 'Mountain', 'Courtyard', 'None');
    }

    private function _housekeeping_options()
    {
        return array('Clean', 'Dirty', 'Inspected', 'Out of Service');
    }

    private function _condition_options()
    {
        return array('Good', 'Fair', 'Under Maintenance', 'Damaged');
    }
}
