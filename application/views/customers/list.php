<!DOCTYPE html>
<html lang="en" ng-app="customersApp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Master &middot; Stay Management</title>

    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>">
    <style>[ng-cloak]{display:none!important;}</style>
    <script>window.APP_BASE = "<?= base_url() ?>";</script>
</head>

<body class="erp-body" ng-controller="CustomersController as vm">

<!-- Top navigation -->
<?php $this->load->view('layouts/erp_navbar', array('active' => 'customers')); ?>

<div class="erp-wrap">
    <div class="erp-card">

        <!-- Page header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Customers Master
                </h1>
                <p class="erp-sub">View, search and manage customers</p>
            </div>
            <div class="erp-head-actions">
                <a href="<?= site_url('customers/bookings') ?>" class="erp-btn erp-btn-soft">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
                    Booking Details
                </a>
                <a href="<?= site_url('customers/form') ?>" class="erp-btn erp-btn-soft">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add Customer
                </a>
            </div>
        </div>

        <!-- Flash message (after add/edit/upload) -->
        <?php if ( ! empty($flash)): ?>
            <div class="erp-alert erp-alert-<?= html_escape($flash['type']) ?>">
                <?= html_escape($flash['text']) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="erp-filters">
            <div class="erp-filter-grid">
                <div class="erp-field">
                    <label>Customer ID</label>
                    <input class="erp-input" ng-model="vm.filters.customer_code" ng-change="vm.onFilter()" placeholder="ID">
                </div>
                <div class="erp-field">
                    <label>Name</label>
                    <input class="erp-input" ng-model="vm.filters.name" ng-change="vm.onFilter()" placeholder="Name">
                </div>
                <div class="erp-field">
                    <label>Owner</label>
                    <input class="erp-input" ng-model="vm.filters.owner" ng-change="vm.onFilter()" placeholder="Owner">
                </div>
                <div class="erp-field">
                    <label>Mobile</label>
                    <input class="erp-input" ng-model="vm.filters.phone" ng-change="vm.onFilter()" placeholder="Mobile">
                </div>
                <div class="erp-field">
                    <label>City</label>
                    <input class="erp-input" ng-model="vm.filters.city" ng-change="vm.onFilter()" placeholder="City">
                </div>
                <div class="erp-field">
                    <label>District</label>
                    <input class="erp-input" ng-model="vm.filters.district" ng-change="vm.onFilter()" placeholder="District">
                </div>
                <div class="erp-field">
                    <label>State</label>
                    <select class="erp-select" ng-model="vm.filters.state" ng-change="vm.onFilter()">
                        <option value="">All</option>
                        <?php foreach ($states as $s): ?>
                            <option value="<?= html_escape($s) ?>"><?= html_escape($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-field">
                    <label>Type</label>
                    <select class="erp-select" ng-model="vm.filters.customer_type" ng-change="vm.onFilter()">
                        <option value="">All</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= html_escape($t) ?>"><?= html_escape($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-field">
                    <label>Booking Status</label>
                    <select class="erp-select" ng-model="vm.filters.booking_status" ng-change="vm.onFilter()">
                        <option value="">All</option>
                        <?php foreach ($booking_statuses as $bval => $blabel): ?>
                            <option value="<?= $bval ?>"><?= $blabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="erp-field">
                    <label>Active</label>
                    <select class="erp-select" ng-model="vm.filters.status" ng-change="vm.onFilter()">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="erp-field">
                    <label>&nbsp;</label>
                    <button type="button" class="erp-btn erp-btn-ghost" ng-click="vm.clearFilters()" style="width:100%;justify-content:center;height:42px;">Clear</button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="erp-table-scroll">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Owner / Contact</th>
                        <th>Mobile</th>
                        <th>Alt Mobile</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Booking</th>
                        <th>Checked-In At</th>
                        <th>City</th>
                        <th>District</th>
                        <th>State</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="c in vm.customers" ng-cloak>
                        <td class="cell-strong">{{ c.customer_code }}</td>
                        <td class="cell-strong">{{ c.customer_name }}</td>
                        <td>{{ c.owner_name || '—' }}</td>
                        <td>{{ c.phone }}</td>
                        <td>{{ c.alt_phone || '—' }}</td>
                        <td>{{ c.email || '—' }}</td>
                        <td>{{ c.customer_type || '—' }}</td>
                        <td>
                            <span class="erp-badge" ng-class="vm.bookingClass(c.booking_status)" ng-if="c.booking_status">{{ vm.bookingLabel(c.booking_status) }}</span>
                            <span class="erp-muted" ng-if="!c.booking_status">—</span>
                        </td>
                        <td>{{ c.checked_in_at || '—' }}</td>
                        <td>{{ c.city || '—' }}</td>
                        <td>{{ c.district || '—' }}</td>
                        <td>{{ c.state || '—' }}</td>
                        <td>{{ c.country || '—' }}</td>
                        <td>
                            <span class="erp-badge" ng-class="c.is_active == 1 ? 'erp-badge-active' : 'erp-badge-inactive'">
                                {{ c.is_active == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <span class="erp-actions">
                                <!-- View -->
                                <button class="erp-icon-btn erp-icon-view" title="View" ng-click="vm.viewCustomer(c.id)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <!-- Edit -->
                                <a class="erp-icon-btn erp-icon-edit" title="Edit" href="<?= site_url('customers/form') ?>/{{ c.id }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <!-- Delete -->
                                <button class="erp-icon-btn erp-icon-delete" title="Delete" ng-click="vm.deleteCustomer(c)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>
                                </button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Loading / empty states -->
            <div class="erp-state" ng-if="vm.loading" ng-cloak><span class="erp-spinner"></span><div style="margin-top:10px;">Loading customers…</div></div>
            <div class="erp-state" ng-if="!vm.loading && vm.customers.length === 0" ng-cloak>No customers match your filters.</div>
        </div>

        <div class="erp-count" ng-cloak>{{ vm.customers.length }} customer<span ng-if="vm.customers.length != 1">s</span></div>
    </div>
</div>

<!-- ================= Detail Modal ================= -->
<div class="erp-modal-backdrop" ng-if="vm.showModal" ng-click="vm.closeModal($event)" ng-cloak>
    <div class="erp-modal" ng-click="$event.stopPropagation()">
        <div class="erp-modal-head">
            <h3>{{ vm.detail.customer_name }} <span class="erp-muted" style="font-weight:500;font-size:1rem;">({{ vm.detail.customer_code }})</span></h3>
            <button class="erp-modal-close" ng-click="vm.closeModal()">&times;</button>
        </div>
        <div class="erp-modal-body">

            <div class="erp-section-title">Customer Info</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Owner / Contact</div><div class="v">{{ vm.detail.owner_name || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Customer Type</div><div class="v">{{ vm.detail.customer_type || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Mobile No</div><div class="v">{{ vm.detail.phone }}</div></div>
                <div class="erp-detail-item"><div class="k">Alt Mobile No</div><div class="v">{{ vm.detail.alt_phone || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Landline No</div><div class="v">{{ vm.detail.landline_no || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Email</div><div class="v">{{ vm.detail.email || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Status</div><div class="v">
                    <span class="erp-badge" ng-class="vm.detail.is_active == 1 ? 'erp-badge-active':'erp-badge-inactive'">{{ vm.detail.is_active == 1 ? 'Active':'Inactive' }}</span>
                </div></div>
                <div class="erp-detail-item" style="grid-column:1/-1;"><div class="k">Address 1</div><div class="v">{{ vm.detail.address1 || '—' }}</div></div>
                <div class="erp-detail-item" style="grid-column:1/-1;"><div class="k">Address 2</div><div class="v">{{ vm.detail.address2 || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">City</div><div class="v">{{ vm.detail.city || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">District</div><div class="v">{{ vm.detail.district || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">State</div><div class="v">{{ vm.detail.state || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Pincode</div><div class="v">{{ vm.detail.pincode || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Zip Code</div><div class="v">{{ vm.detail.zip_code || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Country</div><div class="v">{{ vm.detail.country || '—' }}</div></div>
            </div>

            <hr class="erp-hr">

            <div class="erp-section-title">Identity Documents</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item">
                    <div class="k">Aadhar Number</div><div class="v">{{ vm.detail.aadhar_number || '—' }}</div>
                    <div class="k" style="margin-top:8px;">Aadhar Name</div><div class="v">{{ vm.detail.aadhar_name || '—' }}</div>
                    <a class="erp-doc-link" ng-if="vm.detail.aadhar_url" ng-href="{{ vm.detail.aadhar_url }}" target="_blank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        View Aadhar Document
                    </a>
                    <div class="v erp-muted" ng-if="!vm.detail.aadhar_url">No document uploaded</div>
                </div>
                <div class="erp-detail-item">
                    <div class="k">PAN Number</div><div class="v">{{ vm.detail.pan_number || '—' }}</div>
                    <div class="k" style="margin-top:8px;">PAN Name</div><div class="v">{{ vm.detail.pan_name || '—' }}</div>
                    <a class="erp-doc-link" ng-if="vm.detail.pan_url" ng-href="{{ vm.detail.pan_url }}" target="_blank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        View PAN Document
                    </a>
                    <div class="v erp-muted" ng-if="!vm.detail.pan_url">No document uploaded</div>
                </div>
            </div>
        </div>
        <div class="erp-form-foot">
            <button class="erp-btn erp-btn-ghost" ng-click="vm.closeModal()">Close</button>
            <a class="erp-btn erp-btn-primary" ng-href="<?= site_url('customers/form') ?>/{{ vm.detail.id }}">Edit</a>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/angular.min.js') ?>"></script>
<script src="<?= base_url('assets/js/customers.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>"></script>
</body>
</html>
