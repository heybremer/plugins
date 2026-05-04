<div class="container opc-Container">
    <div id="br-caravan-root" data-initialized="0">
        <script>
            window.MarkeFiltreBrands = {$mfBrandsJson|default:'{}' nofilter};
        </script>
        {* Zusätzlich als application/json: funktioniert auch wenn externes JS (defer/Rocket Loader) vor Inline-Script läuft *}
        <script type="application/json" class="mf-brands-json">{$mfBrandsJson|default:'{}' nofilter}</script>
        <div class="mf-card">
            <div class="mf-head">
                <div class="mf-title">Marke auswählen</div>
                <div class="mf-tabs" role="tablist" aria-label="Fahrzeugtyp wählen">
                    <button type="button" class="mf-tab mf-is-active" data-mf-tab="PKW" role="tab" aria-selected="true">PKW</button>
                    <button type="button" class="mf-tab" data-mf-tab="Wohnmobil" role="tab" aria-selected="false">Wohnmobil</button>
                </div>
            </div>

            <div class="mf-body">
                <div class="mf-tools">
                    <div class="mf-search" data-mf-searchwrap>
                        <span class="mf-ico" aria-hidden="true">⌕</span>
                        <input type="text" data-mf-search placeholder="Marke suchen (z.B. Adria)" autocomplete="off" />
                    </div>
                </div>

                <div class="mf-letterbar" data-mf-letterbar></div>

                <div class="mf-grid" data-mf-grid></div>

                <div class="mf-footer">
                    <button type="button" class="mf-btn" data-mf-more style="display:none;">Weitere Marken</button>
                </div>
            </div>
        </div>
    </div>
</div>
