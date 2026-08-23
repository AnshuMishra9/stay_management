<?php
/**
 * copy_callback v2 — bulletproof:
 * 1. Extract can_check_in_on_scheduled_date() from Checkins.php using a
 *    character-level BRACE DEPTH scan (immune to nested braces/comments).
 * 2. Insert into Bookings.php before its final class-closing brace,
 *    keeping that brace intact.
 */
$root = dirname(__DIR__);
$srcText = file_get_contents($root.'/application/controllers/Checkins.php');
$dstFile = $root.'/application/controllers/Bookings.php';
$dstText = file_get_contents($dstFile);

// ---- locate callback start ----
$p = strpos($srcText, 'function can_check_in_on_scheduled_date');
if ($p === false) { fwrite(STDERR, "callback not found in Checkins\n"); exit(1); }
// include preceding docblock if directly attached
$docStart = $p;
if (preg_match('/\/\*\*[^\r\n]*\*\/\s*$/', substr($srcText, 0, $p), $dm, PREG_OFFSET_CAPTURE)) {
	$docStart = $dm[0][1];
}

// ---- brace-depth scan to find method end ----
$depth = 0; $started = false; $end = -1;
for ($i = $p; $i < strlen($srcText); $i++) {
	$ch = $srcText[$i];
	if ($ch === '{') { $depth++; $started = true; }
	elseif ($ch === '}') { $depth--; if ($started && $depth === 0) { $end = $i; break; } }
}
if ($end < 0) { fwrite(STDERR, "method end not found\n"); exit(1); }

$body = substr($srcText, $docStart, $end - $docStart + 1);
$body = preg_replace('/^(\s*)(private|protected|public)(\s+function\s+)/m', '${1}public${3}', $body, 1);

// ---- insert into Bookings before final closing brace ----
$closePos = strrpos($dstText, '}');
if ($closePos === false) { fwrite(STDERR, "no closing brace in Bookings\n"); exit(1); }
if (strpos($dstText, 'function can_check_in_on_scheduled_date') !== false) {
	echo "already present\n"; exit(0);
}
$t = substr_replace($dstText, "\n".$body."\n}", $closePos, 1);
file_put_contents($dstFile, $t);
echo "callback inserted into Bookings\n";
