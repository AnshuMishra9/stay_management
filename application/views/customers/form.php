<?php
/**
 * Add / Edit Customer form.
 * $customer   -> row object when editing, NULL when adding
 * $next_code  -> customer code to display (existing or next generated)
 * $type_opts / $state_opts / $country_opts -> dropdown option arrays
 */
$is_edit = ($customer !== NULL);
$posted  = ($this->input->server('REQUEST_METHOD') === 'POST');

// Helper: current value for a field (posted value wins on validation failure).
// Return RAW (html_escape=FALSE); the template escapes once at output.
$val = function ($field, $fallback = '') use ($customer) {
    return set_value($field, $customer ? ($customer->$field ?? '') : $fallback, FALSE);
};
$sel_type    = $val('customer_type');
$sel_state   = $val('state');
$sel_country = $val('country', 'India');
$sel_channel = $val('booking_channel_id');
$sel_roomcat = $val('room_category_id');
$sel_status  = $val('booking_status');
$active      = $posted ? ($this->input->post('is_active') ? 1 : 0) : ($customer ? (int) $customer->is_active : 1);

// Format a stored DATETIME ("Y-m-d H:i:s") for a datetime-local input ("Y-m-d\TH:i").
$dtlocal = function ($field) use ($val) {
    $v = $val($field);
    return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Customer &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>">
</head>
<body class="erp-body">

<!-- Top navigation (with mobile Back button) -->
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
                    <label>Owner Name / Contact Person</label>
                    <input class="erp-input" type="text" name="owner_name" maxlength="150" value="<?= html_escape($val('owner_name')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Customer Type</label>
                    <select class="erp-select" name="customer_type">
                        <option value="">Select</option>
                        <?php foreach ($type_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_type === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <!-- Auto-enhanced into a searchable combobox by searchable-select.js -->
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

        <!-- ===== Booking Details ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Booking Details</div>

            <div class="erp-grid-1" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Booking Status <span class="erp-muted" style="font-weight:400;">(has the booking come / has the guest checked in?)</span></label>
                    <select class="erp-select" id="bk_status" name="booking_status">
                        <option value="">— Not set —</option>
                        <?php foreach ($status_opts as $sval => $slabel): ?>
                            <option value="<?= $sval ?>" <?= $sel_status === $sval ? 'selected' : '' ?>><?= $slabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Booking Channel</label>
                    <select class="erp-select" name="booking_channel_id">
                        <option value="">Select</option>
                        <?php foreach ($channel_opts as $ch): ?>
                            <option value="<?= (int) $ch->channel_id ?>" <?= (string) $sel_channel === (string) $ch->channel_id ? 'selected' : '' ?>><?= html_escape($ch->channel_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>Booking By <span class="erp-muted" style="font-weight:400;">(who booked)</span></label>
                    <input class="erp-input" type="text" name="booking_by" maxlength="150" value="<?= html_escape($val('booking_by')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Guest Name</label>
                    <input class="erp-input" type="text" name="guest_name" maxlength="150" value="<?= html_escape($val('guest_name')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Guest Mobile No</label>
                    <input class="erp-input" type="text" name="guest_mobile_no" maxlength="20" value="<?= html_escape($val('guest_mobile_no')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Guest Contact No</label>
                    <input class="erp-input" type="text" name="guest_contact_no" maxlength="20" value="<?= html_escape($val('guest_contact_no')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Property Name</label>
                    <input class="erp-input" type="text" name="property_name" maxlength="150" value="<?= html_escape($val('property_name')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Scheduled Check-In Date <span class="erp-muted" style="font-weight:400;">(planned arrival)</span></label>
                    <input class="erp-input" type="date" id="bk_checkin" name="scheduled_check_in_date" value="<?= html_escape($val('scheduled_check_in_date')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Scheduled Check-Out Date <span class="erp-muted" style="font-weight:400;">(planned departure)</span></label>
                    <input class="erp-input" type="date" id="bk_checkout" name="scheduled_check_out_date" value="<?= html_escape($val('scheduled_check_out_date')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Actual Checked-In At <span class="erp-muted" style="font-weight:400;">(auto-set on "Checked In")</span></label>
                    <input class="erp-input" type="datetime-local" name="checked_in_at" value="<?= html_escape($dtlocal('checked_in_at')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Actual Checked-Out At <span class="erp-muted" style="font-weight:400;">(auto-set on "Checked Out")</span></label>
                    <input class="erp-input" type="datetime-local" name="checked_out_at" value="<?= html_escape($dtlocal('checked_out_at')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Length of Stay <span class="erp-muted" style="font-weight:400;">(nights, auto)</span></label>
                    <input class="erp-input" type="number" id="bk_los" name="length_of_stay" value="<?= html_escape($val('length_of_stay')) ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Total Guests</label>
                    <input class="erp-input" type="number" min="0" name="total_guest" value="<?= html_escape($val('total_guest')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room Category</label>
                    <select class="erp-select" name="room_category_id">
                        <option value="">Select</option>
                        <?php foreach ($room_cat_opts as $rc): ?>
                            <option value="<?= (int) $rc->category_id ?>" <?= (string) $sel_roomcat === (string) $rc->category_id ? 'selected' : '' ?>><?= html_escape($rc->category_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>Room Quantity</label>
                    <input class="erp-input" type="number" min="0" name="room_quantity" value="<?= html_escape($val('room_quantity')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Total Units <span class="erp-muted" style="font-weight:400;">(total rooms booked)</span></label>
                    <input class="erp-input" type="number" min="0" name="total_unit" value="<?= html_escape($val('total_unit')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Total Amount</label>
                    <input class="erp-input" type="number" step="0.01" min="0" id="bk_total" name="total_amount" value="<?= html_escape($val('total_amount')) ?>">
                </div>
            </div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>Amount Paid</label>
                    <input class="erp-input" type="number" step="0.01" min="0" id="bk_paid" name="amount_paid" value="<?= html_escape($val('amount_paid')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Remaining Amount <span class="erp-muted" style="font-weight:400;">(auto)</span></label>
                    <input class="erp-input" type="number" step="0.01" id="bk_remaining" name="remaining_amount" value="<?= html_escape($val('remaining_amount')) ?>" readonly>
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

<script src="<?= base_url('assets/js/searchable-select.js') ?>"></script>
<script>
/* Booking Details — auto-calc Length of Stay and Remaining Amount. */
(function () {
    var ci = document.getElementById('bk_checkin'), co = document.getElementById('bk_checkout'),
        los = document.getElementById('bk_los'),
        total = document.getElementById('bk_total'), paid = document.getElementById('bk_paid'),
        rem = document.getElementById('bk_remaining');
    function num(el) { var v = parseFloat(el && el.value); return isNaN(v) ? 0 : v; }
    function calcLOS() {
        if (ci.value && co.value) {
            var d = Math.round((new Date(co.value) - new Date(ci.value)) / 86400000);
            los.value = d >= 0 ? d : '';
        } else { los.value = ''; }
    }
    function calcRem() {
        if ((total && total.value !== '') || (paid && paid.value !== '')) {
            rem.value = (num(total) - num(paid)).toFixed(2);
        } else { rem.value = ''; }
    }
    if (ci && co) { ci.addEventListener('change', calcLOS); co.addEventListener('change', calcLOS); }
    if (total && paid) {
        total.addEventListener('input', calcRem);
        paid.addEventListener('input', calcRem);
    }
})();
</script>
</body>
</html>
