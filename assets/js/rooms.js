/* ============================================================
   Stay Management ERP — Rooms Master (AngularJS 1.x)
   Live server-side filtering, detail modal, delete with cleanup.
   Mirrors customers.js.
   ============================================================ */
(function () {
    'use strict';

    angular.module('roomsApp', ['erpQuery'])
        .controller('RoomsController', ['$http', '$timeout', 'erpQuery', RoomsController]);

    function RoomsController($http, $timeout, erpQuery) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;

        // After a save/redirect (flash present) the cached data is stale — drop it once.
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

        // ---- API (cached: instant from cache, revalidated in the background) ----
        vm.load = function () {
            erpQuery.fetch('rooms', base + 'rooms/list_ajax', vm.filters, {}, {
                data:    function (rows) { vm.rooms = rows; },
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

        // ---- Display helpers ----
        vm.hkClass = function (s) {
            switch (s) {
                case 'Available':     return 'erp-chip-green';
                case 'Not Available': return 'erp-chip-red';
                default:              return 'erp-chip-grey';
            }
        };

        // ---- Detail modal ----
        vm.viewRoom = function (id) {
            $http.get(base + 'rooms/view/' + id)
                .then(function (res) {
                    if (res.data && res.data.status) {
                        vm.detail = res.data.data;
                        vm.showModal = true;
                    } else {
                        alert((res.data && res.data.message) || 'Unable to load room.');
                    }
                })
                .catch(function () { alert('Unable to load room.'); });
        };

        vm.closeModal = function () {
            vm.showModal = false;
            vm.detail = {};
        };

        // ---- Delete ----
        vm.deleteRoom = function (r) {
            var ok = window.confirm(
                'Delete room "' + r.room_no + '" (' + r.room_code + ')?\n\n' +
                'This cannot be undone.'
            );
            if (!ok) { return; }

            $http.post(base + 'rooms/delete/' + r.id)
                .then(function (res) {
                    if (res.data && res.data.status) {
                        erpQuery.invalidate('rooms');   // data changed → drop cached lists
                        // Drop the row locally for instant feedback.
                        var i = vm.rooms.indexOf(r);
                        if (i > -1) { vm.rooms.splice(i, 1); }
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
