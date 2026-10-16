(function () {
    'use strict';

    var root = document.getElementById('seat-filter');
    if (!root) return;

    var API = root.getAttribute('data-api') || '/sf-search';

    var SEAT_META = {
        '1':  { name: 'Sitz 1',  tooltip: 'Milan 12',       img: 'https://womosi.de/mediafiles/sitz/1.jpg' },
        '2':  { name: 'Sitz 2',  tooltip: 'Liner 12',       img: 'https://womosi.de/mediafiles/sitz/2.jpg' },
        '3':  { name: 'Sitz 3',  tooltip: 'Captain Chair',  img: 'https://womosi.de/mediafiles/sitz/3.jpg' },
        '4':  { name: 'Sitz 4',  tooltip: 'i-Size Chair',   img: 'https://womosi.de/mediafiles/sitz/4.jpg' },
        '5':  { name: 'Sitz 5',  tooltip: 'Aguti Milan',    img: 'https://womosi.de/mediafiles/sitz/5.jpg' },
        '6':  { name: 'Sitz 6',  tooltip: 'Phoenix',        img: 'https://womosi.de/mediafiles/sitz/6.jpg' },
        '7':  { name: 'Sitz 7',  tooltip: 'Isri 6860',      img: 'https://womosi.de/mediafiles/sitz/7.jpg' },
        '8':  { name: 'Sitz 8',  tooltip: 'Torvec',         img: 'https://womosi.de/mediafiles/sitz/8.jpg' },
        '9':  { name: 'Sitz 9',  tooltip: 'Sportscraft',    img: 'https://womosi.de/mediafiles/sitz/9.jpg' },
        '10': { name: 'Sitz 10', tooltip: 'Scopema',        img: 'https://womosi.de/mediafiles/sitz/10.jpg' },
        '11': { name: 'Sitz 11', tooltip: 'Schnierle',      img: 'https://womosi.de/mediafiles/sitz/11.jpg' },
        '12': { name: 'Sitz 12', tooltip: 'Bremer Sitze',   img: 'https://womosi.de/mediafiles/sitz/12.jpg' },
        '13': { name: 'Sitz 13', tooltip: 'Joko 330',       img: 'https://womosi.de/mediafiles/sitz/13.jpg' },
        '14': { name: 'Sitz 14', tooltip: 'Be Cool',        img: 'https://womosi.de/mediafiles/sitz/14.jpg' }
    };

    var SERIES_META = {
        ZM:    { label: 'Serie ZM',     img: 'https://womosi.de/mediafiles/seat/zm-serie.avif' },
        1:     { label: 'Serie 01',     img: 'https://womosi.de/mediafiles/seat/serie-01.avif' },
        S1:    { label: 'Serie 01',     img: 'https://womosi.de/mediafiles/seat/serie-01.avif' },
        FT:    { label: 'Serie FT',     img: 'https://womosi.de/mediafiles/seat/ft-serie.avif' },
        FNL:   { label: 'Serie FNL',    img: 'https://womosi.de/mediafiles/seat/fnl-serie.avif' },
        V:     { label: 'Serie V',      img: 'https://womosi.de/mediafiles/seat/v-serie.avif' },
        TK600: { label: 'Serie TK600',  img: 'https://womosi.de/mediafiles/seat/tk600-serie.avif' },
        T:     { label: 'Serie T',      img: 'https://womosi.de/mediafiles/seat/t-serie.avif' },
        TF:    { label: 'Serie TF',     img: 'https://womosi.de/mediafiles/seat/tf-serie.avif' },
        SL:    { label: 'Serie SL',     img: 'https://womosi.de/mediafiles/seat/sl-serie.avif' },
        PS:    { label: 'Serie PS',     img: 'https://womosi.de/mediafiles/seat/ps-serie.avif' },
        PL:    { label: 'Serie PL',     img: 'https://womosi.de/mediafiles/seat/pl-serie.avif' },
        NL:    { label: 'Serie NL',     img: 'https://womosi.de/mediafiles/seat/nl-serie.avif' },
        N3:    { label: 'Serie N3',     img: 'https://womosi.de/mediafiles/seat/n3-serie.avif' },
        MD:    { label: 'Serie MD',     img: 'https://womosi.de/mediafiles/seat/md-serie.avif' },
        M:     { label: 'Serie M',      img: 'https://womosi.de/mediafiles/seat/m-serie.avif' },
        KT300: { label: 'Serie KT3',    img: 'https://womosi.de/mediafiles/seat/kt300-serie.avif' },
        KT:    { label: 'Serie KT',     img: 'https://womosi.de/mediafiles/seat/kt-serie.avif' },
        AH:    { label: 'Serie AH',     img: 'https://womosi.de/mediafiles/seat/ah-serie.avif' },
        GT:    { label: 'Serie GT',     img: 'https://womosi.de/mediafiles/seat/gt-serie.avif' },
        C:     { label: 'Serie C',      img: 'https://womosi.de/mediafiles/seat/c-serie.avif' },
        DPL:   { label: 'Serie DPL',    img: 'https://womosi.de/mediafiles/seat/dpl-serie.avif' },
        H:     { label: 'Serie H',      img: 'https://womosi.de/mediafiles/seat/h-serie.avif' },
        PLKT:  { label: 'Serie PLKT',   img: 'https://womosi.de/mediafiles/seat/plkt-serie.avif' },
        WPL:   { label: 'Serie WPL',    img: 'https://womosi.de/mediafiles/seat/wpl-serie.avif' },
        WMS:   { label: 'Serie WMS',    img: 'https://womosi.de/mediafiles/seat/wms-serie.avif' },
        HW:    { label: 'Serie HW',     img: 'https://womosi.de/mediafiles/seat/hw-serie.avif' },
        BC:    { label: 'Serie BC',     img: 'https://womosi.de/mediafiles/seat/bc-serie.avif' },
        DS:    { label: 'Serie DS',     img: 'https://womosi.de/mediafiles/seat/ds-serie.avif' },
        LT:    { label: 'Serie LT',     img: 'https://womosi.de/mediafiles/seat/lt-serie.avif' },
        WDS:   { label: 'Serie WDS',    img: 'https://womosi.de/mediafiles/seat/wds-serie.avif' },
        800:   { label: 'Serie 800er',  img: 'https://womosi.de/mediafiles/seat/800er-serie.avif' },
        WMA:   { label: 'Aguti Sitze',  img: 'https://womosi.de/mediafiles/seat/aguti-sitze-serie.avif' }
    };

    var els = {
        seatGrid:    document.getElementById('sfSeatGrid'),
        serieGrid:   document.getElementById('sfSerieGrid'),
        artikelGrid: document.getElementById('sfArtikelGrid'),
        step1:       document.getElementById('sfStep1'),
        step2:       document.getElementById('sfStep2'),
        step3:       document.getElementById('sfStep3'),
        loading:     document.getElementById('sfLoading'),
        summary:     document.getElementById('sfSummary'),
        tagSitz:     document.getElementById('sfTagSitz'),
        tagSerie:    document.getElementById('sfTagSerie'),
        resetBtn:    document.getElementById('sfReset'),
        empty:       document.getElementById('sfEmpty')
    };

    var state     = { step: 1, sitz: '', sitzLabel: '', serie: '', serieLabel: '' };
    var seatCache = null;

    function fetchJson(url, cb) {
        els.loading.style.display = '';
        els.empty.style.display = 'none';
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            els.loading.style.display = 'none';
            if (xhr.status >= 200 && xhr.status < 300) {
                try { cb(null, JSON.parse(xhr.responseText)); }
                catch (e) { cb(e, null); }
            } else { cb(new Error('HTTP ' + xhr.status), null); }
        };
        xhr.onerror = function () {
            els.loading.style.display = 'none';
            cb(new Error('Network'), null);
        };
        xhr.send();
    }

    function seatCodeFromValue(val) {
        var s = String(val).trim();
        var m = s.match(/(\d+)/);
        return m ? m[1] : null;
    }

    function findSeriesMeta(code) {
        if (SERIES_META[code]) return SERIES_META[code];
        var upper = String(code).toUpperCase();
        for (var k in SERIES_META) {
            if (String(k).toUpperCase() === upper) return SERIES_META[k];
        }
        return null;
    }

    /* ─── Step management ─── */
    function setStep(n) {
        state.step = n;
        els.step1.style.display = (n === 1) ? '' : 'none';
        els.step2.style.display = (n === 2) ? '' : 'none';
        els.step3.style.display = (n === 3) ? '' : 'none';
        els.empty.style.display = 'none';

        updateIndicator(n);
        updateSummary();

        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function updateIndicator(n) {
        root.querySelectorAll('.sf-step-dot').forEach(function (d) {
            var s = parseInt(d.getAttribute('data-step'), 10);
            d.classList.toggle('is-active', s === n);
            d.classList.toggle('is-done', s < n);
        });
        root.querySelectorAll('.sf-step-line').forEach(function (l, i) {
            l.classList.toggle('is-done', i + 1 < n);
        });
        root.querySelectorAll('.sf-step-label').forEach(function (l) {
            var s = parseInt(l.getAttribute('data-step'), 10);
            l.classList.toggle('is-active', s <= n);
        });
    }

    function updateSummary() {
        var show = state.sitz !== '' || state.serie !== '';
        els.summary.style.display = show ? '' : 'none';
        els.tagSitz.textContent  = state.sitzLabel ? ('Sitz: ' + state.sitzLabel) : '';
        els.tagSerie.textContent = state.serieLabel ? ('Serie: ' + state.serieLabel) : '';
    }

    /* ─── Render: Seats ─── */
    function renderSeats(list) {
        els.seatGrid.innerHTML = '';
        if (!list || list.length === 0) {
            els.empty.style.display = '';
            els.empty.textContent = 'Keine Sitz-Optionen gefunden.';
            return;
        }
        list.forEach(function (val) {
            var code = seatCodeFromValue(val);
            var meta = code ? SEAT_META[code] : null;

            var tile = document.createElement('div');
            tile.className = 'sf-tile' + (state.sitz === val ? ' is-active' : '');

            if (meta && meta.img) {
                var imgWrap = document.createElement('div');
                imgWrap.className = 'sf-tile-img';
                var img = document.createElement('img');
                img.loading = 'lazy';
                img.decoding = 'async';
                img.src = meta.img;
                img.alt = (meta.name) || val;
                imgWrap.appendChild(img);
                tile.appendChild(imgWrap);
            }

            var name = document.createElement('div');
            name.className = 'sf-tile-name';
            name.textContent = (meta && meta.name) ? meta.name : ('Sitz ' + val);
            tile.appendChild(name);

            if (meta && meta.tooltip) {
                var tip = document.createElement('div');
                tip.className = 'sf-tile-tooltip';
                tip.textContent = meta.tooltip;
                tile.appendChild(tip);
            }

            tile.addEventListener('click', function () {
                state.sitz = val;
                state.sitzLabel = (meta && meta.name) ? meta.name : ('Sitz ' + val);
                state.serie = '';
                state.serieLabel = '';
                setStep(2);
                loadSeries();
            });

            els.seatGrid.appendChild(tile);
        });
    }

    /* ─── Render: Series ─── */
    function renderSeries(list) {
        els.serieGrid.innerHTML = '';
        if (!list || list.length === 0) {
            els.empty.style.display = '';
            els.empty.textContent = 'Keine Serien für diesen Sitz gefunden.';
            return;
        }
        list.forEach(function (item, idx) {
            var code = item.serie || '';
            var meta = findSeriesMeta(code);

            var tile = document.createElement('div');
            tile.className = 'sf-tile' + (state.serie === code ? ' is-active' : '');

            var imgWrap = document.createElement('div');
            imgWrap.className = 'sf-tile-img';
            if (meta && meta.img) {
                var img = document.createElement('img');
                img.loading = idx < 4 ? 'eager' : 'lazy';
                img.decoding = 'async';
                img.src = meta.img;
                img.alt = (meta.label) || ('Serie ' + code);
                img.onerror = function () { this.parentNode.innerHTML = '<div class="sf-tile-placeholder">' + code + '</div>'; };
                imgWrap.appendChild(img);
            } else {
                imgWrap.innerHTML = '<div class="sf-tile-placeholder">' + code + '</div>';
            }
            tile.appendChild(imgWrap);

            var name = document.createElement('div');
            name.className = 'sf-tile-name';
            name.textContent = (meta && meta.label) ? meta.label : ('Serie ' + code);
            tile.appendChild(name);

            tile.addEventListener('click', function () {
                state.serie = code;
                state.serieLabel = (meta && meta.label) ? meta.label : ('Serie ' + code);
                setStep(3);
                loadArtikel();
            });

            els.serieGrid.appendChild(tile);
        });
    }

    /* ─── Render: Artikel ─── */
    function renderArtikel(list) {
        els.artikelGrid.innerHTML = '';
        if (!list || list.length === 0) {
            els.empty.style.display = '';
            els.empty.textContent = 'Keine Produkte für diese Kombination gefunden.';
            return;
        }

        var heading = document.createElement('p');
        heading.className = 'sf-result-count';
        heading.textContent = list.length + ' Produkt' + (list.length !== 1 ? 'e' : '') + ' gefunden';
        els.artikelGrid.appendChild(heading);

        list.forEach(function (art) {
            var card = document.createElement('a');
            card.href = art.cUrl || '#';
            card.className = 'sf-artikel-card';

            var imgWrap = document.createElement('div');
            imgWrap.className = 'sf-card-img';
            if (art.cBild) {
                var img = document.createElement('img');
                img.loading = 'lazy';
                img.decoding = 'async';
                img.src = art.cBild;
                img.alt = art.cName || '';
                img.onerror = function () {
                    this.style.display = 'none';
                    imgWrap.classList.add('sf-card-img-fallback');
                };
                imgWrap.appendChild(img);
            }
            card.appendChild(imgWrap);

            var body = document.createElement('div');
            body.className = 'sf-card-body';

            var nameEl = document.createElement('div');
            nameEl.className = 'sf-card-name';
            nameEl.textContent = art.cName || art.cHAN || '';
            body.appendChild(nameEl);

            if (art.cHAN) {
                var hanEl = document.createElement('div');
                hanEl.className = 'sf-card-han';
                hanEl.textContent = 'HAN: ' + art.cHAN;
                body.appendChild(hanEl);
            }

            if (art.cArtNr) {
                var artNr = document.createElement('div');
                artNr.className = 'sf-card-artnr';
                artNr.textContent = 'Art.-Nr. ' + art.cArtNr;
                body.appendChild(artNr);
            }

            var footer = document.createElement('div');
            footer.className = 'sf-card-footer';

            if (art.fPreis > 0) {
                var price = document.createElement('span');
                price.className = 'sf-card-price';
                price.textContent = art.fPreis.toFixed(2).replace('.', ',') + ' ' + (art.cWaehrung || '€');
                footer.appendChild(price);
            }

            var btn = document.createElement('span');
            btn.className = 'sf-card-btn';
            btn.textContent = 'Ansehen →';
            footer.appendChild(btn);

            body.appendChild(footer);
            card.appendChild(body);
            els.artikelGrid.appendChild(card);
        });
    }

    /* ─── API calls ─── */
    function loadSeats() {
        if (seatCache) {
            renderSeats(seatCache);
            return;
        }
        fetchJson(API + '?mode=sitz', function (err, data) {
            if (err || !Array.isArray(data)) { renderSeats([]); return; }
            seatCache = data;
            renderSeats(data);
        });
    }

    function loadSeries() {
        if (!state.sitz) return;
        els.serieGrid.innerHTML = '';
        els.artikelGrid.innerHTML = '';
        fetchJson(API + '?mode=serie&sitz=' + encodeURIComponent(state.sitz), function (err, data) {
            if (err || !Array.isArray(data)) { renderSeries([]); return; }
            renderSeries(data);
        });
    }

    function loadArtikel() {
        if (!state.sitz || !state.serie) return;
        els.artikelGrid.innerHTML = '';
        var url = API + '?mode=artikel&sitz=' + encodeURIComponent(state.sitz)
                + '&serie=' + encodeURIComponent(state.serie);
        fetchJson(url, function (err, data) {
            if (err || !Array.isArray(data)) { renderArtikel([]); return; }
            renderArtikel(data);
        });
    }

    /* ─── Step indicator clicks (navigate back) ─── */
    root.querySelectorAll('.sf-step-dot').forEach(function (dot) {
        dot.addEventListener('click', function () {
            var target = parseInt(dot.getAttribute('data-step'), 10);
            if (target >= state.step) return;
            if (target === 1) {
                state.serie = '';
                state.serieLabel = '';
                setStep(1);
                loadSeats();
            } else if (target === 2 && state.sitz) {
                state.serie = '';
                state.serieLabel = '';
                setStep(2);
                loadSeries();
            }
        });
    });

    /* ─── Summary tag clicks (navigate back) ─── */
    els.tagSitz.addEventListener('click', function () {
        if (state.step > 1) {
            state.serie = '';
            state.serieLabel = '';
            setStep(1);
            loadSeats();
        }
    });
    els.tagSerie.addEventListener('click', function () {
        if (state.step > 2 && state.sitz) {
            state.serie = '';
            state.serieLabel = '';
            setStep(2);
            loadSeries();
        }
    });

    /* ─── Reset ─── */
    els.resetBtn.addEventListener('click', function () {
        state.sitz = '';
        state.sitzLabel = '';
        state.serie = '';
        state.serieLabel = '';
        els.serieGrid.innerHTML = '';
        els.artikelGrid.innerHTML = '';
        els.empty.style.display = 'none';
        setStep(1);
        loadSeats();
    });

    /* ─── Init ─── */
    setStep(1);
    loadSeats();
})();
