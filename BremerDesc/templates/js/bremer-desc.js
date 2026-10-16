/* BremerDesc — bremer-desc.js */
(function () {
  'use strict';

  function init() {
    document.querySelectorAll('.bd-showcase').forEach(initShowcase);
  }

  function initShowcase(root) {
    var tabs   = Array.prototype.slice.call(root.querySelectorAll('.bd-showcase__tab'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('.bd-showcase__panel'));

    /* ── Tab switching ────────────────────────────────────── */
    function activateTab(idx) {
      tabs.forEach(function (t, i) {
        var on = i === idx;
        t.classList.toggle('bd-showcase__tab--active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (p, i) {
        var on = i === idx;
        p.classList.toggle('bd-showcase__panel--active', on);
        if (on) { p.removeAttribute('hidden'); }
        else    { p.setAttribute('hidden', ''); }
      });
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { activateTab(i); });
    });

    /* ── Sliders ──────────────────────────────────────────── */
    root.querySelectorAll('[data-bd-slider]').forEach(function (slider) {
      var slides  = Array.prototype.slice.call(slider.querySelectorAll('.bd-showcase__slide'));
      var prevBtn = slider.querySelector('[data-bd-prev]');
      var nextBtn = slider.querySelector('[data-bd-next]');
      var dotsEl  = slider.parentElement && slider.parentElement.querySelector('[data-bd-dots]');
      var cur = 0;

      if (!slides.length) { return; }

      /* Resimleri önceden topla (lightbox için) */
      var allSrcs = slides.map(function (slide) {
        var img = slide.querySelector('img');
        return img ? { src: img.src, alt: img.alt || '' } : null;
      }).filter(Boolean);

      /* Dots oluştur */
      if (dotsEl) {
        slides.forEach(function (_, i) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'bd-showcase__dot' + (i === 0 ? ' bd-showcase__dot--active' : '');
          btn.setAttribute('aria-label', 'Bild ' + (i + 1));
          btn.addEventListener('click', function () { goTo(i); });
          dotsEl.appendChild(btn);
        });
      }

      function getDots() {
        return dotsEl ? Array.prototype.slice.call(dotsEl.querySelectorAll('.bd-showcase__dot')) : [];
      }

      function goTo(n) {
        n = Math.max(0, Math.min(n, slides.length - 1));
        slides[cur].classList.remove('bd-showcase__slide--active');
        var dots = getDots();
        if (dots[cur]) { dots[cur].classList.remove('bd-showcase__dot--active'); }
        cur = n;
        slides[cur].classList.add('bd-showcase__slide--active');
        if (dots[cur]) { dots[cur].classList.add('bd-showcase__dot--active'); }
        if (prevBtn) { prevBtn.disabled = cur === 0; }
        if (nextBtn) { nextBtn.disabled = cur === slides.length - 1; }
      }

      goTo(0);

      /* Lightbox: tıklayınca aynı sıralı liste ile aç */
      slides.forEach(function (slide, i) {
        var img = slide.querySelector('img');
        if (!img) { return; }
        img.addEventListener('click', function () {
          openLightbox(allSrcs, i);
        });
      });

      if (prevBtn) { prevBtn.addEventListener('click', function () { goTo(cur - 1); }); }
      if (nextBtn) { nextBtn.addEventListener('click', function () { goTo(cur + 1); }); }

      /* Swipe */
      var startX = null;
      slider.addEventListener('touchstart', function (e) {
        startX = e.touches[0].clientX;
      }, { passive: true });
      slider.addEventListener('touchend', function (e) {
        if (startX === null) { return; }
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) { goTo(dx < 0 ? cur + 1 : cur - 1); }
        startX = null;
      }, { passive: true });
    });
  }

  /* ══════════════════════════════════════════════════════════
     LIGHTBOX — resim listesi + ok navigasyonu
  ══════════════════════════════════════════════════════════ */
  var lbEl   = null;
  var lbImg  = null;
  var lbCnt  = null;   // "2 / 5" sayacı
  var lbList = [];
  var lbCur  = 0;

  function openLightbox(list, startIdx) {
    if (lbEl) { closeLightbox(); }

    lbList = list || [];
    lbCur  = startIdx || 0;

    /* Overlay */
    lbEl = document.createElement('div');
    lbEl.className = 'bd-lightbox';
    lbEl.setAttribute('role', 'dialog');
    lbEl.setAttribute('aria-modal', 'true');

    /* Kapat butonu */
    var closeBtn = document.createElement('button');
    closeBtn.className = 'bd-lightbox__close';
    closeBtn.setAttribute('aria-label', 'Schliessen');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', function (e) { e.stopPropagation(); closeLightbox(); });

    /* Resim */
    lbImg = document.createElement('img');
    lbImg.className = 'bd-lightbox__img';

    /* Sayaç */
    lbCnt = document.createElement('div');
    lbCnt.className = 'bd-lightbox__counter';

    /* Sol ok */
    var prevBtn = document.createElement('button');
    prevBtn.className = 'bd-lightbox__nav bd-lightbox__nav--prev';
    prevBtn.setAttribute('aria-label', 'Vorheriges Bild');
    prevBtn.innerHTML = '&#8249;';
    prevBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      lbGoTo(lbCur - 1);
    });

    /* Sağ ok */
    var nextBtn = document.createElement('button');
    nextBtn.className = 'bd-lightbox__nav bd-lightbox__nav--next';
    nextBtn.setAttribute('aria-label', 'Nächstes Bild');
    nextBtn.innerHTML = '&#8250;';
    nextBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      lbGoTo(lbCur + 1);
    });

    lbEl.appendChild(closeBtn);
    lbEl.appendChild(prevBtn);
    lbEl.appendChild(lbImg);
    lbEl.appendChild(nextBtn);
    lbEl.appendChild(lbCnt);

    /* Overlay tıklaması kapatır */
    lbEl.addEventListener('click', function (e) {
      if (e.target === lbEl) { closeLightbox(); }
    });

    document.body.appendChild(lbEl);
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onLbKey);

    lbGoTo(lbCur);
  }

  function lbGoTo(n) {
    if (!lbList.length) { return; }
    n = ((n % lbList.length) + lbList.length) % lbList.length; // döngüsel
    lbCur = n;
    lbImg.src = lbList[n].src;
    lbImg.alt = lbList[n].alt || '';

    /* Sayaç */
    if (lbCnt) {
      lbCnt.textContent = lbList.length > 1 ? (n + 1) + ' / ' + lbList.length : '';
    }

    /* Tek resimse okları gizle */
    var prevEl = lbEl && lbEl.querySelector('.bd-lightbox__nav--prev');
    var nextEl = lbEl && lbEl.querySelector('.bd-lightbox__nav--next');
    if (prevEl) { prevEl.style.display = lbList.length > 1 ? '' : 'none'; }
    if (nextEl) { nextEl.style.display = lbList.length > 1 ? '' : 'none'; }
  }

  function closeLightbox() {
    if (!lbEl) { return; }
    document.body.removeChild(lbEl);
    lbEl  = null;
    lbImg = null;
    lbCnt = null;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onLbKey);
  }

  function onLbKey(e) {
    if (e.key === 'Escape')     { closeLightbox(); }
    if (e.key === 'ArrowRight') { lbGoTo(lbCur + 1); }
    if (e.key === 'ArrowLeft')  { lbGoTo(lbCur - 1); }
  }

  /* ── Boot ─────────────────────────────────────────────── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
