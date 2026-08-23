<?php
/**
 * One-shot refactor: split Customers.php (God controller) into
 * Customers / Bookings / Checkins / Checkouts + shared Ops_Controller.
 * Method bodies are moved VERBATIM by line range — zero transcription risk.
 */

$src = dirname(__DIR__).'/application/controllers/Customers.php';
$lines = file($src);
$n = count($lines);

// ---- 1) locate every method -------------------------------------------------
$methods = array();          // name => ['start'=>lineIdx(1-based incl docblock), 'sig'=>lineIdx]
foreach ($lines as $i => $line) {
	if (preg_match('/^\s+(public|private)\s+function\s+(\w+)\s*\(/', $line, $m)) {
		$methods[$m[2]] = array('sig' => $i + 1);            // 1-based
	}
}
// attach preceding docblock/comment lines to each method
foreach ($methods as $name => &$info) {
	$s = $info['sig'] - 1;                                    // 0-based sig line
	$j = $s - 1;
	while ($j >= 0) {
		$t = trim($lines[$j]);
		if ($t === '' || $t === '*/' || strpos($t, '*') === 0 || strpos($t, '/**') === 0 || strpos($t, '//') === 0) {
			$j--; continue;
		}
		break;
	}
	$info['start'] = $j + 2;                                  // 1-based inclusive
}
unset($info);

$order = array();
foreach ($lines as $i => $line) {
	if (preg_match('/^\s+(public|private)\s+function\s+(\w+)\s*\(/', $line, $m)) {
		$order[] = $m[2];
	}
}
// end of method i = start of next method - 1 ; last method ends before closing class brace
$names = array_values(array_unique($order));
for ($k = 0; $k < count($names); $k++) {
	$startNext = isset($names[$k+1]) ? $methods[$names[$k+1]]['start'] : $n; // include trailing "}\n" of class? handle below
	$methods[$names[$k]]['end'] = $startNext - 1;
}

function slice(array $lines, array $methods, $name) {
	return implode('', array_slice($lines, $methods[$name]['start'] - 1, $methods[$name]['end'] - $methods[$name]['start'] + 1));
}

// ---- 2) partition -----------------------------------------------------------
$toCustomers = array('__construct','index','form','list_ajax','lookup','view','save','delete','identity_file');
$toBookings  = array('booking_form','inventory_booking_form','available_rooms_ajax','bookings_ajax','booking_save','booking_view','valid_stay_dates','room_available_for_stay','valid_booking_room_category','valid_booking_channel','_booking_from_post','_posted_booking_range','_datetime_availability_range','_room_availability_range');
$toCheckins  = array('checkins_ajax','checkin','checkin_edit','checkin_save','inventory_checkin_date','checkin_status_allowed','can_check_in_on_scheduled_date');
$toCheckouts = array('checkedouts_ajax','checkout','checkedout_details','checkedout_edit','_render_checkout_page','checkout_save');
$toShared    = array('bookings','checkins','checkedouts','_booking_filters','_render_booking_list','_status_id','_booking_form_failure','_checkin_form_failure','_json','_int','_num','_datetime','_date','_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error','_identity_belongs_to_scope','_valid_customer_write_token','_require_property_context','_save_identities','_delete_identity_path','_upload_identity_file','_country_options','_identity_types');

$assigned = array_merge($toCustomers, $toBookings, $toCheckins, $toCheckouts, $toShared);
$all = array_keys($methods);
$missing = array_diff($all, $assigned);
if ($missing) { echo "MISSING PARTITION: ".implode(', ', $missing)."\n"; exit(1); }

// ---- 3) emit shared Ops_Controller ------------------------------------------
$sharedBody = '';
foreach ($toShared as $m) {
	$body = slice($lines, $methods, $m);
	$body = preg_replace('/^(\s*)(private|public)\s+function/', '$1protected function', $body);
	$sharedBody .= "\n    /* Moved verbatim from Customers.php */\n".$body."\n";
}
$opsFile = dirname(__DIR__).'/application/core/Ops_Controller.php';
$opsPhp = "<?php\ndefined('BASEPATH') OR exit('No direct script access allowed');\n\n"
	."/**\n * Shared operational plumbing for the split stay-management controllers\n"
	." * (Customers / Bookings / Checkins / Checkouts). Everything here was\n"
	." * moved VERBATIM out of the former God controller.\n */\n"
	."class Ops_Controller extends Property_Controller\n{\n"
	."    public function __construct()\n    {\n"
	."        parent::__construct();\n"
	."        \$this->load->model('Customer_model');\n"
	."    }\n"
	.$sharedBody
."}\n";
file_put_contents($opsFile, $opsPhp);

// ---- 4) emit feature controllers --------------------------------------------
$base = dirname(__DIR__).'/application/controllers/';
// name-in-new-controller => source-method-name (bodies move VERBATIM)
function emit_controller($file, $class, $methodsMap, array $lines, array $methods) {
	$body = '';
	foreach ($methodsMap as $asName => $srcName) {
		$chunk = slice($lines, $methods, $srcName);
		if ($asName !== $srcName) {
			// rename the emitted function (e.g. bookings() -> index())
			$chunk = preg_replace('/^(\s*)(public|private)(\s+function\s+)'.preg_quote($srcName,'/').'(\s*\()/m', '$1public$3'.$asName.'$4', $chunk, 1);
		}
		$body .= "\n".$chunk."\n";
	}
	$php = "<?php\ndefined('BASEPATH') OR exit('No direct script access allowed');\n\n"
		."/** {$class} — split out of the former Customers god controller (SRP).\n"
		." *  URLs remain unchanged; see application/config/routes.php. */\n"
		."class {$class} extends Ops_Controller\n{\n"
		."    public function __construct()\n    {\n"
		."        parent::__construct();\n"
		."        \$this->load->model('Room_model');\n"
		."    }\n"
		.$body
	."}\n";
	file_put_contents($file, $php);
}

emit_controller($base.'Bookings.php', 'Bookings',
	array(
		'index'                    => 'bookings',
		'booking_form'             => 'booking_form',
		'inventory_booking_form'   => 'inventory_booking_form',
		'available_rooms_ajax'     => 'available_rooms_ajax',
		'bookings_ajax'            => 'bookings_ajax',
		'booking_save'             => 'booking_save',
		'booking_view'             => 'booking_view',
		'valid_stay_dates'         => 'valid_stay_dates',
		'room_available_for_stay'  => 'room_available_for_stay',
		'valid_booking_room_category' => 'valid_booking_room_category',
		'valid_booking_channel'    => 'valid_booking_channel',
		'_booking_from_post'       => '_booking_from_post',
		'_posted_booking_range'    => '_posted_booking_range',
		'_datetime_availability_range' => '_datetime_availability_range',
		'_room_availability_range' => '_room_availability_range',
	),
	$lines, $methods);

emit_controller($base.'Checkins.php', 'Checkins',
	array(
		'index'                     => 'checkins',
		'checkins_ajax'             => 'checkins_ajax',
		'checkin'                   => 'checkin',
		'checkin_edit'              => 'checkin_edit',
		'checkin_save'              => 'checkin_save',
		'inventory_checkin_date'    => 'inventory_checkin_date',
		'checkin_status_allowed'    => 'checkin_status_allowed',
		'can_check_in_on_scheduled_date' => 'can_check_in_on_scheduled_date',
	),
	$lines, $methods);

emit_controller($base.'Checkouts.php', 'Checkouts',
	array(
		'index'              => 'checkedouts',
		'checkedouts_ajax'   => 'checkedouts_ajax',
		'checkout'           => 'checkout',
		'checkedout_details' => 'checkedout_details',
		'checkedout_edit'    => 'checkedout_edit',
		'_render_checkout_page' => '_render_checkout_page',
		'checkout_save'      => 'checkout_save',
	),
	$lines, $methods);

// ---- 5) slim Customers.php --------------------------------------------------
$remove = array_merge($toBookings, $toCheckins, $toCheckouts, $toShared);
$keepLines = array();
$skipUntil = 0;
foreach ($lines as $idx => $line) {
	$ln = $idx + 1;
	if ($ln < $skipUntil) { continue; }
	$hit = false;
	foreach ($remove as $m) {
		if ($ln >= $methods[$m]['start'] && $ln <= $methods[$m]['end']) {
			$skipUntil = $methods[$m]['end'] + 1;
			$hit = true; break;
		}
	}
	if (! $hit) { $keepLines[] = $line; }
}
file_put_contents($src, implode('', $keepLines));
$slim = file_get_contents($src);
$slim = str_replace('class Customers extends Property_Controller', 'class Customers extends Ops_Controller', $slim);
file_put_contents($src, $slim);

echo "Split complete.\n";
foreach (array('Customers','Bookings','Checkins','Checkouts') as $c) {
	$f = $base.$c.'.php';
	echo str_replace(dirname(__DIR__).'/', '', $f).' : '.round(filesize($f)/1024,1)." KB\n";
}
echo 'Ops_Controller.php : '.round(filesize($opsFile)/1024,1)." KB\n";
