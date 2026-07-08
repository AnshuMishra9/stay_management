<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customers (Master)
 *
 * Protected module (extends Secure_Controller) — an authenticated session is
 * required for every action, including document streaming.
 *
 *   index()        -> renders the list grid page
 *   list_ajax()    -> [AJAX] JSON of filtered customers (live filtering)
 *   view($id)      -> [AJAX] JSON of one customer, all fields + document URLs
 *   form($id=null) -> renders the Add / Edit form (prefilled when editing)
 *   save()         -> [POST] insert or update + handle secure file uploads
 *   delete($id)    -> [AJAX/POST] delete row + remove the customer's files
 *   file($id,$t)   -> streams an Aadhar/PAN document from outside the web root
 */
class Customers extends Secure_Controller
{
    /** Allowed upload extensions / size (KB). */
    const UPLOAD_TYPES   = 'jpg|jpeg|png|pdf';
    const UPLOAD_MAX_KB  = 4096;

    /**
     * Booking lifecycle stages (must match the `booking_status` ENUM in the DB).
     * value => human label shown in the dropdown / list badge.
     */
    const BOOKING_STATUSES = array(
        'enquiry', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Customer_model');
        $this->load->helper('file');
    }

    // ---------------------------------------------------------------------
    //  Pages
    // ---------------------------------------------------------------------

    /**
     * Customers Master list page.
     */
    public function index()
    {
        $data = array(
            'types'  => $this->Customer_model->distinct_values('customer_type'),
            'states' => $this->Customer_model->distinct_values('state'),
            'booking_statuses' => $this->_booking_status_options(),
            'flash'  => $this->session->flashdata('customer_msg'),
        );
        $this->load->view('customers/list', $data);
    }

    /**
     * Add / Edit form. Pass an id to edit; omit to add.
     */
    public function form($id = NULL)
    {
        $customer = NULL;

        if ($id !== NULL) {
            $customer = $this->Customer_model->get_by_id($id);
            if ( ! $customer) {
                show_404();
                return;
            }
        }

        $data = array(
            'customer'   => $customer,
            'next_code'  => $customer ? $customer->customer_code : $this->Customer_model->next_code(),
            'type_opts'  => $this->_type_options(),
            'state_opts' => $this->_state_options(),
            'country_opts' => $this->_country_options(),
            'channel_opts'  => $this->Customer_model->booking_channels(),
            'room_cat_opts' => $this->Customer_model->room_categories(),
            'status_opts'   => $this->_booking_status_options(),
        );
        $this->load->view('customers/form', $data);
    }

    /**
     * Booking Details list page — a booking-centric view over customers,
     * filterable by check-in/out date, status, channel, guest, etc.
     */
    public function bookings()
    {
        $data = array(
            'booking_statuses' => $this->_booking_status_options(),
            'channel_opts'     => $this->Customer_model->booking_channels(),
        );
        $this->load->view('customers/bookings', $data);
    }

    // ---------------------------------------------------------------------
    //  AJAX / JSON endpoints
    // ---------------------------------------------------------------------

    /**
     * Return filtered customers as JSON (consumed by the live filter UI).
     */
    public function list_ajax()
    {
        $filters = array(
            'customer_code' => $this->input->get('customer_code'),
            'name'          => $this->input->get('name'),
            'owner'         => $this->input->get('owner'),
            'phone'         => $this->input->get('phone'),
            'city'          => $this->input->get('city'),
            'district'      => $this->input->get('district'),
            'state'         => $this->input->get('state'),
            'customer_type' => $this->input->get('customer_type'),
            'booking_status' => $this->input->get('booking_status'),
            'status'        => $this->input->get('status'),
        );

        $rows = $this->Customer_model->get_filtered($filters);

        return $this->_json(array('status' => TRUE, 'data' => $rows));
    }

    /**
     * [AJAX] JSON of bookings filtered by booking-specific criteria
     * (consumed by the Booking Details live-filter UI).
     */
    public function bookings_ajax()
    {
        $filters = array(
            'q'                  => $this->input->get('q'),
            'booking_status'     => $this->input->get('booking_status'),
            'booking_channel_id' => $this->input->get('booking_channel_id'),
            'checkin_from'       => $this->input->get('checkin_from'),
            'checkin_to'         => $this->input->get('checkin_to'),
            'checkout_from'      => $this->input->get('checkout_from'),
            'checkout_to'        => $this->input->get('checkout_to'),
        );

        $rows = $this->Customer_model->get_bookings($filters);

        return $this->_json(array('status' => TRUE, 'data' => $rows));
    }

    /**
     * Return one customer's full detail (all fields + document URLs).
     */
    public function view($id = NULL)
    {
        $customer = $id ? $this->Customer_model->get_by_id($id) : NULL;
        if ( ! $customer) {
            return $this->_json(array('status' => FALSE, 'message' => 'Customer not found.'));
        }

        // Attach streamed-document URLs only when a file exists.
        $customer->aadhar_url = $customer->aadhar_card_path
            ? site_url('customers/file/'.$customer->id.'/aadhar') : NULL;
        $customer->pan_url = $customer->pan_card_path
            ? site_url('customers/file/'.$customer->id.'/pan') : NULL;

        return $this->_json(array('status' => TRUE, 'data' => $customer));
    }

    // ---------------------------------------------------------------------
    //  Writes
    // ---------------------------------------------------------------------

    /**
     * Insert or update a customer (multipart form submit).
     */
    public function save()
    {
        $id       = (int) $this->input->post('id');
        $is_edit  = $id > 0;
        $existing = $is_edit ? $this->Customer_model->get_by_id($id) : NULL;

        if ($is_edit && ! $existing) {
            show_404();
            return;
        }

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('customer_name', 'Customer Name', 'required|trim|max_length[150]');
        $this->form_validation->set_rules('phone', 'Mobile No', 'required|trim|max_length[20]');
        $this->form_validation->set_rules('email', 'Email', 'trim|valid_email|max_length[150]');

        if ($this->form_validation->run() === FALSE) {
            // Re-render the form with errors + submitted values.
            $data = array(
                'customer'     => $existing,
                'next_code'    => $is_edit ? $existing->customer_code : $this->Customer_model->next_code(),
                'type_opts'    => $this->_type_options(),
                'state_opts'   => $this->_state_options(),
                'country_opts' => $this->_country_options(),
                'channel_opts'  => $this->Customer_model->booking_channels(),
                'room_cat_opts' => $this->Customer_model->room_categories(),
                'status_opts'   => $this->_booking_status_options(),
            );
            $this->load->view('customers/form', $data);
            return;
        }

        // Immutable code: keep on edit, generate fresh on add (never trust POST).
        $code = $is_edit ? $existing->customer_code : $this->Customer_model->next_code();

        // --- Scalar fields ----------------------------------------------
        $data = array(
            'customer_name' => $this->input->post('customer_name', TRUE),
            'owner_name'    => $this->input->post('owner_name', TRUE),
            'phone'         => $this->input->post('phone', TRUE),        // Mobile No
            'alt_phone'     => $this->input->post('alt_phone', TRUE),
            'landline_no'   => $this->input->post('landline_no', TRUE),
            'email'         => $this->input->post('email', TRUE),
            'customer_type' => $this->input->post('customer_type', TRUE),
            'address1'      => $this->input->post('address1', TRUE),
            'address2'      => $this->input->post('address2', TRUE),
            'city'          => $this->input->post('city', TRUE),
            'district'      => $this->input->post('district', TRUE),
            'pincode'       => $this->input->post('pincode', TRUE),
            'zip_code'      => $this->input->post('zip_code', TRUE),
            'state'         => $this->input->post('state', TRUE),
            'country'       => $this->input->post('country', TRUE),
            'aadhar_number' => $this->input->post('aadhar_number', TRUE),
            'aadhar_name'   => $this->input->post('aadhar_name', TRUE),
            'pan_number'    => $this->input->post('pan_number', TRUE),
            'pan_name'      => $this->input->post('pan_name', TRUE),
            'is_active'     => $this->input->post('is_active') !== NULL ? 1 : 0,

            // ----- Booking / stay details (flat on the customer row) -----
            'booking_channel_id' => $this->input->post('booking_channel_id') ?: NULL,
            'booking_status'     => $this->_booking_status($this->input->post('booking_status')),
            'booking_by'         => $this->input->post('booking_by', TRUE),
            'guest_name'         => $this->input->post('guest_name', TRUE),
            'guest_mobile_no'    => $this->input->post('guest_mobile_no', TRUE),
            'guest_contact_no'   => $this->input->post('guest_contact_no', TRUE),
            'property_name'      => $this->input->post('property_name', TRUE),
            'scheduled_check_in_date'  => $this->input->post('scheduled_check_in_date') ?: NULL,
            'scheduled_check_out_date' => $this->input->post('scheduled_check_out_date') ?: NULL,
            'checked_in_at'      => $this->_datetime($this->input->post('checked_in_at')),
            'checked_out_at'     => $this->_datetime($this->input->post('checked_out_at')),
            'total_guest'        => $this->_int($this->input->post('total_guest')),
            'room_category_id'   => $this->input->post('room_category_id') ?: NULL,
            'room_quantity'      => $this->_int($this->input->post('room_quantity')),
            'total_unit'         => $this->_int($this->input->post('total_unit')),
            'total_amount'       => $this->_num($this->input->post('total_amount')),
            'amount_paid'        => $this->_num($this->input->post('amount_paid')),
        );
        if ($is_edit) {
            $data['is_active'] = (int) $this->input->post('is_active');
        }

        // Server-side derived fields (never trust the client for these).
        $data['length_of_stay']   = $this->_nights($data['scheduled_check_in_date'], $data['scheduled_check_out_date']);
        $data['remaining_amount'] = ($data['total_amount'] !== NULL || $data['amount_paid'] !== NULL)
            ? round((float) $data['total_amount'] - (float) $data['amount_paid'], 2)
            : NULL;

        // Auto-stamp the ACTUAL check-in / check-out time from the booking status,
        // so setting the status to "Checked In" records *when* it happened without
        // making the user type a timestamp. An explicit posted time is respected.
        $now = date('Y-m-d H:i:s');
        if ($data['booking_status'] === 'checked_in' && empty($data['checked_in_at'])) {
            $data['checked_in_at'] = $now;
        }
        if ($data['booking_status'] === 'checked_out') {
            if (empty($data['checked_in_at']))  { $data['checked_in_at']  = $now; }  // can't leave without arriving
            if (empty($data['checked_out_at'])) { $data['checked_out_at'] = $now; }
        }

        // --- File uploads (replace old file if a new one is provided) ----
        $upload_error = NULL;
        $aadhar_path  = $this->_handle_upload('aadhar_card', $code, 'aadhar_card',
                            $existing ? $existing->aadhar_card_path : NULL, $upload_error);
        $pan_path     = $this->_handle_upload('pan_card', $code, 'pan_card',
                            $existing ? $existing->pan_card_path : NULL, $upload_error);

        if ($upload_error !== NULL) {
            $this->session->set_flashdata('customer_msg', array('type' => 'danger', 'text' => $upload_error));
            redirect($is_edit ? 'customers/form/'.$id : 'customers/form');
            return;
        }

        if ($aadhar_path !== NULL) { $data['aadhar_card_path'] = $aadhar_path; }
        if ($pan_path    !== NULL) { $data['pan_card_path']    = $pan_path; }

        // --- Persist -----------------------------------------------------
        if ($is_edit) {
            $this->Customer_model->update($id, $data);
            $msg = 'Customer "'.$data['customer_name'].'" updated successfully.';
        } else {
            $data['customer_code'] = $code;
            $this->Customer_model->insert($data);
            $msg = 'Customer "'.$data['customer_name'].'" ('.$code.') added successfully.';
        }

        $this->session->set_flashdata('customer_msg', array('type' => 'success', 'text' => $msg));
        redirect('customers');
    }

    /**
     * Delete a customer and remove their uploaded documents/folder.
     */
    public function delete($id = NULL)
    {
        $customer = $id ? $this->Customer_model->get_by_id($id) : NULL;
        if ( ! $customer) {
            return $this->_json(array('status' => FALSE, 'message' => 'Customer not found.'));
        }

        // Remove the whole per-customer upload folder (files + directory).
        $dir = SECURE_UPLOAD_PATH.'customers'.DIRECTORY_SEPARATOR.$customer->customer_code;
        if (is_dir($dir)) {
            delete_files($dir, TRUE);   // recursive contents
            @rmdir($dir);
        }

        $this->Customer_model->delete($id);

        return $this->_json(array(
            'status'  => TRUE,
            'message' => 'Customer "'.$customer->customer_name.'" deleted.',
        ));
    }

    // ---------------------------------------------------------------------
    //  Secure document streaming
    // ---------------------------------------------------------------------

    /**
     * Stream an uploaded Aadhar/PAN document. Files live outside the public
     * tree; access is only possible here, behind the auth guard.
     *
     * @param int    $id
     * @param string $type  'aadhar' | 'pan'
     */
    public function file($id = NULL, $type = NULL)
    {
        $customer = $id ? $this->Customer_model->get_by_id($id) : NULL;
        if ( ! $customer) { show_404(); return; }

        $column = ($type === 'aadhar') ? 'aadhar_card_path'
                : (($type === 'pan')   ? 'pan_card_path' : NULL);
        if ($column === NULL || empty($customer->$column)) { show_404(); return; }

        // Resolve + confine the path to the secure base (defence in depth).
        $rel  = str_replace(array('\\', '..'), array('/', ''), $customer->$column);
        $abs  = SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $rel);

        if ( ! is_file($abs)) { show_404(); return; }

        $mimes = array(
            'pdf' => 'application/pdf', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        );
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = isset($mimes[$ext]) ? $mimes[$ext] : 'application/octet-stream';

        // Inline display (opens in a new tab); switch to 'attachment' to force download.
        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Disposition: inline; filename="'.basename($abs).'"')
            ->set_header('Content-Length: '.filesize($abs))
            ->set_output(file_get_contents($abs));
    }

    // ---------------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------------

    /**
     * Upload a single document into the customer's secure folder, replacing
     * any previous file for that slot. Returns the DB-relative path, or NULL
     * when no new file was submitted. Sets $error on failure.
     *
     * @param  string      $field      $_FILES field name
     * @param  string      $code       customer_code (folder name)
     * @param  string      $base_name  logical name (aadhar_card / pan_card)
     * @param  string|null $old_path   existing stored path (to be replaced)
     * @param  string|null $error      out-param, populated on failure
     * @return string|null
     */
    private function _handle_upload($field, $code, $base_name, $old_path, &$error)
    {
        if (empty($_FILES[$field]['name'])) {
            return NULL; // nothing uploaded for this slot
        }

        $dir = SECURE_UPLOAD_PATH.'customers'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR;
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
            'file_name'     => $base_name,   // extension appended by the library
            'overwrite'     => TRUE,
        );

        $this->load->library('upload');
        $this->upload->initialize($config);

        if ( ! $this->upload->do_upload($field)) {
            $error = strip_tags($this->upload->display_errors('', ''));
            return NULL;
        }

        $info = $this->upload->data();
        // Store path relative to SECURE_UPLOAD_PATH, with forward slashes.
        return 'customers/'.$code.'/'.$info['file_name'];
    }

    /** JSON output helper. */
    private function _json($payload)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

    /** Cast to int, or NULL when blank (keeps optional numeric columns clean). */
    private function _int($v)
    {
        return ($v === NULL || $v === '') ? NULL : (int) $v;
    }

    /** Cast to a numeric value, or NULL when blank / non-numeric. */
    private function _num($v)
    {
        return ($v === NULL || $v === '' || ! is_numeric($v)) ? NULL : (float) $v;
    }

    /** Nights between two Y-m-d dates (>= 0), or NULL if either is missing. */
    private function _nights($check_in, $check_out)
    {
        if ( ! $check_in || ! $check_out) { return NULL; }
        $days = (int) floor((strtotime($check_out) - strtotime($check_in)) / 86400);
        return max(0, $days);
    }

    /**
     * Normalise a datetime-local value ("Y-m-d\TH:i") into a MySQL DATETIME
     * ("Y-m-d H:i:s"), or NULL when blank / unparseable.
     */
    private function _datetime($v)
    {
        if ($v === NULL || trim($v) === '') { return NULL; }
        $ts = strtotime(str_replace('T', ' ', $v));
        return $ts ? date('Y-m-d H:i:s', $ts) : NULL;
    }

    /** Whitelist the booking status to a known lifecycle value, else NULL. */
    private function _booking_status($v)
    {
        return in_array($v, self::BOOKING_STATUSES, TRUE) ? $v : NULL;
    }

    /** Static dropdown option lists (extend as needed). */
    private function _type_options()
    {
        return array('Retail Customer', 'Wholesale Customer', 'Corporate', 'Agent', 'Walk-in');
    }

    private function _state_options()
    {
        // Prefer the full states master (state_details); fall back to a small
        // built-in list if that table is empty/absent.
        $states = $this->Customer_model->all_states();
        if ( ! empty($states)) {
            return $states;
        }
        return array(
            'Andhra Pradesh', 'Delhi', 'Goa', 'Gujarat', 'Karnataka', 'Kerala',
            'Maharashtra', 'Tamil Nadu', 'Telangana', 'Uttar Pradesh', 'West Bengal',
        );
    }

    private function _country_options()
    {
        return array('India', 'United Arab Emirates', 'United Kingdom', 'United States');
    }

    /** Booking status value => human label (order = lifecycle order). */
    private function _booking_status_options()
    {
        return array(
            'enquiry'     => 'Enquiry',
            'confirmed'   => 'Confirmed',
            'checked_in'  => 'Checked In',
            'checked_out' => 'Checked Out',
            'cancelled'   => 'Cancelled',
            'no_show'     => 'No Show',
        );
    }
}
