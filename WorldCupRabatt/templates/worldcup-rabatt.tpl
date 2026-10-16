{* Plugin-Template (aktiv). Quelle/Entwicklung: Tasarim/index.html *}
<div id="worldcup-rabatt-root"
     data-wcr-attempts="{$wcrAttempts|intval}"
     data-wcr-autoopen="{$wcrAutoOpen|intval}"
     data-wcr-code5="{$wcrCode5|escape:'html'}"
     data-wcr-code10="{$wcrCode10|escape:'html'}"
     data-wcr-code15="{$wcrCode15|escape:'html'}"
     data-wcr-preshot-sound="{$wcrPreshotSound|escape:'html'}"
     data-wcr-kick-sound="{$wcrKickSound|escape:'html'}"
     data-wcr-goal-song="{$wcrGoalSong|escape:'html'}">

    <button type="button" class="wcr-launch" aria-label="{$wcrButtonLabel|escape:'html'}">
        <img class="wcr-launch-logo" src="{$wcrLogo|escape:'html'}" alt="FIFA World Cup 2026" aria-hidden="true">
        <span class="wcr-launch-label">{$wcrButtonLabel|escape:'html'}</span>
    </button>

    <div class="wcr-overlay" role="dialog" aria-modal="true" aria-label="{$wcrTitle|escape:'html'}" hidden>
        <div class="wcr-modal">
            <button type="button" class="wcr-close" aria-label="Schließen">&times;</button>

            <div class="wcr-head">
                <div class="wcr-tournament-badge" aria-hidden="true">
                    <span>World Cup</span>
                    <strong>2026</strong>
                </div>
                <h3 class="wcr-title">{$wcrTitle|escape:'html'}</h3>
                {if $wcrIntro}<p class="wcr-intro">{$wcrIntro|escape:'html'}</p>{/if}
            </div>

            <div class="wcr-status">
                <span class="wcr-attempts-wrap">Schuss übrig: <strong class="wcr-attempts-left">3</strong></span>
                <span class="wcr-best-wrap" hidden>Bester Treffer: <strong class="wcr-best">0&nbsp;%</strong></span>
            </div>

            <div class="wcr-stage" aria-hidden="false">
                <div class="wcr-goal">
                    <div class="wcr-net"></div>
                    <div class="wcr-keeper" aria-hidden="true">
                        <div class="wcr-keeper-figure">
                            <span class="wcr-keeper-shadow"></span>
                            <span class="wcr-keeper-arm wcr-keeper-arm-left"><i></i></span>
                            <span class="wcr-keeper-arm wcr-keeper-arm-right"><i></i></span>
                            <span class="wcr-keeper-leg wcr-keeper-leg-left"></span>
                            <span class="wcr-keeper-leg wcr-keeper-leg-right"></span>
                            <span class="wcr-keeper-body"></span>
                            <span class="wcr-keeper-neck"></span>
                            <span class="wcr-keeper-head">
                                <i class="wcr-keeper-hair"></i>
                                <i class="wcr-keeper-face"></i>
                            </span>
                            <span class="wcr-keeper-short"></span>
                        </div>
                    </div>
                    <button type="button" class="wcr-target wcr-target-top-left" data-discount="15" aria-label="15 Prozent Rabatt" tabindex="-1">
                        <span class="wcr-target-label">15%</span>
                    </button>
                    <button type="button" class="wcr-target wcr-target-top-right" data-discount="15" aria-label="15 Prozent Rabatt" tabindex="-1">
                        <span class="wcr-target-label">15%</span>
                    </button>
                    <button type="button" class="wcr-target wcr-target-left" data-discount="10" aria-label="10 Prozent Rabatt" tabindex="-1">
                        <span class="wcr-target-label">10%</span>
                    </button>
                    <button type="button" class="wcr-target wcr-target-right" data-discount="10" aria-label="10 Prozent Rabatt" tabindex="-1">
                        <span class="wcr-target-label">10%</span>
                    </button>
                    <button type="button" class="wcr-target wcr-target-bottom-left" data-discount="5" aria-label="5 Prozent Rabatt" tabindex="-1">
                        <span class="wcr-target-label">5%</span>
                    </button>
                    <button type="button" class="wcr-target wcr-target-bottom-right" data-discount="5" aria-label="5 Prozent Rabatt" tabindex="-1">
                        <span class="wcr-target-label">5%</span>
                    </button>
                </div>
                <div class="wcr-pitch"></div>
                <button type="button" class="wcr-ball" aria-label="Schuss ausführen">⚽</button>
                <div class="wcr-flash" aria-live="polite"></div>
                <div class="wcr-confetti" aria-hidden="true"></div>
            </div>

            <div class="wcr-result" hidden>
                <div class="wcr-result-inner">
                    <div class="wcr-result-emoji" aria-hidden="true">🎉</div>
                    <h4 class="wcr-result-title">Glückwunsch!</h4>
                    <p class="wcr-result-text">Du hast <strong class="wcr-result-discount">0&nbsp;%</strong> Rabatt erspielt.</p>
                    <p class="wcr-upgrade-note" hidden>
                        Die Fans waren von deinen Schüssen so begeistert, dass sie Druck auf die Geschäftsführung gemacht haben:
                        Statt 5&nbsp;% bekommst du jetzt einen 10&nbsp;%-Rabattcode!
                    </p>
                    <button type="button" class="wcr-code" data-wcr-code="" title="Gutscheincode kopieren">
                        <span class="wcr-code-label">Dein Code</span>
                        <span class="wcr-code-value"></span>
                        <span class="wcr-code-hint">Klicken zum Kopieren</span>
                    </button>
                </div>
            </div>

            <div class="wcr-lose" hidden>
                <div class="wcr-result-inner">
                    <div class="wcr-result-emoji" aria-hidden="true">🧤</div>
                    <h4 class="wcr-result-title">Knapp daneben!</h4>
                    <p class="wcr-result-text">Der Torwart hat deinen Schuss gehalten. Jeder Besucher hat nur einen Versuch.</p>
                </div>
            </div>
        </div>
    </div>
</div>
