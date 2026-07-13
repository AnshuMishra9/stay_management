<?php
/**
 * Add / Edit CUSTOMER form — customer fields ONLY.
 * Bookings are made on their own form (customers/booking_form), because one
 * customer can have MANY bookings.
 *
 * $customer   -> customers row when editing, NULL when adding
 * $next_code  -> customer code to display (existing or next generated)
 * $state_opts / $country_opts -> dropdown option arrays
 */
$is_edit = ($customer !== NULL);
$posted  = ($this->input->server('REQUEST_METHOD') === 'POST');

// Current value for a field (posted value wins on validation failure).
// Return RAW (html_escape=FALSE); the template escapes once at output.
$val = function ($field, $fallback = '') use ($customer) {
    return set_value($field, $customer ? ($customer->$field ?? '') : $fallback, FALSE);
};
$sel_state   = $val('state');
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
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array('active' => 'customers', 'back' => site_url('customers'))); ?>

<div class="erp-wrap">
    <form class="erp-card" style="max-width:920px;margin:0 auto;" action="<?= site_url('customers/save') ?>" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="id" value="<?= $is_edit ? (int) $customer->id : '' ?>">

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
                    <label>Alt Mobile No</label>
                    <input class="erp-input" type="text" name="alt_phone" maxlength="20" value="<?= html_escape($val('alt_phone')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Landline No</label>
                    <input class="erp-input" type="text" name="landline_no" maxlength="20" value="<?= html_escape($val('landline_no')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Email</label>
                    <input class="erp-input" type="email" name="email" maxlength="150" value="<?= html_escape($val('email')) ?>">
                    <?= form_error('email', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Address 1</label>
                    <input class="erp-input" type="text" name="address1" maxlength="255" value="<?= html_escape($val('address1')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Address 2</label>
                    <input class="erp-input" type="text" name="address2" maxlength="255" value="<?= html_escape($val('address2')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>City</label>
                    <input class="erp-input" type="text" name="city" maxlength="100" value="<?= html_escape($val('city')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>District</label>
                    <input class="erp-input" type="text" name="district" maxlength="100" value="<?= html_escape($val('district')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>State</label>
                    <select class="erp-select" name="state">
                        <option value="">Select</option>
                        <?php foreach ($state_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_state === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>Zip Code</label>
                    <input class="erp-input" type="text" name="zip_code" maxlength="15" value="<?= html_escape($val('zip_code')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Pincode</label>
                    <input class="erp-input" type="text" name="pincode" maxlength="15" value="<?= html_escape($val('pincode')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Country</label>
                    <select class="erp-select" name="country">
                        <option value="">Select</option>
                        <?php foreach ($country_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_country === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

        <!-- ===== Identity ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Identity</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Aadhar Number</label>
                    <input class="erp-input" type="text" name="aadhar_number" maxlength="20" value="<?= html_escape($val('aadhar_number')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Aadhar Card <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf, max 4MB)</span></label>
                    <input class="erp-file" type="file" name="aadhar_card" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($is_edit && ! empty($customer->aadhar_card_path)): ?>
                        <div class="erp-existing-file">
                            <a class="erp-doc-link" href="<?= site_url('customers/file/'.$customer->id.'/aadhar') ?>" target="_blank">Current file — view</a>
                            <span class="erp-muted"> · upload a new file to replace</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Aadhar Name</label>
                    <input class="erp-input" type="text" name="aadhar_name" maxlength="150" value="<?= html_escape($val('aadhar_name')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>PAN Number</label>
                    <input class="erp-input" type="text" name="pan_number" maxlength="20" value="<?= html_escape($val('pan_number')) ?>">
                </div>
            </div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>PAN Card <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf, max 4MB)</span></label>
                    <input class="erp-file" type="file" name="pan_card" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($is_edit && ! empty($customer->pan_card_path)): ?>
                        <div class="erp-existing-file">
                            <a class="erp-doc-link" href="<?= site_url('customers/file/'.$customer->id.'/pan') ?>" target="_blank">Current file — view</a>
                            <span class="erp-muted"> · upload a new file to replace</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="erp-form-field">
                    <label>PAN Name</label>
                    <input class="erp-input" type="text" name="pan_name" maxlength="150" value="<?= html_escape($val('pan_name')) ?>">
                </div>
            </div>
        </div>

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
</body>
</html>
