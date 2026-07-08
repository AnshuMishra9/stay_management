<?php
/**
 * Add / Edit Room form.
 * $room          -> row object when editing, NULL when adding
 * $next_code     -> room code to display (existing or next generated)
 * $categories    -> active room_categories (dropdown)
 * $taxes         -> active taxes (dropdown)
 * $amenities     -> active amenities (checkbox grid)
 * $selected_amen -> array of amenity ids already linked to this room
 * $bed_opts / $unit_opts / $view_opts / $hk_opts / $cond_opts -> option arrays
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

$sel_cat   = $val('category_id');
$sel_bed   = $val('bed_type');
$sel_unit  = $val('room_size_unit', 'sq.ft');
$sel_view  = $val('window_view');
$sel_tax   = $val('tax_id');
$sel_hk    = $val('housekeeping_status', 'Clean');
$sel_cond  = $val('room_condition', 'Good');
$active    = $posted ? ($this->input->post('is_active') ? 1 : 0) : ($room ? (int) $room->is_active : 1);
$sel_amen  = array_map('intval', (array) $selected_amen);
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
    <form class="erp-card" style="max-width:980px;margin:0 auto;" action="<?= site_url('rooms/save') ?>" method="post" enctype="multipart/form-data" novalidate
          onsubmit="var b=document.getElementById('roomSaveBtn'); if(b){b.disabled=true; b.classList.add('is-loading');}">
        <input type="hidden" name="id" value="<?= $is_edit ? (int) $room->id : '' ?>">

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

        <!-- ===== Section 1: Basic Information ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Basic Information</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room ID</label>
                    <input class="erp-input" type="text" value="<?= html_escape($next_code) ?>" readonly>
                </div>
                <div class="erp-form-field">
                    <label>Room Number <span class="req">*</span></label>
                    <input class="erp-input" type="text" name="room_no" required maxlength="30" value="<?= html_escape($val('room_no')) ?>" placeholder="e.g. 101">
                    <?= form_error('room_no', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room Name</label>
                    <input class="erp-input" type="text" name="room_name" maxlength="150" value="<?= html_escape($val('room_name')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Category <span class="req">*</span></label>
                    <select class="erp-select" name="category_id" required>
                        <option value="">Select</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c->category_id ?>" <?= (string) $sel_cat === (string) $c->category_id ? 'selected' : '' ?>><?= html_escape($c->category_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= form_error('category_id', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Floor</label>
                    <input class="erp-input" type="text" name="floor_no" maxlength="20" value="<?= html_escape($val('floor_no')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Wing</label>
                    <input class="erp-input" type="text" name="wing" maxlength="40" value="<?= html_escape($val('wing')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Room Size</label>
                    <input class="erp-input" type="text" name="room_size" maxlength="30" value="<?= html_escape($val('room_size')) ?>" placeholder="e.g. 250">
                </div>
                <div class="erp-form-field">
                    <label>Unit</label>
                    <select class="erp-select" name="room_size_unit">
                        <?php foreach ($unit_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_unit === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

        <!-- ===== Section 2: Occupancy & Configuration ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Occupancy &amp; Configuration</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Max Adults</label>
                    <input class="erp-input" type="number" min="0" name="max_adults" value="<?= html_escape($val('max_adults')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Max Children</label>
                    <input class="erp-input" type="number" min="0" name="max_children" value="<?= html_escape($val('max_children')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Bed Type</label>
                    <select class="erp-select" name="bed_type">
                        <option value="">Select</option>
                        <?php foreach ($bed_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_bed === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>Bed Count</label>
                    <input class="erp-input" type="number" min="0" name="bed_count" value="<?= html_escape($val('bed_count')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Bed Size</label>
                    <input class="erp-input" type="text" name="bed_size" maxlength="40" value="<?= html_escape($val('bed_size')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Window View</label>
                    <select class="erp-select" name="window_view">
                        <option value="">Select</option>
                        <?php foreach ($view_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_view === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Connected Room</label>
                    <input class="erp-input" type="text" name="connected_room" maxlength="60" value="<?= html_escape($val('connected_room')) ?>" placeholder="e.g. 202">
                </div>
                <div class="erp-form-field"></div>
            </div>

            <div class="erp-check-row">
                <label class="erp-check"><input type="checkbox" name="extra_bed_allowed" value="1" <?= $chk('extra_bed_allowed') ? 'checked' : '' ?>> Extra Bed Allowed</label>
                <label class="erp-check"><input type="checkbox" name="accessible_room" value="1" <?= $chk('accessible_room') ? 'checked' : '' ?>> Accessible Room</label>
                <label class="erp-check"><input type="checkbox" name="balcony" value="1" <?= $chk('balcony') ? 'checked' : '' ?>> Balcony</label>
                <label class="erp-check"><input type="checkbox" name="smoking" value="1" <?= $chk('smoking') ? 'checked' : '' ?>> Smoking</label>
            </div>
        </div>

        <!-- ===== Section 3: Amenities ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Amenities</div>
            <div class="erp-amenity-grid">
                <?php foreach ($amenities as $a): ?>
                    <?php $is_img = (bool) preg_match('/\.(svg|png|jpe?g|webp|gif)$/i', (string) $a->icon); ?>
                    <label class="erp-amenity-item">
                        <input type="checkbox" name="amenities[]" value="<?= (int) $a->amenity_id ?>" <?= in_array((int) $a->amenity_id, $sel_amen, TRUE) ? 'checked' : '' ?>>
                        <span class="erp-amenity-face">
                            <span class="erp-amenity-icon">
                                <?php if ($is_img): ?>
                                    <img class="erp-amenity-img" src="<?= base_url('assets/icons/'.rawurlencode($a->icon)) ?>" alt="<?= html_escape($a->amenity_name) ?>" loading="lazy">
                                <?php else: ?>
                                    <?= html_escape($a->icon) ?>
                                <?php endif; ?>
                            </span>
                            <span class="erp-amenity-name"><?= html_escape($a->amenity_name) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== Section 4: Pricing ===== -->
        <div class="erp-form-section">
            <div class="erp-section-title">Pricing</div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Base Price</label>
                    <input class="erp-input" type="number" step="0.01" min="0" name="base_price" value="<?= html_escape($val('base_price')) ?>">
                    <?= form_error('base_price', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Selling Price</label>
                    <input class="erp-input" type="number" step="0.01" min="0" name="selling_price" value="<?= html_escape($val('selling_price')) ?>">
                    <?= form_error('selling_price', '<div class="erp-error">', '</div>') ?>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Tax</label>
                    <select class="erp-select" name="tax_id">
                        <option value="">Select</option>
                        <?php foreach ($taxes as $t): ?>
                            <option value="<?= (int) $t->tax_id ?>" <?= (string) $sel_tax === (string) $t->tax_id ? 'selected' : '' ?>><?= html_escape($t->tax_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field">
                    <label>SAC Code</label>
                    <input class="erp-input" type="text" name="sac_code" maxlength="20" value="<?= html_escape($val('sac_code')) ?>">
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Extra Person Charge</label>
                    <input class="erp-input" type="number" step="0.01" min="0" name="extra_person_charge" value="<?= html_escape($val('extra_person_charge')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Child Charge</label>
                    <input class="erp-input" type="number" step="0.01" min="0" name="child_charge" value="<?= html_escape($val('child_charge')) ?>">
                </div>
            </div>

            <div class="erp-grid-2">
                <div class="erp-form-field">
                    <label>Effective From</label>
                    <input class="erp-input" type="date" name="effective_from" value="<?= html_escape($val('effective_from')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Effective To</label>
                    <input class="erp-input" type="date" name="effective_to" value="<?= html_escape($val('effective_to')) ?>">
                </div>
            </div>
        </div>

        <!-- ===== Section 5: Housekeeping & Status ===== -->
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
                <div class="erp-form-field">
                    <label>Room Condition</label>
                    <select class="erp-select" name="room_condition">
                        <?php foreach ($cond_opts as $opt): ?>
                            <option value="<?= html_escape($opt) ?>" <?= $sel_cond === $opt ? 'selected' : '' ?>><?= html_escape($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="erp-grid-2" style="margin-bottom:16px;">
                <div class="erp-form-field">
                    <label>Phone Extension</label>
                    <input class="erp-input" type="text" name="room_phone" maxlength="20" value="<?= html_escape($val('room_phone')) ?>">
                </div>
                <div class="erp-form-field">
                    <label>Room Image <span class="erp-muted" style="font-weight:400;">(jpg/png/pdf, max 4MB)</span></label>
                    <input class="erp-file" type="file" name="room_image" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($is_edit && ! empty($room->image_path)): ?>
                        <div class="erp-existing-file">
                            <a class="erp-doc-link" href="<?= site_url('rooms/file/'.$room->id) ?>" target="_blank">Current image — view</a>
                            <span class="erp-muted"> · upload a new file to replace</span>
                        </div>
                    <?php endif; ?>
                </div>
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

        <!-- ===== Section 6: Audit (read-only, edit only) ===== -->
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
