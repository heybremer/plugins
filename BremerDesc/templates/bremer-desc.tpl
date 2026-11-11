{* BremerDesc — Multi-tab Video + Bilder Showcase *}
{if $bdTabs|@count > 0}
{if $bdUseContainer|default:false}
<div class="container opc-Container">
{/if}
<div class="bd-showcase" id="bd-showcase-{$bdKArtikel|intval}">

  {* ── TAB BAR ──────────────────────────────────────────────── *}
  <div class="bd-showcase__tabbar" role="tablist" aria-label="Produktdetails">
    {foreach from=$bdTabs item=bdTab name=tl}
    <button class="bd-showcase__tab{if $smarty.foreach.tl.first} bd-showcase__tab--active{/if}"
            role="tab"
            aria-selected="{if $smarty.foreach.tl.first}true{else}false{/if}"
            aria-controls="bd-panel-{$bdKArtikel|intval}-{$smarty.foreach.tl.index}"
            data-tab="{$smarty.foreach.tl.index}">
      {if $bdTab.type === 'video'}
        <svg class="bd-showcase__tab-icon" viewBox="0 0 20 20" fill="currentColor"><polygon points="7,4 17,10 7,16"/></svg>
      {else}
        <svg class="bd-showcase__tab-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3" width="16" height="13" rx="2"/><path d="M2 7h16"/></svg>
      {/if}
      {$bdTab.label|escape:'html'}
    </button>
    {/foreach}
  </div>

  {* ── PANELS ───────────────────────────────────────────────── *}
  {foreach from=$bdTabs item=bdTab name=tl}
  <div class="bd-showcase__panel{if $smarty.foreach.tl.first} bd-showcase__panel--active{/if}"
       id="bd-panel-{$bdKArtikel|intval}-{$smarty.foreach.tl.index}"
       role="tabpanel"
       {if !$smarty.foreach.tl.first}hidden{/if}>

    {if $bdTab.type === 'video' && $bdTab.videoId !== ''}
    {* ── VIDEO ──────────────────────────────────────────────── *}
    <div class="bd-showcase__video-wrap-outer">
      <div class="bd-showcase__video-wrap">
        {* YouTube-Player (~1 MB JS) erst nach Klick laden *}
        <button type="button" class="bd-showcase__yt"
                data-bd-yt="{$bdTab.videoId|escape:'html'}"
                data-bd-yt-title="{$bdTab.label|escape:'html'}"
                aria-label="Video abspielen: {$bdTab.label|escape:'html'}">
          <img src="https://i.ytimg.com/vi/{$bdTab.videoId|escape:'html'}/hqdefault.jpg"
               alt="" width="480" height="360" loading="lazy" decoding="async">
          <span class="bd-showcase__yt-play" aria-hidden="true">
            <svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.5 7.7a8.5 8.5 0 0 0-6-6C55.2.3 34 .3 34 .3S12.8.3 7.5 1.7a8.5 8.5 0 0 0-6 6C.1 13 .1 24 .1 24s0 11 1.4 16.3a8.5 8.5 0 0 0 6 6C12.8 47.7 34 47.7 34 47.7s21.2 0 26.5-1.4a8.5 8.5 0 0 0 6-6C67.9 35 67.9 24 67.9 24s0-11-1.4-16.3z" fill="#f00"/><path d="M45 24 27 14v20z" fill="#fff"/></svg>
          </span>
        </button>
      </div>
    </div>

    {elseif $bdTab.type === 'images' && $bdTab.images|@count > 0}
    {* ── BILDER ─────────────────────────────────────────────── *}
    <div class="bd-showcase__image-panel">
      <div class="bd-showcase__slider" data-bd-slider>
        <button type="button" class="bd-showcase__arrow bd-showcase__arrow--prev"
                data-bd-prev aria-label="Zurück">&#8249;</button>
        <div class="bd-showcase__viewport">
          <div class="bd-showcase__track">
            {foreach from=$bdTab.images item=src name=il}
            <figure class="bd-showcase__slide{if $smarty.foreach.il.first} bd-showcase__slide--active{/if}"
                    style="margin:0">
              <img src="{$src|escape:'html'}"
                   alt="{$bdTab.label|escape:'html'} {$smarty.foreach.il.iteration}"
                   loading="lazy" decoding="async">
            </figure>
            {/foreach}
          </div>
        </div>
        <button type="button" class="bd-showcase__arrow bd-showcase__arrow--next"
                data-bd-next aria-label="Weiter">&#8250;</button>
      </div>
      <div class="bd-showcase__dots" data-bd-dots aria-label="Bildauswahl"></div>
    </div>
    {/if}

  </div>
  {/foreach}
</div>
{if $bdUseContainer|default:false}
</div>
{/if}
{/if}
