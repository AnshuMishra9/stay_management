<?php
$h = shell_exec('git show HEAD:application/controllers/Customers.php');
$p = strpos($h, 'private function _checkin_form_failure');
echo "def position: " . var_export($p, true) . "\n";
if ($p !== false) { echo substr($h, $p - 120, 400) . "\n"; }
