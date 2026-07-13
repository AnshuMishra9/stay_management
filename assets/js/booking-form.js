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
    var FIELDS = [
        'customer_name', 'alt_phone', 'landline_no', 'email',
        'address1', 'address2', 'city', 'district', 'state',
        'pincode', 'zip_code', 'country',
        'aadhar_number', 'aadhar_name', 'pan_number', 'pan_name'
    ];

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

    // ---- Auto-calc: Length of Stay + Remaining Amount ----
    var ci    = document.getElementById('bk_checkin');
    var co    = document.getElementById('bk_checkout');
    var los   = document.getElementById('bk_los');
    var total = document.getElementById('bk_total');
    var paid  = document.getElementById('bk_paid');
    var rem   = document.getElementById('bk_remaining');

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

    function calcRemaining() {
        if (!rem) { return; }
        if ((total && total.value !== '') || (paid && paid.value !== '')) {
            rem.value = (num(total) - num(paid)).toFixed(2);
        } else {
            rem.value = '';
        }
    }

    if (ci && co) {
        ci.addEventListener('change', calcLOS);
        co.addEventListener('change', calcLOS);
    }
    if (total && paid) {
        total.addEventListener('input', calcRemaining);
        paid.addEventListener('input', calcRemaining);
    }
})();
