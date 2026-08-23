<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Auth
 *
 * OTP-based authentication for the Stay Management System.
 *
 *   index()       -> renders the login page
 *   send_otp()    -> [AJAX] validates mobile, inserts a NEW otp_requests row
 *   verify_otp()  -> [AJAX] validates the latest OTP and creates the session
 *   logout()      -> destroys the session
 *
 * Data model:
 *   - users        : permanent authorized users (only last_login mutates)
 *   - otp_requests : one new row per OTP request (full history / audit trail)
 *
 * NOTE: For this demo there is no SMS gateway. The generated OTP is returned
 * in the send_otp() JSON response so it can be shown on screen. Swapping in an
 * SMS gateway later only means removing the `otp` field from that response —
 * no other backend logic changes.
 */
class Auth extends CI_Controller
{
    /** OTP validity window, in seconds (2 minutes). */
    const OTP_TTL_SECONDS = 120;

    /** Max failed verification attempts on a single OTP before a new one is required. */
    const MAX_OTP_ATTEMPTS = 5;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('User_model');
        $this->load->model('Otp_model');
        $this->load->model('Property_model');
    }

    // ---------------------------------------------------------------------
    //  Pages
    // ---------------------------------------------------------------------

    /**
     * Login page. If already authenticated, skip straight to the landing page.
     */
    public function index()
    {
        if ($this->session->userdata('logged_in')) {
            $user = $this->User_model->get_active_context_by_id(
                (int) $this->session->userdata('user_id')
            );
            if ($user) {
                redirect($this->_landing_path($user, TRUE));
                return;
            }
            $this->session->sess_destroy();
        }

        $this->load->view('auth/login');
    }

    /**
     * Destroy the session and return to the login page.
     */
    public function logout()
    {
        $this->session->sess_destroy();
        redirect('login');
    }

    // ---------------------------------------------------------------------
    //  AJAX endpoints (consumed by AngularJS)
    // ---------------------------------------------------------------------

    /**
     * Validate the mobile number, confirm it is an authorized & active user,
     * then generate a 6-digit OTP and insert a NEW otp_requests record.
     */
    public function send_otp()
    {
        $input     = $this->_read_input();
        $mobile_no = isset($input['mobile_no']) ? trim($input['mobile_no']) : '';

        // --- Server-side validation ------------------------------------
        if ( ! $this->_valid_mobile($mobile_no)) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Please enter a valid mobile number.',
            ));
        }

        // Only mobile numbers that exist AND are active may log in.
        $user = $this->User_model->get_active_by_mobile($mobile_no);

        if ( ! $user) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Mobile number is not authorized.',
            ));
        }

        // --- Generate + persist a NEW OTP (never overwrite old rows) ----
        $otp        = (string) random_int(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS);

        $this->Otp_model->create($user->id, $otp, $expires_at);

        return $this->_json(array(
            'status'     => TRUE,
            'message'    => 'OTP generated successfully.',
            'expires_in' => self::OTP_TTL_SECONDS,
            // DEV ONLY: replace this with an SMS dispatch in production.
            'otp'        => $otp,
        ));
    }

    /**
     * Validate the submitted OTP against the user's latest valid record.
     * On success: mark that OTP row verified, stamp last_login, open session.
     */
    public function verify_otp()
    {
        $input     = $this->_read_input();
        $mobile_no = isset($input['mobile_no']) ? trim($input['mobile_no']) : '';
        $otp       = isset($input['otp']) ? trim($input['otp']) : '';

        if ( ! $this->_valid_mobile($mobile_no) || ! preg_match('/^[0-9]{6}$/', $otp)) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Please enter the 6-digit OTP.',
            ));
        }

        $user = $this->User_model->get_active_by_mobile($mobile_no);

        if ( ! $user) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Mobile number is not authorized.',
            ));
        }

        // Happy path: latest unverified, unexpired OTP that matches the code.
        $valid = $this->Otp_model->get_latest_valid($user->id, $otp);

        if ($valid) {
            // Single-use: the conditional update is the concurrency boundary;
            // only one simultaneous verifier may consume this OTP row.
            if ( ! $this->Otp_model->mark_verified($valid->id)) {
                return $this->_verification_error($user->id, $otp);
            }
            $this->User_model->update_last_login($user->id);

            // Authentication is a privilege boundary: replace the pre-login
            // session id and discard any property state from a previous user.
            $this->session->sess_regenerate(TRUE);
            $this->session->unset_userdata(array(
                'active_property_id', 'property_context_token',
                'session_write_token', 'customer_write_token',
            ));
            $this->session->set_userdata(array(
                'logged_in'          => TRUE,
                'user_id'            => (int) $user->id,
                'mobile_no'          => $user->mobile_no,
                'role'               => $user->role,
                'session_write_token' => bin2hex(random_bytes(32)),
            ));

            $landing = $this->_landing_path($user, FALSE);

            return $this->_json(array(
                'status'   => TRUE,
                'message'  => 'Login successful. Redirecting...',
                'redirect' => site_url($landing),
            ));
        }

        // No valid match — diagnose against the most recent OTP request.
        return $this->_verification_error($user->id, $otp);
    }

    // ---------------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------------

    /**
     * Produce an accurate failure response (Invalid / Expired / Already used /
     * Too many attempts) and increment the attempt counter for a wrong code.
     *
     * @param  int    $user_id
     * @param  string $otp
     * @return void
     */
    private function _verification_error($user_id, $otp)
    {
        $latest = $this->Otp_model->get_latest($user_id);

        // User never requested an OTP.
        if ( ! $latest) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Please request an OTP first.',
                'reset'   => TRUE,
            ));
        }

        // The submitted code matches the latest row but it was already used.
        if ((int) $latest->status === 1 && hash_equals((string) $latest->otp, $otp)) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'This OTP has already been used. Please request a new one.',
                'reset'   => TRUE,
            ));
        }

        // The most recent OTP has expired.
        if (strtotime($latest->expires_at) < time()) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'OTP has expired. Please request a new one.',
                'reset'   => TRUE,
            ));
        }

        // Too many wrong tries on the current OTP -> force a fresh request.
        if ((int) $latest->attempts >= self::MAX_OTP_ATTEMPTS) {
            return $this->_json(array(
                'status'  => FALSE,
                'message' => 'Too many invalid attempts. Please request a new OTP.',
                'reset'   => TRUE,
            ));
        }

        // Otherwise it's simply the wrong code — count the failed attempt.
        $this->Otp_model->increment_attempts($latest->id);

        return $this->_json(array(
            'status'  => FALSE,
            'message' => 'Invalid OTP. Please try again.',
        ));
    }

    /**
     * Read the request body. AngularJS $http posts JSON by default, so we
     * parse the raw stream and fall back to standard form fields.
     *
     * @return array
     */
    private function _read_input()
    {
        $raw = $this->input->raw_input_stream;

        if ( ! empty($raw)) {
            $decoded = json_decode($raw, TRUE);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->input->post() ?: array();
    }

    /**
     * A mobile number is 10 to 15 digits.
     *
     * @param  string $mobile_no
     * @return bool
     */
    private function _valid_mobile($mobile_no)
    {
        return (bool) preg_match('/^[0-9]{10,15}$/', $mobile_no);
    }

    /**
     * Choose a safe post-login destination and establish context only when
     * exactly one active property is authorized.
     */
    private function _landing_path($user, $preserve_valid_context)
    {
        $properties = $this->Property_model->list_authorized_for_user($user, TRUE);
        $active_id = (int) $this->session->userdata('active_property_id');

        if ($preserve_valid_context && $active_id > 0) {
            foreach ($properties as $property) {
                if ((int) $property->id === $active_id) {
                    if ( ! $this->session->userdata('property_context_token')) {
                        $this->session->set_userdata(
                            'property_context_token',
                            bin2hex(random_bytes(32))
                        );
                    }
                    return '';
                }
            }
            $this->session->unset_userdata('active_property_id');
            $this->session->set_userdata(
                'property_context_token',
                bin2hex(random_bytes(32))
            );
        }

        // The remembered id is only a convenience hint. It is never trusted:
        // every fresh login matches it against the role's current DB-derived
        // property list before activating it.
        $remembered_id = (int) $this->input->cookie('stay_last_property', TRUE);
        if ($remembered_id > 0) {
            foreach ($properties as $property) {
                if ((int) $property->id === $remembered_id) {
                    $this->session->set_userdata(array(
                        'active_property_id'     => $remembered_id,
                        'property_context_token' => bin2hex(random_bytes(32)),
                    ));
                    $this->_remember_property($remembered_id);
                    return '';
                }
            }
        }

        if (count($properties) === 1) {
            $this->session->set_userdata(array(
                'active_property_id'     => (int) $properties[0]->id,
                'property_context_token' => bin2hex(random_bytes(32)),
            ));
            $this->_remember_property((int) $properties[0]->id);
            return '';
        }
        if (count($properties) > 1) {
            return 'properties/select';
        }
        return $user->role === User_model::ROLE_USER
            ? 'access/no-properties'
            : 'properties';
    }

    private function _remember_property($property_id)
    {
        $this->input->set_cookie(array(
            'name'     => 'stay_last_property',
            'value'    => (string) (int) $property_id,
            'expire'   => 90 * 24 * 60 * 60,
            'secure'   => (bool) config_item('cookie_secure'),
            'httponly' => TRUE,
            'samesite' => 'Lax',
        ));
    }

    /**
     * Emit a JSON response.
     *
     * @param  array $payload
     * @return void
     */
    private function _json($payload)
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }
}
