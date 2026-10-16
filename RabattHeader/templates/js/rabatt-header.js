(function () {
    'use strict';

    var rhOffsetSels = [
        '#header-wrapper',
        'header#main-header',
        'header[role="banner"]',
        'header.header',
        'header.sticky-top',
        '#jtl-nav-wrapper',
        '#mainNavigation',
        '.navbar-collapse'
    ];

    function rhClearOffset(el) {
        if (el && el.hasAttribute('data-rh-top')) {
            el.style.top = el.getAttribute('data-rh-prev-top') || '';
            el.removeAttribute('data-rh-top');
            el.removeAttribute('data-rh-prev-top');
        }
    }

    function rhSyncOffset() {
        var bar = document.getElementById('rabatt-header-bar');
        if (!bar) {
            document.documentElement.classList.remove('has-rh-bar');
            rhOffsetSels.forEach(function (sel) {
                document.querySelectorAll(sel).forEach(rhClearOffset);
            });
            return;
        }
        document.documentElement.classList.add('has-rh-bar');
        if (bar.parentElement !== document.body) {
            document.body.insertBefore(bar, document.body.firstChild);
        }
        var h = bar.offsetHeight;
        document.documentElement.style.setProperty('--rh-bar-h', h + 'px');
        rhOffsetSels.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el) {
                if (el.id === 'rabatt-header-bar' || bar.contains(el)) {
                    return;
                }
                var pos = window.getComputedStyle(el).position;
                if (pos === 'fixed') {
                    if (!el.hasAttribute('data-rh-top')) {
                        el.setAttribute('data-rh-prev-top', el.style.top || '');
                        el.setAttribute('data-rh-top', '1');
                    }
                    el.style.top = h + 'px';
                } else {
                    rhClearOffset(el);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', rhSyncOffset);
    } else {
        rhSyncOffset();
    }
    window.addEventListener('resize', rhSyncOffset);

    function rhShowCopied(btn) {
        var hint = btn.querySelector('.rh-code-hint');
        if (!hint) {
            return;
        }
        var orig = hint.getAttribute('data-rh-orig');
        if (!orig) {
            hint.setAttribute('data-rh-orig', hint.textContent || '');
            orig = hint.getAttribute('data-rh-orig');
        }
        btn.classList.add('is-copied');
        hint.textContent = 'Kopiert!';
        window.setTimeout(function () {
            btn.classList.remove('is-copied');
            if (orig) {
                hint.textContent = orig;
            }
        }, 2200);
    }

    function rhCopyText(text, btn) {
        if (!text) {
            return;
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(function () {
                rhShowCopied(btn);
            }).catch(function () {
                rhCopyFallback(text, btn);
            });
            return;
        }
        rhCopyFallback(text, btn);
    }

    function rhCopyFallback(text, btn) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            if (document.execCommand('copy')) {
                rhShowCopied(btn);
            }
        } catch (e) {
            // ignore
        }
        document.body.removeChild(ta);
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('.rh-code') : null;
        if (!btn) {
            return;
        }
        ev.preventDefault();
        var code = (btn.getAttribute('data-rh-code') || '').trim();
        if (code === '') {
            return;
        }
        rhCopyText(code, btn);
    });
})();
