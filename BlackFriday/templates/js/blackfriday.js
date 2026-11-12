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

    function serieKeys(code, label) {
        var out = [];
        [code, label].forEach(function (v) {
            var k = String(v || '').toUpperCase()
                .replace(/_\d+$/, '')
                .replace(/[\s\-]*SERIE[\s\-]*/g, '')
                .replace(/[\s\-]+/g, '');
            if (k && out.indexOf(k) === -1) {
                out.push(k);
            }
            var m = k.match(/^(\d+)ER$/);
            if (m && out.indexOf(m[1]) === -1) {
                out.push(m[1]);
            }
        });
        return out;
    }

    function fmt(p) {
        return String(Math.round(p * 10) / 10).replace('.', ',');
    }

    function decorateSeries() {
        var map = window.BF_SERIES;
        if (!map) {
            return;
        }
        var tiles = document.querySelectorAll('.serie-tile-link[data-serie], .fk-micro-serie-tile[data-serie]');
        Array.prototype.forEach.call(tiles, function (el) {
            var seg = map[el.getAttribute('data-segment') === 'pkw' ? 'pkw' : 'wm'] || {};
            var host = el.querySelector('.tile') || el;
            var label = (el.querySelector('.serie-desc, .fk-micro-serie-name') || {}).textContent || '';
            var pct = 0;
            serieKeys(el.getAttribute('data-serie'), label).forEach(function (k) {
                if (seg[k] > pct) {
                    pct = seg[k];
                }
            });
            var badge = host.querySelector(':scope > .bf-serie-badge');
            if (pct <= 0) {
                if (badge) {
                    badge.remove();
                }
                host.classList.remove('bf-serie-deal');
                return;
            }
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'bf-serie-badge';
                host.insertBefore(badge, host.firstChild);
            }
            var text = '-' + fmt(pct) + '%';
            if (badge.getAttribute('data-pct') !== text) {
                badge.setAttribute('data-pct', text);
                badge.innerHTML = '<small>BLACK FRIDAY</small>' + text;
            }
            host.classList.add('bf-serie-deal');
        });
    }

    function decorateBanner() {
        var banner = document.querySelector('.womosi-konfigurator .konfig-banner');
        if (!banner || banner.querySelector('.bf-fk-kicker')) {
            return;
        }
        var max = window.BF_SERIES && window.BF_SERIES.max ? window.BF_SERIES.max : 0;
        var kicker = document.createElement('div');
        kicker.className = 'bf-fk-kicker';
        kicker.innerHTML = '<span class="bf-fk-kicker__tag">BLACK FRIDAY</span>'
            + (max > 0 ? '<span class="bf-fk-kicker__pct">bis zu -' + fmt(max) + '%</span>' : '');
        banner.insertBefore(kicker, banner.firstChild);
    }

    var queued = false;
    function schedule() {
        if (queued) {
            return;
        }
        queued = true;
        (window.requestAnimationFrame || setTimeout)(function () {
            queued = false;
            decorateSeries();
        });
    }

    function initDecorations() {
        if (!document.body || !document.body.classList.contains('bf-active')) {
            return;
        }
        decorateBanner();
        decorateSeries();
        if ('MutationObserver' in window) {
            new MutationObserver(function (list) {
                for (var i = 0; i < list.length; i++) {
                    if (list[i].addedNodes.length) {
                        schedule();
                        return;
                    }
                }
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    function boot() {
        init();
        initDecorations();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
