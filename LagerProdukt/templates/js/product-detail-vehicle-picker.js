(function () {
  'use strict';

  var STORAGE_KEY = 'lp_choice';

  function uniqueValues(rows) {
    var seen = Object.create(null);
    return (Array.isArray(rows) ? rows : []).map(function (row) {
      return String(row && (row.value || row.name) || '').trim();
    }).filter(function (value) {
      if (!value || seen[value]) return false;
      seen[value] = true;
      return true;
    });
  }

  function fill(select, values, placeholder) {
    select.innerHTML = '';
    var empty = document.createElement('option');
    empty.value = '';
    empty.textContent = placeholder;
    select.appendChild(empty);
    values.forEach(function (value) {
      var option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });
  }

  function fetchJson(endpoint, params) {
    var query = new URLSearchParams(params);
    return fetch(endpoint + '?' + query.toString(), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    });
  }

  function seriesCode(value) {
    return String(value || '')
      .replace(/\bserie\b/ig, '')
      .replace(/[^0-9a-z]/ig, '')
      .toUpperCase();
  }

  function readStoredChoice() {
    try {
      var raw = window.sessionStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function writeStoredChoice(data) {
    try {
      window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch (e) {}
  }

  function queryParam(name) {
    try {
      return String(new URLSearchParams(window.location.search).get(name) || '').trim();
    } catch (e) {
      return '';
    }
  }

  function titleNodes() {
    return document.querySelectorAll('h1.product-title.h2, h1.product-title, .product-title.h2');
  }

  function addHidden(form, name, value) {
    var existing = form.querySelector('input[name="' + name + '"]');
    if (existing) {
      existing.value = value;
      return;
    }
    var inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = name;
    inp.value = value;
    form.appendChild(inp);
  }

  function injectCartTitle(title) {
    ['buy_form', 'buy_form-quickview'].forEach(function (id) {
      var form = document.getElementById(id);
      if (!form) return;
      addHidden(form, 'lp_dyn_title', title);
      addHidden(form, 'fk_womosi_manual', '1');
      addHidden(form, 'fk_womosi_dyn_title', title);
    });
    var box = document.querySelector('#pushed-success .productbox-title.subheadline');
    if (box) {
      box.textContent = title;
    }
  }

  function applyPageTitle(title, original) {
    titleNodes().forEach(function (node) {
      if (!node.getAttribute('data-lp-original-title')) {
        node.setAttribute('data-lp-original-title', original || node.textContent || '');
      }
      node.textContent = title;
    });
  }

  function restorePageTitle() {
    titleNodes().forEach(function (node) {
      var original = node.getAttribute('data-lp-original-title');
      if (original) {
        node.textContent = original;
      }
    });
  }

  function init(root) {
    document.body.classList.add('lp-lager-detail');
    var endpoint = root.getAttribute('data-endpoint') || '/fk-search';
    var groupName = String(root.getAttribute('data-series') || '').trim();
    var currentSeries = seriesCode(groupName);
    var productName = String(root.getAttribute('data-product-name') || '').trim();
    var defaultSeats = [
      { code: '3', name: 'ISRI Captain Chair' },
      { code: '5', name: 'ISRI Ducato / Jumper / Boxer, Bj. 2006–2014' },
      { code: '4', name: 'ISRI CC mit Gurt' },
      { code: '7', name: 'Aguti GIS-Liner' },
      { code: '6', name: 'Aguti Milan' },
      { code: '2', name: 'Aguti Liner 12' },
      { code: '1', name: 'Aguti Milan 12' },
      { code: '9', name: 'Ford Transit ab Bj. 2014' },
      { code: '10', name: 'Ford Transit ab Bj. 2014 mit integrierter Kopfstütze' },
      { code: '14', name: 'Mercedes-Benz Sprinter / Hymer' },
      { code: '8', name: 'Aguti GIS-Liner 3' },
      { code: '11', name: 'Aguti Milan 3' },
      { code: '12', name: 'SKA 1800 M23 Komfort' },
      { code: '13', name: 'SKA 1800/1000 M23 Classic' }
    ];
    var seats = [];
    try {
      seats = JSON.parse(root.getAttribute('data-seats') || '[]');
    } catch (e) {
      seats = [];
    }
    if (!Array.isArray(seats) || !seats.length) {
      seats = defaultSeats;
    }
    var stored = readStoredChoice() || {};
    var selectedSeat = String(root.getAttribute('data-selected-seat') || queryParam('sitz') || stored.seat || '').trim();
    var selectedBrand = String(queryParam('hersteller') || stored.brand || '').trim();
    var selectedBasis = String(queryParam('basis') || stored.basis || '').trim();
    var selectedModel = String(queryParam('modell') || stored.model || '').trim();
    var seat = root.querySelector('[data-lpvp="sitz"]');
    var brand = root.querySelector('[data-lpvp="hersteller"]');
    var basis = root.querySelector('[data-lpvp="basis"]');
    var model = root.querySelector('[data-lpvp="modell"]');
    var result = root.querySelector('[data-lpvp-result]');
    var message = root.querySelector('[data-lpvp-message]');
    var link = root.querySelector('[data-lpvp-link]');
    var requestId = 0;
    var originalTitle = '';
    var titleNode = document.querySelector('h1.product-title.h2, h1.product-title, .product-title.h2');
    if (titleNode) {
      originalTitle = titleNode.textContent || '';
    }
    if (!productName) {
      productName = originalTitle;
    }

    function basisValue() {
      return basis ? basis.value : '';
    }

    function basisReady() {
      return !basis || !!basis.value || basis.options.length <= 1;
    }

    function isComplete() {
      return !!(seat.value && brand.value && basisReady() && model.value);
    }

    function persistChoice() {
      writeStoredChoice({
        seat: seat.value,
        seatName: seatName(),
        brand: brand.value,
        basis: basisValue(),
        model: model.value,
        series: currentSeries,
        title: buildTitle()
      });
    }

    function applyCurrentTitle() {
      if (!isComplete()) {
        restorePageTitle();
        return '';
      }
      var title = buildTitle();
      applyPageTitle(title, originalTitle);
      injectCartTitle(title);
      persistChoice();
      return title;
    }

    function decorateVariantLinks() {
      document.querySelectorAll('#lp-detail-variants a[href]').forEach(function (anchor) {
        try {
          var url = new URL(anchor.getAttribute('href'), window.location.origin);
          if (seat.value) {
            url.searchParams.set('sitz', seat.value);
          } else {
            url.searchParams.delete('sitz');
          }
          if (brand.value) {
            url.searchParams.set('hersteller', brand.value);
          } else {
            url.searchParams.delete('hersteller');
          }
          if (basisValue()) {
            url.searchParams.set('basis', basisValue());
          } else {
            url.searchParams.delete('basis');
          }
          if (model.value) {
            url.searchParams.set('modell', model.value);
          } else {
            url.searchParams.delete('modell');
          }
          anchor.setAttribute('href', url.pathname + url.search + url.hash);
        } catch (e) {}
      });
    }

    function resetResult() {
      result.hidden = true;
      result.classList.remove('is-warning');
      message.textContent = '';
      link.hidden = true;
      link.removeAttribute('href');
      if (!isComplete()) {
        restorePageTitle();
      }
    }

    function seriesParam() {
      return currentSeries || groupName;
    }

    function seatName() {
      var selected = seats.find(function (item) {
        return String(item.code) === String(seat.value);
      });
      return selected ? selected.name : (stored.seatName || seat.value);
    }

    function buildTitle() {
      var prefix = productName;
      if (prefix && !/serie/i.test(prefix)) {
        prefix += ' Serie';
      }
      return [prefix, seatName(), brand.value, model.value].filter(Boolean).join(' ');
    }

    function seriesVariants() {
      var code = seriesParam();
      var raw = groupName;
      var list = [];
      [code ? (code + '-Serie') : '', code, raw, code ? ('Serie ' + code) : '', raw ? raw.replace(/\s+/g, '') : ''].forEach(function (value) {
        var item = String(value || '').trim();
        if (item && list.indexOf(item) === -1) {
          list.push(item);
        }
      });
      return list;
    }

    function seatVariants() {
      var list = [];
      [seat.value, seatName(), stored.seat, stored.seatName].forEach(function (value) {
        var item = String(value || '').trim();
        if (item && list.indexOf(item) === -1) {
          list.push(item);
        }
      });
      return list;
    }

    function load(select, params, placeholder) {
      select.disabled = true;
      fill(select, [], 'Wird geladen…');
      return fetchJson(endpoint, params).then(function (rows) {
        var values = uniqueValues(rows);
        fill(select, values, values.length ? placeholder : 'Keine Auswahl verfügbar');
        select.disabled = values.length === 0;
        return values;
      }).catch(function () {
        fill(select, [], 'Auswahl nicht verfügbar');
        select.disabled = true;
        return [];
      });
    }

    function loadFirst(select, attempts, placeholder) {
      var chain = Promise.resolve([]);
      attempts.forEach(function (params) {
        chain = chain.then(function (values) {
          if (values.length) {
            return values;
          }
          return load(select, params, placeholder);
        });
      });
      return chain;
    }

    function keepValue(select, preferred) {
      var value = String(preferred || '').trim();
      if (!value) return false;
      var found = Array.prototype.some.call(select.options, function (option) {
        return option.value === value;
      });
      if (!found) {
        var extra = document.createElement('option');
        extra.value = value;
        extra.textContent = value;
        select.appendChild(extra);
        select.disabled = false;
      }
      select.value = value;
      return true;
    }

    function afterSelectionChange() {
      persistChoice();
      decorateVariantLinks();
      if (isComplete()) {
        applyCurrentTitle();
      }
    }

    function withBasis(params) {
      if (basisValue()) {
        params.basis = basisValue();
      }
      return params;
    }

    function loadModels(preferredModel) {
      var waiting = !brand.value ? 'Zuerst Marke wählen' : (!basisReady() ? 'Zuerst Fahrgestell wählen' : '');
      fill(model, [], waiting || 'Wird geladen…');
      model.disabled = true;
      if (waiting) {
        afterSelectionChange();
        return Promise.resolve([]);
      }
      var attempts = [];
      seatVariants().forEach(function (sitz) {
        seriesVariants().forEach(function (serie) {
          attempts.push(withBasis({
            mode: 'options',
            field: 'modell',
            sitz: sitz,
            serie: serie,
            hersteller: brand.value
          }));
        });
      });
      seriesVariants().forEach(function (serie) {
        attempts.push(withBasis({
          mode: 'options',
          field: 'modell',
          serie: serie,
          hersteller: brand.value
        }));
      });
      attempts.push(withBasis({
        mode: 'options',
        field: 'modell',
        hersteller: brand.value
      }));
      return loadFirst(model, attempts, 'Bitte wählen').then(function (values) {
        keepValue(model, preferredModel);
        afterSelectionChange();
        if (isComplete()) {
          checkCompatibility();
        }
        return values;
      });
    }

    function loadBasis(preferredBasis, preferredModel) {
      fill(model, [], 'Zuerst Fahrgestell wählen');
      model.disabled = true;
      if (!basis) {
        return loadModels(preferredModel);
      }
      if (!brand.value) {
        fill(basis, [], 'Zuerst Marke wählen');
        basis.disabled = true;
        afterSelectionChange();
        return Promise.resolve([]);
      }
      var attempts = [];
      seatVariants().forEach(function (sitz) {
        seriesVariants().forEach(function (serie) {
          attempts.push({
            mode: 'options',
            field: 'basis',
            sitz: sitz,
            serie: serie,
            hersteller: brand.value
          });
        });
      });
      seriesVariants().forEach(function (serie) {
        attempts.push({
          mode: 'options',
          field: 'basis',
          serie: serie,
          hersteller: brand.value
        });
      });
      attempts.push({
        mode: 'options',
        field: 'basis',
        hersteller: brand.value
      });
      return loadFirst(basis, attempts, 'Bitte wählen').then(function (values) {
        if (!values.length) {
          fill(basis, [], 'Nicht erforderlich');
          basis.disabled = true;
        } else if (!keepValue(basis, preferredBasis) && values.length === 1) {
          basis.value = values[0];
        }
        afterSelectionChange();
        return loadModels(preferredModel);
      });
    }

    function loadBrands(preferredBrand, preferredModel, preferredBasis) {
      fill(model, [], 'Zuerst Marke wählen');
      model.disabled = true;
      if (basis) {
        fill(basis, [], 'Zuerst Marke wählen');
        basis.disabled = true;
      }
      if (!seat.value) {
        fill(brand, [], 'Zuerst Sitztyp wählen');
        brand.disabled = true;
        afterSelectionChange();
        return Promise.resolve([]);
      }
      var attempts = [];
      seatVariants().forEach(function (sitz) {
        seriesVariants().forEach(function (serie) {
          attempts.push({
            mode: 'options',
            field: 'hersteller',
            sitz: sitz,
            serie: serie
          });
        });
      });
      seriesVariants().forEach(function (serie) {
        attempts.push({
          mode: 'options',
          field: 'hersteller',
          serie: serie
        });
      });
      attempts.push({ mode: 'options', field: 'hersteller' });
      return loadFirst(brand, attempts, 'Bitte wählen').then(function (values) {
        keepValue(brand, preferredBrand);
        afterSelectionChange();
        if (brand.value) {
          return loadBasis(preferredBasis, preferredModel);
        }
        return values;
      });
    }

    function checkCompatibility() {
      var id = ++requestId;
      resetResult();
      if (!isComplete()) return;
      message.textContent = 'Kompatibilität wird geprüft…';
      result.hidden = false;
      fetchJson(endpoint, withBasis({
        sitz: seat.value,
        serie: seriesParam(),
        hersteller: brand.value,
        modell: model.value
      })).then(function (rows) {
        if (id !== requestId) return;
        var items = Array.isArray(rows) ? rows : [];
        var matching = items.find(function (item) {
          return seriesCode(item && item.serie) === currentSeries;
        });
        if (!matching) {
          return fetchJson(endpoint, withBasis({
            sitz: seatName(),
            serie: seriesParam(),
            hersteller: brand.value,
            modell: model.value
          })).then(function (altRows) {
            return { items: Array.isArray(altRows) ? altRows : items };
          });
        }
        return { items: items, matching: matching };
      }).then(function (payload) {
        if (id !== requestId || !payload) return;
        var items = payload.items || [];
        var matching = payload.matching || items.find(function (item) {
          return seriesCode(item && item.serie) === currentSeries;
        });
        var target = matching || items[0];
        if (matching) {
          applyCurrentTitle();
          message.textContent = '✓ Dieser Bezug passt zu Ihrem Fahrzeug.';
        } else {
          result.classList.add('is-warning');
          message.textContent = items.length
            ? 'Andere Serien passen zu Ihrem Fahrzeug.'
            : 'Keine passende Serie gefunden.';
        }
        if (target && target.url) {
          link.href = String(target.url);
          link.textContent = matching ? 'Passende Auswahl öffnen' : 'Passende Produkte anzeigen';
          link.hidden = false;
        }
      }).catch(function () {
        if (id !== requestId) return;
        result.classList.add('is-warning');
        message.textContent = 'Prüfung momentan nicht möglich.';
      });
    }

    fill(seat, seats.map(function (item) {
      return item.code;
    }), 'Bitte wählen');
    Array.prototype.forEach.call(seat.options, function (option) {
      var meta = seats.find(function (item) {
        return String(item.code) === String(option.value);
      });
      if (meta) {
        option.textContent = meta.name;
      }
    });
    if (selectedSeat) {
      var known = seats.some(function (item) { return String(item.code) === String(selectedSeat); });
      if (!known) {
        var extra = document.createElement('option');
        extra.value = selectedSeat;
        extra.textContent = stored.seatName || selectedSeat;
        seat.appendChild(extra);
      }
      seat.value = selectedSeat;
    } else if (seats.length === 1) {
      seat.value = seats[0].code;
    }
    if (seat.value) {
      loadBrands(selectedBrand, selectedModel, selectedBasis);
    } else {
      decorateVariantLinks();
    }

    seat.addEventListener('change', function () {
      persistChoice();
      decorateVariantLinks();
      resetResult();
      loadBrands(brand.value, model.value, basisValue());
    });

    brand.addEventListener('change', function () {
      persistChoice();
      decorateVariantLinks();
      resetResult();
      loadBasis('', '');
    });

    if (basis) {
      basis.addEventListener('change', function () {
        persistChoice();
        decorateVariantLinks();
        resetResult();
        loadModels('');
      });
    }

    model.addEventListener('change', function () {
      afterSelectionChange();
      checkCompatibility();
    });

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!form || (form.id !== 'buy_form' && form.id !== 'buy_form-quickview')) {
        return;
      }
      var storedTitle = (readStoredChoice() || {}).title || buildTitle();
      if (isComplete() && storedTitle) {
        injectCartTitle(storedTitle);
      }
    });

    decorateVariantLinks();
  }

  function boot() {
    document.querySelectorAll('#lp-vehicle-picker').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}());
