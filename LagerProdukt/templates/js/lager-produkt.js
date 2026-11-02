(function () {
  var input = document.getElementById('lpSearch');
  var catalog = document.getElementById('lpCatalog');
  var count = document.getElementById('lpCount');
  var none = document.getElementById('lpNone');
  var sort = document.getElementById('lpSort');
  var chips = document.getElementById('lpChips');
  var seriesNav = document.getElementById('lpSeriesNav');
  var seatStart = document.getElementById('lpSeatStart');
  var seatGrid = document.getElementById('lpSeatGrid');
  var seatHint = document.getElementById('lpSeatHint');
  var seatSelected = document.getElementById('lpSeatSelected');
  var seatSelectedImg = document.getElementById('lpSeatSelectedImg');
  var seatSelectedName = document.getElementById('lpSeatSelectedName');
  var seatChange = document.getElementById('lpSeatChange');
  var inquiry = document.getElementById('lpSeatInquiry');
  var inquirySeatName = document.getElementById('lpInquirySeatName');
  var inquiryForm = document.getElementById('lpInquiryForm');
  var inquiryTrigger = document.getElementById('lpInquiryImagesTrigger');
  var inquiryInfo = document.getElementById('lpInquiryImagesInfo');
  var inquiryImages = document.getElementById('lp_fallback_images');
  var statusOverlay = document.getElementById('lpStatusOverlay');
  var statusIcon = document.getElementById('lpStatusIcon');
  var statusTitle = document.getElementById('lpStatusTitle');
  var statusMessage = document.getElementById('lpStatusMessage');
  var statusClose = document.getElementById('lpStatusClose');
  if (!catalog) {
    return;
  }

  var products = Array.prototype.slice.call(catalog.querySelectorAll('.lp-product'));
  var sections = Array.prototype.slice.call(catalog.querySelectorAll('.lp-group'));
  var total = products.length;
  var activeGroup = 'all';
  var requireSeat = !!(seatStart && seatStart.getAttribute('data-require-seat') === '1');
  var activeSeat = '';
  var STORAGE_KEY = 'lp_choice';
  var VEHICLE_KEY = 'lp_vehicle';
  var vehicleSuffix = '';

  function displayName(name) {
    return vehicleSuffix ? name + ' – ' + vehicleSuffix : name;
  }

  function norm(value) {
    return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function selectVariant(product, variant) {
    var card = product.querySelector('.lp-card');
    if (!card || !variant) {
      return;
    }
    var name = variant.getAttribute('data-name') || '';
    var artNr = variant.getAttribute('data-artnr') || '';
    card.href = variant.getAttribute('data-url') || card.href;
    card.setAttribute('data-name', name);
    card.setAttribute('data-artnr', artNr);
    card.setAttribute('data-price', variant.getAttribute('data-price') || '0');

    var image = card.querySelector('.lp-main-image');
    var nameNode = card.querySelector('.lp-name');
    var artNrNode = card.querySelector('.lp-artnr');
    var priceNode = card.querySelector('.lp-price');
    if (image) {
      image.src = variant.getAttribute('data-img') || image.src;
      image.alt = name;
    }
    if (nameNode) {
      nameNode.textContent = displayName(name);
    }
    if (artNrNode) {
      artNrNode.textContent = 'Art.-Nr. ' + artNr;
    }
    if (!variant.getAttribute('data-base-url')) {
      variant.setAttribute('data-base-url', variant.getAttribute('data-url') || '');
    }
    card.href = withSeat(variant.getAttribute('data-base-url') || card.href, activeSeat);
    if (priceNode) {
      priceNode.textContent = variant.getAttribute('data-price-f') || '';
    }
    Array.prototype.forEach.call(product.querySelectorAll('.lp-variant'), function (button) {
      var active = button === variant;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function searchableText(product) {
    var values = [];
    var card = product.querySelector('.lp-card');
    if (card) {
      values.push(card.getAttribute('data-name'), card.getAttribute('data-artnr'));
    }
    Array.prototype.forEach.call(product.querySelectorAll('.lp-variant'), function (variant) {
      values.push(variant.getAttribute('data-name'), variant.getAttribute('data-artnr'));
    });
    return norm(values.join(' '));
  }

  function storedChoice() {
    try {
      return JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function withSeat(url, seat) {
    if (!url) {
      return url;
    }
    try {
      var parsed = new URL(url, window.location.origin);
      var stored = storedChoice();
      if (seat) {
        parsed.searchParams.set('sitz', seat);
      } else {
        parsed.searchParams.delete('sitz');
      }
      if (stored.brand) {
        parsed.searchParams.set('hersteller', stored.brand);
      }
      if (stored.model) {
        parsed.searchParams.set('modell', stored.model);
      }
      if (vehicleSuffix) {
        parsed.searchParams.set('fahrzeug', vehicleSuffix);
      } else {
        parsed.searchParams.delete('fahrzeug');
      }
      return parsed.pathname + parsed.search + parsed.hash;
    } catch (e) {
      return url;
    }
  }

  function persistChoice(seat, seatName) {
    var prev = storedChoice();
    try {
      window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
        seat: seat || '',
        seatName: seatName || '',
        brand: seat ? (prev.brand || '') : '',
        basis: seat ? (prev.basis || '') : '',
        model: seat ? (prev.model || '') : '',
        series: prev.series || '',
        title: prev.title || ''
      }));
    } catch (e) {}
  }

  function decorateProductLinks() {
    products.forEach(function (product) {
      var card = product.querySelector('.lp-card');
      if (card) {
        if (!card.getAttribute('data-base-href')) {
          card.setAttribute('data-base-href', card.getAttribute('href') || '');
        }
        card.href = withSeat(card.getAttribute('data-base-href'), activeSeat);
      }
      Array.prototype.forEach.call(product.querySelectorAll('.lp-variant'), function (variant) {
        if (!variant.getAttribute('data-base-url')) {
          variant.setAttribute('data-base-url', variant.getAttribute('data-url') || '');
        }
        variant.setAttribute('data-url', withSeat(variant.getAttribute('data-base-url'), activeSeat));
      });
    });
  }

  function hasSeat(node, seat) {
    if (!seat) {
      return true;
    }
    var seats = String(node.getAttribute('data-seats') || '').split(',');
    return seats.indexOf(seat) !== -1;
  }

  function seatHasSeries(tile) {
    return String((tile && tile.getAttribute('data-groups')) || '').trim() !== '';
  }

  function showInquiry(show, seatName) {
    if (!inquiry) {
      return;
    }
    inquiry.hidden = !show;
    inquiry.classList.toggle('hidden', !show);
    if (inquirySeatName) {
      inquirySeatName.textContent = seatName || '';
    }
    if (catalog) {
      catalog.classList.toggle('is-inquiry', !!show);
    }
    if (seriesNav) {
      seriesNav.classList.toggle('is-inquiry', !!show);
    }
    if (none && show) {
      none.hidden = true;
    }
  }

  function showStatus(ok, title, message) {
    if (!statusOverlay) {
      window.alert(message);
      return;
    }
    if (statusIcon) {
      statusIcon.textContent = ok ? '✓' : '!';
    }
    if (statusTitle) {
      statusTitle.textContent = title;
    }
    if (statusMessage) {
      statusMessage.textContent = message;
    }
    statusOverlay.classList.toggle('is-error', !ok);
    var modal = statusOverlay.querySelector('.lp-status-modal');
    if (modal) {
      modal.classList.toggle('is-error', !ok);
    }
    statusOverlay.hidden = false;
    statusOverlay.classList.remove('hidden');
  }

  function hideStatus() {
    if (!statusOverlay) {
      return;
    }
    statusOverlay.hidden = true;
    statusOverlay.classList.add('hidden');
  }

  function setSeatUi(selected, unmatched) {
    if (!requireSeat) {
      return;
    }
    var hideCatalog = !selected || !!unmatched;
    catalog.classList.toggle('is-waiting-seat', hideCatalog && !unmatched);
    catalog.classList.toggle('is-inquiry', !!unmatched);
    if (seriesNav) {
      seriesNav.classList.toggle('is-waiting-seat', hideCatalog && !unmatched);
      seriesNav.classList.toggle('is-inquiry', !!unmatched);
    }
    if (seatGrid) {
      seatGrid.classList.toggle('hidden', !!selected);
    }
    if (seatHint) {
      seatHint.classList.toggle('hidden', !!selected);
    }
    if (seatSelected) {
      seatSelected.classList.toggle('hidden', !selected);
    }
    if (seatStart) {
      seatStart.classList.toggle('has-selection', !!selected);
      seatStart.classList.toggle('has-inquiry', !!unmatched);
    }
    showInquiry(!!unmatched, seatSelectedName ? seatSelectedName.textContent : '');
  }

  function selectSeat(tile) {
    activeSeat = String(tile.getAttribute('data-seat') || '');
    activeGroup = 'all';
    if (chips) {
      Array.prototype.forEach.call(chips.querySelectorAll('.lp-chip'), function (chip) {
        chip.classList.toggle('is-active', chip.getAttribute('data-group') === 'all');
      });
    }
    if (seatSelectedImg) {
      seatSelectedImg.src = tile.getAttribute('data-img') || '';
      seatSelectedImg.alt = tile.getAttribute('data-name') || '';
    }
    if (seatSelectedName) {
      seatSelectedName.textContent = tile.getAttribute('data-name') || '';
    }
    if (seatGrid) {
      Array.prototype.forEach.call(seatGrid.querySelectorAll('.lp-seat-tile'), function (button) {
        button.classList.toggle('is-active', button === tile);
      });
    }
    persistChoice(activeSeat, tile.getAttribute('data-name') || '');
    decorateProductLinks();
    setSeatUi(true, !seatHasSeries(tile));
    apply();
    var target = (!seatHasSeries(tile) && inquiry) ? inquiry : catalog;
    if (target) {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function resetSeat() {
    activeSeat = '';
    activeGroup = 'all';
    if (seatGrid) {
      Array.prototype.forEach.call(seatGrid.querySelectorAll('.lp-seat-tile'), function (button) {
        button.classList.remove('is-active');
      });
    }
    if (chips) {
      Array.prototype.forEach.call(chips.querySelectorAll('.lp-chip'), function (chip) {
        chip.classList.toggle('is-active', chip.getAttribute('data-group') === 'all');
      });
    }
    persistChoice('', '');
    decorateProductLinks();
    setSeatUi(false, false);
    apply();
    if (seatStart) {
      seatStart.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function apply() {
    var query = input ? norm(input.value) : '';
    var shown = 0;
    var waiting = requireSeat && !activeSeat;
    products.forEach(function (product) {
      var card = product.querySelector('.lp-card');
      if (card) {
        card.classList.remove('is-hidden');
      }
      var groupId = String(product.getAttribute('data-group') || '0');
      var matches = query === '' || searchableText(product).indexOf(query) !== -1;
      var inGroup = activeGroup === 'all' || groupId === activeGroup;
      var inSeat = hasSeat(product, activeSeat);
      var visible = !waiting && matches && inGroup && inSeat;
      product.classList.toggle('is-hidden', !visible);
      if (visible) {
        shown += 1;
      }
    });
    sections.forEach(function (section) {
      var groupVisible = !waiting && hasSeat(section, activeSeat)
        && section.querySelectorAll('.lp-product:not(.is-hidden)').length > 0;
      section.hidden = !groupVisible;
    });
    if (chips) {
      Array.prototype.forEach.call(chips.querySelectorAll('.lp-chip'), function (chip) {
        var groupId = String(chip.getAttribute('data-group') || 'all');
        chip.hidden = waiting || (groupId !== 'all' && !hasSeat(chip, activeSeat));
      });
    }
    if (count) {
      count.textContent = waiting
        ? total + ' Treffer'
        : (shown === total && query === '' && activeGroup === 'all' && !activeSeat
          ? shown + ' Treffer'
          : shown + ' / ' + total + ' Treffer');
    }
    if (none) {
      none.hidden = waiting || shown > 0 || (inquiry && !inquiry.hidden);
    }
  }

  catalog.addEventListener('pointerover', function (event) {
    var card = event.target.closest('a.lp-card');
    if (!card || card.getAttribute('data-lp-prefetch') === '1') {
      return;
    }
    var href = card.getAttribute('href') || '';
    if (href === '') {
      return;
    }
    card.setAttribute('data-lp-prefetch', '1');
    var link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = href;
    document.head.appendChild(link);
  });

  catalog.addEventListener('click', function (event) {
    var variant = event.target.closest('.lp-variant');
    if (!variant) {
      return;
    }
    selectVariant(variant.closest('.lp-product'), variant);
  });

  if (input) {
    input.addEventListener('input', apply);
  }

  if (seatGrid) {
    seatGrid.addEventListener('click', function (event) {
      var tile = event.target.closest('.lp-seat-tile');
      if (!tile) {
        return;
      }
      selectSeat(tile);
    });
  }

  if (seatChange) {
    seatChange.addEventListener('click', resetSeat);
  }

  if (chips) {
    chips.addEventListener('click', function (event) {
      var button = event.target.closest('.lp-chip');
      if (!button) {
        return;
      }
      activeGroup = String(button.getAttribute('data-group') || 'all');
      Array.prototype.forEach.call(chips.querySelectorAll('.lp-chip'), function (chip) {
        chip.classList.toggle('is-active', chip === button);
      });
      apply();
    });
  }

  if (sort) {
    sort.addEventListener('change', function () {
      var mode = sort.value;
      sections.forEach(function (section) {
        var grid = section.querySelector('.lp-grid');
        if (!grid) {
          return;
        }
        var list = Array.prototype.slice.call(grid.querySelectorAll('.lp-product'));
        list.sort(function (a, b) {
          var cardA = a.querySelector('.lp-card');
          var cardB = b.querySelector('.lp-card');
          if (mode === 'name') {
            return norm(cardA.getAttribute('data-name')).localeCompare(norm(cardB.getAttribute('data-name')), 'de');
          }
          if (mode === 'price-asc' || mode === 'price-desc') {
            var diff = parseFloat(cardA.getAttribute('data-price') || '0') -
              parseFloat(cardB.getAttribute('data-price') || '0');
            return mode === 'price-desc' ? -diff : diff;
          }
          return parseInt(a.getAttribute('data-order') || '0', 10) -
            parseInt(b.getAttribute('data-order') || '0', 10);
        });
        list.forEach(function (product) {
          grid.appendChild(product);
        });
      });
      var groupedSections = sections.filter(function (section) {
        return section.getAttribute('data-group') !== '0';
      });
      var otherSections = sections.filter(function (section) {
        return section.getAttribute('data-group') === '0';
      });
      groupedSections.sort(function (a, b) {
        var cardA = a.querySelector('.lp-card');
        var cardB = b.querySelector('.lp-card');
        if (mode === 'name') {
          return norm(cardA.getAttribute('data-name')).localeCompare(norm(cardB.getAttribute('data-name')), 'de');
        }
        if (mode === 'price-asc' || mode === 'price-desc') {
          var diff = parseFloat(cardA.getAttribute('data-price') || '0') -
            parseFloat(cardB.getAttribute('data-price') || '0');
          return mode === 'price-desc' ? -diff : diff;
        }
        return parseInt(a.getAttribute('data-order') || '0', 10) -
          parseInt(b.getAttribute('data-order') || '0', 10);
      });
      groupedSections.concat(otherSections).forEach(function (section) {
        catalog.appendChild(section);
      });
    });
  }

  if (inquiryTrigger && inquiryImages) {
    inquiryTrigger.addEventListener('click', function () {
      inquiryImages.click();
    });
    inquiryImages.addEventListener('change', function () {
      var files = inquiryImages.files || [];
      if (!inquiryInfo) {
        return;
      }
      if (!files.length) {
        inquiryInfo.textContent = 'Keine Dateien ausgewählt';
      } else if (files.length === 1) {
        inquiryInfo.textContent = files[0].name;
      } else {
        inquiryInfo.textContent = files.length + ' Dateien ausgewählt';
      }
    });
  }

  if (inquiryForm) {
    inquiryForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var submit = inquiryForm.querySelector('.lp-inquiry-submit');
      var formData = new FormData(inquiryForm);
      var seatName = inquirySeatName ? inquirySeatName.textContent.trim() : '';
      var existing = String(formData.get('message') || '').trim();
      var body = 'Quelle: Lagerprodukte\nSitz: ' + (seatName || activeSeat || 'unbekannt');
      if (existing) {
        body += '\n\n--- Nachricht / Hinweise ---\n' + existing;
      }
      formData.set('body', body);
      if (submit) {
        submit.disabled = true;
      }
      fetch('/fk-anfrage', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      }).then(function (response) {
        return response.text().then(function (text) {
          var parsed = null;
          try {
            parsed = JSON.parse(text);
          } catch (e) {
            parsed = null;
          }
          if (!parsed) {
            throw new Error('invalid-json');
          }
          return parsed;
        });
      }).then(function (data) {
        if (data && data.ok) {
          showStatus(
            true,
            'Vielen Dank!',
            'Ihre Anfrage ist eingegangen.\n\nWir werden uns schnellstmöglich mit Ihnen in Verbindung setzen.\n\nMit freundlichen Grüßen\nWomosi'
          );
          inquiryForm.reset();
          if (inquiryInfo) {
            inquiryInfo.textContent = 'Keine Dateien ausgewählt';
          }
        } else {
          showStatus(false, 'Senden nicht möglich', (data && data.msg) || 'Die Anfrage konnte nicht gesendet werden.');
        }
      }).catch(function () {
        showStatus(false, 'Verbindungsfehler', 'Es ist ein Fehler beim Senden der Anfrage aufgetreten.');
      }).then(function () {
        if (submit) {
          submit.disabled = false;
        }
      });
    });
  }

  if (statusClose) {
    statusClose.addEventListener('click', hideStatus);
  }
  if (statusOverlay) {
    statusOverlay.addEventListener('click', function (event) {
      if (event.target === statusOverlay) {
        hideStatus();
      }
    });
  }

  /* PKW-Seiten: Marke → Modell → Baujahr. Produkte bleiben unverändert, nur der Titel ändert sich. */
  function refreshTitles() {
    products.forEach(function (product) {
      var card = product.querySelector('.lp-card');
      var nameNode = card && card.querySelector('.lp-name');
      if (nameNode) {
        nameNode.textContent = displayName(card.getAttribute('data-name') || '');
      }
    });
    decorateProductLinks();
  }

  function initVehicle() {
    var root = document.getElementById('lpVehicle');
    if (!root) {
      return;
    }
    var endpoint = root.getAttribute('data-endpoint') || '/fk-search';
    var brand = document.getElementById('lpVehBrand');
    var model = document.getElementById('lpVehModel');
    var year = document.getElementById('lpVehYear');
    var reset = document.getElementById('lpVehicleReset');
    var summary = document.getElementById('lpVehicleSummary');
    if (!brand || !model || !year) {
      return;
    }

    function fetchOptions(field, extra) {
      var params = { mode: 'options', field: field, segment: 'pkw' };
      Object.keys(extra || {}).forEach(function (key) {
        if (extra[key]) {
          params[key] = extra[key];
        }
      });
      var query = Object.keys(params).map(function (key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
      }).join('&');
      return fetch(endpoint + '?' + query, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      }).then(function (response) {
        return response.json();
      }).then(function (rows) {
        var seen = {};
        var values = [];
        (Array.isArray(rows) ? rows : []).forEach(function (row) {
          var value = String((row && row.value) || '').trim();
          if (value && !seen[value]) {
            seen[value] = true;
            values.push(value);
          }
        });
        values.sort(function (a, b) {
          return a.localeCompare(b, 'de', { numeric: true, sensitivity: 'base' });
        });
        return values;
      }).catch(function () {
        return [];
      });
    }

    function fill(select, values, placeholder, keep) {
      select.innerHTML = '';
      var first = document.createElement('option');
      first.value = '';
      first.textContent = values.length ? 'Bitte wählen' : placeholder;
      select.appendChild(first);
      values.forEach(function (value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        select.appendChild(option);
      });
      select.disabled = values.length === 0;
      if (keep && values.indexOf(keep) !== -1) {
        select.value = keep;
      }
    }

    function update() {
      vehicleSuffix = [brand.value, model.value, year.value].filter(Boolean).join(' ');
      refreshTitles();
      if (summary) {
        summary.hidden = vehicleSuffix === '';
        summary.textContent = vehicleSuffix ? 'Ihr Fahrzeug: ' + vehicleSuffix : '';
      }
      if (reset) {
        reset.hidden = vehicleSuffix === '';
      }
      try {
        window.sessionStorage.setItem(VEHICLE_KEY, JSON.stringify({
          brand: brand.value, model: model.value, year: year.value
        }));
      } catch (e) {}
    }

    function loadYears(keep) {
      if (!brand.value || !model.value) {
        fill(year, [], 'Zuerst Modell wählen');
        update();
        return Promise.resolve();
      }
      fill(year, [], 'Wird geladen…');
      return fetchOptions('baujahr', { hersteller: brand.value, modell: model.value }).then(function (values) {
        fill(year, values, 'Keine Auswahl verfügbar', keep);
        update();
      });
    }

    function loadModels(keepModel, keepYear) {
      if (!brand.value) {
        fill(model, [], 'Zuerst Marke wählen');
        return loadYears('');
      }
      fill(model, [], 'Wird geladen…');
      return fetchOptions('modell', { hersteller: brand.value }).then(function (values) {
        fill(model, values, 'Keine Auswahl verfügbar', keepModel);
        return loadYears(keepYear);
      });
    }

    var brandSearch = document.getElementById('lpVehBrandSearch');
    var allBrands = [];

    brand.addEventListener('change', function () {
      loadModels('', '');
    });
    if (brandSearch) {
      brandSearch.addEventListener('input', function () {
        var term = norm(brandSearch.value);
        var matches = allBrands.filter(function (value) {
          return term === '' || norm(value).indexOf(term) !== -1;
        });
        var keep = brand.value;
        fill(brand, matches, 'Keine Treffer', keep);
        if (term !== '' && matches.length === 1 && brand.value !== matches[0]) {
          brand.value = matches[0];
          loadModels('', '');
        }
      });
    }
    model.addEventListener('change', function () {
      loadYears('');
    });
    year.addEventListener('change', update);
    if (reset) {
      reset.addEventListener('click', function () {
        brand.value = '';
        loadModels('', '');
      });
    }

    var saved = {};
    try {
      saved = JSON.parse(window.sessionStorage.getItem(VEHICLE_KEY) || '{}') || {};
    } catch (e) {
      saved = {};
    }
    fetchOptions('hersteller', {}).then(function (values) {
      allBrands = values;
      if (brandSearch) {
        brandSearch.disabled = values.length === 0;
      }
      fill(brand, values, 'Keine Auswahl verfügbar', saved.brand || '');
      if (brand.value) {
        return loadModels(saved.model || '', saved.year || '');
      }
      update();
    });
  }

  initVehicle();
  apply();
})();
