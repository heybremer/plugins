(function () {
    'use strict';

    var MOBILE_MQ = '(max-width: 768px)';
    var BATCH = 4;

    function isMobile() {
        try {
            return window.matchMedia && window.matchMedia(MOBILE_MQ).matches;
        } catch (e) {
            return false;
        }
    }

    function syncTabAria(root) {
        var buttons = root.querySelectorAll('.pc-tab-btn');
        var panels = root.querySelectorAll('.pc-tab-panel');
        buttons.forEach(function (btn) {
            var on = btn.classList.contains('active');
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            var on = panel.classList.contains('active');
            if (on) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });
    }

    function updateLoadMoreVisibility(panel) {
        var grid = panel.querySelector('.produktcard-grid');
        var loadWrap = panel.querySelector('[data-pc-load-more-wrap]');
        var btn = panel.querySelector('[data-pc-load-more]');
        if (!grid) {
            return;
        }

        if (!loadWrap || !btn) {
            return;
        }
        var hidden = grid.querySelectorAll('.produktcard-card--folded:not(.is-visible)');
        if (hidden.length === 0) {
            loadWrap.setAttribute('aria-hidden', 'true');
            loadWrap.style.display = 'none';
        } else {
            loadWrap.setAttribute('aria-hidden', 'false');
            loadWrap.style.display = 'flex';
        }
    }

    function preparePanel(panel, resetMobile) {
        var grid = panel.querySelector('.produktcard-grid');
        var loadWrap = panel.querySelector('[data-pc-load-more-wrap]');
        if (!grid) {
            return;
        }

        if (!isMobile()) {
            grid.querySelectorAll('.produktcard-card--folded').forEach(function (el) {
                el.classList.add('is-visible');
            });
            if (loadWrap) {
                loadWrap.setAttribute('aria-hidden', 'true');
                loadWrap.style.display = 'none';
            }
            return;
        }

        if (resetMobile) {
            grid.querySelectorAll('.produktcard-card--folded').forEach(function (el) {
                el.classList.remove('is-visible');
            });
        }
        updateLoadMoreVisibility(panel);
    }

    function foldedNotVisible(grid) {
        return grid.querySelectorAll('.produktcard-card--folded:not(.is-visible)');
    }

    function initProduktcardTabs(root) {
        var buttons = root.querySelectorAll('.pc-tab-btn');
        var panels = root.querySelectorAll('.pc-tab-panel');

        panels.forEach(function (p) { preparePanel(p, true); });
        syncTabAria(root);

        root.addEventListener('click', function (e) {
            var tabBtn = e.target.closest('.pc-tab-btn');
            if (tabBtn && root.contains(tabBtn)) {
                e.preventDefault();
                var pid = tabBtn.getAttribute('data-pc-panel');
                buttons.forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-pc-panel') === pid);
                });
                panels.forEach(function (p) {
                    p.classList.toggle('active', p.getAttribute('data-pc-panel') === pid);
                });
                syncTabAria(root);
                // Tab-Wechsel: mobil wieder auf „erste 4“ zurücksetzen
                panels.forEach(function (p) { preparePanel(p, true); });
                return;
            }

            var loadMoreBtn = e.target.closest('[data-pc-load-more]');
            if (loadMoreBtn && root.contains(loadMoreBtn)) {
                if (!isMobile()) {
                    return;
                }
                e.preventDefault();
                var panel = loadMoreBtn.closest('.pc-tab-panel');
                if (!panel) {
                    return;
                }
                var g = panel.querySelector('.produktcard-grid');
                if (!g) {
                    return;
                }
                var items = foldedNotVisible(g);
                for (var i = 0; i < BATCH && i < items.length; i++) {
                    items[i].classList.add('is-visible');
                }
                updateLoadMoreVisibility(panel);
            }
        });

        window.addEventListener('resize', function () {
            // Bei Wechsel zwischen mobile/desktop Layout neu synchronisieren
            panels.forEach(function (p) { preparePanel(p, true); });
        });
    }

    function init() {
        document.querySelectorAll('[data-produktcard-tabs="1"]').forEach(initProduktcardTabs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
