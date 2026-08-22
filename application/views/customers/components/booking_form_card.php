<?php
/**
 * Shared New / Edit Booking form card.
 *
 * Rendered by the normal Booking page and by the Inventory booking modal.
 * Inventory reservations use scheduled dates; actual check-in/out timestamps
 * remain empty until the corresponding operational workflow is completed.
 */
$booking = isset($booking) ? $booking : NULL;
$customer = isset($customer) ? $customer : NULL;
$is_edit = ($booking !== NULL);
$form_context = isset($form_context) ? $form_context : 'page';
$inventory_mode = ($form_context === 'inventory' && ! $is_edit);
$booking_defaults = isset($booking_defaults) && is_array($booking_defaults)
    ? $booking_defaults
    : array();
$booking_form_error = isset($booking_form_error) ? $booking_form_error : '';
$active_property_name = isset($current_property) && $current_property
    ? (string) $current_property->property_name
    : '';

// Posted values win after validation, followed by stored/default values.
$val = function ($field, $fallback = '') use ($customer) {
    return set_value($field, $customer ? ($customer->$field ?? '') : $fallback, FALSE);
};
$bval = function ($field, $fallback = '') use ($booking, $booking_defaults) {
    if ($booking && isset($booking->$field)) {
        $fallback = $booking->$field;
    } elseif (array_key_exists($field, $booking_defaults)) {
        $fallback = $booking_defaults[$field];
    }
    return set_value($field, $fallback, FALSE);
};

$sel_country = $val('country', 'India');
$sel_channel = $bval('booking_channel_id');
$sel_roomcat = $bval('room_category_id');
$sel_room = $bval('room_id');
$sel_status = $bval('status_id');
if ($sel_status === '' || $sel_status === NULL || $inventory_mode) {
    foreach ($status_opts as $s) {
        if ($s->status_code === 'room_booked') {
            $sel_status = $s->status_id;
            break;
        }
    }
}

// Stored DATETIME -> datetime-local input value.
$dtlocal = function ($field) use ($bval) {
    $value = $bval($field);
    return $value ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
};
?>
<form class="erp-card booking-form-card"
      data-booking-form
      data-booking-context="<?= html_escape($form_context) ?>"
      style="max-width:920px;margin:0 auto;"
      action="<?= site_url('customers/booking_save') ?>"
      method="post"
      novalidate>
    <input type="hidden" name="booking_id" value="<?= $is_edit ? (int) $booking->id : '' ?>">
    <input type="hidden" name="property_context_token" value="<?= html_escape($property_context_token ?? '') ?>">
    <?php if ($inventory_mode): ?>
        <input type="hidden" name="booking_source" value="inventory">
    <?php endif; ?>

    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/>
                </svg>
                <?= $is_edit ? 'Edit Booking' : 'New Booking' ?>
            </h1>
            <p class="erp-sub">
                <?php if ($is_edit): ?>
                    Booking <?= html_escape($booking->booking_number) ?> &mdash; update the booking or the customer's details
                <?php elseif ($inventory_mode): ?>
                    Selected room and stay dates are ready. Enter the guest details to confirm this booking.
                <?php else: ?>
                    Start with the mobile number &mdash; an existing customer is loaded automatically
                <?php endif; ?>
            </p>
        </div>
        <?php if ($is_edit): ?>
            <div class="erp-head-total"><?= html_escape($booking->booking_number) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($booking_form_error): ?>
        <div class="erp-alert erp-alert-danger" role="alert"><?= html_escape($booking_form_error) ?></div>
    <?php endif; ?>
    <?php if (validation_errors()): ?>
        <div class="erp-alert erp-alert-danger" role="alert"><?= validation_errors() ?></div>
    <?php endif; ?>

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

    <div class="erp-form-section">
        <div class="erp-section-title">Booking Details</div>

        <div class="erp-grid-1" style="margin-bottom:16px;">
            <div class="erp-form-field">
                <label>Booking Status</label>
                <?php if ($inventory_mode): ?>
                    <input type="hidden" name="status_id" value="<?= (int) $sel_status ?>">
                    <input class="erp-input" type="text" value="Room booked" readonly aria-readonly="true">
                <?php else: ?>
                    <select class="erp-select" id="bk_status" name="status_id">
                        <?php foreach ($status_opts as $s): ?>
                            <option value="<?= (int) $s->status_id ?>" <?= (string) $sel_status === (string) $s->status_id ? 'selected' : '' ?>><?= html_escape($s->status_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?= form_error('status_id', '<div class="erp-error">', '</div>') ?>
            </div>
        </div>

        <div class="erp-grid-2" style="margin-bottom:16px;">
            <div class="erp-form-field">
                <label>Booking Channel</label>
                <select class="erp-select" name="booking_channel_id">
                    <option value="">Select</option>
                    <?php foreach ($channel_opts as $channel): ?>
                        <option value="<?= (int) $channel->channel_id ?>" <?= (string) $sel_channel === (string) $channel->channel_id ? 'selected' : '' ?>><?= html_escape($channel->channel_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="erp-form-field">
                <label>Property Name</label>
                <input class="erp-input" type="text" value="<?= html_escape($active_property_name) ?>" readonly aria-readonly="true">
            </div>
        </div>

        <?php if ($inventory_mode): ?>
            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Scheduled Check In <span class="req">*</span></label>
                    <input class="erp-input" type="date" id="bk_checkin" name="scheduled_check_in_date" required readonly
                           value="<?= html_escape($bval('scheduled_check_in_date')) ?>">
                    <?= form_error('scheduled_check_in_date', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Scheduled Check Out <span class="req">*</span></label>
                    <input class="erp-input" type="date" id="bk_checkout" name="scheduled_check_out_date" required readonly
                           aria-describedby="inventoryCheckoutPolicy"
                           value="<?= html_escape($bval('scheduled_check_out_date')) ?>">
                    <div class="erp-muted" id="inventoryCheckoutPolicy" style="font-size:.78rem;margin-top:5px;">
                        Checkout at 11:00 AM. This checkout date remains available for the next booking.
                    </div>
                    <?= form_error('scheduled_check_out_date', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>
        <?php else: ?>
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
        <?php endif; ?>

        <div class="erp-grid-2" style="margin-bottom:16px;">
            <div class="erp-form-field">
                <label>Room Category</label>
                <select class="erp-select" id="bk_room_category" name="room_category_id">
                    <option value="">Select</option>
                    <?php foreach ($room_cat_opts as $category): ?>
                        <option value="<?= (int) $category->category_id ?>" <?= (string) $sel_roomcat === (string) $category->category_id ? 'selected' : '' ?>><?= html_escape($category->category_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="erp-form-field">
                <label>Allot Room <?= $inventory_mode ? '<span class="req">*</span>' : '' ?> <span class="erp-muted" style="font-weight:400;">(available for selected dates)</span></label>
                <select class="erp-select" id="bk_room" name="room_id" data-search="always" data-placeholder="Select a room" <?= $inventory_mode ? 'required' : '' ?>>
                    <option value="">&mdash; No room &mdash;</option>
                    <?php foreach ($room_opts as $room): ?>
                        <option value="<?= (int) $room->id ?>"
                                data-category-id="<?= $room->category_id !== NULL ? (int) $room->category_id : '' ?>"
                                data-selling-price="<?= html_escape($room->selling_price) ?>"
                                <?= (string) $sel_room === (string) $room->id ? 'selected' : '' ?>><?= html_escape($room->room_no) ?></option>
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

    <div class="erp-form-foot">
        <?php if ($inventory_mode): ?>
            <button type="button" class="erp-btn erp-btn-ghost" data-booking-cancel>Cancel</button>
        <?php else: ?>
            <a href="<?= site_url('customers/bookings') ?>" class="erp-btn erp-btn-ghost">Cancel</a>
        <?php endif; ?>
        <button type="submit" class="erp-btn erp-btn-primary" data-booking-submit>
            <?= $is_edit ? 'Update Booking' : 'Save Booking' ?>
        </button>
    </div>
</form>
