{assign var=items value=$flItems|default:[]}
{if !is_array($items) || $items|@count < 4}
    {assign var=items value=[]}
{/if}

<div class="container opc-Container">
<div class="fl-wrap" data-fourlayout="1">
    <div class="fl-grid">
        {foreach $items as $it}
            {assign var=rating value=$it.rating|default:0}
            <a class="fl-card" href="{$it.url|default:'#'|escape:'html'}">
                <div class="fl-media" {if $it.image|default:'' != ''}style="background-image:url('{$it.image|escape:'html'}');"{/if}></div>
                <div class="fl-overlay"></div>
                <div class="fl-content">
                    {if $rating|intval > 0}
                        <div class="fl-rating" aria-label="Rating">
                            {section name=s start=1 loop=$rating|intval+1}
                                <span class="fl-star">★</span>
                            {/section}
                        </div>
                    {/if}
                    <div class="fl-title">{$it.title|default:''|escape:'html'}</div>
                    {if $it.subtitle|default:'' != ''}<div class="fl-subtitle">{$it.subtitle|escape:'html'}</div>{/if}
                    {if $it.button|default:'' != ''}<div class="fl-btn">{$it.button|escape:'html'}</div>{/if}
                </div>
            </a>
        {/foreach}
    </div>
</div>
</div>

