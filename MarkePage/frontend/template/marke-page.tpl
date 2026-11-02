{* MarkePage: Produktseite pro Marke (Artikelnummern aus dem Backend) *}
<link rel="stylesheet" href="{$mpCssUrl}">
<div class="mp-page">
    {if $mpPage !== null}
        {* Die H1 (Seitentitel) liefert bereits das Theme. *}
        {if $mpIntroHtml !== ''}
            <div class="mp-intro">{$mpIntroHtml nofilter}</div>
        {/if}
        {if $mpProducts|count > 0}
            <div class="row product-list layout-gallery mp-grid" id="product-list">
                {foreach $mpProducts as $Artikel}
                    <div class="col-6 col-md-4 col-xl-3 product-wrapper">
                        {include file='productlist/item_box.tpl' tplscope='gallery'}
                    </div>
                {/foreach}
            </div>
        {else}
            <div class="alert alert-info">Für diese Seite sind aktuell keine Produkte hinterlegt.</div>
        {/if}
    {else}
        <div class="alert alert-info">Diese Seite ist nicht verfügbar.</div>
    {/if}
</div>
