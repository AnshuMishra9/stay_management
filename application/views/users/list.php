<?php $page_title = 'Users'; $this->load->view('access/management_head', compact('page_title')); ?>
<div class="erp-card mgmt-card">

    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Property users
            </h1>
            <p class="erp-sub">Users receive full existing operations only inside their assigned properties</p>
        </div>
        <div class="erp-head-actions">
            <a class="erp-btn erp-btn-soft" href="<?= site_url('users/add'.($tenant_id ? '?tenant_id='.(int) $tenant_id : '')) ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add User
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="erp-alert erp-alert-<?= html_escape($flash['type']) ?>"><?= html_escape($flash['text']) ?></div>
    <?php endif; ?>

    <?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?>
        <form class="mgmt-filter-bar" method="get" action="<?= site_url('users') ?>">
            <div style="flex:1 1 260px;min-width:0">
                <label for="filter_tenant">Plant</label>
                <select class="erp-select" id="filter_tenant" name="tenant_id">
                    <option value="">All plants</option>
                    <?php foreach ($admin_tenants as $tenant): ?><option value="<?= (int) $tenant->id ?>" <?= (int) $tenant_id === (int) $tenant->id ? 'selected' : '' ?>><?= html_escape($tenant->name.' — '.$tenant->admin_name) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:0">
                <label for="filter_status">Status</label>
                <select class="erp-select" id="filter_status" name="status" onchange="this.form.submit()">
                    <option value="">Active</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            <button class="erp-btn erp-btn-sm erp-btn-primary" type="submit">Filter</button>
        </form>
    <?php endif; ?>

    <div class="erp-table-scroll">
        <table class="erp-table mgmt-table">
            <thead>
                <tr><th>User</th><th>Mobile</th><?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?><th>Plant</th><?php endif; ?><th>Assigned properties</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="cell-strong"><?= html_escape($user->name) ?></td><td><?= html_escape($user->mobile_no) ?></td>
                    <?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?><td><?= html_escape($user->tenant_name) ?><div class="mgmt-help"><?= html_escape($user->admin_name) ?></div></td><?php endif; ?>
                    <td><?= (int) $user->property_count ?></td><td><span class="erp-badge <?= (int) $user->is_active === 1 ? 'erp-badge-active' : 'erp-badge-inactive' ?>"><?= (int) $user->is_active === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td><span class="erp-actions"><a class="erp-btn erp-btn-sm erp-btn-primary" href="<?= site_url('users/edit/'.$user->id) ?>">Edit</a>
                    <form method="post" action="<?= site_url('users/status/'.$user->id) ?>"><input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><input type="hidden" name="is_active" value="<?= (int) $user->is_active === 1 ? 0 : 1 ?>"><button class="erp-btn erp-btn-sm erp-btn-ghost" type="submit"><?= (int) $user->is_active === 1 ? 'Deactivate' : 'Activate' ?></button></form>
                    <form method="post" action="<?= site_url('users/delete/'.$user->id) ?>" onsubmit="return confirm('Deactivate this user? The record is never deleted and can be re-activated later.');"><input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><button class="erp-btn erp-btn-sm erp-btn-danger" type="submit">Delete</button></form></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($users)): ?>
            <div class="erp-state">No normal users found for this account.</div>
        <?php endif; ?>
    </div>

    <div class="erp-count"><?= count($users) ?> user<?= count($users) != 1 ? 's' : '' ?></div>
</div>
<?php $this->load->view('access/management_foot'); ?>
