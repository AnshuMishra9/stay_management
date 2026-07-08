/* ============================================================
   Stay Management ERP — Rooms Master (AngularJS 1.x)
   Live server-side filtering, detail modal, delete with cleanup.
   Mirrors customers.js.
   ============================================================ */
(function () {
    'use strict';

    angular.module('roomsApp', [])
        .controller('RoomsController', ['$http', '$timeout', RoomsController]);

    function RoomsController($http, $timeout) {
        var vm = this;
        var base = (window.APP_BASE || '/').replace(/\/?$/, '/');
        var debounce = null;

        vm.rooms     = [];
        vm.loading   = true;
        vm.showModal = false;
        vm.detail    = {};
        vm.filters   = {
            room_no: '', room_name: '', category_id: '', floor_no: '', wing: '',
            housekeeping_status: '', room_condition: '', smoking: '', status: ''
        };

        // ---- API ----
        vm.load = function () {
            vm.loading = true;
            $http.get(base + 'rooms/list_ajax', { params: vm.filters })
                .then(function (res) {
                    vm.rooms = (res.data && res.data.data) ? res.data.data : [];
                })
                .catch(function () { vm.rooms = []; })
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

        // ---- Display helpers ----
        // Build an icon URL when `icon` is an image filename (svg/png/…);
        // returns null for emoji/text so the caller can fall back to text.
        vm.iconUrl = function (icon) {
            if (icon && /\.(svg|png|jpe?g|webp|gif)$/i.test(icon)) {
                return base + 'assets/icons/' + encodeURIComponent(icon);
            }
            return null;
        };

        vm.occupancy = function (r) {
            var a = parseInt(r.max_adults, 10) || 0;
            var c = parseInt(r.max_children, 10) || 0;
            if (!a && !c) { return '—'; }
            return a + 'A' + (c ? ' + ' + c + 'C' : '');
        };

        vm.hkClass = function (s) {
            switch (s) {
                case 'Clean':          return 'erp-chip-green';
                case 'Inspected':      return 'erp-chip-blue';
                case 'Dirty':          return 'erp-chip-amber';
                case 'Out of Service': return 'erp-chip-red';
                default:               return 'erp-chip-grey';
            }
        };

        vm.condClass = function (s) {
            switch (s) {
                case 'Good':              return 'erp-chip-green';
                case 'Fair':              return 'erp-chip-amber';
                case 'Under Maintenance': return 'erp-chip-blue';
                case 'Damaged':           return 'erp-chip-red';
                default:                  return 'erp-chip-grey';
            }
        };

        // ---- Detail modal ----
        vm.viewRoom = function (id) {
            $http.get(base + 'rooms/view/' + id)
                .then(function (res) {
                    if (res.data && res.data.status) {
                        vm.detail = res.data.data;
                        if (!vm.detail.amenities) { vm.detail.amenities = []; }
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
                'This also removes its uploaded image and cannot be undone.'
            );
            if (!ok) { return; }

            $http.post(base + 'rooms/delete/' + r.id)
                .then(function (res) {
                    if (res.data && res.data.status) {
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
