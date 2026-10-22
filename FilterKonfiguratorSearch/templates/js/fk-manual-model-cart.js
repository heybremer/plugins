/**
 * Nur „Mein Modell ist nicht in der Liste“ + Freitext:
 * - buy_form: dynamischer Titel → Warenkorb cName (Wawi)
 * - #pushed-success
 * - Produktlisten: .productbox-title > a (z. B. HW1 → Kompaktzeile)
 */
(function () {
  'use strict';

  var FK_STATE_KEY = 'womosi_fk_state_v1';
  var FK_MODELL_CUSTOM = '__FK_MODELL_CUSTOM__';

  // Konfigurator bağlamı olmayan sayfada (seriden bağımsız gezinme): dinamik başlık uygulama, son seçim korunur.
  try {
    var _qs = window.location.search || '';
    var _path = window.location.pathname || '';
    var _hasCfgQs = /[?&](segment|typ|hersteller|basis|modell|baujahr|sitz|fkCustomModell|fkNichtInListe)=/i.test(_qs);
    var _hasFilterPath = _path.indexOf('__') !== -1;
    if (!_hasCfgQs && !_hasFilterPath) {
      return;
    }
  } catch (e) {}

  function compactLineFromStorage() {
    try {
      var raw = window.localStorage.getItem(FK_STATE_KEY);
      if (!raw) return '';
      var c = JSON.parse(raw);
      if (!c || typeof c !== 'object') return '';
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

  function isManualModelOnly() {
    try {
      var raw = window.localStorage.getItem(FK_STATE_KEY);
      if (!raw) return false;
      var c = JSON.parse(raw);
      if (!c || typeof c !== 'object') return false;
      var m = String(c.modell || '').trim();
      if (m === FK_MODELL_CUSTOM) return true;
      if (!m && String(c.modellCustom || '').trim() !== '') return true;
      return false;
    } catch (e) {
      return false;
    }
  }

  function removeOldHidden(form) {
    ['fk_womosi_manual', 'fk_womosi_dyn_title'].forEach(function (name) {
      var nodes = form.querySelectorAll('input[name="' + name + '"]');
      for (var i = 0; i < nodes.length; i++) nodes[i].remove();
    });
  }

  function addHidden(form, name, value) {
    var inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = name;
    inp.value = value;
    form.appendChild(inp);
  }

  function injectBuyFormFields() {
    if (!isManualModelOnly()) return;
    var line = compactLineFromStorage();
    if (!line) return;
    ['buy_form', 'buy_form-quickview'].forEach(function (id) {
      var form = document.getElementById(id);
      if (!form) return;
      removeOldHidden(form);
      addHidden(form, 'fk_womosi_manual', '1');
      addHidden(form, 'fk_womosi_dyn_title', line);
    });
  }

  function updatePushedSuccessTitle() {
    if (!isManualModelOnly()) return;
    var line = compactLineFromStorage();
    if (!line) return;
    var box = document.querySelector('#pushed-success .productbox-title.subheadline');
    if (!box) return;
    box.textContent = line;
    box.classList.add('fk-selection-h1');
  }

  /**
   * Kategori / Liste / Box: <div class="productbox-title"><a class="text-clamp-2">HW1</a></div>
   * .subheadline = anderer Zweck (z. B. Überschrift), nicht überschreiben.
   */
  function updateProductboxTitleLinks() {
    if (!isManualModelOnly()) return;
    var line = compactLineFromStorage();
    if (!line) return;
    var root =
      document.querySelector('main#main-wrapper') ||
      document.querySelector('main');
    if (!root) return;
    var wraps = root.querySelectorAll('.productbox-title');
    for (var i = 0; i < wraps.length; i++) {
      var wrap = wraps[i];
      if (wrap.classList.contains('subheadline')) continue;
      var a = wrap.querySelector('a.text-clamp-2') || wrap.querySelector('a[href]');
      if (!a) continue;
      a.textContent = line;
      a.setAttribute('title', line);
    }
  }

  function run() {
    injectBuyFormFields();
    updatePushedSuccessTitle();
    updateProductboxTitleLinks();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }

  // Nach „In den WK“-Redirect erscheint #pushed-success ggf. verzögert
  var pollN = 0;
  var poll = setInterval(function () {
    pollN++;
    updatePushedSuccessTitle();
    updateProductboxTitleLinks();
    if (document.querySelector('#pushed-success .productbox-title') || pollN > 30) {
      clearInterval(poll);
    }
  }, 200);

  document.addEventListener('submit', function (e) {
    var t = e.target;
    if (!t || !t.id) return;
    if (t.id !== 'buy_form' && t.id !== 'buy_form-quickview') return;
    injectBuyFormFields();
  }, true);

  // Unendliches Nachladen / spätes Rendering der Produktboxen
  (function observeMain() {
    var moRoot =
      document.querySelector('main#main-wrapper') ||
      document.querySelector('main');
    if (!moRoot || typeof MutationObserver === 'undefined') return;
    var timer;
    var mo = new MutationObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        updateProductboxTitleLinks();
      }, 100);
    });
    try {
      mo.observe(moRoot, { childList: true, subtree: true });
    } catch (e) {}
  })();
})();
