<div id="bf-topbar" class="bf-topbar" role="region" aria-label="Black Friday">
    <div class="bf-topbar__inner">
        <span class="bf-topbar__tag">BLACK FRIDAY</span>
        <span class="bf-topbar__text">{$bf.topText|escape:'html'}</span>
        {if $bf.endIso !== ''}
            <span class="bf-countdown bf-countdown--mini" data-bf-end="{$bf.endIso|escape:'html'}" aria-label="Aktion endet in">
                <b data-u="d">00</b>T <b data-u="h">00</b>:<b data-u="m">00</b>:<b data-u="s">00</b>
            </span>
        {/if}
        <a class="bf-topbar__cta" href="{$bf.buttonUrl|escape:'html'}">{$bf.buttonLabel|escape:'html'} &rarr;</a>
    </div>
</div>
