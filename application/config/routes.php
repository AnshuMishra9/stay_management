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

// Role and ownership management
$route['admins']                  = 'admins/index';
$route['admins/add']              = 'admins/form';
$route['admins/edit/(:num)']      = 'admins/form/$1';
$route['admins/save']             = 'admins/save';
$route['admins/status/(:num)']    = 'admins/status/$1';
$route['admins/delete/(:num)']    = 'admins/delete/$1';

$route['properties']               = 'properties/index';
$route['properties/add']           = 'properties/form';
$route['properties/edit/(:num)']   = 'properties/form/$1';
$route['properties/save']          = 'properties/save';
$route['properties/status/(:num)'] = 'properties/status/$1';
$route['properties/delete/(:num)'] = 'properties/delete/$1';
$route['properties/select']        = 'properties/select_property';
$route['properties/switch']        = 'properties/switch_property';

$route['users']                  = 'users/index';
$route['users/add']              = 'users/form';
$route['users/edit/(:num)']      = 'users/form/$1';
$route['users/save']             = 'users/save';
$route['users/status/(:num)']    = 'users/status/$1';
$route['users/delete/(:num)']    = 'users/delete/$1';

$route['access/no-properties'] = 'access/no_properties';
$route['access/forbidden']     = 'access/forbidden';

// Keep the dashboard alias for bookmarks and links created before Room Master.
$route['dashboard']       = 'rooms/index';
$route['inventory']       = 'inventory/index';
$route['inventory/booking_form'] = 'bookings/inventory_booking_form';
$route['inventory/booking_detail/(:num)'] = 'inventory/booking_detail/$1';

// Customers Master
$route['customers']              = 'customers/index';
$route['customers/list']         = 'customers/list_ajax';
$route['customers/add']          = 'customers/form';
$route['customers/edit/(:num)']  = 'customers/form/$1';

// Preserve legacy customer URLs while dispatching each workflow to its dedicated controller.
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
$route['customers/checkins/checkout/(:num)'] = 'checkouts/checkout/$1';
$route['customers/checkins/edit/(:num)']     = 'checkins/checkin_edit/$1';

$route['customers/checkedouts']              = 'checkouts/index';
$route['customers/checkedouts_list']         = 'checkouts/checkedouts_ajax';
$route['customers/checkedouts_ajax']         = 'checkouts/checkedouts_ajax';
$route['customers/checkedouts/details/(:num)'] = 'checkouts/checkedout_details/$1';
$route['customers/checkedouts/edit/(:num)']  = 'checkouts/checkedout_edit/$1';
$route['customers/checkout_save']            = 'checkouts/checkout_save';

$route['customers/lookup']              = 'customers/lookup';

// Room management
$route['rooms']              = 'rooms/index';
$route['rooms/list']         = 'rooms/list_ajax';
$route['rooms/add']          = 'rooms/form';
$route['rooms/edit/(:num)']  = 'rooms/form/$1';

// Property-scoped Room Category Master
$route['room-categories']                = 'roomcategories/index';
$route['room-categories/add']            = 'roomcategories/form';
$route['room-categories/edit/(:num)']    = 'roomcategories/form/$1';
$route['room-categories/save']           = 'roomcategories/save';
$route['room-categories/delete/(:num)']  = 'roomcategories/delete/$1';
