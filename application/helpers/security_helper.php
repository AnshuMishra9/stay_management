<?php
/**
 * Security helpers.
 *
 * otp_exposure_allowed()
 * The raw OTP may appear in the send_otp JSON response ONLY while running
 * locally: ENVIRONMENT must be 'development' AND the .env flag
 * DEV_EXPOSE_OTP must be exactly 'true'. In every other combination
 * (production, testing, or the flag off) the OTP is never leaked.
 */
if ( ! function_exists('otp_exposure_allowed'))
{
	function otp_exposure_allowed()
	{
		if (ENVIRONMENT !== 'development') { return FALSE; }
		$flag = getenv('DEV_EXPOSE_OTP');
		return strtolower((string) $flag) === 'true';
	}
}

if ( ! function_exists('app_env'))
{
	/** Read an environment variable with a safe fallback. */
	function app_env($name, $fallback = NULL)
	{
		$value = getenv($name);
		return $value === FALSE ? $fallback : $value;
	}
}
