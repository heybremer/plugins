(function () {
  'use strict';

  window.WomosiHomepageInjectorEnabled = 1;

  function normalizeSel(s) {
    s = String(s || '').trim();
    if (!s) return '';
    var m1 = s.match(/class\s*=\s*['"]([^'"]+)['"]/i);
    if (m1 && m1[1]) {
      var c = String(m1[1]).trim().split(/\s+/)[0];
      if (c) return '.' + c;
    }
    var m2 = s.match(/id\s*=\s*['"]([^'"]+)['"]/i);
    if (m2 && m2[1]) {
      var id = String(m2[1]).trim();
      if (id) return '#' + id;
    }
    if (/^[A-Za-z0-9_-]+$/.test(s)) return '#' + s;
    var toks = s.split(/\s+/);
    if (toks.length === 2 && /^[A-Za-z]+$/.test(toks[0]) && /^[A-Za-z0-9_-]+$/.test(toks[1])) return toks[0] + '.' + toks[1];
    return s;
  }

  function safeQuery(sel, root) {
    try { return (root || document).querySelector(sel); } catch (e) { return null; }
  }
  function safeQueryAll(sel, root) {
    try { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); } catch (e) { return []; }
  }

  function findWidgetRoot(key) {
    if (key === 'MarkeFiltre') {
      return document.getElementById('markefiltre-mount') || document.getElementById('br-caravan-root');
    }
    if (key === 'Top10List') {
      return safeQuery('[data-top10list-wrapper="1"]');
    }
    if (key === 'CardProduct') {
      return safeQuery('[data-cardproduct-injected="1"]');
    }
    return null;
  }

  function moveNode(node, target, mode) {
    if (!node || !target) return false;
    try {
      if (mode === 'prepend') {
        target.insertBefore(node, target.firstChild);
      } else if (mode === 'append') {
        target.appendChild(node);
      } else if (mode === 'before') {
        target.parentNode && target.parentNode.insertBefore(node, target);
      } else {
        target.parentNode && target.parentNode.insertBefore(node, target.nextSibling);
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function ensureContainer(target, mode) {
    var id = 'womosi-homepage-widgets';
    var c = document.getElementById(id);
    if (c) return c;
    c = document.createElement('div');
    c.id = id;
    c.setAttribute('data-homepage-injector', '1');
    // minimal safe styles
    c.style.display = 'block';
    c.style.width = '100%';

    moveNode(c, target, mode);
    return c;
  }

  function runOnce() {
    var cfg = window.WomosiHomepageInjectorConfig || {};
    if (cfg && cfg.enabled === false) return false;
    var targetSel = normalizeSel(cfg.targetSelector || 'div.index-filter-konfigurator');
    var mode = String(cfg.targetMode || 'after').trim();
    if (['prepend', 'append', 'before', 'after'].indexOf(mode) === -1) mode = 'after';

    var target = targetSel ? safeQuery(targetSel) : null;
    if (!target) {
      target = safeQuery('div.index-filter-konfigurator') || safeQuery('main#main-wrapper') || safeQuery('main') || document.body;
    }
    if (!target) return false;

    var container = ensureContainer(target, mode);
    if (!container) return false;

    var order = cfg.order;
    if (!Array.isArray(order) || !order.length) {
      order = ['CardProduct', 'MarkeFiltre', 'Top10List'];
    }

    var movedAny = false;
    order.forEach(function (key) {
      var root = findWidgetRoot(String(key));
      if (!root) return;
      // If the root is a nested element, prefer moving its outer container wrapper when possible
      var wrapper = root;
      if (key === 'MarkeFiltre' && root.id === 'br-caravan-root') {
        wrapper = root.closest && root.closest('.container') ? root.closest('.container') : root;
      }
      if (key === 'Top10List') {
        wrapper = root.closest && root.closest('.container') ? root.closest('.container') : root;
      }
      if (key === 'CardProduct') {
        wrapper = root.closest && root.closest('.container') ? root.closest('.container') : root;
      }
      if (wrapper && wrapper.parentNode !== container) {
        container.appendChild(wrapper);
        movedAny = true;
      } else if (wrapper) {
        // already in container; ensure correct order
        container.appendChild(wrapper);
      }
    });

    return movedAny;
  }

  function start() {
    var stopAt = Date.now() + 60000;
    var ran = false;

    function tick() {
      ran = runOnce() || ran;
      if (Date.now() > stopAt) return;
    }

    tick();
    var iv = setInterval(function () {
      tick();
      if (Date.now() > stopAt) clearInterval(iv);
    }, 250);

    try {
      var mo = new MutationObserver(function () {
        tick();
        if (Date.now() > stopAt) {
          try { mo.disconnect(); } catch (e) {}
        }
      });
      mo.observe(document.documentElement || document.body, { childList: true, subtree: true });
    } catch (e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();

