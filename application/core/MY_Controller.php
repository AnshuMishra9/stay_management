<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller
 *
 * Base controller for the application. Public-facing controllers (e.g. the
 * login page) may extend this directly.
 *
 * CodeIgniter automatically loads this file (subclass_prefix = 'MY_') before
 * any controller, so every base class defined here is available application-wide.
 */
class MY_Controller extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
}

/**
 * Secure_Controller
 *
 * Base controller for every page that requires an authenticated session.
 * Any controller that extends this class is protected: if there is no valid
 * login session the user is redirected to the login page before the requested
 * action ever runs. This blocks direct URL access to internal pages.
 *
 * Usage:
 *     class Dashboard extends Secure_Controller { ... }
 */
class Secure_Controller extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        if ( ! $this->session->userdata('logged_in')) {
            // Not authenticated -> bounce to login.
            redirect('login');
            exit;
        }
    }
}
