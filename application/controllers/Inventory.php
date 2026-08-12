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
}
