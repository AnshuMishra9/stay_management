<?php
/**
 * Recover the shared helper methods that fix_ops_dupes accidentally ate,
 * using the committed (HEAD) Customers.php as the byte-exact source.
 * Only methods untouched by today's changes are recovered this way;
 * _delete_identity_path (edited today) is re-created manually below.
 */

$root = dirname(__DIR__);
$head = shell_exec('git show HEAD:application/controllers/Customers.php');
if ( ! $head) { fwrite(STDERR, "git show failed\n"); exit(1); }
$headLines = explode("\n", $head);

// scan methods in HEAD source
$methods = array(); $order = array();
foreach ($headLines as $i => $line) {
	if (preg_match('/^\s+(public|private)\s+function\s+(\w+)\s*\(/', $line, $m)) {
		$methods[$m[2]]['sig'] = $i;
		$order[] = $m[2];
	}
}
for ($k = 0; $k < count($order); $k++) {
	$s = $methods[$order[$k]]['sig'];
	$j = $s - 1;
	while ($j >= 0) {
		$t = trim($headLines[$j]);
		if ($t === '' || strpos($t,'*') === 0 || strpos($t,'/**') === 0 || strpos($t,'//') === 0) { $j--; continue; }
		break;
	}
	$methods[$order[$k]]['start'] = $j + 1;
	$endNext = ($k+1 < count($order)) ? $methods[$order[$k+1]]['start'] : count($headLines);
	$methods[$order[$k]]['end'] = $endNext - 1;
}

function slice_head(array $hl, array $m, $name) {
	return implode("\n", array_slice($hl, $m[$name]['start'], $m[$name]['end'] - $m[$name]['start'] + 1));
}

$recover = array(
	'_render_booking_list','_booking_filters','_status_id',
	'_booking_form_failure','_checkin_form_failure',
	'_json','_int','_num','_datetime','_date',
	'_secure_upload_file','_handle_upload','_identity_upload_entry','_identity_upload_error',
	'_posted_booking_range','_datetime_availability_range','_room_availability_range',
);

$opsFile = dirname(__DIR__).'/application/core/Ops_Controller.php';
$t = file_get_contents($opsFile);
$inserted = 0;
$insertAt = strrpos($t, '}');            // class closing brace

$block = '';
foreach ($recover as $name) {
	if ( ! isset($methods[$name])) { echo "SKIP (not in HEAD): {$name}\n"; continue; }
	$body = slice_head($headLines, $methods, $name);
	$body = preg_replace('/^(\s*)(private|public)(\s+function\s+)/m', '${1}protected${3}', $body);
	$block .= "\n".$body."\n";
	$inserted++;
	echo "recovered: {$name}\n";
}

// _delete_identity_path — TODAY's no-op version (files are never deleted)
$block .= "\n".<<<'PHP'
    /**
     * Identity document files are NEVER deleted from disk (soft-delete policy).
     * The DB row keeps its path; removed rows just get status = 0.
     */
    protected function _delete_identity_path($path)
    {
        return;
    }

PHP;
$block .= "\n";

$t = substr_replace($t, $block, $insertAt, 0);
file_put_contents($opsFile, $t);
echo "inserted {$inserted} methods into Ops_Controller\n";
