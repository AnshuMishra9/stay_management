<?php
// Shared list template — driven by the controller (bookings vs checkins).
$title      = isset($title)      ? $title      : 'Booking Details';
$sub        = isset($sub)        ? $sub        : 'Customers with a room booked';
$ajax       = isset($ajax)       ? $ajax       : 'customers/bookings_ajax';
$ns         = isset($ns)         ? $ns         : 'bookings';
$show_new   = isset($show_new)   ? $show_new   : TRUE;
$cross_url  = isset($cross_url)  ? $cross_url  : '';
$cross_text = isset($cross_text) ? $cross_text : '';
?>
<!DOCTYPE html>
<html lang="en" ng-app="bookingsApp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= html_escape($title) ?> &middot; Stay Management</title>

    <link rel="stylesheet" href="<?= base_url('assets/css/erp.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/erp.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/searchable-select.css') ?>?v=<?= @filemtime(FCPATH.'assets/css/searchable-select.css') ?>">
    <style>[ng-cloak]{display:none!important;}</style>
    <script>
        window.APP_BASE = "<?= base_url() ?>";
        window.APP_FRESH = <?= ! empty($flash) ? 'true' : 'false' ?>;   // a save just happened -> bypass cache once
        window.APP_LIST_URL = "<?= site_url($ajax) ?>";                 // which list to fetch
        window.APP_LIST_NS  = "<?= html_escape($ns) ?>";               // cache namespace
    </script>
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
                    <?= html_escape($title) ?>
                </h1>
                <p class="erp-sub"><?= html_escape($sub) ?></p>
            </div>
            <div class="erp-head-actions">
                <button type="button" class="erp-filter-clear" ng-click="vm.clearFilters()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    Clear
                </button>
                <div class="erp-head-total" ng-cloak>Total:&nbsp; {{ vm.bookings.length }}</div>
                <?php if ($show_new): ?>
                <a href="<?= site_url('customers/booking_form') ?>" class="erp-btn erp-btn-soft">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    New Booking
                </a>
                <?php endif; ?>
                <?php if ($cross_url !== ''): ?>
                <a href="<?= site_url($cross_url) ?>" class="erp-btn erp-btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <?= html_escape($cross_text) ?>
                </a>
                <?php endif; ?>
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
                                <!-- View (present data) -->
                                <button class="erp-icon-btn erp-icon-view" title="View" ng-click="vm.viewBooking(c.id)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                <!-- Check-in (customer + identity + status) -->
                                <a class="erp-icon-btn erp-icon-checkin" title="Check-in" href="<?= site_url('customers/checkin') ?>/{{ c.id }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
                                </a>
                                <!-- Edit (full booking form) -->
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

<!-- ================= Booking Detail Modal (eye) ================= -->
<div class="erp-modal-backdrop" ng-if="vm.showModal" ng-click="vm.closeModal($event)" ng-cloak>
    <div class="erp-modal" ng-click="$event.stopPropagation()">
        <div class="erp-modal-head">
            <h3>Booking {{ vm.detail.booking_number }}
                <span class="erp-badge" ng-class="vm.statusClass(vm.detail.status_code)" style="font-size:.8rem;vertical-align:middle;">{{ vm.detail.status_name }}</span>
            </h3>
            <button class="erp-modal-close" ng-click="vm.closeModal()">&times;</button>
        </div>
        <div class="erp-modal-body">

            <div class="erp-section-title">Customer</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Customer Name</div><div class="v">{{ vm.detail.customer_name || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Customer ID</div><div class="v">{{ vm.detail.customer_code || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Mobile No</div><div class="v">{{ vm.detail.phone || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Pincode</div><div class="v">{{ vm.detail.pincode || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Country</div><div class="v">{{ vm.detail.country || '—' }}</div></div>
            </div>

            <hr class="erp-hr">

            <div class="erp-section-title">Booking</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Room No</div><div class="v">{{ vm.detail.allotted_room_no || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Room Category</div><div class="v">{{ vm.detail.room_category || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Channel</div><div class="v">{{ vm.detail.channel_name || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Property</div><div class="v">{{ vm.detail.property_name || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Scheduled Check-In</div><div class="v">{{ vm.detail.scheduled_check_in_date || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Scheduled Check-Out</div><div class="v">{{ vm.detail.scheduled_check_out_date || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Nights</div><div class="v">{{ vm.detail.length_of_stay || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Total Guests</div><div class="v">{{ vm.detail.total_guest || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Checked-In At</div><div class="v">{{ vm.detail.checked_in_at || '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Checked-Out At</div><div class="v">{{ vm.detail.checked_out_at || '—' }}</div></div>
            </div>

            <hr class="erp-hr">

            <div class="erp-section-title">Amounts</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item"><div class="k">Total</div><div class="v">{{ vm.detail.total_amount ? ('₹' + (vm.detail.total_amount | number:2)) : '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Paid</div><div class="v">{{ vm.detail.amount_paid ? ('₹' + (vm.detail.amount_paid | number:2)) : '—' }}</div></div>
                <div class="erp-detail-item"><div class="k">Remaining</div><div class="v">{{ vm.detail.remaining_amount != null ? ('₹' + (vm.detail.remaining_amount | number:2)) : '—' }}</div></div>
            </div>

            <hr class="erp-hr">

            <div class="erp-section-title">Identity Proof</div>
            <div class="erp-detail-grid">
                <div class="erp-detail-item" ng-repeat="idn in vm.detail.identities">
                    <div class="k">{{ idn.type_label }}</div>
                    <div class="v">{{ idn.identity_number || '—' }}</div>
                    <a class="erp-doc-link" ng-if="idn.document_url" ng-href="{{ idn.document_url }}" target="_blank">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        View Document
                    </a>
                    <div class="v erp-muted" ng-if="!idn.document_url">No document uploaded</div>
                </div>
                <div class="erp-detail-item erp-muted" ng-if="!vm.detail.identities || !vm.detail.identities.length">No identity proof added</div>
            </div>
        </div>
        <div class="erp-form-foot">
            <button class="erp-btn erp-btn-ghost" ng-click="vm.closeModal()">Close</button>
            <a class="erp-btn erp-btn-primary" ng-href="<?= site_url('customers/checkin') ?>/{{ vm.detail.id }}">Check-in</a>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/angular.min.js') ?>"></script>
<script src="<?= base_url('assets/js/erp-query.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/erp-query.js') ?>"></script>
<script src="<?= base_url('assets/js/searchable-select.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/searchable-select.js') ?>"></script>
<script src="<?= base_url('assets/js/bookings.js') ?>?v=<?= @filemtime(FCPATH.'assets/js/bookings.js') ?>"></script>
</body>
</html>
