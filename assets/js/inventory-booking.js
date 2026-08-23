/* ============================================================
   Inventory night-range selection and in-page New Booking modal.

   A selected calendar cell is one occupied night. An inclusive selection
   D1..D2 is submitted as [D1, D2 + 1 day), so the checkout date is free.
   ============================================================ */
(function (global) {
    'use strict';

    var SELECTION_STORAGE_KEY = 'stay.inventory.booking.selection.v2:'
        + String(global.APP_CONTEXT_KEY || 'no-context');
    var SELECTION_MAX_AGE_MS = 4 * 60 * 60 * 1000;

    function pad(value) { return String(value).padStart(2, '0'); }

    function parseDate(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        return match ? { year: +match[1], month: +match[2], day: +match[3] } : null;
    }

    function addDays(value, amount) {
        var parts = parseDate(value);
        if (!parts) { return ''; }
        var date = new Date(Date.UTC(parts.year, parts.month - 1, parts.day + amount));
        return date.getUTCFullYear() + '-' + pad(date.getUTCMonth() + 1) + '-' + pad(date.getUTCDate());
    }

    function utcDay(value) {
        var parts = parseDate(value);
        if (!parts) { return null; }
        var timestamp = Date.UTC(parts.year, parts.month - 1, parts.day);
        var date = new Date(timestamp);
        if (
            date.getUTCFullYear() !== parts.year
            || date.getUTCMonth() + 1 !== parts.month
            || date.getUTCDate() !== parts.day
        ) {
            return null;
        }
        return Math.floor(timestamp / 86400000);
    }

    function inclusiveNightCount(first, last) {
        var start = utcDay(first);
        var end = utcDay(last);
        return start === null || end === null || end < start ? 0 : end - start + 1;
    }

    function rangeDates(first, last) {
        var start = first <= last ? first : last;
        var end = first <= last ? last : first;
        var dates = [];
        var cursor = start;
        var safety = 0;
        while (cursor && cursor <= end && safety < 3700) {
            dates.push(cursor);
            if (cursor === end) { break; }
            cursor = addDays(cursor, 1);
            safety++;
        }
        return dates;
    }

    function formatDate(value) {
        var parts = parseDate(value);
        if (!parts) { return value; }
        var date = new Date(parts.year, parts.month - 1, parts.day, 12, 0, 0);
        try {
            return new Intl.DateTimeFormat('en-IN', {
                day: '2-digit', month: 'short', year: 'numeric'
            }).format(date);
        } catch (error) {
            return value;
        }
    }

    function localToday() {
        var now = new Date();
        return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    }

    function init(root) {
        var doc = root && root.querySelectorAll ? root : document;
        var config = global.INVENTORY_BOOKING_CONFIG || {};
        var slots = Array.prototype.slice.call(doc.querySelectorAll('.inv-room-slot'));
        var popup = doc.getElementById('invSelectionPopup');
        var popupRoom = doc.getElementById('invSelectionRoom');
        var popupDates = doc.getElementById('invSelectionDates');
        var popupNights = doc.getElementById('invSelectionNights');
        var rangeStartInput = doc.getElementById('invSelectionStartDate');
        var rangeEndInput = doc.getElementById('invSelectionEndDate');
        var popupError = doc.getElementById('invSelectionError');
        var clearButton = doc.getElementById('invSelectionClear');
        var createButton = doc.getElementById('invCreateBooking');
        var backdrop = doc.getElementById('invBookingBackdrop');
        var modal = doc.getElementById('invBookingModal');
        var modalBody = doc.getElementById('invBookingModalBody');
        var modalClose = doc.getElementById('invBookingClose');
        var detailBackdrop = doc.getElementById('invGuestBackdrop');
        var detailModal = doc.getElementById('invGuestModal');
        var detailBody = doc.getElementById('invGuestModalBody');
        var detailClose = doc.getElementById('invGuestClose');
        var detailAction = doc.getElementById('invGuestAction');
        var detailTitle = doc.getElementById('invGuestModalTitle');
        var detailSubtitle = doc.getElementById('invGuestModalSubtitle');

        if (!popup || !createButton || !backdrop || !modalBody || !config.formUrl) {
            return;
        }

        var slotIndex = {};
        slots.forEach(function (slot) {
            var roomId = slot.getAttribute('data-room-id');
            var date = slot.getAttribute('data-date');
            if (!slotIndex[roomId]) { slotIndex[roomId] = {}; }
            slotIndex[roomId][date] = slot;
        });

        var selection = null;
        var paintedSlots = [];
        var modalInvoker = null;
        var loadRequest = 0;
        var rangeCheckRequest = 0;
        var detailInvoker = null;
        var detailRequest = 0;
        var detailEnabled = !!(
            detailBackdrop && detailModal && detailBody && detailClose
            && detailAction && detailTitle && detailSubtitle && config.detailUrl
        );

        function syncBodyModalState() {
            var bookingOpen = backdrop && !backdrop.hidden;
            var detailOpen = detailBackdrop && !detailBackdrop.hidden;
            doc.body.classList.toggle('inv-modal-open', bookingOpen || detailOpen);
        }

        function effectiveToday() {
            var browserToday = localToday();
            return config.today && config.today > browserToday
                ? config.today
                : browserToday;
        }

        function forgetStoredSelection() {
            try {
                global.sessionStorage.removeItem(SELECTION_STORAGE_KEY);
            } catch (error) {
                // Storage may be unavailable in a private/restricted browser.
            }
        }

        function persistSelection() {
            if (!selection) {
                forgetStoredSelection();
                return;
            }
            try {
                global.sessionStorage.setItem(SELECTION_STORAGE_KEY, JSON.stringify({
                    roomId: selection.roomId,
                    roomNo: selection.roomNo,
                    categoryName: selection.categoryName || '',
                    anchor: selection.anchor,
                    start: selection.start,
                    end: selection.end,
                    savedAt: Date.now()
                }));
            } catch (error) {
                // The range still works on this page when storage is blocked.
            }
        }

        function restoreStoredSelection() {
            try {
                var saved = JSON.parse(global.sessionStorage.getItem(SELECTION_STORAGE_KEY) || 'null');
                var valid = saved
                    && saved.roomId
                    && addDays(saved.start, 0) === saved.start
                    && addDays(saved.end, 0) === saved.end
                    && saved.end >= saved.start
                    && saved.start >= effectiveToday()
                    && Date.now() - Number(saved.savedAt || 0) <= SELECTION_MAX_AGE_MS;
                if (!valid) {
                    forgetStoredSelection();
                    return null;
                }
                return {
                    roomId: String(saved.roomId),
                    roomNo: String(saved.roomNo || saved.roomId),
                    categoryName: String(saved.categoryName || ''),
                    anchor: addDays(saved.anchor, 0) === saved.anchor ? saved.anchor : saved.start,
                    start: saved.start,
                    end: saved.end,
                    invoker: null
                };
            } catch (error) {
                forgetStoredSelection();
                return null;
            }
        }

        function hideError() {
            popupError.hidden = true;
            popupError.textContent = '';
        }

        function showError(message) {
            popupError.textContent = message;
            popupError.hidden = false;
            popup.hidden = false;
        }

        function paintSelection() {
            paintedSlots.forEach(function (slot) {
                slot.classList.remove('is-range-selected', 'is-range-start', 'is-range-end');
                var cell = slot.closest('.inv-cell');
                if (cell) { cell.classList.remove('is-range-selected'); }
                if (slot.tagName === 'BUTTON') { slot.setAttribute('aria-pressed', 'false'); }
            });
            paintedSlots = [];

            if (!selection) { return; }
            var roomSlots = slotIndex[selection.roomId] || {};
            Object.keys(roomSlots).forEach(function (date) {
                if (date < selection.start || date > selection.end) { return; }
                var slot = roomSlots[date];
                slot.classList.add('is-range-selected');
                var cell = slot.closest('.inv-cell');
                if (cell) { cell.classList.add('is-range-selected'); }
                if (date === selection.start) { slot.classList.add('is-range-start'); }
                if (date === selection.end) { slot.classList.add('is-range-end'); }
                if (slot.tagName === 'BUTTON') { slot.setAttribute('aria-pressed', 'true'); }
                paintedSlots.push(slot);
            });
        }

        function renderSelection() {
            if (!selection) {
                popup.hidden = true;
                hideError();
                paintSelection();
                forgetStoredSelection();
                return;
            }

            var checkout = addDays(selection.end, 1);
            var nights = inclusiveNightCount(selection.start, selection.end);
            popupRoom.textContent = 'Room ' + selection.roomNo;
            popupDates.textContent = 'Check-in ' + formatDate(selection.start)
                + ' \u2192 Check-out ' + formatDate(checkout) + ', 11:00 AM';
            popupNights.textContent = nights + (nights === 1 ? ' night' : ' nights')
                + ' \u00b7 Checkout ' + formatDate(checkout) + ', 11 AM';
            if (rangeStartInput) { rangeStartInput.value = selection.start; }
            if (rangeEndInput) {
                rangeEndInput.value = selection.end;
                rangeEndInput.min = selection.start;
                rangeEndInput.removeAttribute('max');
            }
            createButton.textContent = nights === 1 ? 'Book 1 Night' : 'Book ' + nights + ' Nights';
            popup.hidden = false;
            hideError();
            paintSelection();
            persistSelection();
        }

        function clearSelection(restoreFocus) {
            var invoker = selection && selection.invoker;
            rangeCheckRequest++;
            createButton.disabled = false;
            selection = null;
            renderSelection();
            if (restoreFocus && invoker && doc.contains(invoker)) { invoker.focus(); }
        }

        function firstVisibleBlockedDate(roomId, start, end) {
            var roomSlots = slotIndex[roomId] || {};
            var visibleDates = Object.keys(roomSlots).sort();
            for (var index = 0; index < visibleDates.length; index++) {
                var date = visibleDates[index];
                if (
                    date >= start
                    && date <= end
                    && roomSlots[date].getAttribute('data-bookable') !== '1'
                ) {
                    return date;
                }
            }
            return null;
        }

        function selectSlot(button) {
            var roomId = button.getAttribute('data-room-id');
            var date = button.getAttribute('data-date');
            // A tab left open across midnight must not offer yesterday. Reload
            // so both the visual status and server-provided hotel date refresh.
            if (date < effectiveToday()) {
                global.location.reload();
                return;
            }

            if (!selection || selection.roomId !== roomId) {
                rangeCheckRequest++;
                createButton.disabled = false;
                selection = {
                    roomId: roomId,
                    roomNo: button.getAttribute('data-room-no') || roomId,
                    categoryName: button.getAttribute('data-category-name') || '',
                    anchor: date,
                    start: date,
                    end: date,
                    invoker: button
                };
                renderSelection();
                return;
            }

            var candidateStart = selection.anchor <= date ? selection.anchor : date;
            var candidateEnd = selection.anchor <= date ? date : selection.anchor;
            var blocked = firstVisibleBlockedDate(roomId, candidateStart, candidateEnd);

            if (blocked) {
                showError(
                    'This range includes ' + formatDate(blocked)
                    + ', which is booked or cannot be booked. Select a continuous available range.'
                );
                return;
            }

            validateAndApplyRange(
                roomId,
                candidateStart,
                candidateEnd,
                button
            );
        }

        function rangeCheckUrl(roomId, start, end) {
            return config.formUrl
                + '?room_id=' + encodeURIComponent(roomId)
                + '&check_in=' + encodeURIComponent(start)
                + '&check_out=' + encodeURIComponent(addDays(end, 1))
                + '&check_only=1';
        }

        function validateAndApplyRange(roomId, start, end, invoker) {
            var requestId = ++rangeCheckRequest;
            createButton.disabled = true;
            hideError();

            global.fetch(rangeCheckUrl(roomId, start, end), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Property-Context-Token': global.APP_PROPERTY_CONTEXT_TOKEN || ''
                }
            }).then(function (response) {
                return response.json().then(function (payload) {
                    return { response: response, payload: payload };
                });
            }).then(function (result) {
                if (requestId !== rangeCheckRequest) { return; }
                if (!result.response.ok || !result.payload || !result.payload.status) {
                    throw new Error(
                        result.payload && result.payload.message
                            ? result.payload.message
                            : 'This room is not available for the complete selected range.'
                    );
                }

                selection.start = start;
                selection.end = end;
                selection.anchor = start;
                selection.invoker = invoker;
                renderSelection();
            }).catch(function (error) {
                if (requestId !== rangeCheckRequest) { return; }
                if (rangeEndInput && selection) { rangeEndInput.value = selection.end; }
                showError(
                    error.message
                        || 'The complete date range could not be checked. Please try again.'
                );
            }).then(function () {
                if (requestId === rangeCheckRequest) {
                    createButton.disabled = false;
                }
            });
        }

        // The number button remains keyboard accessible, while event delegation
        // makes the full visible cell (including the "Available" label) clickable.
        doc.addEventListener('click', function (event) {
            if (!event.target || !event.target.closest) { return; }
            var occupiedButton = event.target.closest('button.inv-room-slot[data-booking-id]');
            if (!occupiedButton) {
                var occupiedCell = event.target.closest('td.inv-cell-occupied');
                occupiedButton = occupiedCell && occupiedCell.querySelector('button.inv-room-slot[data-booking-id]');
            }
            if (occupiedButton) {
                event.preventDefault();
                openGuestDetails(occupiedButton);
                return;
            }

            var button = event.target.closest('button.inv-room-slot[data-bookable="1"]');
            if (!button) {
                var cell = event.target.closest('td.inv-cell-bookable');
                button = cell && cell.querySelector('button.inv-room-slot[data-bookable="1"]');
            }
            if (button) {
                selectSlot(button);
                // Native button clicks generated by Enter/Space have detail 0.
                // Move keyboard users straight to the newly revealed range/action bar.
                if (event.detail === 0 && rangeEndInput && selection) {
                    rangeEndInput.focus();
                }
            }
        });

        if (rangeEndInput) {
            rangeEndInput.addEventListener('change', function () {
                if (!selection) { return; }
                var requestedEnd = rangeEndInput.value;
                var previousEnd = selection.end;
                if (!parseDate(requestedEnd) || requestedEnd < selection.start) {
                    rangeEndInput.value = previousEnd;
                    showError('Last night must be on or after the first night.');
                    return;
                }

                var blocked = firstVisibleBlockedDate(
                    selection.roomId,
                    selection.start,
                    requestedEnd
                );
                if (blocked) {
                    rangeEndInput.value = previousEnd;
                    showError(
                        'This range includes ' + formatDate(blocked)
                        + ', which is already booked.'
                    );
                    return;
                }

                validateAndApplyRange(
                    selection.roomId,
                    selection.start,
                    requestedEnd,
                    rangeEndInput
                );
            });
        }

        function loadingMarkup() {
            return '<div class="inv-booking-loading" role="status">'
                + '<span class="erp-spinner" aria-hidden="true"></span>'
                + '<span>Loading booking form&hellip;</span>'
                + '</div>';
        }

        function modalError(message) {
            modalBody.innerHTML = '<div class="erp-alert erp-alert-danger inv-booking-load-error" role="alert">'
                + escapeHtml(message) + '</div>';
        }

        function escapeHtml(value) {
            var el = doc.createElement('div');
            el.textContent = value || '';
            return el.innerHTML;
        }

        function detailValue(value) {
            if (value === null || value === undefined || value === '') { return '&mdash;'; }
            return escapeHtml(String(value));
        }

        function detailDate(value) {
            if (!value) { return '&mdash;'; }
            return escapeHtml(formatDate(String(value).slice(0, 10)));
        }

        function detailDateTime(value) {
            if (!value) { return '&mdash;'; }
            var raw = String(value);
            var date = formatDate(raw.slice(0, 10));
            var timeMatch = /[ T](\d{2}:\d{2})/.exec(raw);
            return escapeHtml(date + (timeMatch ? ' \u00b7 ' + timeMatch[1] : ''));
        }

        function detailItem(label, value, valueIsHtml) {
            return '<div class="erp-detail-item">'
                + '<div class="k">' + escapeHtml(label) + '</div>'
                + '<div class="v">' + (valueIsHtml ? value : detailValue(value)) + '</div>'
                + '</div>';
        }

        function detailStatusClass(status) {
            var classes = {
                room_booked: 'erp-badge-confirmed',
                checked_in: 'erp-badge-checkedin',
                checked_out: 'erp-badge-checkedout',
                cancelled: 'erp-badge-cancelled',
                no_show: 'erp-badge-noshow'
            };
            return classes[status] || 'erp-badge-active';
        }

        function detailStatusLabel(status) {
            var labels = {
                room_booked: 'Room Booked',
                checked_in: 'Checked In',
                checked_out: 'Checked Out',
                cancelled: 'Cancelled',
                no_show: 'No Show'
            };
            return labels[status] || 'Current status';
        }

        function workflowAction(status, bookingId, workflow) {
            var definitions = {
                room_booked: { label: 'Check-in' },
                checked_in: { label: 'Check-out' },
                checked_out: { label: 'View record' }
            };
            var definition = definitions[status];
            var serverUrl = workflow && workflow.url;
            if (!definition || !serverUrl || !Number.isInteger(bookingId) || bookingId < 1) { return null; }

            try {
                var url = new URL(String(serverUrl), global.location.href);
                if (url.origin !== global.location.origin) { return null; }
                if (!new RegExp('/' + bookingId + '/?$').test(url.pathname)) { return null; }
                return { label: definition.label, url: url.href };
            } catch (error) {
                return null;
            }
        }

        function loadingDetailMarkup() {
            return '<div class="inv-detail-loading" role="status">'
                + '<span class="erp-spinner" aria-hidden="true"></span>'
                + '<span>Loading guest details&hellip;</span>'
                + '</div>';
        }

        function renderGuestDetails(booking) {
            var status = String(booking.status_code || '');
            var bookingId = Number.parseInt(booking.id, 10);
            var statusName = booking.status_name || detailStatusLabel(status);
            var action = workflowAction(status, bookingId, booking.workflow);
            var room = booking.allotted_room_no || 'Room not assigned';
            var dates = detailDate(booking.scheduled_check_in_date) + ' &ndash; '
                + detailDate(booking.scheduled_check_out_date);
            var meta = [];
            if (booking.phone) { meta.push(String(booking.phone)); }
            if (booking.customer_code) { meta.push(String(booking.customer_code)); }

            detailTitle.textContent = booking.booking_number
                ? 'Booking ' + booking.booking_number
                : 'Booking Details';
            detailSubtitle.textContent = room + ' \u00b7 ' + formatDate(String(booking.scheduled_check_in_date || '').slice(0, 10));

            if (action) {
                detailAction.textContent = action.label;
                detailAction.href = action.url;
                detailAction.hidden = false;
            } else {
                detailAction.hidden = true;
                detailAction.removeAttribute('href');
                detailAction.textContent = '';
            }

            var activity = '';
            if (booking.checked_in_at || booking.checked_out_at) {
                activity = '<div class="inv-detail-section">'
                    + '<div class="erp-section-title">Stay Activity</div>'
                    + '<div class="erp-detail-grid">'
                    + detailItem('Checked-in At', detailDateTime(booking.checked_in_at), true)
                    + detailItem('Checked-out At', detailDateTime(booking.checked_out_at), true)
                    + '</div></div>';
            }

            detailBody.innerHTML = '<div class="inv-detail-summary">'
                + '<div class="inv-detail-guest">'
                + '<div class="inv-detail-guest-name">' + detailValue(booking.customer_name) + '</div>'
                + '<div class="inv-detail-guest-meta">' + (meta.length ? escapeHtml(meta.join(' \u00b7 ')) : 'Guest details') + '</div>'
                + '</div>'
                + '<span class="erp-badge ' + detailStatusClass(status) + '">' + escapeHtml(statusName) + '</span>'
                + '</div>'
                + '<div class="inv-detail-section">'
                + '<div class="erp-section-title">Customer</div>'
                + '<div class="erp-detail-grid">'
                + detailItem('Customer Name', booking.customer_name)
                + detailItem('Mobile No', booking.phone)
                + detailItem('Customer ID', booking.customer_code)
                + detailItem('Country', booking.country)
                + '</div></div>'
                + '<div class="inv-detail-section">'
                + '<div class="erp-section-title">Booking</div>'
                + '<div class="erp-detail-grid">'
                + detailItem('Booking No', booking.booking_number)
                + detailItem('Room No', booking.allotted_room_no)
                + detailItem('Room Category', booking.room_category)
                + detailItem('Stay Dates', dates, true)
                + detailItem('Total Guests', booking.total_guest)
                + detailItem('Channel', booking.channel_name)
                + '</div></div>'
                + activity
                + (action ? '' : '<div class="inv-detail-note" role="status">No action is available for the booking\'s current status.</div>');
        }

        function showDetailError(message) {
            detailTitle.textContent = 'Booking Details';
            detailSubtitle.textContent = 'Details could not be loaded';
            detailAction.hidden = true;
            detailAction.removeAttribute('href');
            detailAction.textContent = '';
            detailBody.innerHTML = '<div class="erp-alert erp-alert-danger inv-detail-error" role="alert">'
                + escapeHtml(message || 'Guest details could not be loaded. Please try again.')
                + '</div>';
        }

        function openGuestDetails(invoker) {
            if (!detailEnabled) { return; }
            var rawId = invoker.getAttribute('data-booking-id') || '';
            if (!/^\d+$/.test(rawId)) { return; }
            var bookingId = Number.parseInt(rawId, 10);
            if (!Number.isInteger(bookingId) || bookingId < 1) { return; }

            detailInvoker = invoker;
            detailBackdrop.hidden = false;
            syncBodyModalState();
            detailTitle.textContent = 'Booking Details';
            detailSubtitle.textContent = 'Loading current details\u2026';
            detailAction.hidden = true;
            detailAction.removeAttribute('href');
            detailAction.textContent = '';
            detailBody.innerHTML = loadingDetailMarkup();
            detailClose.focus();

            var requestId = ++detailRequest;
            var detailUrl = String(config.detailUrl).replace(/\/+$/, '') + '/' + bookingId;
            global.fetch(detailUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Property-Context-Token': global.APP_PROPERTY_CONTEXT_TOKEN || ''
                }
            }).then(function (response) {
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') === -1) {
                    throw new Error(response.redirected
                        ? 'Your session has expired. Sign in again to view booking details.'
                        : 'Guest details could not be loaded.');
                }
                return response.json().then(function (payload) {
                    return { response: response, payload: payload };
                });
            }).then(function (result) {
                if (requestId !== detailRequest) { return; }
                var booking = result.payload && result.payload.data;
                if (!result.response.ok || !result.payload || !result.payload.status || !booking) {
                    throw new Error(result.payload && result.payload.message
                        ? result.payload.message
                        : 'Guest details could not be loaded.');
                }
                if (Number.parseInt(booking.id, 10) !== bookingId) {
                    throw new Error('The booking details did not match the selected room.');
                }
                renderGuestDetails(booking);
            }).catch(function (error) {
                if (requestId !== detailRequest) { return; }
                showDetailError(error.message);
            });
        }

        function closeGuestDetails(restoreFocus) {
            if (!detailEnabled) { return; }
            detailRequest++;
            detailBackdrop.hidden = true;
            detailBody.innerHTML = '';
            detailAction.hidden = true;
            detailAction.removeAttribute('href');
            syncBodyModalState();
            if (restoreFocus && detailInvoker && doc.contains(detailInvoker)) {
                detailInvoker.focus();
            }
        }

        function initializeInjectedForm() {
            if (global.SearchableSelect && global.SearchableSelect.init) {
                global.SearchableSelect.init(modalBody);
            }
            if (global.BookingForm && global.BookingForm.init) {
                global.BookingForm.init(modalBody);
            }
            var first = modalBody.querySelector('#bf_phone, input, select, button');
            if (first) { first.focus(); }
        }

        function openBookingModal() {
            if (!selection) { return; }
            modalInvoker = doc.activeElement;
            backdrop.hidden = false;
            syncBodyModalState();
            modalBody.innerHTML = loadingMarkup();
            modalClose.focus();

            var checkout = addDays(selection.end, 1);
            var url = config.formUrl
                + '?room_id=' + encodeURIComponent(selection.roomId)
                + '&check_in=' + encodeURIComponent(selection.start)
                + '&check_out=' + encodeURIComponent(checkout);
            var requestId = ++loadRequest;

            global.fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Property-Context-Token': global.APP_PROPERTY_CONTEXT_TOKEN || ''
                }
            }).then(function (response) {
                return response.json().then(function (payload) {
                    return { response: response, payload: payload };
                });
            }).then(function (result) {
                if (requestId !== loadRequest) { return; }
                if (!result.payload || !result.payload.status || !result.payload.html) {
                    throw new Error(
                        result.payload && result.payload.message
                            ? result.payload.message
                            : 'The booking form could not be loaded.'
                    );
                }
                modalBody.innerHTML = result.payload.html;
                initializeInjectedForm();
            }).catch(function (error) {
                if (requestId !== loadRequest) { return; }
                modalError(error.message || 'The booking form could not be loaded.');
            });
        }

        function closeBookingModal(restoreFocus) {
            loadRequest++;
            backdrop.hidden = true;
            syncBodyModalState();
            modalBody.innerHTML = '';
            if (restoreFocus && modalInvoker && doc.contains(modalInvoker)) {
                modalInvoker.focus();
            }
        }

        function showSubmissionError(form, message) {
            var existing = form.querySelector('.inv-booking-submit-error');
            if (existing) { existing.remove(); }
            var alert = doc.createElement('div');
            alert.className = 'erp-alert erp-alert-danger inv-booking-submit-error';
            alert.setAttribute('role', 'alert');
            alert.textContent = message || 'The booking could not be saved. Please try again.';
            var header = form.querySelector('.erp-page-head');
            if (header && header.parentNode) {
                header.parentNode.insertBefore(alert, header.nextSibling);
            } else {
                form.insertBefore(alert, form.firstChild);
            }
            alert.scrollIntoView({ block: 'nearest' });
        }

        function replaceForm(html, message, form) {
            if (html) {
                modalBody.innerHTML = html;
                initializeInjectedForm();
                return;
            }
            showSubmissionError(form, message || 'Please check the booking details and try again.');
        }

        modalBody.addEventListener('click', function (event) {
            if (event.target.closest('[data-booking-cancel]')) {
                closeBookingModal(true);
            }
        });

        modalBody.addEventListener('submit', function (event) {
            var form = event.target.closest('form[data-booking-context="inventory"]');
            if (!form) { return; }
            event.preventDefault();

            var submit = form.querySelector('[data-booking-submit]');
            if (submit && submit.disabled) { return; }
            if (submit) {
                submit.disabled = true;
                submit.dataset.originalText = submit.textContent;
                submit.textContent = 'Saving\u2026';
            }

            global.fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Property-Context-Token': global.APP_PROPERTY_CONTEXT_TOKEN || ''
                }
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (payload && payload.status) {
                    // Do not reopen the committed selection after navigation.
                    forgetStoredSelection();
                    selection = null;
                    global.location.assign(
                        payload.redirect
                            || ((global.APP_BASE || '/').replace(/\/?$/, '/') + 'customers/bookings')
                    );
                    return;
                }
                replaceForm(payload && payload.html, payload && payload.message, form);
                if (submit && (!payload || !payload.html)) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalText || 'Save Booking';
                }
            }).catch(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = submit.dataset.originalText || 'Save Booking';
                }
                showSubmissionError(
                    form,
                    'The booking could not be saved because of a network or server error. Your entered details are still here; please try again.'
                );
            });
        });

        function focusableElements() {
            return Array.prototype.filter.call(
                modal.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'),
                function (element) { return element.offsetParent !== null; }
            );
        }

        backdrop.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                // The searchable select consumes Escape to close its own panel;
                // that same key must not also close the entire booking modal.
                if (event.target.closest && event.target.closest('.erp-ss-panel')) {
                    return;
                }
                event.preventDefault();
                closeBookingModal(true);
                return;
            }
            if (event.key !== 'Tab') { return; }
            var focusable = focusableElements();
            if (!focusable.length) { return; }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && doc.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && doc.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) { closeBookingModal(true); }
        });
        modalClose.addEventListener('click', function () { closeBookingModal(true); });
        createButton.addEventListener('click', openBookingModal);
        clearButton.addEventListener('click', function () { clearSelection(true); });

        if (detailEnabled) {
            detailBackdrop.addEventListener('click', function (event) {
                if (event.target === detailBackdrop) { closeGuestDetails(true); }
            });
            detailClose.addEventListener('click', function () { closeGuestDetails(true); });
            detailBackdrop.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeGuestDetails(true);
                    return;
                }
                if (event.key !== 'Tab') { return; }
                var focusable = Array.prototype.filter.call(
                    detailModal.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'),
                    function (element) { return element.offsetParent !== null; }
                );
                if (!focusable.length) { return; }
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (event.shiftKey && doc.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && doc.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });
        }

        // Restores the range after Inventory Previous/Next reloads. The stored
        // selection is per-tab, short-lived, and cleared after save or Clear.
        selection = restoreStoredSelection();
        if (selection && !slotIndex[selection.roomId]) {
            selection = null;
            forgetStoredSelection();
        }
        renderSelection();
    }

    global.InventoryBooking = {
        init: init,
        addDays: addDays,
        rangeDates: rangeDates
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }
})(window);
