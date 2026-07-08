/* ============================================================
   Stay Management ERP — Booking Details (AngularJS 1.x)
   Booking-centric list over customers with live server-side
   filtering by check-in/out date, status, channel and search.
   ============================================================ */
(function () {
    'use strict';

    angular.module('bookingsApp', [])
        .controller('BookingsController', ['$http', '$timeout', BookingsController]);

    function BookingsController($http, $timeout) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;

        vm.bookings = [];
        vm.loading  = true;
        vm.filters  = {
            q: '', booking_status: '', booking_channel_id: '',
            checkin_from: '', checkin_to: '', checkout_from: '', checkout_to: ''
        };

        // Booking status value -> { label, css class } for the list badge.
        var BOOKING_STATUS = {
            enquiry:     { label: 'Enquiry',     cls: 'erp-badge-enquiry' },
            confirmed:   { label: 'Confirmed',   cls: 'erp-badge-confirmed' },
            checked_in:  { label: 'Checked In',  cls: 'erp-badge-checkedin' },
            checked_out: { label: 'Checked Out', cls: 'erp-badge-checkedout' },
            cancelled:   { label: 'Cancelled',   cls: 'erp-badge-cancelled' },
            no_show:     { label: 'No Show',     cls: 'erp-badge-noshow' }
        };
        vm.bookingLabel = function (s) { return (BOOKING_STATUS[s] && BOOKING_STATUS[s].label) || s; };
        vm.bookingClass = function (s) { return (BOOKING_STATUS[s] && BOOKING_STATUS[s].cls) || ''; };

        // ---- API ----
        vm.load = function () {
            vm.loading = true;
            $http.get(base + 'customers/bookings_ajax', { params: vm.filters })
                .then(function (res) {
                    vm.bookings = (res.data && res.data.data) ? res.data.data : [];
                })
                .catch(function () { vm.bookings = []; })
                .finally(function () { vm.loading = false; });
        };

        // Debounced reload — fires 300ms after the last keystroke/selection.
        vm.onFilter = function () {
            if (debounce) { $timeout.cancel(debounce); }
            debounce = $timeout(vm.load, 300);
        };

        vm.clearFilters = function () {
            angular.forEach(vm.filters, function (v, k) { vm.filters[k] = ''; });
            vm.load();
        };

        // Initial load
        vm.load();
    }
})();
