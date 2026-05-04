<!-- CardProduct injected -->
<div class="container opc-Container" data-cardproduct-injected="1">
<div class="cardproduct-grid-pen">
    <div class="cardproduct-title-row">
        <div class="cardproduct-title-texts">
            <h2 class="cardproduct-heading">Fahrer- &amp; Beifahrer-Set (1+1)</h2>
            <p class="cardproduct-subheading">Unsere Modelle f&uuml;r Wohnmobil-Sitzbez&uuml;ge</p>
            {if isset($cardProductProdukttypItems) && $cardProductProdukttypItems|@count > 0 && isset($cardProductProdukttypSummary) && $cardProductProdukttypSummary ne ''}
            <details class="cardproduct-produkttyp-details">
                <summary class="cardproduct-produkttyp-summary">{$cardProductProdukttypSummary|escape:'html'}</summary>
                <div class="cardproduct-produkttyp-panel">
                    <ul class="cardproduct-produkttyp-list">
                        {foreach $cardProductProdukttypItems as $pt}
                        <li class="cardproduct-produkttyp-row" role="button" tabindex="0" data-kk="{$pt.kKategorie|intval}" data-ka="{$pt.kArtikel|intval}">
                            <span class="cardproduct-produkttyp-cat">{$pt.categoryName|escape:'html'}</span>
                            <span class="cardproduct-produkttyp-sep"> · </span>
                            <span class="cardproduct-produkttyp-pt">{if $pt.produkttyp|strlen > 0}{$pt.produkttyp|escape:'html'}{else}—{/if}</span>
                        </li>
                        {/foreach}
                    </ul>
                    <button type="button" class="cardproduct-produkttyp-reset">Alle Modelle anzeigen</button>
                </div>
            </details>
            {/if}
        </div>
        <a class="cardproduct-all-models-btn" href="{$cardProductAllModelsUrl|escape:'html'}">Alle Modelle ansehen</a>
    </div>
    <div class="cardproduct-grid">
        {foreach $cardProductCards as $card}
            <a class="cardproduct-item{if $card@index >= 3} cardproduct-item--folded{/if}" href="{$card.url|escape:'html'}"{if isset($card.kArtikel)} data-kartikel="{$card.kArtikel|intval}"{else} data-kartikel="0"{/if}>
                <div class="cardproduct-title">{$card.title|escape:'html'}</div>
                <div class="cardproduct-media">
                    {if $card.img|strlen > 0}
                        <div class="cardproduct-slider" data-images='{$card.imgs|default:[]|@json_encode|escape:"html"}'>
                            <img class="cardproduct-slider-img" src="{$card.img|escape:'html'}" alt="{$card.title|escape:'html'}" loading="lazy">
                        </div>
                    {else}
                        <div class="cardproduct-placeholder">Bild</div>
                    {/if}
                </div>
                {if isset($card.imgs) && $card.imgs|@count > 1}
                    <div class="cardproduct-thumbs">
                        {foreach $card.imgs as $i => $thumb}
                            <button type="button" class="cardproduct-thumb{if $i === 0} active{/if}" data-idx="{$i}" aria-label="Varyasyon {$i+1}">
                                <img src="{$thumb|escape:'html'}" alt="{$card.title|escape:'html'} varyasyon {$i+1}" loading="lazy">
                            </button>
                        {/foreach}
                    </div>
                {/if}
            </a>
        {/foreach}
    </div>
    {if $cardProductCards|@count > 3}
    <div class="cardproduct-load-more-wrap">
        <button type="button" class="cardproduct-load-more">Weitere Modelle anzeigen</button>
    </div>
    {/if}
    </div>
</div>
<script>
(function () {
  var sliders = document.querySelectorAll('.cardproduct-slider');
  if (!sliders.length) return;
  sliders.forEach(function (slider) {
    var img = slider.querySelector('.cardproduct-slider-img');
    if (!img) return;
    var images = [];
    try { images = JSON.parse(slider.getAttribute('data-images') || '[]') || []; } catch (e) { images = []; }
    images = images.filter(function (u) { return !!u; });
    if (!images.length) return;
    var idx = 0;
    function render() {
      img.src = images[idx] || images[0];
      var thumbs = slider.closest('.cardproduct-item').querySelectorAll('.cardproduct-thumb');
      thumbs.forEach(function (t, i) { t.classList.toggle('active', i === idx); });
    }
    img.addEventListener('error', function () {
      if (images.length < 2) return;
      idx = (idx + 1) % images.length;
      render();
    });
    var thumbs = slider.closest('.cardproduct-item').querySelectorAll('.cardproduct-thumb');
    thumbs.forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var n = parseInt(btn.getAttribute('data-idx') || '0', 10);
        if (!isNaN(n) && images[n]) {
          idx = n;
          render();
        }
      });
    });
  });
})();
(function () {
  var wrap = document.querySelector('.cardproduct-load-more-wrap');
  var btn = document.querySelector('.cardproduct-load-more');
  var grid = document.querySelector('.cardproduct-grid-pen .cardproduct-grid');
  if (!wrap || !btn || !grid) return;
  var mq = window.matchMedia('(max-width: 768px)');
  var batch = 3;
  function foldedItems() {
    return grid.querySelectorAll('.cardproduct-item--folded:not(.is-visible)');
  }
  function sync() {
    var hidden = foldedItems();
    if (!mq.matches) {
      grid.querySelectorAll('.cardproduct-item--folded').forEach(function (el) { el.classList.add('is-visible'); });
      wrap.classList.remove('is-active');
      wrap.setAttribute('aria-hidden', 'true');
      return;
    }
    if (hidden.length === 0) {
      wrap.classList.remove('is-active');
      wrap.setAttribute('aria-hidden', 'true');
    } else {
      wrap.classList.add('is-active');
      wrap.setAttribute('aria-hidden', 'false');
    }
  }
  btn.addEventListener('click', function () {
    var items = foldedItems();
    for (var i = 0; i < batch && i < items.length; i++) {
      items[i].classList.add('is-visible');
    }
    sync();
  });
  if (mq.addEventListener) {
    mq.addEventListener('change', sync);
  } else if (mq.addListener) {
    mq.addListener(sync);
  }
  sync();
})();
(function () {
  var panel = document.querySelector('.cardproduct-produkttyp-panel');
  if (!panel) return;
  var list = panel.querySelector('.cardproduct-produkttyp-list');
  var resetBtn = panel.querySelector('.cardproduct-produkttyp-reset');
  function setFilter(kArtikel) {
    var items = document.querySelectorAll('.cardproduct-item[data-kartikel]');
    var any = false;
    items.forEach(function (el) {
      var k = parseInt(el.getAttribute('data-kartikel') || '0', 10);
      var show = !kArtikel || k === kArtikel;
      if (show) any = true;
      el.style.display = show ? '' : 'none';
    });
    var loadMore = document.querySelector('.cardproduct-load-more-wrap');
    if (loadMore) loadMore.style.display = kArtikel ? 'none' : '';
    if (kArtikel && !any) {
      items.forEach(function (el) { el.style.display = ''; });
      if (loadMore) loadMore.style.display = '';
    }
  }
  if (list) {
    list.addEventListener('click', function (ev) {
      var li = ev.target.closest('.cardproduct-produkttyp-row');
      if (!li) return;
      ev.preventDefault();
      var ka = parseInt(li.getAttribute('data-ka') || '0', 10);
      if (ka) setFilter(ka);
    });
    list.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter' && ev.key !== ' ') return;
      var li = ev.target.closest('.cardproduct-produkttyp-row');
      if (!li) return;
      ev.preventDefault();
      var ka = parseInt(li.getAttribute('data-ka') || '0', 10);
      if (ka) setFilter(ka);
    });
  }
  if (resetBtn) {
    resetBtn.addEventListener('click', function (ev) {
      ev.preventDefault();
      setFilter(0);
    });
  }
})();
</script>
