<?php
/**
 * Shared ERP top navigation.
 *
 *   $active = 'customers' | 'rooms'   -> highlight the current tab
 *   $back   = URL (optional)          -> show a mobile-only "Back" button
 *
 * The brand logo links to the landing/root page. On the landing page itself
 * (Customers) it is rendered as a non-clickable element so clicking it does
 * nothing (avoids a pointless page reload). Requires assets/css/erp.css.
 */
$active     = isset($active) ? $active : '';
$back       = isset($back) ? $back : '';
$is_landing = ($active === 'customers');   // Customers is the default/landing page

// Brand inner markup (logo chip + name), reused for the link/static variants.
$brand_inner =
    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6d5df6" stroke-width="1.8" stroke-linejoin="round">'
  . '<path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/></svg>'
  . '<span>Stay Management</span>';
?>
<nav class="erp-nav">
    <?php if ($is_landing): ?>
        <span class="erp-nav-brand erp-nav-brand-static" title="You are on the home page"><?= $brand_inner ?></span>
    <?php else: ?>
        <a class="erp-nav-brand" href="<?= site_url('') ?>" title="Go to home"><?= $brand_inner ?></a>
    <?php endif; ?>

    <div class="erp-nav-right">
        <a class="erp-nav-link <?= $active === 'customers' ? 'active' : '' ?>" href="<?= site_url('customers') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Customer Master
        </a>
        <a class="erp-nav-link <?= $active === 'rooms' ? 'active' : '' ?>" href="<?= site_url('rooms') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 7h1M9 11h1M14 7h1M14 11h1M10 21v-4h4v4"/>
            </svg>
            Room Master
        </a>
        <a href="<?= site_url('logout') ?>" class="erp-btn erp-btn-ghost erp-btn-sm">Logout</a>

        <?php if ( ! empty($back)): ?>
            <a class="erp-nav-back" href="<?= $back ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                Back
            </a>
        <?php endif; ?>
    </div>
</nav>
