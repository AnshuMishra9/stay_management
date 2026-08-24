<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Shared booking, stay, and identity-document operations. */
class Ops_Controller extends Property_Controller
{
    const UPLOAD_TYPES = 'jpg|jpeg|png|pdf';
    const UPLOAD_MAX_KB = 4096;
    const MAX_IDENTITY_ROWS = 20;
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Customer_model');
        $this->load->library('booking_calculator');
        $this->load->library('identity_upload_guard');
        $this->load->helper('security');

        if ( ! $this->session->userdata('customer_write_token')) {
            $this->session->set_userdata('customer_write_token', $this->new_session_token());
        }
    }

    public function bookings()
    {
        $this->_render_booking_list(array(
            'title'          => 'Booking Details',
            'sub'            => 'Customers with a room booked',
            'ajax'           => 'customers/bookings_ajax',
            'ns'             => 'bookings',
            'show_new'       => TRUE,
            'workflow_url'   => 'customers/bookings/checkin',
            'workflow_title' => 'Check-in',
            'workflow_icon'  => 'checkin',
            'edit_url'       => 'customers/bookings/edit',
            'edit_title'     => 'Edit booking',
        ));
    }


    public function checkins()
    {
        $this->_render_booking_list(array(
            'title'          => 'Check-in Details',
            'sub'            => 'Customers who are checked in',
            'ajax'           => 'customers/checkins_ajax',
            'ns'             => 'checkins',
            'show_new'       => FALSE,
            'workflow_url'   => 'customers/checkins/checkout',
            'workflow_title' => 'Check-out',
            'workflow_icon'  => 'checkout',
            'edit_url'       => 'customers/checkins/edit',
            'edit_title'     => 'Edit check-in',
            'show_date'      => TRUE,
        ));
    }


    public function checkedouts()
    {
        $this->_render_booking_list(array(
            'title'          => 'Check-out Details',
            'sub'            => 'Customers who have checked out',
            'ajax'           => 'customers/checkedouts_ajax',
            'ns'             => 'checkedouts',
            'show_new'       => FALSE,
            'workflow_url'   => 'customers/checkedouts/details',
            'workflow_title' => 'Check-out record',
            'workflow_icon'  => 'checkout',
            'edit_url'       => 'customers/checkedouts/edit',
            'edit_title'     => 'Edit (read only)',
            'show_date'      => TRUE,
        ));
    }


    protected function _booking_filters($status)
    {
        return array(
            'status'        => $status,
            'booking_no'    => $this->input->get('booking_no'),
            'customer_name' => $this->input->get('customer_name'),
            'room_no'       => $this->input->get('room_no'),
            'room_category' => $this->input->get('room_category'),
            'date'          => $this->_date($this->input->get('date')),
        );
    }


    protected function _render_booking_list(array $cfg)
    {
        $cfg['flash']      = $this->session->flashdata('booking_msg');
        $cfg['categories'] = $this->Customer_model->room_categories($this->current_property_id);
        $this->load->view('customers/bookings', $cfg);
    }


    /**
     * Resolve the posted status_id to a valid status_master id, defaulting to
     * "Room booked" when missing/invalid.
     */
    protected function _status_id()
    {
        $id   = (int) $this->input->post('status_id');
        $code = $id ? $this->Customer_model->status_code($id) : NULL;
        return $code ? $id : $this->Customer_model->status_id_by_code('room_booked');
    }


    /**
     * Re-render the shared booking form after validation or a late conflict.
     * Inventory receives the fragment as JSON so its modal stays open; the
     * normal Booking page keeps its existing full-page response.
     */
    protected function _booking_form_failure(
        $existing,
        $booking_id,
        $inventory_source,
        $message = '',
        $http_status = 422
    ) {
        $room_range = $this->_posted_booking_range($existing);
        $data = array(
            'booking'       => $existing,
            'customer'      => $existing
                ? $this->Customer_model->get_by_id($this->current_tenant_id, $existing->customer_id)
                : NULL,
            'country_opts'  => $this->_country_options(),
            'channel_opts'  => $this->Customer_model->booking_channels(),
            'room_cat_opts' => $this->Customer_model->room_categories($this->current_property_id),
            'room_opts'     => $this->Customer_model->available_rooms(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id,
                $room_range[0],
                $room_range[1]
            ),
            'status_opts'   => $this->Customer_model->all_statuses(),
            'booking_defaults' => array(),
            'booking_form_error' => $message,
        );

        if ($inventory_source) {
            $data['form_context'] = 'inventory';
            $html = $this->load->view(
                'customers/components/booking_form_card',
                $data,
                TRUE
            );
            return $this->_json(array(
                'status' => FALSE,
                'message' => $message ?: 'Please correct the highlighted booking details.',
                'html' => $html,
            ), $http_status);
        }

        $this->load->view('customers/booking_form', $data);
    }


    /** Re-render the focused check-in editor after validation or a late race. */
    protected function _checkin_form_failure(
        $booking,
        $customer,
        $booking_id,
        $page_context,
        $message = ''
    ) {
        $data = array(
            'booking'        => $booking,
            'customer'       => $customer,
            'status_opts'    => $this->Customer_model->all_statuses(),
            'room_cat_opts'  => $this->Customer_model->room_categories($this->current_property_id),
            'room_opts'      => $this->Customer_model->available_rooms(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id,
                $this->_date($this->input->post('scheduled_check_in_date')),
                $this->_date($this->input->post('scheduled_check_out_date'))
            ),
            'identity_types' => $this->_identity_types(),
            'identities'     => $this->Customer_model->get_identities(
                $this->current_tenant_id,
                $this->current_property_id,
                $customer->id,
                $booking_id
            ),
            'page_error'     => $message,
        );

        if ($page_context === 'checkins') {
            $data = array_merge($data, array(
                'page_title'    => 'Edit Check-in',
                'page_subtitle' => 'Update the checked-in customer and stay details',
                'active_nav'    => 'checkins',
                'back_url'      => 'customers/checkins',
                'page_context'  => 'checkins',
                'lock_status'   => TRUE,
                'submit_label'  => 'Update Check-in',
            ));
            $this->load->view('customers/checkins/edit', $data);
            return;
        }

        $this->load->view('customers/checkin', $data);
    }


    protected function _json($payload, $http_status = 200)
    {
        $this->output
            ->set_status_header((int) $http_status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }


    protected function _int($v)
    {
        return ($v === NULL || $v === '') ? NULL : (int) $v;
    }


    protected function _num($v)
    {
        return ($v === NULL || $v === '' || ! is_numeric($v)) ? NULL : (float) $v;
    }


    /**
     * Normalise a datetime-local value ("Y-m-d\TH:i") into a MySQL DATETIME
     * ("Y-m-d H:i:s"), or NULL when blank / unparseable.
     */
    protected function _datetime($v)
    {
        if ($v === NULL || trim($v) === '') { return NULL; }
        $ts = strtotime(str_replace('T', ' ', $v));
        return $ts ? date('Y-m-d H:i:s', $ts) : NULL;
    }


    protected function _date($v)
    {
        if ($v === NULL || ! is_string($v)) { return NULL; }
        $value = trim($v);
        $dt = DateTime::createFromFormat('!Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : NULL;
    }

    /** Prefer posted scheduled dates, then the legacy/manual form range. */
    protected function _posted_booking_range($booking = NULL)
    {
        $scheduled_in = $this->_date($this->input->post('scheduled_check_in_date'));
        $scheduled_out = $this->_date($this->input->post('scheduled_check_out_date'));
        if ($scheduled_in && $scheduled_out && $scheduled_out > $scheduled_in) {
            return array($scheduled_in, $scheduled_out);
        }
        return $this->_room_availability_range($booking, TRUE);
    }

    /**
     * Convert Booking Form datetime-local values into the nightly half-open
     * range used by room inventory.
     */
    protected function _datetime_availability_range($check_in, $check_out)
    {
        if ( ! is_string($check_in) || ! is_string($check_out)) {
            return NULL;
        }
        $check_in = $this->_datetime($check_in);
        $check_out = $this->_datetime($check_out);
        if ( ! $check_in || ! $check_out || strtotime($check_out) <= strtotime($check_in)) {
            return NULL;
        }

        $start = substr($check_in, 0, 10);
        $end = substr($check_out, 0, 10);
        // A same-day stay still occupies that calendar night.
        if ($end <= $start) {
            $end = date('Y-m-d', strtotime($start.' +1 day'));
        }
        return array($start, $end);
    }

    /** Stored API schedule first, then actual/manual range, then current night. */
    protected function _room_availability_range($booking = NULL, $use_post = FALSE)
    {
        if ($use_post) {
            $posted = $this->_datetime_availability_range(
                $this->input->post('checked_in_at'),
                $this->input->post('checked_out_at')
            );
            if ($posted) {
                return $posted;
            }
        }

        if ($booking) {
            $scheduled_in = $this->_date($booking->scheduled_check_in_date);
            $scheduled_out = $this->_date($booking->scheduled_check_out_date);
            if ($scheduled_in && $scheduled_out && $scheduled_out > $scheduled_in) {
                return array($scheduled_in, $scheduled_out);
            }

            $actual = $this->_datetime_availability_range(
                $booking->checked_in_at,
                $booking->checked_out_at
            );
            if ($actual) {
                return $actual;
            }
        }

        $today = date('Y-m-d');
        return array($today, date('Y-m-d', strtotime($today.' +1 day')));
    }


    /** Resolve a DB-relative upload path and prove it remains in the secure root. */
    protected function _secure_upload_file($relative)
    {
        if ( ! is_string($relative) || $relative === '' || strpos($relative, "\0") !== FALSE) {
            return NULL;
        }
        $relative = str_replace('\\', '/', $relative);
        if (
            $relative[0] === '/'
            || preg_match('/^[A-Za-z]:/', $relative)
            || in_array('..', explode('/', $relative), TRUE)
        ) {
            return NULL;
        }

        $base = realpath(SECURE_UPLOAD_PATH);
        $file = realpath(SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($base === FALSE || $file === FALSE || ! is_file($file)) {
            return NULL;
        }
        $prefix = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $inside = DIRECTORY_SEPARATOR === '\\'
            ? stripos($file, $prefix) === 0
            : strpos($file, $prefix) === 0;
        return $inside ? $file : NULL;
    }


    /**
     * Store a validated document under its tenant/property/customer boundary.
     * A replacement removes the previous file only after the new file is safe.
     */
    protected function _handle_upload($field, $customer_id, $base_name, $old_path, &$error)
    {
        if (empty($_FILES[$field]['name'])) {
            return NULL;
        }

        $tenant_id = (int) $this->current_tenant_id;
        $property_id = (int) $this->current_property_id;
        $customer_id = (int) $customer_id;
        if ($tenant_id <= 0 || $property_id <= 0 || $customer_id <= 0) {
            $error = 'The customer upload folder is invalid.';
            return NULL;
        }

        $relative_dir = 'customers/'.$tenant_id.'/'.$property_id.'/'.$customer_id.'/';
        $dir = SECURE_UPLOAD_PATH.str_replace('/', DIRECTORY_SEPARATOR, $relative_dir);
        if ( ! is_dir($dir) && ! @mkdir($dir, 0755, TRUE)) {
            $error = 'Could not create the upload folder. Check permissions.';
            return NULL;
        }

        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (Exception $exception) {
            $suffix = sha1(uniqid((string) mt_rand(), TRUE));
        }

        $config = array(
            'upload_path'   => $dir,
            'allowed_types' => self::UPLOAD_TYPES,
            'max_size'      => self::UPLOAD_MAX_KB,
            'file_name'     => $base_name.'_'.$suffix,
            'overwrite'     => FALSE,
            'remove_spaces' => TRUE,
            'detect_mime'   => TRUE,
            'mod_mime_fix'  => TRUE,
        );

        $this->load->library('upload');
        $this->upload->initialize($config);

        if ( ! $this->upload->do_upload($field)) {
            $error = strip_tags($this->upload->display_errors('', ''));
            return NULL;
        }

        $info = $this->upload->data();
        $stored_abs = $dir.$info['file_name'];
        $stored_file = array(
            'name'     => $info['file_name'],
            'type'     => $info['file_type'],
            'tmp_name' => $stored_abs,
            'error'    => UPLOAD_ERR_OK,
            'size'     => $info['file_size'] * 1024,
        );
        $post_write_error = $this->identity_upload_guard->validate($stored_file, TRUE, FALSE);
        if ($post_write_error !== NULL) {
            @unlink($stored_abs);
            $error = $post_write_error;
            return NULL;
        }

        $old_abs = $this->_secure_upload_file($old_path);
        if ($old_abs !== NULL && $old_abs !== realpath($stored_abs)) {
            @unlink($old_abs);
        }

        // Store path relative to SECURE_UPLOAD_PATH, with forward slashes.
        return $relative_dir.$info['file_name'];
    }


    /** Normalize one identity_document_N[] entry without trusting its shape. */
    protected function _identity_upload_entry($field, $index)
    {
        $empty = array(
            'name' => '', 'type' => '', 'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE, 'size' => 0,
        );
        if ( ! isset($_FILES[$field])) {
            return $empty;
        }
        if ( ! is_array($_FILES[$field])) {
            return array('name' => array(), 'type' => '', 'tmp_name' => '', 'error' => '', 'size' => '');
        }
        foreach ($empty as $key => $default) {
            if ( ! isset($_FILES[$field][$key]) || ! is_array($_FILES[$field][$key])) {
                return array('name' => array(), 'type' => '', 'tmp_name' => '', 'error' => '', 'size' => '');
            }
            $empty[$key] = array_key_exists($index, $_FILES[$field][$key])
                ? $_FILES[$field][$key][$index]
                : $default;
        }
        return $empty;
    }


    /** Validate every submitted identity upload before any customer data changes. */
    protected function _identity_upload_error($customer_id = 0, $booking_id = NULL)
    {
        $types = (array) $this->input->post('identity_type');
        $numbers = (array) $this->input->post('identity_number');
        $row_ids = (array) $this->input->post('identity_id');
        $remove_fronts = (array) $this->input->post('identity_document_remove_1');
        $remove_backs = (array) $this->input->post('identity_document_remove_2');
        $valid_types = array_keys($this->_identity_types());
        if (count($types) > self::MAX_IDENTITY_ROWS) {
            return 'A maximum of '.self::MAX_IDENTITY_ROWS.' identity proofs can be saved at once.';
        }

        // Reject file indexes that do not map to a submitted identity row.
        foreach (array('identity_document_1', 'identity_document_2') as $field) {
            if ( ! isset($_FILES[$field])) { continue; }
            if ( ! is_array($_FILES[$field]) || ! isset($_FILES[$field]['name']) || ! is_array($_FILES[$field]['name'])) {
                return 'The uploaded document has an invalid request format.';
            }
            foreach ($_FILES[$field]['name'] as $index => $name) {
                if (
                    (! is_int($index) && ! ctype_digit((string) $index))
                    || (int) $index >= count($types)
                    || ! is_scalar($name)
                ) {
                    return 'The uploaded document does not match an identity proof row.';
                }
            }
        }

        foreach (array($remove_fronts, $remove_backs) as $removals) {
            foreach ($removals as $index => $remove) {
                if (
                    (! is_int($index) && ! ctype_digit((string) $index))
                    || (int) $index >= count($types)
                    || ! is_scalar($remove)
                    || ! in_array((string) $remove, array('0', '1'), TRUE)
                ) {
                    return 'The document removal request has an invalid format.';
                }
            }
        }

        foreach ($types as $index => $type) {
            if (
                (! is_int($index) && ! ctype_digit((string) $index))
                || ! is_scalar($type)
                || (isset($numbers[$index]) && ! is_scalar($numbers[$index]))
                || (isset($row_ids[$index]) && ! is_scalar($row_ids[$index]))
            ) {
                return 'The identity proof row has an invalid request format.';
            }
            $existing_identity = NULL;
            if (isset($row_ids[$index]) && (string) $row_ids[$index] !== '') {
                $existing_identity = $this->Customer_model->get_identity(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    (int) $row_ids[$index]
                );
                if ( ! $this->_identity_belongs_to_scope($existing_identity, $customer_id, $booking_id)) {
                    return 'The identity proof does not belong to this booking.';
                }
            }
            $front = $this->_identity_upload_entry('identity_document_1', $index);
            $back  = $this->_identity_upload_entry('identity_document_2', $index);
            $has_front = $this->identity_upload_guard->is_present($front);
            $has_back  = $this->identity_upload_guard->is_present($back);
            $remove_front = isset($remove_fronts[$index]) && (string) $remove_fronts[$index] === '1';
            $remove_back = isset($remove_backs[$index]) && (string) $remove_backs[$index] === '1';
            if (($has_front && $remove_front) || ($has_back && $remove_back)) {
                return 'Identity row '.((int) $index + 1).': an image cannot be uploaded and removed at the same time.';
            }
            if (($has_front || $has_back) && ! in_array(trim((string) $type), $valid_types, TRUE)) {
                return 'Select a valid ID Proof Type before uploading its document.';
            }

            $error = $this->identity_upload_guard->validate($front, TRUE, TRUE);
            if ($error !== NULL) { return 'Identity row '.((int) $index + 1).': '.$error; }
            $error = $this->identity_upload_guard->validate($back, FALSE, TRUE);
            if ($error !== NULL) { return 'Identity row '.((int) $index + 1).': '.$error; }

            $front_is_pdf = $has_front
                && strtolower(pathinfo((string) $front['name'], PATHINFO_EXTENSION)) === 'pdf';
            if ($front_is_pdf && $has_back) {
                return 'Identity row '.((int) $index + 1).': choose either one PDF or Image 1/Image 2, not both.';
            }

            // A new back cannot be attached to a previously stored PDF unless
            // the same request also replaces that PDF with a front image.
            if ($has_back && ! $has_front && ! $remove_front && $existing_identity) {
                if (
                    strtolower(pathinfo((string) $existing_identity->document_path, PATHINFO_EXTENSION)) === 'pdf'
                ) {
                    return 'Identity row '.((int) $index + 1).': replace the PDF with Image 1 before adding Image 2.';
                }
            }
        }
        return NULL;
    }


    /** Prove an identity row belongs to both the customer and document scope. */
    protected function _identity_belongs_to_scope($identity, $customer_id, $booking_id)
    {
        if (
            ! $identity
            || (int) $identity->customer_id !== (int) $customer_id
            || (int) ($identity->fk_plant ?? $identity->tenant_id ?? 0) !== (int) $this->current_tenant_id
            || (int) $identity->property_id !== (int) $this->current_property_id
        ) {
            return FALSE;
        }
        $row_booking_id = isset($identity->booking_id) && $identity->booking_id !== NULL
            ? (int) $identity->booking_id
            : NULL;
        return $booking_id === NULL
            ? $row_booking_id === NULL
            : $row_booking_id === (int) $booking_id;
    }


    /** Per-session CSRF guard for the two forms that accept identity files. */
    protected function _valid_customer_write_token()
    {
        $expected = (string) $this->session->userdata('customer_write_token');
        $received = (string) $this->input->post('customer_write_token');
        return $expected !== '' && $received !== '' && hash_equals($expected, $received);
    }


    /** Reject stale/cross-property AJAX reads and writes before scoped work. */
    protected function _require_property_context($json_response)
    {
        if (method_exists($this, 'require_property_context_token')) {
            return (bool) $this->require_property_context_token();
        }

        // Compatibility fallback for deployments that have not yet loaded the
        // new Property_Controller helper. The field/session names are shared.
        $expected = (string) $this->session->userdata('property_context_token');
        $received = (string) $this->input->post('property_context_token');
        if ($received === '') {
            $received = (string) $this->input->server('HTTP_X_PROPERTY_CONTEXT_TOKEN');
        }
        if ($expected !== '' && $received !== '' && hash_equals($expected, $received)) {
            return TRUE;
        }

        $message = 'The active property changed while this page was open. Reload the page and try again.';
        if ($json_response) {
            $this->_json(array(
                'status' => FALSE,
                'context_stale' => TRUE,
                'message' => $message,
            ), 409);
        } else {
            show_error($message, 409, 'Property changed');
        }
        return FALSE;
    }


    /**
     * Synchronize submitted identity rows within customer and optional booking
     * scope. Explicit removals clear DB references, while the retention policy
     * leaves their files on disk and soft-deactivates omitted rows.
     */
    protected function _save_identities($customer_id, $booking_id = NULL)
    {
        $types   = (array) $this->input->post('identity_type');
        $numbers = (array) $this->input->post('identity_number');
        $row_ids = (array) $this->input->post('identity_id');
        $remove_fronts = (array) $this->input->post('identity_document_remove_1');
        $remove_backs = (array) $this->input->post('identity_document_remove_2');
        $valid   = array_keys($this->_identity_types());

        $keep = array();
        $errors = array();
        foreach ($types as $i => $type) {
            $type   = trim((string) $type);
            $number = isset($numbers[$i]) ? trim((string) $numbers[$i]) : '';
            $rid    = (isset($row_ids[$i]) && $row_ids[$i] !== '') ? (int) $row_ids[$i] : 0;
            $front_file = $this->_identity_upload_entry('identity_document_1', $i);
            $back_file  = $this->_identity_upload_entry('identity_document_2', $i);
            $has_front  = $this->identity_upload_guard->is_present($front_file);
            $has_back   = $this->identity_upload_guard->is_present($back_file);
            $remove_front = isset($remove_fronts[$i]) && (string) $remove_fronts[$i] === '1';
            $remove_back = isset($remove_backs[$i]) && (string) $remove_backs[$i] === '1';

            // A row is meaningful only when a valid identity type is chosen.
            if ($type === '' || ! in_array($type, $valid, TRUE)) {
                continue;
            }

            $existing_row = $rid ? $this->Customer_model->get_identity(
                $this->current_tenant_id,
                $this->current_property_id,
                $rid
            ) : NULL;
            $belongs = $this->_identity_belongs_to_scope($existing_row, $customer_id, $booking_id);

            $fields = array(
                'identity_type'   => $type,
                'identity_number' => $number !== ''
                    ? (function_exists('mb_substr') ? mb_substr($number, 0, 50) : substr($number, 0, 50))
                    : NULL,
            );

            if ($belongs) {
                $this->Customer_model->update_identity(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $rid,
                    $customer_id,
                    $fields
                );
                $iid = $rid;
            } else {
                $fields['customer_id'] = $customer_id;
                $fields['booking_id'] = $booking_id === NULL ? NULL : (int) $booking_id;
                $iid = $this->Customer_model->insert_identity(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $fields
                );
            }
            $keep[] = $iid;

            if ($belongs) {
                foreach (array(
                    'document_path' => array($remove_front, $has_front),
                    'document_path_2' => array($remove_back, $has_back),
                ) as $path_field => $removal) {
                    if ( ! $removal[0] || $removal[1] || empty($existing_row->$path_field)) {
                        continue;
                    }
                    $old_path = $existing_row->$path_field;
                    if ($this->Customer_model->update_identity(
                        $this->current_tenant_id,
                        $this->current_property_id,
                        $iid,
                        $customer_id,
                        array($path_field => NULL)
                    )) {
                        $this->_delete_identity_path($old_path);
                        $existing_row->$path_field = NULL;
                    } else {
                        $errors[] = 'Image removal could not be saved.';
                    }
                }
            }

            $front_replacement_saved = FALSE;
            foreach (array(
                1 => array('identity_document_1', 'document_path'),
                2 => array('identity_document_2', 'document_path_2'),
            ) as $slot => $upload) {
                $has_file = $slot === 1 ? $has_front : $has_back;
                if ( ! $has_file) { continue; }
                $path_field = $upload[1];
                $err = NULL;
                $old = ($belongs && isset($existing_row->$path_field)) ? $existing_row->$path_field : NULL;
                $path = $this->_upload_identity_file(
                    $upload[0],
                    $i,
                    $customer_id,
                    'identity_'.$iid.'_side'.$slot,
                    $old,
                    $err
                );
                if ($path !== NULL) {
                    $this->Customer_model->update_identity(
                        $this->current_tenant_id,
                        $this->current_property_id,
                        $iid,
                        $customer_id,
                        array($path_field => $path)
                    );
                    if ($slot === 1) { $front_replacement_saved = TRUE; }
                } elseif ($err) {
                    $errors[] = $err;
                }
            }

            // A PDF represents the complete document, so replacing the front
            // with a PDF removes any old back-side image.
            if (
                $has_front
                && $front_replacement_saved
                && strtolower(pathinfo((string) $front_file['name'], PATHINFO_EXTENSION)) === 'pdf'
                && $belongs
                && ! empty($existing_row->document_path_2)
            ) {
                $this->_delete_identity_path($existing_row->document_path_2);
                $this->Customer_model->update_identity(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $iid,
                    $customer_id,
                    array('document_path_2' => NULL)
                );
            }
        }

        // Rows omitted from the form are soft-deactivated for audit retention.
        foreach ($this->Customer_model->identities_to_remove(
            $this->current_tenant_id,
            $this->current_property_id,
            $customer_id,
            $keep,
            $booking_id
        ) as $gone) {
            $this->_delete_identity_path($gone->document_path);
            $this->_delete_identity_path(isset($gone->document_path_2) ? $gone->document_path_2 : NULL);
            $this->Customer_model->delete_identity(
                $this->current_tenant_id,
                $this->current_property_id,
                $gone->id,
                $customer_id
            );
        }
        return $errors;
    }


    /**
     * Intentional no-op: user removals retain identity files for audit history.
     * Upload replacements are handled separately after validation succeeds.
     */
    protected function _delete_identity_path($path)
    {
        return;
    }


    /**
     * Upload one indexed front/back input by re-mapping it to the single-file
     * field expected by CodeIgniter's upload library.
     */
    protected function _upload_identity_file($source_field, $index, $customer_id, $base_name, $old_path, &$error)
    {
        $entry = $this->_identity_upload_entry($source_field, $index);
        if ( ! $this->identity_upload_guard->is_present($entry)) {
            return NULL;
        }
        $_FILES['identity_upload'] = $entry;
        return $this->_handle_upload('identity_upload', $customer_id, $base_name, $old_path, $error);
    }


    protected function _country_options()
    {
        return array('India', 'United Arab Emirates', 'United Kingdom', 'United States');
    }


    /** Identity-proof types: value => label (drives the ID Proof dropdown). */
    protected function _identity_types()
    {
        return array(
            'aadhar'   => 'Aadhar Card',
            'pan'      => 'PAN Card',
            'passport' => 'Passport',
            'voter_id' => 'Voter ID',
        );
    }

    /** Inventory creates cannot start before the current hotel date. */
    public function inventory_checkin_date($checkin)
    {
        $checkin = $this->_date($checkin);
        if ($checkin && $checkin >= date('Y-m-d')) {
            return TRUE;
        }
        $this->form_validation->set_message(
            'inventory_checkin_date',
            'Scheduled Check-In must be today or a future date.'
        );
        return FALSE;
    }

    /** Prevent a future booking from being checked in before its arrival date. */
    public function can_check_in_on_scheduled_date($status_id)
    {
        $status_code = $this->Customer_model->status_code((int) $status_id);
        if ($status_code !== 'checked_in') {
            return TRUE;
        }

        $scheduled_check_in = $this->_date($this->input->post('scheduled_check_in_date'));
        if ( ! $scheduled_check_in) {
            $booking_id = (int) $this->input->post('booking_id');
            $existing = $booking_id ? $this->Customer_model->get_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id
            ) : NULL;
            $scheduled_check_in = $existing
                ? $this->_date($existing->scheduled_check_in_date)
                : NULL;
        }

        // No scheduled date means a direct/walk-in booking, which may check in now.
        if ( ! $scheduled_check_in) {
            return TRUE;
        }

        if ($scheduled_check_in && $scheduled_check_in <= date('Y-m-d')) {
            return TRUE;
        }

        $display_date = $scheduled_check_in
            ? date('d M Y', strtotime($scheduled_check_in))
            : 'the scheduled check-in date';
        $this->form_validation->set_message(
            'can_check_in_on_scheduled_date',
            'This booking cannot be checked in before '.$display_date.'.'
        );
        return FALSE;
    }


    /** Checkout is the exclusive end of the nightly stay range. */
    public function valid_stay_dates($checkout)
    {
        $checkin = $this->_date($this->input->post('scheduled_check_in_date'));
        $checkout = $this->_date($checkout);
        if ($checkin && $checkout && $checkout > $checkin) { return TRUE; }
        $this->form_validation->set_message('valid_stay_dates', 'Scheduled Check-Out must be after Scheduled Check-In.');
        return FALSE;
    }

    /** Reject rooms held by another live booking during the stay. */
    public function room_available_for_stay($room_id)
    {
        if ($room_id === NULL || $room_id === '') { return TRUE; }
        $bid = (int) $this->input->post('booking_id');
        $cin = $this->_date($this->input->post('scheduled_check_in_date'));
        $cout = $this->_date($this->input->post('scheduled_check_out_date'));
        if (!$cin || !$cout || $cout <= $cin) {
            $existing = $bid ? $this->Customer_model->get_booking($this->current_tenant_id, $this->current_property_id, $bid) : NULL;
            list($cin, $cout) = $this->_room_availability_range($existing, TRUE);
        }
        if ($this->Customer_model->is_room_available($this->current_tenant_id, $this->current_property_id, (int)$room_id, $cin, $cout, $bid ?: NULL)) { return TRUE; }
        $this->form_validation->set_message('room_available_for_stay', 'Room not available.');
        return FALSE;
    }
}
