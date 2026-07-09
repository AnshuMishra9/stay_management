/* ============================================================
   Stay Management ERP — Booking Details (AngularJS 1.x)
   Booking-centric list over customers with live server-side
   filtering: search, status, channel, and two date-range pickers
   (check-in range + check-out range) powered by flatpickr.
   ============================================================ */
(function () {
    'use strict';

    angular.module('bookingsApp', [])
        .controller('BookingsController', ['$http', '$timeout', BookingsController]);

    function BookingsController($http, $timeout) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;
        var fpCheckin = null, fpCheckout = null;

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
            if (fpCheckin)  { fpCheckin.clear(false); }   // reset picker, don't fire onChange
            if (fpCheckout) { fpCheckout.clear(false); }
            vm.load();
        };

        // ---- Date-range pickers (flatpickr) ----
        // A single range widget fills two filter keys (…_from / …_to).
        function ymd(d) {
            return d.getFullYear() + '-' +
                ('0' + (d.getMonth() + 1)).slice(-2) + '-' +
                ('0' + d.getDate()).slice(-2);
        }

        function initRange(id, fromKey, toKey) {
            var el = document.getElementById(id);
            if (!el || typeof flatpickr === 'undefined') { return null; }

            // Apply the current selection to the filters:
            //   2 dates -> full range   |   1 date -> that single day (from == to)
            //   0 dates -> cleared. A no-op guard avoids redundant reloads.
            function apply(dates) {
                var from = dates.length ? ymd(dates[0]) : '';
                var to   = dates.length ? ymd(dates[dates.length - 1]) : '';
                if (vm.filters[fromKey] === from && vm.filters[toKey] === to) { return; }
                vm.filters[fromKey] = from;
                vm.filters[toKey]   = to;
                $timeout(function () { vm.load(); });   // apply inside a digest
            }

            return flatpickr(el, {
                mode: 'range',
                dateFormat: 'Y-m-d',        // internal value (kept as-is for the backend)
                altInput: true,             // show a friendly formatted field to the user
                altFormat: 'd/m/Y',         // user sees dd/mm/yyyy  (e.g. 11/07/2026)
                altInputClass: 'erp-input', // keep the ERP input styling on the visible field
                allowInput: false,
                onChange: function (dates) {
                    if (dates.length === 2) { apply(dates); }   // instant apply on a full range
                },
                onClose: function (dates) {
                    apply(dates);   // finalize on close — this is what makes a SINGLE date work
                }
            });
        }

        // Init after the view is in the DOM.
        $timeout(function () {
            fpCheckin  = initRange('bk_checkin_range',  'checkin_from',  'checkin_to');
            fpCheckout = initRange('bk_checkout_range', 'checkout_from', 'checkout_to');
        });

        // Initial load
        vm.load();
    }
})();
