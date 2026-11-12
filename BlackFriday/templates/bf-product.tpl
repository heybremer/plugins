<div id="bf-product-deal" class="bf-deal">
    <div class="bf-deal__head">
        <span class="bf-deal__tag">BLACK FRIDAY DEAL</span>
        <span class="bf-deal__pct">-{$bfDeal.pct}%</span>
    </div>
    <div class="bf-deal__prices">
        <span class="bf-deal__new">{$bfDeal.new}</span>
        {if $bfDeal.old !== ''}<del class="bf-deal__old">{$bfDeal.old}</del>{/if}
    </div>
    {if $bfDeal.save !== ''}
        <p class="bf-deal__save">Du sparst <strong>{$bfDeal.save}</strong></p>
    {/if}
    {if $bf.endIso !== ''}
        <div class="bf-deal__timer">
            <span>Angebot endet in</span>
            <span class="bf-countdown bf-countdown--deal" data-bf-end="{$bf.endIso|escape:'html'}">
                <b data-u="d">00</b>T <b data-u="h">00</b>:<b data-u="m">00</b>:<b data-u="s">00</b>
            </span>
        </div>
    {/if}
</div>
