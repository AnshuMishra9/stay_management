<?php
$editing = $admin && ! empty($admin->id);
$page_title = $editing ? 'Edit admin' : 'Create admin';
$back = site_url('admins');
$this->load->view('access/management_head', array('page_title' => $page_title, 'back' => $back));
?>
<form class="erp-card mgmt-card" style="max-width:920px;margin:0 auto" method="post" action="<?= site_url('admins/save') ?>" novalidate>

    <!-- Page header -->
    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M12 13c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5z"/>
                </svg>
                <?= $editing ? 'Edit admin' : 'Create admin' ?>
            </h1>
            <p class="erp-sub">The mobile number uses the existing OTP login flow</p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="erp-alert erp-alert-danger"><ul><?php foreach ($errors as $error): ?><li><?= html_escape($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="erp-form-section">
        <div class="erp-section-title">Admin Details</div>
        <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>">
        <input type="hidden" name="id" value="<?= $editing ? (int) $admin->id : 0 ?>">
        <div class="erp-grid-2" style="margin-bottom:16px">
            <div class="erp-form-field">
                <label>Admin Name <span class="req">*</span></label>
                <input class="erp-input" id="admin_name" name="name" maxlength="150" required value="<?= html_escape($admin ? $admin->name : '') ?>">
            </div>
            <div class="erp-form-field">
                <label>Mobile Number <span class="req">*</span></label>
                <input class="erp-input" id="admin_mobile" name="mobile_no" inputmode="numeric" maxlength="15" required value="<?= html_escape($admin ? $admin->mobile_no : '') ?>">
            </div>
        </div>
        <div class="erp-form-field">
            <label>Account / Organization Name <span class="req">*</span></label>
            <input class="erp-input" id="tenant_name" name="tenant_name" maxlength="150" required value="<?= html_escape($admin ? $admin->tenant_name : '') ?>">
            <div class="mgmt-help">All properties and users under this admin are isolated inside this account.</div>
        </div>
    </div>

    <div class="erp-form-section">
        <div class="erp-section-title">Status</div>
        <label class="erp-check"><input type="checkbox" id="admin_active" name="is_active" value="1" <?= ! $admin || (int) $admin->is_active === 1 ? 'checked' : '' ?>> Active account</label>
    </div>

    <div class="erp-form-foot">
        <a class="erp-btn erp-btn-ghost" href="<?= site_url('admins') ?>">Cancel</a>
        <button class="erp-btn erp-btn-primary" type="submit"><?= $editing ? 'Update' : 'Save' ?> Admin</button>
    </div>
</form>
<?php $this->load->view('access/management_foot'); ?>
