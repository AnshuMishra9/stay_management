<?php
/**
 * Tokenizer-based extraction — brace-perfect by construction.
 * Source: git HEAD Customers.php. Emits canonical Ops_Controller.
 */
$root = dirname(__DIR__);
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
$tokens = token_get_all($head);

$required = array_flip(array(
	'_render_booking_list','_booking_filters','_status_id',
	'_booking_form_failure','_checkin_form_failure',
	'_json','_int','_num','_datetime','_date',
	'_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error',
	'_identity_belongs_to_scope','_valid_customer_write_token','_require_property_context',
	'_save_identities','_upload_identity_file',
	'_country_options','_identity_types',
	'_posted_booking_range','_datetime_availability_range','_room_availability_range',
));

$consts = array();
$found = array();          // name => ['start'=>tokIdx,'end'=>tokIdx]
$count = count($tokens);
for ($i = 0; $i < $count; $i++) {
	$tk = $tokens[$i];
	if ( ! is_array($tk)) { continue; }

	if ($tk[0] === T_CONST) {
		// const NAME = value;
		$j = $i + 1;
		while ($j < $count && $tokens[$j][0] === T_WHITESPACE) { $j++; }
		if (isset($tokens[$j][1])) { $name = $tokens[$j][1]; $end = $j; while ($end < $count && $tokens[$end] !== ';') { $end++; } $consts[$name] = array('s'=>$i,'e'=>$end); }
		continue;
	}

	if ($tk[0] !== T_FUNCTION) { continue; }
	$j = $i + 1;
	while ($j < $count && $tokens[$j][0] === T_WHITESPACE) { $j++; }
	if ( ! isset($tokens[$j][1])) { continue; }
	$name = $tokens[$j][1];
	if ( ! isset($required[$name]) || isset($found[$name])) { continue; }
	// find opening brace
	$k = $j; $depth0 = 0; 
	while ($k < $count) {
		$t2 = $tokens[$k];
		if ($t2 === '{') { break; }
		$k++;
	}
	if ($k >= $count) { continue; }
	$d = 0;
	$e = $k;
	for (; $k < $count; $k++) {
		$t2 = $tokens[$k];
		if ($t2 === '{') { $d++; }
		elseif ($t2 === '}') { $d--; if ($d === 0) { $e = $k; break; } }
	}
	$found[$name] = array('s'=>$i, 'open'=>$k, 'end'=>$e);
}

$missing = array_diff(array_keys($required), array_keys($found));
if ($missing) { fwrite(STDERR, 'MISSING: '.implode(', ', $missing)."\n"); exit(1); }

function tok_slice(array $tokens, $s, $e)
{
	$out = '';
	for ($i = $s; $i <= $e; $i++) {
		$t = $tokens[$i];
		$out .= is_array($t) ? $t[1] : $t;
	}
	return $out;
}

$bodyOut = '';
foreach ($found as $name => $pos) {
	$b = tok_slice($tokens, $pos['s'], $pos['end']);
	$b = preg_replace('/^(\s*)(private|public)(\s+function\s+)/', '${1}protected${3}', $b, 1);
	$bodyOut .= "\n".ltrim($b)."\n";
}

$constOut = '';
foreach ($consts as $cinfo) {
	$constOut .= rtrim(tok_slice($tokens, $cinfo['s'], $cinfo['e']))."\n";
}

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
echo 'Ops v4 rebuilt: '.count($found).' methods + '.count($consts)." constants (tokenizer-exact)\n";
