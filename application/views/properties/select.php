<?php $page_title = 'Choose property'; $this->load->view('access/management_head', compact('page_title')); ?>
<div class="erp-card mgmt-card">
    <div class="erp-page-head">
        <div>
            <h1>
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/>
                </svg>
                Choose active property
            </h1>
            <p class="erp-sub">All operational screens will use only the selected property's data</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="erp-alert erp-alert-danger"><?= html_escape($flash['text']) ?></div>
    <?php endif; ?>

    <div style="padding:22px">
        <div class="mgmt-property-grid">
        <?php foreach ($properties as $property): ?>
            <section class="erp-card mgmt-card mgmt-property-card">
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/>
                    </svg>
                    <?= html_escape($property->property_name) ?>
                </h2>
                <div class="mgmt-help" style="margin:4px 0 16px"><?= html_escape($property->property_code) ?><?php if ($auth_user->role === User_model::ROLE_SUPER_ADMIN): ?> &middot; <?= html_escape($property->tenant_name) ?><?php endif; ?></div>
                <form method="post" action="<?= site_url('properties/switch') ?>" style="margin-top:auto">
                    <input type="hidden" name="session_write_token" value="<?= html_escape($session_write_token) ?>"><input type="hidden" name="property_id" value="<?= (int) $property->id ?>"><button class="erp-btn erp-btn-primary" type="submit" style="width:100%;justify-content:center">Open Property</button>
                </form>
            </section>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php $this->load->view('access/management_foot'); ?>
