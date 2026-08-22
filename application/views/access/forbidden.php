<?php $page_title = 'Access denied'; $this->load->view('access/management_head', compact('page_title')); ?>
<section class="erp-card mgmt-card mgmt-empty" style="max-width:620px;margin:0 auto">
    <div class="erp-state">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <h1 style="font-size:1.15rem;margin:0 0 8px">Access denied</h1>
        <p style="margin:0 0 18px">Your account does not have permission to open this page.</p>
        <a class="erp-btn erp-btn-primary" href="<?= site_url('') ?>">Return to the application</a>
    </div>
</section>
<?php $this->load->view('access/management_foot'); ?>
