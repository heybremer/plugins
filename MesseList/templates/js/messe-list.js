(function () {
    'use strict';

    var products = Array.isArray(window.ML_PRODUCTS) ? window.ML_PRODUCTS : [];
    var list = document.getElementById('mlList');
    var cartBody = document.getElementById('mlCartBody');
    var grand = document.getElementById('mlGrand');
    var lightbox = document.getElementById('mlLightbox');
    var lightboxImg = lightbox ? lightbox.querySelector('img') : null;
    if (!list || !cartBody) {
        return;
    }

    /** @type {Array<{i:number,qty:number,disc:number}>} */
    var cart = [];

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function euro(n) {
        return (Math.round(n * 100) / 100).toLocaleString('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' €';
    }

    function lineTotal(p, qty, disc) {
        var d = Math.min(100, Math.max(0, disc));
        return Math.max(0, qty) * p.fGross * (1 - d / 100);
    }

    function imgTag(p) {
        var src = p.cImgLg || p.cImg || '';
        var lg = p.cImgLg || p.cImg || '';
        return '<img class="ml-zoom" src="' + esc(src) + '" srcset="' + esc(lg) + ' 2x" data-lg="'
            + esc(lg) + '" alt="' + esc(p.cName || '') + '">';
    }

    function addToCart(i) {
        var p = products[i];
        if (!p) {
            return;
        }
        var row = cart.find(function (c) { return c.i === i; });
        if (row) {
            row.qty += 1;
        } else {
            cart.push({ i: i, qty: 1, disc: 0 });
        }
        renderCart();
        showAddedToast(p);
    }

    var toastTimer = 0;
    function showAddedToast(p) {
        var toast = document.getElementById('mlToast');
        if (!toast) {
            return;
        }
        var name = String(p.cName || p.cArtNr || 'Artikel');
        toast.textContent = name + ' wurde zur Gesamtliste hinzugefügt.';
        toast.hidden = false;
        toast.classList.add('is-on');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toast.classList.remove('is-on');
            toast.hidden = true;
        }, 2600);
    }

    function openLightbox(src) {
        if (!lightbox || !lightboxImg || !src) {
            return;
        }
        lightboxImg.src = src;
        lightbox.hidden = false;
        document.body.classList.add('ml-noscroll');
    }

    function closeLightbox() {
        if (!lightbox) {
            return;
        }
        lightbox.hidden = true;
        if (lightboxImg) {
            lightboxImg.removeAttribute('src');
        }
        document.body.classList.remove('ml-noscroll');
    }

    function renderCart() {
        if (!cart.length) {
            cartBody.className = 'ml-cart-empty';
            cartBody.innerHTML = 'Noch keine Artikel.';
            setGrand(0);
            var offerHide = document.getElementById('mlOfferOpen');
            if (offerHide) {
                offerHide.hidden = true;
            }
            var emptyFab = document.getElementById('mlCartFabCount');
            if (emptyFab) {
                emptyFab.hidden = true;
            }
            return;
        }
        cartBody.className = 'ml-cart-list';
        var sum = 0;
        var html = '';
        cart.forEach(function (c, ci) {
            var p = products[c.i];
            if (!p) {
                return;
            }
            var t = lineTotal(p, c.qty, c.disc);
            sum += t;
            html += '<div class="ml-cart-row" data-ci="' + ci + '">'
                + imgTag(p)
                + '<div class="ml-cart-mid">'
                + '<div class="ml-art">' + esc(p.cArtNr || '') + ' · ' + esc(p.cSerie || '') + '</div>'
                + '<div class="ml-name">' + esc(p.cName || '') + '</div>'
                + '<div class="ml-cart-tools">'
                + '<label>Stück <input class="ml-qty" type="number" min="1" step="1" value="' + c.qty + '" inputmode="numeric"></label>'
                + '<label>Rabatt % <input class="ml-disc" type="number" min="0" max="100" step="0.1" value="' + c.disc + '" inputmode="decimal"></label>'
                + '</div></div>'
                + '<div class="ml-cart-side">'
                + '<button type="button" class="ml-remove" aria-label="Entfernen">×</button>'
                + '<div class="ml-line">' + euro(t) + '</div>'
                + '</div></div>';
        });
        cartBody.innerHTML = html;
        setGrand(sum);
        var offerBtn = document.getElementById('mlOfferOpen');
        if (offerBtn) {
            offerBtn.hidden = cart.length === 0;
        }
        var fabCount = document.getElementById('mlCartFabCount');
        if (fabCount) {
            var n = cart.reduce(function (sum, c) { return sum + (c.qty || 0); }, 0);
            fabCount.textContent = String(n);
            fabCount.hidden = n <= 0;
        }
    }

    function setGrand(sum) {
        if (!grand) {
            return;
        }
        var strong = grand.querySelector('strong');
        if (strong) {
            strong.textContent = euro(sum);
        }
    }

    function cardHtml(it) {
        var p = it.p;
        return '<div class="ml-card" data-i="' + it.i + '">'
            + '<div class="ml-serie">' + esc(p.cSerie || '') + '</div>'
            + imgTag(p)
            + '<div class="ml-meta">'
            + '<div class="ml-art">' + esc(p.cArtNr || '') + '</div>'
            + '<div class="ml-name">' + esc(p.cName || '') + '</div>'
            + '<div class="ml-price">' + euro(p.fGross) + ' · Lager ' + Math.round(p.nStock || 0) + '</div>'
            + '</div>'
            + '<button type="button" class="ml-add" aria-label="Zur Gesamtliste">+</button>'
            + '</div>';
    }

    function sortItems(items) {
        return items.slice().sort(function (a, b) {
            var c = String(a.p.cSerie || '').localeCompare(String(b.p.cSerie || ''), 'de', { numeric: true, sensitivity: 'base' });
            if (c !== 0) return c;
            return String(a.p.cArtNr || '').localeCompare(String(b.p.cArtNr || ''), 'de', { numeric: true });
        });
    }

    var indexed = products.map(function (p, i) { return { p: p, i: i }; });
    var tabs = Array.isArray(window.ML_TABS) ? window.ML_TABS : [];
    var groups = Array.isArray(window.ML_GROUPS) ? window.ML_GROUPS : [];
    if (!tabs.length && groups.length) {
        tabs = [{ name: 'Übersicht', groups: groups }];
    }

    function familyPrefix(s) {
        s = String(s || '').toUpperCase();
        var m = s.match(/^([A-Z]{1,8})([0-9]*[1-9])0{2,}$/);
        if (m) {
            return m[1] + m[2];
        }
        if (/^[A-Z]{2,8}$/.test(s)) {
            return s;
        }
        return '';
    }

    function groupSeries(g) {
        var series = (g && Array.isArray(g.series) ? g.series : []).map(function (s) {
            return String(s).toUpperCase();
        }).filter(Boolean);
        if (series.length) {
            return series.filter(function (s, i) { return series.indexOf(s) === i; });
        }
        var name = String(g && g.name ? g.name : '').toUpperCase();
        var codes = name.match(/\b([A-Z]{1,8}[0-9]{1,5})\b/g);
        if (codes) {
            series = series.concat(codes);
        }
        var letterSerie = name.match(/\b([A-Z]{2,8})\s+SERIE\b/);
        if (letterSerie) {
            series.push(letterSerie[1]);
        }
        var seen = {};
        return series.filter(function (s) {
            if (!s || seen[s]) {
                return false;
            }
            seen[s] = true;
            return true;
        });
    }

    function serieMatches(selected, productSerie) {
        productSerie = String(productSerie || '').toUpperCase();
        if (!productSerie) {
            return false;
        }
        if (selected.indexOf(productSerie) !== -1) {
            return true;
        }
        var families = [];
        var hasChild = false;
        for (var i = 0; i < selected.length; i++) {
            var fam = familyPrefix(selected[i]);
            if (fam) {
                families.push(fam);
            } else {
                hasChild = true;
            }
        }
        if (hasChild || !families.length) {
            return false;
        }
        for (var j = 0; j < families.length; j++) {
            if (productSerie.indexOf(families[j]) === 0 && /^[A-Z]{1,8}[0-9]{1,5}$/.test(productSerie)) {
                return true;
            }
        }
        return false;
    }

    function groupItems(g, tabIndex, groupIndex) {
        var series = groupSeries(g);
        return indexed.filter(function (it) {
            if (it.p.cManual && (it.p.nTab >= 0 || it.p.nGroup >= 0)) {
                return Number(it.p.nTab) === Number(tabIndex) && Number(it.p.nGroup) === Number(groupIndex);
            }
            return serieMatches(series, it.p.cSerie);
        });
    }

    function leftoverItems() {
        var used = [];
        tabs.forEach(function (tab) {
            (tab && tab.groups ? tab.groups : []).forEach(function (g) {
                used = used.concat(groupSeries(g));
            });
        });
        return indexed.filter(function (it) {
            if (it.p.cManual && Number(it.p.nTab) >= 0 && Number(it.p.nGroup) >= 0) {
                return false;
            }
            return !serieMatches(used, it.p.cSerie);
        });
    }

    function visibleGroups(tab) {
        return ((tab && tab.groups) || []).slice();
    }

    function tabAllItems(tab, ti) {
        var seen = {};
        var items = [];
        visibleGroups(tab).forEach(function (g, gi) {
            groupItems(g, ti, gi).forEach(function (it) {
                if (!seen[it.i]) {
                    seen[it.i] = true;
                    items.push(it);
                }
            });
        });
        if (tab === tabs[0]) {
            leftover.forEach(function (it) {
                if (!seen[it.i]) {
                    seen[it.i] = true;
                    items.push(it);
                }
            });
        }
        return items;
    }

    var leftover = leftoverItems();
    var activeTab = 0;
    var activeGroup = -1;

    function cardsHtml(items) {
        if (!items.length) {
            return '<p class="ml-hint">Keine Artikel in dieser Gruppe.</p>';
        }
        var html = '<div class="ml-cards">';
        sortItems(items).forEach(function (it) { html += cardHtml(it); });
        html += '</div>';
        return html;
    }

    function renderNav() {
        var html = '';
        if (tabs.length) {
            html += '<div class="ml-nav">';
            html += '<div class="ml-nav-row"><span class="ml-nav-label">Tab</span><div class="ml-tabs" role="tablist">';
            tabs.forEach(function (tab, ti) {
                html += '<button type="button" class="ml-tab' + (ti === activeTab ? ' is-on' : '') + '" data-tab="' + ti + '">'
                    + esc(tab && tab.name ? tab.name : ('Tab ' + (ti + 1))) + '</button>';
            });
            html += '</div></div>';

            var tabGroups = visibleGroups(tabs[activeTab]);
            if (leftover.length && activeTab === 0) {
                tabGroups = tabGroups.concat([{ name: 'Weitere Art.-Nr.', leftover: true }]);
            }
            if (activeGroup >= tabGroups.length) {
                activeGroup = -1;
            }
            html += '<div class="ml-nav-row ml-nav-groups"><span class="ml-nav-label">Gruppe</span><div class="ml-groups-nav">';
            html += '<button type="button" class="ml-group-btn' + (activeGroup === -1 ? ' is-on' : '') + '" data-group="-1">All Produkt</button>';
            tabGroups.forEach(function (g, gi) {
                html += '<button type="button" class="ml-group-btn' + (gi === activeGroup ? ' is-on' : '') + '" data-group="' + gi + '">'
                    + esc(g && g.name ? g.name : ('Gruppe ' + (gi + 1))) + '</button>';
            });
            html += '</div></div>';
            html += '</div>';

            var current = activeGroup === -1 ? { name: 'All Produkt', all: true } : tabGroups[activeGroup];
            var items = current && current.all
                ? tabAllItems(tabs[activeTab], activeTab)
                : (current && current.leftover ? leftover : groupItems(current, activeTab, activeGroup));
            html += '<section class="ml-group"><h2>' + esc(current && current.name ? current.name : 'Gruppe') + '</h2>'
                + cardsHtml(items) + '</section>';
        } else {
            html = leftover.length ? leftoverHtmlFallback() : '<p class="ml-hint">Keine Lagerartikel gefunden.</p>';
        }
        return html;
    }

    function leftoverHtmlFallback() {
        return '<section class="ml-group"><h2>Weitere Art.-Nr.</h2>' + cardsHtml(leftover) + '</section>';
    }

    if (!indexed.length) {
        list.innerHTML = '<p class="ml-hint">Keine Lagerartikel gefunden.</p>';
        return;
    }

    list.innerHTML = renderNav();

    list.addEventListener('click', function (e) {
        var tabBtn = e.target.closest('.ml-tab');
        if (tabBtn) {
            activeTab = parseInt(tabBtn.getAttribute('data-tab'), 10) || 0;
            activeGroup = -1;
            list.innerHTML = renderNav();
            return;
        }
        var groupBtn = e.target.closest('.ml-group-btn');
        if (groupBtn) {
            activeGroup = parseInt(groupBtn.getAttribute('data-group'), 10);
            if (!isFinite(activeGroup)) {
                activeGroup = -1;
            }
            list.innerHTML = renderNav();
        }
    });

    list.addEventListener('click', function (e) {
        var zoom = e.target.closest('.ml-zoom');
        if (zoom) {
            openLightbox(zoom.getAttribute('data-lg') || zoom.getAttribute('src'));
            return;
        }
        var btn = e.target.closest('.ml-add');
        if (!btn) {
            return;
        }
        var row = btn.closest('.ml-card');
        if (!row) {
            return;
        }
        addToCart(parseInt(row.getAttribute('data-i'), 10));
    });

    function refreshTotals() {
        var sum = 0;
        cartBody.querySelectorAll('.ml-cart-row').forEach(function (row) {
            var ci = parseInt(row.getAttribute('data-ci'), 10);
            var item = cart[ci];
            var p = item ? products[item.i] : null;
            if (!item || !p) {
                return;
            }
            var t = lineTotal(p, item.qty, item.disc);
            sum += t;
            var out = row.querySelector('.ml-line');
            if (out) {
                out.textContent = euro(t);
            }
        });
        setGrand(sum);
    }

    cartBody.addEventListener('input', function (e) {
        var row = e.target.closest('.ml-cart-row');
        if (!row) {
            return;
        }
        var ci = parseInt(row.getAttribute('data-ci'), 10);
        var item = cart[ci];
        if (!item) {
            return;
        }
        if (e.target.classList.contains('ml-qty')) {
            var q = parseFloat(String(e.target.value).replace(',', '.'));
            item.qty = isFinite(q) && q > 0 ? q : 1;
        }
        if (e.target.classList.contains('ml-disc')) {
            var d = parseFloat(String(e.target.value).replace(',', '.'));
            item.disc = isFinite(d) ? Math.min(100, Math.max(0, d)) : 0;
        }
        refreshTotals();
    });

    cartBody.addEventListener('click', function (e) {
        var zoom = e.target.closest('.ml-zoom');
        if (zoom) {
            openLightbox(zoom.getAttribute('data-lg') || zoom.getAttribute('src'));
            return;
        }
        var btn = e.target.closest('.ml-remove');
        if (!btn) {
            return;
        }
        var row = btn.closest('.ml-cart-row');
        if (!row) {
            return;
        }
        var ci = parseInt(row.getAttribute('data-ci'), 10);
        if (ci >= 0) {
            cart.splice(ci, 1);
            renderCart();
        }
    });

    document.addEventListener('error', function (e) {
        var img = e.target;
        if (!img || img.tagName !== 'IMG' || !img.classList.contains('ml-zoom')) {
            return;
        }
        var src = img.getAttribute('src') || '';
        var step = img.getAttribute('data-fb') || '0';
        if (step === '0' && src.indexOf('.jpg') !== -1) {
            img.setAttribute('data-fb', '1');
            img.src = src.replace('.jpg', '.webp');
            return;
        }
        if (step === '1' && src.indexOf('/sm/') !== -1) {
            img.setAttribute('data-fb', '2');
            img.src = src.replace('/sm/', '/md/').replace('.webp', '.jpg');
            return;
        }
        img.classList.add('ml-img-miss');
    }, true);

    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox || e.target.closest('.ml-lb-close')) {
                closeLightbox();
            }
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeLightbox();
        }
    });

    var offerModal = document.getElementById('mlOfferModal');
    var offerForm = document.getElementById('mlOfferForm');
    var offerMsg = document.getElementById('mlOfferMsg');
    var offerSend = document.getElementById('mlOfferSend');
    var offerOpen = document.getElementById('mlOfferOpen');
    var offerDownload = document.getElementById('mlOfferDownload');

    function setOfferMsg(text, ok) {
        if (!offerMsg) {
            return;
        }
        offerMsg.hidden = !text;
        offerMsg.textContent = text || '';
        offerMsg.className = 'ml-offer-msg' + (ok ? ' is-ok' : ' is-err');
    }

    function closeOffer() {
        if (!offerModal) {
            return;
        }
        offerModal.hidden = true;
        document.body.classList.remove('ml-noscroll');
    }

    if (offerOpen && offerModal) {
        offerOpen.addEventListener('click', function () {
            if (!cart.length) {
                return;
            }
            setOfferMsg('', false);
            if (offerDownload) {
                offerDownload.hidden = true;
                offerDownload.removeAttribute('href');
            }
            offerModal.hidden = false;
            document.body.classList.add('ml-noscroll');
            var first = offerModal.querySelector('input');
            if (first) {
                first.focus();
            }
        });
    }
    var offerClose = document.getElementById('mlOfferClose');
    if (offerClose) {
        offerClose.addEventListener('click', closeOffer);
    }
    if (offerModal) {
        offerModal.addEventListener('click', function (e) {
            if (e.target === offerModal) {
                closeOffer();
            }
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && offerModal && !offerModal.hidden) {
            closeOffer();
        }
    });

    if (offerForm) {
        offerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!cart.length) {
                setOfferMsg('Bitte zuerst Artikel in die Gesamtliste legen.', false);
                return;
            }
            var fd = new FormData(offerForm);
            var items = [];
            cart.forEach(function (c) {
                var p = products[c.i];
                if (!p) {
                    return;
                }
                items.push({
                    artnr: p.cArtNr || '',
                    serie: p.cSerie || '',
                    name: p.cName || '',
                    qty: c.qty,
                    disc: c.disc,
                    gross: p.fGross || 0
                });
            });
            var payload = {
                vorname: String(fd.get('vorname') || '').trim(),
                nachname: String(fd.get('nachname') || '').trim(),
                telefon: String(fd.get('telefon') || '').trim(),
                email: String(fd.get('email') || '').trim(),
                items: items
            };
            if (offerSend) {
                offerSend.disabled = true;
                offerSend.textContent = 'Wird gesendet …';
            }
            setOfferMsg('', false);
            fetch(window.ML_QUOTE_URL || '', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { res: res, data: data };
                });
            }).then(function (pack) {
                var data = pack.data || {};
                if (data.ok) {
                    var extra = data.mailed ? ' und per E-Mail gesendet.' : ' (E-Mail konnte nicht gesendet werden).';
                    setOfferMsg('Angebot ' + (data.nr || '') + ' wurde erstellt' + extra, true);
                    if (offerDownload && data.download_url) {
                        offerDownload.href = data.download_url;
                        offerDownload.setAttribute('download', (data.nr || 'Angebot') + '.pdf');
                        offerDownload.hidden = false;
                    }
                    offerForm.reset();
                    return;
                }
                setOfferMsg(data.error || 'Angebot konnte nicht erstellt werden.', false);
            }).catch(function () {
                setOfferMsg('Netzwerkfehler. Bitte erneut versuchen.', false);
            }).finally(function () {
                if (offerSend) {
                    offerSend.disabled = false;
                    offerSend.textContent = 'Angebot senden';
                }
            });
        });
    }

    var cartFab = document.getElementById('mlCartFab');
    if (cartFab) {
        cartFab.addEventListener('click', function () {
            var target = document.getElementById('mlCart');
            if (target && typeof target.scrollIntoView === 'function') {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    renderCart();
}());
