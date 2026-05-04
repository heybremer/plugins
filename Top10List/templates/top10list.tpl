{assign var=tabs value=$t10Tabs|default:[]}
{if is_array($tabs) && $tabs|@count > 0}
<div class="container opc-Container" data-top10list-wrapper="1">
    <div class="aktion-container t10-container" data-top10list="1">
        <div class="aktion-banner t10-banner">
            <div class="t10-head">
                <h2 class="t10-title">{$t10Title|default:'En çok satılan'|escape:'html'}</h2>
                <div class="aktion-tabs t10-tabs">
                    {foreach $tabs as $t}
                        {assign var=i value=$t@iteration}
                        <button type="button" class="aktion-tab-button t10-tab-btn {if $i==1}active{/if}" data-tab="tab{$i}" id="aktion-tab-{$i}">
                            {$t.label|default:'Tab'|escape:'html'}
                        </button>
                    {/foreach}
                </div>
            </div>

            {foreach $tabs as $t}
                {assign var=i value=$t@iteration}
                <div class="aktion-tab-content t10-panel {if $i==1}active{/if}" id="aktion-tab-content-{$i}" data-panel="tab{$i}">
                    <div class="t10-grid">
                        {foreach $t.products|default:[] as $p}
                            <a class="t10-card {if $p@iteration > 3}t10-card--hidden{/if}" href="{$p.url|escape:'html'}">
                                <div class="t10-img" style="background-image:url('{$p.image|escape:'html'}');"></div>
                                <div class="t10-name">{$p.name|escape:'html'}</div>
                                <div class="t10-price">{$p.price|escape:'html'}</div>
                            </a>
                        {/foreach}
                    </div>
                    {if $t.products|@count > 3}
                        <div class="t10-load-more-wrap">
                            <button type="button" class="t10-load-more-btn" data-t10-load-more>Weitere Produkte</button>
                        </div>
                    {/if}
                </div>
            {/foreach}
        </div>
    </div>
</div>
{/if}
