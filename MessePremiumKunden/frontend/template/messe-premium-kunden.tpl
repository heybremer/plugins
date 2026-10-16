{if !isset($mpkFormRendered)}
{assign var=mpkFormRendered value=1}
<link rel="stylesheet" href="{$mpkCssUrl}">
<div id="mpk-form-wrap" class="mpk-wrap">
    <div class="mpk-card">
        <div class="mpk-card-header">
            <div class="mpk-logo-badge" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" fill="#ccff00"/>
                    <path d="M7 13.5l3 3 7-8" stroke="#1a1a2e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <h1 class="mpk-title">Messe Premium Kunden</h1>
                <p class="mpk-subtitle">Registrieren Sie sich und sichern Sie sich Ihren persönlichen Messe-Rabatt.</p>
            </div>
        </div>

        {if $mpkActive == 1}
        <div class="mpk-rabatt-banner" role="status">
            <span class="mpk-rabatt-value">{$mpkRabatt|string_format:"%.0f"}&nbsp;%</span>
            <span class="mpk-rabatt-text">Messe-Rabatt auf Ihre nächste Bestellung. Der Gutschein ist {$mpkGueltigTage} Tage gültig und wird nach dem Absenden per E-Mail zugestellt.</span>
        </div>
        {/if}

        <div class="mpk-alert mpk-alert-error" id="mpkErrors" style="display:none;" role="alert"></div>
        <div class="mpk-alert mpk-alert-success" id="mpkSuccess" style="display:none;" role="status"></div>

        {if $mpkActive != 1}
            <p class="mpk-inactive">Das Formular ist derzeit deaktiviert.</p>
        {else}
        <form id="mpkForm" class="mpk-form" action="{$mpkFormAction}" method="post" enctype="multipart/form-data" novalidate
              data-modelle-url="{$mpkModelleUrl}"
              data-max-fotos="{$mpkMaxFotos}"
              data-max-kb="{$mpkMaxFotoKb}">
            {$mpkCsrf}
            <input type="hidden" name="mpk_ts" value="{$mpkFormTs}">
            <div class="mpk-hp" aria-hidden="true">
                <label for="mpkWebsite">Website</label>
                <input type="text" id="mpkWebsite" name="website" tabindex="-1" autocomplete="off">
            </div>

            <fieldset class="mpk-fieldset">
                <legend>Persönliche Daten</legend>
                <div class="mpk-field">
                    <label for="mpkName">Vor- und Nachname <span class="mpk-req">*</span></label>
                    <input type="text" id="mpkName" name="name" autocomplete="name" required maxlength="200">
                </div>
                <div class="mpk-row mpk-row-2">
                    <div class="mpk-field">
                        <label for="mpkTelefon">Telefon <span class="mpk-req">*</span></label>
                        <input type="tel" id="mpkTelefon" name="telefon" autocomplete="tel" required maxlength="50">
                    </div>
                    <div class="mpk-field">
                        <label for="mpkEmail">E-Mail-Adresse <span class="mpk-req">*</span></label>
                        <input type="email" id="mpkEmail" name="email" autocomplete="email" required maxlength="200">
                    </div>
                </div>
                <div class="mpk-field">
                    <label for="mpkAdresse">Adresse <span class="mpk-req">*</span></label>
                    <textarea id="mpkAdresse" name="adresse" rows="3" autocomplete="street-address" required maxlength="500" placeholder="Straße, Hausnummer, PLZ, Ort, Land"></textarea>
                </div>
            </fieldset>

            <fieldset class="mpk-fieldset">
                <legend>Wohnmobil / Karavan</legend>
                <div class="mpk-row mpk-row-2">
                    <div class="mpk-field">
                        <label for="mpkMarke">Marke</label>
                        <select id="mpkMarke" name="karavanMarke">
                            <option value="">– Bitte wählen –</option>
                            {foreach $mpkMarken as $marke}
                                <option value="{$marke|escape:'html'}">{$marke|escape:'html'}</option>
                            {/foreach}
                            <option value="Andere">Andere / manuelle Eingabe</option>
                        </select>
                    </div>
                    <div class="mpk-field">
                        <label for="mpkModell">Modell</label>
                        <select id="mpkModell" name="karavanModell">
                            <option value="">– Zuerst Marke wählen –</option>
                        </select>
                    </div>
                </div>
                <div class="mpk-field" id="mpkManuellWrap">
                    <label for="mpkManuell">Marke / Modell manuell</label>
                    <input type="text" id="mpkManuell" name="karavanManuell" maxlength="200" placeholder="z. B. Hymer B-Klasse / ML-T 580">
                </div>
            </fieldset>

            <fieldset class="mpk-fieldset">
                <legend>Fotos <span class="mpk-legend-note">(max. {$mpkMaxFotos}, je {$mpkMaxFotoKb} KB)</span></legend>
                <div class="mpk-field">
                    <label for="mpkFotos">Fotos hochladen</label>
                    <input type="file" id="mpkFotos" name="fotos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                    <p class="mpk-hint" id="mpkFotoHint">JPG, PNG, WEBP oder GIF – höchstens {$mpkMaxFotos} Dateien.</p>
                </div>
            </fieldset>

            <fieldset class="mpk-fieldset mpk-fieldset-optional">
                <legend>Nachricht</legend>
                <div class="mpk-field">
                    <label for="mpkNachricht">Notiz / Nachricht</label>
                    <textarea id="mpkNachricht" name="nachricht" rows="4" maxlength="4000" placeholder="Optionale Hinweise zu Ihrem Fahrzeug oder Ihrer Anfrage"></textarea>
                </div>
            </fieldset>

            <div class="mpk-field mpk-datenschutz-field">
                <label class="mpk-checkbox-label">
                    <input type="checkbox" id="mpkDatenschutz" name="datenschutz" value="1" required>
                    <span>Ich habe die <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner Daten zu. <span class="mpk-req">*</span></span>
                </label>
            </div>

            <div class="mpk-submit-row">
                <button type="submit" id="mpkSubmitBtn" class="mpk-btn-submit">
                    <span class="mpk-btn-text">Registrierung absenden</span>
                    <span class="mpk-btn-spinner" style="display:none;">⟳</span>
                </button>
                <p class="mpk-req-note"><span class="mpk-req">*</span> Pflichtfelder</p>
            </div>
        </form>
        {/if}
    </div>
</div>
<script src="{$mpkJsUrl}"></script>
{/if}
