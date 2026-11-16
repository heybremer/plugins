<div id="bf-landing" class="bf-landing">
    <section class="bf-landing__hero" aria-label="Black Friday">
        <div class="bf-landing__hero-bg" aria-hidden="true"></div>
        <div class="bf-landing__hero-inner">
            <p class="bf-landing__kicker"><span>Nur für kurze Zeit</span></p>
            <h1 class="bf-landing__title">BLACK <span>FRIDAY</span></h1>
            {if $bf.max !== '0'}
                <p class="bf-landing__max">bis zu <strong>-{$bf.max}%</strong> auf ausgewählte Sitzbezüge</p>
            {/if}
            {if $bf.homeSub !== ''}<p class="bf-landing__sub">{$bf.homeSub|escape:'html'}</p>{/if}
            {if $bf.endIso !== ''}
                <div class="bf-countdown bf-countdown--big bf-landing__countdown" data-bf-end="{$bf.endIso|escape:'html'}" aria-label="Aktion endet in">
                    <div><b data-u="d">00</b><small>Tage</small></div>
                    <div><b data-u="h">00</b><small>Std</small></div>
                    <div><b data-u="m">00</b><small>Min</small></div>
                    <div><b data-u="s">00</b><small>Sek</small></div>
                </div>
            {/if}
            <div class="bf-landing__ctas">
                <a class="bf-landing__btn" href="#bf-l-finder">Fahrzeug wählen &darr;</a>
                {if $bfCategories}<a class="bf-landing__btn bf-landing__btn--ghost" href="#bf-l-cats">Alle Kategorien &darr;</a>{/if}
            </div>
        </div>
    </section>

    <section id="bf-l-finder" class="bf-landing__section bf-landing__finder">
        <h2 class="bf-landing__h2"><span>Passend für Ihr Fahrzeug</span></h2>
        <p class="bf-landing__finder-sub">Fahrzeug wählen und direkt die passenden Black-Friday-Sitzbezüge sehen.</p>
        <div class="bf-landing__finder-box"><div id="bf-micro-slot"></div></div>
    </section>

    {if $bfCategories}
        <section id="bf-l-cats" class="bf-landing__section bf-landing__section--cats">
            <h2 class="bf-landing__h2"><span>Alle Black-Friday-Kategorien</span></h2>
            {foreach $bfCategories as $g}
                <div class="bf-home__group">
                    <h3 class="bf-home__group-title"><span>{$g.title|escape:'html'}</span></h3>
                    <div class="bf-home__grid">
                        {foreach $g.items as $c}
                            <a class="bf-cat" href="{$c.url|escape:'html'}">
                                <span class="bf-cat__badge">-{$c.pct}%</span>
                                {if $c.img !== ''}
                                    <img class="bf-cat__img" src="{$c.img|escape:'html'}" alt="" loading="lazy" width="240" height="160">
                                {else}
                                    <span class="bf-cat__img bf-cat__img--empty" aria-hidden="true">%</span>
                                {/if}
                                <span class="bf-cat__top">{$g.title|escape:'html'}</span>
                                <span class="bf-cat__name">{$c.name|escape:'html'}</span>
                                {if $c.sub !== ''}<span class="bf-cat__sub">{$c.sub|escape:'html'}</span>{/if}
                                <span class="bf-cat__go">Zu den Deals &rarr;</span>
                            </a>
                        {/foreach}
                    </div>
                </div>
            {/foreach}
        </section>
    {/if}

    <section class="bf-landing__konfig">
        <div>
            <h2>Nicht sicher, welcher Bezug passt?</h2>
            <p>Mit unserem Konfigurator finden Sie in wenigen Klicks die passenden Sitzbezüge für Ihr Fahrzeug &ndash; inklusive Black-Friday-Preis.</p>
        </div>
        <a class="bf-landing__btn" href="{$bfShopUrl}/#womosi-fk-konfigurator-anchor">Zum Konfigurator &rarr;</a>
    </section>
</div>
