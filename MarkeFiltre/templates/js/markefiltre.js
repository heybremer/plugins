(function () {
  'use strict';

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function mfBrandsEndpoint() {
    try {
      var baseEl = document.querySelector('base[href]');
      if (baseEl) {
        return new URL('mf-brands', baseEl.href).href;
      }
    } catch (_) {}
    try {
      var p = String(window.location.pathname || '');
      var parts = p.replace(/^\/+|\/+$/g, '').split('/');
      var first = parts[0] || '';
      if (first && first !== 'index.php') {
        return window.location.origin + '/' + first + '/mf-brands';
      }
    } catch (_) {}
    return window.location.origin + '/mf-brands';
  }

  function mfKonfigUrlEndpoint() {
    try {
      var baseEl = document.querySelector('base[href]');
      if (baseEl) {
        return new URL('mf-konfig-url', baseEl.href).href;
      }
    } catch (_) {}
    try {
      var p2 = String(window.location.pathname || '');
      var parts2 = p2.replace(/^\/+|\/+$/g, '').split('/');
      var first2 = parts2[0] || '';
      if (first2 && first2 !== 'index.php') {
        return window.location.origin + '/' + first2 + '/mf-konfig-url';
      }
    } catch (_) {}
    return window.location.origin + '/mf-konfig-url';
  }

  /** Wenn /mf-konfig-url fehlt: WM = Marke__Produkttyp__Serie, PKW = Marke__Serie__Produkttyp. */
  function mfClientTripleUrl(segment, brandName) {
    var base = mfShopBaseUrl().replace(/\/+$/, '');
    var WM_PT = 'Wohnmobil-Schonbezuege-Fahrer-und-Beifahrer-Set';
    var PKW_PT = 'Fahrer-Beifahrer-Set-Schonbezuege';
    function slug(s) {
      s = String(s || '').trim();
      if (!s) return '';
      s = s.replace(/ä/gi, 'ae').replace(/ö/gi, 'oe').replace(/ü/gi, 'ue').replace(/ß/gi, 'ss');
      s = s.replace(/\s+/g, '-').replace(/[^a-zA-Z0-9\-]/g, '');
      return s.replace(/-+/g, '-').replace(/^-+|-+$/g, '') || '';
    }
    var b = slug(brandName);
    if (!b) return '';
    if (String(segment || '').toLowerCase() === 'pkw') {
      return base + '/' + b + '__C__' + PKW_PT;
    }
    return base + '/' + b + '__' + WM_PT + '__C';
  }

  function mfLooksLikeSingleSegmentMerkmalUrl(href) {
    try {
      var u = new URL(href, window.location.origin);
      var p = String(u.pathname || '').replace(/^\/+|\/+$/g, '');
      if (!p || p.indexOf('__') !== -1) return false;
      return p.indexOf('/') === -1;
    } catch (_) {
      return false;
    }
  }

  /** Shop-Root-URL (ohne trailing slash) — gleiche Logik wie mfBrandsEndpoint. */
  function mfShopBaseUrl() {
    try {
      var baseEl = document.querySelector('base[href]');
      if (baseEl) {
        return new URL('/', baseEl.href).href.replace(/\/$/, '');
      }
    } catch (_) {}
    try {
      var p = String(window.location.pathname || '');
      var parts = p.replace(/^\/+|\/+$/g, '').split('/');
      var first = parts[0] || '';
      if (first && first !== 'index.php') {
        return window.location.origin + '/' + first;
      }
    } catch (_) {}
    return window.location.origin;
  }

  /**
   * Schreibt Konfigurator-Vorauswahl in localStorage und navigiert zur Listen-URL.
   * Server: /mf-konfig-url?segment=pkw|wohnmobil&brand=… → /Marke__Produkttyp__Serie (z. B. Adria__Wohnmobil-…__C).
   * Fallback: Merkmal-Link (targetUrl) oder Startseite.
   */
  function mfOpenKonfigWithBrand(segment, brandName, targetUrl) {
    var seg = String(segment || '').toLowerCase() === 'pkw' ? 'pkw' : 'wohnmobil';
    var name = String(brandName || '').trim();
    var fallback = String(targetUrl || '').trim();

    function writeStorage(extra) {
      try {
        var existing = {};
        try {
          var raw = window.localStorage.getItem('womosi_fk_state_v1');
          if (raw) existing = JSON.parse(raw) || {};
        } catch (_) {}
        var st = extra && typeof extra === 'object' ? extra : {};
        var payload = {
          step:      2,
          segment:   seg,
          mfPreset:  'brand_click',
          forceSerieSelect: 1,
          typKey:    String(st.typKey != null && st.typKey !== '' ? st.typKey : (existing.typKey || '')),
          typLabel:  String(st.typLabel != null && st.typLabel !== '' ? st.typLabel : (existing.typLabel || st.typKey || '')),
          hersteller: String(st.hersteller != null && st.hersteller !== '' ? st.hersteller : name),
          basis:     String(st.basis != null ? st.basis : (existing.basis || '')),
          modell:    String(st.modell != null ? st.modell : ''),
          modellCustom: '',
          year:      '',
          seatId:    String(st.seatId != null ? st.seatId : (existing.seatId || '')),
          seatLabel: String(st.seatLabel != null ? st.seatLabel : (existing.seatLabel || '')),
          serie:     String(st.serie != null && st.serie !== '' ? st.serie : (existing.serie || 'C'))
        };
        window.localStorage.setItem('womosi_fk_state_v1', JSON.stringify(payload));
      } catch (_) {}
    }

    function go(url) {
      try {
        if (url && url !== '#') {
          window.location.href = url;
          return true;
        }
      } catch (_) {}
      return false;
    }

    var ep = mfKonfigUrlEndpoint();
    var qs = 'segment=' + encodeURIComponent(seg) + '&brand=' + encodeURIComponent(name);
    fetch(ep + (ep.indexOf('?') === -1 ? '?' : '&') + qs, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) {
        return r.text().then(function (t) {
          try {
            return JSON.parse(t);
          } catch (_) {
            return {};
          }
        });
      })
      .then(function (j) {
        var u = j && j.url ? String(j.url).trim() : '';
        var st = j && j.state && typeof j.state === 'object' ? j.state : null;
        if (u) {
          writeStorage(st);
          go(u);
          return;
        }
        var clientU = (seg === 'wohnmobil' || seg === 'pkw') ? mfClientTripleUrl(seg, name) : '';
        if (clientU) {
          writeStorage(null);
          go(clientU);
          return;
        }
        writeStorage(null);
        if (!mfLooksLikeSingleSegmentMerkmalUrl(fallback) && go(fallback)) return;
        go(mfShopBaseUrl() + '/');
      })
      .catch(function () {
        var clientU2 = mfClientTripleUrl(seg, name);
        if (clientU2) {
          writeStorage(null);
          go(clientU2);
          return;
        }
        writeStorage(null);
        if (!mfLooksLikeSingleSegmentMerkmalUrl(fallback) && go(fallback)) return;
        go(mfShopBaseUrl() + '/');
      });
  }

  /**
   * Ein Widget-Root initialisieren. Mehrere #br-caravan-root im Dokument sind im Wildwuchs möglich;
   * getElementById nimmt nur das erste — daher querySelectorAll + Schleife.
   */
  function initRoot(root) {
    if (!root || root.nodeType !== 1) return;
    if (root.dataset.initialized === '1') return;
    if (root.getAttribute('data-mf-invalid') === '1') return;

    var letterbar = qs('[data-mf-letterbar]', root);
    var grid = qs('[data-mf-grid]', root);
    if (!letterbar || !grid) {
      root.setAttribute('data-mf-invalid', '1');
      return;
    }

    root.dataset.initialized = '1';

    var tabs = qsa('[data-mf-tab]', root);
    var search = qs('[data-mf-search]', root);
    var moreBtn = qs('[data-mf-more]', root);

    var sets = { PKW: [], Wohnmobil: [] };
    var loaded = false;

    function mergeBrandsFromWindow() {
      try {
        var w = window.MarkeFiltreBrands;
        if (!w) return;
        if (Array.isArray(w.PKW)) sets.PKW = w.PKW;
        if (Array.isArray(w.Wohnmobil)) sets.Wohnmobil = w.Wohnmobil;
        loaded = true;
      } catch (_) {}
    }

    /** Immer zuerst: JSON im Widget (application/json), unabhängig von Script-Ausführungsreihenfolge. */
    function mergeBrandsFromDom() {
      var el = root.querySelector('script.mf-brands-json[type="application/json"]');
      if (!el) return false;
      try {
        var data = JSON.parse(el.textContent || '{}');
        if (!data || typeof data !== 'object') return false;
        var ok = false;
        if (Array.isArray(data.PKW)) {
          sets.PKW = data.PKW;
          ok = true;
        }
        if (Array.isArray(data.Wohnmobil)) {
          sets.Wohnmobil = data.Wohnmobil;
          ok = true;
        }
        if (ok) {
          loaded = true;
          try {
            window.MarkeFiltreBrands = data;
          } catch (_) {}
          return true;
        }
      } catch (_) {}
      return false;
    }

    function fetchBrands() {
      if (mergeBrandsFromDom()) {
        return Promise.resolve();
      }
      try {
        if (window.MarkeFiltreBrands && (Array.isArray(window.MarkeFiltreBrands.PKW) || Array.isArray(window.MarkeFiltreBrands.Wohnmobil))) {
          mergeBrandsFromWindow();
          return Promise.resolve();
        }
      } catch (_) {}

      return fetch(mfBrandsEndpoint(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && Array.isArray(data.PKW)) sets.PKW = data.PKW;
          if (data && Array.isArray(data.Wohnmobil)) sets.Wohnmobil = data.Wohnmobil;
          loaded = true;
        })
        .catch(function () {
          loaded = false;
        });
    }

    function updateTabVisibility() {
      tabs = qsa('[data-mf-tab]', root);
      tabs.forEach(function (t) {
        var key = t.dataset.mfTab || '';
        var list = sets[key];
        var has = Array.isArray(list) && list.length > 0;
        t.style.display = has ? '' : 'none';
      });
    }

    var state = {
      activeSet: 'PKW',
      letter: 'all',
      query: '',
      expanded: false
    };

    function currentMap() {
      var list = sets[state.activeSet] || [];
      return (Array.isArray(list) ? list : []).map(function (it) {
        var name = it && it.name ? String(it.name) : '';
        var url  = it && it.url  ? String(it.url)  : '';
        return { name: name, url: url };
      }).filter(function (x) { return !!x.name; });
    }

    function buildLetters(names) {
      letterbar = qs('[data-mf-letterbar]', root);
      if (!letterbar) return;
      letterbar.innerHTML = '';
      var all = document.createElement('button');
      all.type = 'button';
      all.className = 'mf-chip mf-is-active';
      all.dataset.letter = 'all';
      all.textContent = 'Alle';
      letterbar.appendChild(all);

      var letters = {};
      names.forEach(function (n) {
        var ch = (n || '').trim().charAt(0).toUpperCase();
        if (ch) letters[ch] = true;
      });
      Object.keys(letters).sort().forEach(function (ch) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'mf-chip';
        b.dataset.letter = ch;
        b.textContent = ch;
        letterbar.appendChild(b);
      });
    }

    function applyActiveChips() {
      letterbar = qs('[data-mf-letterbar]', root);
      if (!letterbar) return;
      qsa('.mf-chip', letterbar).forEach(function (c) {
        c.classList.toggle('mf-is-active', (c.dataset.letter || '') === state.letter);
      });
    }

    function filterList(list) {
      var out = list.slice();
      if (state.letter !== 'all') {
        out = out.filter(function (it) { return it.name.toUpperCase().startsWith(state.letter); });
      }
      if (state.query) {
        var q = state.query.toLowerCase();
        out = out.filter(function (it) { return it.name.toLowerCase().indexOf(q) !== -1; });
      }
      return out;
    }

    function render() {
      grid = qs('[data-mf-grid]', root);
      if (!grid) return;
      var all = currentMap();
      var filtered = filterList(all);
      var limit = state.expanded ? filtered.length : Math.min(filtered.length, 18);

      grid.innerHTML = '';
      filtered.slice(0, limit).forEach(function (it) {
        var a = document.createElement('a');
        a.className = 'mf-brand';
        a.href = it.url || '#';
        a.textContent = it.name;
        a.dataset.mfName    = it.name;
        a.dataset.mfSegment = state.activeSet;
        grid.appendChild(a);
      });

      moreBtn = qs('[data-mf-more]', root);
      if (moreBtn) {
        moreBtn.style.display = filtered.length > 18 ? 'inline-flex' : 'none';
        moreBtn.textContent = state.expanded ? 'Einklappen' : 'Weitere Marken';
      }
    }

    function setActiveSet(name) {
      state.activeSet = name;
      state.letter = 'all';
      state.query = '';
      state.expanded = false;
      search = qs('[data-mf-search]', root);
      if (search) search.value = '';

      tabs = qsa('[data-mf-tab]', root);
      tabs.forEach(function (t) {
        t.classList.toggle('mf-is-active', (t.dataset.mfTab || '') === name);
      });

      var names = currentMap().map(function (x) { return x.name; });
      buildLetters(names);
      applyActiveChips();
      render();
    }

    root.addEventListener('click', function (e) {
      var el = e.target;
      if (!el || !el.closest) return;

      // Marka tıklaması: Fahrzeug-Marke karakteristik sayfasına git (PKW ve Wohnmobil).
      var brandEl = el.closest('a.mf-brand[data-mf-name]');
      if (brandEl && root.contains(brandEl)) {
        e.preventDefault();
        var segKey = String(brandEl.dataset.mfSegment || state.activeSet || '').toLowerCase();
        var name = String(brandEl.dataset.mfName || brandEl.textContent || '').trim();
        var listUrl = '';
        try {
          listUrl = String(brandEl.getAttribute('href') || brandEl.href || '').trim();
        } catch (_) {}
        try {
          var payload = {
            step: 2,
            segment: segKey === 'pkw' ? 'pkw' : 'wohnmobil',
            hersteller: name,
            basis: '',
            modell: '',
            modellCustom: '',
            year: '',
            seatId: '',
            seatLabel: '',
            serie: '',
            typKey: '',
            typLabel: '',
            forceSerieSelect: 0,
            mfPreset: ''
          };
          window.localStorage.setItem('womosi_fk_state_v1', JSON.stringify(payload));
        } catch (_) {}
        if (listUrl && listUrl !== '#') {
          window.location.href = listUrl;
          return;
        }
        return;
      }

      var tabBtn = el.closest('button[data-mf-tab]');
      if (tabBtn && root.contains(tabBtn)) {
        setActiveSet(tabBtn.dataset.mfTab || 'PKW');
        return;
      }
      var moreEl = el.closest('button[data-mf-more]');
      if (moreEl && root.contains(moreEl)) {
        state.expanded = !state.expanded;
        render();
        return;
      }
      var letterBtn = el.closest('button[data-letter]');
      if (letterBtn && root.contains(letterBtn)) {
        state.letter = letterBtn.dataset.letter || 'all';
        state.expanded = false;
        applyActiveChips();
        render();
      }
    });

    root.addEventListener('input', function (e) {
      var el = e.target;
      if (!el || !el.matches || !el.matches('[data-mf-search]')) return;
      if (!root.contains(el)) return;
      state.query = (el.value || '').trim();
      state.expanded = false;
      render();
    });

    function applyAfterLoad() {
      mergeBrandsFromWindow();
      letterbar = qs('[data-mf-letterbar]', root);
      grid = qs('[data-mf-grid]', root);
      moreBtn = qs('[data-mf-more]', root);
      search = qs('[data-mf-search]', root);
      tabs = qsa('[data-mf-tab]', root);
      updateTabVisibility();
      var hasPKW = Array.isArray(sets.PKW) && sets.PKW.length > 0;
      var hasWohn = Array.isArray(sets.Wohnmobil) && sets.Wohnmobil.length > 0;
      if (hasPKW) {
        setActiveSet('PKW');
      } else if (hasWohn) {
        setActiveSet('Wohnmobil');
      } else {
        if (letterbar) letterbar.style.display = 'none';
        if (grid) grid.innerHTML = '';
        if (moreBtn) moreBtn.style.display = 'none';
      }
    }

    function maybeRetryAfterOpc() {
      var g = qs('[data-mf-grid]', root);
      if (!g || !g.children || g.children.length > 0) return;
      mergeBrandsFromDom();
      mergeBrandsFromWindow();
      var hasPKW = Array.isArray(sets.PKW) && sets.PKW.length > 0;
      var hasWohn = Array.isArray(sets.Wohnmobil) && sets.Wohnmobil.length > 0;
      if (!hasPKW && !hasWohn) return;
      applyAfterLoad();
    }

    fetchBrands().finally(function () {
      applyAfterLoad();
      setTimeout(maybeRetryAfterOpc, 50);
      setTimeout(maybeRetryAfterOpc, 400);
      setTimeout(maybeRetryAfterOpc, 1500);
    });
  }

  function initAll() {
    var roots = document.querySelectorAll('#br-caravan-root');
    for (var i = 0; i < roots.length; i++) {
      initRoot(roots[i]);
    }
  }

  var moTimer = null;
  /** Widget kommt manchmal erst nach anderen Outputfiltern / OPC ins DOM — einmal beobachten. */
  function scheduleLateInit() {
    if (document.querySelectorAll('#br-caravan-root').length > 0) {
      return;
    }
    var pending = false;
    var obs = new MutationObserver(function () {
      if (pending) return;
      if (document.querySelectorAll('#br-caravan-root').length === 0) return;
      pending = true;
      requestAnimationFrame(function () {
        pending = false;
        initAll();
        try { obs.disconnect(); } catch (_) {}
        if (moTimer) clearTimeout(moTimer);
      });
    });
    try {
      obs.observe(document.documentElement || document.body, { childList: true, subtree: true });
    } catch (_) {}
    moTimer = setTimeout(function () {
      try { obs.disconnect(); } catch (_) {}
    }, 12000);
  }

  function boot() {
    function run() {
      requestAnimationFrame(function () {
        initAll();
        scheduleLateInit();
      });
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else {
      run();
    }
  }

  boot();
})();
