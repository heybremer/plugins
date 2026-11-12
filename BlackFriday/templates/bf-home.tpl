<section id="bf-home" class="bf-home" aria-label="Black Friday Angebote">
    <div class="bf-home__bg" aria-hidden="true"></div>
    <div class="bf-home__inner">
        <div class="bf-home__hero">
            <p class="bf-home__kicker">Nur für kurze Zeit</p>
            <h2 class="bf-home__title"><span>{$bf.homeTitle|escape:'html'}</span></h2>
            {if $bf.max !== '0'}
                <p class="bf-home__max">bis zu <strong>-{$bf.max}%</strong></p>
            {/if}
            {if $bf.homeSub !== ''}<p class="bf-home__sub">{$bf.homeSub|escape:'html'}</p>{/if}
            {if $bf.endIso !== ''}
                <div class="bf-countdown bf-countdown--big" data-bf-end="{$bf.endIso|escape:'html'}" aria-label="Aktion endet in">
                    <div><b data-u="d">00</b><small>Tage</small></div>
                    <div><b data-u="h">00</b><small>Std</small></div>
                    <div><b data-u="m">00</b><small>Min</small></div>
                    <div><b data-u="s">00</b><small>Sek</small></div>
                </div>
            {/if}
        </div>
        {if !empty($bfCategories)}
            <div class="bf-home__grid">
                {foreach $bfCategories as $c}
                    <a class="bf-cat" href="{$c.url|escape:'html'}">
                        <span class="bf-cat__badge">-{$c.pct}%</span>
                        {if $c.img !== ''}
                            <img class="bf-cat__img" src="{$c.img|escape:'html'}" alt="" loading="lazy" width="240" height="160">
                        {else}
                            <span class="bf-cat__img bf-cat__img--empty" aria-hidden="true">%</span>
                        {/if}
                        <span class="bf-cat__name">{$c.name|escape:'html'}</span>
                        <span class="bf-cat__go">Zu den Deals &rarr;</span>
                    </a>
                {/foreach}
            </div>
        {/if}
    </div>
</section>
