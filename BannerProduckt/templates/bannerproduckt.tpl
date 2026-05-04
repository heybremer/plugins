{assign var=banners value=$bpBanners|default:[]}
{if !is_array($banners) || $banners|@count < 5}
    {assign var=banners value=[]}
{/if}

<div class="container opc-Container">
<div class="bp-wrap" data-bannerproduckt="1">
    <div class="bp-grid">
        {foreach $banners as $b}
            {assign var=i value=$b@iteration}
            {assign var=variant value=$b.variant|default:''}
            {if $i==3}{assign var=variant value='tall'}{/if}
            <a class="bp-card bp-{$variant|escape:'html'} bp-pos-{$i}" href="{$b.url|default:'#'|escape:'html'}">
                <div class="bp-content">
                    {if $b.eyebrow|default:'' != ''}<div class="bp-eyebrow">{$b.eyebrow|escape:'html'}</div>{/if}
                    <div class="bp-title">{$b.title|default:''|escape:'html'}</div>
                    <div class="bp-subtitle">{$b.subtitle|default:''|escape:'html'}</div>
                    {if $i==3 && $b.cta|default:'' != ''}
                        <div class="bp-cta">{$b.cta|escape:'html'}</div>
                    {/if}
                </div>
                {if $b.image|default:'' != ''}
                    <div class="bp-media" style="background-image:url('{$b.image|escape:'html'}');"></div>
                {/if}
            </a>
        {/foreach}
    </div>
</div>
