<?php
$active = isset($active) ? $active : '';
$back = isset($back) ? $back : '';
$nav_user = isset($auth_user) ? $auth_user : NULL;
$nav_role = $nav_user && isset($nav_user->role) ? $nav_user->role : '';
$nav_properties = isset($accessible_properties) && is_array($accessible_properties)
    ? $accessible_properties
    : array();
$nav_property = isset($current_property) ? $current_property : NULL;
$nav_property_id = isset($current_property_id) ? (int) $current_property_id : 0;
$nav_context_token = isset($property_context_token) ? (string) $property_context_token : '';
$nav_context_fingerprint = $nav_context_token === ''
    ? 'no-context'
    : substr(hash('sha256', $nav_context_token), 0, 20);
$nav_write_token = isset($session_write_token) ? (string) $session_write_token : '';
$has_property = $nav_property_id > 0 && $nav_property;
$is_landing = $active === 'inventory';
$home_url = $has_property
    ? 'inventory'
    : ( ! empty($nav_properties)
        ? 'properties/select'
        : ($nav_role === 'user' ? 'access/no-properties' : 'properties'));
$brand_inner =
    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6d5df6" stroke-width="1.8" stroke-linejoin="round">'
  . '<path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/></svg>'
  . '<span>Stay Management</span>';
$role_label = array(
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'user' => 'User',
);
?>
<script>
window.APP_BASE = <?= json_encode(base_url()) ?>;
window.APP_USER_ID = <?= $nav_user ? (int) $nav_user->id : 0 ?>;
window.APP_PROPERTY_ID = <?= (int) $nav_property_id ?>;
window.APP_PROPERTY_CONTEXT_TOKEN = <?= json_encode($nav_context_token) ?>;
window.APP_PROPERTY_CONTEXT_SWITCHED = <?= ! empty($property_context_switched) ? 'true' : 'false' ?>;
window.APP_CONTEXT_KEY = <?= json_encode(
    ($nav_user ? (int) $nav_user->id : 0).':'
    .(isset($current_tenant_id) ? (int) $current_tenant_id : 0).':'
    .$nav_property_id.':'.$nav_context_fingerprint
) ?>;
</script>

<nav class="erp-nav">
    <div class="erp-nav-primary">
        <?php if ($is_landing): ?>
            <span class="erp-nav-brand erp-nav-brand-static" title="You are on the home page"><?= $brand_inner ?></span>
        <?php else: ?>
            <a class="erp-nav-brand" href="<?= site_url($home_url) ?>" title="Go to home"><?= $brand_inner ?></a>
        <?php endif; ?>

        <?php if ( ! empty($nav_properties)): ?>
            <form class="erp-property-switch" action="<?= site_url('properties/switch') ?>" method="post" data-property-switch-form>
                <input type="hidden" name="session_write_token" value="<?= html_escape($nav_write_token) ?>">
                <label for="erpPropertySelector">Active property</label>
                <select id="erpPropertySelector" name="property_id" class="erp-select" aria-label="Active property">
                    <?php if ( ! $has_property): ?><option value="">Select property</option><?php endif; ?>
                    <?php foreach ($nav_properties as $property):
                        $owner = isset($property->admin_name) ? $property->admin_name : (isset($property->owner_name) ? $property->owner_name : '');
                        $label = ($nav_role === 'super_admin' && $owner !== '' ? $owner.' â€” ' : '').$property->property_name;
                    ?>
                        <option value="<?= (int) $property->id ?>" <?= (int) $property->id === $nav_property_id ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>

    <div class="erp-nav-right">
        <?php if ($has_property): ?>
            <a class="erp-nav-link <?= $active === 'inventory' ? 'active' : '' ?>" href="<?= site_url('inventory') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3zM4 7v14h16V7M9 12h6"/></svg>
                Inventory
            </a>
            <a class="erp-nav-link <?= $active === 'customers' ? 'active' : '' ?>" href="<?= site_url('customers') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Customer Master
            </a>
            <a class="erp-nav-link <?= $active === 'rooms' ? 'active' : '' ?>" href="<?= site_url('rooms') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 7h1M9 11h1M14 7h1M14 11h1M10 21v-4h4v4"/></svg>
                Room Master
            </a>
        <?php endif; ?>

        <?php if ($nav_role === 'super_admin' || $nav_role === 'admin'): ?>
        <details class="erp-manage-menu">
            <summary class="erp-btn erp-btn-ghost erp-btn-sm">Manage</summary>
            <div class="erp-manage-panel">
                <div class="erp-manage-user">
                    <strong><?= html_escape($nav_user && isset($nav_user->name) ? $nav_user->name : ($nav_user ? $nav_user->mobile_no : '')) ?></strong>
                    <span><?= html_escape(isset($role_label[$nav_role]) ? $role_label[$nav_role] : $nav_role) ?></span>
                </div>
                <?php if ($nav_role === 'super_admin'): ?>
                    <a href="<?= site_url('admins') ?>">Plants</a>
                <?php endif; ?>
                <?php if ($nav_role === 'super_admin' || $nav_role === 'admin'): ?>
                    <a href="<?= site_url('properties') ?>">Properties</a>
                    <a href="<?= site_url('users') ?>">Hotel Users</a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <a href="<?= site_url('logout') ?>" class="erp-btn erp-btn-ghost erp-btn-sm" data-app-logout>Logout</a>
        <?php if ($back): ?>
            <a class="erp-nav-back" href="<?= $back ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                Back
            </a>
        <?php endif; ?>
    </div>
</nav>

<?php if ($has_property): ?>
<div class="erp-subnav">
    <div class="erp-subnav-inner">
        <a class="erp-tab <?= $active === 'bookings' ? 'active' : '' ?>" href="<?= site_url('customers/bookings') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
            Booking Details
        </a>
        <a class="erp-tab <?= $active === 'checkins' ? 'active' : '' ?>" href="<?= site_url('customers/checkins') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
            Check-in Details
        </a>
        <a class="erp-tab <?= $active === 'checkedouts' ? 'active' : '' ?>" href="<?= site_url('customers/checkedouts') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="M14 17l5-5-5-5"/><path d="M19 12H8"/></svg>
            Check-out Details
        </a>
    </div>
</div>
<?php endif; ?>

<link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
<script src="<?= base_url('assets/js/property-context.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/property-context.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/erp-ui.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/erp-ui.js') ?>"></script>
