/* Property-scoped stale-while-revalidate cache for AngularJS list pages. */
angular.module('erpQuery', []).factory('erpQuery', ['$http', function ($http) {
    'use strict';

    var PREFIX   = 'erpq:v2:'; // Increment when persisted cache semantics change.
    var STALE_MS = 10 * 60 * 1000;

    function serialize(params) {
        if (!params) { return ''; }
        return Object.keys(params).sort().map(function (k) {
            var v = params[k];
            return k + '=' + (v === null || v === undefined ? '' : v);
        }).join('&');
    }
    function contextKey() {
        return String(window.APP_CONTEXT_KEY || 'no-context');
    }
    function keyFor(ns, url, params) {
        return PREFIX + contextKey() + ':' + ns + ':' + url + '?' + serialize(params);
    }
    function readCache(key) {
        try { var raw = sessionStorage.getItem(key); return raw ? JSON.parse(raw) : null; }
        catch (e) { return null; }
    }
    function writeCache(key, data) {
        try { sessionStorage.setItem(key, JSON.stringify({ t: Date.now(), data: data })); }
        catch (e) { /* sessionStorage may be unavailable or full. */ }
    }
    function removeByPrefix(pre) {
        try {
            for (var i = sessionStorage.length - 1; i >= 0; i--) {
                var k = sessionStorage.key(i);
                if (k && k.indexOf(pre) === 0) { sessionStorage.removeItem(k); }
            }
        } catch (e) { /* Cache cleanup is best-effort. */ }
    }

    return {
        /**
         * Returns cached data immediately, then revalidates stale entries.
         * opts controls cache bypass, freshness, and response mapping; cb receives
         * data(rows, fromCache), loading(active), and error() notifications.
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

            // Serve cached data immediately and show a spinner only on a cold cache.
            if (cached && cached.data !== undefined) {
                hadCache = true;
                if (cb.data)    { cb.data(cached.data, true); }
                if (cb.loading) { cb.loading(false); }
                if ((Date.now() - (cached.t || 0)) < staleMs) { return; }
            } else if (cb.loading) {
                cb.loading(true);
            }

            // Revalidate stale entries without replacing an unchanged list.
            $http.get(url, {
                params: params,
                headers: { 'X-Property-Context-Token': window.APP_PROPERTY_CONTEXT_TOKEN || '' }
            }).then(function (res) {
                var data = pick(res);
                var prev = readCache(key);
                var changed = !prev || JSON.stringify(prev.data) !== JSON.stringify(data);
                writeCache(key, data);
                if (changed && cb.data) { cb.data(data, false); }
            }).catch(function (error) {
                if (error && error.status === 409) {
                    // A stale property token invalidates all contexts before redirect.
                    removeByPrefix('erpq:');
                    window.location.assign((window.APP_BASE || '/').replace(/\/?$/, '/') + 'inventory');
                    return;
                }
                if (!hadCache) {
                    if (cb.data)  { cb.data([], false); }
                    if (cb.error) { cb.error(); }
                }
            }).finally(function () {
                if (cb.loading) { cb.loading(false); }
            });
        },

        /** Clears cached lists for one namespace after a mutation. */
        invalidate: function (ns) { removeByPrefix(PREFIX + contextKey() + ':' + ns + ':'); },

        /** Clears every query cache when the authenticated context ends. */
        clearAll: function () { removeByPrefix('erpq:'); }
    };
}]);
