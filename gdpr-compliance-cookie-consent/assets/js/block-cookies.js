/* Blocks JavaScript cookies until the visitor has accepted. Loaded on every page (so cached HTML works) and switches
   itself off when the consent cookie holds an acceptance. Only the consent cookie itself may be written. */
(function () {
    var NAME = 'stm_gdpr_cookie';
    var proto = Object.getPrototypeOf(document);
    var native = Object.getOwnPropertyDescriptor(proto, 'cookie') || Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');

    if (!native || !native.configurable) {
        return;
    }

    // Cookies are allowed once the visitor accepted; a declined visitor stays blocked.
    function decided() {
        var m = native.get.call(document).match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
        return !!m && decodeURIComponent(m[1]) !== 'declined';
    }

    if (decided()) {
        return;
    }

    Object.defineProperty(document, 'cookie', {
        configurable: true,
        get: function () {
            return native.get.call(document);
        },
        set: function (val) {
            var name = String(val).split('=')[0].trim();
            if (name === NAME) {
                native.set.call(document, val);
                if (decided()) {
                    delete document.cookie;
                }
                return;
            }
            if (decided()) {
                native.set.call(document, val);
            }
        }
    });
})();
