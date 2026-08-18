<?php
/**
 * Shared presentation for:
 * - Check-in Details -> Check-out confirmation
 * - Check-out Details -> completed record
 * - Check-out Details -> read-only edit page
 *
 * Booking/customer fields remain disabled. On the confirmation page only the
 * status can be changed from Checked In to Checked Out.
 */
$display = function ($value, $fallback = '—') {
    return ($value === NULL || $value === '') ? $fallback : $value;
};
$date_display = function ($value) use ($display) {
    if ( ! $value) {
        return $display(NULL);
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y', $timestamp) : $display($value);
};
$datetime_display = function ($value) use ($display) {
    if ( ! $value) {
        return $display(NULL);
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y, h:i A', $timestamp) : $display($value);
};
$checkout_datetime = $booking->checked_out_at;
if ( ! $checkout_datetime && $booking->scheduled_check_out_date) {
    $checkout_datetime = substr($booking->scheduled_check_out_date, 0, 10).' 11:00:00';
}
$scheduled_check_in_day = $booking->scheduled_check_in_date
    ? date('Y-m-d', strtotime($booking->scheduled_check_in_date))
    : NULL;
$actual_check_in_day = $booking->checked_in_at
    ? date('Y-m-d', strtotime($booking->checked_in_at))
    : NULL;
$show_scheduled_dates = $scheduled_check_in_day
    && $actual_check_in_day
    && $scheduled_check_in_day !== $actual_check_in_day;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= html_escape($page_title) ?> <?= html_escape($booking->booking_number) ?> &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <style>
        .checkout-readonly fieldset { border:0; margin:0; padding:0; min-width:0; }
        .checkout-readonly .erp-input:disabled {
            background:#f7f8fc;
            border-color:#dde1ec;
            color:var(--text);
            cursor:not-allowed;
            opacity:1;
        }
        .checkout-readonly .readonly-note {
            display:flex;
            align-items:center;
            gap:8px;
            color:#5b6478;
            font-size:.86rem;
        }
        .checkout-readonly .identity-doc { margin-top:7px; }
    </style>
</head>
<body class="erp-body">

<?php $this->load->view('layouts/erp_navbar', array(
    'active' => $active_nav,
    'back'   => site_url($back_url),
)); ?>

<div class="erp-wrap">
    <?php if ($confirm): ?>
        <form class="erp-card checkout-readonly" style="max-width:920px;margin:0 auto;" action="<?= site_url('customers/checkout_save') ?>" method="post">
            <input type="hidden" name="booking_id" value="<?= (int) $booking->id ?>">
    <?php else: ?>
        <div class="erp-card checkout-readonly" style="max-width:920px;margin:0 auto;">
    <?php endif; ?>

        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="M14 17l5-5-5-5"/><path d="M19 12H7"/></svg>
                    <?= html_escape($page_title) ?>
                </h1>
                <p class="erp-sub"><?= html_escape($page_subtitle) ?></p>
            </div>
            <div class="erp-head-total"><?= html_escape($booking->booking_number) ?></div>
        </div>

        <?php if ( ! $confirm): ?>
            <div class="erp-alert erp-alert-success">
                This check-out is complete. The information below is read only and cannot be changed.
            </div>
        <?php endif; ?>

        <fieldset disabled>
            <div class="erp-form-section">
                <div class="erp-section-title">Customer</div>
                <div class="erp-grid-2" style="margin-bottom:16px;">
                    <div class="erp-form-field">
                        <label>Customer Name</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->customer_name)) ?>">
                    </div>
                    <div class="erp-form-field">
                        <label>Mobile No</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->phone)) ?>">
                    </div>
                </div>
                <div class="erp-grid-2">
                    <div class="erp-form-field">
                        <label>Customer ID</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->customer_code)) ?>">
                    </div>
                    <div class="erp-form-field">
                        <label>Country / Pincode</label>
                        <input class="erp-input" type="text" value="<?= html_escape(trim($display($booking->country, '').' '.$display($booking->pincode, '')) ?: '—') ?>">
                    </div>
                </div>
            </div>
        </fieldset>

            <div class="erp-form-section">
                <div class="erp-section-title">Stay Details</div>
                <div class="erp-grid-2" style="margin-bottom:16px;">
                    <div class="erp-form-field">
                        <label>Booking No</label>
                        <input class="erp-input" type="text" value="<?= html_escape($booking->booking_number) ?>" disabled>
                    </div>
                    <div class="erp-form-field">
                        <label>Status</label>
                        <?php if ($confirm): ?>
                            <select class="erp-select" name="status_id" data-search="never">
                                <?php foreach ($status_opts as $status): ?>
                                    <?php if ( ! in_array($status->status_code, array('checked_in', 'checked_out'), TRUE)) { continue; } ?>
                                    <option value="<?= (int) $status->status_id ?>" <?= $status->status_code === $booking->status_code ? 'selected' : '' ?>><?= html_escape($status->status_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input class="erp-input" type="text" value="<?= html_escape($booking->status_name) ?>" disabled>
                        <?php endif; ?>
                    </div>
                </div>
                <fieldset disabled>
                <div class="erp-grid-2" style="margin-bottom:16px;">
                    <div class="erp-form-field">
                        <label>Room No</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->allotted_room_no)) ?>">
                    </div>
                    <div class="erp-form-field">
                        <label>Room Category</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->room_category)) ?>">
                    </div>
                </div>
                <?php if ($show_scheduled_dates): ?>
                    <div class="erp-grid-2" style="margin-bottom:16px;">
                        <div class="erp-form-field">
                            <label>Scheduled Check-In</label>
                            <input class="erp-input" type="text" value="<?= html_escape($date_display($booking->scheduled_check_in_date)) ?>">
                        </div>
                        <div class="erp-form-field">
                            <label>Scheduled Check-Out</label>
                            <input class="erp-input" type="text" value="<?= html_escape($date_display($booking->scheduled_check_out_date)) ?>">
                        </div>
                    </div>
                <?php endif; ?>
                <div class="erp-grid-2" style="margin-bottom:16px;">
                    <div class="erp-form-field">
                        <label>Check-In</label>
                        <input class="erp-input" type="text" value="<?= html_escape($datetime_display($booking->checked_in_at)) ?>">
                    </div>
                    <div class="erp-form-field">
                        <label>Check-Out</label>
                        <input class="erp-input" type="text" value="<?= html_escape($datetime_display($checkout_datetime)) ?>">
                    </div>
                </div>
                <div class="erp-grid-2">
                    <div class="erp-form-field">
                        <label>Total Guests</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->total_guest)) ?>">
                    </div>
                    <div class="erp-form-field">
                        <label>Length of Stay</label>
                        <input class="erp-input" type="text" value="<?= html_escape($display($booking->length_of_stay)) ?>">
                    </div>
                </div>
                </fieldset>
            </div>

        <fieldset disabled>
            <div class="erp-form-section">
                <div class="erp-section-title">Amounts</div>
                <div class="erp-grid-2">
                    <div class="erp-form-field">
                        <label>Total Amount</label>
                        <input class="erp-input" type="text" value="<?= html_escape($booking->total_amount !== NULL ? number_format((float) $booking->total_amount, 2) : '—') ?>">
                    </div>
                    <div class="erp-form-field">
                        <label>Amount Paid / Remaining</label>
                        <input class="erp-input" type="text" value="<?= html_escape(
                            ($booking->amount_paid !== NULL ? number_format((float) $booking->amount_paid, 2) : '—')
                            .' / '.
                            ($booking->remaining_amount !== NULL ? number_format((float) $booking->remaining_amount, 2) : '—')
                        ) ?>">
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="erp-form-section">
            <div class="erp-section-title">Identity Proof</div>
            <div class="erp-detail-grid">
                <?php foreach ($identities as $identity): ?>
                    <div class="erp-detail-item">
                        <div class="k"><?= html_escape($identity->type_label) ?></div>
                        <div class="v"><?= html_escape($display($identity->identity_number)) ?></div>
                        <?php if ($identity->document_url || $identity->document_url_2): ?>
                            <div class="identity-doc">
                                <?php if ($identity->document_url): ?>
                                    <a class="erp-doc-link" href="<?= html_escape($identity->document_url) ?>" target="_blank" rel="noopener">View Front / File</a>
                                <?php endif; ?>
                                <?php if ($identity->document_url_2): ?>
                                    <a class="erp-doc-link" href="<?= html_escape($identity->document_url_2) ?>" target="_blank" rel="noopener">View Back</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($identities)): ?>
                    <div class="erp-muted">No identity proof added.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="erp-form-foot">
            <a href="<?= site_url($back_url) ?>" class="erp-btn erp-btn-ghost">Back</a>
            <?php if ($confirm): ?>
                <span class="readonly-note">Only status can be changed on this page.</span>
                <button type="submit" class="erp-btn erp-btn-primary">Confirm Check-out</button>
            <?php endif; ?>
        </div>

    <?php if ($confirm): ?>
        </form>
    <?php else: ?>
        </div>
    <?php endif; ?>
</div>
<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
</body>
</html>
