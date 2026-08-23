<?php
$h = shell_exec('git show HEAD:application/controllers/Customers.php');
$p = strpos($h, '_booking_form_failure');
$seg = substr($h, $p, 3200);
$lines = explode("\n", $seg);
foreach ($lines as $i => $l) {
	if (strpos($l, 'function') !== false || trim($l) === '}') {
		echo ($i+1) . ': ' . $l . "\n";
	}
}
