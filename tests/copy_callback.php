<?php
/**
 * booking_save (Bookings controller) uses validation callback
 * can_check_in_on_scheduled_date() that originally lived beside it.
 * After the split it resides in Checkins; copy it verbatim into
 * Bookings so form_validation resolves on THIS controller too.
 */
$root = dirname(__DIR__);
$src = file_get_contents($root.'/application/controllers/Checkins.php');
$dst = $root.'/application/controllers/Bookings.php';

if ( ! preg_match('/((?:\s*\/\*\*[\s\S]*?\*\/)?\s*(?:public|protected)\s+function\s+can_check_in_on_scheduled_date\s*\([\s\S]*?)\n    \}\n/', $src, $m)) {
	fwrite(STDERR, "callback not found in Checkins\n"); exit(1);
}
$body = preg_replace('/^(\s*)(protected|private)(\s+function\s+)/m', '${1}public${3}', $m[1]);

$dstText = file_get_contents($dst);
if (strpos($dstText, 'function can_check_in_on_scheduled_date') !== false) {
	echo "already present\n"; exit(0);
}

// insert before the class-closing brace at EOF
$pos = strrpos($dstText, '}');
$t = substr_replace($dstText, "\n".$body."\n}\n", $pos, 1);
file_put_contents($dst, $t);
echo "copied into Bookings\n";
