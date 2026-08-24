<?php
$is_edit = $category !== NULL;
$posted = $this->input->server('REQUEST_METHOD') === 'POST';
// Preserve submitted values after validation; escape only when rendering.
$value = function ($field, $fallback = '') use ($category) {
    return set_value($field, $category && isset($category->$field) ? $category->$field : $fallback, FALSE);
};
$checked = function ($field, $default = 0) use ($category, $posted) {
    if ($posted) { return $this->input->post($field) ? 1 : 0; }
    return $category ? (int) $category->$field : $default;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit' : 'Add' ?> Room Category &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
</head>
<body class="erp-body">
<?php $this->load->view('layouts/erp_navbar', array('active' => 'rooms', 'back' => site_url('room-categories'))); ?>

<div class="erp-wrap">
    <form class="erp-card" style="max-width:920px;margin:0 auto" action="<?= site_url('room-categories/save') ?>" method="post" novalidate>
        <input type="hidden" name="category_id" value="<?= $is_edit ? (int) $category->category_id : '' ?>">
        <input type="hidden" name="property_context_token" value="<?= html_escape($property_context_token) ?>">

        <div class="erp-page-head">
            <div>
                <h1><?= $is_edit ? 'Edit' : 'Add' ?> Room Category</h1>
                <p class="erp-sub"><?= html_escape($current_property->property_name) ?></p>
            </div>
        </div>
        <?php if (validation_errors()): ?>
            <div class="erp-alert erp-alert-danger"><?= validation_errors() ?></div>
        <?php endif; ?>

        <div class="erp-form-section">
            <div class="erp-section-title">Category Details</div>
            <div class="erp-grid-2" style="margin-bottom:16px">
                <div class="erp-form-field">
                    <label>Category Name <span class="req">*</span></label>
                    <input class="erp-input" name="category_name" maxlength="100" required value="<?= html_escape($value('category_name')) ?>">
                    <?= form_error('category_name', '<div class="erp-error">', '</div>') ?>
                </div>
                <div class="erp-form-field">
                    <label>Short Code</label>
                    <input class="erp-input" name="short_code" maxlength="20" value="<?= html_escape($value('short_code')) ?>">
                </div>
            </div>
            <div class="erp-form-field" style="margin-bottom:16px">
                <label>Description</label>
                <textarea class="erp-textarea" name="description" maxlength="255"><?= html_escape($value('description')) ?></textarea>
            </div>
            <div class="erp-grid-2" style="margin-bottom:16px">
                <div class="erp-form-field"><label>Max Adults</label><input class="erp-input" type="number" min="0" name="max_adults" value="<?= html_escape($value('max_adults')) ?>"></div>
                <div class="erp-form-field"><label>Max Children</label><input class="erp-input" type="number" min="0" name="max_children" value="<?= html_escape($value('max_children')) ?>"></div>
            </div>
            <div class="erp-grid-2" style="margin-bottom:16px">
                <div class="erp-form-field"><label>Room Size</label><input class="erp-input" name="room_size" maxlength="30" value="<?= html_escape($value('room_size')) ?>"></div>
                <div class="erp-form-field"><label>Size Unit</label><input class="erp-input" name="room_size_unit" maxlength="15" value="<?= html_escape($value('room_size_unit', 'sq.ft')) ?>"></div>
            </div>
            <div class="erp-grid-2" style="margin-bottom:16px">
                <div class="erp-form-field"><label>Bed Type</label><input class="erp-input" name="bed_type" maxlength="40" value="<?= html_escape($value('bed_type')) ?>"></div>
                <div class="erp-form-field"><label>Bed Count</label><input class="erp-input" type="number" min="0" name="bed_count" value="<?= html_escape($value('bed_count')) ?>"></div>
            </div>
            <div class="erp-grid-2" style="margin-bottom:16px">
                <div class="erp-form-field">
                    <label>Default Tax</label>
                    <select class="erp-select" name="default_tax_id">
                        <option value="">Select</option>
                        <?php foreach ($taxes as $tax): ?>
                            <option value="<?= (int) $tax->tax_id ?>" <?= (string) $value('default_tax_id') === (string) $tax->tax_id ? 'selected' : '' ?>><?= html_escape($tax->tax_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-form-field"><label>SAC Code</label><input class="erp-input" name="default_sac_code" maxlength="20" value="<?= html_escape($value('default_sac_code')) ?>"></div>
            </div>
            <div class="erp-grid-2">
                <div class="erp-form-field"><label>Display Order</label><input class="erp-input" type="number" min="0" name="display_order" value="<?= html_escape($value('display_order', 0)) ?>"></div>
                <div class="erp-check-row">
                    <label class="erp-check"><input type="checkbox" name="smoking_allowed" value="1" <?= $checked('smoking_allowed') ? 'checked' : '' ?>> Smoking Allowed</label>
                    <label class="erp-check"><input type="checkbox" name="status" value="1" <?= $checked('status', 1) ? 'checked' : '' ?>> Active</label>
                </div>
            </div>
        </div>
        <div class="erp-form-foot">
            <a class="erp-btn erp-btn-ghost" href="<?= site_url('room-categories') ?>">Cancel</a>
            <button class="erp-btn erp-btn-primary" type="submit"><?= $is_edit ? 'Update' : 'Save' ?> Category</button>
        </div>
    </form>
</div>
</body>
</html>
