<?php
$h = shell_exec('git show HEAD:application/controllers/Customers.php');
$p = strpos($h, '_checkin_form_failure');
$seg = substr($h, max(0, $p - 400), 500);
echo $seg;
