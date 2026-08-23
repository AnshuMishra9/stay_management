<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Customers (Master)
 *
 * Protected module (extends Secure_Controller) — an authenticated session is
 * required for every action, including document streaming.
 *
 *   index()        -> renders the list grid page
 *   list_ajax()    -> [AJAX] JSON of filtered customers (live filtering)
 *   view($id)      -> [AJAX] JSON of one customer, all fields + document URLs
 *   form($id=null) -> renders the Add / Edit form (prefilled when editing)
 *   save()         -> [POST] insert or update + handle secure file uploads
 *   delete($id)    -> [AJAX/POST] delete row + remove the customer's files
 *   file($id,$t)   -> streams an Aadhar/PAN document from outside the web root
 */
class Customers extends Ops_Controller
{
    /** Allowed upload extensions / size (KB). */
    const UPLOAD_TYPES   = 'jpg|jpeg|png|pdf';
    const UPLOAD_MAX_KB  = 4096;
    const MAX_IDENTITY_ROWS = 20;

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Customer_model');
        $this->load->helper('file');
        $this->load->library('Identity_upload_guard');
    }

    // ---------------------------------------------------------------------
    //  Pages
    // ---------------------------------------------------------------------

    /**
     * Customers Master list page.
     */
    public function index()
    {
        $data = array(
            'flash'  => $this->session->flashdata('customer_msg'),
        );
        $this->load->view('customers/list', $data);
    }

    /**
     * Add / Edit CUSTOMER form (customer fields only — no booking here).
     * Pass an id to edit; omit to add.
     */
    public function form($id = NULL)
    {
        $customer = NULL;

        if ($id !== NULL) {
            $customer = $this->Customer_model->get_by_id($this->current_tenant_id, $id);
            if ( ! $customer) {
                show_404();
                return;
            }
        }

        $data = array(
            'customer'       => $customer,
            'next_code'      => $customer ? $customer->customer_code : $this->Customer_model->next_code($this->current_tenant_id),
            'country_opts'   => $this->_country_options(),
            'identity_types' => $this->_identity_types(),
            'identities'     => $customer ? $this->Customer_model->get_identities(
                $this->current_tenant_id,
                $this->current_property_id,
                $customer->id
            ) : array(),
        );
        $this->load->view('customers/form', $data);
    }

    // ---------------------------------------------------------------------
    //  AJAX / JSON endpoints
    // ---------------------------------------------------------------------

    /**
     * Return filtered customers as JSON (consumed by the live filter UI).
     */
    public function list_ajax()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $filters = array(
            'customer_code' => $this->input->get('customer_code'),
            'name'          => $this->input->get('name'),
            'phone'         => $this->input->get('phone'),
            'status'        => $this->input->get('status'),
        );

        $rows = $this->Customer_model->get_filtered($this->current_tenant_id, $filters);

        return $this->_json(array('status' => TRUE, 'data' => $rows));
    }

    /**
     * [AJAX] Look a customer up by MOBILE NO, so the Booking form can auto-fill
     * an existing customer's saved details the moment the number is typed.
     */
    public function lookup()
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $customer = $this->Customer_model->get_by_phone(
            $this->current_tenant_id,
            $this->input->get('phone')
        );

        if ( ! $customer) {
            return $this->_json(array('status' => TRUE, 'found' => FALSE));
        }

        return $this->_json(array('status' => TRUE, 'found' => TRUE, 'data' => $customer));
    }

    /**
     * Return one customer's full detail (all fields + document URLs).
     */
    public function view($id = NULL)
    {
        if ( ! $this->_require_property_context(TRUE)) { return; }
        $customer = $id ? $this->Customer_model->get_by_id($this->current_tenant_id, $id) : NULL;
        if ( ! $customer) {
            return $this->_json(array('status' => FALSE, 'message' => 'Customer not found.'), 404);
        }

        // Attach identity proofs (with a label + streamed-document URL each).
        $labels = $this->_identity_types();
        $customer->identities = array_map(function ($idn) use ($labels) {
            return array(
                'identity_type'   => $idn->identity_type,
                'type_label'      => isset($labels[$idn->identity_type]) ? $labels[$idn->identity_type] : $idn->identity_type,
                'identity_number' => $idn->identity_number,
                'document_url'    => $idn->document_path ? site_url('customers/identity_file/'.$idn->id) : NULL,
                'document_url_2'  => $idn->document_path_2 ? site_url('customers/identity_file/'.$idn->id.'/2') : NULL,
            );
        }, $this->Customer_model->get_identities(
            $this->current_tenant_id,
            $this->current_property_id,
            $customer->id
        ));

        return $this->_json(array('status' => TRUE, 'data' => $customer));
    }

    // ---------------------------------------------------------------------
    //  Writes
    // ---------------------------------------------------------------------

    /**
     * Insert or update a customer (multipart form submit).
     */
    public function save()
    {
        if ( ! $this->require_post() || ! $this->_require_property_context(FALSE)) { return; }
        if ( ! $this->_valid_customer_write_token()) {
            show_error('This form expired or came from another site. Refresh the page and try again.', 403);
            return;
        }
        $id       = (int) $this->input->post('id');
        $is_edit  = $id > 0;
        $existing = $is_edit
            ? $this->Customer_model->get_by_id($this->current_tenant_id, $id)
            : NULL;

        if ($is_edit && ! $existing) {
            show_404();
            return;
        }

        // --- Validation --------------------------------------------------
        $this->load->library('form_validation');
        $this->form_validation->set_rules('customer_name', 'Customer Name', 'required|trim|max_length[150]');
        $this->form_validation->set_rules('phone', 'Mobile No', 'required|trim|max_length[20]');
        $identity_upload_error = $this->_identity_upload_error($id);
        $submitted_phone = trim((string) $this->input->post('phone', TRUE));
        $phone_owner = $this->Customer_model->get_by_phone($this->current_tenant_id, $submitted_phone);
        if ($phone_owner && ( ! $existing || (int) $phone_owner->id !== (int) $existing->id)) {
            $identity_upload_error = 'A customer with this mobile number already exists in this account.';
        }
        if ($this->form_validation->run() === FALSE || $identity_upload_error !== NULL) {
            // Re-render the form with errors + submitted values.
            $data = array(
                'customer'       => $existing,
                'next_code'      => $is_edit ? $existing->customer_code : $this->Customer_model->next_code($this->current_tenant_id),
                'country_opts'   => $this->_country_options(),
                'identity_types' => $this->_identity_types(),
                'identities'     => $existing ? $this->Customer_model->get_identities(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $existing->id
                ) : array(),
                'identity_upload_error' => $identity_upload_error,
            );
            $this->load->view('customers/form', $data);
            return;
        }

        // Immutable code: keep on edit, generate fresh on add (never trust POST).
        $code = $is_edit ? $existing->customer_code : NULL;

        // --- Scalar fields (CUSTOMER only — bookings are a separate form) --
        $data = array(
            'customer_name' => $this->input->post('customer_name', TRUE),
            'phone'         => $submitted_phone,                         // Mobile No
            'pincode'       => $this->input->post('pincode', TRUE),
            'country'       => $this->input->post('country', TRUE),
            'is_active'     => $this->input->post('is_active') !== NULL ? 1 : 0,
        );
        if ($is_edit) {
            $data['is_active'] = (int) $this->input->post('is_active');
        }

        // Customer code and phone are tenant-wide. Serialize the final
        // duplicate check for both creates and edits so two properties cannot
        // race a phone change or generate the same code.
        $this->db->trans_begin();
        if ( ! $this->Customer_model->lock_customer_creation_sequence($this->current_tenant_id)) {
            $this->db->trans_rollback();
            show_error('The customer could not be saved right now. Please try again.', 503);
            return;
        }

        $locked_phone_owner = $this->Customer_model->get_by_phone(
            $this->current_tenant_id,
            $submitted_phone
        );
        if ($locked_phone_owner && ( ! $existing || (int) $locked_phone_owner->id !== (int) $existing->id)) {
            $this->db->trans_rollback();
            $this->load->view('customers/form', array(
                'customer'       => $existing,
                'next_code'      => $is_edit
                    ? $existing->customer_code
                    : $this->Customer_model->next_code($this->current_tenant_id),
                'country_opts'   => $this->_country_options(),
                'identity_types' => $this->_identity_types(),
                'identities'     => $existing ? $this->Customer_model->get_identities(
                    $this->current_tenant_id,
                    $this->current_property_id,
                    $existing->id
                ) : array(),
                'identity_upload_error' => 'A customer with this mobile number already exists in this account.',
            ));
            return;
        }

        if ($is_edit) {
            $saved = $this->Customer_model->update($this->current_tenant_id, $id, $data);
            $cust_id = $saved ? $id : 0;
            $msg = 'Customer "'.$data['customer_name'].'" updated successfully.';
        } else {
            $code = $this->Customer_model->next_code($this->current_tenant_id);
            $data['customer_code'] = $code;
            $cust_id = $this->Customer_model->insert($this->current_tenant_id, $data);
            $msg = 'Customer "'.$data['customer_name'].'" ('.$code.') added successfully.';
        }

        if ($this->db->trans_status() === FALSE || ! $cust_id) {
            $this->db->trans_rollback();
            show_error('The customer could not be saved. Please try again.', 500);
            return;
        }
        if ( ! $this->db->trans_commit()) {
            show_error('The customer could not be saved. Please try again.', 500);
            return;
        }

        // --- Identity proofs (dynamic rows + their uploaded documents) -------
        $identity_save_errors = $this->_save_identities($cust_id);

        if ($identity_save_errors) {
            $msg .= ' Customer details were saved, but a document could not be stored: '.reset($identity_save_errors);
        }

        $this->session->set_flashdata('customer_msg', array(
            'type' => $identity_save_errors ? 'danger' : 'success',
            'text' => $msg,
        ));
        redirect('customers');
    }

    /**
     * Delete a customer and remove their uploaded documents/folder.
     */
    public function delete($id = NULL)
    {
        if ( ! $this->require_post() || ! $this->_require_property_context(TRUE)) { return; }
        $this->db->trans_begin();
        if ( ! $this->Customer_model->lock_customer_creation_sequence($this->current_tenant_id)) {
            $this->db->trans_rollback();
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'The customer could not be changed right now. Please try again.',
            ), 503);
        }

        $customer = $id ? $this->Customer_model->get_by_id($this->current_tenant_id, $id) : NULL;
        if ( ! $customer) {
            $this->db->trans_rollback();
            return $this->_json(array('status' => FALSE, 'message' => 'Customer not found.'), 404);
        }

        // A customer is never deleted. Delete always means deactivate: the
        // row (and every booking/document) stays in the database forever.
        $changed = $this->Customer_model->update(
            $this->current_tenant_id,
            $id,
            array('is_active' => 0)
        );
        if ( ! $changed || $this->db->trans_status() === FALSE || ! $this->db->trans_commit()) {
            $this->db->trans_rollback();
            return $this->_json(array(
                'status' => FALSE,
                'message' => 'The customer could not be deactivated.',
            ), 500);
        }

        return $this->_json(array(
            'status'  => TRUE,
            'deleted' => FALSE,
            'deactivated' => TRUE,
            'message' => 'Customer "'.$customer->customer_name.'" was deactivated. No data was deleted.',
        ));
    }

    // ---------------------------------------------------------------------
    //  Secure document streaming
    // ---------------------------------------------------------------------

    /**
     * Stream an uploaded identity-proof document by its identity id. Files live
     * outside the public tree; access is only possible here, behind the guard.
     *
     * @param int $identity_id
     * @param int $slot 1 = front/file, 2 = back image
     */
    public function identity_file($identity_id = NULL, $slot = 1)
    {
        $identity = $identity_id ? $this->Customer_model->get_identity(
            $this->current_tenant_id,
            $this->current_property_id,
            $identity_id
        ) : NULL;
        $slot = (int) $slot;
        if ( ! $identity || ! in_array($slot, array(1, 2), TRUE)) { show_404(); return; }
        $field = $slot === 2 ? 'document_path_2' : 'document_path';
        $path  = isset($identity->$field) ? $identity->$field : NULL;
        $abs   = $this->_secure_upload_file($path);
        if ($abs === NULL) { show_404(); return; }

        $mimes = array(
            'pdf' => 'application/pdf', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        );
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if ( ! isset($mimes[$ext])) { show_404(); return; }
        $mime = $mimes[$ext];

        // Stored identity documents are sensitive and must not be MIME-sniffed,
        // embedded by another site, or retained in a shared browser cache.
        $safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($abs));

        $this->output
            ->set_content_type($mime)
            ->set_header('Content-Disposition: inline; filename="'.$safe_name.'"')
            ->set_header('Content-Length: '.filesize($abs))
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data:; style-src \'unsafe-inline\'; sandbox')
            ->set_header('Cache-Control: private, no-store, max-age=0')
            ->set_header('Pragma: no-cache')
            ->set_output(file_get_contents($abs));
    }
}
