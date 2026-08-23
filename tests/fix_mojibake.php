<?php
/**
 * One-off mojibake repair: replaces common double-encoded UTF-8 sequences
 * with proper HTML entities across views / controllers / JS assets.
 */
$root = dirname(__DIR__);
$targets = array_merge(
	glob($root.'/application/views/**/*.php', GLOB_BRACE | GLOB_NOSORT),
	glob($root.'/application/views/*.php'),
	glob($root.'/application/controllers/*.php'),
	glob($root.'/assets/js/*.js')
);

// mojibake needle => proper replacement
$map = array(
	"\xC3\xA2\xE2\x82\xAC\xE2\x80\x9C" => '&ldquo;',  // “
	"\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D" => '&rdquo;',  // ”
	"\xC3\xA2\xE2\x82\xAC\xC5\u{0093}" => '&ldquo;',
	"\xE2\x80\x9C"                     => '&ldquo;',  // clean “ ok to keep, skip
	"\xC3\xA2\xE2\x82\xAC\xE2\x84\xA2" => '&rsquo;',  // ’
	"\xC3\xA2\xE2\x82\xAC\xC5\u{0094}" => '&mdash;',  // — variant
	"\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D" => '&mdash;',
	"\xC3\xA2\xE2\x82\xAC"             => '&euro;',   // €
	"\xC3\xA2\xE2\x80\x94"             => '&mdash;',  // â”
	"\xC3\xA2\xE2\x80\x93"             => '&ndash;',  // â€“
	"\xC3\xA2\xE2\x82\xAC\xC2\xA2"     => '&cent;',
	"\xC2\xB7"                         => '&middot;', // ·
	"\xC3\xAF\xC2\xBF\xC2\xBD"         => '',         // ï¿½ replacement junk
);

$totalFiles = 0; $totalFixes = 0;
foreach ($targets as $file) {
	if ( ! is_file($file)) { continue; }
	$raw  = file_get_contents($file);
	$fix  = $raw;
	$count = 0;
	foreach ($map as $needle => $repl) {
		if ($needle === "\xE2\x80\x9C") { continue; } // keep legit curly quotes
		$c = substr_count($fix, $needle);
		if ($c > 0) { $fix = str_replace($needle, $repl, $fix); $count += $c; }
	}
	// generic leftover: any remaining 'â€' prefixed junk -> mdash
	$c = preg_match_all('/\xC3\xA2\xE2\x82\xAC[^\x20-\x7E]{0,3}/', $fix, $mm);
	if ($c) { $fix = preg_replace('/\xC3\xA2\xE2\x82\xAC[^\x20-\x7E]{0,3}/', '&mdash;', $fix); $count += $c; }

	if ($count > 0 && $fix !== $raw) {
		file_put_contents($file, $fix);
		$totalFiles++; $totalFixes += $count;
		echo str_replace($root.'/', '', $file)." : {$count} replaced\n";
	}
}
echo "\nDone. Files: {$totalFiles}, replacements: {$totalFixes}\n";
