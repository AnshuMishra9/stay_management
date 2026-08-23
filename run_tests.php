<?php
/**
 * Unified test runner — executes every PHP regression suite in isolation
 * and reports a single aggregated result.
 *
 * Usage:
 *   php run_tests.php
 *   composer test
 */

$php = PHP_BINARY;
$root = __DIR__;
$suites = array(
	'Identity upload guard'  => $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'identity_upload_guard_test.php',
	'Multitenancy isolation' => $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'multitenancy_isolation_test.php',
	'Soft delete policy'     => $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'soft_delete_test.php',
	'OTP exposure guard'     => $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'otp_exposure_test.php',
);

$failed = array();
foreach ($suites as $label => $file) {
	if ( ! is_file($file)) {
		$failed[] = $label.' (missing file)';
		echo "[SKIP] {$label} — file missing\n";
		continue;
	}
	echo "\n========== {$label} ==========\n";
	passthru(escapeshellarg($php).' '.escapeshellarg($file), $exit);
	if ($exit !== 0) { $failed[] = $label; }
}

echo "\n================================ SUMMARY =================================\n";
$total = count($suites) - count($failed);
echo "Passed: {$total} / ".count($suites)." suites\n";
if ($failed) {
	echo "FAILED suites:\n - ".implode("\n - ", $failed)."\n";
	exit(1);
}
echo "ALL TEST SUITES PASSED\n";
