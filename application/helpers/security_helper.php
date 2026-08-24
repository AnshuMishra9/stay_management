<?php
if ( ! function_exists('otp_exposure_allowed'))
{
	// Require both gates so a deployment flag alone can never expose an OTP.
	function otp_exposure_allowed()
	{
		if (ENVIRONMENT !== 'development') { return FALSE; }
		$flag = getenv('DEV_EXPOSE_OTP');
		return strtolower((string) $flag) === 'true';
	}
}

if ( ! function_exists('app_env'))
{
	function app_env($name, $fallback = NULL)
	{
		$value = getenv($name);
		return $value === FALSE ? $fallback : $value;
	}
}
