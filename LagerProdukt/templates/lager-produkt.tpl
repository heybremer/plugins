{if !isset($lpPageRendered)}
{assign var=lpPageRendered value=1}
<link rel="stylesheet" href="{$lpCssUrl}&amp;lpv=1.0.36">
<div id="lp-page" class="lp-wrap">
    <header class="lp-header">
        <div class="lp-header-info">
            <span class="lp-header-kicker">Sofort lieferbar</span>
            <p class="lp-header-title">
                <strong class="lp-header-number">{$lpProducts|@count}</strong>
                <span>Produkte auf Lager</span>
            </p>
        </div>
        {if $lpProducts|@count > 0}
        <div class="lp-toolbar">
            <label class="lp-sort" for="lpSort">
                <span>Sortierung</span>
                <select id="lpSort">
                    <option value="default">Empfohlen</option>
                    <option value="name">Name A–Z</option>
                    <option value="price-asc">Preis aufsteigend</option>
                    <option value="price-desc">Preis absteigend</option>
                </select>
            </label>
            <span class="lp-count" id="lpCount">{$lpProducts|@count} Treffer</span>
        </div>
        {/if}
    </header>

    {if !$lpCategoryFound}
        <p class="lp-empty">Diese Seite wurde nicht gefunden.</p>
    {elseif $lpProducts|@count == 0}
        <p class="lp-empty">Für diese Seite sind noch keine Art.-Nr. hinterlegt.</p>
    {else}
        {if $lpSeats|@count > 0}
        <section class="lp-seat-start" id="lpSeatStart" data-require-seat="1">
            <div class="lp-seat-start-heading">
                <div>
                    <span class="lp-start-kicker">Passende Bezüge</span>
                    <h3>Mit Sitz-Auswahl starten</h3>
                    <p>Wähle deinen Sitz und erhalte direkt die passenden Serien.</p>
                </div>
                <span class="lp-start-order">Sitz &rarr; Serie</span>
            </div>
            <div class="lp-seat-selected hidden" id="lpSeatSelected">
                <img id="lpSeatSelectedImg" src="" alt="">
                <div>
                    <span>Ausgewählter Sitz</span>
                    <strong id="lpSeatSelectedName"></strong>
                </div>
                <button type="button" id="lpSeatChange">Sitz ändern</button>
            </div>
            <p class="lp-seat-hint" id="lpSeatHint">Wähle deinen Sitztyp als Startpunkt.</p>
            <div class="lp-seat-grid" id="lpSeatGrid">
                {foreach $lpSeats as $s}
                    <button type="button"
                            class="lp-seat-tile{if $s.groupsCsv == ''} is-inquiry{/if}"
                            data-seat="{$s.code|escape:'html'}"
                            data-groups="{$s.groupsCsv|escape:'html'}"
                            data-name="{$s.name|escape:'html'}"
                            data-img="{$s.img|escape:'html'}">
                        <span class="lp-seat-img"><img src="{$s.img|escape:'html'}" alt="{$s.name|escape:'html'}" loading="lazy" width="220" height="220"></span>
                        <span class="lp-seat-name">{$s.name|escape:'html'}</span>
                        {if $s.groupsCsv == ''}<span class="lp-seat-badge">Anfrage</span>{/if}
                    </button>
                {/foreach}
            </div>
            <div class="lp-seat-inquiry hidden" id="lpSeatInquiry" hidden>
                <div class="lp-inquiry-text">
                    <p><strong>Kein passender Sitzbezug im Konfigurator gefunden?</strong></p>
                    <p>Kein Problem! Wir sind auf maßgefertigte Schonbezüge spezialisiert und können Sitzbezüge für nahezu jedes Fahrzeug individuell anfertigen.</p>
                    <p>Senden Sie uns einfach einige Bilder Ihrer Vordersitze über den Bilderupload und hinterlassen Sie Ihre Telefonnummer.</p>
                    <p>Falls Sie auch Interesse an Schonbezügen für die hinteren Sitzbänke haben, senden Sie uns bitte zusätzlich auch Bilder der Rücksitze.</p>
                    <p>Unser Team prüft Ihre Sitze und meldet sich anschließend persönlich bei Ihnen, um die passenden Schonbezüge für Ihr Fahrzeug zu planen.</p>
                    <p class="lp-inquiry-seat">Ausgewählter Sitz: <strong id="lpInquirySeatName"></strong></p>
                </div>
                <form id="lpInquiryForm" class="lp-inquiry-form" method="post" action="#" enctype="multipart/form-data">
                    <div class="lp-inquiry-field">
                        <label for="lp_fallback_phone">Telefon <span>*</span></label>
                        <input type="tel" id="lp_fallback_phone" name="phone" required placeholder="z.B. 0123 456789">
                    </div>
                    <div class="lp-inquiry-row">
                        <div class="lp-inquiry-field">
                            <label for="lp_fallback_vorname">Vorname <span>*</span></label>
                            <input type="text" id="lp_fallback_vorname" name="vorname" required placeholder="Vorname">
                        </div>
                        <div class="lp-inquiry-field">
                            <label for="lp_fallback_nachname">Nachname <span>*</span></label>
                            <input type="text" id="lp_fallback_nachname" name="nachname" required placeholder="Nachname">
                        </div>
                    </div>
                    <div class="lp-inquiry-field">
                        <label for="lp_fallback_email">E-Mail <span>*</span></label>
                        <input type="email" id="lp_fallback_email" name="email" required placeholder="ihre@email.de">
                    </div>
                    <div class="lp-inquiry-field">
                        <label for="lp_fallback_message">Nachricht / Hinweise</label>
                        <textarea id="lp_fallback_message" name="message" rows="4" placeholder="Zusätzliche Informationen zu Ihrem Fahrzeug oder Ihren Wünschen"></textarea>
                    </div>
                    <div class="lp-inquiry-field">
                        <label for="lp_fallback_images">Bilder Ihrer Sitze (Vorder- und ggf. Rücksitze)</label>
                        <div class="lp-file-upload">
                            <button type="button" class="lp-file-trigger" id="lpInquiryImagesTrigger">Bilder auswählen</button>
                            <span class="lp-file-info" id="lpInquiryImagesInfo">Keine Dateien ausgewählt</span>
                            <input type="file" id="lp_fallback_images" name="images[]" accept="image/*" multiple>
                        </div>
                    </div>
                    <button type="submit" class="lp-inquiry-submit">Anfrage absenden</button>
                </form>
            </div>
        </section>
        <div class="lp-status-overlay hidden" id="lpStatusOverlay" hidden role="dialog" aria-modal="true">
            <div class="lp-status-modal">
                <div class="lp-status-icon" id="lpStatusIcon">✓</div>
                <h3 id="lpStatusTitle">Erfolg</h3>
                <p id="lpStatusMessage"></p>
                <button type="button" class="lp-status-close" id="lpStatusClose">Schließen</button>
            </div>
        </div>
        {/if}
        {if $lpGroups|@count > 1}
        <div class="lp-series-nav{if $lpSeats|@count > 0} is-waiting-seat{/if}" id="lpSeriesNav">
            <span class="lp-series-label">Serien</span>
            <nav class="lp-chips" id="lpChips" aria-label="Gruppen">
                <button type="button" class="lp-chip is-active" data-group="all">Alle</button>
                {foreach $lpGroups as $g}
                    <button type="button" class="lp-chip" data-group="{$g.id|intval}" data-seats="{$g.seatsCsv|escape:'html'}">{$g.name|escape:'html'}</button>
                {/foreach}
            </nav>
        </div>
        {/if}
        <div id="lpCatalog"{if $lpSeats|@count > 0} class="is-waiting-seat"{/if}>
            {foreach $lpGroups as $g name=lpGroup}
                <section class="lp-group{if $g.id == 0} lp-group-other{/if}" data-group="{$g.id|intval}" data-seats="{$g.seatsCsv|escape:'html'}" data-order="{$smarty.foreach.lpGroup.index}">
                    <div class="lp-group-head">
                        <h3 class="lp-group-title">{$g.name|escape:'html'}</h3>
                        <span class="lp-group-meta">{if $g.id > 0}{$g.variants|@count} Varianten{else}{$g.products|@count} Produkte{/if}</span>
                    </div>
                    <div class="lp-grid">
                        {if $g.id > 0 && $g.mainProduct}
                            {assign var=p value=$g.mainProduct}
                            <article class="lp-product" data-group="{$g.id|intval}" data-seats="{$g.seatsCsv|escape:'html'}" data-order="{$g.id|intval}">
                            <a class="lp-card" href="{$p.url|escape:'html'}" data-name="{$p.name|escape:'html'}" data-artnr="{$p.artNr|escape:'html'}" data-price="{$p.price}">
                                <span class="lp-media">
                                    <img class="lp-main-image" src="{$p.img|escape:'html'}" alt="{$p.name|escape:'html'}" loading="lazy" width="400" height="400">
                                    <span class="lp-arrow" aria-hidden="true">↗</span>
                                </span>
                                <span class="lp-body">
                                    <span class="lp-meta">
                                        <span class="lp-artnr">Art.-Nr. {$p.artNr|escape:'html'}</span>
                                        <span class="lp-stock"><i></i> Sofort verfügbar</span>
                                    </span>
                                    <span class="lp-name">{$p.name|escape:'html'}</span>
                                    <span class="lp-card-foot">
                                        <span class="lp-price">{$p.priceF|escape:'html'}</span>
                                        <span class="lp-tax">inkl. MwSt.</span>
                                    </span>
                                </span>
                            </a>
                            {if $g.variants|@count > 1}
                                <div class="lp-variants">
                                    <span class="lp-variants-label">Farben &amp; Varianten ({$g.variants|@count})</span>
                                    <div class="lp-variant-track" role="list" aria-label="{$g.name|escape:'html'} Varianten">
                                        {foreach $g.variants as $v}
                                            <button type="button"
                                                    class="lp-variant{if $v.id == $p.id} is-active{/if}"
                                                    role="listitem"
                                                    data-url="{$v.url|escape:'html'}"
                                                    data-img="{$v.img|escape:'html'}"
                                                    data-name="{$v.name|escape:'html'}"
                                                    data-artnr="{$v.artNr|escape:'html'}"
                                                    data-price="{$v.price}"
                                                    data-price-f="{$v.priceF|escape:'html'}"
                                                    aria-label="{$v.name|escape:'html'} – Art.-Nr. {$v.artNr|escape:'html'}"
                                                    aria-pressed="{if $v.id == $p.id}true{else}false{/if}">
                                                <img src="{$v.img|escape:'html'}" alt="" loading="lazy" width="64" height="64">
                                                <span>{$v.artNr|escape:'html'}</span>
                                            </button>
                                        {/foreach}
                                    </div>
                                </div>
                            {/if}
                            </article>
                        {else}
                            {foreach $g.products as $p name=lpProducts}
                                <article class="lp-product" data-group="0" data-seats="{$p.seatsCsv|escape:'html'}" data-order="{$smarty.foreach.lpProducts.index}">
                                <a class="lp-card" href="{$p.url|escape:'html'}" data-name="{$p.name|escape:'html'}" data-artnr="{$p.artNr|escape:'html'}" data-price="{$p.price}">
                                    <span class="lp-media">
                                        <img class="lp-main-image" src="{$p.img|escape:'html'}" alt="{$p.name|escape:'html'}" loading="lazy" width="400" height="400">
                                        <span class="lp-arrow" aria-hidden="true">↗</span>
                                    </span>
                                    <span class="lp-body">
                                        <span class="lp-meta">
                                            <span class="lp-artnr">Art.-Nr. {$p.artNr|escape:'html'}</span>
                                            <span class="lp-stock"><i></i> Sofort verfügbar</span>
                                        </span>
                                        <span class="lp-name">{$p.name|escape:'html'}</span>
                                        <span class="lp-card-foot">
                                            <span class="lp-price">{$p.priceF|escape:'html'}</span>
                                            <span class="lp-tax">inkl. MwSt.</span>
                                        </span>
                                    </span>
                                </a>
                                </article>
                            {/foreach}
                        {/if}
                    </div>
                </section>
            {/foreach}
        </div>
        <p class="lp-empty lp-none" id="lpNone" hidden>Keine Treffer für Ihre Suche.</p>
    {/if}
</div>
<script src="{$lpJsUrl}&amp;lpv=1.0.36" defer></script>
{/if}
