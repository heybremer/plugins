<section id="pl-launch" class="pl-launch" aria-label="{$plSectionTitle|escape:'html'}">
    <div class="container">
        <div class="pl-head">
            <span class="pl-kicker">{$plSectionTitle|escape:'html'}</span>
            {if $plItems|@count > 1}
                <div class="pl-nav">
                    <button type="button" class="pl-arrow" data-pl-dir="-1" aria-label="Zur&uuml;ck">&#8249;</button>
                    <button type="button" class="pl-arrow" data-pl-dir="1" aria-label="Weiter">&#8250;</button>
                </div>
            {/if}
        </div>
        <div class="pl-track" tabindex="0">
            {foreach $plItems as $it}
                <article class="pl-card">
                    <a class="pl-media" href="{$it.url|escape:'html'}" tabindex="-1">
                        {if $it.image}
                            <img src="{$it.image|escape:'html'}" alt="{$it.name|escape:'html'}" loading="lazy" decoding="async">
                        {/if}
                        <span class="pl-badge">{$it.badge|escape:'html'}</span>
                    </a>
                    <div class="pl-body">
                        {if $it.catName}
                            <a class="pl-cat" href="{$it.catUrl|escape:'html'}">{$it.catName|escape:'html'}</a>
                        {/if}
                        <h2 class="pl-title"><a href="{$it.url|escape:'html'}">{$it.title|escape:'html'}</a></h2>
                        {if $it.text}
                            <p class="pl-text">{$it.text|escape:'html'}</p>
                        {/if}
                        {if $it.price}
                            <div class="pl-price">{$it.price|escape:'html'}</div>
                        {/if}
                        <div class="pl-actions">
                            {if $it.url}
                                <a class="pl-btn pl-btn--primary" href="{$it.url|escape:'html'}">{$it.button|escape:'html'} &rarr;</a>
                            {/if}
                            {if $it.catUrl && $it.catName && $it.catUrl != $it.url}
                                <a class="pl-btn pl-btn--ghost" href="{$it.catUrl|escape:'html'}">Alle in {$it.catName|escape:'html'}</a>
                            {/if}
                        </div>
                    </div>
                </article>
            {/foreach}
        </div>
        {if $plItems|@count > 1}
            <div class="pl-dots" role="tablist">
                {foreach $plItems as $it}
                    <button type="button" class="pl-dot{if $it@first} is-active{/if}" data-pl-go="{$it@index}" aria-label="{$it.title|escape:'html'}"></button>
                {/foreach}
            </div>
            <script>
            {literal}
            (function () {
                var root = document.getElementById('pl-launch');
                if (!root) { return; }
                var track = root.querySelector('.pl-track');
                var cards = root.querySelectorAll('.pl-card');
                var dots = root.querySelectorAll('.pl-dot');
                var idx = 0, timer = null;
                function go(i) {
                    idx = (i + cards.length) % cards.length;
                    track.scrollTo({ left: cards[idx].offsetLeft - track.offsetLeft, behavior: 'smooth' });
                }
                function sync() {
                    var w = track.clientWidth || 1;
                    idx = Math.round(track.scrollLeft / w);
                    dots.forEach(function (d, n) { d.classList.toggle('is-active', n === idx); });
                }
                function start() { stop(); timer = setInterval(function () { go(idx + 1); }, 7000); }
                function stop() { if (timer) { clearInterval(timer); timer = null; } }
                track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
                dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.getAttribute('data-pl-go')); start(); }); });
                root.querySelectorAll('.pl-arrow').forEach(function (b) {
                    b.addEventListener('click', function () { go(idx + (+b.getAttribute('data-pl-dir'))); start(); });
                });
                root.addEventListener('mouseenter', stop);
                root.addEventListener('mouseleave', start);
                root.addEventListener('focusin', stop);
                start();
            })();
            {/literal}
            </script>
        {/if}
    </div>
</section>
