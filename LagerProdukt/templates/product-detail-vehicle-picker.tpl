<section id="lp-vehicle-picker"
         class="lpvp"
         data-endpoint="/fk-search"
         data-series="{$lpDetailVariants.groupName|escape:'html'}"
         data-product-name="{$lpDetailVariants.productName|escape:'html'}"
         data-selected-seat="{$lpDetailVariants.selectedSeat|escape:'html'}"
         data-seats="{$lpDetailVariants.compatibleSeats|@json_encode|escape:'html'}"
         aria-label="Fahrzeug-Kompatibilität prüfen">
    <div class="lpvp-intro">
        <span class="lpvp-step">Fahrzeug-Check</span>
        <strong>Passt dieser Bezug?</strong>
    </div>
    <div class="lpvp-fields">
        <label>
            <span>Sitztyp</span>
            <select data-lpvp="sitz" aria-label="Sitztyp">
                <option value="">Bitte wählen</option>
            </select>
        </label>
        <label>
            <span>Fahrzeug-Marke</span>
            <select data-lpvp="hersteller" aria-label="Fahrzeug-Marke" disabled>
                <option value="">Zuerst Sitztyp wählen</option>
            </select>
        </label>
        <label>
            <span>Fahrzeug-Modell</span>
            <select data-lpvp="modell" aria-label="Fahrzeug-Modell" disabled>
                <option value="">Zuerst Marke wählen</option>
            </select>
        </label>
    </div>
    <div class="lpvp-result" data-lpvp-result aria-live="polite" hidden>
        <span data-lpvp-message></span>
        <a data-lpvp-link href="#" hidden>Passende Produkte anzeigen</a>
    </div>
</section>
