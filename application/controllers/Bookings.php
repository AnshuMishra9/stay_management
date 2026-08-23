<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Bookings — split out of the former Customers god controller (SRP).
 *  URLs remain unchanged; see application/config/routes.php. */
class Bookings extends Ops_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_model');
    }


    /**
     * Booking Details list page — lists ONLY "Room booked" bookings.
     */
    public function index()
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



    /**
     * Build a `booking_details` row from POST: raw booking fields + the
     * server-derived ones (remaining amount) + the check-in/check-out
     * auto-stamp driven by the status (from status_master).
     */
    protected function _booking_from_post()
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



}
