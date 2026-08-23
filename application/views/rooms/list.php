<!DOCTYPE html>
<html lang="en" ng-app="roomsApp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms Master &middot; Stay Management</title>

    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/rooms.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/rooms.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <style>[ng-cloak]{display:none!important;}</style>
    <script>
        window.APP_BASE = "<?= base_url() ?>";
        window.APP_FRESH = <?= ! empty($flash) ? 'true' : 'false' ?>;   // a save just happened -> bypass cache once
    </script>
</head>

<body class="erp-body" ng-controller="RoomsController as vm">

<!-- Top navigation -->
<?php $this->load->view('layouts/erp_navbar', array('active' => 'rooms')); ?>

<div class="erp-wrap">
    <div class="erp-card">

        <!-- Page header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 7h1M9 11h1M14 7h1M14 11h1M10 21v-4h4v4"/>
                    </svg>
                    Rooms Master
                </h1>
                <p class="erp-sub">View, search and manage rooms</p>
            </div>
            <div class="erp-head-actions">
                <button type="button" class="erp-filter-clear" ng-click="vm.clearFilters()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    Clear
                </button>
                <div class="erp-head-total" ng-cloak>Total Rooms:&nbsp; {{ vm.rooms.length }}</div>
                <a href="<?= site_url('room-categories/add') ?>" class="erp-btn erp-btn-ghost">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add Room Category
                </a>
                <a href="<?= site_url('rooms/form') ?>" class="erp-btn erp-btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add Room
                </a>
            </div>
        </div>

        <!-- Flash message (after add/edit/upload) -->
        <?php if ( ! empty($flash)): ?>
            <div class="erp-alert erp-alert-<?= html_escape($flash['type']) ?>">
                <?= html_escape($flash['text']) ?>
            </div>
        <?php endif; ?>

        <!-- Table (filters sit in the header row, right under each column name) -->
        <div class="erp-table-scroll">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Room No</th>
                        <th>Category</th>
                        <th>Floor</th>
                        <th>Price</th>
                        <th>Housekeeping</th>
                        <th>Status</th>
                        <th>Added By</th>
                        <th>Actions</th>
                    </tr>
                    <tr class="erp-filter-row">
                        <th><input class="erp-input" ng-model="vm.filters.room_no" ng-change="vm.onFilter()" placeholder="Room No"></th>
                        <th>
                            <select class="erp-select" ng-model="vm.filters.category_id" ng-change="vm.onFilter()">
                                <option value="">All</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c->category_id ?>"><?= html_escape($c->category_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input class="erp-input" ng-model="vm.filters.floor_no" ng-change="vm.onFilter()" placeholder="Floor"></th>
                        <th></th>
                        <th>
                            <select class="erp-select" ng-model="vm.filters.housekeeping_status" ng-change="vm.onFilter()">
                                <option value="">All</option>
                                <?php foreach ($hk_statuses as $s): ?>
                                    <option value="<?= html_escape($s) ?>"><?= html_escape($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th>
                        <select class="erp-select" ng-model="vm.filters.status" ng-change="vm.onFilter()">
                            <option value="">Active</option>
                            <option value="0">Inactive</option>
                            <option value="all">All</option>
                        </select>
                        </th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="r in vm.rooms" ng-cloak>
                        <td class="cell-strong">{{ r.room_no }}</td>
                        <td>{{ r.category_name || '—' }}</td>
                        <td>{{ r.floor_no || '—' }}</td>
                        <td>{{ r.selling_price ? ('₹' + (r.selling_price | number:2)) : '—' }}</td>
                        <td><span class="erp-chip" ng-class="vm.hkClass(r.housekeeping_status)">{{ r.housekeeping_status || '—' }}</span></td>
                        <td>
                            <span class="erp-badge" ng-class="r.is_active == 1 ? 'erp-badge-active' : 'erp-badge-inactive'">
                                {{ r.is_active == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>{{ r.created_by || '—' }}</td>
                        <td>
                            <span class="erp-actions">
                                <!-- View -->
                                <button class="erp-icon-btn erp-icon-view" title="View" ng-click="vm.viewRoom(r.id)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <!-- Edit -->
                                <a class="erp-icon-btn erp-icon-edit" title="Edit" href="<?= site_url('rooms/form') ?>/{{ r.id }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <!-- Delete -->
                                <button class="erp-icon-btn erp-icon-delete" title="Delete" ng-click="vm.deleteRoom(r)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>
                                </button>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Loading / empty states -->
            <div class="erp-state" ng-if="vm.loading" ng-cloak><span class="erp-spinner"></span><div style="margin-top:10px;">Loading rooms…</div></div>
            <div class="erp-state" ng-if="!vm.loading && vm.rooms.length === 0" ng-cloak>No rooms match your filters.</div>
        </div>

        <div class="erp-count" ng-cloak>{{ vm.rooms.length }} room<span ng-if="vm.rooms.length != 1">s</span></div>
    </div>
</div>

<!-- ================= Detail Modal ================= -->
<div class="erp-modal-backdrop" ng-if="vm.showModal" ng-click="vm.closeModal($event)" ng-cloak>
    <div class="erp-modal" ng-click="$event.stopPropagation()">
        <div class="erp-modal-head">
            <h3>Room {{ vm.detail.room_no }} <span class="erp-muted" style="font-weight:500;font-size:1rem;">({{ vm.detail.room_code }})</span></h3>
            <button class="erp-modal-close" ng-click="vm.closeModal()">&times;</button>
        </div>
        <div class="erp-modal-body">

            <div class="erp-section-title">Basic Info</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Category</div><div class="v">{{ vm.detail.category_name || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Floor</div><div class="v">{{ vm.detail.floor_no || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Extra Bed</div><div class="v">{{ vm.detail.extra_bed_allowed == 1 ? 'Allowed' : 'No' }}</div></div>
                <div class="erp-detail-item"><div class="k">Status</div><div class="v">
                    <span class="erp-badge" ng-class="vm.detail.is_active == 1 ? 'erp-badge-active':'erp-badge-inactive'">{{ vm.detail.is_active == 1 ? 'Active':'Inactive' }}</span>
                </div></div>
                <div class="erp-detail-item" style="grid-column:1/-1;"><div class="k">Description</div><div class="v">{{ vm.detail.description || '—' }}</div></div>
                <div class="erp-detail-item" style="grid-column:1/-1;"><div class="k">Remarks</div><div class="v">{{ vm.detail.remarks || '—' }}</div></div>
            </div>

            <hr class="erp-hr">

            <div class="erp-section-title">Pricing</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Price</div><div class="v">{{ vm.detail.selling_price ? ('₹' + (vm.detail.selling_price | number:2)) : '—' }}</div></div>
            </div>

            <hr class="erp-hr">

            <div class="erp-section-title">Housekeeping &amp; Audit</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Housekeeping</div><div class="v">{{ vm.detail.housekeeping_status || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Created By</div><div class="v">{{ vm.detail.created_by || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Created At</div><div class="v">{{ vm.detail.created_at || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Updated By</div><div class="v">{{ vm.detail.updated_by || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Updated At</div><div class="v">{{ vm.detail.updated_at || '—' }}</div></div>
            </div>
        </div>
        <div class="erp-form-foot">
            <button class="erp-btn erp-btn-ghost" ng-click="vm.closeModal()">Close</button>
            <a class="erp-btn erp-btn-primary" ng-href="<?= site_url('rooms/form') ?>/{{ vm.detail.id }}">Edit</a>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/angular.min.js') ?>"></script>
<script src="<?= base_url('assets/js/erp-query.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/erp-query.js') ?>"></script>
<script src="<?= base_url('assets/js/rooms.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/rooms.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
</body>
</html>
