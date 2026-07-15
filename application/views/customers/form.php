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
    <style>
        .idn-row { position: relative; border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; margin-bottom: 14px; background: var(--head); }
        .idn-fields { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .idn-remove { position: absolute; top: 8px; right: 10px; width: 26px; height: 26px; padding: 0; border: none; border-radius: 8px; background: var(--red-soft); color: var(--red); font-size: 18px; line-height: 1; cursor: pointer; }
        .idn-remove:hover { background: var(--red); color: #fff; }
        .idn-title { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        @media (max-width: 720px) { .idn-fields { grid-template-columns: 1fr; } }
    </style>
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

        <!-- ===== Identity Proof (repeatable) ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title idn-title">
                <span>Identity Proof</span>
                <button type="button" id="idnAddMore" class="erp-btn erp-btn-soft erp-btn-sm">+ Add More</button>
            </div>

            <div id="identityRows">
                <?php
                // Existing identities on edit; at least one blank row otherwise.
                $rows = ! empty($identities) ? $identities : array(NULL);
                foreach ($rows as $idn):
                    $rid  = $idn ? (int) $idn->id : '';
                    $rtyp = $idn ? $idn->identity_type : '';
                    $rnum = $idn ? $idn->identity_number : '';
                    $rdoc = ($idn && ! empty($idn->document_path)) ? site_url('customers/identity_file/'.$idn->id) : '';
                ?>
                <div class="idn-row">
                    <button type="button" class="idn-remove" title="Remove">&times;</button>
                    <input type="hidden" name="identity_id[]" value="<?= html_escape($rid) ?>">
                    <div class="idn-fields">
                        <div class="erp-form-field">
                            <label>ID Proof Type</label>
                            <select class="erp-select" name="identity_type[]" data-search="never" data-placeholder="Select ID Proof">
                                <option value="">Select ID Proof</option>
                                <?php foreach ($identity_types as $tv => $tl): ?>
                                    <option value="<?= html_escape($tv) ?>" <?= $rtyp === $tv ? 'selected' : '' ?>><?= html_escape($tl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="erp-form-field">
                            <label>Identity Number</label>
                            <input class="erp-input" type="text" name="identity_number[]" maxlength="50" value="<?= html_escape($rnum) ?>">
                        </div>
                        <div class="erp-form-field">
                            <label>Document <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf)</span></label>
                            <input class="erp-file" type="file" name="identity_document[]" accept=".jpg,.jpeg,.png,.pdf">
                            <?php if ($rdoc): ?>
                                <div class="erp-existing-file">
                                    <a class="erp-doc-link" href="<?= $rdoc ?>" target="_blank">Current file — view</a>
                                    <span class="erp-muted"> · upload to replace</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Blank row template cloned by the "Add More" button -->
        <template id="identityRowTpl">
            <div class="idn-row">
                <button type="button" class="idn-remove" title="Remove">&times;</button>
                <input type="hidden" name="identity_id[]" value="">
                <div class="idn-fields">
                    <div class="erp-form-field">
                        <label>ID Proof Type</label>
                        <select class="erp-select" name="identity_type[]" data-search="never" data-placeholder="Select ID Proof">
                            <option value="">Select ID Proof</option>
                            <?php foreach ($identity_types as $tv => $tl): ?>
                                <option value="<?= html_escape($tv) ?>"><?= html_escape($tl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="erp-form-field">
                        <label>Identity Number</label>
                        <input class="erp-input" type="text" name="identity_number[]" maxlength="50" value="">
                    </div>
                    <div class="erp-form-field">
                        <label>Document <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf)</span></label>
                        <input class="erp-file" type="file" name="identity_document[]" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>
            </div>
        </template>

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
