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
class Customers extends Property_Controller
{
    /** Allowed upload extensions / size (KB). */
    const UPLOAD_TYPES   = 'jpg|jpeg|png|pdf';
    const UPLOAD_MAX_KB  = 4096;
    const MAX_IDENTITY_ROWS = 20;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Customer_model');
        $this->load->helper('file');
        $this->load->library('Identity_upload_guard');
        if ( ! $this->session->userdata('customer_write_token')) {
            $this->session->set_userdata('customer_write_token', bin2hex(random_bytes(32)));
        }
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
            $customer = $this->Customer_model->get_by_id($this->current_tenant_id, $id);
            if ( ! $customer) {
                show_404();
                return;
            }
        }

        $data = array(
            'customer'       => $customer,
            'next_code'      => $customer ? $customer->customer_code : $this->Customer_model->next_code($this->current_tenant_id),
            'country_opts'   => $this->_country_options(),
            'identity_types' => $this->_identity_types(),
            'identities'     => $customer ? $this->Customer_model->get_identities(
                $this->current_tenant_id,
                $this->current_property_id,
                $customer->id
            ) : array(),
        );
        $this->load->view('customers/form', $data);
    }

    /**
     * Booking Details list page — lists ONLY "Room booked" bookings.
     */
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

    /**
     * Check-in Details list page — lists ONLY "Checked in" bookings.
     * Reached from the "Check-in Details" button on the Booking Details page.
     */
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

    /** Check-out Details list page — lists ONLY "Checked out" bookings. */
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

    /** Shared renderer for the two status-scoped booking lists. */
    private function _render_booking_list(array $cfg)
    {
        $cfg['flash']      = $this->session->flashdata('booking_msg');
        $cfg['categories'] = $this->Customer_model->room_categories($this->current_property_id);
        $this->load->view('customers/bookings', $cfg);
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
            $booking = $this->Customer_model->get_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id
            );
            if ( ! $booking) {
                show_404();
                return;
            }

            // Status-specific records must use their own pages. In particular,
            // a completed check-out can only open its read-only edit route.
            $status_code = $this->Customer_model->status_code($booking->status_id);
            if ($status_code === 'checked_in') {
                redirect('customers/checkins/edit/'.$booking->id);
                return;
            }
            if ($status_code === 'checked_out') {
                redirect('customers/checkedouts/edit/'.$booking->id);
                return;
            }
            $customer = $this->Customer_model->get_by_id($this->current_tenant_id, $booking->customer_id);
        }

        $room_range = $this->_room_availability_range($booking);
        $data = array(
            'booking'       => $booking,
            'customer'      => $customer,
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
            'booking_form_error' => '',
        );
        $this->load->view('customers/booking_form', $data);
    }

    /**
     * Shared New Booking form rendered inside the Inventory modal.
     *
     * Calendar cells represent occupied nights. The browser sends an
     * inclusive first/last selection as the canonical half-open stay
     * [check_in, check_out), where check_out is already last night + 1 day.
     */
    public function inventory_booking_form()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $check_in = $this->_date($this->input->get('check_in'));
        $check_out = $this->_date($this->input->get('check_out'));
        $room_id = (int) $this->input->get('room_id');

        if (
            ! $check_in || ! $check_out || $check_out <= $check_in
            || $check_in < date('Y-m-d') || ! $room_id
        ) {
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'Select a valid available room and a present or future stay range.',
            ), 422);
        }

        $room_opts = $this->Customer_model->available_rooms(
            $this->current_tenant_id,
            $this->current_property_id,
            NULL,
            $check_in,
            $check_out
        );
        $selected_room = NULL;
        foreach ($room_opts as $room) {
            if ((int) $room->id === $room_id) {
                $selected_room = $room;
                break;
            }
        }

        // The calendar may have become stale after another user booked it.
        if ( ! $selected_room) {
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'This room is no longer available for the selected dates. Refresh Inventory and choose another range.',
            ), 409);
        }

        // Lightweight full-range check used while extending a selection over
        // multiple Inventory pages. available_rooms() evaluates the complete
        // [check-in, checkout) interval, including dates not currently visible.
        if ($this->input->get('check_only') === '1') {
            return $this->_json(array(
                'status' => TRUE,
                'available' => TRUE,
            ));
        }

        $nights = (int) ((strtotime($check_out) - strtotime($check_in)) / 86400);
        $room_booked_id = $this->Customer_model->status_id_by_code('room_booked');
        $data = array(
            'booking'       => NULL,
            'customer'      => NULL,
            'country_opts'  => $this->_country_options(),
            'channel_opts'  => $this->Customer_model->booking_channels(),
            'room_cat_opts' => $this->Customer_model->room_categories($this->current_property_id),
            'room_opts'     => $room_opts,
            'status_opts'   => $this->Customer_model->all_statuses(),
            'form_context'  => 'inventory',
            'booking_form_error' => '',
            'booking_defaults' => array(
                'status_id' => $room_booked_id,
                'room_id' => $room_id,
                'room_category_id' => $selected_room->category_id !== NULL
                    ? (int) $selected_room->category_id
                    : NULL,
                'scheduled_check_in_date' => $check_in,
                'scheduled_check_out_date' => $check_out,
                'length_of_stay' => $nights,
                'room_quantity' => 1,
                'total_unit' => 1,
            ),
        );

        $html = $this->load->view(
            'customers/components/booking_form_card',
            $data,
            TRUE
        );
        return $this->_json(array('status' => TRUE, 'html' => $html));
    }

    // ---------------------------------------------------------------------
    //  AJAX / JSON endpoints
    // ---------------------------------------------------------------------

    /**
     * Return filtered customers as JSON (consumed by the live filter UI).
     */
    public function list_ajax()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $filters = array(
            'customer_code' => $this->input->get('customer_code'),
            'name'          => $this->input->get('name'),
            'phone'         => $this->input->get('phone'),
            'status'        => $this->input->get('status'),
        );

        $rows = $this->Customer_model->get_filtered($this->current_tenant_id, $filters);

        return $this->_json(array('status' => TRUE, 'data' => $rows));
    }

    /**
     * [AJAX] Look a customer up by MOBILE NO, so the Booking form can auto-fill
     * an existing customer's saved details the moment the number is typed.
     */
    public function lookup()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $customer = $this->Customer_model->get_by_phone(
            $this->current_tenant_id,
            $this->input->get('phone')
        );

        if ( ! $customer) {
            return $this->_json(array('status' => TRUE, 'found' => FALSE));
        }

        return $this->_json(array('status' => TRUE, 'found' => TRUE, 'data' => $customer));
    }

    /** [AJAX] Rooms available for the Booking Form Check In / Check Out range. */
    public function available_rooms_ajax()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $range = $this->_datetime_availability_range(
            $this->input->get('check_in'),
            $this->input->get('check_out')
        );
        if ( ! $range) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Select a valid Check In and Check Out range.',
                'data'    => array(),
            ));
        }

        $booking_id = (int) $this->input->get('booking_id');
        return $this->_json(array(
            'status' => TRUE,
            'data'   => $this->Customer_model->available_rooms(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id ?: NULL,
                $range[0],
                $range[1]
            ),
        ));
    }

    /** [AJAX] "Room booked" bookings (Booking Details list). */
    public function bookings_ajax()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        return $this->_json(array('status' => TRUE, 'data' =>
            $this->Customer_model->get_bookings(
                $this->current_tenant_id,
                $this->current_property_id,
                $this->_booking_filters('room_booked')
            )));
    }

    /** [AJAX] "Checked in" bookings (Check-in Details list). */
    public function checkins_ajax()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        return $this->_json(array('status' => TRUE, 'data' =>
            $this->Customer_model->get_bookings(
                $this->current_tenant_id,
                $this->current_property_id,
                $this->_booking_filters('checked_in')
            )));
    }

    /** [AJAX] "Checked out" bookings (Check-out Details list). */
    public function checkedouts_ajax()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        return $this->_json(array('status' => TRUE, 'data' =>
            $this->Customer_model->get_bookings(
                $this->current_tenant_id,
                $this->current_property_id,
                $this->_booking_filters('checked_out')
            )));
    }

    /** Per-column filters shared by both booking lists. */
    private function _booking_filters($status)
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

    /**
     * Return one customer's full detail (all fields + document URLs).
     */
    public function view($id = NULL)
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $customer = $id ? $this->Customer_model->get_by_id($this->current_tenant_id, $id) : NULL;
        if ( ! $customer) {
            return $this->_json(array('status' => FALSE, 'message' => 'Customer not found.'), 404);
        }

        // Attach identity proofs (with a label + streamed-document URL each).
        $labels = $this->_identity_types();
        $customer->identities = array_map(function ($idn) use ($labels) {
            return array(
                'identity_type'   => $idn->identity_type,
                'type_label'      => isset($labels[$idn->identity_type]) ? $labels[$idn->identity_type] : $idn->identity_type,
                'identity_number' => $idn->identity_number,
                'document_url'    => $idn->document_path ? site_url('customers/identity_file/'.$idn->id) : NULL,
                'document_url_2'  => $idn->document_path_2 ? site_url('customers/identity_file/'.$idn->id.'/2') : NULL,
            );
        }, $this->Customer_model->get_identities(
            $this->current_tenant_id,
            $this->current_property_id,
            $customer->id
        ));

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
        if ( ! $this->require_post() || ! $this->_require_property_context(FALSE)) { return; }
        if ( ! $this->_valid_customer_write_token()) {
            show_error('This form expired or came from another site. Refresh the page and try again.', 403);
            return;
        }
        $id       = (int) $this->input->post('id');
        $is_edit  = $id > 0;
        $existing = $is_edit
            ? $this->Customer_model->get_by_id($this->current_tenant_id, $id)
            : NULL;

        if ($is_edit && ! $existing) {
            show_404();
            return;
        }

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('customer_name', 'Customer Name', 'required|trim|max_length[150]');
        $this->form_validation->set_rules('phone', 'Mobile No', 'required|trim|max_length[20]');
        $identity_upload_error = $this->_identity_upload_error($id);
        $submitted_phone = trim((string) $this->input->post('phone', TRUE));
        $phone_owner = $this->Customer_model->get_by_phone($this->current_tenant_id, $submitted_phone);
        if ($phone_owner && ( ! $existing || (int) $phone_owner->id !== (int) $existing->id)) {
            $identity_upload_error = 'A customer with this mobile number already exists in this account.';
        }
        if ($this->form_validation->run() === FALSE || $identity_upload_error !== NULL) {
            // Re-render the form with errors + submitted values.
            $data = array(
                'customer'       => $existing,
                'next_code'      => $is_edit ? $existing->customer_code : $this->Customer_model->next_code($this->current_tenant_id),
                'country_opts'   => $this->_country_options(),
                'identity_types' => $this->_identity_types(),
                'identities'     => $existing ? $this->Customer_model->get_identities(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $existing->id
                ) : array(),
                'identity_upload_error' => $identity_upload_error,
            );
            $this->load->view('customers/form', $data);
            return;
        }

        // Immutable code: keep on edit, generate fresh on add (never trust POST).
        $code = $is_edit ? $existing->customer_code : NULL;

        // --- Scalar fields (CUSTOMER only — bookings are a separate form) --
        $data = array(
            'customer_name' => $this->input->post('customer_name', TRUE),
            'phone'         => $submitted_phone,                         // Mobile No
            'pincode'       => $this->input->post('pincode', TRUE),
            'country'       => $this->input->post('country', TRUE),
            'is_active'     => $this->input->post('is_active') !== NULL ? 1 : 0,
        );
        if ($is_edit) {
            $data['is_active'] = (int) $this->input->post('is_active');
        }

        // Customer code and phone are tenant-wide. Serialize the final
        // duplicate check for both creates and edits so two properties cannot
        // race a phone change or generate the same code.
        $this->db->trans_begin();
        if ( ! $this->Customer_model->lock_customer_creation_sequence($this->current_tenant_id)) {
            $this->db->trans_rollback();
            show_error('The customer could not be saved right now. Please try again.', 503);
            return;
        }

        $locked_phone_owner = $this->Customer_model->get_by_phone(
            $this->current_tenant_id,
            $submitted_phone
        );
        if ($locked_phone_owner && ( ! $existing || (int) $locked_phone_owner->id !== (int) $existing->id)) {
            $this->db->trans_rollback();
            $this->load->view('customers/form', array(
                'customer'       => $existing,
                'next_code'      => $is_edit
                    ? $existing->customer_code
                    : $this->Customer_model->next_code($this->current_tenant_id),
                'country_opts'   => $this->_country_options(),
                'identity_types' => $this->_identity_types(),
                'identities'     => $existing ? $this->Customer_model->get_identities(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $existing->id
                ) : array(),
                'identity_upload_error' => 'A customer with this mobile number already exists in this account.',
            ));
            return;
        }

        if ($is_edit) {
            $saved = $this->Customer_model->update($this->current_tenant_id, $id, $data);
            $cust_id = $saved ? $id : 0;
            $msg = 'Customer "'.$data['customer_name'].'" updated successfully.';
        } else {
            $code = $this->Customer_model->next_code($this->current_tenant_id);
            $data['customer_code'] = $code;
            $cust_id = $this->Customer_model->insert($this->current_tenant_id, $data);
            $msg = 'Customer "'.$data['customer_name'].'" ('.$code.') added successfully.';
        }

        if ($this->db->trans_status() === FALSE || ! $cust_id) {
            $this->db->trans_rollback();
            show_error('The customer could not be saved. Please try again.', 500);
            return;
        }
        if ( ! $this->db->trans_commit()) {
            show_error('The customer could not be saved. Please try again.', 500);
            return;
        }

        // --- Identity proofs (dynamic rows + their uploaded documents) -------
        $identity_save_errors = $this->_save_identities($cust_id);

        if ($identity_save_errors) {
            $msg .= ' Customer details were saved, but a document could not be stored: '.reset($identity_save_errors);
        }

        $this->session->set_flashdata('customer_msg', array(
            'type' => $identity_save_errors ? 'danger' : 'success',
            'text' => $msg,
        ));
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
        if ( ! $this->require_post()) { return; }
        $inventory_source = ! (int) $this->input->post('booking_id')
            && $this->input->post('booking_source') === 'inventory';
        if ( ! $this->_require_property_context($inventory_source)) { return; }

        $booking_id = (int) $this->input->post('booking_id');
        $booking_id = $booking_id > 0 ? $booking_id : NULL;
        $existing_booking = $booking_id ? $this->Customer_model->get_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        if ($booking_id && ! $existing_booking) {
            show_404();
            return;
        }
        if ($existing_booking) {
            $existing_status = $this->Customer_model->status_code($existing_booking->status_id);
            if ($existing_status === 'checked_in') {
                redirect('customers/checkins/edit/'.$booking_id);
                return;
            }
            if ($existing_status === 'checked_out') {
                redirect('customers/checkedouts/edit/'.$booking_id);
                return;
            }
        }

        // Inventory creates are reservations, never operational status changes.
        if ($inventory_source) {
            $_POST['status_id'] = (string) $this->Customer_model->status_id_by_code('room_booked');
        }

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('phone', 'Mobile No', 'required|trim|max_length[20]');
        $this->form_validation->set_rules('customer_name', 'Customer Name', 'required|trim|max_length[150]');
        $this->form_validation->set_rules('status_id', 'Booking Status', 'required|callback_can_check_in_on_scheduled_date');
        $this->form_validation->set_rules(
            'booking_channel_id',
            'Booking Channel',
            'callback_valid_booking_channel'
        );
        $this->form_validation->set_rules(
            'room_category_id',
            'Room Category',
            'callback_valid_booking_room_category'
        );
        if ($inventory_source) {
            $this->form_validation->set_rules(
                'scheduled_check_in_date',
                'Scheduled Check-In',
                'required|callback_inventory_checkin_date'
            );
            $this->form_validation->set_rules(
                'scheduled_check_out_date',
                'Scheduled Check-Out',
                'required|callback_valid_stay_dates'
            );
            $this->form_validation->set_rules(
                'room_id',
                'Allot Room',
                'required|callback_room_available_for_stay'
            );
        } else {
            $this->form_validation->set_rules('room_id', 'Allot Room', 'callback_room_available_for_stay');
        }

        if ($this->form_validation->run() === FALSE) {
            return $this->_booking_form_failure(
                $existing_booking,
                $booking_id,
                $inventory_source
            );
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

        $booking = $this->_booking_from_post();
        if ($existing_booking) {
            // This nullable text column is a historical legacy snapshot, not
            // an authorization field. Editing a legacy row must not rewrite it.
            unset($booking['property_name']);
        }
        $room_id = isset($booking['room_id']) ? (int) $booking['room_id'] : 0;
        $room_range = $this->_posted_booking_range($existing_booking);

        $existing_customer = $existing_booking
            ? $this->Customer_model->get_by_id($this->current_tenant_id, $existing_booking->customer_id)
            : NULL;
        if ($existing_booking && ! $existing_customer) {
            show_404();
            return;
        }
        $phone_customer = $this->Customer_model->get_by_phone($this->current_tenant_id, $phone);
        if (
            $existing_customer
            && $phone_customer
            && (int) $phone_customer->id !== (int) $existing_customer->id
        ) {
            return $this->_booking_form_failure(
                $existing_booking,
                $booking_id,
                $inventory_source,
                'This mobile number belongs to another customer in this account. The booking customer cannot be changed.',
                409
            );
        }

        // Customer + booking write is atomic. Lock the room first, then repeat
        // the availability test as a locking/current read to close stale-page
        // and simultaneous-submit races.
        $this->db->trans_begin();

        // Every booking writer uses one broad-to-narrow mutex order:
        // tenant -> property -> room(s) -> overlapping/target booking(s).
        // This also serializes tenant customer codes/phones and property
        // booking-number generation without booking/room lock inversion.
        if ( ! $this->Customer_model->lock_booking_creation_sequence(
            $this->current_tenant_id,
            $this->current_property_id
        )) {
            $this->db->trans_rollback();
            return $this->_booking_form_failure(
                $existing_booking,
                $booking_id,
                $inventory_source,
                $booking_id
                    ? 'The booking could not be updated right now. Please try again.'
                    : 'The booking could not be created right now. Please try again.',
                503
            );
        }

        if ($room_id) {
            $rooms_to_lock = array($room_id);
            if ($existing_booking && $existing_booking->room_id) {
                $rooms_to_lock[] = (int) $existing_booking->room_id;
            }
            $locked_rooms = $this->Customer_model->lock_rooms_for_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $rooms_to_lock
            );
            if (
                ! in_array($room_id, $locked_rooms, TRUE)
                || ! $this->Customer_model->is_room_available_for_update(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $room_id,
                    $room_range[0],
                    $room_range[1],
                    $booking_id
                )
            ) {
                $this->db->trans_rollback();
                return $this->_booking_form_failure(
                    $existing_booking,
                    $booking_id,
                    $inventory_source,
                    'The selected room was just booked for part of this stay. Choose another available room or date range.',
                    409
                );
            }
        }

        if ($booking_id) {
            // The property/room/overlap locks above are now held. Re-read the
            // target booking last and reject a page that became stale before
            // this transaction acquired the tenant/property mutex.
            $locked_booking = $this->Customer_model->get_booking_for_update(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id
            );
            $locked_status = $locked_booking
                ? $this->Customer_model->status_code($locked_booking->status_id)
                : NULL;
            if (
                ! $locked_booking
                || (int) $locked_booking->status_id !== (int) $existing_booking->status_id
                || (int) $locked_booking->room_id !== (int) $existing_booking->room_id
                || (string) $locked_booking->updated_at !== (string) $existing_booking->updated_at
                || in_array($locked_status, array('checked_in', 'checked_out'), TRUE)
            ) {
                $this->db->trans_rollback();
                return $this->_booking_form_failure(
                    $existing_booking,
                    $booking_id,
                    $inventory_source,
                    'This booking changed while the form was open. Reload it before saving.',
                    409
                );
            }
            $existing_booking = $locked_booking;
        }

        // Re-read under the tenant sequence lock. Another property may have
        // created this phone after the pre-transaction lookup above.
        if ( ! $booking_id) {
            $phone_customer = $this->Customer_model->get_by_phone($this->current_tenant_id, $phone);
        }

        $customer = $existing_customer ?: $phone_customer;
        if ($customer) {
            if ( ! $booking_id && (int) $customer->is_active !== 1) {
                // Starting a deliberate new stay revives the shared profile;
                // prior bookings/documents remain untouched.
                $cdata['is_active'] = 1;
            }
            $this->Customer_model->update($this->current_tenant_id, $customer->id, $cdata);
            $cust_id = (int) $customer->id;
        } else {
            $cdata['customer_code'] = $this->Customer_model->next_code($this->current_tenant_id);
            $cdata['is_active'] = 1;
            $cust_id = $this->Customer_model->insert($this->current_tenant_id, $cdata);
        }

        if ($booking_id) {
            $this->Customer_model->update_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id,
                $booking
            );
            $bkg = $this->Customer_model->get_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id
            );
            $msg = 'Booking '.($bkg ? $bkg->booking_number : '').' updated successfully.';
        } else {
            $new_id = $this->Customer_model->create_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $cust_id,
                $booking
            );
            $bkg = $this->Customer_model->get_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $new_id
            );
            $name   = isset($cdata['customer_name']) ? $cdata['customer_name'] : $phone;
            $msg    = 'Booking '.($bkg ? $bkg->booking_number : '').' created for "'.$name.'".';
        }

        if ($this->db->trans_status() === FALSE || ! $cust_id || ! $bkg) {
            $this->db->trans_rollback();
            return $this->_booking_form_failure(
                $existing_booking,
                $booking_id,
                $inventory_source,
                'The booking could not be saved. Please try again.',
                500
            );
        }
        $this->db->trans_commit();

        if ($inventory_source) {
            // The Inventory modal finishes on Booking Details. This flash also
            // tells the list page to invalidate its cached booking rows.
            $this->session->set_flashdata('booking_msg', array(
                'type' => 'success',
                'text' => $msg,
            ));
            return $this->_json(array(
                'status' => TRUE,
                'message' => $msg,
                'booking_id' => (int) $bkg->id,
                'booking_number' => $bkg->booking_number,
                'redirect' => site_url('customers/bookings'),
            ));
        }

        $this->session->set_flashdata('booking_msg', array('type' => 'success', 'text' => $msg));
        redirect('customers/bookings');
    }

    /**
     * [AJAX] Full detail of one booking for the Booking Details "eye" modal:
     * booking + customer + allotted room/category + status + channel + the
     * customer's identity proofs (with streamed-document URLs).
     */
    public function booking_view($booking_id = NULL)
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $booking = $booking_id ? $this->Customer_model->get_booking_detail(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        if ( ! $booking) {
            return $this->_json(array('status' => FALSE, 'message' => 'Booking not found.'), 404);
        }

        $labels = $this->_identity_types();
        $booking->identities = array_map(function ($idn) use ($labels) {
            return array(
                'type_label'      => isset($labels[$idn->identity_type]) ? $labels[$idn->identity_type] : $idn->identity_type,
                'identity_number' => $idn->identity_number,
                'document_url'    => $idn->document_path ? site_url('customers/identity_file/'.$idn->id) : NULL,
                'document_url_2'  => $idn->document_path_2 ? site_url('customers/identity_file/'.$idn->id.'/2') : NULL,
            );
        }, $this->Customer_model->get_identities(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking->customer_id,
            $booking->id
        ));

        return $this->_json(array('status' => TRUE, 'data' => $booking));
    }

    /**
     * Check-in page — a focused edit of just the fields needed at check-in:
     * the customer's name + mobile, their identity proofs (same block as the
     * customer master), and the booking status. Existing values are pre-loaded.
     */
    public function checkin($booking_id = NULL)
    {
        $booking = $booking_id ? $this->Customer_model->get_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        if ( ! $booking || $this->Customer_model->status_code($booking->status_id) !== 'room_booked') {
            show_404();
            return;
        }
        $customer = $this->Customer_model->get_by_id($this->current_tenant_id, $booking->customer_id);

        $data = array(
            'booking'        => $booking,
            'customer'       => $customer,
            'status_opts'    => $this->Customer_model->all_statuses(),
            'room_cat_opts'  => $this->Customer_model->room_categories($this->current_property_id),
            'room_opts'      => $this->Customer_model->available_rooms(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id,
                $this->_date($booking->scheduled_check_in_date),
                $this->_date($booking->scheduled_check_out_date)
            ),
            'identity_types' => $this->_identity_types(),
            'identities'     => $customer ? $this->Customer_model->get_identities(
                $this->current_tenant_id,
                $this->current_property_id,
                $customer->id,
                $booking->id
            ) : array(),
        );
        $this->load->view('customers/checkin', $data);
    }

    /**
     * Check-in Details edit page. It has its own URL and returns to the
     * Check-in list. Status is locked so editing cannot silently move the row
     * into a different workflow list.
     */
    public function checkin_edit($booking_id = NULL)
    {
        $booking = $booking_id ? $this->Customer_model->get_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        if ( ! $booking || $this->Customer_model->status_code($booking->status_id) !== 'checked_in') {
            show_404();
            return;
        }

        $customer = $this->Customer_model->get_by_id($this->current_tenant_id, $booking->customer_id);
        if ( ! $customer) {
            show_404();
            return;
        }

        $data = array(
            'booking'        => $booking,
            'customer'       => $customer,
            'status_opts'    => $this->Customer_model->all_statuses(),
            'room_cat_opts'  => $this->Customer_model->room_categories($this->current_property_id),
            'room_opts'      => $this->Customer_model->available_rooms(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id,
                $this->_date($booking->scheduled_check_in_date),
                $this->_date($booking->scheduled_check_out_date)
            ),
            'identity_types' => $this->_identity_types(),
            'identities'     => $this->Customer_model->get_identities(
                $this->current_tenant_id,
                $this->current_property_id,
                $customer->id,
                $booking->id
            ),
            'page_title'     => 'Edit Check-in',
            'page_subtitle'  => 'Update the checked-in customer and stay details',
            'active_nav'     => 'checkins',
            'back_url'       => 'customers/checkins',
            'page_context'   => 'checkins',
            'lock_status'    => TRUE,
            'submit_label'   => 'Update Check-in',
        );
        $this->load->view('customers/checkins/edit', $data);
    }

    /** Check-out confirmation page for a currently checked-in booking. */
    public function checkout($booking_id = NULL)
    {
        $this->_render_checkout_page($booking_id, 'confirm');
    }

    /** Read-only record page opened from the Check-out Details workflow button. */
    public function checkedout_details($booking_id = NULL)
    {
        $this->_render_checkout_page($booking_id, 'details');
    }

    /**
     * The Check-out Details edit action deliberately opens a read-only page.
     * Checked-out customer/booking data remains visible but cannot be changed.
     */
    public function checkedout_edit($booking_id = NULL)
    {
        $this->_render_checkout_page($booking_id, 'edit');
    }

    /** Render one of the status-specific check-out pages. */
    private function _render_checkout_page($booking_id, $mode)
    {
        $booking = $booking_id ? $this->Customer_model->get_booking_detail(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        $required_status = ($mode === 'confirm') ? 'checked_in' : 'checked_out';
        if ( ! $booking || $booking->status_code !== $required_status) {
            show_404();
            return;
        }

        $labels = $this->_identity_types();
        $identities = array_map(function ($identity) use ($labels) {
            $identity->type_label = isset($labels[$identity->identity_type])
                ? $labels[$identity->identity_type]
                : $identity->identity_type;
            $identity->document_url = $identity->document_path
                ? site_url('customers/identity_file/'.$identity->id)
                : NULL;
            $identity->document_url_2 = $identity->document_path_2
                ? site_url('customers/identity_file/'.$identity->id.'/2')
                : NULL;
            return $identity;
        }, $this->Customer_model->get_identities(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking->customer_id,
            $booking->id
        ));

        $is_confirm = ($mode === 'confirm');
        $is_edit = ($mode === 'edit');
        $data = array(
            'booking'       => $booking,
            'identities'    => $identities,
            'status_opts'   => $this->Customer_model->all_statuses(),
            'confirm'       => $is_confirm,
            'page_title'    => $is_confirm ? 'Check-out' : ($is_edit ? 'Edit Check-out' : 'Check-out Record'),
            'page_subtitle' => $is_confirm
                ? 'Review the guest and stay details before confirming check-out'
                : 'This completed check-out record is read only',
            'active_nav'    => $is_confirm ? 'checkins' : 'checkedouts',
            'back_url'      => $is_confirm ? 'customers/checkins' : 'customers/checkedouts',
        );

        if ($mode === 'confirm') {
            $this->load->view('customers/checkins/checkout', $data);
        } elseif ($mode === 'edit') {
            $this->load->view('customers/checkedouts/edit', $data);
        } else {
            $this->load->view('customers/checkedouts/details', $data);
        }
    }

    /** Complete check-out after the user changes Status to Checked Out. */
    public function checkout_save()
    {
        if ( ! $this->require_post() || ! $this->_require_property_context(FALSE)) { return; }
        $booking_id = (int) $this->input->post('booking_id');
        $booking = $booking_id ? $this->Customer_model->get_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        if ( ! $booking) {
            show_404();
            return;
        }

        $current_status = $this->Customer_model->status_code($booking->status_id);
        if ($current_status === 'checked_out') {
            redirect('customers/checkedouts');
            return;
        }
        if ($current_status !== 'checked_in') {
            show_404();
            return;
        }

        $checked_out_status = $this->Customer_model->status_id_by_code('checked_out');
        if ( ! $checked_out_status) {
            show_error('The Checked Out status is not configured.');
            return;
        }

        $selected_status = (int) $this->input->post('status_id');
        $checked_in_status = $this->Customer_model->status_id_by_code('checked_in');
        if ($selected_status === $checked_in_status) {
            redirect('customers/checkins');
            return;
        }
        if ($selected_status !== $checked_out_status) {
            show_error('Please select Checked Out to complete the check-out.');
            return;
        }

        $this->db->trans_begin();
        $locked_booking = $this->Customer_model->get_booking_for_update(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        );
        if (
            ! $locked_booking
            || $this->Customer_model->status_code($locked_booking->status_id) !== 'checked_in'
        ) {
            $this->db->trans_rollback();
            show_error(
                'This booking status changed while the form was open. Reload it before checking out.',
                409,
                'Booking changed'
            );
            return;
        }
        $booking = $locked_booking;

        $now = date('Y-m-d H:i:s');
        $updated = $this->Customer_model->update_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id,
            array(
            'status_id'      => $checked_out_status,
            'checked_in_at'  => $booking->checked_in_at ?: $now,
            'checked_out_at' => $now,
        ));

        if ($this->db->trans_status() === FALSE || ! $updated) {
            $this->db->trans_rollback();
            show_error('The booking could not be checked out. Please try again.', 500);
            return;
        }
        $this->db->trans_commit();

        $this->session->set_flashdata('booking_msg', array(
            'type' => 'success',
            'text' => 'Booking '.$booking->booking_number.' checked out successfully.',
        ));
        redirect('customers/checkedouts');
    }

    /**
     * Save the Check-in form: update the booking's customer (name + mobile),
     * re-sync their identity proofs, and update the booking status (auto-
     * stamping checked_in_at / checked_out_at from the status).
     */
    public function checkin_save()
    {
        if ( ! $this->require_post() || ! $this->_require_property_context(FALSE)) { return; }
        if ( ! $this->_valid_customer_write_token()) {
            show_error('This form expired or came from another site. Refresh the page and try again.', 403);
            return;
        }
        $booking_id = (int) $this->input->post('booking_id');
        $booking = $booking_id ? $this->Customer_model->get_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        ) : NULL;
        if ( ! $booking) {
            show_404();
            return;
        }
        $customer = $this->Customer_model->get_by_id($this->current_tenant_id, $booking->customer_id);
        if ( ! $customer) {
            show_404();
            return;
        }

        $page_context = $this->input->post('page_context') === 'checkins'
            ? 'checkins'
            : 'bookings';
        $required_status = $page_context === 'checkins' ? 'checked_in' : 'room_booked';
        if ($this->Customer_model->status_code($booking->status_id) !== $required_status) {
            show_404();
            return;
        }

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('customer_name', 'Customer Name', 'required|trim|max_length[150]');
        $this->form_validation->set_rules('phone', 'Mobile No', 'required|trim|max_length[20]');
        $this->form_validation->set_rules('status_id', 'Booking Status', 'required|callback_checkin_status_allowed|callback_can_check_in_on_scheduled_date');
        $this->form_validation->set_rules('scheduled_check_in_date', 'Scheduled Check-In', 'required');
        $this->form_validation->set_rules('scheduled_check_out_date', 'Scheduled Check-Out', 'required|callback_valid_stay_dates');
        $this->form_validation->set_rules('room_id', 'Allot Room', 'callback_room_available_for_stay');
        $this->form_validation->set_rules(
            'room_category_id',
            'Room Category',
            'callback_valid_booking_room_category'
        );

        $identity_upload_error = $this->_identity_upload_error($customer->id, $booking_id);
        $phone_owner = $this->Customer_model->get_by_phone(
            $this->current_tenant_id,
            trim((string) $this->input->post('phone', TRUE))
        );
        if ($phone_owner && (int) $phone_owner->id !== (int) $customer->id) {
            $identity_upload_error = 'This mobile number belongs to another customer in this account.';
        }
        if ($this->form_validation->run() === FALSE || $identity_upload_error !== NULL) {
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context,
                $identity_upload_error ?: ''
            );
        }

        // --- Booking status (+ deterministic check-in/out stamps) --------
        // Fill the timestamp the target status implies (keeping any existing
        // one) and CLEAR the one it contradicts, so a status change never
        // leaves a stale checked_in_at / checked_out_at behind.
        $status_id   = $page_context === 'checkins'
            ? $this->Customer_model->status_id_by_code('checked_in')
            : $this->_status_id();
        $status_code = $this->Customer_model->status_code($status_id);
        $now = date('Y-m-d H:i:s');
        $room_id = $this->input->post('room_id') ?: NULL;
        $room_category_id = $this->input->post('room_category_id') ?: NULL;
        if ($room_id) {
            $room_category_id = $this->Customer_model->room_category_for_room(
                $this->current_property_id,
                $room_id
            );
        }

        $upd = array(
            'status_id'      => $status_id,
            'room_id'        => $room_id,
            'room_category_id' => $room_category_id,
            'scheduled_check_in_date'  => $this->_date($this->input->post('scheduled_check_in_date')),
            'scheduled_check_out_date' => $this->_date($this->input->post('scheduled_check_out_date')),
            'checked_in_at'  => in_array($status_code, array('checked_in', 'checked_out'), TRUE)
                                    ? ($booking->checked_in_at ?: $now) : NULL,
            'checked_out_at' => ($status_code === 'checked_out')
                                    ? ($booking->checked_out_at ?: $now) : NULL,
        );
        $upd['length_of_stay'] = (int) (
            (strtotime($upd['scheduled_check_out_date']) - strtotime($upd['scheduled_check_in_date'])) / 86400
        );

        // Acquire the common tenant/property mutex before any booking or room.
        // It also serializes shared customer phone changes with customer and
        // booking creation across all properties in this account.
        $this->db->trans_begin();

        if ( ! $this->Customer_model->lock_booking_creation_sequence(
            $this->current_tenant_id,
            $this->current_property_id
        )) {
            $this->db->trans_rollback();
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context,
                'The property is unavailable or busy. Please try again.'
            );
        }

        // A checkout or another status workflow may have completed after the
        // page was opened. Lock/re-read the booking so stale Check-in data can
        // never overwrite a newer terminal status.
        $locked_booking = $this->Customer_model->get_booking_for_update(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id
        );
        if (
            ! $locked_booking
            || $this->Customer_model->status_code($locked_booking->status_id) !== $required_status
        ) {
            $this->db->trans_rollback();
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context,
                'This booking status changed while the form was open. Refresh the booking before making further changes.'
            );
        }

        // Preserve any operational timestamp committed before this row lock.
        $booking = $locked_booking;
        $upd['checked_in_at'] = in_array($status_code, array('checked_in', 'checked_out'), TRUE)
            ? ($booking->checked_in_at ?: $now)
            : NULL;
        $upd['checked_out_at'] = $status_code === 'checked_out'
            ? ($booking->checked_out_at ?: $now)
            : NULL;

        // Validation above gives quick feedback; this second, locking read is
        // what prevents a stale check-in form racing a simultaneous booking.
        $rooms_to_lock = array();
        if ($room_id) {
            $rooms_to_lock[] = (int) $room_id;
        }
        if ($booking->room_id) {
            $rooms_to_lock[] = (int) $booking->room_id;
        }
        $locked_rooms = $this->Customer_model->lock_rooms_for_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $rooms_to_lock
        );

        if (
            $room_id
            && (
                ! in_array((int) $room_id, $locked_rooms, TRUE)
                || ! $this->Customer_model->is_room_available_for_update(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $room_id,
                    $upd['scheduled_check_in_date'],
                    $upd['scheduled_check_out_date'],
                    $booking_id
                )
            )
        ) {
            $this->db->trans_rollback();
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context,
                'The selected room was just booked for part of this stay. Choose another available room or date range.'
            );
        }

        // Customer and stay updates commit together while the room lock is
        // held, so another writer cannot slip between recheck and update.
        $customer_updated = $this->Customer_model->update($this->current_tenant_id, $customer->id, array(
            'customer_name' => $this->input->post('customer_name', TRUE),
            'phone'         => $this->input->post('phone', TRUE),
        ));
        $booking_updated = $this->Customer_model->update_booking(
            $this->current_tenant_id,
            $this->current_property_id,
            $booking_id,
            $upd
        );

        if (
            $this->db->trans_status() === FALSE
            || ! $customer_updated
            || ! $booking_updated
        ) {
            $this->db->trans_rollback();
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context,
                'The check-in details could not be saved. Please try again.'
            );
        }
        if ( ! $this->db->trans_commit()) {
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context,
                'The check-in details could not be saved. Please try again.'
            );
        }

        // Filesystem changes cannot participate in a database rollback. Run
        // identity document syncing only after the room/customer/stay commit.
        $identity_save_errors = $this->_save_identities($customer->id, $booking_id);

        $this->session->set_flashdata('booking_msg', array(
            'type' => $identity_save_errors ? 'danger' : 'success',
            'text' => 'Booking '.$booking->booking_number.' checked-in details updated.'
                .($identity_save_errors ? ' A document could not be stored: '.reset($identity_save_errors) : ''),
        ));
        redirect($page_context === 'checkins' ? 'customers/checkins' : 'customers/bookings');
    }

    /**
     * Delete a customer and remove their uploaded documents/folder.
     */
    public function delete($id = NULL)
    {
        if ( ! $this->require_post() || ! $this->_require_property_context(TRUE)) { return; }
        $this->db->trans_begin();
        if ( ! $this->Customer_model->lock_customer_creation_sequence($this->current_tenant_id)) {
            $this->db->trans_rollback();
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'The customer could not be changed right now. Please try again.',
            ), 503);
        }

        $customer = $id ? $this->Customer_model->get_by_id($this->current_tenant_id, $id) : NULL;
        if ( ! $customer) {
            $this->db->trans_rollback();
            return $this->_json(array('status' => FALSE, 'message' => 'Customer not found.'), 404);
        }

        // A customer is shared by every property in the tenant. Never cascade
        // another property's booking/document history from this endpoint.
        if ($this->Customer_model->has_history($this->current_tenant_id, $id)) {
            $changed = $this->Customer_model->update(
                $this->current_tenant_id,
                $id,
                array('is_active' => 0)
            );
            if ( ! $changed || $this->db->trans_status() === FALSE || ! $this->db->trans_commit()) {
                $this->db->trans_rollback();
                return $this->_json(array(
                    'status' => FALSE,
                    'message' => 'The customer could not be deactivated.',
                ), 500);
            }
            return $this->_json(array(
                'status'  => TRUE,
                'deleted' => FALSE,
                'deactivated' => TRUE,
                'message' => 'Customer "'.$customer->customer_name.'" has stay or document history and was deactivated instead of deleted.',
            ));
        }

        $deleted = $this->Customer_model->delete($this->current_tenant_id, $id);
        if ( ! $deleted || $this->db->trans_status() === FALSE || ! $this->db->trans_commit()) {
            $this->db->trans_rollback();
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'The customer could not be deleted.',
            ), 409);
        }

        return $this->_json(array(
            'status'  => TRUE,
            'deleted' => TRUE,
            'deactivated' => FALSE,
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
     * @param int $slot 1 = front/file, 2 = back image
     */
    public function identity_file($identity_id = NULL, $slot = 1)
    {
        $identity = $identity_id ? $this->Customer_model->get_identity(
            $this->current_tenant_id,
            $this->current_property_id,
            $identity_id
        ) : NULL;
        $slot = (int) $slot;
        if ( ! $identity || ! in_array($slot, array(1, 2), TRUE)) { show_404(); return; }
        $field = $slot === 2 ? 'document_path_2' : 'document_path';
        $path  = isset($identity->$field) ? $identity->$field : NULL;
        $abs   = $this->_secure_upload_file($path);
        if ($abs === NULL) { show_404(); return; }

        $mimes = array(
            'pdf' => 'application/pdf', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        );
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if ( ! isset($mimes[$ext])) { show_404(); return; }
        $mime = $mimes[$ext];

        // Stored identity documents are sensitive and must not be MIME-sniffed,
        // embedded by another site, or retained in a shared browser cache.
        $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($abs));

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Disposition: inline; filename="'.$safe_name.'"')
            ->set_header('Content-Length: '.filesize($abs))
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data:; style-src \'unsafe-inline\'; sandbox')
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_output(file_get_contents($abs));
    }

    // ---------------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------------

    /** Resolve a DB-relative upload path and prove it remains in the secure root. */
    private function _secure_upload_file($relative)
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
     * Upload a single document into the tenant/property/customer secure folder, replacing
     * any previous file for that slot. Returns the DB-relative path, or NULL
     * when no new file was submitted. Sets $error on failure.
     *
     * @param  string      $field      $_FILES field name
     * @param  int         $customer_id tenant-scoped customer id
     * @param  string      $base_name  logical file name (e.g. identity_12)
     * @param  string|null $old_path   existing stored path (to be replaced)
     * @param  string|null $error      out-param, populated on failure
     * @return string|null
     */
    private function _handle_upload($field, $customer_id, $base_name, $old_path, &$error)
    {
        if (empty($_FILES[$field]['name'])) {
            return NULL; // nothing uploaded for this slot
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

        // Use a new unpredictable name. The old document is deleted only after
        // the replacement has passed validation and has been written safely.
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
    private function _identity_upload_entry($field, $index)
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
    private function _identity_upload_error($customer_id = 0, $booking_id = NULL)
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
    private function _identity_belongs_to_scope($identity, $customer_id, $booking_id)
    {
        if (
            ! $identity
            || (int) $identity->customer_id !== (int) $customer_id
            || (int) $identity->tenant_id !== (int) $this->current_tenant_id
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
    private function _valid_customer_write_token()
    {
        $expected = (string) $this->session->userdata('customer_write_token');
        $received = (string) $this->input->post('customer_write_token');
        return $expected !== '' && $received !== '' && hash_equals($expected, $received);
    }

    /** Reject stale/cross-property AJAX reads and writes before scoped work. */
    private function _require_property_context($json_response)
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
     * Persist the dynamic identity-proof rows for a customer:
     *   - existing rows are updated, new rows inserted
     *   - freshly uploaded front/back files replace their matching slots
     *   - individually removed images are cleared from DB and secure storage
     *   - rows removed on the form are deleted (with their files)
     *
     * @param int    $customer_id
     * @param int|null $booking_id booking scope, NULL for current-property customer documents
     */
    private function _save_identities($customer_id, $booking_id = NULL)
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

        // Remove identity rows the user deleted on the form (and their files).
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

    private function _delete_identity_path($path)
    {
        $absolute = $this->_secure_upload_file($path);
        if ($absolute !== NULL) { @unlink($absolute); }
    }

    /**
     * Upload one indexed front/back input by re-mapping it to the single-file
     * field expected by CodeIgniter's upload library.
     *
     * @return string|null DB-relative path, or NULL when nothing uploaded.
     */
    private function _upload_identity_file($source_field, $index, $customer_id, $base_name, $old_path, &$error)
    {
        $entry = $this->_identity_upload_entry($source_field, $index);
        if ( ! $this->identity_upload_guard->is_present($entry)) {
            return NULL;
        }
        $_FILES['identity_upload'] = $entry;
        return $this->_handle_upload('identity_upload', $customer_id, $base_name, $old_path, $error);
    }

    /**
     * Re-render the shared booking form after validation or a late conflict.
     * Inventory receives the fragment as JSON so its modal stays open; the
     * normal Booking page keeps its existing full-page response.
     */
    private function _booking_form_failure(
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
    private function _checkin_form_failure(
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

    /** JSON output helper. */
    private function _json($payload, $http_status = 200)
    {
        $this->output
            ->set_status_header((int) $http_status)
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

    /** Normalise a date input into a real Y-m-d value. */
    private function _date($v)
    {
        if ($v === NULL || ! is_string($v)) { return NULL; }
        $value = trim($v);
        $dt = DateTime::createFromFormat('!Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : NULL;
    }

    /**
     * Convert Booking Form datetime-local values into the nightly half-open
     * range used by room inventory.
     */
    private function _datetime_availability_range($check_in, $check_out)
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
    private function _room_availability_range($booking = NULL, $use_post = FALSE)
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

    /** Prefer posted scheduled dates, then the legacy/manual form range. */
    private function _posted_booking_range($booking = NULL)
    {
        $scheduled_in = $this->_date($this->input->post('scheduled_check_in_date'));
        $scheduled_out = $this->_date($this->input->post('scheduled_check_out_date'));
        if ($scheduled_in && $scheduled_out && $scheduled_out > $scheduled_in) {
            return array($scheduled_in, $scheduled_out);
        }
        return $this->_room_availability_range($booking, TRUE);
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

    /** Form-validation callback: checkout is an exclusive date after check-in. */
    public function valid_stay_dates($checkout)
    {
        $checkin = $this->_date($this->input->post('scheduled_check_in_date'));
        $checkout = $this->_date($checkout);
        if ($checkin && $checkout && $checkout > $checkin) {
            return TRUE;
        }
        $this->form_validation->set_message(
            'valid_stay_dates',
            'Scheduled Check-Out must be after Scheduled Check-In.'
        );
        return FALSE;
    }

    /** Reject an allotted room when another live booking overlaps this stay. */
    public function room_available_for_stay($room_id)
    {
        if ($room_id === NULL || $room_id === '') {
            return TRUE;
        }

        $booking_id = (int) $this->input->post('booking_id');
        $check_in = $this->_date($this->input->post('scheduled_check_in_date'));
        $check_out = $this->_date($this->input->post('scheduled_check_out_date'));
        if ( ! $check_in || ! $check_out || $check_out <= $check_in) {
            $existing = $booking_id ? $this->Customer_model->get_booking(
                $this->current_tenant_id,
                $this->current_property_id,
                $booking_id
            ) : NULL;
            $range = $this->_room_availability_range($existing, TRUE);
            $check_in = $range[0];
            $check_out = $range[1];
        }

        if ($this->Customer_model->is_room_available(
            $this->current_tenant_id,
            $this->current_property_id,
            (int) $room_id,
            $check_in,
            $check_out,
            $booking_id ?: NULL
        )) {
            return TRUE;
        }

        $this->form_validation->set_message(
            'room_available_for_stay',
            'The selected room is already booked for the applicable stay dates.'
        );
        return FALSE;
    }

    /** A posted category must belong to the active property. */
    public function valid_booking_room_category($category_id)
    {
        if ($category_id === NULL || $category_id === '') {
            return TRUE;
        }
        if ($this->Customer_model->room_category_belongs_to_property(
            $this->current_property_id,
            (int) $category_id
        )) {
            return TRUE;
        }
        $this->form_validation->set_message(
            'valid_booking_room_category',
            'Select an active room category from the current property.'
        );
        return FALSE;
    }

    /** Booking channels are shared references, but forged/inactive ids fail. */
    public function valid_booking_channel($channel_id)
    {
        if ($channel_id === NULL || $channel_id === '') {
            return TRUE;
        }
        if ($this->Customer_model->active_booking_channel_exists((int) $channel_id)) {
            return TRUE;
        }
        $this->form_validation->set_message(
            'valid_booking_channel',
            'Select an active booking channel.'
        );
        return FALSE;
    }

    /** Check-out must be completed from the Check-in Details workflow. */
    public function checkin_status_allowed($status_id)
    {
        $status_code = $this->Customer_model->status_code((int) $status_id);
        if ($status_code && $status_code !== 'checked_out') {
            return TRUE;
        }

        $this->form_validation->set_message(
            'checkin_status_allowed',
            'Check Out is not available on this page.'
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
        $inventory_source = ! (int) $this->input->post('booking_id')
            && $this->input->post('booking_source') === 'inventory';
        $status_id   = $this->_status_id();
        $status_code = $this->Customer_model->status_code($status_id);
        $room_id = $this->input->post('room_id') ?: NULL;
        $room_category_id = $this->input->post('room_category_id') ?: NULL;
        if ($room_id) {
            $room_category_id = $this->Customer_model->room_category_for_room(
                $this->current_property_id,
                $room_id
            );
        }
        $booking = array(
            'booking_channel_id' => $this->input->post('booking_channel_id') ?: NULL,
            'status_id'          => $status_id,
            // Property context is never accepted from POST. Keep the legacy
            // display column only as a server-derived name snapshot.
            'property_id'        => (int) $this->current_property_id,
            'property_name'      => $this->current_property
                ? (string) $this->current_property->property_name
                : '',
            'room_id'            => $room_id,   // allotted room
            'checked_in_at'      => $inventory_source
                ? NULL
                : $this->_datetime($this->input->post('checked_in_at')),
            'checked_out_at'     => $inventory_source
                ? NULL
                : $this->_datetime($this->input->post('checked_out_at')),
            'length_of_stay'     => $this->_int($this->input->post('length_of_stay')),
            'total_guest'        => $this->_int($this->input->post('total_guest')),
            'room_category_id'   => $room_category_id,
            'room_quantity'      => $this->_int($this->input->post('room_quantity')),
            'total_unit'         => $this->_int($this->input->post('total_unit')),
            'total_amount'       => $this->_num($this->input->post('total_amount')),
            'amount_paid'        => $this->_num($this->input->post('amount_paid')),
        );

        // Inventory selections are future reservations, stored as a canonical
        // half-open scheduled range. Actual timestamps stay NULL until check-in.
        if ($inventory_source) {
            $scheduled_in = $this->_date($this->input->post('scheduled_check_in_date'));
            $scheduled_out = $this->_date($this->input->post('scheduled_check_out_date'));
            $booking['scheduled_check_in_date'] = $scheduled_in;
            $booking['scheduled_check_out_date'] = $scheduled_out;
            $booking['length_of_stay'] = ($scheduled_in && $scheduled_out)
                ? (int) ((strtotime($scheduled_out) - strtotime($scheduled_in)) / 86400)
                : NULL;
        }

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
