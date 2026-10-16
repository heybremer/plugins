<section id="lp-detail-variants" class="lpdv" aria-labelledby="lpdv-title">
    <div class="lpdv-head">
        <div>
            <span class="lpdv-kicker">Weitere Farben &amp; Varianten</span>
            <h2 id="lpdv-title">{$lpDetailVariants.groupName|escape:'html'}</h2>
        </div>
        <span class="lpdv-count">{$lpDetailVariants.variants|@count} Varianten</span>
    </div>
    <div class="lpdv-track" role="list">
        {foreach $lpDetailVariants.variants as $variant}
            <a class="lpdv-item{if $variant.isCurrent} is-current{/if}"
               href="{$variant.url|escape:'html'}"
               role="listitem"
               {if $variant.isCurrent}aria-current="page"{/if}
               aria-label="{$variant.name|escape:'html'}, Art.-Nr. {$variant.artNr|escape:'html'}, {$variant.priceF|escape:'html'}">
                <span class="lpdv-image">
                    <img src="{$variant.img|escape:'html'}"
                         alt="{$variant.name|escape:'html'}"
                         loading="lazy"
                         width="96"
                         height="96">
                    {if $variant.isCurrent}<i aria-hidden="true"></i>{/if}
                </span>
                <span class="lpdv-info">
                    <strong>{$variant.name|escape:'html'}</strong>
                    <small>Art.-Nr. {$variant.artNr|escape:'html'}</small>
                    <span>{$variant.priceF|escape:'html'}</span>
                </span>
            </a>
        {/foreach}
    </div>
</section>
