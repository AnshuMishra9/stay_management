<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Shared booking calculations with no controller or database dependencies. */
class Booking_calculator
{
	public static function length_of_stay($check_in, $check_out)
	{
		if (empty($check_in) || empty($check_out)) { return 0; }
		$days = (int) floor((strtotime($check_out) - strtotime($check_in)) / 86400);
		return max(0, $days);
	}

	public static function remaining_amount($total, $paid)
	{
		return max(0, (float) $total - (float) $paid);
	}

	/**
	 * Effective stay range for an existing booking row: scheduled dates win;
	 * actual check-in/out stamps are the fallback; a checked-in guest without
	 * checkout stays open-ended (NULL end).
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
			$cout = NULL; // In-house stays block the room until checkout.
		} else {
			$cout = date('Y-m-d', strtotime($cin.' +1 day')); // Undated holds occupy one night.
		}

		return array($cin, $cout);
	}
}


