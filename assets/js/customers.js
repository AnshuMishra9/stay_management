/* Customer list, filters, detail modal, and deactivation (AngularJS 1.x). */
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

        // Booking lists reuse customer rows, so both caches must be invalidated.
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
            customer_code: '', name: '', phone: '', status: ''
        };

        vm.load = function () {
            var params = angular.extend({}, vm.filters);
            erpQuery.fetch('customers', base + 'customers/list_ajax', params, {}, {
                data:    function (rows) { vm.customers = rows; },
                loading: function (b)    { vm.loading = b; }
            });
        };

        // Coalesce rapid filter changes into one request.
        vm.onFilter = function () {
            if (debounce) { $timeout.cancel(debounce); }
            debounce = $timeout(vm.load, 300);
        };

        vm.clearFilters = function () {
            angular.forEach(vm.filters, function (v, k) { vm.filters[k] = ''; });
            vm.load();
        };

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

        vm.deleteCustomer = function (c) {
var ok = window.confirm(
'Deactivate customer "' + c.customer_name + '" (' + c.customer_code + ')?\n\n' +
'The record is never deleted — it stays in the database and disappears from the list.'
            );
            if (!ok) { return; }

            $http.post(base + 'customers/delete/' + c.id, {
                property_context_token: window.APP_PROPERTY_CONTEXT_TOKEN || ''
            })
                .then(function (res) {
                    if (res.data && res.data.status) {
                        // Deactivation changes both customer and booking projections.
                        erpQuery.invalidate('customers');
                        erpQuery.invalidate('bookings');
                        var i = vm.customers.indexOf(c);
                        if (i > -1) { vm.customers.splice(i, 1); }
                        if (window.ErpToast) { window.ErpToast.show(res.data.message || 'Customer deactivated.'); }
                        else { alert(res.data.message || 'Customer deactivated.'); }
                    } else {
                        alert((res.data && res.data.message) || 'Deactivation failed.');
                    }
                })
                .catch(function (error) {
                    if (error && error.status === 409) {
                        window.location.assign(base + 'inventory');
                        return;
                    }
                    alert('Deactivation failed. Please try again.');
                });
        };

        vm.load();
    }
})();
