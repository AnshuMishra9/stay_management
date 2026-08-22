<?php
/**
 * Add / Edit Room form (simplified master).
 * $room       -> row object when editing, NULL when adding
 * $next_code  -> room code to display (existing or next generated)
 * $categories -> active room_categories (optional dropdown)
 * $hk_opts    -> housekeeping status options (Available / Not Available)
 */
$is_edit = ($room !== NULL);

// Helper: current value for a field (posted value wins on validation failure).
// Return RAW (html_escape=FALSE); the template escapes once at output.
$val = function ($field, $fallback = '') use ($room) {
    return set_value($field, $room ? ($room->$field ?? '') : $fallback, FALSE);
};
// Checkbox helper (posted wins on re-render, else DB value, else default).
$posted = ($this->input->server('REQUEST_METHOD') === 'POST');
$chk = function ($field, $default = 0) use ($room, $posted) {
    if ($posted) { return $this->input->post($field) ? 1 : 0; }
    return $room ? (int) $room->$field : $default;
};

$sel_cat = $val('category_id');
$sel_hk  = $val('housekeeping_status', 'Available');
$active  = $posted ? ($this->input->post('is_active') ? 1 : 0) : ($room ? (int) $room->is_active : 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Room &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/rooms.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/rooms.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>">
</head>
<body class="erp-body">

<!-- Top navigation (with mobile Back button) -->
<?php $this->load->view('layouts/erp_navbar', array('active' => 'rooms', 'back' => site_url('rooms'))); ?>

<div class="erp-wrap">
    <form class="erp-card" style="max-width:980px;margin:0 auto;" action="<?= site_url('rooms/save') ?>" method="post" novalidate
          onsubmit="var b=document.getElementById('roomSaveBtn'); if(b){b.disabled=true; b.classList.add('is-loading');}">
        <input type="hidden" name="id" value="<?= $is_edit ? (int) $room->id : '' ?>">
        <input type="hidden" name="property_context_token" value="<?= html_escape($property_context_token) ?>">

        <!-- Header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 7h1M9 11h1M14 7h1M14 11h1M10 21v-4h4v4"/></svg>
                    <?= $is_edit ? 'Edit Room' : 'Add New Room' ?>
                </h1>
                <p class="erp-sub"><?= $is_edit ? 'Update room details' : 'Enter room details' ?></p>
            </div>
        </div>

        <?php if (validation_errors()): ?>
            <div class="erp-alert erp-alert-danger"><?= validation_errors() ?></div>
        <?php endif; ?>

        <!-- ===== Basic Information ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Basic Information</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room ID</label>
                    <input class="erp-input" type="text" value="<?= html_escape($next_code) ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Room Name / Number <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="room_no" required maxlength="30" value="<?= html_escape($val('room_no')) ?>" placeholder="e.g. 101 / Deluxe Suite">
                    <?= form_error('room_no', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Category <span class="erp-muted" style="font-weight:400;">(optional)</span></label>
                    <select class="erp-select" name="category_id">
                        <option value="">No category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c->category_id ?>" <?= (string) $sel_cat === (string) $c->category_id ? 'selected' : '' ?>><?= html_escape($c->category_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= form_error('category_id', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Floor</label>
                    <input class="erp-input" type="text" name="floor_no" maxlength="20" value="<?= html_escape($val('floor_no')) ?>">
                </div>
            </div>

            <div class="erp-check-row" style="margin-bottom:16px;">
                <label class="erp-check"><input type="checkbox" name="extra_bed_allowed" value="1" <?= $chk('extra_bed_allowed') ? 'checked' : '' ?>> Extra Bed Allowed</label>
            </div>

            <div class="erp-grid-1" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Description</label>
                    <textarea class="erp-textarea" name="description" maxlength="500"><?= html_escape($val('description')) ?></textarea>
                </div>
            </div>

            <div class="erp-grid-1">
                <div class="erp-form-field">
                    <label>Remarks</label>
                    <textarea class="erp-textarea" name="remarks" maxlength="500"><?= html_escape($val('remarks')) ?></textarea>
                </div>
            </div>
        </div>

        <!-- ===== Pricing ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Pricing</div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>Price</label>
                    <input class="erp-input" type="number" step="0.01" min="0" name="selling_price" value="<?= html_escape($val('selling_price')) ?>">
                    <?= form_error('selling_price', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field"></div>
            </div>
        </div>

        <!-- ===== Housekeeping & Status ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Housekeeping &amp; Status</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Housekeeping Status</label>
                    <select class="erp-select" name="housekeeping_status">
                        <?php foreach ($hk_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_hk === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field"></div>
            </div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label class="erp-check" style="margin:0;">
                        <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
                        Operational (Active) room
                    </label>
                </div>
            </div>
        </div>

        <!-- ===== Audit (read-only, edit only) ===== -->
        <?php if ($is_edit): ?>
        <div class="erp-form-section">
            <div class="erp-section-title">Audit</div>
            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Created By</label>
                    <input class="erp-input" type="text" value="<?= html_escape($room->created_by ?: '—') ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Created Date</label>
                    <input class="erp-input" type="text" value="<?= html_escape($room->created_at ?: '—') ?>" readonly>
                </div>
            </div>
            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>Updated By</label>
                    <input class="erp-input" type="text" value="<?= html_escape($room->updated_by ?: '—') ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Updated Date</label>
                    <input class="erp-input" type="text" value="<?= html_escape($room->updated_at ?: '—') ?>" readonly>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer actions -->
        <div class="erp-form-foot">
            <a href="<?= site_url('rooms') ?>" class="erp-btn erp-btn-ghost">Cancel</a>
            <button type="submit" id="roomSaveBtn" class="erp-btn erp-btn-primary">
                <?= $is_edit ? 'Update Room' : 'Save Room' ?>
            </button>
        </div>
    </form>
</div>

<script src="<?= base_url('assets/js/searchable-select.js') ?>"></script>
</body>
</html>
