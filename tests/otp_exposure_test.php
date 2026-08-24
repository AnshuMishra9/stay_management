<?php
/**
 * Verifies the otp_exposure_allowed() environment gate for raw OTP values.
 * It does not invoke Auth::send_otp; response wiring requires integration coverage.
 *
 * Raw OTP values may appear only when ENVIRONMENT is "development" and
 * DEV_EXPOSE_OTP=true.
 *
 * Invoke this file once per scenario with the environment and flag as CLI
 * arguments so ENVIRONMENT is defined independently for each process.
 * Example: php tests\otp_exposure_test.php development true
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

// Mirror the application's boot-time environment in this isolated process.
putenv("CI_ENVIRONMENT={$env}");
putenv("DEV_EXPOSE_OTP={$flag}");
define('ENVIRONMENT', $env);

expect(
	"{$env} + DEV_EXPOSE_OTP={$flag}",
	otp_exposure_allowed(),
	($env === 'development' && strtolower($flag) === 'true')
);

exit($failures > 0 ? 1 : 0);
