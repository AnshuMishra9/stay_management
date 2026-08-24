<?php
$editing = $property && ! empty($property->id);
$page_title = $editing ? 'Edit property' : 'Create property';
$back = site_url('properties');
$this->load->view('access/management_head', array('page_title' => $page_title, 'back' => $back));
?>
<form class="erp-card mgmt-card" style="max-width:920px;margin:0 auto" method="post" action="<?= site_url('properties/save') ?>" novalidate>

    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/>
                </svg>
                <?= $editing ? 'Edit property' : 'Create property' ?>
            </h1>
            <p class="erp-sub">Ownership is fixed after creation; property codes are unique inside an account</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="erp-alert erp-alert-danger"><ul><?php foreach ($errors as $error): ?><li><?= html_escape($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><input type="hidden" name="id" value="<?= $editing ? (int) $property->id : 0 ?>">

    <?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?>
        <div class="erp-form-section">
            <div class="erp-section-title">Ownership</div>
            <div class="erp-form-field">
                <label>Plant <span class="req">*</span></label>
                <?php if ($editing): ?>
                    <input type="hidden" name="tenant_id" value="<?= (int) $property->tenant_id ?>"><input class="erp-input" disabled value="<?= html_escape($property->tenant_name.' &mdash; '.$property->admin_name) ?>">
                <?php else: ?>
                    <select class="erp-select" id="tenant_id" name="tenant_id" required>
                        <option value="">Choose plant</option>
                        <?php foreach ($tenants as $tenant): ?><option value="<?= (int) $tenant->id ?>" <?= $property && (int) $property->tenant_id === (int) $tenant->id ? 'selected' : '' ?>><?= html_escape($tenant->name.' &mdash; '.$tenant->admin_name.' ('.$tenant->admin_mobile.')') ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="erp-form-section">
        <div class="erp-section-title">Property Details</div>
        <div class="erp-grid-2" style="margin-bottom:16px">
            <div class="erp-form-field">
                <label>Property Name <span class="req">*</span></label>
                <input class="erp-input" id="property_name" name="property_name" maxlength="150" required value="<?= html_escape($property ? $property->property_name : '') ?>">
            </div>
            <div class="erp-form-field">
                <label>Property Code <span class="req">*</span></label>
                <input class="erp-input" id="property_code" name="property_code" maxlength="30" required style="text-transform:uppercase" value="<?= html_escape($property ? $property->property_code : '') ?>">
                <div class="mgmt-help">Letters, numbers, _ and -</div>
            </div>
        </div>
        <label class="erp-check"><input type="checkbox" id="property_active" name="is_active" value="1" <?= ! $property || (int) $property->is_active === 1 ? 'checked' : '' ?>> Active property</label>
    </div>

    <div class="erp-form-foot">
        <a class="erp-btn erp-btn-ghost" href="<?= site_url('properties') ?>">Cancel</a>
        <button class="erp-btn erp-btn-primary" type="submit"><?= $editing ? 'Update' : 'Save' ?> Property</button>
    </div>
</form>
<?php $this->load->view('access/management_foot'); ?>
