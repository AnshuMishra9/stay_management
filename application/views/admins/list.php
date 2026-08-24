<?php $page_title = 'Plants'; $this->load->view('access/management_head', compact('page_title')); ?>
<div class="erp-card mgmt-card">

    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M12 13c-4.42 0-8 2.24-8 5v3h16v-3c0-2.76-3.58-5-8-5z"/>
                </svg>
                Plants
            </h1>
            <p class="erp-sub">Each plant owns one isolated account and may own multiple properties</p>
        </div>
        <div class="erp-head-actions">
            <form method="get" style="display:inline-flex;align-items:center">
                <select name="status" class="erp-select" style="height:38px;width:auto" onchange="this.form.submit()" title="Deleted plants stay hidden as Inactive">
                    <option value="">Active</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </form>
            <a class="erp-btn erp-btn-soft" href="<?= site_url('admins/add') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Plant
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="erp-alert erp-alert-<?= html_escape($flash['type']) ?>"><?= html_escape($flash['text']) ?></div>
    <?php endif; ?>

    <div class="erp-table-scroll">
        <table class="erp-table mgmt-table">
            <thead>
                <tr><th>Plant</th><th>Mobile</th><th>Plant Name</th><th>Properties</th><th>Users</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $admin): ?>
                <tr>
                    <td class="cell-strong"><?= html_escape($admin->name) ?></td>
                    <td><?= html_escape($admin->mobile_no) ?></td>
                    <td><?= html_escape($admin->tenant_name) ?></td>
                    <td><?= (int) $admin->property_count ?></td>
                    <td><?= (int) $admin->user_count ?></td>
                    <td><span class="erp-badge <?= (int) $admin->is_active === 1 ? 'erp-badge-active' : 'erp-badge-inactive' ?>"><?= (int) $admin->is_active === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <span class="erp-actions">
                            <a class="erp-btn erp-btn-sm erp-btn-primary" href="<?= site_url('admins/edit/'.$admin->id) ?>">Edit</a>
                            <form method="post" action="<?= site_url('admins/status/'.$admin->id) ?>">
                                <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>">
                                <input type="hidden" name="is_active" value="<?= (int) $admin->is_active === 1 ? '0' : '1' ?>">
                                <button class="erp-btn erp-btn-sm erp-btn-ghost" type="submit"><?= (int) $admin->is_active === 1 ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <form method="post" action="<?= site_url('admins/delete/'.$admin->id) ?>" onsubmit="return confirm('Deactivate this plant? The record is never deleted and can be re-activated later.');">
                                <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>">
                                <button class="erp-btn erp-btn-sm erp-btn-danger" type="submit">Delete</button>
                            </form>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($admins)): ?>
            <div class="erp-state">No plants have been created.</div>
        <?php endif; ?>
    </div>

    <div class="erp-count"><?= count($admins) ?> plant<?= count($admins) != 1 ? 's' : '' ?></div>
</div>
<?php $this->load->view('access/management_foot'); ?>
