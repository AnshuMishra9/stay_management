<?php
/**
 * Canonical Ops_Controller builder v3 — single pristine source:
 * git HEAD application/controllers/Customers.php.
 * Only intentional override: _delete_identity_path is today's no-op.
 */
$root = dirname(__DIR__);
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
if ( ! $head) { fwrite(STDERR, "git show failed\n"); exit(1); }

$lines = explode("\n", $head);
$meth = array(); $order = array();
foreach ($lines as $i => $line) {
	if (preg_match('/^\s+(public|private|protected)\s+function\s+(\w+)\s*\(/', $line, $m)) {
		if ( ! isset($meth[$m[2]]['sig'])) { $order[] = $m[2]; }
		$meth[$m[2]]['sig'] = $i;
	}
}
// pass 1: compute every method's start (incl. preceding docblock)
foreach ($order as $n) {
	$s = $meth[$n]['sig']; $j = $s - 1;
	while ($j >= 0) {
		$t = trim($lines[$j]);
		if ($t === '' || $t[0] === '*' || strpos($t,'/**') === 0 || strpos($t,'//') === 0) { $j--; continue; }
		break;
	}
	$meth[$n]['start'] = $j + 1;
}
// pass 2: ends reference already-computed starts
for ($k = 0; $k < count($order); $k++) {
	$meth[$order[$k]]['end'] = ($k+1 < count($order)) ? ($meth[$order[$k+1]]['start'] - 1) : (count($lines)-1);
}
// pass 3: trim tail to the method's OWN closing brace (drops stray class braces)
foreach ($order as $n) {
	for ($e = $meth[$n]['end']; $e > $meth[$n]['start']; $e--) {
		if (trim($lines[$e-1]) === '}') { $meth[$n]['end'] = $e; break; }
	}
}

$required = array(
	'_render_booking_list','_booking_filters','_status_id',
	'_booking_form_failure','_checkin_form_failure',
	'_json','_int','_num','_datetime','_date',
	'_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error',
	'_identity_belongs_to_scope','_valid_customer_write_token','_require_property_context',
	'_save_identities','_upload_identity_file',
	'_country_options','_identity_types',
	'_posted_booking_range','_datetime_availability_range','_room_availability_range',
);
$missing = array_diff($required, $order);
if ($missing) { fwrite(STDERR, "MISSING IN HEAD: ".implode(', ',$missing)."\n"); exit(1); }

preg_match_all('/^\s*const\s+(\w+)\s*=\s*([^;]+);/m', $head, $cmap, PREG_SET_ORDER);

$bodyOut = '';
foreach ($required as $n) {
	$b = implode("\n", array_slice($lines, $meth[$n]['start'], $meth[$n]['end'] - $meth[$n]['start'] + 1));
	$b = preg_replace('/^(\s*)(private|public)(\s+function\s+)/m', '${1}protected${3}', $b);
	$bodyOut .= "\n".trim($b)."\n";
}

$constOut = '';
foreach ($cmap as $c) { $constOut .= "    const {$c[1]} = {$c[2]};\n"; }

// Today's soft-delete policy override (files are never removed from disk)
$noOp = <<<PHP

    /**
     * Identity document files are NEVER deleted from disk (soft-delete policy).
     * The DB row keeps its path; removed rows just get status = 0.
     */
    protected function _delete_identity_path(\$path)
    {
        return;
    }

PHP;

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
	.$noOp
."}\n";

file_put_contents($root.'/application/core/Ops_Controller.php', $php);
echo "Ops v3 rebuilt: ".count($required)." shared methods + ".count($cmap)." constants + no-op override\n";
