<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Inventory
 *
 * A date-wise room AVAILABILITY calendar. For every day (present + future) it
 * shows how many rooms are still available per room type — total active rooms
 * minus the rooms held by active bookings that night. Availability remains
 * derived data; available room nights can launch the in-page booking flow.
 */
class Inventory extends Secure_Controller
{
    /** Selected date plus five dates before and five dates after it. */
    const DAYS = 11;
    const DAYS_EACH_SIDE = 5;

    /**
     * The first date not already visible is six days away from the centre.
     * Navigation brings that date into the centre column.
     */
    const NAVIGATION_STEP = 6;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Inventory_model');
    }

    /**
     * Optional ?start=Y-m-d chooses the selected/centre date (defaults to
     * today). The selected date is always the sixth column in the 11-day view.
     */
    public function index()
    {
        // Accept ?start only when it is a real, calendar-valid Y-m-d string.
        // Rejects arrays (?start[]=…) and rolled-over dates like 2026-02-30.
        $selected = $this->input->get('start');
        $dt = is_string($selected) ? DateTime::createFromFormat('Y-m-d', $selected) : FALSE;
        if ( ! $dt || $dt->format('Y-m-d') !== $selected) {
            $selected = date('Y-m-d');
        }

        // Get filter parameters
        $room_no = $this->input->get('room_no');
        $category_id = $this->input->get('category_id');
        $filters = array(
            'room_no'     => is_string($room_no) ? $room_no : '',
            'category_id' => is_string($category_id) ? $category_id : '',
        );

        $window_start = date(
            'Y-m-d',
            strtotime($selected.' -'.self::DAYS_EACH_SIDE.' day')
        );

        $data = $this->Inventory_model->availability($window_start, self::DAYS, $filters);

        // "start" remains the query/input name, but now represents the selected date.
        $data['start'] = $selected;
        $data['selected'] = $selected;
        $data['window_start'] = $window_start;
        $data['today'] = date('Y-m-d');
        $data['prev']  = date(
            'Y-m-d',
            strtotime($selected.' -'.self::NAVIGATION_STEP.' day')
        );
        $data['next']  = date(
            'Y-m-d',
            strtotime($selected.' +'.self::NAVIGATION_STEP.' day')
        );
        $data['end']   = end($data['dates']);
        $data['flash'] = $this->session->flashdata('inventory_msg');

        // Pass filters and categories for dropdown
        $data['filters'] = $filters;
        $data['categories'] = $this->Inventory_model->get_all_categories();

        $this->load->view('inventory/calendar', $data);
    }

    /**
     * Minimal, read-only detail payload for an occupied inventory cell.
     * The current database status is authoritative; document/identity records
     * are intentionally omitted because this popup only identifies the guest.
     */
    public function booking_detail($booking_id = NULL)
    {
        $this->load->model('Customer_model');
        $booking_id = is_numeric($booking_id) ? (int) $booking_id : 0;
        $booking = $booking_id > 0
            ? $this->Customer_model->get_booking_detail($booking_id)
            : NULL;

        if ( ! $booking) {
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'Booking not found. Refresh Inventory and try again.',
            ), 404);
        }

        $workflow = array(
            'room_booked' => array(
                'label' => 'Check-in',
                'url' => site_url('customers/bookings/checkin/'.$booking_id),
            ),
            'checked_in' => array(
                'label' => 'Check-out',
                'url' => site_url('customers/checkins/checkout/'.$booking_id),
            ),
            'checked_out' => array(
                'label' => 'View record',
                'url' => site_url('customers/checkedouts/details/'.$booking_id),
            ),
        );

        if ( ! isset($workflow[$booking->status_code])) {
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'This booking status has changed. Refresh Inventory to see the latest availability.',
            ), 409);
        }

        if ( ! $this->Inventory_model->booking_is_inventory_visible($booking_id)) {
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'This booking is no longer visible in Inventory. Refresh the page and try again.',
            ), 404);
        }

        return $this->_json(array(
            'status' => TRUE,
            'data' => array(
                'id' => (int) $booking->id,
                'booking_number' => $booking->booking_number,
                'customer_name' => $booking->customer_name,
                'customer_code' => $booking->customer_code,
                'phone' => $booking->phone,
                'country' => $booking->country,
                'allotted_room_no' => $booking->allotted_room_no,
                'room_category' => $booking->room_category,
                'scheduled_check_in_date' => $booking->scheduled_check_in_date,
                'scheduled_check_out_date' => $booking->scheduled_check_out_date,
                'total_guest' => $booking->total_guest,
                'channel_name' => $booking->channel_name,
                'checked_in_at' => $booking->checked_in_at,
                'checked_out_at' => $booking->checked_out_at,
                'status_name' => $booking->status_name,
                'status_code' => $booking->status_code,
                'workflow' => $workflow[$booking->status_code],
            ),
        ));
    }

    /** Send privacy-sensitive inventory JSON without allowing browser caches. */
    private function _json(array $payload, $http_status = 200)
    {
        return $this->output
            ->set_status_header((int) $http_status)
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }
}
