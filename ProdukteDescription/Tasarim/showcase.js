/* ProdukteShowcase — showcase.js */
(function () {
  'use strict';

  /* ── Lightbox ──────────────────────────────────────────── */
  var lbEl = null;

  function openLightbox(src, alt) {
    if (lbEl) { return; }
    lbEl = document.createElement('div');
    lbEl.className = 'pds-lightbox';
    lbEl.setAttribute('role', 'dialog');
    lbEl.setAttribute('aria-modal', 'true');
    lbEl.setAttribute('aria-label', alt || 'Bild');

    var img = document.createElement('img');
    img.src = src;
    img.alt = alt || '';

    var btn = document.createElement('button');
    btn.className = 'pds-lightbox-close';
    btn.setAttribute('aria-label', 'Schließen');
    btn.innerHTML = '&times;';
    btn.addEventListener('click', function (e) { e.stopPropagation(); closeLightbox(); });

    lbEl.appendChild(btn);
    lbEl.appendChild(img);
    lbEl.addEventListener('click', closeLightbox);
    document.body.appendChild(lbEl);
    document.body.style.overflow = 'hidden';

    document.addEventListener('keydown', onLbKey);
  }

  function closeLightbox() {
    if (!lbEl) { return; }
    document.body.removeChild(lbEl);
    lbEl = null;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onLbKey);
  }

  function onLbKey(e) {
    if (e.key === 'Escape') { closeLightbox(); }
  }

  /* ── Roots ─────────────────────────────────────────────── */
  document.querySelectorAll('.pds-root').forEach(function (root) {
    var tabs   = Array.prototype.slice.call(root.querySelectorAll('.pds-tab'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('.pds-panel'));

    /* Tab switching */
    function activateTab(idx) {
      tabs.forEach(function (t, i) {
        var on = i === idx;
        t.classList.toggle('is-active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (p, i) {
        var on = i === idx;
        p.classList.toggle('is-active', on);
        if (on) { p.removeAttribute('hidden'); }
        else    { p.setAttribute('hidden', ''); }
      });
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { activateTab(i); });
    });

    /* Slider */
    root.querySelectorAll('[data-pds-slider]').forEach(function (slider) {
      var slides  = Array.prototype.slice.call(slider.querySelectorAll('.pds-slide'));
      var prevBtn = slider.querySelector('[data-pds-prev]');
      var nextBtn = slider.querySelector('[data-pds-next]');
      var panel   = slider.closest('.pds-image-panel');
      var dotsEl  = panel && panel.querySelector('[data-pds-dots]');
      var cur = 0;

      /* dots oluştur */
      if (dotsEl) {
        slides.forEach(function (_, i) {
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'pds-dot' + (i === 0 ? ' is-active' : '');
          btn.setAttribute('aria-label', 'Bild ' + (i + 1));
          btn.addEventListener('click', function () { goTo(i); });
          dotsEl.appendChild(btn);
        });
      }

      function getDots() {
        return dotsEl ? Array.prototype.slice.call(dotsEl.querySelectorAll('.pds-dot')) : [];
      }

      function goTo(n) {
        n = Math.max(0, Math.min(n, slides.length - 1));
        slides[cur].classList.remove('is-active');
        var dots = getDots();
        if (dots[cur]) { dots[cur].classList.remove('is-active'); }
        cur = n;
        slides[cur].classList.add('is-active');
        if (dots[cur]) { dots[cur].classList.add('is-active'); }
        if (prevBtn) { prevBtn.disabled = cur === 0; }
        if (nextBtn) { nextBtn.disabled = cur === slides.length - 1; }
      }

      goTo(0);

      /* lightbox: resimlere tıklayınca aç */
      slides.forEach(function (slide) {
        var img = slide.querySelector('img');
        if (!img) { return; }
        img.addEventListener('click', function () {
          openLightbox(img.src, img.alt);
        });
      });

      if (prevBtn) { prevBtn.addEventListener('click', function () { goTo(cur - 1); }); }
      if (nextBtn) { nextBtn.addEventListener('click', function () { goTo(cur + 1); }); }

      /* swipe */
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
  });
})();
