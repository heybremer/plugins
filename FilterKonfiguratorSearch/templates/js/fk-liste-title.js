/**
 * Listen-, Produkt-, Warenkorb-, Checkout: H1 aus localStorage (womosi_fk_state_v1), wenn keine Server-Auswahl.
 * Konfiguratorauswahl bleibt vom Produkt bis zur Bestellung sichtbar (solange localStorage gesetzt ist).
 */
(function () {
  'use strict';

  var FK_STATE_KEY = 'womosi_fk_state_v1';
  var FK_MODELL_CUSTOM = '__FK_MODELL_CUSTOM__';
  var FK_QS_KEYS = ['segment', 'typ', 'hersteller', 'basis', 'modell', 'baujahr', 'sitz', 'fkCustomModell', 'fkNichtInListe'];

  // Konfigurator bağlamı yoksa (ne GET-parametreleri ne de __-SEO filtresi yok): state'i temizle ve hiçbir şey yapma.
  try {
    var _qs = window.location.search || '';
    var _path = window.location.pathname || '';
    var _hasCfgQs = /[?&](segment|typ|hersteller|basis|modell|baujahr|sitz|fkCustomModell|fkNichtInListe)=/i.test(_qs);
    var _hasFilterPath = _path.indexOf('__') !== -1;
    if (!_hasCfgQs && !_hasFilterPath) {
      try { window.localStorage.removeItem(FK_STATE_KEY); } catch (e) {}
      return;
    }
  } catch (e) {}

  function compactFromStorage() {
    try {
      var raw = window.localStorage.getItem(FK_STATE_KEY);
      if (!raw) return '';
      var c = JSON.parse(raw);
      if (!c || typeof c !== 'object') return '';

      var isPkw = (String(c.segment || '') === 'pkw');
      var isCustomModell = (c.modell === FK_MODELL_CUSTOM || !!c.fkCustomModell || !!c.fkNichtInListe);

      if (isPkw || !isCustomModell) {
        return '';
      }

      var parts = [];
      if (String(c.segment || '') === 'pkw') parts.push('PKW');
      if (c.typLabel) parts.push(String(c.typLabel).trim());
      if (c.hersteller) parts.push(String(c.hersteller).trim());
      if (c.basis) parts.push(String(c.basis).trim());
      var m = c.modell != null ? String(c.modell).trim() : '';
      if (m === FK_MODELL_CUSTOM) {
        m = c.modellCustom ? String(c.modellCustom).trim() : '';
      } else if (!m && c.modellCustom) {
        m = String(c.modellCustom).trim();
      }
      if (m) parts.push(m);
      if (c.year) parts.push('Model ' + String(c.year).trim());
      return parts.filter(Boolean).join(' ');
    } catch (e) {
      return '';
    }
  }

  function hasCfgParamsInSearch(qs) {
    return /[?&](segment|typ|hersteller|basis|modell|baujahr|sitz|fkCustomModell|fkNichtInListe)=/i.test(String(qs || ''));
  }

  function getCfgParamsFromLocation() {
    try {
      var out = {};
      var sp = new URLSearchParams(window.location.search || '');
      FK_QS_KEYS.forEach(function (k) {
        var v = sp.get(k);
        if (v != null && String(v).trim() !== '') out[k] = String(v);
      });
      return out;
    } catch (e) {
      return {};
    }
  }

  function getCfgParamsFromStorageFallback() {
    // Only used if URL has no cfg params but page still has "__" filter path.
    try {
      var raw = window.localStorage.getItem(FK_STATE_KEY);
      if (!raw) return {};
      var c = JSON.parse(raw);
      if (!c || typeof c !== 'object') return {};

      var out = {};
      if (String(c.segment || '') === 'pkw') out.segment = 'pkw';
      if (c.typKey) out.typ = String(c.typKey);
      if (c.hersteller) out.hersteller = String(c.hersteller);
      if (c.basis) out.basis = String(c.basis);
      var m = c.modell != null ? String(c.modell).trim() : '';
      if (m === FK_MODELL_CUSTOM) {
        out.fkCustomModell = '1';
        out.fkNichtInListe = '1';
        if (c.modellCustom) out.modell = String(c.modellCustom);
      } else {
        if (!m && c.modellCustom) m = String(c.modellCustom).trim();
        if (m) out.modell = m;
        // legacy custom-only path
        if (!c.modell && c.modellCustom) out.fkCustomModell = '1';
      }
      if (c.year) out.baujahr = String(c.year);
      if (c.seatId) out.sitz = String(c.seatId);
      return out;
    } catch (e) {
      return {};
    }
  }

  function buildCfgQueryString() {
    var cfg = getCfgParamsFromLocation();
    if (!hasCfgParamsInSearch(window.location.search || '')) {
      // Keep state alive on "__" filtered routes.
      cfg = getCfgParamsFromStorageFallback();
    }
    try {
      var sp = new URLSearchParams();
      FK_QS_KEYS.forEach(function (k) {
        if (cfg[k] != null && String(cfg[k]).trim() !== '') {
          sp.set(k, String(cfg[k]));
        }
      });
      var s = sp.toString();
      return s ? ('?' + s) : '';
    } catch (e) {
      return '';
    }
  }

  /**
   * Merge current Konfigurator query params into a same-origin navigation URL.
   * @returns {string|null} new href, or null if nothing to change / skip
   */
  function applyCfgQueryToHref(hrefRaw, cfgQs) {
    if (!hrefRaw || hrefRaw.charAt(0) === '#') return null;
    if (/^javascript:|^mailto:|^tel:/i.test(hrefRaw)) return null;
    try {
      var u = new URL(hrefRaw, window.location.href);
      if (u.origin !== window.location.origin) return null;
      var p = String(u.pathname || '');
      if (!p || p === '/' || p.indexOf('/admin') === 0) return null;
      if (/\.(css|js|png|jpe?g|gif|webp|svg|pdf|zip)$/i.test(p)) return null;

      var cfg = new URLSearchParams(String(cfgQs || '').replace(/^\?/, ''));
      var existing = new URLSearchParams(u.search || '');
      var changed = false;
      FK_QS_KEYS.forEach(function (k) {
        if (existing.get(k) == null && cfg.get(k) != null) {
          existing.set(k, cfg.get(k));
          changed = true;
        }
      });
      if (!changed) return null;
      u.search = existing.toString();
      return u.toString();
    } catch (e) {
      return null;
    }
  }

  function shouldDecorateProductLinks() {
    try {
      // Only decorate when we are in a configurator context.
      var _qs2 = window.location.search || '';
      var _path2 = window.location.pathname || '';
      return hasCfgParamsInSearch(_qs2) || (_path2.indexOf('__') !== -1);
    } catch (e) {
      return false;
    }
  }

  function isProductListArticleAnchor(a) {
    if (!a || !a.closest) return false;
    // Vergleich/Merken etc. - keine Produkt-URL anreichern
    if (a.closest('.productbox-quick-actions')) return false;
    if (a.closest('#product-list')) return true;
    if (a.closest('#result-wrapper') && a.closest('.productbox')) return true;
    if (a.closest('.product-list') && a.closest('.productbox')) return true;
    if (a.closest('[itemprop="itemListElement"]')) return true;
    return false;
  }

  function collectProductListAnchors() {
    var sel = [
      '#product-list a[href]',
      '#result-wrapper .product-list a[href]',
      '.product-list .productbox a[href]',
      '[itemprop="itemListElement"] a[href]'
    ];
    var out = [];
    var seen = new Set();
    sel.forEach(function (s) {
      try {
        var nodes = document.querySelectorAll(s);
        if (!nodes || !nodes.length) return;
        Array.prototype.slice.call(nodes).forEach(function (a) {
          if (!a || seen.has(a)) return;
          if (!isProductListArticleAnchor(a)) return;
          seen.add(a);
          out.push(a);
        });
      } catch (e) {}
    });
    return out;
  }

  function decorateProductLinks() {
    if (!shouldDecorateProductLinks()) return;

    var cfgQs = buildCfgQueryString();
    if (!cfgQs) return;

    var nodes = collectProductListAnchors();

    var seen = new Set();
    nodes.forEach(function (a) {
      try {
        if (!a || !a.getAttribute) return;
        if (a.getAttribute('data-fk-decorated') === '1') return;
        var hrefRaw = a.getAttribute('href') || '';
        if (seen.has(a)) return;
        seen.add(a);

        var next = applyCfgQueryToHref(hrefRaw, cfgQs);
        if (!next) {
          a.setAttribute('data-fk-decorated', '1');
          return;
        }
        a.setAttribute('href', next);
        a.setAttribute('data-fk-decorated', '1');
      } catch (e) {}
    });
  }

  /**
   * Capture-phase click: some themes / overlays make href decoration unreliable; fix href right before navigation.
   */
  function patchProductListAnchor(a) {
    try {
      if (!shouldDecorateProductLinks()) return;
      var cfgQs = buildCfgQueryString();
      if (!cfgQs) return;
      if (!a || !isProductListArticleAnchor(a)) return;
      var hrefRaw = a.getAttribute('href') || '';
      var next = applyCfgQueryToHref(hrefRaw, cfgQs);
      if (!next) return;
      a.setAttribute('href', next);
    } catch (err) {}
  }

  function installProductLinkClickHook() {
    document.addEventListener(
      'click',
      function (e) {
        try {
          if (e.defaultPrevented) return;
          var t = e.target;
          if (!t || !t.closest) return;
          var a = t.closest('a[href]');
          if (!a) return;
          patchProductListAnchor(a);
        } catch (err) {}
      },
      true
    );
    // Statusleiste zeigt oft die URL bereits beim Hover: vorher href patchen.
    document.addEventListener(
      'mouseover',
      function (e) {
        try {
          var t = e.target;
          if (!t || !t.closest) return;
          var a = t.closest('a[href]');
          if (!a) return;
          patchProductListAnchor(a);
        } catch (err) {}
      },
      true
    );
  }

  function findTargetH1() {
    // NOVA: productdetails/details.tpl → <h1 class="product-title h2" itemprop="name"> (h2 = Bootstrap-typography, kein <h2>!)
    return (
      document.querySelector('h1.product-title.h2') ||
      document.querySelector('h1.product-title') ||
      document.querySelector('h2.product-title') ||
      document.querySelector('div.title h1.h2') ||
      document.querySelector('div.title h1') ||
      document.querySelector('h1.basket-heading') ||
      document.querySelector('#checkout h1')
    );
  }

  function skipGreenMessage(h1) {
    if (!h1) return true;
    if (h1.classList && h1.classList.contains('product-title')) return true;
    if (h1.classList && h1.classList.contains('basket-heading')) return true;
    if (h1.closest && h1.closest('#checkout')) return true;
    return false;
  }

  function applyTitle() {
    if (document.querySelector('.fk-selection-summary')) return;
    var line = compactFromStorage();
    if (!line) return;
    var h1 = findTargetH1();
    if (!h1) return;

    // Liste (Serie/Kategorie) → nur dynamisch, wenn URL echte Konfigurator-Parameter hat.
    var isProductTitle = h1.classList && h1.classList.contains('product-title');
    var isBasket      = h1.classList && h1.classList.contains('basket-heading');
    var isCheckout    = h1.closest && !!h1.closest('#checkout');
    if (!isProductTitle && !isBasket && !isCheckout) {
      var qs = window.location.search || '';
      if (!hasCfgParamsInSearch(qs)) {
        return;
      }
    }

    h1.textContent = line;
    var cls = h1.getAttribute('class') || '';
    if (cls.indexOf('fk-selection-h1') === -1) {
      h1.setAttribute('class', (cls + ' fk-selection-h1').trim());
    }
    if (skipGreenMessage(h1)) return;
    if (document.querySelector('.fk-selection-micro')) return;
    var wrap = document.createElement('div');
    wrap.className = 'fk-selection-summary';
    wrap.innerHTML =
      '<div class="fk-selection-subtitle text-muted mt-2 mb-1">' +
      '<span class="fk-selection-message"><span class="fk-selection-icon" aria-hidden="true">✔</span> Für Ihr Fahrzeug wurden passende Ergebnisse gefunden</span>' +
      '</div>';
    if (h1.parentNode) {
      h1.parentNode.insertBefore(wrap, h1.nextSibling);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      installProductLinkClickHook();
      decorateProductLinks();
      applyTitle();
      // Product lists may be loaded/replaced dynamically (pagination, filters).
      try {
        var mo = new MutationObserver(function () {
          decorateProductLinks();
        });
        mo.observe(document.documentElement || document.body, { childList: true, subtree: true });
      } catch (e) {}
    });
  } else {
    installProductLinkClickHook();
    decorateProductLinks();
    applyTitle();
    try {
      var mo2 = new MutationObserver(function () {
        decorateProductLinks();
      });
      mo2.observe(document.documentElement || document.body, { childList: true, subtree: true });
    } catch (e) {}
  }
})();
