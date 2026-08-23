<?php
/**
 * Full deterministic rebuild pipeline for the controller split.
 * Run: php tests/rebuild_split_pipeline.php
 */
$root = dirname(__DIR__);
$steps = array(
	'reset_customers.php',
	'split_customers_refactor.php',
	'reapply_patches.php',
	'finalize_ops.php',
	'fix_visibility.php',
	'copy_callback_v2.php',
);
foreach ($steps as $s) {
	$file = $root.'/tests/'.$s;
	echo "\n>>> {$s}\n";
	passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file), $exit);
	if ($exit !== 0) { fwrite(STDERR, "PIPELINE FAILED at {$s}\n"); exit(1); }
}
echo "\nPIPELINE COMPLETE\n";
