/**
 * ProdukteDescription: Tabs + manueller Slider (kein Autoplay / keine Intervalle).
 */
(function () {
    'use strict';

    function qs(root, sel) {
        return root.querySelector(sel);
    }

    function qsa(root, sel) {
        return Array.prototype.slice.call(root.querySelectorAll(sel));
    }

    function setSlide(panel, index) {
        var slides = qsa(panel, '[data-pd-slide]');
        var dots = qsa(panel, '[data-pd-dot]');
        var n = slides.length;
        if (n === 0) {
            return;
        }
        var i = ((index % n) + n) % n;
        slides.forEach(function (el, j) {
            el.classList.toggle('is-active', j === i);
        });
        dots.forEach(function (el, j) {
            el.classList.toggle('is-active', j === i);
        });
        var prev = qs(panel, '[data-pd-prev]');
        var next = qs(panel, '[data-pd-next]');
        if (prev) {
            prev.disabled = n <= 1;
        }
        if (next) {
            next.disabled = n <= 1;
        }
        panel.setAttribute('data-pd-current-slide', String(i));
    }

    function getSlideIndex(panel) {
        var v = parseInt(panel.getAttribute('data-pd-current-slide') || '0', 10);
        if (isNaN(v)) {
            return 0;
        }
        return v;
    }

    function bindSlider(panel) {
        if (panel.getAttribute('data-pd-slider-bound') === '1') {
            return;
        }
        panel.setAttribute('data-pd-slider-bound', '1');
        var slides = qsa(panel, '[data-pd-slide]');
        if (!slides.length) {
            return;
        }
        panel.setAttribute('data-pd-current-slide', '0');
        setSlide(panel, 0);

        var prev = qs(panel, '[data-pd-prev]');
        var next = qs(panel, '[data-pd-next]');
        if (prev) {
            prev.addEventListener('click', function () {
                setSlide(panel, getSlideIndex(panel) - 1);
            });
        }
        if (next) {
            next.addEventListener('click', function () {
                setSlide(panel, getSlideIndex(panel) + 1);
            });
        }
        qsa(panel, '[data-pd-dot]').forEach(function (dot) {
            dot.addEventListener('click', function () {
                var d = parseInt(dot.getAttribute('data-pd-dot') || '0', 10);
                if (!isNaN(d)) {
                    setSlide(panel, d);
                }
            });
        });
    }

    function activateTab(root, index) {
        var tabs = qsa(root, '.pd-tab');
        var panels = qsa(root, '.pd-panel');
        tabs.forEach(function (t, j) {
            var on = j === index;
            t.classList.toggle('is-active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (p, j) {
            var on = j === index;
            p.classList.toggle('is-active', on);
            p.classList.toggle('pd-hidden', !on);
            p.setAttribute('aria-hidden', on ? 'false' : 'true');
            if (on) {
                bindSlider(p);
                setSlide(p, 0);
            }
        });
    }

    function initRoot(root) {
        if (root.getAttribute('data-pd-inited') === '1') {
            return;
        }
        root.setAttribute('data-pd-inited', '1');

        qsa(root, '.pd-panel').forEach(function (p) {
            bindSlider(p);
        });
        var firstPanel = qs(root, '.pd-panel.is-active') || qs(root, '.pd-panel');
        if (firstPanel) {
            setSlide(firstPanel, 0);
        }

        qsa(root, '.pd-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var idx = parseInt(tab.getAttribute('data-pd-tab-index') || '0', 10);
                if (!isNaN(idx)) {
                    activateTab(root, idx);
                }
            });
        });
    }

    function boot() {
        qsa(document, '.pd-root').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
