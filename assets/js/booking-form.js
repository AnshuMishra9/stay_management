/* ============================================================
   Stay Management ERP — Booking form (vanilla JS)

   1) MOBILE LOOKUP: typing a mobile number that already exists in
      `customers` pulls that customer's saved details into the form
      (still editable — saving writes any edits back to the customer).
      An unknown number just means a new customer will be created.
   2) Auto-calc: Length of Stay (nights) and Remaining Amount.
   ============================================================ */
(function () {
    'use strict';

    var base = (window.APP_BASE || '/').replace(/\/?$/, '/');

    var phoneEl = document.getElementById('bf_phone');

    // Customer fields the lookup fills in (name attribute -> element).
    var FIELDS = ['customer_name', 'pincode', 'country'];

    function field(name) { return document.querySelector('[name="' + name + '"]'); }

    // Setting .value on a <select> enhanced by searchable-select.js re-syncs its
    // trigger (the setter is hooked), so this works for plain inputs AND selects.
    function setField(name, value) {
        var el = field(name);
        if (!el) { return; }
        el.value = (value === null || value === undefined) ? '' : value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ---- Mobile lookup ----
    var lookupTimer = null;
    var autofilled  = false;   // did WE fill the fields? (so we can clear them)

    function lookup() {
        var phone = (phoneEl.value || '').trim();

        if (phone.length < 6) { return; }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', base + 'customers/lookup?phone=' + encodeURIComponent(phone), true);
        xhr.onload = function () {
            var res;
            try { res = JSON.parse(xhr.responseText); } catch (e) { return; }

            if (res && res.found && res.data) {
                FIELDS.forEach(function (f) { setField(f, res.data[f]); });
                autofilled = true;
            } else if (autofilled) {
                // Unknown number: clear anything WE auto-filled so the previous
                // customer's details don't get saved onto a new customer.
                FIELDS.forEach(function (f) { setField(f, ''); });
                autofilled = false;
            }
        };
        xhr.send();
    }

    if (phoneEl) {
        phoneEl.addEventListener('input', function () {
            if (lookupTimer) { clearTimeout(lookupTimer); }
            lookupTimer = setTimeout(lookup, 400);   // debounce while typing
        });
        phoneEl.addEventListener('blur', lookup);
    }

    // ---- Linked Room Category / Allot Room dropdowns ----
    var roomCategory = document.getElementById('bk_room_category');
    var room = document.getElementById('bk_room');
    var total = document.getElementById('bk_total');
    var paid = document.getElementById('bk_paid');
    var rem = document.getElementById('bk_remaining');

    function refreshSelect(el) {
        if (window.SearchableSelect && window.SearchableSelect.refresh) {
            window.SearchableSelect.refresh(el);
        }
    }

    function filterRoomsByCategory() {
        if (!roomCategory || !room) { return; }
        var categoryId = roomCategory.value;
        var selected = room.options[room.selectedIndex];

        Array.prototype.forEach.call(room.options, function (opt) {
            if (!opt.value) {
                opt.disabled = false;
                return;
            }
            opt.disabled = !!categoryId && opt.getAttribute('data-category-id') !== categoryId;
        });

        if (selected && selected.value && selected.disabled) {
            room.value = '';
        }
        refreshSelect(room);
    }

    function applyCategoryBasePrice() {
        if (!roomCategory || !total) { return; }
        var selected = roomCategory.options[roomCategory.selectedIndex];
        var basePrice = selected ? selected.getAttribute('data-base-price') : '';
        if (basePrice === null || basePrice === '') { return; }

        var parsedPrice = parseFloat(basePrice);
        total.value = isNaN(parsedPrice) ? basePrice : parsedPrice.toFixed(2);
        calcRemaining();
    }

    function syncCategoryFromRoom(updatePrice) {
        if (!roomCategory || !room) { return; }
        var selected = room.options[room.selectedIndex];
        var categoryId = selected ? selected.getAttribute('data-category-id') : '';
        if (categoryId) {
            roomCategory.value = categoryId;
            refreshSelect(roomCategory);
        }
        filterRoomsByCategory();
        if (updatePrice) {
            applyCategoryBasePrice();
        }
    }

    if (roomCategory && room) {
        roomCategory.addEventListener('change', function () {
            filterRoomsByCategory();
            applyCategoryBasePrice();
        });
        room.addEventListener('change', function () {
            syncCategoryFromRoom(true);
        });

        // On edit/validation reload, the allotted room is authoritative.
        if (room.value) {
            syncCategoryFromRoom(false);
        } else {
            filterRoomsByCategory();
        }
    }
    if (roomCategory && total && total.value === '') {
        applyCategoryBasePrice();
    }

    // ---- Auto-calc: Length of Stay + Remaining Amount ----
    var ci    = document.getElementById('bk_checkin');
    var co    = document.getElementById('bk_checkout');
    var los   = document.getElementById('bk_los');
    var bookingIdEl = document.querySelector('[name="booking_id"]');
    var availabilityRequest = 0;

    function num(el) { var v = parseFloat(el && el.value); return isNaN(v) ? 0 : v; }

    function calcLOS() {
        if (!ci || !co || !los) { return; }
        if (ci.value && co.value) {
            var d = Math.round((new Date(co.value) - new Date(ci.value)) / 86400000);
            los.value = d >= 0 ? d : '';
        } else {
            los.value = '';
        }
    }

    function refreshAvailableRooms() {
        if (!ci || !co || !room || !ci.value || !co.value || co.value <= ci.value) {
            return;
        }

        var requestId = ++availabilityRequest;
        var selectedRoom = room.value;
        var query = 'check_in=' + encodeURIComponent(ci.value)
            + '&check_out=' + encodeURIComponent(co.value);
        if (bookingIdEl && bookingIdEl.value) {
            query += '&booking_id=' + encodeURIComponent(bookingIdEl.value);
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', base + 'customers/available_rooms?' + query, true);
        xhr.onload = function () {
            if (requestId !== availabilityRequest) { return; }

            var res;
            try { res = JSON.parse(xhr.responseText); } catch (e) { return; }
            if (!res || !res.status || !Array.isArray(res.data)) { return; }

            room.innerHTML = '';
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '\u2014 No room \u2014';
            room.appendChild(empty);

            res.data.forEach(function (availableRoom) {
                var option = document.createElement('option');
                option.value = String(availableRoom.id);
                option.textContent = availableRoom.room_no;
                option.setAttribute('data-category-id', String(availableRoom.category_id || ''));
                room.appendChild(option);
            });

            var selectedStillAvailable = Array.prototype.some.call(room.options, function (option) {
                return option.value === selectedRoom;
            });
            room.value = selectedStillAvailable ? selectedRoom : '';
            filterRoomsByCategory();
            refreshSelect(room);
        };
        xhr.send();
    }

    function calcRemaining() {
        if (!rem) { return; }
        if ((total && total.value !== '') || (paid && paid.value !== '')) {
            rem.value = (num(total) - num(paid)).toFixed(2);
        } else {
            rem.value = '';
        }
    }

    if (ci && co) {
        ci.addEventListener('change', function () {
            calcLOS();
            refreshAvailableRooms();
        });
        co.addEventListener('change', function () {
            calcLOS();
            refreshAvailableRooms();
        });
        refreshAvailableRooms();
    }
    if (total && paid) {
        total.addEventListener('input', calcRemaining);
        paid.addEventListener('input', calcRemaining);
    }
})();
