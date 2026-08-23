<?php $page_title = 'Properties'; $this->load->view('access/management_head', compact('page_title')); ?>
<div class="erp-card mgmt-card">

    <!-- Page header -->
    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/>
                </svg>
                Properties
            </h1>
            <p class="erp-sub">Operational data is always isolated to one selected property</p>
        </div>
        <div class="erp-head-actions">
            <form method="get" style="display:inline-flex;align-items:center">
                <select name="status" class="erp-select" style="height:38px;width:auto" onchange="this.form.submit()" title="Deleted properties stay hidden as Inactive">
                    <option value="">Active</option>
                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                </select>
            </form>
            <a class="erp-btn erp-btn-soft" href="<?= site_url('properties/add') ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add Property
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="erp-alert erp-alert-<?= html_escape($flash['type']) ?>"><?= html_escape($flash['text']) ?></div>
    <?php endif; ?>

    <div class="erp-table-scroll">
        <table class="erp-table mgmt-table">
            <thead>
                <tr><th>Property</th><th>Code</th><?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?><th>Plant</th><?php endif; ?><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($properties as $property): ?>
                <tr>
                    <td class="cell-strong"><?= html_escape($property->property_name) ?></td>
                    <td><?= html_escape($property->property_code) ?></td>
                    <?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?><td><?= html_escape($property->tenant_name) ?><div class="mgmt-help"><?= html_escape($property->admin_name ?: 'No active owner') ?></div></td><?php endif; ?>
                    <td><span class="erp-badge <?= (int) $property->is_active === 1 ? 'erp-badge-active' : 'erp-badge-inactive' ?>"><?= (int) $property->is_active === 1 ? 'Active' : 'Inactive' ?></span></td>
                    <td>
                        <span class="erp-actions">
                            <?php if ((int) $property->is_active === 1): ?>
                            <form method="post" action="<?= site_url('properties/switch') ?>">
                                <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><input type="hidden" name="property_id" value="<?= (int) $property->id ?>"><button class="erp-btn erp-btn-sm erp-btn-primary" type="submit">Open</button>
                            </form>
                            <?php endif; ?>
                            <a class="erp-btn erp-btn-sm erp-btn-primary" href="<?= site_url('properties/edit/'.$property->id) ?>">Edit</a>
                            <form method="post" action="<?= site_url('properties/status/'.$property->id) ?>">
                                <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><input type="hidden" name="is_active" value="<?= (int) $property->is_active === 1 ? 0 : 1 ?>"><button class="erp-btn erp-btn-sm erp-btn-ghost" type="submit"><?= (int) $property->is_active === 1 ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <form method="post" action="<?= site_url('properties/delete/'.$property->id) ?>" onsubmit="return confirm('Deactivate this property? The record is never deleted and can be re-activated later.');">
                                <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><button class="erp-btn erp-btn-sm erp-btn-danger" type="submit">Delete</button>
                            </form>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($properties)): ?>
            <div class="erp-state">No properties yet. Create the first property to begin using Inventory, Rooms, Customers, and Bookings.</div>
        <?php endif; ?>
    </div>

    <div class="erp-count"><?= count($properties) ?> propert<?= count($properties) != 1 ? 'ies' : 'y' ?></div>
</div>
<?php $this->load->view('access/management_foot'); ?>
