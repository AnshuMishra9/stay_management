<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Checkins — split out of the former Customers god controller (SRP).
 *  URLs remain unchanged; see application/config/routes.php. */
class Checkins extends Ops_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_model');
    }


    /**
     * Check-in Details list page — lists ONLY "Checked in" bookings.
     * Reached from the "Check-in Details" button on the Booking Details page.
     */
    public function index()
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
            'The selected room was just booked for part of this stay.'
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

}
