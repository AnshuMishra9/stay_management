<?php
// Residual mojibake audit: report any remaining suspicious byte clusters.
$root = dirname(__DIR__);
$files = array_merge(
	glob($root.'/application/views/**/*.php'),
	glob($root.'/application/controllers/*.php'),
	glob($root.'/assets/js/*.js')
);
$suspects = array(
	"\xC3\xA2",           // 'â' prefix (double-encoding start)
	"\xC3\xAF\xC2\xBF",   // 'ï¿½'
	"\xC2\xA0\xC2\xA0",   // double nbsp junk
);
foreach ($files as $f) {
	$txt = file_get_contents($f);
	foreach ($suspects as $s) {
		if (strpos($txt, $s) !== FALSE) {
			$pos = strpos($txt, $s);
			echo str_replace($root.'/', '', $f).' @'.$pos.' : '.substr($txt, max(0,$pos-30), 70)."\n";
			continue 2;
		}
	}
}
echo "audit complete\n";
