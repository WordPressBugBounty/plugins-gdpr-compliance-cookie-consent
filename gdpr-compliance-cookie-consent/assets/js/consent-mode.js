/* Runs in <head> right after the Consent Mode default. Reads the stored choice from the consent cookie (so it works on
   cached pages) and applies it as a consent update; exposes window.stmGdprConsent for scripts.js and for themes. */
(function () {
    var NAME = 'stm_gdpr_cookie';
    var map = window.stmGdprConsentMap || {};

    function readCookie() {
        var m = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : '';
    }

    /* Cookie value: "declined", or a token optionally ending in ".a1m0" (analytics, marketing; 1 = granted).
       A plain token (older versions, or "accept all") grants every category. */
    function parse(value) {
        var state = {};
        var cats = Object.keys(map);
        var granted = {};
        var m;
        if (!value || value === 'declined') {
            cats.forEach(function (c) { granted[c] = false; });
        } else if ((m = value.match(/\.a([01])m([01])$/))) {
            granted.analytics = m[1] === '1';
            granted.marketing = m[2] === '1';
        } else {
            cats.forEach(function (c) { granted[c] = true; });
        }
        cats.forEach(function (c) {
            map[c].forEach(function (type) { state[type] = granted[c] ? 'granted' : 'denied'; });
        });
        return state;
    }

    function update(state) {
        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', state);
        }
        try {
            document.dispatchEvent(new CustomEvent('stm_gdpr_consent', { detail: state }));
        } catch (e) { /* CustomEvent is missing in very old browsers */ }
    }

    window.stmGdprConsent = {
        name: NAME,
        read: readCookie,
        parse: parse,
        update: update
    };

    var stored = readCookie();
    if (stored) {
        update(parse(stored));
    }
})();
