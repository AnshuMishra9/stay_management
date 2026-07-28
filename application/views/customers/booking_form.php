<?php
/**
 * New / Edit BOOKING form.
 *
 * The FIRST field is the customer's MOBILE NO. Typing a number that already
 * exists in `customers` pulls that customer's saved details in (see
 * assets/js/booking-form.js -> customers/lookup). Those details stay editable,
 * and saving writes any edits back to the customers table. A new number simply
 * creates a new customer. One customer can hold MANY bookings.
 *
 * $booking   -> booking_details row when editing, NULL for a new booking
 * $customer  -> that booking's customer (NULL for a new booking)
 */
$is_edit = ($booking !== NULL);
$booking = isset($booking) ? $booking : NULL;
$customer = isset($customer) ? $customer : NULL;

// CUSTOMER field value (posted wins on validation failure).
$val = function ($field, $fallback = '') use ($customer) {
    return set_value($field, $customer ? ($customer->$field ?? '') : $fallback, FALSE);
};
// BOOKING field value.
$bval = function ($field, $fallback = '') use ($booking) {
    return set_value($field, $booking ? ($booking->$field ?? '') : $fallback, FALSE);
};

$sel_country = $val('country', 'India');
$sel_channel = $bval('booking_channel_id');
$sel_roomcat = $bval('room_category_id');
$sel_room    = $bval('room_id');
// Booking status comes from status_master now. New bookings default to
// "Room booked" (the fixed default).
$sel_status  = $bval('status_id');
if ($sel_status === '' || $sel_status === NULL) {
    foreach ($status_opts as $s) {
        if ($s->status_code === 'room_booked') { $sel_status = $s->status_id; break; }
    }
}

// Stored DATETIME -> datetime-local input value.
$dtlocal = function ($field) use ($bval) {
    $v = $bval($field);
    return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'New' ?> Booking &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <script>window.APP_BASE = "<?= base_url() ?>";</script>
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array('active' => 'bookings', 'back' => site_url('customers/bookings'))); ?>

<div class="erp-wrap">
    <form class="erp-card" style="max-width:920px;margin:0 auto;" action="<?= site_url('customers/booking_save') ?>" method="post" novalidate>
        <input type="hidden" name="booking_id" value="<?= $is_edit ? (int) $booking->id : '' ?>">

        <!-- Header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/>
                    </svg>
                    <?= $is_edit ? 'Edit Booking' : 'New Booking' ?>
                </h1>
                <p class="erp-sub">
                    <?= $is_edit
                        ? 'Booking '.html_escape($booking->booking_number).' — update the booking or the customer\'s details'
                        : 'Start with the mobile number — an existing customer is loaded automatically' ?>
                </p>
            </div>
            <?php if ($is_edit): ?>
                <div class="erp-head-total"><?= html_escape($booking->booking_number) ?></div>
            <?php endif; ?>
        </div>

        <?php if (validation_errors()): ?>
            <div class="erp-alert erp-alert-danger"><?= validation_errors() ?></div>
        <?php endif; ?>

        <!-- ===== Customer (found by mobile) ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Customer</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Mobile No <span class="req">*</span></label>
                    <input class="erp-input" type="text" id="bf_phone" name="phone" required maxlength="20"
                           autocomplete="off" value="<?= html_escape($val('phone')) ?>">
                    <?= form_error('phone', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Customer Name <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="customer_name" required maxlength="150" value="<?= html_escape($val('customer_name')) ?>">
                    <?= form_error('customer_name', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2">
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
        </div>

        <!-- ===== Booking Details ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Booking Details</div>

            <div class="erp-grid-1" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Booking Status</label>
                    <select class="erp-select" id="bk_status" name="status_id">
                        <?php foreach ($status_opts as $s): ?>
                            <option value="<?= (int) $s->status_id ?>" <?= (string) $sel_status === (string) $s->status_id ? 'selected' : '' ?>><?= html_escape($s->status_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= form_error('status_id', '<div class="erp-error">', '</div>') ?>
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
                    <label>Property Name</label>
                    <input class="erp-input" type="text" name="property_name" maxlength="150" value="<?= html_escape($bval('property_name')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Check In</label>
                    <input class="erp-input" type="datetime-local" id="bk_checkin" name="checked_in_at" value="<?= html_escape($dtlocal('checked_in_at')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Check Out</label>
                    <input class="erp-input" type="datetime-local" id="bk_checkout" name="checked_out_at" value="<?= html_escape($dtlocal('checked_out_at')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room Category</label>
                    <select class="erp-select" id="bk_room_category" name="room_category_id">
                        <option value="">Select</option>
                        <?php foreach ($room_cat_opts as $rc): ?>
                            <option value="<?= (int) $rc->category_id ?>" <?= (string) $sel_roomcat === (string) $rc->category_id ? 'selected' : '' ?>><?= html_escape($rc->category_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>Allot Room <span class="erp-muted" style="font-weight:400;">(only rooms not already assigned)</span></label>
                    <select class="erp-select" id="bk_room" name="room_id" data-search="always" data-placeholder="Select a room">
                        <option value="">— No room —</option>
                        <?php foreach ($room_opts as $rm): ?>
                            <option value="<?= (int) $rm->id ?>" data-category-id="<?= (int) $rm->category_id ?>" <?= (string) $sel_room === (string) $rm->id ? 'selected' : '' ?>><?= html_escape($rm->room_no) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= form_error('room_id', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room Quantity</label>
                    <input class="erp-input" type="number" min="0" name="room_quantity" value="<?= html_escape($bval('room_quantity')) ?>">
                </div>
                <div class="erp-form-field"></div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Length of Stay <span class="erp-muted" style="font-weight:400;">(nights, auto)</span></label>
                    <input class="erp-input" type="number" id="bk_los" name="length_of_stay" value="<?= html_escape($bval('length_of_stay')) ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Total Guests</label>
                    <input class="erp-input" type="number" min="0" name="total_guest" value="<?= html_escape($bval('total_guest')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Total Units <span class="erp-muted" style="font-weight:400;">(total rooms booked)</span></label>
                    <input class="erp-input" type="number" min="0" name="total_unit" value="<?= html_escape($bval('total_unit')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Total Amount</label>
                    <input class="erp-input" type="number" step="0.01" min="0" id="bk_total" name="total_amount" value="<?= html_escape($bval('total_amount')) ?>">
                </div>
            </div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>Amount Paid</label>
                    <input class="erp-input" type="number" step="0.01" min="0" id="bk_paid" name="amount_paid" value="<?= html_escape($bval('amount_paid')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Remaining Amount <span class="erp-muted" style="font-weight:400;">(auto)</span></label>
                    <input class="erp-input" type="number" step="0.01" id="bk_remaining" value="<?= html_escape($bval('remaining_amount')) ?>" readonly>
                </div>
            </div>
        </div>

        <!-- Footer actions -->
        <div class="erp-form-foot">
            <a href="<?= site_url('customers/bookings') ?>" class="erp-btn erp-btn-ghost">Cancel</a>
            <button type="submit" class="erp-btn erp-btn-primary">
                <?= $is_edit ? 'Update Booking' : 'Save Booking' ?>
            </button>
        </div>
    </form>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/booking-form.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/booking-form.js') ?>"></script>
</body>
</html>
