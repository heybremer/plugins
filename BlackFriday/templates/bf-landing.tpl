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
                {if $bfProducts}<a class="bf-landing__btn" href="#bf-l-deals">Top-Deals ansehen &darr;</a>{/if}
                {if $bfCategories}<a class="bf-landing__btn bf-landing__btn--ghost" href="#bf-l-cats">Alle Kategorien &darr;</a>{/if}
            </div>
        </div>
    </section>

    {if $bfProducts}
        <section id="bf-l-deals" class="bf-landing__section">
            <div class="bf-landing__head">
                <h2 class="bf-landing__h2"><span>Top-Deals</span></h2>
                {$has20 = false}{$has30 = false}{$has40 = false}
                {foreach $bfProducts as $p}
                    {if $p.pctN >= 20}{$has20 = true}{/if}
                    {if $p.pctN >= 30}{$has30 = true}{/if}
                    {if $p.pctN >= 40}{$has40 = true}{/if}
                {/foreach}
                <div class="bf-landing__chips" role="group" aria-label="Rabatt filtern">
                    <button type="button" class="bf-chip is-active" data-bf-filter="0">Alle</button>
                    {if $has20}<button type="button" class="bf-chip" data-bf-filter="20">ab -20%</button>{/if}
                    {if $has30}<button type="button" class="bf-chip" data-bf-filter="30">ab -30%</button>{/if}
                    {if $has40}<button type="button" class="bf-chip" data-bf-filter="40">ab -40%</button>{/if}
                </div>
            </div>
            <div class="bf-landing__grid">
                {foreach $bfProducts as $p}
                    <a class="bf-pcard" href="{$p.url|escape:'html'}" data-pct="{$p.pctN}">
                        <span class="bf-pcard__badge">-{$p.pct}%</span>
                        <span class="bf-pcard__img"><img src="{$p.img|escape:'html'}" alt="{$p.name|escape:'html'}" loading="lazy" width="300" height="300"></span>
                        <span class="bf-pcard__body">
                            <span class="bf-pcard__tag">BLACK FRIDAY</span>
                            <span class="bf-pcard__name">{$p.name|escape:'html'}</span>
                            <span class="bf-pcard__prices">
                                <span class="bf-pcard__new">{if $p.from}ab {/if}{$p.new|escape:'html'}</span>
                                {if $p.old !== ''}<del class="bf-pcard__old">{$p.old|escape:'html'}</del>{/if}
                            </span>
                            {if $p.save !== ''}<span class="bf-pcard__save">Du sparst {$p.save|escape:'html'}</span>{/if}
                            <span class="bf-pcard__go">Zum Deal &rarr;</span>
                        </span>
                    </a>
                {/foreach}
            </div>
        </section>
    {/if}

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
