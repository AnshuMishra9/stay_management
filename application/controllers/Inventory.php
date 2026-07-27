<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Inventory
 *
 * A date-wise room AVAILABILITY calendar. For every day (present + future) it
 * shows how many rooms are still available per room type — total active rooms
 * minus the rooms held by active bookings that night. Read-only: the numbers
 * are derived from `rooms` + `booking_details`, not manually set.
 */
class Inventory extends Secure_Controller
{
    /** Number of days shown per window. */
    const DAYS = 15;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Inventory_model');
    }

    /**
     * Availability calendar. Optional ?start=Y-m-d chooses the window start
     * (defaults to today); prev/next shift the window by DAYS.
     */
    public function index()
    {
        // Accept ?start only when it is a real, calendar-valid Y-m-d string.
        // Rejects arrays (?start[]=…) and rolled-over dates like 2026-02-30.
        $start = $this->input->get('start');
        $dt = is_string($start) ? DateTime::createFromFormat('Y-m-d', $start) : FALSE;
        if ( ! $dt || $dt->format('Y-m-d') !== $start) {
            $start = date('Y-m-d');
        }

        // Get filter parameters
        $filters = array(
            'room_no'      => $this->input->get('room_no'),
            'category_id'  => $this->input->get('category_id'),
        );

        $data = $this->Inventory_model->availability($start, self::DAYS, $filters);
        $data['start'] = $start;
        $data['today'] = date('Y-m-d');
        $data['prev']  = date('Y-m-d', strtotime($start.' -'.self::DAYS.' day'));
        $data['next']  = date('Y-m-d', strtotime($start.' +'.self::DAYS.' day'));
        $data['end']   = end($data['dates']);

        // Pass filters and categories for dropdown
        $data['filters'] = $filters;
        $data['categories'] = $this->Inventory_model->get_all_categories();

        $this->load->view('inventory/calendar', $data);
    }
}
