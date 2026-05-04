/**
 * Dynamic micro konfigurator for product list page (under .productlist-page-nav-header-m).
 * Uses /fk-search?mode=options to populate dropdowns and /fk-search (series list) for Serie.
 */
(function () {
  'use strict';

  var ROOT_SEL = '.fk-selection-micro[data-fk-micro-dynamic="1"]';
  var FK_STATE_KEY = 'womosi_fk_state_v1';
  var FK_QS_KEYS = ['segment', 'typ', 'hersteller', 'basis', 'modell', 'baujahr', 'sitz', 'serie', 'fkCustomModell', 'fkNichtInListe'];
  var SERIES_META = {
    ZM: { label: 'Serie ZM', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/zm-serie.avif' },
    1: { label: 'Serie 01', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/serie-01.avif' },
    FT: { label: 'Serie FT', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/ft-serie.avif' },
    FNL: { label: 'Serie FNL', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/fnl-serie.avif' },
    V: { label: 'Serie V', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/v-serie.avif' },
    TK600: { label: 'Serie TK600', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/tk600-serie.avif' },
    T: { label: 'Serie T', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/t-serie.avif' },
    TF: { label: 'Serie TF', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/tf-serie.avif' },
    SL: { label: 'Serie SL', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/sl-serie.avif' },
    PS: { label: 'Serie PS', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/ps-serie.avif' },
    PL: { label: 'Serie PL', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/pl-serie.avif' },
    NL: { label: 'Serie NL', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/nl-serie.avif' },
    N3: { label: 'Serie N3', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/n3-serie.avif' },
    MD: { label: 'Serie MD', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/md-serie.avif' },
    M: { label: 'Serie M', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/m-serie.avif' },
    KT300: { label: 'Serie KT3', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/kt300-serie.avif' },
    KT: { label: 'Serie KT', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/kt-serie.avif' },
    S1: { label: 'Serie 01', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/serie-01.avif' },
    AH: { label: 'Serie AH', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/ah-serie.avif' },
    GT: { label: 'Serie GT', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/gt-serie.avif' },
    C: { label: 'Serie C', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/c-serie.avif' },
    DPL: { label: 'Serie DPL', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/dpl-serie.avif' },
    H: { label: 'Serie H', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/h-serie.avif' },
    PLKT: { label: 'Serie PLKT', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/plkt-serie.avif' },
    WPL: { label: 'Serie WPL', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/wpl-serie.avif' },
    WMS: { label: 'Serie WMS', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/wms-serie.avif' },
    HW: { label: 'Serie HW', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/hw-serie.avif' },
    BC: { label: 'Serie BC', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/bc-serie.avif' },
    DS: { label: 'Serie DS', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/ds-serie.avif' },
    LT: { label: 'Serie LT', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/lt-serie.avif' },
    800: { label: 'Serie 800er', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/800er-serie.avif' },
    WMA: { label: 'Aguti Sitze', color: '#f5f5f5', img: 'https://womosi.de/mediafiles/seat/aguti-sitze-serie.avif' }
  };

  var SERIES_HIDDEN = Object.create(null);

  function applyFkSeriesConfig(cfg) {
    if (!cfg || typeof cfg !== 'object') return;
    if (cfg.meta && typeof cfg.meta === 'object') {
      Object.keys(cfg.meta).forEach(function (k) {
        var m = cfg.meta[k];
        if (m && typeof m === 'object') {
          SERIES_META[k] = {
            label: m.label != null ? String(m.label) : (SERIES_META[k] && SERIES_META[k].label) || String(k),
            color: m.color != null && String(m.color) !== '' ? String(m.color) : (SERIES_META[k] && SERIES_META[k].color) || '#f5f5f5',
            img: m.img != null ? String(m.img) : (SERIES_META[k] && SERIES_META[k].img) || ''
          };
        }
      });
    }
    SERIES_HIDDEN = Object.create(null);
    (cfg.hidden || []).forEach(function (h) {
      var s = String(h || '').trim();
      if (s) SERIES_HIDDEN[s.toUpperCase()] = true;
    });
  }
  if (typeof window.FK_SERIES_CONFIG !== 'undefined' && window.FK_SERIES_CONFIG) {
    applyFkSeriesConfig(window.FK_SERIES_CONFIG);
  }

  function isSerieHidden(val) {
    if (!val) return true;
    var c = String(val).trim();
    if (!c) return true;
    if (SERIES_HIDDEN[c.toUpperCase()]) return true;
    var stripped = c.replace(/^Serie\s+/i, '').trim();
    if (SERIES_HIDDEN[stripped.toUpperCase()]) return true;
    return false;
  }

  function findSeriesMeta(code) {
    if (!code) return null;
    var c = String(code).trim();
    if (SERIES_META[c]) return SERIES_META[c];
    var stripped = c.replace(/^Serie\s+/i, '');
    if (SERIES_META[stripped]) return SERIES_META[stripped];
    var upper = stripped.toUpperCase();
    for (var k in SERIES_META) {
      if (k.toUpperCase() === upper) return SERIES_META[k];
    }
    for (var k2 in SERIES_META) {
      if (SERIES_META[k2].label && SERIES_META[k2].label.toUpperCase() === c.toUpperCase()) {
        return SERIES_META[k2];
      }
    }
    return null;
  }


  function $(sel, root) { return (root || document).querySelector(sel); }
  function isPkwFromUrlOrStorage() {
    try {
      var sp = new URLSearchParams(window.location.search || '');
      var seg = (sp.get('segment') || '').toLowerCase();
      if (seg === 'pkw') return true;
    } catch (e) { }
    try {
      var raw = window.localStorage.getItem(FK_STATE_KEY);
      if (!raw) return false;
      var s = JSON.parse(raw);
      return String(s && s.segment || '') === 'pkw';
    } catch (e2) {
      return false;
    }
  }

  function baseUrl() {
    try {
      if (window.WomosiFkBase && String(window.WomosiFkBase).trim()) {
        return String(window.WomosiFkBase).replace(/\/+$/, '');
      }
    } catch (e) { }
    try {
      var b = document.querySelector('base[href]');
      if (b && b.href) {
        return String(b.href).replace(/\/+$/, '');
      }
    } catch (e2) { }
    return ''; // relative fallback
  }

  function fetchJson(path, params) {
    var b = baseUrl();
    var url = (b ? (b + path) : path);
    try {
      var sp = new URLSearchParams();
      Object.keys(params || {}).forEach(function (k) {
        var v = params[k];
        if (v == null) return;
        v = String(v).trim();
        if (!v) return;
        sp.set(k, v);
      });
      var qs = sp.toString();
      if (qs) url += (url.indexOf('?') === -1 ? '?' : '&') + qs;
    } catch (e) { }
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  var seriesConfigPromise = null;
  function ensureSeriesConfigLoaded() {
    try {
      if (typeof window.FK_SERIES_CONFIG !== 'undefined' && window.FK_SERIES_CONFIG) {
        applyFkSeriesConfig(window.FK_SERIES_CONFIG);
        return Promise.resolve();
      }
    } catch (e) { }
    if (seriesConfigPromise) return seriesConfigPromise;
    seriesConfigPromise = fetchJson('/fk-search', { mode: 'series_meta' })
      .then(function (cfg) {
        try { applyFkSeriesConfig(cfg); } catch (_) { }
      })
      .catch(function () { })
      .finally(function () { });
    return seriesConfigPromise;
  }

  function uniqSorted(values) {
    var out = [];
    var seen = Object.create(null);
    (values || []).forEach(function (x) {
      var s = String(x || '').trim();
      if (!s || seen[s]) return;
      seen[s] = true;
      out.push(s);
    });
    out.sort(function (a, b) { return a.localeCompare(b); });
    return out;
  }

  function fillSelect(sel, values, keep) {
    if (!sel) return;
    var k = keep != null ? String(keep) : '';
    sel.innerHTML = '';
    var emptyOpt = document.createElement('option');
    emptyOpt.value = '';
    emptyOpt.textContent = 'Bitte wählen';
    sel.appendChild(emptyOpt);
    (values || []).forEach(function (v) {
      var opt = document.createElement('option');
      opt.value = String(v);
      opt.textContent = String(v);
      sel.appendChild(opt);
    });
    if (k) {
      sel.value = k;
    }
  }

  function getCfgFromUrlOrStorage() {
    var out = {};
    try {
      var sp = new URLSearchParams(window.location.search || '');
      FK_QS_KEYS.forEach(function (k) {
        var v = sp.get(k);
        if (v != null && String(v).trim() !== '') out[k] = String(v);
      });
    } catch (e) { }

    // If there is no qs, fallback to storage (SEO pages often have none).
    if (!Object.keys(out).length) {
      try {
        var raw = window.localStorage.getItem(FK_STATE_KEY);
        if (raw) {
          var s = JSON.parse(raw);
          if (s && typeof s === 'object') {
            if (String(s.segment || '') === 'pkw') out.segment = 'pkw';
            if (s.typKey) out.typ = String(s.typKey);
            if (s.hersteller) out.hersteller = String(s.hersteller);
            if (s.basis) out.basis = String(s.basis);
            if (s.modellCustom && !s.modell) {
              out.modell = String(s.modellCustom);
              out.fkCustomModell = '1';
            } else if (s.modell) {
              out.modell = String(s.modell);
            }
            if (s.year) out.baujahr = String(s.year);
            if (s.seatId) out.sitz = String(s.seatId);
            if (s.serie) out.serie = String(s.serie);
            // Marker from MarkeFiltre: allow intentional serie preselect
            if (s.forceSerieSelect) out.forceSerieSelect = '1';
            if (s.mfPreset) out.mfPreset = String(s.mfPreset);
          }
        }
      } catch (e2) { }
    }
    return out;
  }

  function initOne(root) {
    if (!root) return;

    var state = getCfgFromUrlOrStorage();
    var isPkw = isPkwFromUrlOrStorage();
    if (!state.segment) {
      // No explicit segment in state; check which tab is selected in HTML (honors ordering)
      var activeTab = root.querySelector('.fk-micro-tab[aria-selected="true"]');
      if (activeTab) {
        isPkw = (activeTab.getAttribute('data-fk-micro-segment') === 'pkw');
      }
    }
    root.classList.toggle('fk-micro-is-pkw', !!isPkw);

    // Segment tabs (Wohnmobil / PKW)
    var tabs = root.querySelectorAll('.fk-micro-tab[data-fk-micro-segment]');
    function tabAllowed(seg) {
      try {
        for (var i = 0; i < tabs.length; i++) {
          var t = tabs[i];
          if (!t) continue;
          if (String(t.getAttribute('data-fk-micro-segment') || '') !== String(seg)) continue;
          return Number(t.getAttribute('data-fk-seg-allowed') || '1') !== 0;
        }
      } catch (e) { }
      return false; // If tab not in DOM, it's not allowed
    }
    function setActiveTab(seg) {
      try {
        Array.prototype.slice.call(tabs || []).forEach(function (t) {
          if (!t) return;
          var s = String(t.getAttribute('data-fk-micro-segment') || '');
          var allowed = Number(t.getAttribute('data-fk-seg-allowed') || '1') !== 0;
          t.classList.toggle('is-disabled', !allowed);
          t.setAttribute('aria-selected', s === seg ? 'true' : 'false');
        });
      } catch (e) { }
    }

    var selTyp = $('#fkMicroTyp', root);
    var selHer = $('#fkMicroHersteller', root);
    var selBasis = $('#fkMicroBasis', root);
    var selMod = $('#fkMicroModell', root);
    var selYear = $('#fkMicroBaujahr', root);
    var selSitz = $('#fkMicroSitz', root);
    var selSerie = $('#fkMicroSerie', root);
    var gridSerie = $('#fkMicroSerieGrid', root);
    var statusDiv = $('#fkMicroStatus', root);

    if (isPkw) state.segment = 'pkw';

    var seriesCache = [];

    function optionsParams(extra) {
      var p = extra || {};
      if (isPkw) p.segment = 'pkw';
      if (state.typ) p.typ = state.typ;
      if (state.hersteller) p.hersteller = state.hersteller;
      if (state.basis) p.basis = state.basis;
      if (state.modell) p.modell = state.modell;
      if (state.baujahr) p.baujahr = state.baujahr;
      if (state.sitz) p.sitz = state.sitz;
      if (state.serie) p.serie = state.serie;
      return p;
    }

    function loadTyp() {
      // WM typ depends on segment only; PKW typ not used
      if (isPkw) {
        fillSelect(selTyp, ['PKW'], 'PKW');
        selTyp.disabled = true;
        state.typ = '';
        return Promise.resolve();
      }
      selTyp.disabled = false;
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'typ' }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selTyp, vals, state.typ || '');
          state.typ = String(selTyp.value || '');
        })
        .catch(function () { fillSelect(selTyp, [], ''); });
    }

    function loadHersteller() {
      if (!isPkw && !state.typ) {
        fillSelect(selHer, [], '');
        return Promise.resolve();
      }
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'hersteller', typ: isPkw ? '' : state.typ }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selHer, vals, state.hersteller || '');
          state.hersteller = String(selHer.value || '');
        })
        .catch(function () { fillSelect(selHer, [], ''); });
    }

    function loadBasis() {
      if (isPkw) {
        fillSelect(selBasis, [], '');
        return Promise.resolve();
      }
      if (!state.typ || !state.hersteller) {
        fillSelect(selBasis, [], '');
        return Promise.resolve();
      }
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'basis' }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selBasis, vals, state.basis || '');
          state.basis = String(selBasis.value || '');
        })
        .catch(function () { fillSelect(selBasis, [], ''); });
    }

    function loadModell() {
      if (isPkw) {
        if (!state.hersteller) {
          fillSelect(selMod, [], '');
          return Promise.resolve();
        }
        return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'modell', hersteller: state.hersteller }))
          .then(function (data) {
            var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
            fillSelect(selMod, vals, state.modell || '');
            state.modell = String(selMod.value || '');
          })
          .catch(function () { fillSelect(selMod, [], ''); });
      }
      if (!state.typ || !state.hersteller || !state.basis) {
        fillSelect(selMod, [], '');
        return Promise.resolve();
      }
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'modell' }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selMod, vals, state.modell || '');
          state.modell = String(selMod.value || '');
        })
        .catch(function () { fillSelect(selMod, [], ''); });
    }

    function loadBaujahr() {
      if (!state.hersteller || !state.modell) {
        fillSelect(selYear, [], '');
        return Promise.resolve();
      }
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'baujahr', hersteller: state.hersteller, modell: state.modell }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selYear, vals, state.baujahr || '');
          state.baujahr = String(selYear.value || '');
        })
        .catch(function () { fillSelect(selYear, [], ''); });
    }

    function loadSitz() {
      if (isPkw) {
        fillSelect(selSitz, [], '');
        return Promise.resolve();
      }
      if (!state.typ || !state.hersteller || !state.basis || !state.modell) {
        fillSelect(selSitz, [], '');
        return Promise.resolve();
      }
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'sitz' }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selSitz, vals, state.sitz || '');
          state.sitz = String(selSitz.value || '');
        })
        .catch(function () { fillSelect(selSitz, [], ''); });
    }

    function canLoadSeries() {
      if (isPkw) {
        return !!(state.hersteller && state.modell && state.baujahr);
      }
      return !!(state.typ && state.hersteller && state.basis && state.modell && state.sitz);
    }

    function loadSeries() {
      selSerie.disabled = true;
      fillSelect(selSerie, [], '');
      if (gridSerie) gridSerie.innerHTML = '';
      seriesCache = [];
      if (!canLoadSeries()) return Promise.resolve();
      var p = optionsParams({});
      return fetchJson('/fk-search', p)
        .then(function (items) {
          var arr = Array.isArray(items) ? items : [];
          seriesCache = arr;
          var serieVals = uniqSorted(arr.map(function (it) { return it && it.serie ? it.serie : ''; }))
            .filter(function (v) { return !isSerieHidden(v); });

          // Default: no auto-selection. Exception: explicit preset from MarkeFiltre.
          var allowPreset = String(state.forceSerieSelect || '') === '1' && String(state.mfPreset || '') === 'brand_click';
          var keepSerie = (allowPreset && state.serie) ? state.serie : '';
          fillSelect(selSerie, serieVals, keepSerie);
          renderSeriesGrid(serieVals);
          updateStatus(arr.length);

          selSerie.disabled = false;
          state.serie = String(selSerie.value || '');
        })
        .catch(function () {
          seriesCache = [];
          selSerie.disabled = true;
          fillSelect(selSerie, [], '');
          if (gridSerie) gridSerie.innerHTML = '';
          updateStatus(0);
        });
    }

    function updateStatus(count) {
      if (!statusDiv) return;
      if (count > 0) {
        statusDiv.innerHTML = '<span class="fk-micro-status text-success"><span class="fk-micro-status-icon" aria-hidden="true">✔</span> Passend (' + count + ')</span>';
      } else {
        statusDiv.innerHTML = '';
      }
    }

    function renderSeriesGrid(serieVals) {
      if (!gridSerie) return;
      gridSerie.innerHTML = '';
      (serieVals || []).forEach(function (val) {
        var meta = findSeriesMeta(val) || { label: val, img: '' };
        var tile = document.createElement('div');
        tile.className = 'fk-micro-serie-tile';
        tile.setAttribute('data-serie', val);
        tile.title = meta.label;
        if (meta.color) {
          tile.style.backgroundColor = meta.color;
        }

        if (meta.img) {
          var img = document.createElement('img');
          img.src = meta.img;
          img.alt = meta.label;
          tile.appendChild(img);
        }

        var name = document.createElement('span');
        name.className = 'fk-micro-serie-name';
        name.textContent = meta.label;
        tile.appendChild(name);

        tile.addEventListener('click', function () {
          selSerie.value = val;
          state.serie = val;
          navigateToSelectedSerie();
        });

        gridSerie.appendChild(tile);
      });
    }

    function navigateToSelectedSerie() {
      var code = String(selSerie && selSerie.value || '').trim();
      if (!code) return;
      var url = '';
      for (var i = 0; i < seriesCache.length; i++) {
        var it = seriesCache[i];
        if (!it) continue;
        if (String(it.serie || '').trim() === code && it.url) {
          url = String(it.url);
          break;
        }
      }
      if (url) {
        window.location.assign(url);
      }
    }

    function resetDownstream(fromKey) {
      // clear keys + selects after a change
      var order = ['typ', 'hersteller', 'basis', 'modell', 'baujahr', 'sitz', 'serie'];
      var idx = order.indexOf(fromKey);
      if (idx === -1) return;

      var selectMap = {
        'typ': selTyp,
        'hersteller': selHer,
        'basis': selBasis,
        'modell': selMod,
        'baujahr': selYear,
        'sitz': selSitz,
        'serie': selSerie
      };

      for (var i = idx + 1; i < order.length; i++) {
        var k = order[i];
        state[k] = '';
        var s = selectMap[k];
        if (s) {
          if (k === 'serie') {
            s.disabled = true;
          }
          fillSelect(s, [], '');
        }
      }

      if (idx < order.length - 1) {
        seriesCache = [];
        updateStatus(0);
      }
    }

    function persistSegment(seg) {
      try {
        var raw = window.localStorage.getItem(FK_STATE_KEY);
        var s = raw ? JSON.parse(raw) : {};
        if (!s || typeof s !== 'object') s = {};
        s.segment = seg;
        window.localStorage.setItem(FK_STATE_KEY, JSON.stringify(s));
      } catch (e) { }
    }

    function switchSegment(toPkw) {
      isPkw = !!toPkw;
      state.segment = isPkw ? 'pkw' : 'wohnmobil';
      persistSegment(state.segment);
      root.classList.toggle('fk-micro-is-pkw', isPkw);
      setActiveTab(isPkw ? 'pkw' : 'wohnmobil');
      // Clear fields that don't exist in the other segment
      if (isPkw) {
        state.typ = '';
        state.basis = '';
        state.sitz = '';
        if (selTyp) { selTyp.disabled = true; fillSelect(selTyp, [], ''); }
        if (selBasis) fillSelect(selBasis, [], '');
        if (selSitz) fillSelect(selSitz, [], '');
        resetDownstream('hersteller');
        loadHersteller().then(function () { return loadModell(); }).then(function () { return loadBaujahr(); }).then(function () { return loadSeries(); });
      } else {
        resetDownstream('typ');
        loadTyp().then(function () { return loadHersteller(); }).then(function () { return loadBasis(); }).then(function () { return loadModell(); }).then(function () { return loadBaujahr(); }).then(function () { return loadSitz(); }).then(function () { return loadSeries(); });
      }
    }

    // bind tab events
    Array.prototype.slice.call(tabs || []).forEach(function (t) {
      if (!t) return;
      t.addEventListener('click', function () {
        var seg = String(t.getAttribute('data-fk-micro-segment') || '');
        if (!tabAllowed(seg)) return;
        switchSegment(seg === 'pkw');
      });
    });

    // events
    if (selTyp) selTyp.addEventListener('change', function () {
      state.typ = String(selTyp.value || '');
      resetDownstream('typ');
      loadHersteller().then(function () { return loadBasis(); }).then(function () { return loadModell(); }).then(function () { return loadSitz(); }).then(function () { return loadSeries(); });
    });
    if (selHer) selHer.addEventListener('change', function () {
      state.hersteller = String(selHer.value || '');
      resetDownstream('hersteller');
      loadBasis().then(function () { return loadModell(); }).then(function () { return loadBaujahr(); }).then(function () { return loadSitz(); }).then(function () { return loadSeries(); });
    });
    if (selBasis) selBasis.addEventListener('change', function () {
      state.basis = String(selBasis.value || '');
      resetDownstream('basis');
      loadModell().then(function () { return loadSitz(); }).then(function () { return loadSeries(); });
    });
    if (selMod) selMod.addEventListener('change', function () {
      state.modell = String(selMod.value || '');
      resetDownstream('modell');
      loadBaujahr().then(function () { return loadSitz(); }).then(function () { return loadSeries(); });
    });
    if (selYear) selYear.addEventListener('change', function () {
      state.baujahr = String(selYear.value || '');
      resetDownstream('baujahr');
      loadSeries();
    });
    if (selSitz) selSitz.addEventListener('change', function () {
      state.sitz = String(selSitz.value || '');
      resetDownstream('sitz');
      loadSeries();
    });
    if (selSerie) selSerie.addEventListener('change', function () {
      state.serie = String(selSerie.value || '');
      navigateToSelectedSerie();
    });

    // initial hydrate sequence
    // clamp to allowed segments, then mark tab UI
    if (isPkw && !tabAllowed('pkw') && tabAllowed('wohnmobil')) {
      isPkw = false;
    }
    if (!isPkw && !tabAllowed('wohnmobil') && tabAllowed('pkw')) {
      isPkw = true;
    }
    root.classList.toggle('fk-micro-is-pkw', !!isPkw);
    setActiveTab(isPkw ? 'pkw' : 'wohnmobil');

    ensureSeriesConfigLoaded()
      .then(function () { return loadTyp(); })
      .then(function () { return loadHersteller(); })
      .then(function () { return loadBasis(); })
      .then(function () { return loadModell(); })
      .then(function () { return loadBaujahr(); })
      .then(function () { return loadSitz(); })
      .then(function () { return loadSeries(); })
      .then(function () {
        // ensure current selection reflected
        if (state.typ && selTyp) selTyp.value = state.typ;
        if (state.hersteller && selHer) selHer.value = state.hersteller;
        if (state.basis && selBasis) selBasis.value = state.basis;
        if (state.modell && selMod) selMod.value = state.modell;
        if (state.baujahr && selYear) selYear.value = state.baujahr;
        if (state.sitz && selSitz) selSitz.value = state.sitz;
        if (state.serie && selSerie) {
          // User request: Do not auto-select the series on initial load
          // selSerie.value = state.serie; 
        }
      });
  }

  function initAll() {
    var roots = document.querySelectorAll(ROOT_SEL);
    if (!roots || !roots.length) return;
    Array.prototype.slice.call(roots).forEach(function (r) {
      try { initOne(r); } catch (e) { }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();

