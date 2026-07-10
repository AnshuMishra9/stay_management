/* ============================================================
   Stay Management ERP — erpQuery
   A tiny, reusable "stale-while-revalidate" cache for AngularJS
   1.x list pages (a lightweight stand-in for TanStack Query).

   What it gives every list page, for free:
     • Cached list data is shown INSTANTLY (no spinner) when you
       revisit a page or re-apply the same filters.
     • In the background it refetches; if the data actually changed
       it updates the view, otherwise it leaves it untouched
       (no needless re-render / "refresh").
     • Cache survives tab-to-tab navigation (uses sessionStorage).
     • Freshness window (staleMs): within it, no refetch at all.
     • Invalidate a namespace after add/edit/delete so stale data
       never lingers.

   ------------------------------------------------------------
   HOW TO USE ON A NEW LIST PAGE  (3 small steps)
   ------------------------------------------------------------
   1) Load this file BEFORE your page controller, and add the
      module dependency:
        angular.module('myApp', ['erpQuery'])
          .controller('MyCtrl', ['$http','$timeout','erpQuery', MyCtrl]);

   2) In the controller, replace your $http list call with:
        vm.load = function () {
          erpQuery.fetch('mymaster', base + 'mymaster/list_ajax', vm.filters, {},
            { data:    function (rows) { vm.rows = rows; },
              loading: function (b)    { vm.loading = b; } });
        };

   3) After any change, clear the cache:
        erpQuery.invalidate('mymaster');   // e.g. inside delete/save success
      …and, after a form save that redirects back to the list, set
        <script>window.APP_FRESH = <?= !empty($flash) ? 'true':'false' ?>;</script>
      then pass  { fresh: consumeFresh() }  as the opts (see customers.js).
   ============================================================ */
angular.module('erpQuery', []).factory('erpQuery', ['$http', function ($http) {
    'use strict';

    var PREFIX   = 'erpq:';        // sessionStorage key prefix
    var STALE_MS = 10 * 60 * 1000; // default freshness window (10 min)

    function serialize(params) {
        if (!params) { return ''; }
        return Object.keys(params).sort().map(function (k) {
            var v = params[k];
            return k + '=' + (v === null || v === undefined ? '' : v);
        }).join('&');
    }
    function keyFor(ns, url, params) {
        return PREFIX + ns + ':' + url + '?' + serialize(params);
    }
    function readCache(key) {
        try { var raw = sessionStorage.getItem(key); return raw ? JSON.parse(raw) : null; }
        catch (e) { return null; }
    }
    function writeCache(key, data) {
        try { sessionStorage.setItem(key, JSON.stringify({ t: Date.now(), data: data })); }
        catch (e) { /* quota / private mode — silently skip caching */ }
    }
    function removeByPrefix(pre) {
        try {
            for (var i = sessionStorage.length - 1; i >= 0; i--) {
                var k = sessionStorage.key(i);
                if (k && k.indexOf(pre) === 0) { sessionStorage.removeItem(k); }
            }
        } catch (e) { /* ignore */ }
    }

    return {
        /**
         * Fetch a list with stale-while-revalidate caching.
         *
         * @param {string}   ns      namespace for invalidation (e.g. 'customers')
         * @param {string}   url     endpoint
         * @param {object}   params  query params (also part of the cache key)
         * @param {object}   opts    { fresh:bool (skip cache), staleMs:number,
         *                             pick:fn(res)->data }
         * @param {object}   cb      { data:fn(rows, fromCache), loading:fn(bool),
         *                             error:fn() }
         */
        fetch: function (ns, url, params, opts, cb) {
            opts = opts || {};
            cb   = cb   || {};
            var key     = keyFor(ns, url, params);
            var staleMs = (opts.staleMs != null) ? opts.staleMs : STALE_MS;
            var pick    = opts.pick || function (res) {
                return (res.data && res.data.data) ? res.data.data : [];
            };
            var cached  = opts.fresh ? null : readCache(key);
            var hadCache = false;

            // 1) Instant paint from cache (no spinner).
            if (cached && cached.data !== undefined) {
                hadCache = true;
                if (cb.data)    { cb.data(cached.data, true); }
                if (cb.loading) { cb.loading(false); }
                // 2) Fresh enough? then don't hit the server at all.
                if ((Date.now() - (cached.t || 0)) < staleMs) { return; }
                // else: fall through and revalidate quietly (no spinner)
            } else if (cb.loading) {
                cb.loading(true);   // nothing cached → show the spinner
            }

            // 3) Revalidate against the server.
            $http.get(url, { params: params }).then(function (res) {
                var data = pick(res);
                var prev = readCache(key);
                var changed = !prev || JSON.stringify(prev.data) !== JSON.stringify(data);
                writeCache(key, data);
                if (changed && cb.data) { cb.data(data, false); }   // only re-render on real change
            }).catch(function () {
                if (!hadCache) {                 // no cache to fall back on
                    if (cb.data)  { cb.data([], false); }
                    if (cb.error) { cb.error(); }
                }
            }).finally(function () {
                if (cb.loading) { cb.loading(false); }
            });
        },

        /** Clear all cached lists for a namespace (call after add/edit/delete). */
        invalidate: function (ns) { removeByPrefix(PREFIX + ns + ':'); },

        /** Clear the entire query cache (e.g. on logout). */
        clearAll: function () { removeByPrefix(PREFIX); }
    };
}]);
