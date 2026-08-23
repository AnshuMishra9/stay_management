<?php
// Visibility normalizer: everything moved into the shared base must be
// at least protected, or subclasses get fatal "undefined method" errors.
$files = array(
	dirname(__DIR__).'/application/core/Ops_Controller.php',
	dirname(__DIR__).'/application/controllers/Customers.php',
	dirname(__DIR__).'/application/controllers/Bookings.php',
	dirname(__DIR__).'/application/controllers/Checkins.php',
	dirname(__DIR__).'/application/controllers/Checkouts.php',
);
foreach ($files as $f) {
	$t = file_get_contents($f);
	$n = 0;
	$t = preg_replace('/(\r?\n\s+)private(\s+function\s+)/', '$1protected$2', $t, -1, $n);
	if ($n > 0) { file_put_contents($f, $t); }
	echo basename($f)." : {$n} private->protected\n";
}
