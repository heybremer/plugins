{if isset($apActive) && (int)$apActive === 0}
<div data-actionproduct-disabled="1" style="display:none;"></div>
{else}
<div class="videoblinder">
<div class="container opc-Container" data-actionproduct="1">
    <div class="aktion-container">
        <div class="aktion-banner">
            {assign var=tabs value=$apTabs|default:[]}
            {if !is_array($tabs) || $tabs|@count == 0}
                {assign var=tabs value=[]}
            {/if}

            <div class="aktion-tabs">
                {foreach $tabs as $t}
                    {assign var=idx value=$t@iteration}
                    <button class="aktion-tab-button {if $idx==1}active{/if}" data-tab="tab{$idx}" id="aktion-tab-{$idx}">
                        {$t.tabTitle|default:'Produkt des Monats'|escape:'html'}
                    </button>
                {/foreach}
            </div>

            {foreach $tabs as $t}
                {assign var=idx value=$t@iteration}
                {assign var=suf value=''}
                {if $idx>1}{assign var=suf value="-`$idx`"}{/if}
                {assign var=images value=$t.images|default:[]}

                <div class="aktion-tab-content {if $idx==1}active{/if}" id="aktion-tab-content-{$idx}"
                     data-price-old="{$t.priceOld|default:0}"
                     data-price-new="{$t.priceNew|default:0}"
                     data-total-stock="{$t.totalStock|default:0}"
                     data-sold-count="{$t.soldCount|default:0}"
                     data-urgency-active="{$t.urgencyActive|default:1}"
                     data-urgency-text="{$t.urgencyText|default:'Nur noch {remain} Stück verfügbar!'|escape:'html'}"
                     data-kartikel="{$t.kArtikel|default:0}"
                     data-coupon-code="{$t.couponCode|default:''|escape:'html'}"
                     data-coupon-wert="{$t.couponWert|default:0}"
                     data-coupon-typ="{$t.couponTyp|default:''|escape:'html'}">
                    <div class="aktion-content">
                        <div class="aktion-left">
                            <div class="aktion-image-gallery">
                                <div class="aktion-slider-wrapper">
                                    <div class="aktion-slider-container" id="aktion-slider-container{$suf}">
                                        {assign var=apBuyUrl value=$t.buyUrl|default:''|trim}
                                        {foreach $images as $img}
                                            <div class="aktion-slide {if $img@iteration==1}active{/if}">
                                                {if $apBuyUrl != '' && $apBuyUrl != '#'}
                                                <a href="{$apBuyUrl|escape:'html'}" class="aktion-slide-link" aria-label="Zum Produkt">
                                                    <img src="{$img|escape:'html'}" alt="Produktbild {$img@iteration}" class="aktion-product-image">
                                                </a>
                                                {else}
                                                <img src="{$img|escape:'html'}" alt="Produktbild {$img@iteration}" class="aktion-product-image">
                                                {/if}
                                            </div>
                                        {/foreach}
                                    </div>
                                    <button class="aktion-slider-btn aktion-slider-prev" id="aktion-slider-prev{$suf}" type="button" aria-label="Vorheriges Bild">&lsaquo;</button>
                                    <button class="aktion-slider-btn aktion-slider-next" id="aktion-slider-next{$suf}" type="button" aria-label="Nächstes Bild">&rsaquo;</button>
                                    <div class="aktion-slider-dots" id="aktion-slider-dots{$suf}"></div>
                                </div>

                                <div class="aktion-thumbnails">
                                    {foreach $images as $img}
                                        <div class="aktion-thumbnail {if $img@iteration==1}active{/if}" data-index="{$img@index}">
                                            <img src="{$img|escape:'html'}" alt="Thumbnail {$img@iteration}">
                                        </div>
                                    {/foreach}
                                </div>
                            </div>
                        </div>

                        <div class="aktion-right">
                            <div class="aktion-product-header">
                                <div class="aktion-badge">{$t.badge|default:'Produkt des Monats'|escape:'html'}</div>
                                <div class="aktion-countdown" id="aktion-countdown{$suf}" data-countdown-date="{$t.countdownIso|default:''|escape:'html'}">
                                    <div class="aktion-countdown-label">Angebot endet in:</div>
                                    <div class="aktion-countdown-timer">
                                        <div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="days" id="aktion-countdown-days{$suf}">00</span> <span class="aktion-countdown-label-small">Tage</span></div>
                                        <div class="aktion-countdown-separator">:</div>
                                        <div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="hours" id="aktion-countdown-hours{$suf}">00</span> <span class="aktion-countdown-label-small">Stunden</span></div>
                                        <div class="aktion-countdown-separator">:</div>
                                        <div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="minutes" id="aktion-countdown-minutes{$suf}">00</span> <span class="aktion-countdown-label-small">Minuten</span></div>
                                        <div class="aktion-countdown-separator">:</div>
                                        <div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="seconds" id="aktion-countdown-seconds{$suf}">00</span> <span class="aktion-countdown-label-small">Sekunden</span></div>
                                    </div>
                                </div>
                                <h1 class="aktion-product-name" id="aktion-product-name{$suf}">{$t.name|default:''|escape:'html'}</h1>
                                <p class="aktion-product-desc" id="aktion-product-desc{$suf}">{$t.desc|default:''|escape:'html'}</p>
                            </div>

                            <div class="aktion-pricing">
                                <div class="aktion-price-wrapper">
                                    <div class="aktion-price-old" id="aktion-price-old{$suf}"></div>
                                    <div class="aktion-price-new" id="aktion-price-new{$suf}"></div>
                                </div>
                                <div class="aktion-discount-badge" id="aktion-discount-badge{$suf}"></div>
                            </div>

                            <div class="aktion-stock-info">
                                <div class="aktion-stock-header">
                                    <span class="aktion-stock-label">Verfügbar</span>
                                    <span class="aktion-stock-numbers" style="display:none;">
                                        <span class="aktion-sold-count" id="aktion-sold-count{$suf}"></span>
                                        <span class="aktion-stock-separator">/</span>
                                        <span class="aktion-total-count" id="aktion-total-count{$suf}"></span>
                                    </span>
                                </div>
                                <div class="aktion-progress-bar-wrapper">
                                    <div class="aktion-progress-bar" id="aktion-progress-bar{$suf}">
                                        <div class="aktion-progress-fill" id="aktion-progress-fill{$suf}"></div>
                                    </div>
                                </div>
                                <div class="aktion-stock-footer">
                                    <span class="aktion-sold-percentage" id="aktion-sold-percentage{$suf}"></span>
                                    <span class="aktion-remaining-count" id="aktion-remaining-count{$suf}"></span>
                                </div>
                            </div>

                            <div class="aktion-urgency" id="aktion-urgency{$suf}">
                                <span class="aktion-alarm-icon">⏰</span>
                                <span class="aktion-urgency-text" id="aktion-urgency-text{$suf}"></span>
                            </div>

                            <button class="aktion-buy-button" id="aktion-buy-button{$suf}" type="button" data-buy-url="{$t.buyUrl|default:'#'|escape:'html'}">Produktdetails</button>
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>
</div>
{/if}
