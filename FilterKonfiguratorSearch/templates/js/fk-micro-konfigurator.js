/**
 * Dynamic micro konfigurator for product list page (under .productlist-page-nav-header-m).
 * Uses /fk-search?mode=options to populate dropdowns and /fk-search (series list) for Serie.
 */
(function () {
  'use strict';

  var ROOT_SEL = '.fk-selection-micro[data-fk-micro-dynamic="1"]';
  var FK_STATE_KEY = 'womosi_fk_state_v1';
  var FK_QS_KEYS = ['segment', 'typ', 'hersteller', 'basis', 'modell', 'baujahr', 'sitz', 'serie', 'fkCustomModell', 'fkNichtInListe'];
  var SERIES_LOADING_MESSAGES = [
    'Wir suchen die passenden Sitzbezüge für Ihr Fahrzeug...',
    'Die Liste wird zusammengestellt...',
    'Fast fertig, die besten Optionen werden vorbereitet...',
    'Gleich geht\'s weiter, wir optimieren die Auswahl...'
  ];
  var AI_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></svg>';
  var SERIES_META = {
    ZM: { label: 'Serie ZM', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/zm-serie.avif' },
    1: { label: 'Serie 01', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/serie-01.avif' },
    FT: { label: 'Serie FT', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/ft-serie.avif' },
    FNL: { label: 'Serie FNL', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/fnl-serie.avif' },
    V: { label: 'Serie V', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/v-serie.avif' },
    TK600: { label: 'Serie TK600', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/tk600-serie.avif' },
    T: { label: 'Serie T', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/t-serie.avif' },
    TF: { label: 'Serie TF', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/tf-serie.avif' },
    SL: { label: 'Serie SL', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/sl-serie.avif' },
    PS: { label: 'Serie PS', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/ps-serie.avif' },
    PL: { label: 'Serie PL', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/pl-serie.avif' },
    NL: { label: 'Serie NL', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/nl-serie.avif' },
    N3: { label: 'Serie N3', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/n3-serie.avif' },
    MD: { label: 'Serie MD', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/md-serie.avif' },
    M: { label: 'Serie M', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/m-serie.avif' },
    KT300: { label: 'Serie KT3', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/kt300-serie.avif' },
    KT: { label: 'Serie KT', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/kt-serie.avif' },
    S1: { label: 'Serie 01', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/serie-01.avif' },
    AH: { label: 'Serie AH', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/ah-serie.avif' },
    GT: { label: 'Serie GT', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/gt-serie.avif' },
    C: { label: 'Serie C', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/c-serie.avif' },
    DPL: { label: 'Serie DPL', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/dpl-serie.avif' },
    H: { label: 'Serie H', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/h-serie.avif' },
    PLKT: { label: 'Serie PLKT', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/plkt-serie.avif' },
    WPL: { label: 'Serie WPL', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/wpl-serie.avif' },
    WMS: { label: 'Serie WMS', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/wms-serie.avif' },
    HW: { label: 'Serie HW', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/hw-serie.avif' },
    BC: { label: 'Serie BC', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/bc-serie.avif' },
    DS: { label: 'Serie DS', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/ds-serie.avif' },
    LT: { label: 'Serie LT', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/lt-serie.avif' },
    800: { label: 'Serie 800er', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/800er-serie.avif' },
    WMA: { label: 'Aguti Sitze', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/aguti-sitze-serie.avif' },
    WDS: { label: 'Serie WDS', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/wds-serie.webp' },
    SMD: { label: 'Serie SMD', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/smd-serie.webp' },
    NP: { label: 'Serie NP', color: '#f5f5f5', img: 'https://bremer-sitzbezuege.de/mediafiles/seat/np-serie.webp' }
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
    if (!c) return null;
    var candidates = [];
    function add(v) {
      v = String(v || '').trim();
      if (v && candidates.indexOf(v) === -1) candidates.push(v);
    }
    add(c);
    add(c.replace(/^Serie\s+/i, ''));
    candidates.slice().forEach(function (v) {
      add(v.replace(/[\s_-]*serie$/i, ''));
    });
    candidates.slice().forEach(function (v) {
      add(v.replace(/er$/i, ''));
    });
    for (var i = 0; i < candidates.length; i++) {
      var cand = candidates[i];
      if (SERIES_META[cand]) return SERIES_META[cand];
      var upper = cand.toUpperCase();
      for (var k in SERIES_META) {
        if (k.toUpperCase() === upper) return SERIES_META[k];
      }
    }
    var cUpper = c.toUpperCase();
    for (var k2 in SERIES_META) {
      var label = SERIES_META[k2].label ? String(SERIES_META[k2].label).toUpperCase() : '';
      if (label && (label === cUpper || candidates.some(function (v) { return label === ('SERIE ' + v).toUpperCase(); }))) {
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
      // URL-Segment ist verbindlich (hat Vorrang vor evtl. veraltetem localStorage)
      if (seg === 'pkw') return true;
      if (seg === 'wohnmobil' || seg === 'wm') return false;
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

  var SEAT_NAMES = {
    '1': 'Aguti Milan 12',
    '2': 'Aguti Liner 12',
    '3': 'ISRI Captain Chair',
    '4': 'ISRI CC mit Gurt',
    '5': 'ISRI Ducato / Jumper / Boxer, Bj. 2006–2014',
    '6': 'Aguti Milan',
    '7': 'Aguti GIS-Liner',
    '8': 'Aguti GIS-Liner 3',
    '9': 'Ford Transit ab Bj. 2014',
    '10': 'Ford Transit ab Bj. 2014 mit integrierter Kopfstütze',
    '11': 'Aguti Milan 3',
    '12': 'SKA 1800 M23 Komfort',
    '13': 'SKA 1800/1000 M23 Classic',
    '14': 'Mercedes-Benz Sprinter / Hymer'
  };

  var SEAT_ORDER = ['3', '5', '4', '7', '6', '2', '1', '9', '10', '14', '8', '11', '12', '13'];

  function optionLabel(sel, value) {
    var v = String(value);
    if (sel && sel.id === 'fkMicroSitz') {
      var m = v.match(/^\s*(?:Sitz\s*)?0*(\d{1,2})\s*$/i);
      if (m && SEAT_NAMES[m[1]]) return SEAT_NAMES[m[1]];
    }
    return v;
  }

  function setFieldLoading(sel, on) {
    var field = sel && sel.closest('.fk-micro-field');
    if (field) field.classList.toggle('is-loading', !!on);
    if (sel) sel.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  function fillSelect(sel, values, keep) {
    if (!sel) return;
    setFieldLoading(sel, false);
    var k = keep != null ? String(keep) : '';
    sel.innerHTML = '';
    var emptyOpt = document.createElement('option');
    emptyOpt.value = '';
    emptyOpt.textContent = 'Bitte wählen';
    sel.appendChild(emptyOpt);
    (values || []).forEach(function (v) {
      var opt = document.createElement('option');
      opt.value = String(v);
      opt.textContent = optionLabel(sel, v);
      sel.appendChild(opt);
    });
    if (k) {
      var hasKeep = false;
      for (var oi = 0; oi < sel.options.length; oi++) {
        if (sel.options[oi].value === k) { hasKeep = true; break; }
      }
      if (!hasKeep) {
        var keepOpt = document.createElement('option');
        keepOpt.value = k;
        keepOpt.textContent = optionLabel(sel, k);
        sel.appendChild(keepOpt);
      }
      sel.value = k;
    }
    if (sel.id !== 'fkMicroSerie' && sel.id !== 'fkMicroTyp') {
      sel.disabled = sel.options.length <= 1;
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
    if (!isPkw && !state.segment && (state.typ || state.basis || state.sitz)) {
      state.segment = 'wohnmobil';
    }
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
    var filterAfterNextSeriesLoad = false;
    var updateSerieNav = setupSerieNav();

    function setupSerieNav() {
      if (!gridSerie || !gridSerie.parentNode) return function () { };
      var wrap = document.createElement('div');
      wrap.className = 'fk-micro-serie-scroller';
      gridSerie.parentNode.insertBefore(wrap, gridSerie);
      wrap.appendChild(gridSerie);

      function makeBtn(dir) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'fk-micro-serie-nav fk-micro-serie-nav--' + (dir < 0 ? 'prev' : 'next');
        b.setAttribute('aria-label', dir < 0 ? 'Vorherige Serien' : 'Weitere Serien');
        b.innerHTML = dir < 0 ? '&#8249;' : '&#8250;';
        b.addEventListener('click', function () {
          gridSerie.scrollBy({ left: dir * Math.max(200, gridSerie.clientWidth * 0.8), behavior: 'smooth' });
        });
        wrap.appendChild(b);
        return b;
      }
      var prev = makeBtn(-1);
      var next = makeBtn(1);

      function update() {
        var max = gridSerie.scrollWidth - gridSerie.clientWidth;
        var hasTiles = !!gridSerie.querySelector('.fk-micro-serie-tile');
        prev.hidden = !hasTiles || gridSerie.scrollLeft <= 4;
        next.hidden = !hasTiles || gridSerie.scrollLeft >= max - 4;
      }
      gridSerie.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update);
      if (window.MutationObserver) {
        new MutationObserver(function () { window.requestAnimationFrame(update); })
          .observe(gridSerie, { childList: true });
      }
      update();
      return update;
    }

    if (isPkw) state.segment = 'pkw';

    var seriesCache = [];
    var seriesLoadingInterval = null;

    function hasCompleteWmSelection() {
      return !!(state.sitz && state.serie && state.hersteller && state.basis && state.modell);
    }

    function updateSelectionMarks() {
      [
        [selHer, state.hersteller],
        [selBasis, state.basis],
        [selMod, state.modell],
        [selSitz, state.sitz]
      ].forEach(function (entry) {
        if (entry[0]) {
          entry[0].required = true;
          entry[0].setAttribute('aria-required', 'true');
        }
        var field = entry[0] && entry[0].closest('.fk-micro-field');
        if (field) field.classList.toggle('is-selected', !!String(entry[1] || '').trim());
      });
      if (selSerie) {
        selSerie.required = true;
        selSerie.setAttribute('aria-required', 'true');
      }
      var serieField = selSerie && selSerie.closest('.fk-micro-serie-field');
      if (serieField) serieField.classList.toggle('is-selected', !!String(state.serie || '').trim());
      var yearField = selYear && selYear.closest('.fk-micro-field');
      if (yearField) yearField.classList.toggle('is-selected', isPkw && !!String(state.baujahr || '').trim());
      numberSteps();
    }

    function numberSteps() {
      var all = [selSitz, selSerie, selTyp, selHer, selBasis, selMod, selYear];
      var steps = isPkw ? [selHer, selMod, selYear, selSerie] : [selSitz, selSerie, selHer, selBasis, selMod];
      all.forEach(function (sel) {
        var field = sel && sel.closest('.fk-micro-field, .fk-micro-serie-field');
        var label = field && field.querySelector(':scope > label');
        if (!label) return;
        var idx = steps.indexOf(sel);
        if (idx >= 0) label.setAttribute('data-step', String(idx + 1));
        else label.removeAttribute('data-step');
      });
    }

    function requiredValues() {
      return isPkw
        ? [state.hersteller, state.modell, state.baujahr, state.serie]
        : [state.sitz, state.serie, state.hersteller, state.basis, state.modell];
    }

    function showRequiredProgress() {
      if (!statusDiv || statusDiv.querySelector('.is-loading')) return;
      var values = requiredValues();
      var selected = values.filter(function (value) { return !!String(value || '').trim(); }).length;
      statusDiv.innerHTML = '<span class="fk-micro-required-status">'
        + '<span class="fk-micro-progress" aria-hidden="true"><i style="width:' + Math.round(selected / values.length * 100) + '%"></i></span>'
        + selected + ' von ' + values.length + ' ausgewählt'
        + '</span>';
    }

    // Pro Segment getroffene Auswahl merken, damit Tab-Wechsel die Eingaben nicht verliert.
    var segMemory = {
      wohnmobil: { typ: '', hersteller: '', basis: '', modell: '', baujahr: '', sitz: '', serie: '' },
      pkw: { typ: '', hersteller: '', basis: '', modell: '', baujahr: '', sitz: '', serie: '' }
    };

    function snapshotSegmentMemory() {
      var key = isPkw ? 'pkw' : 'wohnmobil';
      segMemory[key] = {
        typ: state.typ || '',
        hersteller: state.hersteller || '',
        basis: state.basis || '',
        modell: state.modell || '',
        baujahr: state.baujahr || '',
        sitz: state.sitz || '',
        serie: state.serie || ''
      };
    }

    function applySegmentMemory() {
      var key = isPkw ? 'pkw' : 'wohnmobil';
      var m = segMemory[key] || {};
      state.typ = m.typ || '';
      state.hersteller = m.hersteller || '';
      state.basis = m.basis || '';
      state.modell = m.modell || '';
      state.baujahr = m.baujahr || '';
      state.sitz = m.sitz || '';
      state.serie = m.serie || '';
    }

    function clearSeriesLoading() {
      if (seriesLoadingInterval) {
        clearInterval(seriesLoadingInterval);
        seriesLoadingInterval = null;
      }
      if (statusDiv && statusDiv.querySelector('.fk-micro-status-msg.is-loading')) {
        statusDiv.innerHTML = '';
      }
      if (gridSerie && gridSerie.querySelector('.fk-micro-grid-loading')) {
        gridSerie.innerHTML = '';
      }
    }

    function seriesLoadingHtml() {
      return '<div class="fk-micro-status-msg is-loading fk-micro-grid-loading">'
        + '<span class="fk-micro-ai-icon">' + AI_ICON_SVG + '</span>'
        + '<span class="fk-micro-text-dynamic fk-micro-loading-text">' + SERIES_LOADING_MESSAGES[0] + '</span>'
        + '</div>';
    }

    function showSeriesLoading() {
      if (!statusDiv && !gridSerie) return;
      var html = seriesLoadingHtml();
      if (statusDiv) statusDiv.innerHTML = html;
      if (gridSerie) gridSerie.innerHTML = html;
      var msgIdx = 0;
      if (seriesLoadingInterval) clearInterval(seriesLoadingInterval);
      seriesLoadingInterval = setInterval(function () {
        msgIdx = (msgIdx + 1) % SERIES_LOADING_MESSAGES.length;
        var nodes = root.querySelectorAll('.fk-micro-loading-text');
        for (var i = 0; i < nodes.length; i++) {
          nodes[i].textContent = SERIES_LOADING_MESSAGES[msgIdx];
        }
      }, 3000);
    }

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
      fillSelect(selTyp, [], '');
      state.typ = '';
      return Promise.resolve();
    }

    function wmVehicleParams(field) {
      var p = { mode: 'options', field: field, sitz: state.sitz, serie: state.serie };
      if (field !== 'hersteller' && state.hersteller) p.hersteller = state.hersteller;
      if (field === 'modell' && state.basis) p.basis = state.basis;
      return p;
    }

    function keepIfListed(vals, value) {
      var v = String(value || '');
      return v && vals.indexOf(v) !== -1 ? v : '';
    }

    function loadHersteller() {
      if (!isPkw) {
        if (!state.sitz || !state.serie) {
          fillSelect(selHer, [], '');
          return Promise.resolve();
        }
        setFieldLoading(selHer, true);
        return fetchJson('/fk-search', wmVehicleParams('hersteller'))
          .then(function (data) {
            var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
            fillSelect(selHer, vals, keepIfListed(vals, state.hersteller));
            state.hersteller = String(selHer.value || '');
          })
          .catch(function () { fillSelect(selHer, [], ''); });
      }
      setFieldLoading(selHer, true);
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'hersteller', typ: '' }))
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
      if (!state.sitz || !state.serie || !state.hersteller) {
        fillSelect(selBasis, [], '');
        return Promise.resolve();
      }
      setFieldLoading(selBasis, true);
      return fetchJson('/fk-search', wmVehicleParams('basis'))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selBasis, vals, keepIfListed(vals, state.basis));
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
        setFieldLoading(selMod, true);
        return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'modell', hersteller: state.hersteller }))
          .then(function (data) {
            var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
            fillSelect(selMod, vals, state.modell || '');
            state.modell = String(selMod.value || '');
          })
          .catch(function () { fillSelect(selMod, [], ''); });
      }
      if (!state.sitz || !state.serie || !state.hersteller || !state.basis) {
        fillSelect(selMod, [], '');
        return Promise.resolve();
      }
      setFieldLoading(selMod, true);
      return fetchJson('/fk-search', wmVehicleParams('modell'))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selMod, vals, keepIfListed(vals, state.modell));
          state.modell = String(selMod.value || '');
        })
        .catch(function () { fillSelect(selMod, [], ''); });
    }

    function resetPkwBaujahrSelect() {
      if (!selYear) return;
      state.baujahr = '';
      fillSelect(selYear, [], '');
    }

    /** PKW: wie Anasayfa validateStep2 — Baujahr Pflicht vor Serien */
    function validatePkwSelection() {
      if (!state.hersteller) return { ok: false };
      if (!state.modell) return { ok: false };
      if (!String(state.baujahr || '').trim()) return { ok: false };
      return { ok: true };
    }

    function loadBaujahr(keepValue) {
      if (!state.hersteller || !state.modell) {
        if (isPkw) {
          resetPkwBaujahrSelect();
        } else {
          fillSelect(selYear, [], '');
        }
        return Promise.resolve();
      }
      var keep = keepValue === true ? String(state.baujahr || '') : '';
      setFieldLoading(selYear, true);
      return fetchJson('/fk-search', optionsParams({ mode: 'options', field: 'baujahr', hersteller: state.hersteller, modell: state.modell }))
        .then(function (data) {
          var vals = uniqSorted((Array.isArray(data) ? data : []).map(function (r) { return r && r.value ? r.value : ''; }));
          fillSelect(selYear, vals, keep);
          state.baujahr = keep ? String(selYear.value || '') : '';
        })
        .catch(function () {
          if (isPkw) {
            resetPkwBaujahrSelect();
          } else {
            fillSelect(selYear, [], '');
          }
        });
    }

    function wmVehicleChain() {
      return loadHersteller()
        .then(function () { return loadBasis(); })
        .then(function () { return loadModell(); })
        .then(function () { updateStatus(seriesCache.length); });
    }

    function wmFullChain() {
      return loadSitz()
        .then(function () { return loadSeries(); })
        .then(wmVehicleChain);
    }

    function afterHerstellerChange() {
      if (isPkw) {
        clearSeriesLoading();
        return loadModell().then(function () {
          resetPkwBaujahrSelect();
          return loadBaujahr(false);
        }).then(function () { updateStatus(0); });
      }
      return loadBasis().then(function () { return loadModell(); });
    }

    function afterModellChange() {
      if (isPkw) {
        clearSeriesLoading();
        resetPkwBaujahrSelect();
        return loadBaujahr(false).then(function () { updateStatus(0); });
      }
      updateStatus(seriesCache.length);
      navigateToSelectedSerie();
      return Promise.resolve();
    }

    function maybeLoadSeriesAfterPkwBaujahr() {
      if (!isPkw) {
        return loadSeries();
      }
      if (validatePkwSelection().ok) {
        return loadSeries();
      }
      return Promise.resolve();
    }

    function loadSitz() {
      if (isPkw) {
        fillSelect(selSitz, [], '');
        return Promise.resolve();
      }
      fillSelect(selSitz, SEAT_ORDER, state.sitz || '');
      state.sitz = String(selSitz.value || '');
      return Promise.resolve();
    }

    function canLoadSeries() {
      if (isPkw) {
        return !!(state.hersteller && state.modell && state.baujahr);
      }
      return !!state.sitz;
    }

    function loadSeries() {
      if (!canLoadSeries()) {
        clearSeriesLoading();
        if (!isPkw && state.serie) {
          fillSelect(selSerie, [state.serie], state.serie);
          selSerie.disabled = false;
          renderSeriesGrid([state.serie]);
        } else if (gridSerie) {
          gridSerie.innerHTML = '<div class="fk-micro-serie-empty">'
            + (isPkw ? 'Bitte zuerst Marke, Modell und Baujahr wählen.' : 'Bitte zuerst einen Sitz wählen.')
            + '</div>';
        }
        updateStatus(0);
        return Promise.resolve();
      }
      selSerie.disabled = true;
      fillSelect(selSerie, [], '');
      if (gridSerie) gridSerie.innerHTML = '';
      seriesCache = [];
      showSeriesLoading();
      var p = isPkw ? optionsParams({}) : { sitz: state.sitz };
      return fetchJson('/fk-search', p)
        .then(function (items) {
          clearSeriesLoading();
          var arr = Array.isArray(items) ? items : [];
          seriesCache = arr;
          var serieVals = uniqSorted(arr.map(function (it) { return it && it.serie ? it.serie : ''; }))
            .filter(function (v) { return !isSerieHidden(v); });

          var incomingSerie = String(state.serie || '');
          var serieInList = incomingSerie && serieVals.some(function (item) { return item === incomingSerie; });
          var allowPreset = String(state.forceSerieSelect || '') === '1' && String(state.mfPreset || '') === 'brand_click';
          var keepSerie = serieInList ? incomingSerie : ((allowPreset && incomingSerie) ? incomingSerie : '');
          if (keepSerie && !serieInList) {
            serieVals.unshift(keepSerie);
          }
          fillSelect(selSerie, serieVals, keepSerie);
          renderSeriesGrid(serieVals);
          if (!serieVals.length && gridSerie) {
            gridSerie.innerHTML = '<div class="fk-micro-serie-empty">Für diese Auswahl sind keine Serien verfügbar.</div>';
          }

          selSerie.disabled = false;
          var selectedSerie = String(selSerie.value || '');
          if (selectedSerie || !keepSerie) {
            state.serie = selectedSerie;
          }
          updateSelectionMarks();
          updateStatus(arr.length);
          if (filterAfterNextSeriesLoad && hasCompleteWmSelection()) {
            filterAfterNextSeriesLoad = false;
            navigateToSelectedSerie();
          }
        })
        .catch(function () {
          clearSeriesLoading();
          seriesCache = [];
          selSerie.disabled = true;
          fillSelect(selSerie, [], '');
          if (gridSerie) gridSerie.innerHTML = '';
          updateStatus(0);
        })
        .finally(function () {
          clearSeriesLoading();
        });
    }

    function updateStatus(count) {
      if (!statusDiv) return;
      updateSelectionMarks();
      if (isPkw ? !canLoadSeries() : !hasCompleteWmSelection()) {
        showRequiredProgress();
      } else if (count > 0) {
        statusDiv.innerHTML = '<span class="fk-micro-status text-success"><span class="fk-micro-status-icon" aria-hidden="true">✔</span> '
          + count + (count === 1 ? ' passende Serie' : ' passende Serien') + '</span>';
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
        tile.className = 'fk-micro-serie-tile' + (String(state.serie || '') === String(val) ? ' is-active' : '');
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

        tile.setAttribute('role', 'button');
        tile.setAttribute('tabindex', '0');
        tile.setAttribute('aria-pressed', String(state.serie || '') === String(val) ? 'true' : 'false');

        function choose() {
          selSerie.value = val;
          state.serie = val;
          if (isPkw) {
            updateSelectionMarks();
            navigateToSelectedSerie();
            return;
          }
          selectWmSerie();
        }
        tile.addEventListener('click', choose);
        tile.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            choose();
          }
        });

        gridSerie.appendChild(tile);
      });
      scrollActiveTileIntoView();
    }

    function scrollActiveTileIntoView() {
      if (!gridSerie) return;
      var active = gridSerie.querySelector('.fk-micro-serie-tile.is-active');
      if (!active) return;
      window.requestAnimationFrame(function () {
        var left = active.offsetLeft - gridSerie.offsetLeft - 8;
        gridSerie.scrollLeft = Math.max(0, left);
        updateSerieNav();
      });
    }

    // Aktuelle Auswahl als Query-Parameter an die Ziel-URL hängen,
    // damit die Produktseite den Mikro-Konfigurator korrekt vorbefüllt
    // (Segment + Felder; URL hat Vorrang vor evtl. veraltetem localStorage).
    function appendSelectionToUrl(rawUrl) {
      try {
        var raw = String(rawUrl || '');
        if (!raw || raw === '#') return rawUrl;
        var target = new URL(raw, window.location.origin);
        function set(k, v) {
          v = (v == null) ? '' : String(v).trim();
          if (!v) return;
          target.searchParams.set(k, v);
        }

        set('segment', isPkw ? 'pkw' : 'wohnmobil');
        if (!isPkw) set('typ', state.typ);
        set('hersteller', state.hersteller);
        if (!isPkw) set('basis', state.basis);
        if (state.modell) {
          set('modell', state.modell);
          if (String(state.fkCustomModell || '') === '1') set('fkCustomModell', '1');
        }
        set('baujahr', state.baujahr);
        if (!isPkw) set('sitz', state.sitz);
        set('serie', state.serie);

        return target.toString();
      } catch (_) {
        return rawUrl;
      }
    }

    function findSerieUrl(items, code) {
      var list = Array.isArray(items) ? items : [];
      for (var i = 0; i < list.length; i++) {
        var it = list[i];
        if (it && String(it.serie || '').trim() === code && it.url) {
          return String(it.url);
        }
      }
      return '';
    }

    function selectWmSerie() {
      if (gridSerie) {
        var tiles = gridSerie.querySelectorAll('.fk-micro-serie-tile');
        for (var i = 0; i < tiles.length; i++) {
          var on = tiles[i].getAttribute('data-serie') === state.serie;
          tiles[i].classList.toggle('is-active', on);
          tiles[i].setAttribute('aria-pressed', on ? 'true' : 'false');
        }
      }
      updateSelectionMarks();
      showRequiredProgress();
      return wmVehicleChain().then(function () {
        if (hasCompleteWmSelection()) navigateToSelectedSerie();
      });
    }

    function navigateToSelectedSerie() {
      var code = String(selSerie && selSerie.value || '').trim();
      if (!code) return;
      if (!isPkw && !hasCompleteWmSelection()) {
        updateSelectionMarks();
        showRequiredProgress();
        return;
      }
      var url = findSerieUrl(seriesCache, code);
      if (!isPkw) {
        fetchJson('/fk-search', {
          sitz: state.sitz,
          hersteller: state.hersteller,
          basis: state.basis,
          modell: state.modell
        })
          .then(function (items) { return findSerieUrl(items, code) || url; })
          .catch(function () { return url; })
          .then(function (target) {
            if (target) {
              window.location.assign(appendSelectionToUrl(target));
            } else if (statusDiv) {
              statusDiv.innerHTML = '<span class="fk-micro-no-result">Keine passenden Produkte für diese Kombination gefunden.</span>';
            }
          });
        return;
      }
      if (url) {
        window.location.assign(appendSelectionToUrl(url));
      } else {
        filterAfterNextSeriesLoad = false;
        if (statusDiv) {
          statusDiv.innerHTML = '<span class="fk-micro-no-result">Keine passenden Produkte für diese Kombination gefunden.</span>';
        }
      }
    }

    function resetDownstream(fromKey) {
      // clear keys + selects after a change
      var order = isPkw
        ? ['typ', 'hersteller', 'basis', 'modell', 'baujahr', 'sitz', 'serie']
        : ['sitz', 'serie', 'hersteller', 'basis', 'modell'];
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

      if (idx < order.indexOf('serie')) {
        seriesCache = [];
        if (!isPkw && gridSerie) gridSerie.innerHTML = '';
        clearSeriesLoading();
        updateStatus(0);
      }
      updateSelectionMarks();
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
      var next = !!toPkw;
      if (next === isPkw) return; // gleicher Tab — nichts tun

      // Auswahl des verlassenen Segments sichern, dann Ziel-Segment wiederherstellen.
      snapshotSegmentMemory();
      isPkw = next;
      state.segment = isPkw ? 'pkw' : 'wohnmobil';
      persistSegment(state.segment);
      root.classList.toggle('fk-micro-is-pkw', isPkw);
      setActiveTab(isPkw ? 'pkw' : 'wohnmobil');
      applySegmentMemory();

      clearSeriesLoading();
      if (selSerie) { fillSelect(selSerie, [], ''); selSerie.disabled = true; }
      if (gridSerie) gridSerie.innerHTML = '';
      updateStatus(0);

      var hadBaujahr = !!String(state.baujahr || '').trim();
      if (isPkw) {
        if (selBasis) fillSelect(selBasis, [], '');
        if (selSitz) fillSelect(selSitz, [], '');
        loadTyp()
          .then(function () { return loadHersteller(); })
          .then(function () { return loadModell(); })
          .then(function () { return loadBaujahr(hadBaujahr); })
          .then(function () { return maybeLoadSeriesAfterPkwBaujahr(); });
      } else {
        loadTyp().then(wmFullChain);
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
    if (selHer) selHer.addEventListener('change', function () {
      state.hersteller = String(selHer.value || '');
      if (isPkw) filterAfterNextSeriesLoad = true;
      resetDownstream('hersteller');
      afterHerstellerChange();
    });
    if (selBasis) selBasis.addEventListener('change', function () {
      state.basis = String(selBasis.value || '');
      if (isPkw) filterAfterNextSeriesLoad = true;
      resetDownstream('basis');
      if (isPkw) {
        afterModellChange();
      } else {
        loadModell();
      }
    });
    if (selMod) selMod.addEventListener('change', function () {
      state.modell = String(selMod.value || '');
      if (isPkw) filterAfterNextSeriesLoad = true;
      resetDownstream('modell');
      afterModellChange();
    });
    if (selYear) selYear.addEventListener('change', function () {
      state.baujahr = String(selYear.value || '');
      resetDownstream('baujahr');
      if (isPkw) {
        maybeLoadSeriesAfterPkwBaujahr();
        return;
      }
      loadSeries();
    });
    if (selSitz) selSitz.addEventListener('change', function () {
      state.sitz = String(selSitz.value || '');
      resetDownstream('sitz');
      loadSeries();
    });
    if (selSerie) selSerie.addEventListener('change', function () {
      state.serie = String(selSerie.value || '');
      if (!isPkw) {
        selectWmSerie();
        return;
      }
      updateSelectionMarks();
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

    var hadBaujahrPreset = !!String(state.baujahr || '').trim();
    var initChain = ensureSeriesConfigLoaded().then(function () { return loadTyp(); });
    if (isPkw) {
      initChain = initChain
        .then(function () { return loadHersteller(); })
        .then(function () { return loadModell(); })
        .then(function () { return loadBaujahr(hadBaujahrPreset); })
        .then(function () { return maybeLoadSeriesAfterPkwBaujahr(); });
    } else {
      initChain = initChain.then(wmFullChain);
    }
    initChain.then(function () {
      // ensure current selection reflected
      if (state.hersteller && selHer) selHer.value = state.hersteller;
      if (state.basis && selBasis) selBasis.value = state.basis;
      if (state.modell && selMod) selMod.value = state.modell;
      if (state.baujahr && selYear) selYear.value = state.baujahr;
      if (state.sitz && selSitz) selSitz.value = state.sitz;
      if (state.serie && selSerie) selSerie.value = state.serie;
      updateSelectionMarks();
      updateStatus(seriesCache.length);
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

