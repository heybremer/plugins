<section id="cs-strip" class="cs-strip" aria-label="{if $csTitle}{$csTitle|escape:'html'}{else}Angebote{/if}">
    <div class="container">
        {if $csTitle}<h2 class="cs-heading">{$csTitle|escape:'html'}</h2>{/if}
        <div class="cs-wrap">
            <div class="cs-track" tabindex="0">
                {foreach $csCards as $card}
                    {include file=$csCardTpl card=$card}
                {/foreach}
            </div>
            <button type="button" class="cs-arrow cs-arrow--prev" aria-label="Zur&uuml;ck" hidden>&#8249;</button>
            <button type="button" class="cs-arrow cs-arrow--next" aria-label="Weiter">&#8250;</button>
        </div>
    </div>
    <script>
    {literal}
    (function () {
        var root = document.getElementById('cs-strip');
        if (!root) { return; }
        var track = root.querySelector('.cs-track');
        var prev = root.querySelector('.cs-arrow--prev');
        var next = root.querySelector('.cs-arrow--next');
        function step() {
            var c = track.querySelector('.cs-card');
            if (!c) { return 291; }
            var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 6;
            return c.offsetWidth + gap;
        }
        function sync() {
            var max = track.scrollWidth - track.clientWidth - 2;
            prev.hidden = track.scrollLeft <= 2;
            next.hidden = track.scrollLeft >= max;
        }
        next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });
        prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
        track.addEventListener('scroll', function () { window.requestAnimationFrame(sync); }, { passive: true });
        window.addEventListener('resize', sync);
        sync();
    })();
    {/literal}
    </script>
</section>
