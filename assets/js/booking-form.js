/* Shared booking form behavior; init() supports full-page and modal forms. */
(function (global) {
    'use strict';

    var base = (global.APP_BASE || '/').replace(/\/?$/, '/');

    // Bind every booking request to the server-authorized property context.
    function addPropertyContextHeader(xhr) {
        xhr.setRequestHeader(
            'X-Property-Context-Token',
            global.APP_PROPERTY_CONTEXT_TOKEN || ''
        );
    }

    function handleStaleContext(xhr) {
        if (xhr.status !== 409) { return false; }
        global.alert('The active property changed. This form can no longer be submitted.');
        global.location.assign(base + 'inventory');
        return true;
    }

    function initForm(form) {
        if (!form || form.dataset.bookingFormReady) { return; }
        form.dataset.bookingFormReady = '1';

        var phoneEl = form.querySelector('#bf_phone');
        var customerFields = ['customer_name', 'pincode', 'country'];

        function field(name) {
            return form.querySelector('[name="' + name + '"]');
        }

        function setField(name, value) {
            var el = field(name);
            if (!el) { return; }
            el.value = (value === null || value === undefined) ? '' : value;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        var lookupTimer = null;
        var autofilled = false;

        function lookup() {
            var phone = phoneEl ? (phoneEl.value || '').trim() : '';
            if (phone.length < 6) { return; }

            var xhr = new XMLHttpRequest();
            xhr.open('GET', base + 'customers/lookup?phone=' + encodeURIComponent(phone), true);
            addPropertyContextHeader(xhr);
            xhr.onload = function () {
                if (handleStaleContext(xhr)) { return; }
                var response;
                try { response = JSON.parse(xhr.responseText); } catch (error) { return; }

                if (response && response.found && response.data) {
                    customerFields.forEach(function (name) {
                        setField(name, response.data[name]);
                    });
                    autofilled = true;
                } else if (autofilled) {
                    customerFields.forEach(function (name) { setField(name, ''); });
                    autofilled = false;
                }
            };
            xhr.send();
        }

        if (phoneEl) {
            phoneEl.addEventListener('input', function () {
                if (lookupTimer) { global.clearTimeout(lookupTimer); }
                lookupTimer = global.setTimeout(lookup, 400);
            });
            phoneEl.addEventListener('blur', lookup);
        }

        var roomCategory = form.querySelector('#bk_room_category');
        var room = form.querySelector('#bk_room');
        var total = form.querySelector('#bk_total');
        var paid = form.querySelector('#bk_paid');
        var remaining = form.querySelector('#bk_remaining');
        var lengthOfStay = form.querySelector('#bk_los');

        function refreshSelect(el) {
            if (global.SearchableSelect && global.SearchableSelect.refresh) {
                global.SearchableSelect.refresh(el);
            }
        }

        function filterRoomsByCategory() {
            if (!roomCategory || !room) { return; }
            var categoryId = roomCategory.value;
            var selected = room.options[room.selectedIndex];

            Array.prototype.forEach.call(room.options, function (option) {
                if (!option.value) {
                    option.disabled = false;
                    return;
                }
                option.disabled = !!categoryId
                    && option.getAttribute('data-category-id') !== categoryId;
            });

            if (selected && selected.value && selected.disabled) {
                room.value = '';
            }
            refreshSelect(room);
        }

        function numberValue(el) {
            var value = parseFloat(el && el.value);
            return isNaN(value) ? 0 : value;
        }

        function calculateRemaining() {
            if (!remaining) { return; }
            if ((total && total.value !== '') || (paid && paid.value !== '')) {
                remaining.value = (numberValue(total) - numberValue(paid)).toFixed(2);
            } else {
                remaining.value = '';
            }
        }

        function applyStayPrice() {
            if (!total) { return; }

            var selectedRoom = room && room.options[room.selectedIndex];
            var roomPrice = selectedRoom && selectedRoom.value
                ? selectedRoom.getAttribute('data-selling-price')
                : '';
            var parsedPrice = parseFloat(roomPrice);
            if (isNaN(parsedPrice)) {
                total.value = '';
                calculateRemaining();
                return;
            }

            var nights = lengthOfStay && lengthOfStay.value !== ''
                ? parseFloat(lengthOfStay.value)
                : 1;
            if (isNaN(nights) || nights < 1) { nights = 1; }

            total.value = (parsedPrice * nights).toFixed(2);
            calculateRemaining();
        }

        function syncCategoryFromRoom(updatePrice) {
            if (!roomCategory || !room) { return; }
            var selected = room.options[room.selectedIndex];
            var categoryId = selected ? selected.getAttribute('data-category-id') : '';
            // Clear a stale category before filtering an uncategorized room.
            roomCategory.value = categoryId || '';
            refreshSelect(roomCategory);
            filterRoomsByCategory();
            if (updatePrice) { applyStayPrice(); }
        }

        if (roomCategory && room) {
            roomCategory.addEventListener('change', function () {
                filterRoomsByCategory();
                applyStayPrice();
            });
            room.addEventListener('change', function () {
                syncCategoryFromRoom(true);
            });

            if (room.value) {
                syncCategoryFromRoom(false);
            } else {
                filterRoomsByCategory();
            }
        }

        var checkIn = form.querySelector('#bk_checkin');
        var checkOut = form.querySelector('#bk_checkout');
        var bookingIdEl = form.querySelector('[name="booking_id"]');
        var availabilityRequest = 0;
        var selectedCheckoutDate = checkOut && checkOut.value
            ? checkOut.value.slice(0, 10)
            : '';

        // UTC ordinals keep night counts independent of the browser timezone.
        function calendarOrdinal(value) {
            var match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');
            if (!match) { return null; }
            return Date.UTC(+match[1], +match[2] - 1, +match[3]) / 86400000;
        }

        function applyDefaultCheckoutTime() {
            if (!checkOut || checkOut.type !== 'datetime-local'
                || (bookingIdEl && bookingIdEl.value)) {
                return;
            }

            var date = checkOut.value ? checkOut.value.slice(0, 10) : '';
            if (date && date !== selectedCheckoutDate) {
                checkOut.value = date + 'T11:00';
            }
            selectedCheckoutDate = date;
        }

        function calculateLengthOfStay(updatePrice) {
            if (!checkIn || !checkOut || !lengthOfStay) { return; }
            var start = calendarOrdinal(checkIn.value);
            var end = calendarOrdinal(checkOut.value);
            lengthOfStay.value = start !== null && end !== null && end > start
                ? String(end - start)
                : '';
            if (updatePrice !== false) { applyStayPrice(); }
        }

        function refreshAvailableRooms() {
            if (!checkIn || !checkOut || !room || !checkIn.value || !checkOut.value) {
                return;
            }
            var start = calendarOrdinal(checkIn.value);
            var end = calendarOrdinal(checkOut.value);
            if (start === null || end === null || end <= start) { return; }

            var requestId = ++availabilityRequest;
            var selectedRoom = room.value;
            var query = 'check_in=' + encodeURIComponent(checkIn.value)
                + '&check_out=' + encodeURIComponent(checkOut.value);
            if (bookingIdEl && bookingIdEl.value) {
                query += '&booking_id=' + encodeURIComponent(bookingIdEl.value);
            }

            var xhr = new XMLHttpRequest();
            xhr.open('GET', base + 'customers/available_rooms?' + query, true);
            addPropertyContextHeader(xhr);
            xhr.onload = function () {
                // Ignore a response superseded by a newer availability request.
                if (requestId !== availabilityRequest) { return; }
                if (handleStaleContext(xhr)) { return; }

                var response;
                try { response = JSON.parse(xhr.responseText); } catch (error) { return; }
                if (!response || !response.status || !Array.isArray(response.data)) { return; }

                room.innerHTML = '';
                var empty = document.createElement('option');
                empty.value = '';
                empty.textContent = '\u2014 No room \u2014';
                room.appendChild(empty);

                response.data.forEach(function (availableRoom) {
                    var option = document.createElement('option');
                    option.value = String(availableRoom.id);
                    option.textContent = availableRoom.room_no;
                    option.setAttribute('data-category-id', String(availableRoom.category_id || ''));
                    option.setAttribute(
                        'data-selling-price',
                        availableRoom.selling_price === null
                            || availableRoom.selling_price === undefined
                            ? ''
                            : String(availableRoom.selling_price)
                    );
                    room.appendChild(option);
                });

                var selectedStillAvailable = Array.prototype.some.call(
                    room.options,
                    function (option) { return option.value === selectedRoom; }
                );
                room.value = selectedStillAvailable ? selectedRoom : '';
                if (room.value) { syncCategoryFromRoom(false); }
                filterRoomsByCategory();
                refreshSelect(room);
                if (selectedRoom && !selectedStillAvailable) { applyStayPrice(); }
            };
            xhr.send();
        }

        if (checkIn && checkOut) {
            checkIn.addEventListener('change', function () {
                calculateLengthOfStay(true);
                refreshAvailableRooms();
            });
            checkOut.addEventListener('input', applyDefaultCheckoutTime);
            checkOut.addEventListener('change', function () {
                applyDefaultCheckoutTime();
                calculateLengthOfStay(true);
                refreshAvailableRooms();
            });
            calculateLengthOfStay(!!total && total.value === '');
            refreshAvailableRooms();
        } else if (roomCategory && total && total.value === '') {
            applyStayPrice();
        }

        if (total && paid) {
            total.addEventListener('input', calculateRemaining);
            paid.addEventListener('input', calculateRemaining);
            calculateRemaining();
        }
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var forms = [];
        if (scope.matches && scope.matches('[data-booking-form]')) {
            forms.push(scope);
        }
        Array.prototype.forEach.call(
            scope.querySelectorAll('[data-booking-form]'),
            function (form) { forms.push(form); }
        );
        forms.forEach(initForm);
    }

    global.BookingForm = { init: init };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }
})(window);
