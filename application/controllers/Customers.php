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
            'flash'  => $this->session->flashdata('customer_msg'),
        );
        $this->load->view('customers/list', $data);
    }

    /**
     * Add / Edit CUSTOMER form (customer fields only — no booking here).
     * Pass an id to edit; omit to add.
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
            'customer'       => $customer,
            'next_code'      => $customer ? $customer->customer_code : $this->Customer_model->next_code(),
            'country_opts'   => $this->_country_options(),
            'identity_types' => $this->_identity_types(),
            'identities'     => $customer ? $this->Customer_model->get_identities($customer->id) : array(),
        );
        $this->load->view('customers/form', $data);
    }

    /**
     * Booking Details list page — lists ONLY "Room booked" bookings
     * (status is fixed to Room booked; there is no status filter).
     */
    public function bookings()
    {
        $data = array(
            'flash' => $this->session->flashdata('booking_msg'),
        );
        $this->load->view('customers/bookings', $data);
    }

    /**
     * New / Edit BOOKING form. Pass a booking id to edit; omit for a new one.
     * The first field is the customer's MOBILE NO — typing a number already in
     * `customers` pulls that customer's saved details in (editable; saving the
     * form writes any edits back to the customers table).
     */
    public function booking_form($booking_id = NULL)
    {
        $booking  = NULL;
        $customer = NULL;

        if ($booking_id !== NULL) {
            $booking = $this->Customer_model->get_booking($booking_id);
            if ( ! $booking) {
                show_404();
                return;
            }
            $customer = $this->Customer_model->get_by_id($booking->customer_id);
        }

        $data = array(
            'booking'       => $booking,
            'customer'      => $customer,
            'country_opts'  => $this->_country_options(),
            'channel_opts'  => $this->Customer_model->booking_channels(),
            'room_cat_opts' => $this->Customer_model->room_categories(),
            'room_opts'     => $this->Customer_model->available_rooms($booking_id),
            'status_opts'   => $this->Customer_model->all_statuses(),
        );
        $this->load->view('customers/booking_form', $data);
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
            'phone'         => $this->input->get('phone'),
            'status'        => $this->input->get('status'),
        );

        $rows = $this->Customer_model->get_filtered($filters);

        return $this->_json(array('status' => TRUE, 'data' => $rows));
    }

    /**
     * [AJAX] Look a customer up by MOBILE NO, so the Booking form can auto-fill
     * an existing customer's saved details the moment the number is typed.
     */
    public function lookup()
    {
        $customer = $this->Customer_model->get_by_phone($this->input->get('phone'));

        if ( ! $customer) {
            return $this->_json(array('status' => TRUE, 'found' => FALSE));
        }

        return $this->_json(array('status' => TRUE, 'found' => TRUE, 'data' => $customer));
    }

    /**
     * [AJAX] JSON of bookings filtered by booking-specific criteria
     * (consumed by the Booking Details live-filter UI).
     */
    public function bookings_ajax()
    {
        $filters = array(
            'q' => $this->input->get('q'),
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

        // Attach identity proofs (with a label + streamed-document URL each).
        $labels = $this->_identity_types();
        $customer->identities = array_map(function ($idn) use ($labels) {
            return array(
                'identity_type'   => $idn->identity_type,
                'type_label'      => isset($labels[$idn->identity_type]) ? $labels[$idn->identity_type] : $idn->identity_type,
                'identity_number' => $idn->identity_number,
                'document_url'    => $idn->document_path ? site_url('customers/identity_file/'.$idn->id) : NULL,
            );
        }, $this->Customer_model->get_identities($customer->id));

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

        if ($this->form_validation->run() === FALSE) {
            // Re-render the form with errors + submitted values.
            $data = array(
                'customer'       => $existing,
                'next_code'      => $is_edit ? $existing->customer_code : $this->Customer_model->next_code(),
                'country_opts'   => $this->_country_options(),
                'identity_types' => $this->_identity_types(),
                'identities'     => $existing ? $this->Customer_model->get_identities($existing->id) : array(),
            );
            $this->load->view('customers/form', $data);
            return;
        }

        // Immutable code: keep on edit, generate fresh on add (never trust POST).
        $code = $is_edit ? $existing->customer_code : $this->Customer_model->next_code();

        // --- Scalar fields (CUSTOMER only — bookings are a separate form) --
        $data = array(
            'customer_name' => $this->input->post('customer_name', TRUE),
            'phone'         => $this->input->post('phone', TRUE),        // Mobile No
            'pincode'       => $this->input->post('pincode', TRUE),
            'country'       => $this->input->post('country', TRUE),
            'is_active'     => $this->input->post('is_active') !== NULL ? 1 : 0,
        );
        if ($is_edit) {
            $data['is_active'] = (int) $this->input->post('is_active');
        }

        // --- Persist customer ------------------------------------------------
        if ($is_edit) {
            $this->Customer_model->update($id, $data);
            $cust_id = $id;
            $msg = 'Customer "'.$data['customer_name'].'" updated successfully.';
        } else {
            $data['customer_code'] = $code;
            $cust_id = $this->Customer_model->insert($data);
            $msg = 'Customer "'.$data['customer_name'].'" ('.$code.') added successfully.';
        }

        // --- Identity proofs (dynamic rows + their uploaded documents) -------
        $this->_save_identities($cust_id, $code);

        $this->session->set_flashdata('customer_msg', array('type' => 'success', 'text' => $msg));
        redirect('customers');
    }

    /**
     * Save a BOOKING (new or edit) from the Booking form.
     *
     * The mobile number identifies the customer:
     *   - number already in `customers`  -> that customer is reused, and any
     *     edits made to their details on this form UPDATE the customer row.
     *   - number is new                  -> a new customer is created.
     * Then the booking itself is inserted (new) or updated (edit) in
     * `booking_details` — one customer can hold many bookings.
     */
    public function booking_save()
    {
        $booking_id = (int) $this->input->post('booking_id');
        $booking_id = $booking_id > 0 ? $booking_id : NULL;

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('phone', 'Mobile No', 'required|trim|max_length[20]');
        $this->form_validation->set_rules('customer_name', 'Customer Name', 'required|trim|max_length[150]');

        if ($this->form_validation->run() === FALSE) {
            $existing = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
            $data = array(
                'booking'       => $existing,
                'customer'      => $existing ? $this->Customer_model->get_by_id($existing->customer_id) : NULL,
                'country_opts'  => $this->_country_options(),
                'channel_opts'  => $this->Customer_model->booking_channels(),
                'room_cat_opts' => $this->Customer_model->room_categories(),
                'room_opts'     => $this->Customer_model->available_rooms($booking_id),
                'status_opts'   => $this->Customer_model->all_statuses(),
            );
            $this->load->view('customers/booking_form', $data);
            return;
        }

        $phone = trim($this->input->post('phone', TRUE));

        // --- Customer: reuse the one on this mobile, else create one -------
        // Only fields actually submitted are written, so a form that doesn't
        // carry a field can never blank out what the customer already has.
        $cdata  = array('phone' => $phone);
        $fields = array('customer_name', 'pincode', 'country');
        foreach ($fields as $f) {
            if ($this->input->post($f) !== NULL) {
                $cdata[$f] = $this->input->post($f, TRUE);
            }
        }

        $customer = $this->Customer_model->get_by_phone($phone);

        if ($customer) {
            $this->Customer_model->update($customer->id, $cdata);   // edits flow back to `customers`
            $cust_id = (int) $customer->id;
        } else {
            $cdata['customer_code'] = $this->Customer_model->next_code();
            $cdata['is_active']     = 1;
            $cust_id = $this->Customer_model->insert($cdata);
        }

        // --- Booking -------------------------------------------------------
        $booking = $this->_booking_from_post();

        if ($booking_id) {
            $this->Customer_model->update_booking($booking_id, $booking);
            $bkg = $this->Customer_model->get_booking($booking_id);
            $msg = 'Booking '.($bkg ? $bkg->booking_number : '').' updated successfully.';
        } else {
            $new_id = $this->Customer_model->create_booking($cust_id, $booking);
            $bkg    = $this->Customer_model->get_booking($new_id);
            $name   = isset($cdata['customer_name']) ? $cdata['customer_name'] : $phone;
            $msg    = 'Booking '.($bkg ? $bkg->booking_number : '').' created for "'.$name.'".';
        }

        $this->session->set_flashdata('booking_msg', array('type' => 'success', 'text' => $msg));
        redirect('customers/bookings');
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
     * Stream an uploaded identity-proof document by its identity id. Files live
     * outside the public tree; access is only possible here, behind the guard.
     *
     * @param int $identity_id
     */
    public function identity_file($identity_id = NULL)
    {
        $identity = $identity_id ? $this->Customer_model->get_identity($identity_id) : NULL;
        if ( ! $identity || empty($identity->document_path)) { show_404(); return; }

        // Resolve + confine the path to the secure base (defence in depth).
        $rel = str_replace(array('\\', '..'), array('/', ''), $identity->document_path);
        $abs = SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $rel);

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
     * @param  string      $base_name  logical file name (e.g. identity_12)
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

    /**
     * Persist the dynamic identity-proof rows for a customer:
     *   - existing rows are updated, new rows inserted
     *   - a freshly uploaded document replaces that row's file
     *   - rows removed on the form are deleted (with their files)
     *
     * @param int    $customer_id
     * @param string $code  customer_code (upload folder)
     */
    private function _save_identities($customer_id, $code)
    {
        $types   = (array) $this->input->post('identity_type');
        $numbers = (array) $this->input->post('identity_number');
        $row_ids = (array) $this->input->post('identity_id');
        $valid   = array_keys($this->_identity_types());

        $keep = array();
        foreach ($types as $i => $type) {
            $type   = trim((string) $type);
            $number = isset($numbers[$i]) ? trim((string) $numbers[$i]) : '';
            $rid    = (isset($row_ids[$i]) && $row_ids[$i] !== '') ? (int) $row_ids[$i] : 0;
            $hasfile = ! empty($_FILES['identity_document']['name'][$i]);

            // A row is meaningful only when a valid identity type is chosen.
            if ($type === '' || ! in_array($type, $valid, TRUE)) {
                continue;
            }

            $existing_row = $rid ? $this->Customer_model->get_identity($rid) : NULL;
            $belongs = $existing_row && (int) $existing_row->customer_id === (int) $customer_id;

            $fields = array(
                'identity_type'   => $type,
                'identity_number' => $number !== '' ? $number : NULL,
            );

            if ($belongs) {
                $this->Customer_model->update_identity($rid, $customer_id, $fields);
                $iid = $rid;
            } else {
                $fields['customer_id'] = $customer_id;
                $iid = $this->Customer_model->insert_identity($fields);
            }
            $keep[] = $iid;

            if ($hasfile) {
                $err  = NULL;
                $old  = $belongs ? $existing_row->document_path : NULL;
                $path = $this->_upload_identity_file($i, $code, 'identity_'.$iid, $old, $err);
                if ($path !== NULL) {
                    $this->Customer_model->update_identity($iid, $customer_id, array('document_path' => $path));
                }
            }
        }

        // Remove identity rows the user deleted on the form (and their files).
        foreach ($this->Customer_model->identities_to_remove($customer_id, $keep) as $gone) {
            if ( ! empty($gone->document_path)) {
                $abs = SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $gone->document_path);
                if (is_file($abs)) { @unlink($abs); }
            }
            $this->Customer_model->delete_identity($gone->id, $customer_id);
        }
    }

    /**
     * Upload the indexed identity document (from the identity_document[] array)
     * by re-mapping it to a single-file field the upload library can consume.
     *
     * @return string|null DB-relative path, or NULL when nothing uploaded.
     */
    private function _upload_identity_file($index, $code, $base_name, $old_path, &$error)
    {
        if (empty($_FILES['identity_document']['name'][$index])) {
            return NULL;
        }
        $_FILES['identity_upload'] = array(
            'name'     => $_FILES['identity_document']['name'][$index],
            'type'     => $_FILES['identity_document']['type'][$index],
            'tmp_name' => $_FILES['identity_document']['tmp_name'][$index],
            'error'    => $_FILES['identity_document']['error'][$index],
            'size'     => $_FILES['identity_document']['size'][$index],
        );
        return $this->_handle_upload('identity_upload', $code, $base_name, $old_path, $error);
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

    /**
     * Resolve the posted status_id to a valid status_master id, defaulting to
     * "Room booked" when missing/invalid.
     */
    private function _status_id()
    {
        $id   = (int) $this->input->post('status_id');
        $code = $id ? $this->Customer_model->status_code($id) : NULL;
        return $code ? $id : $this->Customer_model->status_id_by_code('room_booked');
    }

    /**
     * Build a `booking_details` row from POST: raw booking fields + the
     * server-derived ones (remaining amount) + the check-in/check-out
     * auto-stamp driven by the status (from status_master).
     */
    private function _booking_from_post()
    {
        // NOTE: scheduled_check_in_date / scheduled_check_out_date / length_of_stay
        // are intentionally NOT written here — their inputs were removed from the
        // form (view-only on the list), so we leave any stored values untouched.
        $status_id   = $this->_status_id();
        $status_code = $this->Customer_model->status_code($status_id);

        $booking = array(
            'booking_channel_id' => $this->input->post('booking_channel_id') ?: NULL,
            'status_id'          => $status_id,
            'property_name'      => $this->input->post('property_name', TRUE),
            'room_id'            => $this->input->post('room_id') ?: NULL,   // allotted room
            'checked_in_at'      => $this->_datetime($this->input->post('checked_in_at')),
            'checked_out_at'     => $this->_datetime($this->input->post('checked_out_at')),
            'total_guest'        => $this->_int($this->input->post('total_guest')),
            'room_category_id'   => $this->input->post('room_category_id') ?: NULL,
            'room_quantity'      => $this->_int($this->input->post('room_quantity')),
            'total_unit'         => $this->_int($this->input->post('total_unit')),
            'total_amount'       => $this->_num($this->input->post('total_amount')),
            'amount_paid'        => $this->_num($this->input->post('amount_paid')),
        );

        // Derived server-side (never trust the client for these).
        $booking['remaining_amount'] = ($booking['total_amount'] !== NULL || $booking['amount_paid'] !== NULL)
            ? round((float) $booking['total_amount'] - (float) $booking['amount_paid'], 2)
            : NULL;

        // "Checked in"/"Checked out" record *when* it happened, without making
        // the user type a timestamp.
        $now = date('Y-m-d H:i:s');
        if ($status_code === 'checked_in' && empty($booking['checked_in_at'])) {
            $booking['checked_in_at'] = $now;
        }
        if ($status_code === 'checked_out') {
            if (empty($booking['checked_in_at']))  { $booking['checked_in_at']  = $now; }  // can't leave without arriving
            if (empty($booking['checked_out_at'])) { $booking['checked_out_at'] = $now; }
        }

        return $booking;
    }

    /** Static dropdown option lists (extend as needed). */
    private function _country_options()
    {
        return array('India', 'United Arab Emirates', 'United Kingdom', 'United States');
    }

    /** Identity-proof types: value => label (drives the ID Proof dropdown). */
    private function _identity_types()
    {
        return array(
            'aadhar'   => 'Aadhar Card',
            'pan'      => 'PAN Card',
            'passport' => 'Passport',
            'voter_id' => 'Voter ID',
        );
    }
}
