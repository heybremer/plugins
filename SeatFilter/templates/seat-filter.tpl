<div id="seat-filter" class="seat-filter" data-api="{$shopURL}/sf-search">

    <div class="sf-header">
        <h2 class="sf-title">Sitzbezüge nach Sitz &amp; Serie finden</h2>
        <p class="sf-subtitle">Wählen Sie Ihren Sitztyp, dann die passende Serie – und sehen Sie sofort die verfügbaren Produkte.</p>
    </div>

    <div class="sf-steps">
        <div class="sf-step-indicator">
            <span class="sf-step-dot is-active" data-step="1">1</span>
            <span class="sf-step-line"></span>
            <span class="sf-step-dot" data-step="2">2</span>
            <span class="sf-step-line"></span>
            <span class="sf-step-dot" data-step="3">3</span>
        </div>
        <div class="sf-step-labels">
            <span class="sf-step-label is-active" data-step="1">Sitz</span>
            <span class="sf-step-label" data-step="2">Serie</span>
            <span class="sf-step-label" data-step="3">Produkte</span>
        </div>
    </div>

    <div class="sf-summary" id="sfSummary" style="display:none;">
        <span class="sf-tag" id="sfTagSitz"></span>
        <span class="sf-tag" id="sfTagSerie"></span>
        <button type="button" class="sf-reset-btn" id="sfReset" title="Filter zurücksetzen">&times; Zurücksetzen</button>
    </div>

    <div class="sf-loading" id="sfLoading" style="display:none;">
        <div class="sf-loading-bar"></div>
    </div>

    <!-- Step 1: Sitz Grid -->
    <div id="sfStep1" class="sf-panel">
        <h3 class="sf-panel-title">Sitz auswählen</h3>
        <div class="sf-tile-grid sf-seat-grid" id="sfSeatGrid"></div>
    </div>

    <!-- Step 2: Serie Grid -->
    <div id="sfStep2" class="sf-panel" style="display:none;">
        <h3 class="sf-panel-title">Serie auswählen</h3>
        <div class="sf-tile-grid sf-serie-grid" id="sfSerieGrid"></div>
    </div>

    <!-- Step 3: Artikel Grid -->
    <div id="sfStep3" class="sf-panel" style="display:none;">
        <h3 class="sf-panel-title">Produkte</h3>
        <div class="sf-artikel-grid" id="sfArtikelGrid"></div>
    </div>

    <div class="sf-empty-msg" id="sfEmpty" style="display:none;">
        Keine Ergebnisse gefunden. Bitte versuchen Sie eine andere Auswahl.
    </div>
</div>
