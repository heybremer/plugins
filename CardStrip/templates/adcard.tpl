{* Wiederverwendbare Karte. Erwartet $card: variant (A|B|C), title, subtitle, bgColor, tone, image, badge, href, items *}
<a class="cs-card cs-card--{$card.variant} cs-tone-{$card.tone}{if $card.badge} cs-has-tag{/if}" href="{$card.href|escape:'html'}"{if !$card.title && $card.label} aria-label="{$card.label|escape:'html'}"{/if} style="--cs-bg:{$card.bgColor|escape:'html'}">
    {if $card.variant === 'B' && $card.image}
        <img class="cs-bgimg" src="{$card.image|escape:'html'}" alt="{$card.title|escape:'html'}" loading="lazy" decoding="async">
    {/if}
    {if $card.title || $card.subtitle}
        <div class="cs-text">
            {if $card.title}<h3 class="cs-title">{$card.title|escape:'html'}</h3>{/if}
            {if $card.subtitle}<p class="cs-sub">{$card.subtitle|escape:'html'}</p>{/if}
        </div>
    {/if}
    {if $card.variant === 'A' && $card.image}
        <div class="cs-media">
            <img src="{$card.image|escape:'html'}" alt="{$card.title|escape:'html'}" loading="lazy" decoding="async">
        </div>
    {elseif $card.variant === 'C'}
        <div class="cs-grid">
            {foreach $card.items as $it}
                <span class="cs-cell">
                    <img src="{$it.image|escape:'html'}" alt="{$it.alt|escape:'html'}" loading="lazy" decoding="async">
                    {if $it.badge}<em class="cs-disc">{$it.badge|escape:'html'}</em>{/if}
                </span>
            {/foreach}
        </div>
    {/if}
    {if $card.badge}<span class="cs-tag">{$card.badge|escape:'html'}</span>{/if}
</a>
