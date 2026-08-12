/* ============================================================
   Inventory night-range selection and in-page New Booking modal.

   A selected calendar cell is one occupied night. An inclusive selection
   D1..D2 is submitted as [D1, D2 + 1 day), so the checkout date is free.
   ============================================================ */
(function (global) {
    'use strict';

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
        var popupClose = doc.getElementById('invSelectionClose');
        var clearButton = doc.getElementById('invSelectionClear');
        var createButton = doc.getElementById('invCreateBooking');
        var backdrop = doc.getElementById('invBookingBackdrop');
        var modal = doc.getElementById('invBookingModal');
        var modalBody = doc.getElementById('invBookingModalBody');
        var modalClose = doc.getElementById('invBookingClose');

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
        var modalInvoker = null;
        var loadRequest = 0;

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
            slots.forEach(function (slot) {
                slot.classList.remove('is-range-selected', 'is-range-start', 'is-range-end');
                var cell = slot.closest('.inv-cell');
                if (cell) { cell.classList.remove('is-range-selected'); }
                if (slot.tagName === 'BUTTON') { slot.setAttribute('aria-pressed', 'false'); }
            });

            if (!selection) { return; }
            rangeDates(selection.start, selection.end).forEach(function (date) {
                var slot = slotIndex[selection.roomId] && slotIndex[selection.roomId][date];
                if (!slot) { return; }
                slot.classList.add('is-range-selected');
                var cell = slot.closest('.inv-cell');
                if (cell) { cell.classList.add('is-range-selected'); }
                if (date === selection.start) { slot.classList.add('is-range-start'); }
                if (date === selection.end) { slot.classList.add('is-range-end'); }
                if (slot.tagName === 'BUTTON') { slot.setAttribute('aria-pressed', 'true'); }
            });
        }

        function renderSelection() {
            if (!selection) {
                popup.hidden = true;
                hideError();
                paintSelection();
                return;
            }

            var checkout = addDays(selection.end, 1);
            var nights = rangeDates(selection.start, selection.end).length;
            var visibleRoomDates = Object.keys(slotIndex[selection.roomId] || {}).sort();
            popupRoom.textContent = 'Room ' + selection.roomNo;
            popupDates.textContent = 'Check-in ' + formatDate(selection.start)
                + ' \u2192 Check-out ' + formatDate(checkout) + ', 11:00 AM';
            popupNights.textContent = nights + (nights === 1 ? ' night' : ' nights')
                + ' \u00b7 Checkout ' + formatDate(checkout) + ', 11 AM';
            if (rangeStartInput) { rangeStartInput.value = selection.start; }
            if (rangeEndInput) {
                rangeEndInput.value = selection.end;
                rangeEndInput.min = selection.start;
                rangeEndInput.max = visibleRoomDates.length
                    ? visibleRoomDates[visibleRoomDates.length - 1]
                    : selection.end;
            }
            createButton.textContent = nights === 1 ? 'Book 1 Night' : 'Book ' + nights + ' Nights';
            popup.hidden = false;
            hideError();
            paintSelection();
        }

        function clearSelection(restoreFocus) {
            var invoker = selection && selection.invoker;
            selection = null;
            renderSelection();
            if (restoreFocus && invoker && doc.contains(invoker)) { invoker.focus(); }
        }

        function selectSlot(button) {
            var roomId = button.getAttribute('data-room-id');
            var date = button.getAttribute('data-date');
            var effectiveToday = config.today && config.today > localToday()
                ? config.today
                : localToday();

            // A tab left open across midnight must not offer yesterday. Reload
            // so both the visual status and server-provided hotel date refresh.
            if (date < effectiveToday) {
                global.location.reload();
                return;
            }

            if (!selection || selection.roomId !== roomId) {
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

            var candidateDates = rangeDates(selection.anchor, date);
            var blocked = candidateDates.filter(function (candidateDate) {
                var candidate = slotIndex[roomId] && slotIndex[roomId][candidateDate];
                return !candidate || candidate.getAttribute('data-bookable') !== '1';
            })[0];

            if (blocked) {
                showError(
                    'This range includes ' + formatDate(blocked)
                    + ', which is booked or cannot be booked. Select a continuous available range.'
                );
                return;
            }

            selection.start = selection.anchor <= date ? selection.anchor : date;
            selection.end = selection.anchor <= date ? date : selection.anchor;
            // Once both endpoints exist, future clicks adjust the last night
            // from the chronological check-in date (also fixes reverse picks).
            selection.anchor = selection.start;
            selection.invoker = button;
            renderSelection();
        }

        // The number button remains keyboard accessible, while event delegation
        // makes the full visible cell (including the "Available" label) clickable.
        doc.addEventListener('click', function (event) {
            if (!event.target || !event.target.closest) { return; }
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

                var blocked = rangeDates(selection.start, requestedEnd).filter(function (candidateDate) {
                    var candidate = slotIndex[selection.roomId]
                        && slotIndex[selection.roomId][candidateDate];
                    return !candidate || candidate.getAttribute('data-bookable') !== '1';
                })[0];
                if (blocked) {
                    rangeEndInput.value = previousEnd;
                    showError(
                        'This range includes ' + formatDate(blocked)
                        + ', which is booked or outside the visible Inventory dates.'
                    );
                    return;
                }

                selection.anchor = selection.start;
                selection.end = requestedEnd;
                selection.invoker = rangeEndInput;
                renderSelection();
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
            doc.body.classList.add('inv-modal-open');
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
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
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
            doc.body.classList.remove('inv-modal-open');
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
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                if (payload && payload.status) {
                    // Reload the exact same Inventory URL so filters/date stay put
                    // and availability is recomputed from the committed booking.
                    global.location.reload();
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
        popupClose.addEventListener('click', function () { clearSelection(true); });
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
