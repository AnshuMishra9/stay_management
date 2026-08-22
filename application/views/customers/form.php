<?php
/**
 * Add / Edit CUSTOMER form — customer fields ONLY.
 * Bookings are made on their own form (customers/booking_form), because one
 * customer can have MANY bookings.
 *
 * $customer   -> customers row when editing, NULL when adding
 * $next_code  -> customer code to display (existing or next generated)
 * $country_opts -> dropdown option array
 */
$is_edit = ($customer !== NULL);
$posted  = ($this->input->server('REQUEST_METHOD') === 'POST');

// Current value for a field (posted value wins on validation failure).
// Return RAW (html_escape=FALSE); the template escapes once at output.
$val = function ($field, $fallback = '') use ($customer) {
    return set_value($field, $customer ? ($customer->$field ?? '') : $fallback, FALSE);
};
$sel_country = $val('country', 'India');
$active      = $posted ? ($this->input->post('is_active') ? 1 : 0) : ($customer ? (int) $customer->is_active : 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Customer &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <script>
        window.APP_PROPERTY_CONTEXT_TOKEN = <?= json_encode($property_context_token ?? '') ?>;
    </script>
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array('active' => 'customers', 'back' => site_url('customers'))); ?>

<div class="erp-wrap">
    <form class="erp-card" style="max-width:920px;margin:0 auto;" action="<?= site_url('customers/save') ?>" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="id" value="<?= $is_edit ? (int) $customer->id : '' ?>">
        <input type="hidden" name="customer_write_token" value="<?= html_escape($this->session->userdata('customer_write_token')) ?>">
        <input type="hidden" name="property_context_token" value="<?= html_escape($property_context_token ?? '') ?>">

        <!-- Header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                    <?= $is_edit ? 'Edit Customer' : 'Add New Customer' ?>
                </h1>
                <p class="erp-sub"><?= $is_edit ? 'Update customer details' : 'Enter customer details' ?></p>
            </div>
        </div>

        <?php if (validation_errors()): ?>
            <div class="erp-alert erp-alert-danger"><?= validation_errors() ?></div>
        <?php endif; ?>

        <!-- ===== Customer Info ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Customer Info</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Customer ID</label>
                    <input class="erp-input" type="text" value="<?= html_escape($next_code) ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Customer Name <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="customer_name" required maxlength="150" value="<?= html_escape($val('customer_name')) ?>">
                    <?= form_error('customer_name', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Mobile No <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="phone" required maxlength="20" value="<?= html_escape($val('phone')) ?>">
                    <?= form_error('phone', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Pincode</label>
                    <input class="erp-input" type="text" name="pincode" maxlength="15" value="<?= html_escape($val('pincode')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Country</label>
                    <select class="erp-select" name="country">
                        <option value="">Select</option>
                        <?php foreach ($country_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_country === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field"></div>
            </div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label class="erp-check" style="margin:0;">
                        <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
                        Active customer
                    </label>
                </div>
            </div>
        </div>

        <?php $this->load->view('customers/components/identity_proof', array(
            'identity_types' => $identity_types,
            'identities'     => $identities,
            'identity_upload_error' => isset($identity_upload_error) ? $identity_upload_error : '',
        )); ?>

        <!-- Footer actions -->
        <div class="erp-form-foot">
            <a href="<?= site_url('customers') ?>" class="erp-btn erp-btn-ghost">Cancel</a>
            <button type="submit" class="erp-btn erp-btn-primary">
                <?= $is_edit ? 'Update Customer' : 'Save Customer' ?>
            </button>
        </div>
    </form>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/identity-rows.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/identity-rows.js') ?>"></script>
</body>
</html>
