<?php
/**
 * Canonical Ops_Controller builder v2.
 *
 * Sources (priority order):
 *   1. Current (possibly messy) Ops_Controller.php
 *   2. git HEAD application/controllers/Customers.php
 *
 * Every candidate method is emitted EXACTLY ONCE. Class constants from
 * HEAD Customers are also carried over.
 */
$root = dirname(__DIR__);
$sources = array();

// --- source 1: current Ops ---
$cur = @file_get_contents($root.'/application/core/Ops_Controller.php');
if ($cur) { $sources[] = array('name'=>'current-ops', 'text'=>$cur); }

// --- source 2: HEAD Customers ---
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
if ($head) { $sources[] = array('name'=>'head-customers', 'text'=>$head); }

function parse_methods($text)
{
	$lines = explode("\n", $text);
	$meth = array(); $order = array();
	foreach ($lines as $i => $line) {
		if (preg_match('/^\s+(public|private|protected)\s+function\s+(\w+)\s*\(/', $line, $m)) {
			if ( ! isset($meth[$m[2]]['sig'])) { $order[] = $m[2]; }
			$meth[$m[2]]['sig'] = $i;
		}
	}
	for ($k = 0; $k < count($order); $k++) {
		$s = $meth[$order[$k]]['sig']; $j = $s - 1;
		while ($j >= 0) {
			$t = trim($lines[$j]);
			if ($t === '' || $t[0] === '*' || strpos($t,'/**') === 0 || strpos($t,'//') === 0) { $j--; continue; }
			break;
		}
		$meth[$order[$k]]['start'] = $j + 1;
		$meth[$order[$k]]['end']   = ($k+1 < count($order)) ? ($meth[$order[$k+1]]['start'] - 1) : (count($lines)-1);
	}
	$out = array();
	foreach ($order as $n) {
		$body = implode("\n", array_slice($lines, $meth[$n]['start'], $meth[$n]['end'] - $meth[$n]['start'] + 1));
		$body = preg_replace('/^(\s*)(private|public)(\s+function\s+)/m', '${1}protected${3}', $body);
		$out[$n] = trim($body, "\n");
	}
	return array($out, $order);
}

// constants defined in HEAD Customers class
preg_match_all('/^\s*const\s+(\w+)\s*=\s*([^;]+);/m', $head, $cmap, PREG_SET_ORDER);

$library = array();      // name => body
$orderSeen = array();
foreach ($sources as $src) {
	list($methods,) = parse_methods($src['text']);
	foreach ($methods as $name => $body) {
		if ( ! isset($library[$name])) { $library[$name] = $body; $orderSeen[] = $name; }
	}
}

$required = array(
	'_render_booking_list','_booking_filters','_status_id',
	'_booking_form_failure','_checkin_form_failure',
	'_json','_int','_num','_datetime','_date',
	'_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error',
	'_identity_belongs_to_scope','_valid_customer_write_token','_require_property_context',
	'_save_identities','_delete_identity_path','_upload_identity_file',
	'_country_options','_identity_types',
	'_posted_booking_range','_datetime_availability_range','_room_availability_range',
);
$missing = array_diff($required, array_keys($library));
if ($missing) { fwrite(STDERR, "STILL MISSING: ".implode(', ', $missing)."\n"); exit(1); }

$bodyOut = '';
foreach ($required as $n) {
	$bodyOut .= "\n".trim($library[$n])."\n";
}

$constOut = '';
foreach ($cmap as $c) {
	$constOut .= "    const {$c[1]} = {$c[2]};\n";
}

$php = "<?php\ndefined('BASEPATH') OR exit('No direct script access allowed');\n\n"
	."/**\n * Shared operational plumbing for the split stay-management controllers\n"
	." * (Customers / Bookings / Checkins / Checkouts).\n */\n"
	."class Ops_Controller extends Property_Controller\n{\n"
	.$constOut
	."    public function __construct()\n    {\n"
	."        parent::__construct();\n"
	."        \$this->load->model('Customer_model');\n"
	."        \$this->load->helper('security');\n"
	."        \$this->load->library('identity_upload_guard');\n"
	."        \$this->load->library('booking_calculator');\n"
	."    }\n"
	.$bodyOut
."}\n";

file_put_contents($root.'/application/core/Ops_Controller.php', $php);
echo "Ops rebuilt canonically: ".count($required)." required methods present, "
	.count($library)." total methods carried, ".count($cmap)." constants.\n";
