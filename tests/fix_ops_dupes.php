<?php
// Remove the three duplicated list-page methods from Ops_Controller —
// they live (renamed to index()) inside Bookings / Checkins / Checkouts.
$f = dirname(__DIR__).'/application/core/Ops_Controller.php';
$t = file_get_contents($f);
$removed = 0;
foreach (array('bookings', 'checkins', 'checkedouts') as $name) {
	if (preg_match('/\n(    \/\*\*[\s\S]*?\*\/\n)?    public function '.preg_quote($name).'\(.*?\n    \}\n/s', $t, $m)) {
		$t = str_replace($m[0], "\n", $t);
		$removed++;
	}
}
file_put_contents($f, $t);
echo "removed {$removed} duplicated list methods\n";
