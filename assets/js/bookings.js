/* ============================================================
   Stay Management ERP — Booking Details (AngularJS 1.x)
   Lists ONLY "Room booked" bookings (status is fixed on the server;
   there is no status filter here). Columns: booking no, customer,
   room no, room category, status. Free-text search on booking/customer.
   ============================================================ */
(function () {
    'use strict';

    angular.module('bookingsApp', ['erpQuery'])
        .controller('BookingsController', ['$http', '$timeout', 'erpQuery', BookingsController]);

    function BookingsController($http, $timeout, erpQuery) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;

        vm.bookings = [];
        vm.loading  = true;
        vm.filters  = { q: '' };

        // status_code -> badge css class (status_master is the source of truth).
        var STATUS_CLASS = {
            room_booked: 'erp-badge-confirmed',
            checked_in:  'erp-badge-checkedin',
            checked_out: 'erp-badge-checkedout',
            cancelled:   'erp-badge-cancelled',
            no_show:     'erp-badge-noshow'
        };
        vm.statusClass = function (code) { return STATUS_CLASS[code] || 'erp-badge-active'; };

        // ---- API (cached: instant from cache, revalidated in the background) ----
        vm.load = function () {
            erpQuery.fetch('bookings', base + 'customers/bookings_ajax', vm.filters, {}, {
                data:    function (rows) { vm.bookings = rows; },
                loading: function (b)    { vm.loading = b; }
            });
        };

        // Debounced reload — fires 300ms after the last keystroke.
        vm.onFilter = function () {
            if (debounce) { $timeout.cancel(debounce); }
            debounce = $timeout(vm.load, 300);
        };

        vm.clearFilters = function () {
            vm.filters.q = '';
            vm.load();
        };

        // Initial load
        vm.load();
    }
})();
