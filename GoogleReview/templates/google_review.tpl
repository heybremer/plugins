<div class="container opc-Container">
<div class="google-review-section" data-googlereview="1">
    <div class="bremer-grw-slider">
        <div class="bremer-grw-reviews-container">
            <!-- Summary Card -->
            <div class="bremer-grw-summary-card">
                <div class="bremer-grw-business-header">
                    <div class="bremer-grw-business-google-logo">
                        <svg class="bremer-grw-google-logo-img" viewBox="0 0 100 32" xmlns="http://www.w3.org/2000/svg">
                            <text x="0" y="20" font-family="Arial" font-size="18" font-weight="bold" fill="#4285f4">G</text>
                            <text x="12" y="20" font-family="Arial" font-size="18" font-weight="bold" fill="#ea4335">o</text>
                            <text x="22" y="20" font-family="Arial" font-size="18" font-weight="bold" fill="#fbbc05">o</text>
                            <text x="32" y="20" font-family="Arial" font-size="18" font-weight="bold" fill="#4285f4">g</text>
                            <text x="42" y="20" font-family="Arial" font-size="18" font-weight="bold" fill="#34a853">l</text>
                            <text x="50" y="20" font-family="Arial" font-size="18" font-weight="bold" fill="#ea4335">e</text>
                        </svg>
                    </div>
                    <h2 class="bremer-grw-business-name">{$googleReviewSettings.heading|default:'Ihr Unternehmen'|escape:'html'}</h2>
                </div>

                <div class="bremer-grw-rating-section">
                    <div class="bremer-grw-rating-number">
                        {$googleReviewSettings.rating|default:4.5|escape:'html'}
                        <div class="bremer-grw-stars" aria-label="{$googleReviewSettings.rating|default:4.5} Sterne">
                            {section name=s loop=5}
                                {if $smarty.section.s.index < $googleReviewSettings.ratingFull}
                                    <span class="bremer-grw-star bremer-grw-star-filled">★</span>
                                {elseif $smarty.section.s.index == $googleReviewSettings.ratingFull && !empty($googleReviewSettings.ratingHalf)}
                                    <span class="bremer-grw-star bremer-grw-star-half">★</span>
                                {else}
                                    <span class="bremer-grw-star" style="color: #dadce0; opacity: 0.4;">★</span>
                                {/if}
                            {/section}
                        </div>
                    </div>
                    <div class="bremer-grw-review-count">{$googleReviewSettings.sub|default:'Basierend auf 500 Bewertungen'|escape:'html'}</div>
                </div>

                <div class="bremer-grw-action-buttons">
                    {if $googleReviewSettings.writeUrl|strlen > 0}
                        <a href="{$googleReviewSettings.writeUrl|escape:'html'}" class="bremer-grw-btn-primary" target="_blank" rel="noopener">Bewertung schreiben</a>
                    {/if}
                    {if $googleReviewSettings.allUrl|strlen > 0}
                        <a href="{$googleReviewSettings.allUrl|escape:'html'}" class="bremer-grw-btn-secondary" target="_blank" rel="noopener">Alle ansehen</a>
                    {/if}
                </div>

                <div class="bremer-grw-last-review">
                    Letzte Bewertung:
                    <span class="bremer-grw-verified">{$googleReviewLastTimeText|escape:'html'}</span>
                </div>
            </div>

            <!-- Reviews Slider -->
            <div class="bremer-grw-reviews-slider">
                <div class="bremer-grw-slider-container">
                    <button id="bremerPrevBtn" class="bremer-grw-slider-btn bremer-grw-prev-btn" type="button" aria-label="Vorherige Bewertung">&lsaquo;</button>
                    <button id="bremerNextBtn" class="bremer-grw-slider-btn bremer-grw-next-btn" type="button" aria-label="N&auml;chste Bewertung">&rsaquo;</button>
                    <div id="bremerReviewsTrack" class="bremer-grw-reviews-track" role="region" aria-label="Google Bewertungen">
                        {foreach $googleReviewItems as $it}
                            <article class="bremer-grw-review-card">
                                <div class="bremer-grw-review-rating">
                                    <div class="bremer-grw-review-stars" aria-label="{$it.stars} von 5 Sternen">
                                        {section name=s loop=5}
                                            {if $smarty.section.s.index < $it.stars}
                                                <span class="bremer-grw-star bremer-grw-star-filled">★</span>
                                            {else}
                                                <span class="bremer-grw-star" style="color: #dadce0; opacity: 0.4;">★</span>
                                            {/if}
                                        {/section}
                                    </div>
                                </div>
                                <div class="bremer-grw-review-text">{$it.text|escape:'html'}</div>
                                <div class="bremer-grw-reviewer-info">
                                    <div class="bremer-grw-reviewer-details">
                                        <div class="bremer-grw-reviewer-name">{$it.author|escape:'html'}</div>
                                        <div class="bremer-grw-review-date">{$it.time|escape:'html'}</div>
                                    </div>
                                    <div class="bremer-grw-google-logo" role="img" aria-label="Google Bewertung">
                                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                                            <path fill="#4285f4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34a853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#fbbc05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                            <path fill="#ea4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                        </svg>
                                    </div>
                                </div>
                            </article>
                        {/foreach}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
