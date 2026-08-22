/* ============================================================
   Stay Management ERP — Customers Master (AngularJS 1.x)
   Live server-side filtering, detail modal, delete with cleanup.
   ============================================================ */
(function () {
    'use strict';

    angular.module('customersApp', ['erpQuery'])
        .config(['$httpProvider', function ($httpProvider) {
            $httpProvider.defaults.headers.common['X-Property-Context-Token'] =
                window.APP_PROPERTY_CONTEXT_TOKEN || '';
        }])
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
        // Customer-only filters — bookings live on the Booking Details page.
        vm.filters   = {
            customer_code: '', name: '', phone: '', status: ''
        };

        // ---- API (cached: instant from cache, revalidated in the background) ----
        vm.load = function () {
            var params = angular.extend({}, vm.filters);
            erpQuery.fetch('customers', base + 'customers/list_ajax', params, {}, {
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
                .catch(function (error) {
                    if (error && error.status === 409) {
                        window.location.assign(base + 'inventory');
                        return;
                    }
                    alert('Unable to load customer.');
                });
        };

        vm.closeModal = function () {
            vm.showModal = false;
            vm.detail = {};
        };

        // ---- Delete ----
        vm.deleteCustomer = function (c) {
            var ok = window.confirm(
                'Delete customer "' + c.customer_name + '" (' + c.customer_code + ')?\n\n' +
                'Customers with stay or document history will be deactivated instead of deleted.'
            );
            if (!ok) { return; }

            $http.post(base + 'customers/delete/' + c.id, {
                property_context_token: window.APP_PROPERTY_CONTEXT_TOKEN || ''
            })
                .then(function (res) {
                    if (res.data && res.data.status) {
                        // Data changed → drop the cached lists (customers + bookings share rows).
                        erpQuery.invalidate('customers');
                        erpQuery.invalidate('bookings');
                        // Drop the row locally for instant feedback, then resync.
                        if (res.data.deactivated) {
                            c.is_active = 0;
                            alert(res.data.message || 'Customer deactivated.');
                        } else {
                            var i = vm.customers.indexOf(c);
                            if (i > -1) { vm.customers.splice(i, 1); }
                        }
                    } else {
                        alert((res.data && res.data.message) || 'Delete failed.');
                    }
                })
                .catch(function (error) {
                    if (error && error.status === 409) {
                        window.location.assign(base + 'inventory');
                        return;
                    }
                    alert('Delete failed. Please try again.');
                });
        };

        // Initial load
        vm.load();
    }
})();
