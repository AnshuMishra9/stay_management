<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Checkout workflow; completed stays remain read-only. */
class Checkouts extends Ops_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Room_model');
    }


    public function index()
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



    public function checkout($booking_id = NULL)
    {
        $this->_render_checkout_page($booking_id, 'confirm');
    }



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



    protected function _render_checkout_page($booking_id, $mode)
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

}
