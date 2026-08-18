<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'inventory';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Authentication (OTP login)
$route['login']           = 'auth/index';
$route['logout']          = 'auth/logout';
$route['auth/send_otp']   = 'auth/send_otp';
$route['auth/verify_otp'] = 'auth/verify_otp';

// Protected pages
$route['dashboard']       = 'rooms/index';   // legacy alias -> Room Master
$route['inventory']       = 'inventory/index';   // room availability calendar
$route['inventory/booking_form'] = 'customers/inventory_booking_form'; // modal form fragment
$route['inventory/booking_detail/(:num)'] = 'inventory/booking_detail/$1'; // occupied-room guest popup

// Customers Master
$route['customers']              = 'customers/index';
$route['customers/list']         = 'customers/list_ajax';
$route['customers/add']          = 'customers/form';
$route['customers/edit/(:num)']  = 'customers/form/$1';

// Booking Details (booking-centric view over customers)
$route['customers/bookings']            = 'customers/bookings';
$route['customers/bookings_list']       = 'customers/bookings_ajax';
$route['customers/checkins']            = 'customers/checkins';           // checked-in list
$route['customers/checkins_list']       = 'customers/checkins_ajax';
$route['customers/checkedouts']         = 'customers/checkedouts';        // checked-out list
$route['customers/checkedouts_list']    = 'customers/checkedouts_ajax';
$route['customers/booking_form']        = 'customers/booking_form';        // new booking
$route['customers/booking_form/(:num)'] = 'customers/booking_form/$1';     // edit booking
$route['customers/booking_save']        = 'customers/booking_save';
$route['customers/booking_view/(:num)'] = 'customers/booking_view/$1';     // [AJAX] booking detail (eye)
$route['customers/available_rooms']      = 'customers/available_rooms_ajax';
$route['customers/checkin/(:num)']      = 'customers/checkin/$1';          // check-in page
$route['customers/checkin_save']        = 'customers/checkin_save';
$route['customers/bookings/checkin/(:num)']     = 'customers/checkin/$1';
$route['customers/bookings/edit/(:num)']        = 'customers/booking_form/$1';
$route['customers/checkins/checkout/(:num)']    = 'customers/checkout/$1';
$route['customers/checkins/edit/(:num)']        = 'customers/checkin_edit/$1';
$route['customers/checkedouts/details/(:num)']  = 'customers/checkedout_details/$1';
$route['customers/checkedouts/edit/(:num)']     = 'customers/checkedout_edit/$1';
$route['customers/checkout_save']               = 'customers/checkout_save';
$route['customers/lookup']              = 'customers/lookup';              // [AJAX] customer by mobile

// Rooms Master (Room Manager)
$route['rooms']              = 'rooms/index';
$route['rooms/list']         = 'rooms/list_ajax';
$route['rooms/add']          = 'rooms/form';
$route['rooms/edit/(:num)']  = 'rooms/form/$1';
