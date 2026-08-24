/* Progressively enhances .erp-select while preserving native select behavior. */
(function () {
    'use strict';

    var SEARCH_THRESHOLD = 7;
    // data-visible overrides the default cap for unfiltered options.
    var DEFAULT_VISIBLE = 9;

    function enhance(native) {
        if (native.dataset.ssReady || native.hasAttribute('data-no-search')) { return; }
        native.dataset.ssReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'erp-ss';
        native.parentNode.insertBefore(wrap, native);
        wrap.appendChild(native);
        native.classList.add('erp-ss-native', 'erp-ss-hidden');

        function readOptions() {
            return Array.prototype.map.call(native.options, function (o) {
                return { value: o.value, label: o.text, disabled: o.disabled };
            }).filter(function (o) { return !o.disabled; });
        }
        var options = readOptions();
        var emptyOpt   = options.filter(function (o) { return o.value === ''; })[0];
        var realCount  = options.filter(function (o) { return o.value !== ''; }).length;

        var searchAttr    = native.getAttribute('data-search');
        var searchVisible = searchAttr === 'always'
            || (searchAttr !== 'never' && realCount > SEARCH_THRESHOLD);

        var placeholder = native.getAttribute('data-placeholder')
            || (emptyOpt ? emptyOpt.label : 'Select');

        var visibleLimit = parseInt(native.getAttribute('data-visible'), 10) || DEFAULT_VISIBLE;

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'erp-ss-trigger erp-select';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        var triggerText = document.createElement('span');
        triggerText.className = 'erp-ss-value';
        trigger.appendChild(triggerText);

        var panel = document.createElement('div');
        panel.className = 'erp-ss-panel';
        panel.innerHTML =
            (searchVisible
                ? '<div class="erp-ss-search-wrap">' +
                    '<svg class="erp-ss-search-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>' +
                    '<input type="text" class="erp-ss-search" placeholder="Search…" autocomplete="off">' +
                  '</div>'
                : '') +
            '<ul class="erp-ss-list" role="listbox" tabindex="-1"></ul>' +
            '<div class="erp-ss-hint"></div>';

        wrap.appendChild(trigger);
        wrap.appendChild(panel);

        var search = panel.querySelector('.erp-ss-search');
        var list   = panel.querySelector('.erp-ss-list');
        var hint   = panel.querySelector('.erp-ss-hint');

        var open      = false;
        var highlight = -1;
        var rendered  = [];

        function syncTrigger() {
            var opt = native.options[native.selectedIndex];
            var isEmpty = native.value === '';
            triggerText.textContent = isEmpty ? placeholder : (opt ? opt.text : placeholder);
            trigger.classList.toggle('erp-ss-placeholder', isEmpty);
            Array.prototype.forEach.call(list.querySelectorAll('.erp-ss-opt'), function (li) {
                li.classList.toggle('is-selected', li.dataset.value === native.value);
            });
        }

        // Mirror external writes, including Angular model resets.
        var valDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
        if (valDesc && valDesc.configurable) {
            Object.defineProperty(native, 'value', {
                configurable: true,
                get: function () { return valDesc.get.call(this); },
                set: function (v) { valDesc.set.call(this, v); syncTrigger(); }
            });
        }

        function render(term) {
            term = (term || '').trim().toLowerCase();

            var capped = false;
            if (term) {
                // Search results are uncapped and exclude the reset option.
                rendered = options.filter(function (o) {
                    return o.value !== '' && o.label.toLowerCase().indexOf(term) !== -1;
                });
            } else {
                // Unfiltered panels retain the reset option before capped results.
                var reals = options.filter(function (o) { return o.value !== ''; });
                capped = reals.length > visibleLimit;
                var shown = capped ? reals.slice(0, visibleLimit) : reals;
                rendered = emptyOpt ? [emptyOpt].concat(shown) : shown;
            }
            highlight = -1;

            list.innerHTML = '';
            if (rendered.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'erp-ss-empty';
                empty.textContent = 'No match found';
                list.appendChild(empty);
            } else {
                rendered.forEach(function (o, i) {
                    var li = document.createElement('li');
                    li.className = 'erp-ss-opt'
                        + (o.value === native.value ? ' is-selected' : '')
                        + (o.value === '' ? ' erp-ss-opt-empty' : '');
                    li.setAttribute('role', 'option');
                    li.dataset.index = i;
                    li.dataset.value = o.value;
                    li.textContent = o.label;
                    list.appendChild(li);
                });
            }

            if (capped) {
                hint.textContent = 'Showing ' + visibleLimit + ' of ' + realCount
                    + (searchVisible ? ' &middot; type to search' : '');
                hint.style.display = '';
            } else {
                hint.style.display = 'none';
            }
        }

        function moveHighlight(delta) {
            var rows = list.querySelectorAll('.erp-ss-opt');
            if (!rows.length) { return; }
            highlight = (highlight + delta + rows.length) % rows.length;
            Array.prototype.forEach.call(rows, function (li, i) {
                li.classList.toggle('is-active', i === highlight);
            });
            rows[highlight].scrollIntoView({ block: 'nearest' });
        }

        function choose(i) {
            var o = rendered[i];
            if (!o) { return; }
            native.value = o.value;
            native.dispatchEvent(new Event('change', { bubbles: true }));
            close();
            trigger.focus();
        }

        function openPanel() {
            if (open) { return; }
            open = true;
            wrap.classList.add('is-open');
            wrap.classList.remove('erp-ss--up');
            trigger.setAttribute('aria-expanded', 'true');
            if (search) { search.value = ''; }
            render('');

            var rect = trigger.getBoundingClientRect();
            var ph   = panel.offsetHeight;
            if (window.innerHeight - rect.bottom < ph + 12 && rect.top > ph + 12) {
                wrap.classList.add('erp-ss--up');
            }
            setTimeout(function () { (search || list).focus(); }, 0);
        }

        function close() {
            if (!open) { return; }
            open = false;
            wrap.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        // Dependent selects call this after enabling or disabling native options.
        native._erpSsRefresh = function () {
            options = readOptions();
            emptyOpt = options.filter(function (o) { return o.value === ''; })[0];
            realCount = options.filter(function (o) { return o.value !== ''; }).length;
            syncTrigger();
            render(search ? search.value : '');
        };

        trigger.addEventListener('click', function () { open ? close() : openPanel(); });

        if (search) {
            search.addEventListener('input', function () { render(search.value); });
        }

        panel.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown')    { e.preventDefault(); moveHighlight(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveHighlight(-1); }
            else if (e.key === 'Enter')   { e.preventDefault(); choose(highlight === -1 ? 0 : highlight); }
            else if (e.key === 'Escape')  { e.preventDefault(); close(); trigger.focus(); }
        });

        list.addEventListener('click', function (e) {
            var li = e.target.closest('.erp-ss-opt');
            if (li) { choose(parseInt(li.dataset.index, 10)); }
        });

        trigger.addEventListener('keydown', function (e) {
            if ((e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') && !open) {
                e.preventDefault(); openPanel();
            }
        });

        document.addEventListener('click', function (e) {
            if (open && !wrap.contains(e.target)) { close(); }
        });

        syncTrigger();
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        Array.prototype.forEach.call(
            scope.querySelectorAll('select.erp-select'), enhance
        );
    }

    window.SearchableSelect = {
        init: init,
        enhance: enhance,
        refresh: function (native) {
            if (native && native._erpSsRefresh) { native._erpSsRefresh(); }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(document); });
    } else {
        init(document);
    }
})();
