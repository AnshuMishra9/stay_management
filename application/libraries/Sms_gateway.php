<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * SMS gateway abstraction.
 *
 * SMS_DRIVER selects the implementation. The default log driver never sends
 * a message, keeping development and unconfigured deployments offline.
 */
interface Sms_gateway_driver
{
	public function send_otp($mobile, $otp);
}

/** Development driver that records delivery attempts without sending them. */
class Sms_gateway_log implements Sms_gateway_driver
{
	public function send_otp($mobile, $otp)
	{
		$CI =& get_instance();
		log_message('info', 'SMS(otp) -> '.$mobile.' (logged, not sent)');
		return TRUE;
	}
}

/** Fast2SMS driver configured through SMS_API_KEY and SMS_SENDER_ID. */
class Sms_gateway_fast2sms implements Sms_gateway_driver
{
	public function send_otp($mobile, $otp)
	{
		$key = getenv('SMS_API_KEY');
		if ( ! $key) { return FALSE; }
		$payload = http_build_query(array(
			'authorization' => $key,
			'sender_id'     => getenv('SMS_SENDER_ID') ?: 'FSTSMS',
			'message'       => 'Your verification code is '.$otp,
			'numbers'       => $mobile,
		));
		$ch = curl_init('https://www.fast2sms.com/dev/bulkV2?'.$payload);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_TIMEOUT        => 10,
		));
		$result = curl_exec($ch);
		curl_close($ch);
		return $result !== FALSE;
	}
}

/** Twilio driver; SMS_API_KEY contains the SID and token as "sid:token". */
class Sms_gateway_twilio implements Sms_gateway_driver
{
	public function send_otp($mobile, $otp)
	{
		$cred = getenv('SMS_API_KEY');
		$from = getenv('SMS_SENDER_ID');
		if ( ! $cred || ! $from) { return FALSE; }
		list($sid, $token) = explode(':', $cred, 2);
		$ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_POST           => TRUE,
			CURLOPT_USERPWD        => $sid.':'.$token,
			CURLOPT_POSTFIELDS     => http_build_query(array(
				'From' => $from,
				'To'   => $mobile,
				'Body' => 'Your verification code is '.$otp,
			)),
			CURLOPT_TIMEOUT        => 10,
		));
		$result = curl_exec($ch);
		curl_close($ch);
		return $result !== FALSE;
	}
}

class Sms_gateway
{
	/** @var Sms_gateway_driver */
	protected $driver;

	public function __construct()
	{
		$this->driver = $this->make(getenv('SMS_DRIVER') ?: 'log');
	}

	public function driver()
	{
		return $this->driver;
	}

	public function send_otp($mobile, $otp)
	{
		return $this->driver->send_otp($mobile, $otp);
	}

	protected function make($name)
	{
		$map = array(
			'log'       => 'Sms_gateway_log',
			'fast2sms'  => 'Sms_gateway_fast2sms',
			'twilio'    => 'Sms_gateway_twilio',
		);
		if ($name === 'msg91' && class_exists('Sms_gateway_msg91')) {
			return new Sms_gateway_msg91();
		}
		$class = isset($map[$name]) ? $map[$name] : 'Sms_gateway_log';
		return new $class();
	}
}


