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
        $cfg['categories'] = $this->Customer_model->room_categories();   // for the Room Category filter dropdown
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
            $booking = $this->Customer_model->get_booking($booking_id);
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
            $customer = $this->Customer_model->get_by_id($booking->customer_id);
        }

        $room_range = $this->_room_availability_range($booking);
        $data = array(
            'booking'       => $booking,
            'customer'      => $customer,
            'country_opts'  => $this->_country_options(),
            'channel_opts'  => $this->Customer_model->booking_channels(),
            'room_cat_opts' => $this->Customer_model->room_categories(),
            'room_opts'     => $this->Customer_model->available_rooms(
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

        $room_opts = $this->Customer_model->available_rooms(NULL, $check_in, $check_out);
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
            'room_cat_opts' => $this->Customer_model->room_categories(),
            'room_opts'     => $room_opts,
            'status_opts'   => $this->Customer_model->all_statuses(),
            'form_context'  => 'inventory',
            'booking_form_error' => '',
            'booking_defaults' => array(
                'status_id' => $room_booked_id,
                'room_id' => $room_id,
                'room_category_id' => (int) $selected_room->category_id,
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

    /** [AJAX] Rooms available for the Booking Form Check In / Check Out range. */
    public function available_rooms_ajax()
    {
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
                $booking_id ?: NULL,
                $range[0],
                $range[1]
            ),
        ));
    }

    /** [AJAX] "Room booked" bookings (Booking Details list). */
    public function bookings_ajax()
    {
        return $this->_json(array('status' => TRUE, 'data' =>
            $this->Customer_model->get_bookings($this->_booking_filters('room_booked'))));
    }

    /** [AJAX] "Checked in" bookings (Check-in Details list). */
    public function checkins_ajax()
    {
        return $this->_json(array('status' => TRUE, 'data' =>
            $this->Customer_model->get_bookings($this->_booking_filters('checked_in'))));
    }

    /** [AJAX] "Checked out" bookings (Check-out Details list). */
    public function checkedouts_ajax()
    {
        return $this->_json(array('status' => TRUE, 'data' =>
            $this->Customer_model->get_bookings($this->_booking_filters('checked_out'))));
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
        $inventory_source = ! $booking_id
            && $this->input->post('booking_source') === 'inventory';
        $existing_booking = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
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
        $room_id = isset($booking['room_id']) ? (int) $booking['room_id'] : 0;
        $room_range = $this->_posted_booking_range($existing_booking);

        // Customer + booking write is atomic. Lock the room first, then repeat
        // the availability test as a locking/current read to close stale-page
        // and simultaneous-submit races.
        $this->db->trans_begin();

        if ($room_id) {
            $rooms_to_lock = array($room_id);
            if ($existing_booking && $existing_booking->room_id) {
                $rooms_to_lock[] = (int) $existing_booking->room_id;
            }
            $locked_rooms = $this->Customer_model->lock_rooms_for_booking($rooms_to_lock);
            if (
                ! in_array($room_id, $locked_rooms, TRUE)
                || ! $this->Customer_model->is_room_available_for_update(
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

        // MAX+1 codes are protected by one stable row lock for new bookings.
        if ( ! $booking_id && ! $this->Customer_model->lock_booking_creation_sequence()) {
            $this->db->trans_rollback();
            return $this->_booking_form_failure(
                $existing_booking,
                $booking_id,
                $inventory_source,
                'The booking could not be created right now. Please try again.',
                503
            );
        }

        $customer = $this->Customer_model->get_by_phone($phone);
        if ($customer) {
            $this->Customer_model->update($customer->id, $cdata);
            $cust_id = (int) $customer->id;
        } else {
            $cdata['customer_code'] = $this->Customer_model->next_code();
            $cdata['is_active'] = 1;
            $cust_id = $this->Customer_model->insert($cdata);
        }

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
        $booking = $booking_id ? $this->Customer_model->get_booking_detail($booking_id) : NULL;
        if ( ! $booking) {
            return $this->_json(array('status' => FALSE, 'message' => 'Booking not found.'));
        }

        $labels = $this->_identity_types();
        $booking->identities = array_map(function ($idn) use ($labels) {
            return array(
                'type_label'      => isset($labels[$idn->identity_type]) ? $labels[$idn->identity_type] : $idn->identity_type,
                'identity_number' => $idn->identity_number,
                'document_url'    => $idn->document_path ? site_url('customers/identity_file/'.$idn->id) : NULL,
            );
        }, $this->Customer_model->get_identities($booking->customer_id));

        return $this->_json(array('status' => TRUE, 'data' => $booking));
    }

    /**
     * Check-in page — a focused edit of just the fields needed at check-in:
     * the customer's name + mobile, their identity proofs (same block as the
     * customer master), and the booking status. Existing values are pre-loaded.
     */
    public function checkin($booking_id = NULL)
    {
        $booking = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
        if ( ! $booking || $this->Customer_model->status_code($booking->status_id) !== 'room_booked') {
            show_404();
            return;
        }
        $customer = $this->Customer_model->get_by_id($booking->customer_id);

        $data = array(
            'booking'        => $booking,
            'customer'       => $customer,
            'status_opts'    => $this->Customer_model->all_statuses(),
            'room_cat_opts'  => $this->Customer_model->room_categories(),
            'room_opts'      => $this->Customer_model->available_rooms(
                $booking_id,
                $this->_date($booking->scheduled_check_in_date),
                $this->_date($booking->scheduled_check_out_date)
            ),
            'identity_types' => $this->_identity_types(),
            'identities'     => $customer ? $this->Customer_model->get_identities($customer->id) : array(),
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
        $booking = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
        if ( ! $booking || $this->Customer_model->status_code($booking->status_id) !== 'checked_in') {
            show_404();
            return;
        }

        $customer = $this->Customer_model->get_by_id($booking->customer_id);
        if ( ! $customer) {
            show_404();
            return;
        }

        $data = array(
            'booking'        => $booking,
            'customer'       => $customer,
            'status_opts'    => $this->Customer_model->all_statuses(),
            'room_cat_opts'  => $this->Customer_model->room_categories(),
            'room_opts'      => $this->Customer_model->available_rooms(
                $booking_id,
                $this->_date($booking->scheduled_check_in_date),
                $this->_date($booking->scheduled_check_out_date)
            ),
            'identity_types' => $this->_identity_types(),
            'identities'     => $this->Customer_model->get_identities($customer->id),
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
        $booking = $booking_id ? $this->Customer_model->get_booking_detail($booking_id) : NULL;
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
            return $identity;
        }, $this->Customer_model->get_identities($booking->customer_id));

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
        $booking_id = (int) $this->input->post('booking_id');
        $booking = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
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

        $now = date('Y-m-d H:i:s');
        $this->Customer_model->update_booking($booking_id, array(
            'status_id'      => $checked_out_status,
            'checked_in_at'  => $booking->checked_in_at ?: $now,
            'checked_out_at' => $now,
        ));

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
        $booking_id = (int) $this->input->post('booking_id');
        $booking    = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
        if ( ! $booking) {
            show_404();
            return;
        }
        $customer = $this->Customer_model->get_by_id($booking->customer_id);
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

        if ($this->form_validation->run() === FALSE) {
            return $this->_checkin_form_failure(
                $booking,
                $customer,
                $booking_id,
                $page_context
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
            $room_category_id = $this->Customer_model->room_category_for_room($room_id);
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

        // Use the same room-lock protocol as Inventory booking creation.
        // Validation above gives quick feedback; this second, locking read is
        // what prevents a stale check-in form racing a simultaneous booking.
        $this->db->trans_begin();
        $rooms_to_lock = array();
        if ($room_id) {
            $rooms_to_lock[] = (int) $room_id;
        }
        if ($booking->room_id) {
            $rooms_to_lock[] = (int) $booking->room_id;
        }
        $locked_rooms = $this->Customer_model->lock_rooms_for_booking($rooms_to_lock);

        // A checkout or another status workflow may have completed after the
        // page was opened. Lock/re-read the booking so stale Check-in data can
        // never overwrite a newer terminal status.
        $locked_booking = $this->Customer_model->get_booking_for_update($booking_id);
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

        if (
            $room_id
            && (
                ! in_array((int) $room_id, $locked_rooms, TRUE)
                || ! $this->Customer_model->is_room_available_for_update(
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
        $customer_updated = $this->Customer_model->update($customer->id, array(
            'customer_name' => $this->input->post('customer_name', TRUE),
            'phone'         => $this->input->post('phone', TRUE),
        ));
        $booking_updated = $this->Customer_model->update_booking($booking_id, $upd);

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
        $this->_save_identities($customer->id, $customer->customer_code);

        $this->session->set_flashdata('booking_msg', array(
            'type' => 'success',
            'text' => 'Booking '.$booking->booking_number.' checked-in details updated.',
        ));
        redirect($page_context === 'checkins' ? 'customers/checkins' : 'customers/bookings');
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
            'customer'      => $existing ? $this->Customer_model->get_by_id($existing->customer_id) : NULL,
            'country_opts'  => $this->_country_options(),
            'channel_opts'  => $this->Customer_model->booking_channels(),
            'room_cat_opts' => $this->Customer_model->room_categories(),
            'room_opts'     => $this->Customer_model->available_rooms(
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
            'room_cat_opts'  => $this->Customer_model->room_categories(),
            'room_opts'      => $this->Customer_model->available_rooms(
                $booking_id,
                $this->_date($this->input->post('scheduled_check_in_date')),
                $this->_date($this->input->post('scheduled_check_out_date'))
            ),
            'identity_types' => $this->_identity_types(),
            'identities'     => $this->Customer_model->get_identities($customer->id),
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
            $existing = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
            $range = $this->_room_availability_range($existing, TRUE);
            $check_in = $range[0];
            $check_out = $range[1];
        }

        if ($this->Customer_model->is_room_available(
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
            $existing = $booking_id ? $this->Customer_model->get_booking($booking_id) : NULL;
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
            $room_category_id = $this->Customer_model->room_category_for_room($room_id);
        }
        $booking = array(
            'booking_channel_id' => $this->input->post('booking_channel_id') ?: NULL,
            'status_id'          => $status_id,
            'property_name'      => $this->input->post('property_name', TRUE),
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
