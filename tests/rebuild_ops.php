<?php
/**
 * Rebuild Ops_Controller.php cleanly:
 *   A) the 9 helper methods currently intact in the damaged file
 *   B) the 17 methods recovered verbatim from git HEAD (already extracted
 *      by recover_ops_methods.php into a temp var — re-extracted here)
 *   C) today's no-op _delete_identity_path stub
 */
$root = dirname(__DIR__);
$damaged = file_get_contents($root.'/application/core/Ops_Controller.php');

// ---------- parse methods out of the damaged file ----------
preg_match_all('/\n(    \/\*\*[\s\S]*?\*\/\n)?    (?:public|protected) function (\w+)\(([\s\S]*?)\n    \}\n/', "\n".$damaged."\n", $mm, PREG_SET_ORDER);
$have = array();
foreach ($mm as $mset) {
	$body = $mset[0];
	$body = trim($body, "\n");
	$have[$mset[2]] = $body;
}
echo "parsed from damaged file: ".implode(', ', array_keys($have))."\n";

// ---------- recover the 17 from git HEAD ----------
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
$hl = explode("\n", $head);
$meth = array(); $order = array();
foreach ($hl as $i => $line) {
	if (preg_match('/^\s+(public|private)\s+function\s+(\w+)\s*\(/', $line, $m)) { $meth[$m[2]]['sig'] = $i; $order[] = $m[2]; }
}
for ($k = 0; $k < count($order); $k++) {
	$s = $meth[$order[$k]]['sig']; $j = $s - 1;
	while ($j >= 0) { $t2 = trim($hl[$j]); if ($t2==='' || $t2[0]==='*'||strpos($t2,'/**')===0||strpos($t2,'//')===0){$j--;continue;} break; }
	$meth[$order[$k]]['start'] = $j+1;
	$meth[$order[$k]]['end']   = ($k+1 < count($order)) ? ($meth[$order[$k+1]]['start']-1) : (count($hl)-1);
}
function slice_hl($hl,$meth,$n){ return implode("\n",array_slice($hl,$meth[$n]['start'],$meth[$n]['end']-$meth[$n]['start']+1)); }

$recover = array(
	'_render_booking_list','_booking_filters','_status_id',
	'_booking_form_failure','_checkin_form_failure',
	'_json','_int','_num','_datetime','_date',
	'_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error',
	'_posted_booking_range','_datetime_availability_range','_room_availability_range',
);
foreach ($recover as $name) {
	if (isset($have[$name])) { continue; }               // already present?
	$b = slice_hl($hl, $meth, $name);
	$b = preg_replace('/^(\s*)(private|public)(\s+function\s+)/m', '${1}protected${3}', $b);
	$have[$name] = trim($b, "\n");
	echo "recovered: {$name}\n";
}

// ---------- ordered emit ----------
$order = array(
	'_render_booking_list','_booking_filters','_status_id',
	'_booking_form_failure','_checkin_form_failure',
	'_json','_int','_num','_datetime','_date',
	'_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error',
	'_identity_belongs_to_scope','_valid_customer_write_token','_require_property_context',
	'_save_identities','_delete_identity_path','_upload_identity_file',
	'_country_options','_identity_types',
	'_posted_booking_range','_datetime_availability_range','_room_availability_range',
);
$bodyOut = '';
foreach ($order as $n) {
	if ( ! isset($have[$n])) { echo "MISSING IN EMIT: {$n}\n"; continue; }
	$bodyOut .= "\n    /* Moved verbatim from Customers.php */\n".trim($have[$n])."\n";
}
// _delete_identity_path override: today's soft-delete no-op version
if (isset($have['_delete_identity_path'])) {
	$bodyOut .= "\n    /* Moved verbatim from Customers.php */\n".trim($have['_delete_identity_path'])."\n";
}

$php = "<?php\ndefined('BASEPATH') OR exit('No direct script access allowed');\n\n"
	."/**\n * Shared operational plumbing for the split stay-management controllers\n"
	." * (Customers / Bookings / Checkins / Checkouts). Everything here was\n"
	." * moved VERBATIM out of the former God controller.\n */\n"
	."class Ops_Controller extends Property_Controller\n{\n"
	."    public function __construct()\n    {\n"
	."        parent::__construct();\n"
	."        \$this->load->model('Customer_model');\n"
	."    }\n"
	.$bodyOut
."}\n";
file_put_contents($root.'/application/core/Ops_Controller.php', $php);

$cnt = substr_count($php, 'function ');
echo "Ops rebuilt with {$cnt} functions\n";
