<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Otp_model
 *
 * Handles the `mobile_otp` table (ex-`otp_requests`) — the full history/audit
 * trail of every OTP generated. A NEW row is inserted for each request;
 * previous rows are never overwritten. The only in-place updates are on a
 * single target row: marking it verified or incrementing its attempt counter.
 *
 * All queries use CodeIgniter Query Builder (escaped/prepared) — no raw SQL.
 */
class Otp_model extends CI_Model
{
    /** @var string */
    protected $table = 'mobile_otp';

    /**
     * Insert a brand-new OTP request (never updates existing rows).
     *
     * @param  int    $user_id
     * @param  string $otp         6-digit code
     * @param  string $expires_at  MySQL DATETIME
     * @return int    Inserted row id.
     */
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
     * Find the latest still-valid OTP matching the submitted code.
     *
     * Mirrors:
     *   SELECT * FROM mobile_otp
     *   WHERE user_id = ? AND otp = ? AND status = 0 AND expires_at > NOW()
     *   ORDER BY id DESC LIMIT 1
     *
     * NOW() is passed as a PHP timestamp so expiry math stays consistent with
     * the value written at generation time (no DB/PHP timezone drift).
     *
     * @param  int    $user_id
     * @param  string $otp
     * @return object|null
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
     * Get the most recent OTP request for a user, regardless of state.
     * Used to produce an accurate error message (expired / used / invalid).
     *
     * @param  int $user_id
     * @return object|null
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

    /**
     * Mark a single OTP row as verified (consumed).
     *
     * @param  int $otp_id
     * @return bool
     */
    public function mark_verified($otp_id)
    {
        $updated = $this->db
            ->where('id', $otp_id)
            ->where('status', 0)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->update($this->table, array('status' => 1));
        return $updated && (int) $this->db->affected_rows() === 1;
    }

    /**
     * Increment the attempt counter on a single OTP row.
     *
     * @param  int $otp_id
     * @return bool
     */
    public function increment_attempts($otp_id)
    {
        return $this->db
            ->set('attempts', 'attempts + 1', FALSE)
            ->where('id', $otp_id)
            ->update($this->table);
    }
}
