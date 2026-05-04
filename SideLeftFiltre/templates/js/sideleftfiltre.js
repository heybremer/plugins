/* SideLeftFiltre: reorder filters in #sidepanel_left by configured IDs */
(function () {
  'use strict';

  function readConfig() {
    var el = document.getElementById('sideleftfiltre-config');
    if (!el) return { order: [], open: [], close: [], rootSelector: '#sidepanel_left' };
    try {
      var raw = (el.textContent || '').trim();
      if (!raw) return { order: [], open: [], close: [], rootSelector: '#sidepanel_left' };
      var parsed = JSON.parse(raw);
      var order = Array.isArray(parsed.order) ? parsed.order : [];
      var open = Array.isArray(parsed.open) ? parsed.open : [];
      var close = Array.isArray(parsed.close) ? parsed.close : [];
      var rootSelector = (parsed.rootSelector && String(parsed.rootSelector)) || '#sidepanel_left';
      return { order: order, open: open, close: close, rootSelector: rootSelector };
    } catch (e) {
      return { order: [], open: [], close: [], rootSelector: '#sidepanel_left' };
    }
  }

  function uniqLower(list) {
    var out = [];
    var seen = Object.create(null);
    for (var i = 0; i < list.length; i++) {
      var v = String(list[i] || '').trim();
      if (!v) continue;
      var k = v.toLowerCase();
      if (seen[k]) continue;
      seen[k] = true;
      out.push(v);
    }
    return out;
  }

  function reorderOnce(root, orderIds) {
    if (!root || !orderIds.length) return false;

    // candidates: direct children that have an id
    var children = Array.prototype.slice.call(root.children || []);
    if (!children.length) return false;

    var byId = Object.create(null);
    for (var i = 0; i < children.length; i++) {
      var c = children[i];
      if (!c || c.nodeType !== 1) continue;
      var id = (c.getAttribute('id') || '').trim();
      if (!id) continue;
      var key = id.toLowerCase();
      if (!byId[key]) byId[key] = [];
      byId[key].push(c);
    }

    // build new order
    var newNodes = [];
    var used = new Set();
    for (var j = 0; j < orderIds.length; j++) {
      var want = String(orderIds[j] || '').trim().toLowerCase();
      if (!want) continue;
      var arr = byId[want];
      if (arr && arr.length) {
        // use first occurrence in requested order, keep duplicates for the end
        for (var a = 0; a < arr.length; a++) {
          var node = arr[a];
          if (!used.has(node)) {
            newNodes.push(node);
            used.add(node);
            break;
          }
        }
      }
    }

    // append remaining nodes in original order
    for (var k = 0; k < children.length; k++) {
      var node2 = children[k];
      if (node2 && !used.has(node2)) {
        newNodes.push(node2);
        used.add(node2);
      }
    }

    // apply
    for (var n = 0; n < newNodes.length; n++) {
      root.appendChild(newNodes[n]);
    }
    return true;
  }

  function findRoot(selector) {
    try {
      return document.querySelector(selector);
    } catch (e) {
      return null;
    }
  }

  function cssEscapeIdent(s) {
    // minimal escape for #id usage
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(s);
    return String(s).replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
  }

  function setCollapseState(blockEl, shouldOpen) {
    if (!blockEl) return;
    var btn =
      blockEl.querySelector('button.btn-filter-box[data-toggle="collapse"][data-target]') ||
      blockEl.querySelector('button[data-toggle="collapse"][data-target]') ||
      blockEl.querySelector('[data-toggle="collapse"][data-target]');
    if (!btn) return;

    var targetSel = String(btn.getAttribute('data-target') || '').trim();
    var panel = null;
    if (targetSel && targetSel.charAt(0) === '#') {
      panel = document.querySelector(targetSel);
    }

    if (shouldOpen) {
      btn.classList.remove('collapsed');
      btn.setAttribute('aria-expanded', 'true');
      if (panel) panel.classList.add('show');
    } else {
      btn.classList.add('collapsed');
      btn.setAttribute('aria-expanded', 'false');
      if (panel) panel.classList.remove('show');
    }
  }

  function applyOpenClose(root, openIds, closeIds) {
    if (!root) return;
    // close first, then open wins if both set
    for (var i = 0; i < closeIds.length; i++) {
      var idc = String(closeIds[i] || '').trim();
      if (!idc) continue;
      var elc = root.querySelector('#' + cssEscapeIdent(idc)) || document.getElementById(idc);
      if (elc) setCollapseState(elc, false);
    }
    for (var j = 0; j < openIds.length; j++) {
      var ido = String(openIds[j] || '').trim();
      if (!ido) continue;
      var elo = root.querySelector('#' + cssEscapeIdent(ido)) || document.getElementById(ido);
      if (elo) setCollapseState(elo, true);
    }
  }

  function init() {
    var cfg = readConfig();
    var order = uniqLower(cfg.order || []);
    var open = uniqLower(cfg.open || []);
    var close = uniqLower(cfg.close || []);
    var root = findRoot(cfg.rootSelector || '#sidepanel_left');
    if (!root) return;
    reorderOnce(root, order);
    applyOpenClose(root, open, close);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

