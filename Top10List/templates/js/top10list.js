(function () {
    'use strict';

    function isMobile() {
        try {
            return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
        } catch (e) {
            return false;
        }
    }

    function preparePanelCards(panel) {
        if (!isMobile()) {
            const button = panel.querySelector('[data-t10-load-more]');
            if (button) button.style.display = 'none';
            panel.querySelectorAll('.t10-card--hidden').forEach((el) => el.classList.remove('t10-card--hidden'));
            return;
        }
        const cards = Array.from(panel.querySelectorAll('.t10-card'));
        const button = panel.querySelector('[data-t10-load-more]');
        if (cards.length <= 3) {
            if (button) button.style.display = 'none';
            return;
        }
        cards.forEach((card, index) => {
            card.classList.toggle('t10-card--hidden', index >= 3);
        });
        if (button) button.style.display = '';
    }

    function initTop10(root) {
        const buttons = root.querySelectorAll('.aktion-tab-button');
        const panels = root.querySelectorAll('.aktion-tab-content');
        panels.forEach(preparePanelCards);

        root.addEventListener('click', (e) => {
            const tabBtn = e.target.closest('.aktion-tab-button');
            if (tabBtn && root.contains(tabBtn)) {
                const tab = tabBtn.getAttribute('data-tab');
                buttons.forEach((b) => b.classList.toggle('active', b === tabBtn));
                panels.forEach((p) => p.classList.toggle('active', p.getAttribute('data-panel') === tab));
                return;
            }

            const loadMoreBtn = e.target.closest('[data-t10-load-more]');
            if (loadMoreBtn && root.contains(loadMoreBtn)) {
                if (!isMobile()) {
                    return;
                }
                e.preventDefault();
                const panel = loadMoreBtn.closest('.aktion-tab-content');
                if (!panel) return;
                const hidden = panel.querySelectorAll('.t10-card--hidden');
                const step = 3;
                for (let i = 0; i < Math.min(step, hidden.length); i++) {
                    hidden[i].classList.remove('t10-card--hidden');
                }
                if (panel.querySelectorAll('.t10-card--hidden').length === 0) {
                    loadMoreBtn.style.display = 'none';
                }
            }
        });

        // On resize: toggle mobile/desktop behavior
        window.addEventListener('resize', () => {
            panels.forEach(preparePanelCards);
        });
    }

    function init() {
        document.querySelectorAll('[data-top10list="1"]').forEach(initTop10);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

