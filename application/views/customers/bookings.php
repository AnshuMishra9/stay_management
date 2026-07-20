<!DOCTYPE html>
<html lang="en" ng-app="bookingsApp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details &middot; Stay Management</title>

    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <style>[ng-cloak]{display:none!important;}</style>
    <script>window.APP_BASE = "<?= base_url() ?>";</script>
</head>

<body class="erp-body" ng-controller="BookingsController as vm">

<!-- Top navigation -->
<?php $this->load->view('layouts/erp_navbar', array('active' => 'bookings')); ?>

<div class="erp-wrap">
    <div class="erp-card">

        <!-- Page header -->
        <div class="erp-page-head">
            <div>
                <h1>
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#e8eef6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>
                    </svg>
                    Booking Details
                </h1>
                <p class="erp-sub">Customers with a room booked</p>
            </div>
            <div class="erp-head-actions">
                <button type="button" class="erp-filter-clear" ng-click="vm.clearFilters()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    Clear
                </button>
                <div class="erp-head-total" ng-cloak>Total Bookings:&nbsp; {{ vm.bookings.length }}</div>
                <a href="<?= site_url('customers/booking_form') ?>" class="erp-btn erp-btn-soft">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    New Booking
                </a>
            </div>
        </div>

        <!-- Flash message (after saving a booking) -->
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
                        <th>Booking No</th>
                        <th>Customer Name</th>
                        <th>Room No</th>
                        <th>Room Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    <tr class="erp-filter-row">
                        <th><input class="erp-input" ng-model="vm.filters.q" ng-change="vm.onFilter()" placeholder="Search booking / customer"></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="c in vm.bookings" ng-cloak>
                        <td class="cell-strong">{{ c.booking_number }}</td>
                        <td>{{ c.customer_name }}</td>
                        <td>{{ c.allotted_room_no || '—' }}</td>
                        <td>{{ c.room_category || '—' }}</td>
                        <td><span class="erp-badge" ng-class="vm.statusClass(c.status_code)">{{ c.status_name }}</span></td>
                        <td>
                            <span class="erp-actions">
                                <a class="erp-icon-btn erp-icon-edit" title="Edit booking" href="<?= site_url('customers/booking_form') ?>/{{ c.id }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Loading / empty states -->
            <div class="erp-state" ng-if="vm.loading" ng-cloak><span class="erp-spinner"></span><div style="margin-top:10px;">Loading bookings…</div></div>
            <div class="erp-state" ng-if="!vm.loading && vm.bookings.length === 0" ng-cloak>No bookings match your filters.</div>
        </div>

        <div class="erp-count" ng-cloak>{{ vm.bookings.length }} booking<span ng-if="vm.bookings.length != 1">s</span></div>
    </div>
</div>

<script src="<?= base_url('assets/js/angular.min.js') ?>"></script>
<script src="<?= base_url('assets/js/erp-query.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/erp-query.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/bookings.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/bookings.js') ?>"></script>
</body>
</html>
