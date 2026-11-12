(function () {
    'use strict';

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function tick(nodes) {
        var now = Date.now();
        nodes.forEach(function (el) {
            var left = Math.max(0, Math.floor((el._bfEnd - now) / 1000));
            var parts = {
                d: Math.floor(left / 86400),
                h: Math.floor(left / 3600) % 24,
                m: Math.floor(left / 60) % 60,
                s: left % 60
            };
            Object.keys(parts).forEach(function (u) {
                var b = el.querySelector('[data-u="' + u + '"]');
                if (b) {
                    b.textContent = pad(parts[u]);
                }
            });
            if (left === 0) {
                el.classList.add('is-over');
            }
        });
    }

    function init() {
        var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-bf-end]')).filter(function (el) {
            el._bfEnd = Date.parse(el.getAttribute('data-bf-end'));
            return !isNaN(el._bfEnd);
        });
        if (!nodes.length) {
            return;
        }
        tick(nodes);
        setInterval(function () {
            tick(nodes);
        }, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
