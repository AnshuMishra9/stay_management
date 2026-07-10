/* ============================================================
   Stay Management ERP — Customers Master (AngularJS 1.x)
   Live server-side filtering, detail modal, delete with cleanup.
   ============================================================ */
(function () {
    'use strict';

    angular.module('customersApp', ['erpQuery'])
        .controller('CustomersController', ['$http', '$timeout', 'erpQuery', CustomersController]);

    function CustomersController($http, $timeout, erpQuery) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;

        // After a save/redirect (flash present) the cached data is stale — drop it once.
        // Bookings read the same rows, so clear that namespace too.
        if (window.APP_FRESH) {
            erpQuery.invalidate('customers');
            erpQuery.invalidate('bookings');
            window.APP_FRESH = false;
        }

        vm.customers = [];
        vm.loading   = true;
        vm.showModal = false;
        vm.detail    = {};
        vm.filters   = {
            customer_code: '', name: '', owner: '', phone: '',
            city: '', district: '', state: '', customer_type: '',
            booking_status: '', status: ''
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

        // ---- API (cached: instant from cache, revalidated in the background) ----
        vm.load = function () {
            erpQuery.fetch('customers', base + 'customers/list_ajax', vm.filters, {}, {
                data:    function (rows) { vm.customers = rows; },
                loading: function (b)    { vm.loading = b; }
            });
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

        // ---- Detail modal ----
        vm.viewCustomer = function (id) {
            $http.get(base + 'customers/view/' + id)
                .then(function (res) {
                    if (res.data && res.data.status) {
                        vm.detail = res.data.data;
                        vm.showModal = true;
                    } else {
                        alert((res.data && res.data.message) || 'Unable to load customer.');
                    }
                })
                .catch(function () { alert('Unable to load customer.'); });
        };

        vm.closeModal = function () {
            vm.showModal = false;
            vm.detail = {};
        };

        // ---- Delete ----
        vm.deleteCustomer = function (c) {
            var ok = window.confirm(
                'Delete customer "' + c.customer_name + '" (' + c.customer_code + ')?\n\n' +
                'This also removes their uploaded Aadhar/PAN files and cannot be undone.'
            );
            if (!ok) { return; }

            $http.post(base + 'customers/delete/' + c.id)
                .then(function (res) {
                    if (res.data && res.data.status) {
                        // Data changed → drop the cached lists (customers + bookings share rows).
                        erpQuery.invalidate('customers');
                        erpQuery.invalidate('bookings');
                        // Drop the row locally for instant feedback, then resync.
                        var i = vm.customers.indexOf(c);
                        if (i > -1) { vm.customers.splice(i, 1); }
                    } else {
                        alert((res.data && res.data.message) || 'Delete failed.');
                    }
                })
                .catch(function () { alert('Delete failed. Please try again.'); });
        };

        // Initial load
        vm.load();
    }
})();
