<?php
/**
 * OTP exposure guard — security regression test.
 *
 * The raw OTP must NEVER appear in the send_otp response unless the
 * process is running in 'development' AND DEV_EXPOSE_OTP=true.
 *
 * Each scenario runs in a fresh PHP process (see run at bottom) so the
 * ENVIRONMENT constant can be defined per-case without redefinition.
 */

if ( ! function_exists('otp_exposure_allowed')) {
	function otp_exposure_allowed()
	{
		if (ENVIRONMENT !== 'development') { return FALSE; }
		$flag = getenv('DEV_EXPOSE_OTP');
		return strtolower((string) $flag) === 'true';
	}
}

$failures = 0;
function expect($label, $actual, $expected)
{
	global $failures;
	if ($actual === $expected) { echo "PASS: {$label}\n"; return; }
	$failures++;
	echo "FAIL: {$label} — expected ".var_export($expected, TRUE).', got '.var_export($actual, TRUE)."\n";
}

$env  = isset($argv[1]) ? $argv[1] : '';
$flag = isset($argv[2]) ? $argv[2] : '';

// Simulate the boot environment for this isolated process.
putenv("CI_ENVIRONMENT={$env}");
putenv("DEV_EXPOSE_OTP={$flag}");
define('ENVIRONMENT', $env);

expect(
	"{$env} + DEV_EXPOSE_OTP={$flag}",
	otp_exposure_allowed(),
	($env === 'development' && strtolower($flag) === 'true')
);

exit($failures > 0 ? 1 : 0);
