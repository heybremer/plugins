/**
 * SideLeftPanel: small behavior tweaks for #sidepanel_left
 * - Mobile accordion: when one box opens, close others (optional UX)
 * - Improves dropdown usability without changing templates.
 */
(() => {
  'use strict';

  const ready = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  };

  const isMobile = () => {
    try {
      return window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches;
    } catch (e) {
      return false;
    }
  };

  const initAccordion = (root) => {
    if (!root || !isMobile()) return;

    // Check if enabled via admin settings
    const isEnabled = root.getAttribute('data-slp-accordion') !== '0';
    if (!isEnabled) return;

    // Prefer Bootstrap events if available (NOVA uses jQuery/Bootstrap)
    const hasJq = !!window.jQuery;
    const $ = hasJq ? window.jQuery : null;

    const closeOthers = (openEl) => {
      try {
        const nodes = root.querySelectorAll('.collapse.show');
        nodes.forEach((n) => {
          if (n === openEl) return;
          if (hasJq && $ && typeof $(n).collapse === 'function') {
            try { $(n).collapse('hide'); } catch (e) {}
          } else {
            n.classList.remove('show');
          }
        });
      } catch (e) {}
    };

    // Bootstrap (jQuery) path
    if (hasJq && $ && $.fn && $.fn.on) {
      try {
        $(root).on('shown.bs.collapse', '.collapse', (e) => {
          closeOthers(e.target);
        });
        return;
      } catch (e) {}
    }

    // Vanilla fallback: observe class changes
    try {
      const mo = new MutationObserver((mutations) => {
        for (const m of mutations) {
          if (m.type !== 'attributes' || m.attributeName !== 'class') continue;
          const el = m.target;
          if (el?.classList?.contains('collapse') && el.classList.contains('show')) {
            closeOthers(el);
            break; // Stop after first match in this batch
          }
        }
      });

      root.querySelectorAll('.collapse').forEach((el) => {
        mo.observe(el, { attributes: true, attributeFilter: ['class'] });
      });
    } catch (e) {}
  };

  const updateDropdownLabels = (root) => {
    if (!root) return;
    try {
      const dropdowns = root.querySelectorAll('.dropdown');
      dropdowns.forEach((dd) => {
        const toggle = dd.querySelector('.dropdown-toggle');
        const activeItem = dd.querySelector('.dropdown-menu .dropdown-item.active .filter-item-value');
        
        if (toggle && activeItem && activeItem.textContent.trim()) {
          const selectedText = activeItem.textContent.trim();
          // Update the text node of the toggle button, keeping caret or other children intact if possible
          // In standard JTL, toggle usually has just text.
          // But to be safe, we replace only text nodes or set innerHTML carefully.
          let hasUpdated = false;
          toggle.childNodes.forEach(node => {
             if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim().toLowerCase() === 'beliebig') {
                node.nodeValue = selectedText + ' ';
                hasUpdated = true;
             }
          });
          
          if (!hasUpdated && toggle.innerText.trim().toLowerCase() === 'beliebig') {
              toggle.innerText = selectedText;
          }
        }
      });
    } catch (e) {}
  };

  ready(() => {
    const root = document.getElementById('sidepanel_left');
    if (root) {
      initAccordion(root);
      updateDropdownLabels(root);
    }
  });
})();


