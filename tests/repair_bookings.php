<?php
/**
 * Repair Bookings.php: drop the accidentally appended duplicate
 * Checkins class block, then add the missing validation callback
 * can_check_in_on_scheduled_date() properly inside the class.
 */
$f = dirname(__DIR__).'/application/controllers/Bookings.php';
$t = file_get_contents($f);

// 1) truncate everything from the stray second class
$pos = strpos($t, 'class Checkins extends Ops_Controller');
if ($pos === FALSE) { fwrite(STDERR, "stray class not found\n"); exit(1); }
// walk back to include nothing of it; keep up to previous non-empty content
$t = rtrim(substr($t, 0, $pos));
// the truncated tail should now end with the class closing brace of Bookings
if (substr($t, -1) !== '}') {
	fwrite(STDERR, "unexpected tail: ".substr($t,-80)."\n"); exit(1);
}

// 2) extract callback from Checkins.php
$src = dirname(__DIR__).'/application/controllers/Checkins.php';
$s = file_get_contents($src);
if ( ! preg_match('/((?:\s*\/\*\*[\s\S]*?\*\/)?\s*(?:public|protected)\s+function\s+can_check_in_on_scheduled_date\s*\([\s\S]*?)\n    \}/', $s, $m)) {
	fwrite(STDERR, "callback not found\n"); exit(1);
}
$body = preg_replace('/^(\s*)(protected|private)(\s+function\s+)/m', '${1}public${3}', $m[1]);

// 3) insert before final class-closing brace
$pos2 = strrpos($t, '}');
$t = substr_replace($t, "\n".$body."\n", $pos2, 1);
file_put_contents($f, $t);
echo "Bookings.php repaired + callback added\n";
