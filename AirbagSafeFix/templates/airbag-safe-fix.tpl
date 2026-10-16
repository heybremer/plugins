<link rel="stylesheet" href="{$asfCssUrl}">
<div id="asf-form-wrap" class="asf-wrap">
    <div class="asf-card">
        <div class="asf-card-header">
            <div class="asf-logo-badge">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" fill="#ccff00"/>
                    <path d="M12 7v5l3 3" stroke="#1a1a2e" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <h1 class="asf-title">Airbag Safe Fix</h1>
                <p class="asf-subtitle">Sicherheitsregistrierung – Bitte füllen Sie alle Pflichtfelder aus.</p>
            </div>
        </div>

        <div class="asf-alert asf-alert-error" id="asfErrors" style="display:none;" role="alert"></div>
        <div class="asf-alert asf-alert-success" id="asfSuccess" style="display:none;" role="status">
            <strong>Vielen Dank!</strong> Ihre Registrierung wurde erfolgreich übermittelt.
        </div>

        <form id="asfForm" class="asf-form" novalidate>

            <fieldset class="asf-fieldset">
                <legend>Persönliche Daten</legend>
                <div class="asf-row asf-row-2">
                    <div class="asf-field">
                        <label for="asfVorname">Vorname <span class="asf-req">*</span></label>
                        <input type="text" id="asfVorname" name="vorname" autocomplete="given-name" required>
                    </div>
                    <div class="asf-field">
                        <label for="asfNachname">Nachname <span class="asf-req">*</span></label>
                        <input type="text" id="asfNachname" name="nachname" autocomplete="family-name" required>
                    </div>
                </div>
                <div class="asf-row asf-row-2">
                    <div class="asf-field">
                        <label for="asfEmail">E-Mail-Adresse <span class="asf-req">*</span></label>
                        <input type="email" id="asfEmail" name="email" autocomplete="email" required>
                    </div>
                    <div class="asf-field">
                        <label for="asfTelefon">Telefonnummer</label>
                        <input type="tel" id="asfTelefon" name="telefon" autocomplete="tel">
                    </div>
                </div>
            </fieldset>

            <fieldset class="asf-fieldset">
                <legend>Adresse</legend>
                <div class="asf-field">
                    <label for="asfStrasse">Straße und Hausnummer</label>
                    <input type="text" id="asfStrasse" name="strasse" autocomplete="street-address">
                </div>
                <div class="asf-row asf-row-3">
                    <div class="asf-field">
                        <label for="asfPlz">Postleitzahl</label>
                        <input type="text" id="asfPlz" name="plz" autocomplete="postal-code">
                    </div>
                    <div class="asf-field asf-col-2">
                        <label for="asfStadt">Stadt</label>
                        <input type="text" id="asfStadt" name="stadt" autocomplete="address-level2">
                    </div>
                </div>
                <div class="asf-field">
                    <label for="asfLand">Land</label>
                    <select id="asfLand" name="land" autocomplete="country-name">
                        <option value="">– Bitte wählen –</option>
                        <option value="Deutschland" selected>Deutschland</option>
                        <option value="Österreich">Österreich</option>
                        <option value="Schweiz">Schweiz</option>
                        <option value="Andere">Andere</option>
                    </select>
                </div>
            </fieldset>

            <fieldset class="asf-fieldset">
                <legend>Produktinformationen</legend>
                <div class="asf-row asf-row-2">
                    <div class="asf-field">
                        <label for="asfProduktName">Produktname / Modell-Nr. <span class="asf-req">*</span></label>
                        <input type="text" id="asfProduktName" name="produktName" placeholder="z. B. 910 oder Link" required>
                    </div>
                    <div class="asf-field">
                        <label for="asfVariante">Variante</label>
                        <select id="asfVariante" name="variante">
                            <option value="">– Bitte wählen –</option>
                            <option value="Pilot">Pilot</option>
                            <option value="Standard">Standard</option>
                        </select>
                    </div>
                </div>
                <div class="asf-row asf-row-2">
                    <div class="asf-field">
                        <label for="asfFahrzeugMarke">Fahrzeugmarke</label>
                        <input type="text" id="asfFahrzeugMarke" name="fahrzeugMarke" placeholder="z. B. Fiat">
                    </div>
                    <div class="asf-field">
                        <label for="asfFahrzeugModell">Fahrzeugmodell</label>
                        <input type="text" id="asfFahrzeugModell" name="fahrzeugModell" placeholder="z. B. Ducato">
                    </div>
                </div>
                <div class="asf-field">
                    <label for="asfPartiNr">Parti-Nummer</label>
                    <input type="text" id="asfPartiNr" name="partiNr">
                </div>
            </fieldset>

            <fieldset class="asf-fieldset">
                <legend>Airbag-Näht-Nummern</legend>
                <div class="asf-row asf-row-2">
                    <div class="asf-field">
                        <label for="asfAirbag1">Airbag Näht-Nr. 1</label>
                        <input type="text" id="asfAirbag1" name="airbagDikis1">
                    </div>
                    <div class="asf-field">
                        <label for="asfAirbag2">Airbag Näht-Nr. 2</label>
                        <input type="text" id="asfAirbag2" name="airbagDikis2">
                    </div>
                    <div class="asf-field">
                        <label for="asfAirbag3">Airbag Näht-Nr. 3</label>
                        <input type="text" id="asfAirbag3" name="airbagDikis3">
                    </div>
                    <div class="asf-field">
                        <label for="asfAirbag4">Airbag Näht-Nr. 4</label>
                        <input type="text" id="asfAirbag4" name="airbagDikis4">
                    </div>
                </div>
                <div class="asf-field">
                    <label for="asfProductionTime">Production Time</label>
                    <input type="text" id="asfProductionTime" name="productionTime" placeholder="z. B. 2024-03">
                </div>
            </fieldset>

            <fieldset class="asf-fieldset asf-fieldset-optional">
                <legend>Optional</legend>
                <div class="asf-field">
                    <label for="asfPlatform">Wo haben Sie das Produkt gekauft?</label>
                    <input type="text" id="asfPlatform" name="platform" placeholder="z. B. Amazon, womosi.de">
                </div>
                <div class="asf-field">
                    <label for="asfFaturaLink">Produkt-Rechnung (Link oder Dateiname)</label>
                    <input type="text" id="asfFaturaLink" name="faturaLink" placeholder="z. B. https://... oder Rechnungsnummer">
                </div>
            </fieldset>

            <div class="asf-field asf-datenschutz-field">
                <label class="asf-checkbox-label">
                    <input type="checkbox" id="asfDatenschutz" name="datenschutz" value="1" required>
                    <span>Ich habe die <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und stimme der Verarbeitung meiner Daten zu. <span class="asf-req">*</span></span>
                </label>
            </div>

            <div class="asf-submit-row">
                <button type="submit" id="asfSubmitBtn" class="asf-btn-submit">
                    <span class="asf-btn-text">Sicherheitsregistrierung absenden</span>
                    <span class="asf-btn-spinner" style="display:none;">⟳</span>
                </button>
                <p class="asf-req-note"><span class="asf-req">*</span> Pflichtfelder</p>
            </div>

        </form>
    </div>
</div>
<script src="{$asfJsUrl}"></script>
