<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User_model
 *
 * Handles the permanent `users` table only. This table never stores OTPs —
 * OTP history lives in `otp_requests` (see Otp_model). The only field that
 * changes after login is `last_login`.
 *
 * All queries use CodeIgniter Query Builder (escaped/prepared) — no raw SQL.
 */
class User_model extends CI_Model
{
    /** @var string */
    protected $table = 'users';

    /**
     * Fetch an active, authorized user by mobile number.
     *
     * Mirrors: SELECT * FROM users WHERE mobile_no = ? AND is_active = 1
     *
     * @param  string $mobile_no
     * @return object|null  The user row, or NULL if not found / inactive.
     */
    public function get_active_by_mobile($mobile_no)
    {
        return $this->db
            ->where('mobile_no', $mobile_no)
            ->where('is_active', 1)
            ->limit(1)
            ->get($this->table)
            ->row();
    }

    /**
     * Stamp a successful login. This is the ONLY mutation on the users table.
     *
     * @param  int $user_id
     * @return bool
     */
    public function update_last_login($user_id)
    {
        return $this->db
            ->where('id', $user_id)
            ->update($this->table, array(
                'last_login' => date('Y-m-d H:i:s'),
            ));
    }
}
