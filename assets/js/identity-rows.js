/* ============================================================
   Stay Management ERP — Customer Identity Proof rows (vanilla JS)

   Powers the repeatable "Identity Proof" block on the customer form:
     • "+ Add More" clones a blank row (type dropdown + number + document)
     • the × button on a row removes it
   Each new row's <select class="erp-select"> is enhanced into the same
   searchable dropdown used elsewhere.
   ============================================================ */
(function () {
    'use strict';

    var container = document.getElementById('identityRows');
    var tpl       = document.getElementById('identityRowTpl');
    var addBtn    = document.getElementById('idnAddMore');
    if (!container || !tpl) { return; }

    // Enhance a cloned row's select into the styled searchable dropdown.
    function enhanceRow(row) {
        var sel = row.querySelector('select.erp-select');
        if (sel && window.SearchableSelect) { window.SearchableSelect.enhance(sel); }
    }

    // "+ Add More" — append a fresh blank row.
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var frag = tpl.content.cloneNode(true);
            var row  = frag.querySelector('.idn-row');
            container.appendChild(frag);   // moves `row` into the DOM
            enhanceRow(row);
        });
    }

    // Remove a row (event delegation — works for server-rendered & cloned rows).
    container.addEventListener('click', function (e) {
        var btn = e.target.closest('.idn-remove');
        if (!btn) { return; }
        var row = btn.closest('.idn-row');
        if (row) { row.parentNode.removeChild(row); }
    });
})();
