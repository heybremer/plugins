{assign var=tabs value=$produktcardTabs|default:[]}
{if is_array($tabs) && $tabs|@count > 0}
{* Produktcard: Tabs + Einstellungen aus Dashboard (CSS-Variablen) *}
<div class="container opc-Container produktcard-wrap produktcard-t10-root" data-produktcard-injected="1"
     data-pc-bg-cover="{if !empty($produktcardUseCoverBg)}1{else}0{/if}"
     style="--pc-cols: {$produktcardGridColumns|default:4|intval}; --pc-img-pad: {$produktcardImagePadding|default:8|intval}px; --pc-bg-pct: {$produktcardImageBgSizePercent|default:100|intval}; --pc-aspect: {$produktcardAspectCss|default:'1 / 1'|escape:'html'};">
    <div class="aktion-container t10-container">
        <div class="aktion-banner t10-banner" data-produktcard-tabs="1">
            <div class="t10-head">
                <h2 class="t10-title">{$produktcardTitle|default:'Unsere Modelle'|escape:'html'}</h2>
                {if $tabs|@count > 1}
                <div class="pc-tabs aktion-tabs" role="tablist" aria-label="Kategorien">
                    {foreach from=$tabs item=tab name=pctabs}
                    <button type="button" class="pc-tab-btn aktion-tab-button{if $smarty.foreach.pctabs.first} active{/if}"
                            role="tab" aria-selected="{if $smarty.foreach.pctabs.first}true{else}false{/if}"
                            data-pc-panel="{$tab.id|intval}" id="pc-tab-btn-{$tab.id|intval}">
                        {$tab.label|default:'Tab'|escape:'html'}
                    </button>
                    {/foreach}
                </div>
                {/if}
            </div>

            {foreach from=$tabs item=tab name=pcpanels}
            <div class="pc-tab-panel aktion-tab-content t10-panel{if $smarty.foreach.pcpanels.first} active{/if}"
                 role="tabpanel" id="pc-tab-panel-{$tab.id|intval}"
                 data-pc-panel="{$tab.id|intval}"{if !$smarty.foreach.pcpanels.first} hidden{/if}>
                <div class="produktcard-grid">
                    {foreach from=$tab.products item=it name=pcgrid}
                    <a class="t10-card produktcard-card{if $smarty.foreach.pcgrid.iteration > 4} produktcard-card--folded{/if}" href="{$it.url|escape:'html'}"{if $it.id > 0} data-id="{$it.id|intval}"{/if}
                       aria-label="{$it.title|default:'Modell'|escape:'html'}">
                        <div class="t10-img produktcard-img-bg" style="background-image:url('{$it.img|escape:'html'}');" role="img"></div>
                        <div class="t10-name produktcard-name">
                            {if $it.title|strlen > 0}{$it.title|escape:'html'}{else}&nbsp;{/if}
                        </div>
                    </a>
                    {/foreach}
                </div>
                {if $tab.products|@count > 4}
                <div class="produktcard-load-more-wrap" data-pc-load-more-wrap>
                    <button type="button" class="produktcard-load-more-btn" data-pc-load-more>Weitere Produkte</button>
                </div>
                {/if}
                {if !empty($tab.allUrl)}
                <div class="produktcard-all-wrap">
                    <a class="produktcard-all-btn" href="{$tab.allUrl|escape:'html'}">Alle Produkte</a>
                </div>
                {/if}
            </div>
            {/foreach}
        </div>
    </div>
</div>
{/if}
