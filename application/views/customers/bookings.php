<!DOCTYPE html>
<html lang="en" ng-app="bookingsApp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details &middot; Stay Management</title>

    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/flatpickr.min.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/flatpickr.min.css') ?>">
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
                <p class="erp-sub">Filter and review customer bookings by check-in / check-out, status and channel</p>
            </div>
            <div class="erp-head-actions">
                <button type="button" class="erp-filter-clear" ng-click="vm.clearFilters()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    Clear
                </button>
                <div class="erp-head-total" ng-cloak>Total Bookings:&nbsp; {{ vm.bookings.length }}</div>
                <a href="<?= site_url('customers/form') ?>" class="erp-btn erp-btn-soft">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    New Booking
                </a>
            </div>
        </div>

        <!-- Table (filters sit in the header row, right under each column name) -->
        <div class="erp-table-scroll">
            <table class="erp-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer Name</th>
                        <th>Guest Name</th>
                        <th>Guest Mobile</th>
                        <th>Channel</th>
                        <th>Booking Status</th>
                        <th>Sched. Check-In</th>
                        <th>Sched. Check-Out</th>
                        <th>Nights</th>
                        <th>Checked-In At</th>
                        <th>Checked-Out At</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Remaining</th>
                        <th>Actions</th>
                    </tr>
                    <tr class="erp-filter-row">
                        <th><input class="erp-input" ng-model="vm.filters.q" ng-change="vm.onFilter()" placeholder="Search ID / name"></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th>
                            <select class="erp-select" ng-model="vm.filters.booking_channel_id" ng-change="vm.onFilter()">
                                <option value="">All</option>
                                <?php foreach ($channel_opts as $ch): ?>
                                    <option value="<?= (int) $ch->channel_id ?>"><?= html_escape($ch->channel_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th>
                            <select class="erp-select" ng-model="vm.filters.booking_status" ng-change="vm.onFilter()">
                                <option value="">All</option>
                                <?php foreach ($booking_statuses as $bval => $blabel): ?>
                                    <option value="<?= $bval ?>"><?= $blabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input class="erp-input" id="bk_checkin_range" type="text" placeholder="1 or 2 dates" readonly></th>
                        <th><input class="erp-input" id="bk_checkout_range" type="text" placeholder="1 or 2 dates" readonly></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr ng-repeat="c in vm.bookings" ng-cloak>
                        <td class="cell-strong">{{ c.customer_code }}</td>
                        <td class="cell-strong">{{ c.customer_name }}</td>
                        <td>{{ c.guest_name || '—' }}</td>
                        <td>{{ c.guest_mobile_no || '—' }}</td>
                        <td>{{ c.channel_name || '—' }}</td>
                        <td>
                            <span class="erp-badge" ng-class="vm.bookingClass(c.booking_status)" ng-if="c.booking_status">{{ vm.bookingLabel(c.booking_status) }}</span>
                            <span class="erp-muted" ng-if="!c.booking_status">—</span>
                        </td>
                        <td>{{ c.scheduled_check_in_date || '—' }}</td>
                        <td>{{ c.scheduled_check_out_date || '—' }}</td>
                        <td>{{ c.length_of_stay || '—' }}</td>
                        <td>{{ c.checked_in_at || '—' }}</td>
                        <td>{{ c.checked_out_at || '—' }}</td>
                        <td>{{ c.total_amount || '—' }}</td>
                        <td>{{ c.amount_paid || '—' }}</td>
                        <td>{{ c.remaining_amount || '—' }}</td>
                        <td>
                            <span class="erp-actions">
                                <a class="erp-icon-btn erp-icon-edit" title="Edit booking" href="<?= site_url('customers/form') ?>/{{ c.id }}">
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
<script src="<?= base_url('assets/js/flatpickr.min.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/flatpickr.min.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/bookings.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/bookings.js') ?>"></script>
</body>
</html>
