<?php
$editing = $user && ! empty($user->id);
$page_title = $editing ? 'Edit user' : 'Create user';
$back = site_url('users');
$this->load->view('access/management_head', array('page_title' => $page_title, 'back' => $back));
?>
<form class="erp-card mgmt-card" style="max-width:920px;margin:0 auto" method="post" action="<?= site_url('users/save') ?>" novalidate>

    <!-- Page header -->
    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <?= $editing ? 'Edit user' : 'Create user' ?>
            </h1>
            <p class="erp-sub">A user belongs to one admin account and may be assigned one, many, or no properties</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="erp-alert erp-alert-danger"><ul><?php foreach ($errors as $error): ?><li><?= html_escape($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><input type="hidden" name="id" value="<?= $editing ? (int) $user->id : 0 ?>">

    <?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?>
        <div class="erp-form-section">
            <div class="erp-section-title">Account</div>
            <div class="erp-form-field">
                <label>Admin Account <span class="req">*</span></label>
                <?php if ($editing): ?><input type="hidden" name="tenant_id" value="<?= (int) $tenant_id ?>"><input class="erp-input" disabled value="<?= html_escape($user->tenant_name.' — '.$user->admin_name) ?>">
                <?php else: ?><select class="erp-select" id="tenant_id" name="tenant_id" required onchange="if(this.value){window.location='<?= site_url('users/add') ?>?tenant_id='+encodeURIComponent(this.value)}"><option value="">Choose admin account</option><?php foreach ($admin_tenants as $tenant): ?><option value="<?= (int) $tenant->id ?>" <?= (int) $tenant_id === (int) $tenant->id ? 'selected' : '' ?>><?= html_escape($tenant->name.' — '.$tenant->admin_name.' ('.$tenant->admin_mobile.')') ?></option><?php endforeach; ?></select><div class="mgmt-help">Selecting an account reloads its active-property choices.</div><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="erp-form-section">
        <div class="erp-section-title">User Details</div>
        <div class="erp-grid-2" style="margin-bottom:16px">
            <div class="erp-form-field">
                <label>User Name <span class="req">*</span></label>
                <input class="erp-input" id="user_name" name="name" maxlength="150" required value="<?= html_escape($user ? $user->name : '') ?>">
            </div>
            <div class="erp-form-field">
                <label>Mobile Number <span class="req">*</span></label>
                <input class="erp-input" id="user_mobile" name="mobile_no" inputmode="numeric" maxlength="15" required value="<?= html_escape($user ? $user->mobile_no : '') ?>">
            </div>
        </div>
        <div class="erp-form-field">
            <label>Property Assignments</label>
            <?php if (empty($properties)): ?><div class="erp-alert erp-alert-warning" style="margin:8px 0 0">This admin has no active properties. The user can still be saved and will see the no-property access page after login.</div>
            <?php else: ?><div class="mgmt-checkbox-list"><?php foreach ($properties as $property): ?><label class="mgmt-checkbox"><input type="checkbox" name="property_ids[]" value="<?= (int) $property->id ?>" <?= in_array((int) $property->id, array_map('intval', $selected_ids), TRUE) ? 'checked' : '' ?>><?= html_escape($property->property_name) ?> <span class="mgmt-help">(<?= html_escape($property->property_code) ?>)</span></label><?php endforeach; ?></div><?php endif; ?>
        </div>
        <label class="erp-check" style="margin-top:16px"><input type="checkbox" id="user_active" name="is_active" value="1" <?= ! $user || (int) $user->is_active === 1 ? 'checked' : '' ?>> Active user</label>
    </div>

    <div class="erp-form-foot">
        <a class="erp-btn erp-btn-ghost" href="<?= site_url('users') ?>">Cancel</a>
        <button class="erp-btn erp-btn-primary" type="submit"><?= $editing ? 'Update' : 'Save' ?> User</button>
    </div>
</form>
<?php $this->load->view('access/management_foot'); ?>
