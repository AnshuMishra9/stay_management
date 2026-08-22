<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Master &middot; Room Categories &middot; Stay Management</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/rooms.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/rooms.css') ?>">
</head>
<body class="erp-body">
<?php $this->load->view('layouts/erp_navbar', array('active' => 'rooms', 'back' => site_url('rooms'))); ?>

<div class="erp-wrap">
    <div class="erp-card">
        <div class="erp-page-head">
            <div>
                <h1>Room Categories</h1>
                <p class="erp-sub">Configure room types for <?= html_escape($current_property->property_name) ?></p>
            </div>
            <div class="erp-head-actions">
                <div class="erp-head-total"><?= count($categories) ?> categories</div>
                <a class="erp-btn erp-btn-ghost" href="<?= site_url('rooms') ?>">Room Master</a>
                <a class="erp-btn erp-btn-primary" href="<?= site_url('room-categories/add') ?>">Add Category</a>
            </div>
        </div>

        <?php if ( ! empty($flash)): ?>
            <div class="erp-alert erp-alert-<?= html_escape($flash['type']) ?>"><?= html_escape($flash['text']) ?></div>
        <?php endif; ?>

        <div class="erp-table-scroll">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Capacity</th>
                        <th>Bed</th>
                        <th>Status</th>
                        <th>Order</th>
                        <th class="erp-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= html_escape($category->category_name) ?></td>
                        <td><?= html_escape($category->short_code ?: '—') ?></td>
                        <td><?= (int) $category->max_adults ?> adult / <?= (int) $category->max_children ?> child</td>
                        <td><?= html_escape(trim(($category->bed_count ?: '').' '.($category->bed_type ?: '')) ?: '—') ?></td>
                        <td><span class="erp-badge <?= (int) $category->status ? 'erp-badge-active' : 'erp-badge-inactive' ?>"><?= (int) $category->status ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= (int) $category->display_order ?></td>
                        <td>
                            <div class="erp-actions">
                                <a class="erp-icon-btn erp-icon-edit" href="<?= site_url('room-categories/edit/'.(int) $category->category_id) ?>" title="Edit">Edit</a>
                                <form action="<?= site_url('room-categories/delete/'.(int) $category->category_id) ?>" method="post" onsubmit="return confirm('Remove this room category? Categories with history will be deactivated.');">
                                    <input type="hidden" name="property_context_token" value="<?= html_escape($property_context_token) ?>">
                                    <button type="submit" class="erp-icon-btn erp-icon-delete" title="Delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="7"><div class="erp-state">No room categories yet. Categories are optional; rooms can be added directly in Room Master.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
