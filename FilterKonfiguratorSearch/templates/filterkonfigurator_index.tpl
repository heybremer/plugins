{* Wohnmobil Konfigurator Block *}
{assign var='fkSegWm' value=$fkBanner->nSegmentWmActive|default:1}
{assign var='fkSegPkw' value=$fkBanner->nSegmentPkwActive|default:1}
<script>
    // Backend base URL (unterstützt Subfolder wie /WomosiWeb)
    window.WomosiFkBase = '{$ShopURL}';
    window.WomosiFkSegments = {ldelim}"wm":{$fkSegWm},"pkw":{$fkSegPkw}{rdelim};
</script>
<link rel="stylesheet" href="{$ShopURL}/plugins/FilterKonfiguratorSearch/templates/css/womosi-konfigurator.css?v=1.14.91">
<div class="index-filter-konfigurator" id="womosi-fk-konfigurator-anchor">
    <div class="womosi-konfigurator fk-wm-seat-only {if $fkSegPkw == 1 && $fkSegWm != 1}fk-segment-pkw{else}fk-segment-wm{/if}">
        <div class="konfig-banner">
            <h1 class="konfig-banner-title">{$fkBanner->cHeadline|default:'Besuchen Sie uns auf der HanseMesse Rostock'|escape:'html'}</h1>
            <p class="konfig-banner-sub">
                {if $fkBanner->nSub1Active == 1}
                    <b>{$fkBanner->cSub1|default:'Boot &amp; Angeln Camping'|escape:'html'}</b> <br>
                {/if}
                {if $fkBanner->nSub2Active == 1}
                    <a class="konfig-banner-btn" href="{$fkBanner->cSub2ButtonUrl|default:'#'|escape:'html'}">{$fkBanner->cSub2ButtonLabel|default:'Aktivieren'|escape:'html'}</a> <br>
                {/if}
                {if $fkBanner->nSub3Active == 1}
                    {$fkBanner->cSub3|default:'20. bis 22. März 2026 | HanseMesse Rostock'|escape:'html'}
                {/if}
            </p>
        </div>
        <div class="container opc-Container">
            <div class="card">
                <div class="wm-card-header">
                    <div class="fk-konfig-header-inner">
                        <h2 class="fk-konfig-title">Konfigurator</h2>
                        {if $fkSegWm == 1 && $fkSegPkw == 1}
                        <div class="fk-segment-tabs" role="tablist" aria-label="Fahrzeugtyp">
                            <button type="button" class="fk-segment-tab active" role="tab" aria-selected="true" data-fk-segment="wohnmobil" id="fk-tab-wm">Wohnmobil</button>
                            <button type="button" class="fk-segment-tab" role="tab" aria-selected="false" data-fk-segment="pkw" id="fk-tab-pkw">PKW</button>
                        </div>
                        {else}
                        <div class="fk-segment-tabs fk-segment-tabs--single" role="presentation" aria-hidden="true"></div>
                        {/if}
                    </div>
                </div>
                <div class="stepper fk-wm-only" id="fk-stepper-wm">
                    <div class="stepperRow">
                        <div class="steps">
                            <span class="label active" id="lbl1">
                                <span class="dot active" id="dot1">1</span> <span id="lblText1">Wohnmobil-Aufbauart</span>
                            </span>
                            <span style="opacity: .35;">&rarr;</span>
                            <span class="label" id="lbl2">
                                <span class="dot" id="dot2">2</span> <span id="lblText2">Details</span>
                            </span>
                            <span style="opacity: .35;">&rarr;</span>
                            <span class="label" id="lbl3">
                                <span class="dot" id="dot3">3</span> <span id="lblText3">Sitz</span>
                            </span>
                            <span style="opacity: .35;">&rarr;</span>
                            <span class="label" id="lbl4">
                                <span class="dot" id="dot4">4</span> <span id="lblText4">Serie</span>
                            </span>
                        </div>
                        <div style="font-weight: 900; color: rgba(0,0,0,.6); display: flex; align-items: center; gap: 10px;">
                            <span id="stepText">Step 1/4</span>
                            <button type="button" id="resetAll" class="reset-btn">Alles l&ouml;schen</button>
                        </div>
                    </div>
                    <div class="barWrap">
                        <div class="bar" id="bar"></div>
                    </div>
                </div>
                <div class="stepper fk-pkw-only" id="fk-stepper-pkw">
                    <div class="stepperRow">
                        <div class="steps">
                            <span class="label active" id="lblP1">
                                <span class="dot active" id="dotP1">1</span> Details
                            </span>
                            <span style="opacity: .35;">&rarr;</span>
                            <span class="label" id="lblP2">
                                <span class="dot" id="dotP2">2</span> Serie
                            </span>
                        </div>
                        <div class="fk-pkw-stepper-meta" style="font-weight: 900; color: rgba(0,0,0,.6); display: flex; align-items: center; gap: 10px;">
                            <span id="stepTextPkw">Step 1/2</span>
                            <button type="button" id="resetAllPkw" class="reset-btn">Alles l&ouml;schen</button>
                        </div>
                    </div>
                    <div class="barWrap">
                        <div class="bar" id="barPkw"></div>
                    </div>
                </div>
                <!-- STEP 1 -->
                <div id="step1" class="fk-wm-only">
                    <h2>Step 1 &ndash; Fahrzeugtyp</h2>
                    <div class="tile-grid" id="vehicleGrid">
                        <a class="tile-link" href="#" data-url="https://bremer-sitzbezuege.de/Kastenwagen" data-vehicle="Kastenwagen" data-label="Kastenwagen" data-key="Kastenwagen">
                            <div class="tile">
                                <img src="https://bremer-sitzbezuege.de/media/image/storage/opc/kasten-wagen.svg" alt="Kastenwagen">
                                <div class="name">Kastenwagen</div>
                            </div>
                        </a>
                        <a class="tile-link" href="#" data-url="https://bremer-sitzbezuege.de/Teilintegriert" data-vehicle="Teilintegriert" data-label="Teilintegriert" data-key="Teilintegriert">
                            <div class="tile">
                                <img src="https://bremer-sitzbezuege.de/media/image/storage/opc/teilintegriert.svg" alt="Teilintegriert">
                                <div class="name">Teilintegriert</div>
                            </div>
                        </a>
                        <a class="tile-link" href="#" data-url="https://bremer-sitzbezuege.de/Alkoven" data-vehicle="Alkoven" data-label="Alkoven" data-key="Alkoven">
                            <div class="tile">
                                <img src="https://bremer-sitzbezuege.de/media/image/storage/opc/alkoven.svg" alt="Alkoven">
                                <div class="name">Alkoven</div>
                            </div>
                        </a>
                        <a class="tile-link" href="#" data-url="https://bremer-sitzbezuege.de/Integriert" data-vehicle="Integriert" data-label="Integriert" data-key="Integriert">
                            <div class="tile">
                                <img src="https://bremer-sitzbezuege.de/media/image/storage/opc/integriert.svg" alt="Integriert">
                                <div class="name">Integriert</div>
                            </div>
                        </a>
                        <a class="tile-link" href="#" data-url="https://bremer-sitzbezuege.de/Urban-Camper-Minivan" data-vehicle="Urban-Camper" data-label="Urban Camper / Minivan" data-key="Urban-Camper">
                            <div class="tile">
                                <img src="https://bremer-sitzbezuege.de/media/image/storage/opc/urban-camper.svg" alt="Urban Camper / Minivan">
                                <div class="name">Urban Camper / Minivan</div>
                            </div>
                        </a>
                    </div>
                    <div class="actions">
                        <button id="toStep2" class="primary" type="button" disabled="disabled">Weiter zu Details</button>
                    </div>
                    <div class="hint">W&auml;hle zuerst eine Wohnmobil-Aufbauart aus.</div>
                    <div class="fk-start-divider" aria-hidden="true"><span>oder</span></div>
                    <section class="fk-seat-start" aria-labelledby="fk-seat-start-title">
                        <div class="fk-seat-start-heading">
                            <div>
                                <span class="fk-start-kicker">Alternative Suche</span>
                                <h3 id="fk-seat-start-title">Mit Sitz-Auswahl starten</h3>
                                <p>W&auml;hle deinen Sitz und erhalte direkt die passenden Serien.</p>
                            </div>
                            <span class="fk-start-order">Sitz &rarr; Serie</span>
                        </div>
                        {if !empty($fkSeatPhotoEnabled)}
                        <div class="fk-seat-photo" id="seatPhotoRecognizer">
                            <div class="fk-seat-photo-copy">
                                <span class="fk-seat-photo-badge">KI-Fotoerkennung</span>
                                <strong>Sitz fotografieren und automatisch erkennen</strong>
                                <span>Fotografiere den Sitz m&ouml;glichst frontal bei gutem Licht.</span>
                            </div>
                            <div class="fk-seat-photo-workspace">
                                <label class="fk-seat-photo-drop" for="seatPhotoInput" id="seatPhotoTrigger">
                                    <span class="fk-seat-photo-camera" aria-hidden="true">&#128247;</span>
                                    <span class="fk-seat-photo-trigger-title">Foto aufnehmen oder ausw&auml;hlen</span>
                                    <span class="fk-seat-photo-trigger-meta">JPG, PNG oder WebP &middot; max. 8 MB</span>
                                </label>
                                <input class="fk-seat-photo-input" id="seatPhotoInput" name="seat_photo"
                                       type="file" accept="image/jpeg,image/png,image/webp" capture="environment">
                                <div class="fk-seat-photo-preview hidden" id="seatPhotoPreview">
                                    <img id="seatPhotoPreviewImage" src="" alt="Vorschau des aufgenommenen Sitzes">
                                    <button class="fk-seat-photo-remove" id="seatPhotoRemove" type="button"
                                            aria-label="Ausgew&auml;hltes Foto entfernen">&times;</button>
                                </div>
                                <div class="fk-seat-photo-actions">
                                    <label class="fk-seat-photo-consent">
                                        <input id="seatPhotoConsent" type="checkbox">
                                        <span>Ich stimme der Analyse durch OpenAI zu. Das Foto wird nicht gespeichert.</span>
                                    </label>
                                    <button class="primary fk-seat-photo-analyze" id="seatPhotoAnalyze" type="button" disabled>
                                        Sitz erkennen
                                    </button>
                                </div>
                            </div>
                            <div class="fk-seat-photo-status" id="seatPhotoStatus" role="status" aria-live="polite"></div>
                            <div class="fk-seat-photo-result hidden" id="seatPhotoResult" aria-live="polite">
                                <img id="seatPhotoResultImage" src="" alt="">
                                <div>
                                    <span>Erkannter Sitz</span>
                                    <strong id="seatPhotoResultName"></strong>
                                    <small id="seatPhotoResultConfidence"></small>
                                </div>
                                <button id="seatPhotoRetry" type="button">Anderes Foto</button>
                            </div>
                        </div>
                        <div class="fk-start-divider fk-seat-photo-divider" aria-hidden="true"><span>oder manuell</span></div>
                        {/if}
                        <div class="hint" id="seatStartHint">Sitze werden geladen&hellip;</div>
                        <div class="tile-grid seat-grid fk-seat-start-grid" id="seatStartGrid"></div>
                    </section>
                </div>
                <!-- STEP 2 -->
                <div id="step2" class="hidden">
                    <h2 class="fk-wm-only" id="fk-wm-details-title">Step 2 &ndash; Details</h2>
                    <h2 class="fk-pkw-only">Step 1 &ndash; Fahrzeugdetails</h2>
                    <div id="fkLoading" class="fk-loading hidden" aria-hidden="true">
                        <div class="fk-loading-bar"></div>
                    </div>
                    <div class="fields">
                        <div class="field">
                            <label for="hersteller">Fahrzeug-Marke<span class="req" id="reqHer">*</span></label>
                            <div class="fk-control">
                                <select id="hersteller"></select>
                                <button type="button" class="fk-clear-btn" aria-label="Fahrzeug-Marke löschen" data-clear-target="#hersteller">×</button>
                            </div>
                        </div>
                        <div class="field fk-wm-only">
                            <label for="basisfahrzeug">Wohnmobil-Fahrgestell<span class="req" id="reqBasis">*</span></label>
                            <div class="fk-control">
                                <select id="basisfahrzeug"></select>
                                <button type="button" class="fk-clear-btn" aria-label="Wohnmobil-Fahrgestell löschen" data-clear-target="#basisfahrzeug">×</button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="modell">Fahrzeug-Modell<span class="req" id="reqModell">*</span></label>
                            <div class="fk-control">
                                <select id="modell"></select>
                                <button type="button" class="fk-clear-btn" aria-label="Fahrzeug-Modell löschen" data-clear-target="#modell">×</button>
                            </div>
                        </div>
                        <div class="field fk-baujahr-field">
                            <label class="fk-wm-only" for="baujahr">Baujahr<span class="req">*</span></label>
                            <label class="fk-pkw-only" for="baujahr_pkw">Baujahr<span class="req">*</span></label>
                            <div class="fk-control fk-wm-only">
                                <input id="baujahr" inputmode="numeric" pattern="\\d*" placeholder="z.B. 2021" type="text">
                                <button type="button" class="fk-clear-btn" aria-label="Baujahr löschen" data-clear-target="#baujahr">×</button>
                            </div>
                            <div class="fk-control fk-pkw-only">
                                <select id="baujahr_pkw" aria-label="Baujahr"></select>
                                <button type="button" class="fk-clear-btn" aria-label="Baujahr löschen" data-clear-target="#baujahr_pkw">×</button>
                            </div>
                        </div>
                    </div>
                    <div class="fields fk-wm-only">
                        <div class="field hidden" id="fk-modell-custom-row">
                            <label for="modell_custom">Ihr Modell nicht dabei?</label>
                            <div class="fk-control">
                                <input id="modell_custom" type="text" placeholder="Bitte tragen Sie Ihr Modell ein">
                                <button type="button" class="fk-clear-btn" aria-label="Eigene Modellbezeichnung löschen" data-clear-target="#modell_custom">×</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button id="backTo1" type="button" class="fk-wm-only">Zur&uuml;ck</button>
                        <button id="toStep3" class="primary" type="button" disabled="disabled">Weiter zu Sitz-Auswahl</button>
                    </div>
                </div>
                <!-- STEP 3 (nur Wohnmobil) -->
                <div id="step3" class="hidden fk-wm-only">
                    <h2>Step 3 &ndash; Sitz ausw&auml;hlen</h2>
                    <div class="hint" id="seatHint"></div>
                    <div class="tile-grid seat-grid" id="seatGrid"></div>
                    <div class="actions">
                        <button id="backTo2" type="button">Zur&uuml;ck</button>
                        <button id="toStep4" class="primary" type="button" disabled="disabled">Weiter zu Serie</button>
                    </div>
                </div>
                <!-- STEP 4 -->
                <div id="step4" class="hidden">
                    <h2 class="fk-wm-only" id="fk-wm-serie-title">Step 4 &ndash; Serie ausw&auml;hlen</h2>
                    <h2 class="fk-pkw-only">Step 2 &ndash; Serie ausw&auml;hlen</h2>
                    <div class="hint" id="serieHint">W&auml;hle die passende Serie &ndash; tippe auf eine Kachel.</div>
                    <!-- Verstecktes Text-Dropdown für Fallback / Logik -->
                   <div class="fields hidden">
   <div class="fields">
    <div class="field">
        <label for="serie">Serie<span class="req" id="reqSerie">*</span></label>
        <select id="serie"></select>
    </div>
</div>
</div>
                    <!-- Visuelle Serie-Auswahl -->
                    <div class="tile-grid serie-grid" id="serieGrid"></div>
                    <!-- Kein Ergebnis: Maßanfertigung-Formular -->
                    <div id="serieEmptyFallback" class="serie-fallback hidden">
                        <div class="serie-fallback-text">
                            <p><strong>Kein passender Sitzbezug im Konfigurator gefunden?</strong></p>
                            <p>Kein Problem! Wir sind auf ma&szlig;gefertigte Schonbez&uuml;ge spezialisiert und k&ouml;nnen Sitzbez&uuml;ge f&uuml;r nahezu jedes Fahrzeug individuell anfertigen.</p>
                            <p>Senden Sie uns einfach einige Bilder Ihrer Vordersitze &uuml;ber den Bilderupload und hinterlassen Sie Ihre Telefonnummer.</p>
                            <p>Falls Sie auch Interesse an Schonbez&uuml;gen f&uuml;r die hinteren Sitzb&auml;nke haben, senden Sie uns bitte zus&auml;tzlich auch Bilder der R&uuml;cksitze.</p>
                            <p>Unser Team pr&uuml;ft Ihre Sitze und meldet sich anschlie&szlig;end pers&ouml;nlich bei Ihnen, um die passenden Schonbez&uuml;ge f&uuml;r Ihr Fahrzeug zu planen.</p>
                        </div>
                        <form id="serieFallbackForm" class="serie-fallback-form" method="post" action="#" enctype="multipart/form-data">
                            <div class="field">
                                <label for="fallback_phone">Telefon <span class="req">*</span></label>
                                <input type="tel" id="fallback_phone" name="phone" required="" placeholder="z.B. 0123 456789">
                            </div>
                            <div class="field field-row">
                                <div class="field-col">
                                    <label for="fallback_vorname">Vorname <span class="req">*</span></label>
                                    <input type="text" id="fallback_vorname" name="vorname" required="" placeholder="Vorname">
                                </div>
                                <div class="field-col">
                                    <label for="fallback_nachname">Nachname <span class="req">*</span></label>
                                    <input type="text" id="fallback_nachname" name="nachname" required="" placeholder="Nachname">
                                </div>
                            </div>
                            <div class="field">
                                <label for="fallback_email">E-Mail <span class="req">*</span></label>
                                <input type="email" id="fallback_email" name="email" required="" placeholder="ihre@email.de">
                            </div>
                            <div class="field">
                                <label for="fallback_message">Nachricht / Hinweise</label>
                                <textarea id="fallback_message" name="message" rows="4" placeholder="Zus&auml;tzliche Informationen zu Ihrem Fahrzeug oder Ihren W&uuml;nschen"></textarea>
                            </div>
                            <div class="field">
                                <label for="fallback_images">Bilder Ihrer Sitze (Vorder- und ggf. R&uuml;cksitze)</label>
                                <div class="file-upload">
                                    <button type="button" class="file-trigger" id="fallback_images_trigger">Bilder ausw&auml;hlen</button>
                                    <span class="file-info" id="fallback_images_info">Keine Dateien ausgew&auml;hlt</span>
                                    <input type="file" id="fallback_images" name="images[]" accept="image/*" multiple="multiple">
                                </div>
                            </div>
                            <button type="submit" class="primary">Anfrage absenden</button>
                        </form>
                    </div>
                    <div class="actions">
                        <button id="backTo3" type="button">Zur&uuml;ck</button>
                        <button id="finish" class="primary" type="button" disabled="disabled">Abschlie&szlig;en</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="fk-status-overlay hidden" id="fkSuccessOverlay" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="fk-status-modal" role="document">
        <div class="fk-status-icon" id="fkSuccessIcon">âœ“</div>
        <h3 id="fkSuccessTitle">Erfolg</h3>
        <p id="fkSuccessMessage"></p>
        <button type="button" class="fk-status-close" id="fkSuccessClose">Schließen</button>
    </div>
</div>
<script>
    (function () {
        // inline init disabled; logic moved to womosi-konfigurator.js
        return;
        if (window.WomosiKonfiguratorInlineInit) {
            return;
        }
        window.WomosiKonfiguratorInlineInit = true;

        function init() {
            var vehicleGrid = document.getElementById('vehicleGrid');
            var toStep2Btn  = document.getElementById('toStep2');
            var backTo1Btn  = document.getElementById('backTo1');
            var toStep3Btn  = document.getElementById('toStep3');
            var backTo2Btn  = document.getElementById('backTo2');
            var toStep4Btn  = document.getElementById('toStep4');
            var backTo3Btn  = document.getElementById('backTo3');

            var step1 = document.getElementById('step1');
            var step2 = document.getElementById('step2');
            var step3 = document.getElementById('step3');
            var step4 = document.getElementById('step4');

            var lbl1 = document.getElementById('lbl1');
            var lbl2 = document.getElementById('lbl2');
            var lbl3 = document.getElementById('lbl3');
            var lbl4 = document.getElementById('lbl4');
            var dot1 = document.getElementById('dot1');
            var dot2 = document.getElementById('dot2');
            var dot3 = document.getElementById('dot3');
            var dot4 = document.getElementById('dot4');
            var bar  = document.getElementById('bar');
            var stepText = document.getElementById('stepText');

            var merkmalCache = {
                hersteller: [],
                basis: [],
                modell: [],
                serie: []
            };

            function fetchMerkmalOptions(field) {
                var params = new URLSearchParams({
                    mode: 'options',
                    field: field
                });
                var base = (typeof window !== 'undefined' && window.WomosiFkBase) ? String(window.WomosiFkBase).replace(/\/+$/, '') : '';
                return fetch(base + '/fk-search?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (res) { return res.json(); });
            }

            function fillSelect(id, options) {
                var sel = document.getElementById(id);
                if (!sel || !Array.isArray(options)) {
                    return;
                }
                sel.innerHTML = '';
                options.forEach(function (opt) {
                    if (!opt || typeof opt.value !== 'string') return;
                    var o = document.createElement('option');
                    o.value = opt.value;
                    o.textContent = opt.value;
                    sel.appendChild(o);
                });
            }

            function setupAutocomplete(fieldKey, inputId, panelId, selectId) {
                var input = document.getElementById(inputId);
                var panel = document.getElementById(panelId);
                var select = document.getElementById(selectId);
                if (!input || !panel || !select) {
                    return;
                }

                function renderList(term) {
                    var list = merkmalCache[fieldKey] || [];
                    var lower = (term || '').toLowerCase();
                    var filtered = list.filter(function (opt) {
                        if (!opt || typeof opt.value !== 'string') return false;
                        if (!lower) return true;
                        return opt.value.toLowerCase().indexOf(lower) !== -1;
                    });
                    panel.innerHTML = '';
                    if (!filtered.length) {
                        panel.style.display = 'none';
                        return;
                    }
                    filtered.forEach(function (opt) {
                        var item = document.createElement('div');
                        item.textContent = opt.value;
                        item.className = 'ac-item';
                        item.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            input.value = opt.value;
                            select.value = opt.value;
                            panel.style.display = 'none';
                        });
                        panel.appendChild(item);
                    });
                    panel.style.display = 'block';
                }

                input.addEventListener('focus', function () {
                    if (!merkmalCache[fieldKey] || !merkmalCache[fieldKey].length) {
                        fetchMerkmalOptions(fieldKey).then(function (opts) {
                            merkmalCache[fieldKey] = Array.isArray(opts) ? opts : [];
                            fillSelect(selectId, merkmalCache[fieldKey]);
                            renderList(input.value);
                        }).catch(function () {
                            panel.style.display = 'none';
                        });
                    } else {
                        renderList(input.value);
                    }
                });

                input.addEventListener('input', function () {
                    renderList(input.value);
                });

                document.addEventListener('click', function (e) {
                    if (!panel.contains(e.target) && e.target !== input) {
                        panel.style.display = 'none';
                    }
                });
            }

            function setStep(n) {
                if (!step1 || !step2 || !step3 || !step4) {
                    return;
                }
                step1.classList.toggle('hidden', n !== 1);
                step2.classList.toggle('hidden', n !== 2);
                step3.classList.toggle('hidden', n !== 3);
                step4.classList.toggle('hidden', n !== 4);

                var labels = [lbl1, lbl2, lbl3, lbl4];
                var dots   = [dot1, dot2, dot3, dot4];
                for (var i = 0; i < labels.length; i++) {
                    if (!labels[i] || !dots[i]) continue;
                    if (i + 1 === n) {
                        labels[i].classList.add('active');
                        dots[i].classList.add('active');
                    } else {
                        labels[i].classList.remove('active');
                        dots[i].classList.remove('active');
                    }
                }

                if (bar) {
                    var pct = 25 * n;
                    bar.style.width = pct + '%';
                }
                if (stepText) {
                    stepText.textContent = 'Step ' + n + '/4';
                }
            }

            // Autocomplete alanlarını hazırla
            setupAutocomplete('hersteller', 'hersteller_ac', 'hersteller_panel', 'hersteller');
            setupAutocomplete('basis', 'basis_ac', 'basis_panel', 'basisfahrzeug');
            setupAutocomplete('modell', 'modell_ac', 'modell_panel', 'modell');
            setupAutocomplete('serie', 'serie_ac', 'serie_panel', 'serie');

            if (vehicleGrid) {
                vehicleGrid.addEventListener('click', function (e) {
                    var link = e.target.closest ? e.target.closest('a.tile-link') : null;
                    if (!link) {
                        return;
                    }
                    if (e.preventDefault) {
                        e.preventDefault();
                    }

                    var all = vehicleGrid.querySelectorAll('.tile-link.is-active');
                    for (var i = 0; i < all.length; i++) {
                        all[i].classList.remove('is-active');
                    }
                    link.classList.add('is-active');

                    if (toStep2Btn) {
                        toStep2Btn.disabled = false;
                    }
                    // Fahrzeugtyp seçilince otomatik Step 2'ye geç
                    setStep(2);
                });
            }

            if (toStep2Btn) {
                toStep2Btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    if (toStep2Btn.disabled) return;
                    setStep(2);
                });
            }
            if (backTo1Btn) {
                backTo1Btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    setStep(1);
                });
            }
            if (toStep3Btn) {
                toStep3Btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    if (toStep3Btn.disabled) return;
                    setStep(3);
                });
            }
            if (backTo2Btn) {
                backTo2Btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    setStep(2);
                });
            }
            if (toStep4Btn) {
                toStep4Btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    if (toStep4Btn.disabled) return;
                    setStep(4);
                });
            }
            if (backTo3Btn) {
                backTo3Btn.addEventListener('click', function (e) {
                    if (e && e.preventDefault) e.preventDefault();
                    setStep(3);
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
<script src="{$ShopURL}/plugins/FilterKonfiguratorSearch/templates/js/womosi-konfigurator.js?v=1.14.91" defer></script>

