<section id="cs-recs" class="cs-recs cs-recs--{$csContext}">
    <h2 class="cs-recs__title h3">{$csTitle|escape:'html'}</h2>
    <div class="cs-recs__grid">
        {foreach $csItems as $p}
            <div class="cs-card">
                <a class="cs-card__img" href="{$p.url|escape:'html'}">
                    {if $p.image !== ''}<img loading="lazy" src="{$p.image|escape:'html'}" alt="{$p.name|escape:'html'}">{/if}
                </a>
                <a class="cs-card__name" href="{$p.url|escape:'html'}">{$p.name|escape:'html'}</a>
                {if $p.price !== ''}<div class="cs-card__price">{$p.price|escape:'html'}</div>{/if}
                {if $p.buyable}
                    <form method="post" action="{$csShopUrl}/" class="cs-card__form">
                        {$csTokenInput}
                        <input type="hidden" name="a" value="{$p.id}">
                        <input type="hidden" name="wke" value="1">
                        <input type="hidden" name="anzahl" value="1">
                        <button type="submit" class="btn btn-primary btn-sm">In den Warenkorb</button>
                    </form>
                {else}
                    <a class="btn btn-outline-primary btn-sm" href="{$p.url|escape:'html'}">Ansehen</a>
                {/if}
            </div>
        {/foreach}
    </div>
</section>
