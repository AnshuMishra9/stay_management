/* Room list, filters, detail modal, and deactivation (AngularJS 1.x). */
(function () {
    'use strict';

    angular.module('roomsApp', ['erpQuery'])
        .controller('RoomsController', ['$http', '$timeout', 'erpQuery', RoomsController]);

    function RoomsController($http, $timeout, erpQuery) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;

        // Consume the post-save freshness marker once.
        if (window.APP_FRESH) {
            erpQuery.invalidate('rooms');
            window.APP_FRESH = false;
        }

        vm.rooms     = [];
        vm.loading   = true;
        vm.showModal = false;
        vm.detail    = {};
        vm.filters   = {
            room_no: '', category_id: '', floor_no: '',
            housekeeping_status: '', status: ''
        };

        vm.load = function () {
            erpQuery.fetch('rooms', base + 'rooms/list_ajax', vm.filters, {}, {
                data:    function (rows) { vm.rooms = rows; },
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

        vm.hkClass = function (s) {
            switch (s) {
                case 'Available':     return 'erp-chip-green';
                case 'Not Available': return 'erp-chip-red';
                default:              return 'erp-chip-grey';
            }
        };

        vm.viewRoom = function (id) {
            $http.get(base + 'rooms/view/' + id, {
                headers: { 'X-Property-Context-Token': window.APP_PROPERTY_CONTEXT_TOKEN || '' }
            })
                .then(function (res) {
                    if (res.data && res.data.status) {
                        vm.detail = res.data.data;
                        vm.showModal = true;
                    } else {
                        alert((res.data && res.data.message) || 'Unable to load room.');
                    }
                })
                .catch(function (error) {
                    if (error && error.status === 409) {
                        window.location.assign(base + 'inventory');
                        return;
                    }
                    alert('Unable to load room.');
                });
        };

        vm.closeModal = function () {
            vm.showModal = false;
            vm.detail = {};
        };

        vm.deleteRoom = function (r) {
            var ok = window.confirm(
                'Deactivate room "' + r.room_no + '" (' + r.room_code + ')?\n\n' +
                'The record is never deleted — it stays in the database and disappears from the list.'
            );
            if (!ok) { return; }

            $http.post(base + 'rooms/delete/' + r.id, {}, {
                headers: { 'X-Property-Context-Token': window.APP_PROPERTY_CONTEXT_TOKEN || '' }
            })
                .then(function (res) {
                    if (res.data && res.data.status) {
                        erpQuery.invalidate('rooms');
                        var i = vm.rooms.indexOf(r);
                        if (i > -1) { vm.rooms.splice(i, 1); }
                        if (window.ErpToast) { window.ErpToast.show(res.data.message || 'Room deactivated.'); }
                        else { alert(res.data.message || 'Room deactivated.'); }
                    } else {
                        alert((res.data && res.data.message) || 'Deactivation failed.');
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

        vm.load();
    }
})();
