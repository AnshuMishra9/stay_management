<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** OTP request history with per-request verification and attempt state. */
class Otp_model extends CI_Model
{
    /** @var string */
    protected $table = 'mobile_otp';

    public function create($user_id, $otp, $expires_at)
    {
        $this->db->insert($this->table, array(
            'user_id'     => $user_id,
            'otp'         => $otp,
            'expires_at'  => $expires_at,
            'status'      => 0,
            'attempts'    => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ));

        return $this->db->insert_id();
    }

    /**
     * Use a PHP timestamp so expiry math stays consistent with
     * the value written at generation time (no DB/PHP timezone drift).
     */
    public function get_latest_valid($user_id, $otp)
    {
        return $this->db
            ->where('user_id', $user_id)
            ->where('otp', $otp)
            ->where('status', 0)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row();
    }

    /**
     * Include consumed and expired requests so callers can report the precise
     * verification failure.
     */
    public function get_latest($user_id)
    {
        return $this->db
            ->where('user_id', $user_id)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row();
    }

    /** Consume an OTP only once, even under simultaneous verification. */
    public function mark_verified($otp_id)
    {
        $updated = $this->db
            ->where('id', $otp_id)
            ->where('status', 0)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->update($this->table, array('status' => 1));
        return $updated && (int) $this->db->affected_rows() === 1;
    }

    public function increment_attempts($otp_id)
    {
        return $this->db
            ->set('attempts', 'attempts + 1', FALSE)
            ->where('id', $otp_id)
            ->update($this->table);
    }
}


