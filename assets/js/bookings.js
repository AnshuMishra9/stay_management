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

        // Which list this page shows (Booking Details vs Check-in Details).
        var listUrl = window.APP_LIST_URL || (base + 'customers/bookings_ajax');
        var listNs  = window.APP_LIST_NS  || 'bookings';

        // After a save/redirect (flash present) the cached lists are stale. A
        // status change moves a booking between the two lists, so drop both.
        if (window.APP_FRESH) {
            erpQuery.invalidate('bookings');
            erpQuery.invalidate('checkins');
            window.APP_FRESH = false;
        }

        vm.bookings  = [];
        vm.loading   = true;
        vm.showModal = false;
        vm.detail    = {};
        vm.filters   = { booking_no: '', customer_name: '', room_no: '', room_category: '' };

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
            erpQuery.fetch(listNs, listUrl, vm.filters, {}, {
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
            angular.forEach(vm.filters, function (v, k) { vm.filters[k] = ''; });
            vm.load();
        };

        // ---- Detail modal (eye) ----
        vm.viewBooking = function (id) {
            $http.get(base + 'customers/booking_view/' + id)
                .then(function (res) {
                    if (res.data && res.data.status) {
                        vm.detail = res.data.data;
                        if (!vm.detail.identities) { vm.detail.identities = []; }
                        vm.showModal = true;
                    } else {
                        alert((res.data && res.data.message) || 'Unable to load booking.');
                    }
                })
                .catch(function () { alert('Unable to load booking.'); });
        };

        vm.closeModal = function () {
            vm.showModal = false;
            vm.detail = {};
        };

        // Initial load
        vm.load();
    }
})();
