<?php
$f = dirname(__DIR__).'/application/views/access/management_head.php';
$t = file_get_contents($f);
// 'pages <junk> built'  ->  'pages &mdash; built'
if (preg_match("/(master pages )[^\\r\\n]{1,40}( built)/", $t, $m)) {
	$t = str_replace($m[0], '${1}&mdash;${2}', $t);
	file_put_contents($f, $t);
	echo "fixed\n";
} else {
	echo "pattern not found\n";
}
