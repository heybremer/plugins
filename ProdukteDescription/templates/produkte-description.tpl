{* ProdukteDescription: Tabs + manueller Bild-Slider *}
<div class="pd-root" id="pd-root-{$pdKArtikel|intval}" data-pd-k="{$pdKArtikel|intval}">
    <div class="pd-inner">
        <div class="pd-tabs" role="tablist" aria-label="Produktdetails">
            {foreach from=$pdTabs item=tab name=tx}
                <button type="button"
                        class="pd-tab{if $smarty.foreach.tx.first} is-active{/if}"
                        role="tab"
                        id="pd-tab-{$pdKArtikel|intval}-{$smarty.foreach.tx.index}"
                        aria-selected="{if $smarty.foreach.tx.first}true{else}false{/if}"
                        aria-controls="pd-panel-{$pdKArtikel|intval}-{$smarty.foreach.tx.index}"
                        data-pd-tab-index="{$smarty.foreach.tx.index}">
                    {$tab.title|escape:'html'}
                </button>
            {/foreach}
        </div>

        {foreach from=$pdTabs item=tab name=px}
            <div class="pd-panel{if $smarty.foreach.px.first} is-active{else} pd-hidden{/if}"
                 id="pd-panel-{$pdKArtikel|intval}-{$smarty.foreach.px.index}"
                 role="tabpanel"
                 aria-labelledby="pd-tab-{$pdKArtikel|intval}-{$smarty.foreach.px.index}"
                 aria-hidden="{if $smarty.foreach.px.first}false{else}true{/if}"
                 data-pd-panel-index="{$smarty.foreach.px.index}">

                <div class="pd-slider" data-pd-slider>
                    <button type="button" class="pd-nav pd-prev" aria-label="Vorheriges Bild" data-pd-prev>&lsaquo;</button>
                    <div class="pd-viewport">
                        <div class="pd-track" data-pd-track>
                            {foreach from=$tab.slideUrls item=src name=sx}
                                <figure class="pd-slide{if $smarty.foreach.sx.first} is-active{/if}" data-pd-slide="{$smarty.foreach.sx.index}">
                                    <img src="{$src|escape:'html'}" alt="" loading="lazy" decoding="async" width="800" height="600">
                                </figure>
                            {/foreach}
                        </div>
                    </div>
                    <button type="button" class="pd-nav pd-next" aria-label="Nächstes Bild" data-pd-next>&rsaquo;</button>
                </div>

                <div class="pd-dots" role="group" aria-label="Bildauswahl" data-pd-dots>
                    {foreach from=$tab.slideUrls item=src2 name=dx}
                        <button type="button" class="pd-dot{if $smarty.foreach.dx.first} is-active{/if}"
                                data-pd-dot="{$smarty.foreach.dx.index}"
                                aria-label="Bild {$smarty.foreach.dx.iteration}"></button>
                    {/foreach}
                </div>
            </div>
        {/foreach}
    </div>
</div>
