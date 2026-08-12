<?php
/**
 * Booking Check-in — a focused edit of just the fields that matter at
 * check-in: the customer's name + mobile, their identity proofs (same block
 * as the customer master), and the booking status. All pre-loaded; editable;
 * saved back to the customer + booking.
 *
 * $booking        -> booking_details row
 * $customer       -> that booking's customer
 * $status_opts    -> status_master rows (dropdown)
 * $identity_types -> code => label
 * $identities     -> existing customer_identities rows
 */
$page_title = isset($page_title) ? $page_title : 'Check-in';
$page_subtitle = isset($page_subtitle)
    ? $page_subtitle
    : 'Booking '.$booking->booking_number.' — update the customer & status';
$active_nav = isset($active_nav) ? $active_nav : 'bookings';
$back_url = isset($back_url) ? $back_url : 'customers/bookings';
$page_context = isset($page_context) ? $page_context : 'bookings';
$lock_status = isset($lock_status) ? (bool) $lock_status : FALSE;
$submit_label = isset($submit_label) ? $submit_label : 'Update';

$cval = function ($field, $fallback = '') use ($customer) {
    return set_value($field, $customer ? ($customer->$field ?? '') : $fallback, FALSE);
};
$sel_status = $lock_status
    ? (string) $booking->status_id
    : set_value('status_id', (string) $booking->status_id);
$sel_roomcat = set_value('room_category_id', (string) ($booking->room_category_id ?? ''));
$sel_room = set_value('room_id', (string) ($booking->room_id ?? ''));
$scheduled_check_in = $booking->scheduled_check_in_date
    ?: ($booking->checked_in_at ? substr($booking->checked_in_at, 0, 10) : '');
$scheduled_check_out = $booking->scheduled_check_out_date
    ?: ($booking->checked_out_at ? substr($booking->checked_out_at, 0, 10) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= html_escape($page_title) ?> <?= html_escape($booking->booking_number) ?> &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <script>window.APP_BASE = "<?= base_url() ?>";</script>
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array('active' => $active_nav, 'back' => site_url($back_url))); ?>

<div class="erp-wrap">
    <form class="erp-card" data-booking-form data-booking-context="checkin" style="max-width:920px;margin:0 auto;" action="<?= site_url('customers/checkin_save') ?>" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="booking_id" value="<?= (int) $booking->id ?>">
        <input type="hidden" name="page_context" value="<?= html_escape($page_context) ?>">

        <!-- Header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                    <?= html_escape($page_title) ?>
                </h1>
                <p class="erp-sub"><?= html_escape($page_subtitle) ?></p>
            </div>
            <div class="erp-head-total"><?= html_escape($booking->booking_number) ?></div>
        </div>

        <?php if (validation_errors()): ?>
            <div class="erp-alert erp-alert-danger"><?= validation_errors() ?></div>
        <?php endif; ?>

        <?php if ( ! empty($page_error)): ?>
            <div class="erp-alert erp-alert-danger" role="alert"><?= html_escape($page_error) ?></div>
        <?php endif; ?>

        <!-- ===== Customer ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Customer</div>
            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Customer Name <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="customer_name" required maxlength="150" value="<?= html_escape($cval('customer_name')) ?>">
                    <?= form_error('customer_name', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Mobile No <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="phone" required maxlength="20" value="<?= html_escape($cval('phone')) ?>">
                    <?= form_error('phone', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>
        </div>

        <!-- ===== Identity Proof (same block as the customer master) ===== -->
        <?php $this->load->view('customers/components/identity_proof', array(
            'identity_types' => $identity_types,
            'identities'     => $identities,
        )); ?>

        <!-- ===== Status ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Status</div>
            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>Booking Status</label>
                    <?php if ($lock_status): ?>
                        <input type="hidden" name="status_id" value="<?= html_escape($sel_status) ?>">
                    <?php endif; ?>
                    <select class="erp-select" <?= $lock_status ? 'disabled' : 'name="status_id"' ?>>
                        <?php foreach ($status_opts as $s): ?>
                            <?php if ($s->status_code === 'checked_out') { continue; } ?>
                            <option value="<?= (int) $s->status_id ?>" <?= (string) $sel_status === (string) $s->status_id ? 'selected' : '' ?>><?= html_escape($s->status_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= form_error('status_id', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field"></div>
            </div>
            <div class="erp-grid-2" style="margin-top:16px;">
                <div class="erp-form-field">
                    <label>Scheduled Check-In <span class="req">*</span></label>
                    <input class="erp-input" type="date" id="bk_checkin" name="scheduled_check_in_date" required value="<?= html_escape(set_value('scheduled_check_in_date', $scheduled_check_in)) ?>">
                    <?= form_error('scheduled_check_in_date', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Scheduled Check-Out <span class="req">*</span> <span class="erp-muted" style="font-weight:400;">(room available this day)</span></label>
                    <input class="erp-input" type="date" id="bk_checkout" name="scheduled_check_out_date" required value="<?= html_escape(set_value('scheduled_check_out_date', $scheduled_check_out)) ?>">
                    <?= form_error('scheduled_check_out_date', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>
            <div class="erp-grid-2" style="margin-top:16px;">
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
                    <label>Allot Room <span class="erp-muted" style="font-weight:400;">(available for selected dates)</span></label>
                    <select class="erp-select" id="bk_room" name="room_id" data-search="always" data-placeholder="Select a room">
                        <option value="">— No room —</option>
                        <?php foreach ($room_opts as $rm): ?>
                            <option value="<?= (int) $rm->id ?>" data-category-id="<?= (int) $rm->category_id ?>" <?= (string) $sel_room === (string) $rm->id ? 'selected' : '' ?>><?= html_escape($rm->room_no) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= form_error('room_id', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>
        </div>

        <!-- Footer actions -->
        <div class="erp-form-foot">
            <a href="<?= site_url($back_url) ?>" class="erp-btn erp-btn-ghost">Cancel</a>
            <button type="submit" class="erp-btn erp-btn-primary"><?= html_escape($submit_label) ?></button>
        </div>
    </form>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/identity-rows.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/identity-rows.js') ?>"></script>
<script src="<?= base_url('assets/js/booking-form.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/booking-form.js') ?>"></script>
</body>
</html>
