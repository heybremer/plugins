<div id="cs-offer" class="cs-offer">
    <div class="cs-offer__title">Dazu passend &ndash; m&ouml;chten Sie das gleich mitbestellen?</div>
    {foreach $csOfferItems as $p}
        <label class="cs-offer__row">
            <input type="checkbox" class="cs-offer__check" value="{$p.id}">
            {if $p.image !== ''}<img class="cs-offer__img" loading="lazy" src="{$p.image|escape:'html'}" alt="">{/if}
            <span class="cs-offer__name">{$p.name|escape:'html'}</span>
            {if $p.price !== ''}<strong class="cs-offer__price">+ {$p.price|escape:'html'}</strong>{/if}
            <a class="cs-offer__link" href="{$p.url|escape:'html'}" target="_blank" rel="noopener">Ansehen</a>
        </label>
    {/foreach}
    <script>
        (function () {
            var box = document.getElementById('cs-offer');
            var form = document.getElementById('buy_form');
            if (!box || !form) { return; }
            var busy = false;
            box.querySelectorAll('.cs-offer__link').forEach(function (a) {
                a.addEventListener('click', function (e) { e.stopPropagation(); });
            });
            form.addEventListener('submit', function (e) {
                if (busy) { return; }
                var checks = [].slice.call(box.querySelectorAll('.cs-offer__check:checked'));
                if (!checks.length || !form.checkValidity()) { return; }
                var tokenEl = form.querySelector('input[name="jtl_token"]');
                if (!tokenEl) { return; }
                e.preventDefault();
                e.stopImmediatePropagation();
                busy = true;
                var submitter = e.submitter || null;
                var send = function (c) {
                    var fd = new FormData();
                    fd.set('jtl_token', tokenEl.value);
                    fd.set('a', c.value);
                    fd.set('wke', '1');
                    fd.set('anzahl', '1');
                    return fetch('{$csShopUrl}/', { method: 'POST', body: fd, credentials: 'same-origin' });
                };
                var chain = Promise.resolve();
                checks.forEach(function (c) { chain = chain.then(function () { return send(c); }); });
                var proceed = function () {
                    checks.forEach(function (c) { c.checked = false; });
                    if (form.requestSubmit) { form.requestSubmit(submitter || undefined); } else { form.submit(); }
                    busy = false;
                };
                chain.then(proceed, proceed);
            }, true);
        })();
    </script>
</div>
