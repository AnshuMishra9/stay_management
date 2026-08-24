/* Toast and auto-dismissing flash-message helpers. */
(function () {
    'use strict';

    var wrap = null;

    function ensureWrap() {
        if (!wrap || !document.body.contains(wrap)) {
            wrap = document.createElement('div');
            wrap.className = 'erp-toast-wrap';
            document.body.appendChild(wrap);
        }
        return wrap;
    }

    function dismiss(t) {
        if (!t || t.dataset.leaving) { return; }
        t.dataset.leaving = '1';
        t.classList.remove('is-in');
        t.classList.add('is-out');
        setTimeout(function () {
            if (t.parentNode) { t.parentNode.removeChild(t); }
        }, 320);
    }

    function show(message, type) {
        var host = ensureWrap();
        var t = document.createElement('div');
        t.className = 'erp-toast erp-toast-' + (type || 'success');
        t.setAttribute('role', 'status');
        var ic = document.createElement('span');
        ic.className = 'erp-toast-ic';
        ic.textContent = type === 'error' ? '!' : '✓';
        var msg = document.createElement('span');
        msg.className = 'erp-toast-msg';
        msg.textContent = message || '';
        t.appendChild(ic);
        t.appendChild(msg);
        host.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('is-in'); });
        setTimeout(function () { dismiss(t); }, 4000);
        return t;
    }

    window.ErpToast = { show: show, dismiss: dismiss };

    function leave(el) {
        if (!el || el.dataset.leaving) { return; }
        el.dataset.leaving = '1';
        el.classList.add('erp-alert--leaving');
        setTimeout(function () {
            if (el.parentNode) { el.parentNode.removeChild(el); }
        }, 420);
    }

    function armFlashes(root) {
        Array.prototype.forEach.call((root || document).querySelectorAll('.erp-alert'), function (el) {
            if (el.dataset.uiArmed) { return; }
            el.dataset.uiArmed = '1';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'erp-alert-close';
            btn.setAttribute('aria-label', 'Dismiss');
            btn.innerHTML = '&times;';
            btn.addEventListener('click', function () { leave(el); });
            el.appendChild(btn);
            setTimeout(function () { leave(el); }, 4500);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { armFlashes(document); });
    } else {
        armFlashes(document);
    }
})();
