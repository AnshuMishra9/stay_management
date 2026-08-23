<?php
$f = dirname(__DIR__).'/application/config/routes.php';
$t = file_get_contents($f);

// 1) inventory modal form now lives in Bookings
$t = str_replace(
	'$route[\'inventory/booking_form\'] = \'customers/inventory_booking_form\';',
	'$route[\'inventory/booking_form\'] = \'bookings/inventory_booking_form\';',
	$t, $c1);

// 2) swap the whole legacy bookings/checkins/checkedouts routing block
$legacy_block_start = strpos($t, "// Booking Details (booking-centric view over customers)");
$legacy_block_end   = strpos($t, "// Rooms Master");
if ($legacy_block_start === FALSE || $legacy_block_end === FALSE || $legacy_block_end <= $legacy_block_start) {
	fwrite(STDERR, "block markers not found\n"); exit(1);
}
$new_block = <<<'BLOCK'
// ---- Bookings / Checkins / Checkouts (split from the Customers god controller) ----
// Every legacy URL is preserved 1:1 — only the routing targets changed.
$route['customers/bookings']                 = 'bookings/index';
$route['customers/bookings_list']            = 'bookings/bookings_ajax';
$route['customers/bookings_ajax']            = 'bookings/bookings_ajax';
$route['customers/booking_form']             = 'bookings/booking_form';
$route['customers/booking_form/(:num)']      = 'bookings/booking_form/$1';
$route['customers/bookings/edit/(:num)']     = 'bookings/booking_form/$1';
$route['customers/booking_save']             = 'bookings/booking_save';
$route['customers/booking_view/(:num)']      = 'bookings/booking_view/$1';
$route['customers/available_rooms']          = 'bookings/available_rooms_ajax';

$route['customers/checkins']                 = 'checkins/index';
$route['customers/checkins_list']            = 'checkins/checkins_ajax';
$route['customers/checkins_ajax']            = 'checkins/checkins_ajax';
$route['customers/checkin/(:num)']           = 'checkins/checkin/$1';
$route['customers/checkin_save']             = 'checkins/checkin_save';
$route['customers/bookings/checkin/(:num)']  = 'checkins/checkin/$1';
$route['customers/checkins/edit/(:num)']     = 'checkins/checkin_edit/$1';

$route['customers/checkedouts']              = 'checkouts/index';
$route['customers/checkedouts_list']         = 'checkouts/checkedouts_ajax';
$route['customers/checkedouts_ajax']         = 'checkouts/checkedouts_ajax';
$route['customers/checkout_save']            = 'checkouts/checkout_save';

$route['customers/lookup']              = 'customers/lookup';              // [AJAX] customer by mobile


BLOCK;
$t = substr_replace($t, $new_block, $legacy_block_start, $legacy_block_end - $legacy_block_start);

file_put_contents($f, $t);
echo "routes updated (inventory ref: {$c1})\n";
