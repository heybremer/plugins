(function () {
  'use strict';

  window.addEventListener('DOMContentLoaded', function () {

    // ==== Modüller ====
    var WomosiState = {};
    var WomosiApi = {};
    var WomosiUI = {};
    var serieLoadingInterval = null;
    var serieLoadingMessages = [
      "Wir suchen die passenden Sitzbezüge für Ihr Fahrzeug...",
      "Die Liste wird zusammengestellt...",
      "Fast fertig, die besten Optionen werden vorbereitet...",
      "Gleich geht's weiter, wir optimieren die Auswahl..."
    ];
    var AI_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></svg>';

    var FK_STATE_CACHE_KEY = 'womosi_fk_state_v1';

    WomosiState.deleteCookieByName = function (name) {
      try {
        // Try common path variants.
        document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax';
        document.cookie = name + '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; SameSite=Lax';
        document.cookie = name + '=; Max-Age=0; path=/WomosiWeb; SameSite=Lax';
        document.cookie = name + '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/WomosiWeb; SameSite=Lax';
      } catch (_) { }
    }

    WomosiState.clearGlobalFkState = function () {
      try { window.localStorage.removeItem(FK_STATE_CACHE_KEY); } catch (_) { }
      WomosiState.deleteCookieByName(FK_STATE_CACHE_KEY);
    }

    // Global: logo click her sayfada state'i temizlesin (container olmasa da).
    // Delegated listener kullaniyoruz ki header/logo sonradan render edilse bile yakalansin.
    try {
      document.addEventListener('click', function (e) {
        var t = e && e.target ? e.target : null;
        if (!t || !t.closest) return;
        var logoAnchor = t.closest('a.navbar-brand');
        if (!logoAnchor) return;
        var hasShopLogoImg = !!logoAnchor.querySelector('#shop-logo');
        if (!hasShopLogoImg) return;
        WomosiState.clearGlobalFkState();
      }, true);
    } catch (_) { }

    var container = document.querySelector('.womosi-konfigurator');
    if (!container) {
      console.warn('Womosi Konfigurator: Container nicht gefunden');
      return;
    }

    /** Select value for "Mein Modell ist nicht in der Liste" — use modell_custom for API text */
    var FK_MODELL_CUSTOM_VALUE = '__FK_MODELL_CUSTOM__';

    // Konfigüratör her zaman görünsün.
    // "Sayfa konfigüratöre gitmesin" isteği için scroll davranışını aşağıda engelliyoruz.
    container.style.display = '';

    var segRaw = (typeof window.WomosiFkSegments === 'object' && window.WomosiFkSegments)
      ? window.WomosiFkSegments
      : { wm: 1, pkw: 1 };
    var segAllowWm = Number(segRaw.wm) !== 0;
    var segAllowPkw = Number(segRaw.pkw) !== 0;
    if (!segAllowWm && !segAllowPkw) {
      segAllowWm = true;
    }

    // ==== Yardımcılar ====

    function fkBase() {
      if (typeof window !== 'undefined' && window.WomosiFkBase) {
        return String(window.WomosiFkBase).replace(/\/+$/, '');
      }
      return '';
    }

    var fkLoadingEl = container.querySelector('#fkLoading');
    var pendingFetches = 0;

    WomosiUI.setLoading = function (isLoading) {
      if (!fkLoadingEl) return;
      fkLoadingEl.classList.toggle('hidden', !isLoading);
      fkLoadingEl.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
    }

    WomosiApi.fetchJson = function (url, paramsObj) {
      var params = new URLSearchParams(paramsObj || {});
      var full = fkBase() + url + (params.toString() ? ('?' + params.toString()) : '');
      pendingFetches++;
      WomosiUI.setLoading(true);
      return fetch(full, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (res) {
        return res.json();
      }).finally(function () {
        pendingFetches = Math.max(0, pendingFetches - 1);
        if (pendingFetches === 0) {
          WomosiUI.setLoading(false);
        }
      });
    }

    function uniqSorted(arr) {
      return Array.from(new Set(arr)).filter(Boolean).sort(function (a, b) {
        return a.localeCompare(b, 'de');
      });
    }

    WomosiUI.fillSelect = function (sel, values) {
      if (!sel) return;
      sel.innerHTML = '';
      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = 'Bitte wählen';
      sel.appendChild(emptyOpt);
      (values || []).forEach(function (v) {
        var o = document.createElement('option');
        o.value = v;
        o.textContent = v;
        sel.appendChild(o);
      });
    }

    WomosiUI.appendModellNotInListOption = function (sel) {
      if (!sel) return;
      var o = document.createElement('option');
      o.value = FK_MODELL_CUSTOM_VALUE;
      o.textContent = 'Mein Modell ist nicht in der Liste';
      sel.appendChild(o);
    }

    WomosiUI.setClearBtnVisible = function (targetEl, isVisible) {
      if (!targetEl) return;
      var field = targetEl.closest ? targetEl.closest('.field') : null;
      if (!field) return;
      var btn = field.querySelector('.fk-clear-btn[data-clear-target]') ||
        field.querySelector('.fk-clear-btn1[data-clear-target]');
      if (!btn) return;
      btn.style.display = isVisible ? 'flex' : 'none';
    }

    // Modern status modal (success/error)
    var fkSuccessOverlay = document.getElementById('fkSuccessOverlay');
    var fkSuccessIcon = document.getElementById('fkSuccessIcon');
    var fkSuccessTitle = document.getElementById('fkSuccessTitle');
    var fkSuccessMessage = document.getElementById('fkSuccessMessage');
    var fkSuccessClose = document.getElementById('fkSuccessClose');
    var statusModalTimer = null;

    WomosiUI.showStatusModal = function (type, title, message) {
      if (!fkSuccessOverlay || !fkSuccessTitle || !fkSuccessMessage || !fkSuccessIcon) {
        alert((title ? (title + '\n\n') : '') + (message || ''));
        return;
      }
      if (statusModalTimer) {
        clearTimeout(statusModalTimer);
        statusModalTimer = null;
      }
      fkSuccessTitle.textContent = title || (type === 'error' ? 'Fehler' : 'Erfolg');
      fkSuccessMessage.textContent = message || '';
      fkSuccessIcon.textContent = type === 'error' ? '!' : '✓';
      fkSuccessOverlay.classList.remove('hidden');
      fkSuccessOverlay.setAttribute('aria-hidden', 'false');
      statusModalTimer = setTimeout(function () {
        WomosiUI.hideStatusModal();
      }, 4000);
    }

    WomosiUI.hideStatusModal = function () {
      if (!fkSuccessOverlay) return;
      fkSuccessOverlay.classList.add('hidden');
      fkSuccessOverlay.setAttribute('aria-hidden', 'true');
      if (statusModalTimer) {
        clearTimeout(statusModalTimer);
        statusModalTimer = null;
      }
    }

    // ==== DOM referansları ====

    var step1 = container.querySelector('#step1');
    var step2 = container.querySelector('#step2');
    var step3 = container.querySelector('#step3');
    var step4 = container.querySelector('#step4');

    var lbl1 = container.querySelector('#lbl1');
    var lbl2 = container.querySelector('#lbl2');
    var lbl3 = container.querySelector('#lbl3');
    var lbl4 = container.querySelector('#lbl4');
    var dot1 = container.querySelector('#dot1');
    var dot2 = container.querySelector('#dot2');
    var dot3 = container.querySelector('#dot3');
    var dot4 = container.querySelector('#dot4');
    var bar = container.querySelector('#bar');
    var stepText = container.querySelector('#stepText');

    var lblP1 = container.querySelector('#lblP1');
    var lblP2 = container.querySelector('#lblP2');
    var dotP1 = container.querySelector('#dotP1');
    var dotP2 = container.querySelector('#dotP2');
    var barPkw = container.querySelector('#barPkw');
    var stepTextPkw = container.querySelector('#stepTextPkw');
    var resetBtnPkw = container.querySelector('#resetAllPkw');
    var segmentTabs = container.querySelectorAll('.fk-segment-tab');

    var vehicleGrid = container.querySelector('#vehicleGrid');
    var toStep2Btn = container.querySelector('#toStep2');
    var backTo1Btn = container.querySelector('#backTo1');
    var toStep3Btn = container.querySelector('#toStep3');
    var backTo2Btn = container.querySelector('#backTo2');
    var toStep4Btn = container.querySelector('#toStep4');
    var backTo3Btn = container.querySelector('#backTo3');
    var finishBtn = container.querySelector('#finish');
    var resetBtn = container.querySelector('#resetAll');

    // Select ve inputlar (HTML’deki id’lere göre)
    var selHer = container.querySelector('#hersteller');
    var selBasis = container.querySelector('#basisfahrzeug');
    var selMod = container.querySelector('#modell');
    var inpModCustom = container.querySelector('#modell_custom');
    var inpYear = container.querySelector('#baujahr');
    var selYearPkw = container.querySelector('#baujahr_pkw');

    WomosiUI.resetPkwBaujahrSelect = function () {
      if (!selYearPkw) return;
      selYearPkw.innerHTML = '';
      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = 'Bitte wählen';
      selYearPkw.appendChild(emptyOpt);
      selYearPkw.value = '';
    }

    WomosiApi.loadPkwBaujahr = function (doneCb) {
      if (!selYearPkw) {
        if (doneCb) doneCb();
        return;
      }
      if (state.segment !== 'pkw' || !state.hersteller) {
        WomosiUI.resetPkwBaujahrSelect();
        if (doneCb) doneCb();
        return;
      }
      var modApi = WomosiState.modellEffectiveForApi();
      if (!modApi) {
        WomosiUI.resetPkwBaujahrSelect();
        if (doneCb) doneCb();
        return;
      }
      WomosiApi.fetchJson('/fk-search', WomosiState.fkSegmentParams({
        mode: 'options',
        field: 'baujahr',
        hersteller: state.hersteller,
        modell: modApi
      })).then(function (data) {
        var vals = uniqSorted(
          (Array.isArray(data) ? data : [])
            .map(function (row) { return row && row.value ? String(row.value) : ''; })
        );
        WomosiUI.fillSelect(selYearPkw, vals);
        if (state.year) {
          WomosiUI.ensureSelectValue(selYearPkw, state.year);
          selYearPkw.value = state.year;
        }
        WomosiUI.setClearBtnVisible(selYearPkw, !!selYearPkw.value);
        if (doneCb) doneCb();
      }).catch(function () {
        WomosiUI.resetPkwBaujahrSelect();
        if (doneCb) doneCb();
      });
    }
    var seatGrid = container.querySelector('#seatGrid');
    var seatHint = container.querySelector('#seatHint');
    var selSerie = container.querySelector('#serie');
    var serieGrid = container.querySelector('#serieGrid');
    var serieHint = container.querySelector('#serieHint');
    var serieEmptyFallback = container.querySelector('#serieEmptyFallback');
    var serieFallbackForm = container.querySelector('#serieFallbackForm');
    var fallbackImagesInput = container.querySelector('#fallback_images');
    var fallbackImagesTrigger = container.querySelector('#fallback_images_trigger');
    var fallbackImagesInfo = container.querySelector('#fallback_images_info');

    // Özet alanları
    var sumTyp = container.querySelector('#sumTyp');
    var sumHer = container.querySelector('#sumHer');
    var sumModell = container.querySelector('#sumModell');
    var sumYear = container.querySelector('#sumYear');
    var sumSitz = container.querySelector('#sumSitz');
    var sumSerie = container.querySelector('#sumSerie');
    var cardEl = container.querySelector('.card');

    // ==== State ====

    var defaultSeg = 'wohnmobil';
    if (segAllowPkw && !segAllowWm) {
      defaultSeg = 'pkw';
    }

    var state = {
      step: 1,
      segment: defaultSeg,
      typKey: '',
      typLabel: '',
      hersteller: '',
      basis: '',
      modell: '',
      modellCustom: '',
      year: '',
      seatId: '',
      seatLabel: '',
      serie: '',
      isLoadingSerie: false
    };

    WomosiState.clampSegmentToAllowed = function () {
      if (state.segment === 'pkw' && !segAllowPkw) {
        state.segment = 'wohnmobil';
      }
      if (state.segment === 'wohnmobil' && !segAllowWm && segAllowPkw) {
        state.segment = 'pkw';
      }
    }

    WomosiUI.applySegmentToDom = function () {
      var isPkw = state.segment === 'pkw';
      container.classList.toggle('fk-segment-pkw', isPkw);
      container.classList.toggle('fk-segment-wm', !isPkw);
      segmentTabs.forEach(function (btn) {
        var seg = btn.getAttribute('data-fk-segment') || '';
        var on = seg === state.segment;
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
    }

    WomosiState.fkSegmentParams = function (extra) {
      var o = extra || {};
      if (state.segment === 'pkw') {
        o.segment = 'pkw';
      }
      return o;
    }

    WomosiState.modellEffectiveForApi = function () {
      if (state.modell === FK_MODELL_CUSTOM_VALUE) {
        return String(state.modellCustom || '').trim();
      }
      return String(state.modell || state.modellCustom || '').trim();
    }

    WomosiUI.syncModellCustomRowVisibility = function () {
      var row = container.querySelector('#fk-modell-custom-row');
      if (!row) return;
      var selVal = selMod && selMod.value ? String(selMod.value).trim() : '';
      var custom = inpModCustom && inpModCustom.value ? String(inpModCustom.value).trim() : '';
      var show = (selVal === FK_MODELL_CUSTOM_VALUE) || (!selVal && !!custom);
      row.classList.toggle('hidden', !show);
    }

    /** Kein Modell aus der Dropdown-Liste: Sentinel-Option oder nur Freitext → Familie-Serie-API, kein Maßanfertigungs-Formular in Step 4 */
    WomosiState.isNoListModelPath = function () {
      var v = selMod && selMod.value ? String(selMod.value).trim() : '';
      if (v === FK_MODELL_CUSTOM_VALUE) return true;
      if (!v && inpModCustom && String(inpModCustom.value || '').trim() !== '') return true;
      return false;
    }

    var seatOptions = [];
    var seatOptionsKey = '';
    var serieCache = [];
    var serieCacheKey = '';
    var lastSelectionLogKey = '';
    // When we restore from localStorage, some fetch/render functions reset selections.
    // These targets allow us to preserve the user's last seat/serie during restore.
    var restoreTargetSeatId = '';
    var restoreTargetSerieCode = '';
    // Initial page açılışında otomatik scroll olmasın istiyoruz.
    var isInitialPageLoad = true;
    // Sıfırlama gibi durumlarda scroll'ı bastırmak için.
    var suppressStepScroll = false;

    WomosiUI.scrollStepIntoView = function (stepEl) {
      if (isInitialPageLoad) return;
      try {
        if (!stepEl || !stepEl.scrollIntoView) return;
        window.requestAnimationFrame(function () {
          stepEl.scrollIntoView({ behavior: 'auto', block: 'start', inline: 'nearest' });
        });
      } catch (_) { }
    }

    WomosiState.saveStateCache = function () {
      try {
        var payload = {
          step: state.step || 1,
          segment: state.segment || 'wohnmobil',
          typKey: state.typKey || '',
          typLabel: state.typLabel || '',
          hersteller: state.hersteller || '',
          basis: state.basis || '',
          modell: state.modell || '',
          modellCustom: state.modellCustom || '',
          year: state.year || '',
          seatId: state.seatId || '',
          seatLabel: state.seatLabel || '',
          serie: state.serie || ''
        };
        window.localStorage.setItem(FK_STATE_CACHE_KEY, JSON.stringify(payload));
      } catch (_) { }
    }

    WomosiState.clearStateCache = function () {
      try {
        window.localStorage.removeItem(FK_STATE_CACHE_KEY);
      } catch (_) { }
    }

    WomosiUI.ensureSelectValue = function (sel, value) {
      if (!sel || !value) return;
      var wanted = String(value);
      var exists = false;
      Array.prototype.slice.call(sel.options || []).forEach(function (opt) {
        if (String(opt.value) === wanted) exists = true;
      });
      if (!exists) {
        var o = document.createElement('option');
        o.value = wanted;
        o.textContent = wanted === FK_MODELL_CUSTOM_VALUE ? 'Mein Modell ist nicht in der Liste' : wanted;
        sel.appendChild(o);
      }
      sel.value = wanted;
    }

    WomosiState.restoreStateCache = function () {
      try {
        var raw = window.localStorage.getItem(FK_STATE_CACHE_KEY);
        if (!raw) return false;
        var cached = JSON.parse(raw);
        if (!cached || typeof cached !== 'object') return false;

        state.segment = String(cached.segment || 'wohnmobil') === 'pkw' ? 'pkw' : 'wohnmobil';
        WomosiState.clampSegmentToAllowed();
        WomosiUI.applySegmentToDom();
        state.typKey = String(cached.typKey || '');
        state.typLabel = String(cached.typLabel || '');
        state.hersteller = String(cached.hersteller || '');
        state.basis = String(cached.basis || '');
        state.modell = String(cached.modell || '');
        state.modellCustom = String(cached.modellCustom || '');
        state.year = String(cached.year || '');
        state.seatId = String(cached.seatId || '');
        state.seatLabel = String(cached.seatLabel || '');
        state.serie = String(cached.serie || '');

        // Preserve targets for restore mode (Step 3 Sitz + Step 4 Serie).
        restoreTargetSeatId = state.seatId || '';
        restoreTargetSerieCode = state.serie || '';

        var desiredStep = parseInt(cached.step, 10);
        if (!desiredStep || desiredStep < 1 || desiredStep > 4) desiredStep = 1;
        if (state.segment === 'pkw' && desiredStep < 2) {
          desiredStep = 2;
        }
        if (state.segment === 'pkw' && desiredStep === 3) {
          desiredStep = 4;
        }

        // Restore visible controls quickly (without waiting API chains)
        if (vehicleGrid && state.typKey) {
          vehicleGrid.querySelectorAll('.tile-link.is-active').forEach(function (a) {
            a.classList.remove('is-active');
          });
          var activeLink = vehicleGrid.querySelector('a.tile-link[data-key="' + state.typKey.replace(/"/g, '\\"') + '"]');
          if (activeLink) activeLink.classList.add('is-active');
        }
        WomosiUI.ensureSelectValue(selHer, state.hersteller);
        WomosiUI.ensureSelectValue(selBasis, state.basis);
        WomosiUI.ensureSelectValue(selMod, state.modell);
        WomosiUI.ensureSelectValue(selSerie, state.serie);
        if (inpModCustom) inpModCustom.value = state.modellCustom || '';
        if (inpYear) inpYear.value = state.year || '';
        if (selYearPkw && state.segment === 'pkw') {
          WomosiUI.resetPkwBaujahrSelect();
        } else if (selYearPkw) {
          WomosiUI.ensureSelectValue(selYearPkw, state.year || '');
          selYearPkw.value = state.year || '';
        }
        WomosiUI.syncModellCustomRowVisibility();

        function finishRestoreAfterPkwChain() {
          WomosiUI.updateSummary();
          WomosiUI.setStep(desiredStep);
          if (desiredStep === 4) {
            WomosiApi.loadSerie();
          }
        }

        if (state.segment === 'pkw' && state.hersteller) {
          WomosiApi.loadModell(function () {
            WomosiApi.loadPkwBaujahr(function () {
              if (state.year && selYearPkw) {
                WomosiUI.ensureSelectValue(selYearPkw, state.year);
                selYearPkw.value = state.year;
              }
              if (selYearPkw) {
                WomosiUI.setClearBtnVisible(selYearPkw, !!selYearPkw.value);
              }
              finishRestoreAfterPkwChain();
            });
          }, state.modell);
        } else {
          WomosiUI.updateSummary();
          WomosiUI.setStep(desiredStep);
          if (desiredStep === 4) {
            WomosiApi.loadSerie();
          }
        }
        return true;
      } catch (_) {
        return false;
      }
    }

    WomosiState.selectionLogKey = function (serieCode) {
      var modellParam = WomosiState.modellEffectiveForApi();
      return [
        state.typLabel || '',
        state.hersteller || '',
        state.basis || '',
        modellParam,
        String(state.year || ''),
        String(state.seatLabel || state.seatId || ''),
        String(serieCode || '')
      ].join('||');
    }

    WomosiState.logStep4Selection = function (serieCode) {
      if (!serieCode) return;
      // Avoid duplicates (tile click + select change can both fire)
      var key = WomosiState.selectionLogKey(serieCode);
      if (key === lastSelectionLogKey) return;
      lastSelectionLogKey = key;

      var selectionEndpoint = fkBase() + '/fk-selection';

      var logParams = {
        typ: state.typLabel || '',
        hersteller: state.hersteller || '',
        basis: state.basis || '',
        modell: WomosiState.modellEffectiveForApi(),
        baujahr: state.year || '',
        sitz: (state.seatLabel || state.seatId || ''),
        serie: serieCode || ''
      };
      if (state.segment === 'pkw') {
        logParams.segment = 'pkw';
      }
      var payload = new URLSearchParams(logParams);

      try {
        // keepalive helps with short navigation, but still best-effort.
        fetch(selectionEndpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
          body: payload,
          keepalive: true,
          credentials: 'same-origin'
        }).catch(function () { });
      } catch (_) { }
    }

    var SEAT_META = {
      '1': { code: '1', name: 'Sitz 1', tooltip: 'Milan 12', img: 'https://womosi.de/mediafiles/sitz/1.jpg' },
      '2': { code: '2', name: 'Sitz 2', tooltip: 'Liner 12', img: 'https://womosi.de/mediafiles/sitz/2.jpg' },
      '3': { code: '3', name: 'Sitz 3', tooltip: 'Captain Chair', img: 'https://womosi.de/mediafiles/sitz/3.jpg' },
      '4': { code: '4', name: 'Sitz 4', tooltip: 'Itineo', img: 'https://womosi.de/mediafiles/sitz/4.jpg' },
      '5': { code: '5', name: 'Sitz 5', tooltip: 'Standart Ducato Sitz', img: 'https://womosi.de/mediafiles/sitz/5.jpg' },
      '6': { code: '6', name: 'Sitz 6', tooltip: 'Milan', img: 'https://womosi.de/mediafiles/sitz/6.jpg' },
      '7': { code: '7', name: 'Sitz 7', tooltip: 'Gis Liner', img: 'https://womosi.de/mediafiles/sitz/7.jpg' },
      '8': { code: '8', name: 'Sitz 8', tooltip: 'Gis Liner 3', img: 'https://womosi.de/mediafiles/sitz/8.jpg' },
      '9': { code: '9', name: 'Sitz 9', tooltip: 'Ford Transit', img: 'https://womosi.de/mediafiles/sitz/9.jpg' },
      '10': { code: '10', name: 'Sitz 10', tooltip: 'Ford Pilot', img: 'https://womosi.de/mediafiles/sitz/10.jpg' },
      '11': { code: '11', name: 'Sitz 11', tooltip: 'Mian 3', img: 'https://womosi.de/mediafiles/sitz/11.jpg' },
      '12': { code: '12', name: 'Sitz 12', tooltip: 'SKA 1800-M23 Komfort', img: 'https://womosi.de/mediafiles/sitz/12.jpg' },
      '13': { code: '13', name: 'Sitz 13', tooltip: 'SKA 1800-1000 M23 Classic', img: 'https://womosi.de/mediafiles/sitz/13.jpg' },
      '14': { code: '14', name: 'Sitz 14', tooltip: 'Mercedes Sprinter', img: 'https://womosi.de/mediafiles/sitz/14.jpg' }
    };

    WomosiState.seatCodeFromValue = function (v) {
      var s = String(v || '').trim();
      if (!s) return '';
      // Accept "1", "01", "Sitz 1", "Sitz 10", etc.
      var m = s.match(/(\d{1,2})/);
      if (!m) return '';
      var code = String(parseInt(m[1], 14));
      return SEAT_META[code] ? code : '';
    }

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
    } else {
      // Fallback: if head injection is missing due to cache/minify, load config via endpoint.
      try {
        if (!window.__fkSeriesCfgPromise) {
          window.__fkSeriesCfgPromise = WomosiApi.fetchJson('/fk-search', { mode: 'series_meta' })
            .then(function (cfg) {
              try { applyFkSeriesConfig(cfg); } catch (_) { }
              // Re-render cached series list to enforce hide filters.
              try {
                if (Array.isArray(serieCache) && serieCache.length) {
                  WomosiUI.renderSerieItems(serieCache);
                }
              } catch (_) { }
            })
            .catch(function () { });
        }
      } catch (_) { }
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


    // ==== Step kontrol ====

    WomosiUI.setStep = function (n) {
      // Step değişirken bazı tarayıcılar odaklanan element'i görünür kılmak için scroll yapabilir.
      // Bunu engellemek için önce aktif element'i blur'layalım.
      try {
        var ae = document.activeElement;
        if (ae && ae.blur) ae.blur();
      } catch (_) { }

      if (state.segment === 'pkw' && n < 2) {
        n = 2;
      }
      if (state.segment === 'pkw' && n === 3) {
        n = 4;
      }

      state.step = n;

      if (step1) step1.classList.toggle('hidden', n !== 1);
      if (step2) step2.classList.toggle('hidden', n !== 2);
      if (step3) step3.classList.toggle('hidden', n !== 3);
      if (step4) step4.classList.toggle('hidden', n !== 4);
      
      if (n !== 4 && serieLoadingInterval) {
        clearInterval(serieLoadingInterval);
        serieLoadingInterval = null;
      }

      var labels = [lbl1, lbl2, lbl3, lbl4];
      var dots = [dot1, dot2, dot3, dot4];
      labels.forEach(function (l, i) {
        if (!l || !dots[i]) return;
        if (i + 1 === n) {
          l.classList.add('active');
          dots[i].classList.add('active');
        } else {
          l.classList.remove('active');
          dots[i].classList.remove('active');
        }
      });

      if (bar) {
        bar.style.width = (25 * n) + '%';
      }
      if (stepText) {
        stepText.textContent = 'Step ' + n + '/4';
      }

      if (state.segment === 'pkw') {
        var pkwUX = (n >= 4) ? 2 : 1;
        var pkwLabels = [lblP1, lblP2];
        var pkwDots = [dotP1, dotP2];
        pkwLabels.forEach(function (l, i) {
          if (!l || !pkwDots[i]) return;
          if (i + 1 === pkwUX) {
            l.classList.add('active');
            pkwDots[i].classList.add('active');
          } else {
            l.classList.remove('active');
            pkwDots[i].classList.remove('active');
          }
        });
        if (barPkw) {
          barPkw.style.width = (pkwUX === 1 ? 50 : 100) + '%';
        }
        if (stepTextPkw) {
          stepTextPkw.textContent = 'Step ' + pkwUX + '/2';
        }
      }

      WomosiUI.updateButtons();
      // Sadece Step 1-2-3 geçişlerinde scroll:
      // kullanıcı isteği -> her seferinde .card alanına kaydır.
      var doScroll = (n === 1 || n === 2 || n === 3);
      if (state.segment === 'pkw' && n === 4) {
        doScroll = true;
      }
      if (state.segment === 'pkw' && n === 3) {
        doScroll = false;
      }
      if (doScroll && !suppressStepScroll) WomosiUI.scrollStepIntoView(cardEl);

      if (n === 3 && state.segment !== 'pkw') {
        WomosiApi.loadSitzOptions();
      }
    }

    function validateStep2() {
      if (state.segment === 'pkw') {
        if (!state.hersteller) return { ok: false, msg: 'Bitte Hersteller wählen.' };
        var mp = WomosiState.modellEffectiveForApi();
        if (!mp) return { ok: false, msg: 'Bitte Modell wählen.' };
        var yp = (state.year || '').trim();
        if (!yp) {
          return { ok: false, msg: 'Bitte Baujahr wählen.' };
        }
        return { ok: true, msg: '' };
      }
      if (!state.typKey) return { ok: false, msg: 'Bitte zuerst Fahrzeugtyp wählen.' };
      if (!state.hersteller) return { ok: false, msg: 'Bitte Hersteller wählen.' };
      if (!state.basis) return { ok: false, msg: 'Bitte Wohnmobil-Fahrgestell wählen.' };

      var m = WomosiState.modellEffectiveForApi();
      if (!m) return { ok: false, msg: 'Bitte Modell wählen oder eintragen.' };

      var y = (state.year || '').trim();
      var yn = y ? Number(y) : NaN;
      if (!y || !Number.isInteger(yn) || yn < 1900 || yn > 2100) {
        return { ok: false, msg: 'Bitte gültiges Baujahr (1900–2100) eingeben.' };
      }
      return { ok: true, msg: '' };
    }

    WomosiUI.updateButtons = function () {
      var v2 = validateStep2().ok;

      if (toStep2Btn) {
        toStep2Btn.disabled = state.segment === 'pkw' ? true : !state.typKey;
      }
      if (toStep3Btn) {
        toStep3Btn.disabled = !v2;
        toStep3Btn.textContent = state.segment === 'pkw'
          ? 'Weiter zur Serie'
          : 'Weiter zu Sitz-Auswahl';
      }
      if (toStep4Btn) {
        toStep4Btn.disabled = state.segment === 'pkw' ? true : !state.seatId;
      }
      if (finishBtn) finishBtn.disabled = !state.serie;
    }

    // ==== Özet ====

    WomosiUI.updateSummary = function () {
      if (sumTyp) sumTyp.textContent = state.typLabel || '–';
      if (sumHer) sumHer.textContent = state.hersteller || '–';
      var modellText = WomosiState.modellEffectiveForApi() || '–';
      if (sumModell) sumModell.textContent = modellText;
      if (sumYear) sumYear.textContent = state.year || '–';
      if (sumSitz) sumSitz.textContent = state.seatLabel || '–';
      if (sumSerie) sumSerie.textContent = state.serie || '–';

      WomosiUI.updateButtons();
      WomosiState.saveStateCache();
    }

    WomosiUI.hardReset = function () {
      // State sıfırla
      state.step = 1;
      state.typKey = '';
      state.typLabel = '';
      state.hersteller = '';
      state.basis = '';
      state.modell = '';
      state.modellCustom = '';
      state.year = '';
      state.seatId = '';
      state.seatLabel = '';
      state.serie = '';

      seatOptions = [];
      seatOptionsKey = '';

      // Step 1: Fahrzeugtyp seçimlerini kaldır
      if (vehicleGrid) {
        vehicleGrid.querySelectorAll('.tile-link.is-active').forEach(function (a) {
          a.classList.remove('is-active');
        });
      }

      // Step 2: dropdown ve Baujahr input'unu temizle
      if (selHer) {
        WomosiUI.fillSelect(selHer, []);
        selHer.value = '';
        WomosiUI.setClearBtnVisible(selHer, false);
      }
      if (selBasis) {
        WomosiUI.fillSelect(selBasis, []);
        selBasis.value = '';
        WomosiUI.setClearBtnVisible(selBasis, false);
      }
      if (selMod) {
        WomosiUI.fillSelect(selMod, []);
        selMod.value = '';
        WomosiUI.setClearBtnVisible(selMod, false);
      }
      if (inpModCustom) {
        inpModCustom.value = '';
        WomosiUI.setClearBtnVisible(inpModCustom, false);
      }
      if (inpYear) {
        inpYear.value = '';
        WomosiUI.setClearBtnVisible(inpYear, false);
      }
      if (selYearPkw) {
        WomosiUI.resetPkwBaujahrSelect();
        WomosiUI.setClearBtnVisible(selYearPkw, false);
      }
      WomosiUI.syncModellCustomRowVisibility();

      // Step 3: Sitz gridini temizle
      if (seatGrid) {
        seatGrid.innerHTML = '';
      }
      if (seatHint) {
        seatHint.textContent = '';
      }

      // Step 4: Serie select ve gridini temizle
      if (selSerie) {
        WomosiUI.fillSelect(selSerie, []);
        selSerie.value = '';
      }
      if (serieGrid) {
        serieGrid.innerHTML = '';
      }
      if (serieEmptyFallback) {
        serieEmptyFallback.classList.add('hidden');
      }
      if (serieHint) {
        serieHint.classList.remove('hidden');
      }
      if (serieLoadingInterval) {
        clearInterval(serieLoadingInterval);
        serieLoadingInterval = null;
      }

      // Step ve özet'i başa sar
      if (state.segment === 'pkw') {
        WomosiApi.loadHersteller();
        WomosiUI.setStep(2);
      } else {
        WomosiUI.setStep(1);
      }
      WomosiUI.updateSummary();
      WomosiState.clearStateCache();
    }

    // ==== Dropdown yükleme ====

    WomosiApi.loadHersteller = function () {
      if (state.segment === 'wohnmobil' && !state.typKey) {
        if (selHer) WomosiUI.fillSelect(selHer, []);
        return;
      }
      var hq = WomosiState.fkSegmentParams({
        mode: 'options',
        field: 'hersteller',
        typ: state.segment === 'wohnmobil' ? (state.typKey || '') : ''
      });
      WomosiApi.fetchJson('/fk-search', hq).then(function (data) {
        var vals = uniqSorted(
          (Array.isArray(data) ? data : [])
            .map(function (row) { return row && row.value ? String(row.value) : ''; })
        );
        WomosiUI.fillSelect(selHer, vals);
        state.hersteller = '';
        state.basis = '';
        state.modell = '';
        if (selBasis) WomosiUI.fillSelect(selBasis, []);
        if (selMod) WomosiUI.fillSelect(selMod, []);
        if (state.segment === 'pkw') {
          state.year = '';
          WomosiUI.resetPkwBaujahrSelect();
          if (selYearPkw) WomosiUI.setClearBtnVisible(selYearPkw, false);
        }
        WomosiUI.updateSummary();
      }).catch(function () {
        WomosiUI.fillSelect(selHer, []);
      });
    }

    WomosiApi.loadBasis = function () {
      if (!state.typKey || !state.hersteller) {
        if (selBasis) WomosiUI.fillSelect(selBasis, []);
        return;
      }
      WomosiApi.fetchJson('/fk-search', {
        mode: 'options',
        field: 'basis',
        typ: state.typKey || '',
        hersteller: state.hersteller || ''
      }).then(function (data) {
        var vals = uniqSorted(
          (Array.isArray(data) ? data : [])
            .map(function (row) { return row && row.value ? String(row.value) : ''; })
        );
        if (selBasis) WomosiUI.fillSelect(selBasis, vals);
        state.basis = '';
        state.modell = '';
        if (selMod) {
          WomosiUI.fillSelect(selMod, []);
          selMod.value = '';
          WomosiUI.setClearBtnVisible(selMod, false);
        }
        WomosiUI.updateSummary();
      }).catch(function () {
        if (selBasis) WomosiUI.fillSelect(selBasis, []);
      });
    }

    WomosiApi.loadModell = function (doneCb, pkwKeepModell) {
      if (state.segment === 'pkw') {
        if (!state.hersteller) {
          WomosiUI.fillSelect(selMod, []);
          WomosiUI.resetPkwBaujahrSelect();
          state.year = '';
          if (doneCb) doneCb();
          return;
        }
        WomosiApi.fetchJson('/fk-search', WomosiState.fkSegmentParams({
          mode: 'options',
          field: 'modell',
          hersteller: state.hersteller || ''
        })).then(function (data) {
          var vals = uniqSorted(
            (Array.isArray(data) ? data : [])
              .map(function (row) { return row && row.value ? String(row.value) : ''; })
          );
          WomosiUI.fillSelect(selMod, vals);
          var keep = pkwKeepModell ? String(pkwKeepModell).trim() : '';
          if (keep) {
            WomosiUI.ensureSelectValue(selMod, keep);
            state.modell = String(selMod.value || keep);
          } else {
            state.modell = '';
            state.modellCustom = '';
            if (inpModCustom) {
              inpModCustom.value = '';
              WomosiUI.setClearBtnVisible(inpModCustom, false);
            }
          }
          WomosiUI.syncModellCustomRowVisibility();
          WomosiUI.resetPkwBaujahrSelect();
          if (!keep) {
            state.year = '';
          }
          WomosiUI.updateSummary();
          if (state.modell && String(state.modell) !== FK_MODELL_CUSTOM_VALUE) {
            WomosiApi.loadPkwBaujahr(doneCb);
          } else if (doneCb) {
            doneCb();
          }
        }).catch(function () {
          WomosiUI.fillSelect(selMod, []);
          WomosiUI.resetPkwBaujahrSelect();
          state.year = '';
          if (doneCb) doneCb();
        });
        return;
      }
      if (!state.typKey || !state.hersteller || !state.basis) {
        WomosiUI.fillSelect(selMod, []);
        if (doneCb) doneCb();
        return;
      }
      WomosiApi.fetchJson('/fk-search', {
        mode: 'options',
        field: 'modell',
        typ: state.typKey || '',
        hersteller: state.hersteller || '',
        basis: state.basis || ''
      }).then(function (data) {
        var vals = uniqSorted(
          (Array.isArray(data) ? data : [])
            .map(function (row) { return row && row.value ? String(row.value) : ''; })
        );
        WomosiUI.fillSelect(selMod, vals);
        WomosiUI.appendModellNotInListOption(selMod);
        state.modell = '';
        state.modellCustom = '';
        if (inpModCustom) {
          inpModCustom.value = '';
          WomosiUI.setClearBtnVisible(inpModCustom, false);
        }
        WomosiUI.syncModellCustomRowVisibility();
        WomosiUI.updateSummary();
        if (doneCb) doneCb();
      }).catch(function () {
        WomosiUI.fillSelect(selMod, []);
        if (doneCb) doneCb();
      });
    }

    WomosiUI.renderSeats = function (values) {
      if (!seatGrid) return;
      seatGrid.innerHTML = '';
      var list = Array.isArray(values) ? values : [];
      if (!list.length) {
        if (seatHint) seatHint.textContent = 'Keine kompatiblen Sitze gefunden.';
        state.seatId = '';
        state.seatLabel = '';
        WomosiUI.updateSummary();
        return;
      }
      if (seatHint) seatHint.textContent = 'Wähle deinen Sitz.';
      list.forEach(function (label) {
        var seatValue = String(label || '').trim();
        if (!seatValue) return;
        var code = WomosiState.seatCodeFromValue(seatValue);
        var meta = code ? SEAT_META[code] : null;
        var a = document.createElement('a');
        a.href = '#';
        a.className = 'tile-link' + (state.seatId === seatValue ? ' is-active' : '');

        var tile = document.createElement('div');
        tile.className = 'tile';
        if (meta && meta.tooltip) {
          tile.setAttribute('data-tooltip', meta.tooltip);
        }

        if (meta && meta.img) {
          var imgWrap = document.createElement('div');
          imgWrap.className = 'imgWrap';
          var img = document.createElement('img');
          img.loading = 'lazy';
          img.decoding = 'async';
          img.src = meta.img;
          img.alt = meta.name || seatValue;
          imgWrap.appendChild(img);
          tile.appendChild(imgWrap);
        }

        var name = document.createElement('div');
        name.className = 'name';
        name.textContent = (meta && meta.name) ? meta.name : seatValue;
        tile.appendChild(name);

        a.appendChild(tile);
        a.addEventListener('click', function (e) {
          e.preventDefault();
          state.seatId = seatValue;
          state.seatLabel = (meta && meta.name) ? meta.name : seatValue;
          WomosiUI.renderSeats(list);
          WomosiUI.updateSummary();
          // Sitz seçildikten sonra otomatik Step 4'e geç ve Serileri yükle
          WomosiUI.setStep(4);
          WomosiApi.loadSerie();
        });
        seatGrid.appendChild(a);
      });
    }

    WomosiApi.loadSitzOptions = function () {
      if (state.segment === 'pkw') {
        return;
      }
      var preserveSeat = !!restoreTargetSeatId;
      var modellApi = WomosiState.modellEffectiveForApi();
      if (!state.typKey || !state.hersteller || !state.basis || !modellApi) {
        seatOptions = [];
        seatOptionsKey = '';
        WomosiUI.renderSeats([]);
        restoreTargetSeatId = '';
        return;
      }

      var key = [state.typKey, state.hersteller, state.basis, modellApi].join('||');
      if (key === seatOptionsKey && seatOptions.length) {
        WomosiUI.renderSeats(seatOptions);
        if (preserveSeat) restoreTargetSeatId = '';
        return;
      }

      if (seatHint) seatHint.textContent = 'Lade Sitze...';

      var sitzParams = {
        mode: 'options',
        field: 'sitz',
        typ: state.typKey || '',
        hersteller: state.hersteller || '',
        basis: state.basis || '',
        modell: modellApi
      };
      if (state.segment !== 'pkw' && state.modell === FK_MODELL_CUSTOM_VALUE) {
        sitzParams.fkCustomModell = '1';
      }

      WomosiApi.fetchJson('/fk-search', sitzParams).then(function (data) {
        var vals = uniqSorted(
          (Array.isArray(data) ? data : [])
            .map(function (row) { return row && row.value ? String(row.value) : ''; })
        );
        seatOptions = vals;
        seatOptionsKey = key;
        if (!preserveSeat) {
          state.seatId = '';
          state.seatLabel = '';
        }
        WomosiUI.renderSeats(seatOptions);
        WomosiUI.updateSummary();
        if (preserveSeat) restoreTargetSeatId = '';
      }).catch(function () {
        seatOptions = [];
        seatOptionsKey = '';
        WomosiUI.renderSeats([]);
        restoreTargetSeatId = '';
      });
    }

    WomosiApi.loadSerie = function () {
      if (!selSerie) return;

      // Legacy: Select boş, yalnızca Freitext — Maßanfertigung (Serie-Suche yok).
      // "Mein Modell ist nicht in der Liste" + Freitext: önce Sitz, sonra Familie-Serie (API: fkCustomModell).
      var domModellValue = selMod && selMod.value ? String(selMod.value).trim() : '';
      var domCustomValue = inpModCustom && inpModCustom.value ? String(inpModCustom.value).trim() : '';
      var hasLegacyOnlyCustomModel = !domModellValue && !!domCustomValue;

      if (domModellValue === FK_MODELL_CUSTOM_VALUE && !domCustomValue) {
        if (serieEmptyFallback) serieEmptyFallback.classList.add('hidden');
        if (serieGrid) serieGrid.classList.add('hidden');
        if (serieHint) {
          serieHint.textContent = 'Bitte Modellbezeichnung eingeben.';
          serieHint.classList.remove('hidden');
        }
        if (finishBtn) finishBtn.disabled = true;
        WomosiUI.fillSelect(selSerie, []);
        return;
      }

      // Nur ohne gewählten Sitz: direkt Maßanfertigung (früher möglich). Nach Sitz-Wahl: Serien-API (Familie-Serie bei Freitext).
      if (hasLegacyOnlyCustomModel && !state.seatId && state.segment !== 'pkw') {
        if (serieEmptyFallback) serieEmptyFallback.classList.remove('hidden');
        if (serieGrid) serieGrid.classList.add('hidden');
        if (serieHint) serieHint.classList.add('hidden');
        if (finishBtn) finishBtn.disabled = true;
        WomosiUI.fillSelect(selSerie, []);
        return;
      }

      var params = {};
      if (state.segment === 'pkw') {
        params.segment = 'pkw';
      }
      if (state.typKey) params.typ = state.typKey;
      if (state.hersteller) params.hersteller = state.hersteller;
      if (state.basis) params.basis = state.basis;
      var modellParam = WomosiState.modellEffectiveForApi();
      if (modellParam) params.modell = modellParam;
      if (state.year) params.baujahr = state.year;
      if (state.seatId) params.sitz = state.seatId;
      // Sentinel „Nicht in der Liste“: Flags immer (auch ohne Freitext), sonst greift der gefilterte Endpoint → oft []
      if (state.modell === FK_MODELL_CUSTOM_VALUE) {
        params.fkCustomModell = '1';
        params.fkNichtInListe = '1';
      } else if (modellParam && (!domModellValue && !!domCustomValue)) {
        params.fkCustomModell = '1';
      }

      var cacheKey = [
        params.segment || '',
        params.typ || '',
        params.hersteller || '',
        params.basis || '',
        params.modell || '',
        params.baujahr || '',
        params.sitz || '',
        params.fkCustomModell || '',
        params.fkNichtInListe || ''
      ].join('||');

      if (cacheKey === serieCacheKey && Array.isArray(serieCache) && serieCache.length) {
        // reuse cached series items for snappy Step 4
        WomosiUI.renderSerieItems(serieCache);
        return;
      }

      state.isLoadingSerie = true;
      WomosiUI.updateSummary();

      if (serieHint) {
        serieHint.innerHTML = '<div class="ai-hint"><div class="ai-icon">' + AI_ICON_SVG + '</div><span class="ai-text"></span></div>';
        var aiTextEl = serieHint.querySelector('.ai-text');
        serieHint.classList.remove('hidden');
        var msgIdx = 0;
        if (aiTextEl) aiTextEl.textContent = serieLoadingMessages[msgIdx];
        
        if (serieLoadingInterval) clearInterval(serieLoadingInterval);
        serieLoadingInterval = setInterval(function () {
          if (aiTextEl) {
            aiTextEl.classList.add('fading');
            setTimeout(function() {
              msgIdx = (msgIdx + 1) % serieLoadingMessages.length;
              aiTextEl.textContent = serieLoadingMessages[msgIdx];
              aiTextEl.classList.remove('fading');
            }, 300);
          }
        }, 3000);
      }

      WomosiApi.fetchJson('/fk-search', params).then(function (items) {
        state.isLoadingSerie = false;
        if (serieLoadingInterval) {
          clearInterval(serieLoadingInterval);
          serieLoadingInterval = null;
        }
        var list = Array.isArray(items) ? items : [];
        serieCacheKey = cacheKey;
        serieCache = list;

        WomosiUI.renderSerieItems(list);
      }).catch(function () {
        state.isLoadingSerie = false;
        if (serieLoadingInterval) {
          clearInterval(serieLoadingInterval);
          serieLoadingInterval = null;
        }
        WomosiUI.renderSerieItems([]);
        if (finishBtn) finishBtn.disabled = true;
      });
    }

    WomosiUI.renderSerieItems = function (list) {
      var arr = (Array.isArray(list) ? list : []).filter(function (it) {
        return it && !isSerieHidden(it.serie);
      });
      var preserveSerie = !!restoreTargetSerieCode;
      var targetSerie = preserveSerie ? String(restoreTargetSerieCode || '') : '';
      var foundPreserve = false;

      // Kein Treffer: bei normalem Listen-Modell → Maßanfertigungs-Formular; bei „Modell nicht in Liste“ nur Hinweis (kein Formular)
      if (!arr.length) {
        if (WomosiState.isNoListModelPath()) {
          if (serieEmptyFallback) serieEmptyFallback.classList.add('hidden');
          if (serieGrid) serieGrid.classList.add('hidden');
          if (serieHint) {
            serieHint.innerHTML = '<div class="ai-hint" style="background:rgba(0,0,0,0.03);border-color:rgba(0,0,0,0.1)"><span class="ai-text" style="color:rgba(0,0,0,0.5)">Keine Familie-Serie zu dieser Auswahl gefunden.</span></div>';
            serieHint.classList.remove('hidden');
          }
        } else {
          if (serieEmptyFallback) serieEmptyFallback.classList.remove('hidden');
          if (serieGrid) serieGrid.classList.add('hidden');
          if (serieHint) serieHint.classList.add('hidden');
        }
        if (finishBtn) finishBtn.disabled = true;
        state.serie = '';
        if (selSerie) selSerie.value = '';
        if (preserveSerie) restoreTargetSerieCode = '';
        WomosiUI.updateSummary();
        WomosiUI.fillSelect(selSerie, []);
        return;
      }

      if (serieEmptyFallback) serieEmptyFallback.classList.add('hidden');
      if (serieGrid) serieGrid.classList.remove('hidden');
      if (serieHint) {
        serieHint.innerHTML = '<div class="ai-hint"><div class="ai-icon">' + AI_ICON_SVG + '</div><span class="ai-text">Liste gefunden, bitte wählen Sie Ihre Serie.</span></div>';
        serieHint.classList.remove('hidden');
      }

      var serieValues = arr
        .map(function (it) { return String(it.serie || ''); })
        .filter(Boolean);

      WomosiUI.fillSelect(selSerie, serieValues);
      if (!preserveSerie) {
        state.serie = '';
        selSerie.value = '';
      } else {
        state.serie = targetSerie;
        selSerie.value = targetSerie;
      }

      if (serieGrid) {
        serieGrid.innerHTML = '';
        arr.forEach(function (it, idx) {
          var code = String(it.serie || '').trim();
          if (!code) return;
          var url = String(it.url || '#');
          var meta = findSeriesMeta(code);

          var a = document.createElement('a');
          a.href = url;
          if (preserveSerie && code === targetSerie) {
            foundPreserve = true;
          }
          a.className = 'tile-link serie-tile-link' + ((preserveSerie && code === state.serie) ? ' is-active' : '');

          var tile = document.createElement('div');
          tile.className = 'tile';
          if (meta && meta.color) {
            tile.style.backgroundColor = meta.color;
          }

          var imgSrc = meta && meta.img ? meta.img : '';
          if (imgSrc) {
            // Warm up browser cache ASAP for perceived speed
            try {
              var pre = new Image();
              pre.decoding = 'async';
              pre.src = imgSrc;
            } catch (_) { }

            var imgWrap = document.createElement('div');
            imgWrap.className = 'serie-imgWrap';

            var img = document.createElement('img');
            // Improve LCP: prioritize first tiles, lazy for the rest
            if (idx < 2) {
              img.loading = 'eager';
              img.setAttribute('fetchpriority', 'high');
            } else {
              img.loading = 'lazy';
              img.setAttribute('fetchpriority', 'low');
            }
            img.decoding = 'async';
            img.src = imgSrc;
            img.alt = (meta && meta.label) ? meta.label : code;
            // Reserve space to reduce CLS (matches CSS aspect-ratio)
            img.width = 1600;
            img.height = 900;

            imgWrap.appendChild(img);
            tile.appendChild(imgWrap);
          }

          var desc = document.createElement('div');
          desc.className = 'serie-desc';
          desc.textContent = (meta && meta.label) ? meta.label : ('Serie ' + code);
          tile.appendChild(desc);

          a.appendChild(tile);

          a.addEventListener('click', function (e) {
            e.preventDefault();
            state.serie = code;
            selSerie.value = code;
            serieGrid.querySelectorAll('.serie-tile-link').forEach(function (x) {
              x.classList.remove('is-active');
            });
            a.classList.add('is-active');
            WomosiUI.updateSummary();

            // Step 4 - Seçilen seri sonrası admin dashboard için log
            WomosiState.logStep4Selection(code);

            if (url && url !== '#') {
              // Give the logging request a moment on unload.
              window.setTimeout(function () {
                window.location.href = url;
              }, 80);
            }
          });
          serieGrid.appendChild(a);
        });
      }

      if (preserveSerie) {
        if (!foundPreserve) {
          state.serie = '';
          selSerie.value = '';
        }
        restoreTargetSerieCode = '';
      }

      WomosiUI.updateSummary();
    }

    // ==== Event bağlama ====

    if (vehicleGrid) {
      vehicleGrid.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a.tile-link') : null;
        if (!link) return;
        e.preventDefault();
        var key = link.getAttribute('data-key') || '';
        var label = link.getAttribute('data-label') || link.textContent.trim();

        state.typKey = key;
        state.typLabel = label;

        vehicleGrid.querySelectorAll('.tile-link.is-active').forEach(function (a) {
          a.classList.remove('is-active');
        });
        link.classList.add('is-active');

        WomosiApi.loadHersteller();
        WomosiUI.setStep(2);
        WomosiUI.updateSummary();
      });
    }

    if (selHer) {
      selHer.addEventListener('change', function () {
        state.hersteller = selHer.value || '';
        state.basis = '';
        state.modell = '';
        state.seatId = '';
        state.seatLabel = '';
        seatOptions = [];
        seatOptionsKey = '';
        if (selBasis) {
          WomosiUI.fillSelect(selBasis, []);
          selBasis.value = '';
          WomosiUI.setClearBtnVisible(selBasis, false);
        }
        if (selMod) {
          WomosiUI.fillSelect(selMod, []);
          selMod.value = '';
          WomosiUI.setClearBtnVisible(selMod, false);
        }
        if (state.hersteller) {
          if (state.segment === 'pkw') {
            WomosiApi.loadModell();
          } else {
            WomosiApi.loadBasis();
          }
        }
        WomosiUI.updateSummary();
        WomosiUI.setClearBtnVisible(selHer, !!state.hersteller);
      });
    }

    if (selBasis) {
      selBasis.addEventListener('change', function () {
        state.basis = selBasis.value || '';
        state.modell = '';
        state.modellCustom = '';
        if (inpModCustom) {
          inpModCustom.value = '';
          WomosiUI.setClearBtnVisible(inpModCustom, false);
        }
        state.seatId = '';
        state.seatLabel = '';
        seatOptions = [];
        seatOptionsKey = '';
        if (selMod) {
          WomosiUI.fillSelect(selMod, []);
          selMod.value = '';
          WomosiUI.setClearBtnVisible(selMod, false);
        }
        if (state.basis) {
          WomosiApi.loadModell();
        }
        WomosiUI.updateSummary();
        WomosiUI.setClearBtnVisible(selBasis, !!state.basis);
      });
    }

    if (selMod) {
      selMod.addEventListener('change', function () {
        var v = selMod.value || '';
        state.modell = v;
        state.modellCustom = '';
        if (inpModCustom) {
          inpModCustom.value = '';
          WomosiUI.setClearBtnVisible(inpModCustom, false);
        }
        if (v === FK_MODELL_CUSTOM_VALUE) {
          try {
            if (inpModCustom) inpModCustom.focus();
          } catch (_) { }
        }
        state.seatId = '';
        state.seatLabel = '';
        seatOptions = [];
        seatOptionsKey = '';
        if (state.segment === 'pkw') {
          state.year = '';
          if (v && v !== FK_MODELL_CUSTOM_VALUE) {
            WomosiApi.loadPkwBaujahr();
          } else {
            WomosiUI.resetPkwBaujahrSelect();
            WomosiUI.setClearBtnVisible(selYearPkw, false);
          }
        }
        WomosiUI.syncModellCustomRowVisibility();
        WomosiUI.updateSummary();
        WomosiUI.setClearBtnVisible(selMod, !!state.modell);
      });
    }

    if (inpModCustom) {
      inpModCustom.addEventListener('input', function () {
        state.modellCustom = inpModCustom.value || '';
        if (state.modellCustom) {
          // Serbest metin: select'te "Liste dışı" yoksa seçimi temizle
          if (selMod && String(selMod.value) !== FK_MODELL_CUSTOM_VALUE) {
            selMod.value = '';
            state.modell = '';
            WomosiUI.setClearBtnVisible(selMod, false);
          }
        }
        WomosiUI.syncModellCustomRowVisibility();
        WomosiUI.updateSummary();
        WomosiUI.setClearBtnVisible(inpModCustom, !!state.modellCustom);
      });
    }

    if (inpYear) {
      inpYear.addEventListener('input', function () {
        state.year = inpYear.value || '';
        WomosiUI.updateSummary();
        if (state.step === 2 && state.segment !== 'pkw') {
          var v = validateStep2();
          if (v.ok) {
            WomosiUI.setStep(3);
          }
        }
        WomosiUI.setClearBtnVisible(inpYear, !!state.year);
      });
    }

    if (selYearPkw) {
      selYearPkw.addEventListener('change', function () {
        state.year = selYearPkw.value || '';
        WomosiUI.updateSummary();
        if (state.segment === 'pkw' && state.step === 2) {
          var vp = validateStep2();
          if (vp.ok) {
            WomosiUI.setStep(4);
            WomosiApi.loadSerie();
          }
        }
        WomosiUI.setClearBtnVisible(selYearPkw, !!state.year);
      });
    }

    if (selSerie) {
      selSerie.addEventListener('change', function () {
        state.serie = selSerie.value || '';
        WomosiUI.updateSummary();
        // Dashboard için de logla (tile yerine select kullanılabilirse)
        if (state.step === 4) {
          WomosiState.logStep4Selection(selSerie.value || '');
        }
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function (e) {
        e.preventDefault();
        WomosiUI.hardReset();
      });
    }
    if (resetBtnPkw) {
      resetBtnPkw.addEventListener('click', function (e) {
        e.preventDefault();
        WomosiUI.hardReset();
      });
    }

    segmentTabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        var seg = tab.getAttribute('data-fk-segment') || '';
        if (seg !== 'pkw' && seg !== 'wohnmobil') return;
        if (seg === 'pkw' && !segAllowPkw) return;
        if (seg === 'wohnmobil' && !segAllowWm) return;
        if (state.segment === seg) return;
        state.segment = seg;
        WomosiUI.applySegmentToDom();
        WomosiUI.hardReset();
      });
    });

    // Status modal close handlers
    if (fkSuccessClose) {
      fkSuccessClose.addEventListener('click', function () {
        WomosiUI.hideStatusModal();
      });
    }
    if (fkSuccessOverlay) {
      fkSuccessOverlay.addEventListener('click', function (e) {
        if (e.target === fkSuccessOverlay) {
          WomosiUI.hideStatusModal();
        }
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        WomosiUI.hideStatusModal();
      }
    });

    // Step 2 clear buttons (x)
    container.querySelectorAll('.fk-clear-btn[data-clear-target], .fk-clear-btn1[data-clear-target]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var sel = btn.getAttribute('data-clear-target') || '';
        var target = sel ? container.querySelector(sel) : null;
        if (!target) return;

        // Clear field and trigger the right event so dependent fields reset.
        if (target.tagName === 'SELECT') {
          target.value = '';
          target.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          target.value = '';
          target.dispatchEvent(new Event('input', { bubbles: true }));
        }

        WomosiUI.setClearBtnVisible(target, false);
      });
    });

    // Maßanfertigung – Datei-Upload Trigger
    if (fallbackImagesTrigger && fallbackImagesInput) {
      fallbackImagesTrigger.addEventListener('click', function (e) {
        e.preventDefault();
        fallbackImagesInput.click();
      });
    }

    if (fallbackImagesInput && fallbackImagesInfo) {
      fallbackImagesInput.addEventListener('change', function () {
        var files = Array.prototype.slice.call(fallbackImagesInput.files || []);
        if (!files.length) {
          fallbackImagesInfo.textContent = 'Keine Dateien ausgewählt';
          return;
        }
        if (files.length === 1) {
          fallbackImagesInfo.textContent = files[0].name;
        } else {
          fallbackImagesInfo.textContent = files.length + ' Dateien ausgewählt';
        }
      });
    }

    // Maßanfertigung – Formular via AJAX an sitzbezug-anfrage.php senden
    if (serieFallbackForm) {
      serieFallbackForm.addEventListener('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(serieFallbackForm);

        // Konfigurator-Zusammenfassung als Body anhängen
        var summaryParts = [];
        if (state.typLabel) summaryParts.push('Fahrzeugtyp: ' + state.typLabel);
        if (state.hersteller) summaryParts.push('Fahrzeug-Marke: ' + state.hersteller);
        if (state.basis) summaryParts.push('Wohnmobil-Fahrgestell: ' + state.basis);
        var modellText = WomosiState.modellEffectiveForApi();
        if (modellText) summaryParts.push('Fahrzeug-Modell: ' + modellText);
        if (state.year) summaryParts.push('Baujahr: ' + state.year);
        if (state.seatLabel) summaryParts.push('Sitz: ' + state.seatLabel);

        var existingMsg = (formData.get('message') || '').toString();
        var summaryText = summaryParts.join('\n');
        var body = summaryText;
        if (existingMsg.trim()) {
          body += '\n\n--- Nachricht / Hinweise ---\n' + existingMsg.trim();
        }
        formData.set('body', body);

        fetch('/sitzbezug-anfrage.php', {
          method: 'POST',
          body: formData
        })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && data.ok) {
              WomosiUI.showStatusModal(
                'success',
                'Vielen Dank!',
                'Ihre Anfrage ist eingegangen.\n\nWir werden uns schnellstmöglich mit Ihnen in Verbindung setzen.\n\nMit freundlichen Grüßen\nWomosi'
              );
              serieFallbackForm.reset();
              if (fallbackImagesInfo) {
                fallbackImagesInfo.textContent = 'Keine Dateien ausgewählt';
              }
            } else {
              WomosiUI.showStatusModal(
                'error',
                'Senden nicht möglich',
                (data && data.msg) || 'Die Anfrage konnte nicht gesendet werden.'
              );
            }
          })
          .catch(function () {
            WomosiUI.showStatusModal(
              'error',
              'Verbindungsfehler',
              'Es ist ein Fehler beim Senden der Anfrage aufgetreten.'
            );
          });
      });
    }

    if (toStep2Btn) {
      toStep2Btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!state.typKey) return;
        WomosiUI.setStep(2);
      });
    }
    if (backTo1Btn) {
      backTo1Btn.addEventListener('click', function (e) {
        e.preventDefault();
        WomosiUI.setStep(1);
      });
    }
    if (toStep3Btn) {
      toStep3Btn.addEventListener('click', function (e) {
        e.preventDefault();
        var v = validateStep2();
        if (!v.ok) return;
        if (state.segment === 'pkw') {
          WomosiUI.setStep(4);
          WomosiApi.loadSerie();
        } else {
          WomosiUI.setStep(3);
        }
      });
    }
    if (backTo2Btn) {
      backTo2Btn.addEventListener('click', function (e) {
        e.preventDefault();
        WomosiUI.setStep(2);
      });
    }
    if (toStep4Btn) {
      toStep4Btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!state.seatId) return;
        WomosiUI.setStep(4);
        WomosiApi.loadSerie();
      });
    }
    if (backTo3Btn) {
      backTo3Btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (state.segment === 'pkw') {
          WomosiUI.setStep(2);
        } else {
          WomosiUI.setStep(3);
        }
      });
    }
    if (finishBtn) {
      finishBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!state.serie) return;
        // Burada istersen seçilen Seri URL’sine gidebilirsin:
        // window.location.href = ...
      });
    }

    // ==== Başlangıç ====

    WomosiUI.resetPkwBaujahrSelect();

    // IMPORTANT: restore first, otherwise initial empty state overwrites cache.
    if (!WomosiState.restoreStateCache()) {
      WomosiState.clampSegmentToAllowed();
      WomosiUI.applySegmentToDom();
      if (state.segment === 'pkw') {
        WomosiApi.loadHersteller();
        WomosiUI.setStep(2);
      } else {
        WomosiUI.setStep(1);
      }
    }
    WomosiUI.updateSummary();
    WomosiUI.syncModellCustomRowVisibility();

    // Initial clear button visibility
    if (selHer) WomosiUI.setClearBtnVisible(selHer, !!selHer.value);
    if (selMod) WomosiUI.setClearBtnVisible(selMod, !!selMod.value);
    if (selBasis) WomosiUI.setClearBtnVisible(selBasis, !!selBasis.value);
    if (inpYear) WomosiUI.setClearBtnVisible(inpYear, !!inpYear.value);
    if (selYearPkw) WomosiUI.setClearBtnVisible(selYearPkw, !!selYearPkw.value);
    if (inpModCustom) WomosiUI.setClearBtnVisible(inpModCustom, !!inpModCustom.value);

    // İlk açılış tamamlandıktan sonra scroll'a izin ver.
    window.requestAnimationFrame(function () {
      isInitialPageLoad = false;
    });
  });
})();