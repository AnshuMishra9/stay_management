(function (global, doc) {
    'use strict';

    var BROADCAST_KEY = 'stay.property.context.changed.v1';
    var dirty = false;

    function clearPropertyState() {
        try {
            for (var i = global.sessionStorage.length - 1; i >= 0; i--) {
                var key = global.sessionStorage.key(i);
                if (key && (key.indexOf('erpq:') === 0
                    || key.indexOf('stay.inventory.booking.selection.') === 0)) {
                    global.sessionStorage.removeItem(key);
                }
            }
        } catch (error) { /* Storage can be disabled. */ }
    }

    function announceChange(reason) {
        try {
            global.localStorage.setItem(BROADCAST_KEY, JSON.stringify({
                reason: reason || 'property',
                at: Date.now()
            }));
        } catch (error) { /* Server token still protects stale tabs. */ }
    }

    doc.addEventListener('input', function (event) {
        var form = event.target && event.target.closest
            ? event.target.closest('form[method="post"]')
            : null;
        if (form && !form.hasAttribute('data-property-switch-form')) { dirty = true; }
    });
    doc.addEventListener('change', function (event) {
        var form = event.target && event.target.closest
            ? event.target.closest('form[method="post"]')
            : null;
        if (form && !form.hasAttribute('data-property-switch-form')) { dirty = true; }
    });

    doc.addEventListener('DOMContentLoaded', function () {
        var switchForm = doc.querySelector('[data-property-switch-form]');
        var selector = switchForm && switchForm.querySelector('[name="property_id"]');
        if (switchForm && selector) {
            selector.addEventListener('change', function () {
                if (!selector.value) { return; }
                if (dirty && !global.confirm('Switching property will discard unsaved changes. Continue?')) {
                    selector.value = String(global.APP_PROPERTY_ID || '');
                    return;
                }
                clearPropertyState();
                switchForm.submit();
            });
        }

        var logout = doc.querySelector('[data-app-logout]');
        if (logout) {
            logout.addEventListener('click', function () {
                clearPropertyState();
                announceChange('logout');
            });
        }

        // The server emits this marker only after it has authorized and saved
        // the new property context. Broadcasting here avoids another tab
        // racing ahead of the switch POST and repainting the old hotel.
        if (global.APP_PROPERTY_CONTEXT_SWITCHED) {
            clearPropertyState();
            announceChange('property');
        }
    });

    global.addEventListener('storage', function (event) {
        if (event.key !== BROADCAST_KEY || !event.newValue) { return; }
        clearPropertyState();
        var base = (global.APP_BASE || '/').replace(/\/?$/, '/');
        var payload = {};
        try { payload = JSON.parse(event.newValue) || {}; } catch (error) { payload = {}; }
        global.location.assign(base + (payload.reason === 'logout' ? 'logout' : 'inventory'));
    });

    global.StayPropertyContext = {
        clear: clearPropertyState,
        markClean: function () { dirty = false; }
    };
})(window, document);
