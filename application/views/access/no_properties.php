<?php $page_title = 'No property access'; $this->load->view('access/management_head', compact('page_title')); ?>
<section class="erp-card mgmt-card mgmt-empty" style="max-width:620px;margin:0 auto">
    <div class="erp-state">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#6d5df6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:12px">
            <path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-6h10v6"/>
        </svg>
        <h1 style="font-size:1.15rem;margin:0 0 8px">No property is assigned</h1>
        <p style="margin:0 0 18px">Your account is active, but it currently has no active property assignment. Contact your admin to restore access.</p>
        <a class="erp-btn erp-btn-ghost" href="<?= site_url('logout') ?>">Logout</a>
    </div>
</section>
<?php $this->load->view('access/management_foot'); ?>
