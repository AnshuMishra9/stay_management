<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Booking calculation rules â€” pure functions extracted from the former
 * Customers god controller so every controller (Bookings/Checkins/Checkouts)
 * shares one implementation.
 */
class Booking_calculator
{
	/** Whole nights between two Y-m-d dates (min 0). */
	public static function length_of_stay($check_in, $check_out)
	{
		if (empty($check_in) || empty($check_out)) { return 0; }
		$days = (int) floor((strtotime($check_out) - strtotime($check_in)) / 86400);
		return max(0, $days);
	}

	/** Amount still owed after payments. Never negative. */
	public static function remaining_amount($total, $paid)
	{
		return max(0, (float) $total - (float) $paid);
	}

	/**
	 * Effective stay range for an existing booking row: scheduled dates win;
	 * actual check-in/out stamps are the fallback; a checked-in guest without
	 * checkout stays open-ended (NULL end).
	 *
	 * @param  object     $row       booking row (aliased columns ok)
	 * @param  string     $status_code
	 * @return array                 [cin|null, cout|null]
	 */
	public static function effective_stay_range($row, $status_code)
	{
		$cin = ! empty($row->scheduled_check_in_date)
			? $row->scheduled_check_in_date
			: (! empty($row->checked_in_at) ? substr($row->checked_in_at, 0, 10) : NULL);

		if (empty($cin)) { return array(NULL, NULL); }

		if ($status_code === 'checked_out' && ! empty($row->checked_out_at)) {
			$cout = substr($row->checked_out_at, 0, 10);
		} elseif (! empty($row->scheduled_check_out_date)) {
			$cout = $row->scheduled_check_out_date;
		} elseif (! empty($row->checked_out_at)) {
			$cout = substr($row->checked_out_at, 0, 10);
		} elseif ($status_code === 'checked_in') {
			$cout = NULL;                       // in-house: open-ended
		} else {
			$cout = date('Y-m-d', strtotime($cin.' +1 day'));   // single night
		}

		return array($cin, $cout);
	}
}


