/**
 * Copied/adapted from plugins/ActionProduct/Tasarim/index.js
 * Prefix: aktion*
 */
(function () {
    'use strict';

    const AKTION_COUPON_STORAGE_KEY = 'ap_coupon_code';
    const AKTION_COUPON_AUTOSUBMIT_KEY = 'ap_coupon_autosubmit';

    function aktionSafeStorageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) {}
    }
    function aktionSafeStorageGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function aktionSafeStorageRemove(key) {
        try { window.localStorage.removeItem(key); } catch (e) {}
    }

    function aktion$(id) {
        return document.getElementById(id);
    }

    function aktionUpdateText(id, txt) {
        const el = aktion$(id);
        if (el) el.textContent = txt;
    }

    function aktionUpdateHtml(id, html) {
        const el = aktion$(id);
        if (el) el.innerHTML = html;
    }

    function aktionFormatEuro(val) {
        try {
            return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val);
        } catch (e) {
            return '€' + String(val);
        }
    }

    function aktionSetCouponForAutoApply(code) {
        const coupon = String(code || '').trim();
        if (!coupon) return;
        aktionSafeStorageSet(AKTION_COUPON_STORAGE_KEY, coupon);
        aktionSafeStorageRemove(AKTION_COUPON_AUTOSUBMIT_KEY + ':' + coupon);
    }

    function aktionShowDetailDiscountPreview() {
        const wrap = document.querySelector('.aktion-product-detail-extras');
        if (!wrap) return;
        const highlight = wrap.querySelector('.aktion-cart-discount-highlight');
        if (!highlight) return;
        const amount = aktionParseNum(wrap.getAttribute('data-ap-discount-amount'), 0);
        const discounted = aktionParseNum(wrap.getAttribute('data-ap-discount-price'), 0);
        if (amount <= 0) return;
        highlight.textContent = 'Rabatt nach dem Hinzufugen zum Warenkorb: -' + aktionFormatEuro(amount) + ' (Neuer Preis: ' + aktionFormatEuro(discounted) + ')';
        highlight.style.display = '';
    }

    function aktionInitDots(dotsWrap, count, onClick) {
        if (!dotsWrap) return;
        dotsWrap.innerHTML = '';
        for (let i = 0; i < count; i++) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'aktion-slider-dot' + (i === 0 ? ' active' : '');
            b.addEventListener('click', (e) => { e.preventDefault(); onClick(i); });
            dotsWrap.appendChild(b);
        }
    }

    function aktionSetActive(list, idx, className) {
        list.forEach((el, i) => el.classList.toggle(className, i === idx));
    }

    function aktionParseNum(v, fallback = 0) {
        const n = Number(String(v ?? '').replace(',', '.'));
        return Number.isFinite(n) ? n : fallback;
    }

    function aktionInitCountdownElement(countdownEl) {
        if (!countdownEl) return;
        const iso = countdownEl.getAttribute('data-countdown-date') || '';
        const target = iso ? new Date(iso) : null;
        if (!target || isNaN(target.getTime())) return;

        const byUnit = (unit) => countdownEl.querySelector('[data-aktion-unit="' + unit + '"]');
        const daysEl = byUnit('days');
        const hoursEl = byUnit('hours');
        const minutesEl = byUnit('minutes');
        const secondsEl = byUnit('seconds');

        function tick() {
            const now = new Date();
            const diff = target.getTime() - now.getTime();
            const d = Math.max(0, Math.floor(diff / (1000 * 60 * 60 * 24)));
            const h = Math.max(0, Math.floor((diff / (1000 * 60 * 60)) % 24));
            const m = Math.max(0, Math.floor((diff / (1000 * 60)) % 60));
            const s = Math.max(0, Math.floor((diff / 1000) % 60));
            if (daysEl) daysEl.textContent = String(d).padStart(2, '0');
            if (hoursEl) hoursEl.textContent = String(h).padStart(2, '0');
            if (minutesEl) minutesEl.textContent = String(m).padStart(2, '0');
            if (secondsEl) secondsEl.textContent = String(s).padStart(2, '0');
        }

        tick();
        setInterval(tick, 1000);
    }

    function aktionInitStandaloneCountdowns() {
        document.querySelectorAll('.aktion-countdown[data-countdown-date]').forEach((el) => {
            aktionInitCountdownElement(el);
        });
    }

    function aktionInitCountdownFor(root, suf) {
        const countdown = aktion$('aktion-countdown' + suf);
        if (!countdown) return;
        // legacy ids still present, but we update via data-aktion-unit
        aktionInitCountdownElement(countdown);
    }

    function aktionInitTabContent(tabContentEl, idx) {
        const suf = idx > 1 ? '-' + idx : '';
        const manualOld = aktionParseNum(tabContentEl.getAttribute('data-price-old'), 0);
        const basePrice = aktionParseNum(tabContentEl.getAttribute('data-price-new'), 0); // sales price from DB/dashboard
        const couponWert = aktionParseNum(tabContentEl.getAttribute('data-coupon-wert'), 0);
        const couponTyp = String(tabContentEl.getAttribute('data-coupon-typ') || '');
        const hasCoupon = couponWert > 0 && (couponTyp === 'prozent' || couponTyp === 'festpreis');
        const couponCode = String(tabContentEl.getAttribute('data-coupon-code') || '').trim();

        let priceOld = 0;
        let priceNew = basePrice;
        if (priceNew <= 0 && manualOld > 0) {
            priceNew = manualOld;
            priceOld = 0;
        }
        if (hasCoupon) {
            // Coupon active: old price should be current DB/base price.
            priceOld = basePrice;
            if (couponTyp === 'prozent') {
                priceNew = Math.max(0, basePrice * (1 - Math.max(0, couponWert) / 100));
            } else {
                priceNew = Math.max(0, basePrice - Math.max(0, couponWert));
            }
            priceNew = Math.round(priceNew * 100) / 100;
        }
        const totalStock = Math.max(0, parseInt(tabContentEl.getAttribute('data-total-stock') || '0', 10) || 0);
        const soldCount = Math.max(0, parseInt(tabContentEl.getAttribute('data-sold-count') || '0', 10) || 0);
        const urgencyActive = String(tabContentEl.getAttribute('data-urgency-active') ?? '1') !== '0';
        const urgencyTpl = String(tabContentEl.getAttribute('data-urgency-text') ?? 'Nur noch {remain} Stück verfügbar!');

        const elOld = aktion$('aktion-price-old' + suf);
        const elNew = aktion$('aktion-price-new' + suf);
        const elBadge = aktion$('aktion-discount-badge' + suf);
        const pricingWrap = tabContentEl.querySelector('.aktion-pricing');

        // Fallback without coupon: show configured old/new if old > new.
        if (!hasCoupon && manualOld > basePrice && basePrice > 0) {
            priceOld = manualOld;
            priceNew = basePrice;
        }

        if (pricingWrap) {
            pricingWrap.style.display = basePrice > 0 ? '' : 'none';
        }
        if (elNew) {
            elNew.innerHTML = aktionFormatEuro(priceNew);
        }
        const hasOldPrice = priceOld > 0;
        if (hasOldPrice) {
            if (elOld) {
                elOld.style.display = '';
                elOld.innerHTML = aktionFormatEuro(priceOld);
            }
            const disc = (priceNew > 0 && priceOld > priceNew)
                ? Math.round((1 - (priceNew / Math.max(0.01, priceOld))) * 100)
                : 0;
            if (elBadge) {
                if (disc > 0) {
                    elBadge.style.display = '';
                    elBadge.textContent = '-' + disc + '%';
                } else {
                    elBadge.style.display = 'none';
                    elBadge.textContent = '';
                }
            }
        } else {
            if (elOld) {
                elOld.style.display = 'none';
                elOld.textContent = '';
            }
            if (elBadge) {
                elBadge.style.display = 'none';
                elBadge.textContent = '';
            }
        }

        aktionUpdateText('aktion-sold-count' + suf, String(soldCount));
        aktionUpdateText('aktion-total-count' + suf, String(totalStock));
        const percent = totalStock > 0 ? Math.max(0, Math.min(100, Math.round((soldCount / totalStock) * 100))) : 0;
        const fill = aktion$('aktion-progress-fill' + suf);
        if (fill) fill.style.width = percent + '%';
        aktionUpdateText('aktion-sold-percentage' + suf, percent + '% verkauft');
        const remainPercent = Math.max(0, Math.min(100, 100 - percent));
        aktionUpdateText('aktion-remaining-count' + suf, remainPercent + '% Verbleibend');
        const urgWrap = aktion$('aktion-urgency' + suf);
        const urgText = aktion$('aktion-urgency-text' + suf);
        const remain = Math.max(0, totalStock - soldCount);
        if (!urgencyActive) {
            if (urgWrap) urgWrap.style.display = 'none';
        } else {
            if (urgWrap) urgWrap.style.display = '';
            if (urgText) {
                urgText.textContent = urgencyTpl.replaceAll('{remain}', String(remain));
            }
        }

        const buyBtn = aktion$('aktion-buy-button' + suf);
        if (buyBtn) {
            buyBtn.addEventListener('click', () => {
                if (hasCoupon && couponCode !== '') {
                    aktionSetCouponForAutoApply(couponCode);
                }
                const url = buyBtn.getAttribute('data-buy-url') || '#';
                if (url && url !== '#') {
                    window.location.href = url;
                }
            });
        }

        // Slider
        const container = aktion$('aktion-slider-container' + suf);
        if (!container) return;
        const sliderWrap = container.closest('.aktion-slider-wrapper');
        const slides = Array.from(container.querySelectorAll('.aktion-slide'));
        const dotsWrap = aktion$('aktion-slider-dots' + suf);
        const prev = aktion$('aktion-slider-prev' + suf);
        const next = aktion$('aktion-slider-next' + suf);
        const thumbs = Array.from(tabContentEl.querySelectorAll('.aktion-thumbnail'));

        let current = 0;
        function render(newIdx) {
            current = (newIdx + slides.length) % Math.max(1, slides.length);
            aktionSetActive(slides, current, 'active');
            aktionSetActive(thumbs, current, 'active');
            if (dotsWrap) {
                const dots = Array.from(dotsWrap.querySelectorAll('.aktion-slider-dot'));
                aktionSetActive(dots, current, 'active');
            }
        }

        aktionInitDots(dotsWrap, slides.length, (i) => render(i));

        thumbs.forEach((t) => {
            t.addEventListener('click', () => {
                const i = parseInt(t.getAttribute('data-index') || '0', 10) || 0;
                render(i);
            });
        });
        if (prev) prev.addEventListener('click', () => render(current - 1));
        if (next) next.addEventListener('click', () => render(current + 1));

        // Swipe / drag support for mobile (and mouse drag fallback).
        if (sliderWrap) {
            let startX = 0;
            let isDown = false;
            let moved = false;
            const threshold = 40;

            const onStart = (x) => {
                startX = x;
                isDown = true;
                moved = false;
            };
            const onMove = (x) => {
                if (!isDown) return;
                if (Math.abs(x - startX) > 8) {
                    moved = true;
                }
            };
            const onEnd = (x) => {
                if (!isDown) return;
                const dx = x - startX;
                if (Math.abs(dx) > threshold) {
                    if (dx < 0) {
                        render(current + 1);
                    } else {
                        render(current - 1);
                    }
                }
                isDown = false;
            };

            sliderWrap.addEventListener('touchstart', (e) => {
                if (!e.touches || e.touches.length === 0) return;
                onStart(e.touches[0].clientX);
            }, { passive: true });
            sliderWrap.addEventListener('touchmove', (e) => {
                if (!e.touches || e.touches.length === 0) return;
                onMove(e.touches[0].clientX);
            }, { passive: true });
            sliderWrap.addEventListener('touchend', (e) => {
                const x = (e.changedTouches && e.changedTouches.length > 0)
                    ? e.changedTouches[0].clientX
                    : startX;
                onEnd(x);
            });

            sliderWrap.addEventListener('mousedown', (e) => {
                onStart(e.clientX);
            });
            window.addEventListener('mousemove', (e) => {
                onMove(e.clientX);
            });
            window.addEventListener('mouseup', (e) => {
                onEnd(e.clientX);
            });

            sliderWrap.addEventListener('click', (e) => {
                if (moved) {
                    e.preventDefault();
                }
            }, true);
        }

        render(0);
        aktionInitCountdownFor(tabContentEl, suf);
    }

    function aktionFindCouponForm() {
        // NOVA Warenkorb: Form hat id="basket-coupon-form", Collapse-Wrapper id="coupon-form" (kein <form>).
        let form = document.querySelector('#basket-coupon-form');
        if (form && form.tagName === 'FORM') {
            return form;
        }
        form = document.querySelector('form.coupon-form');
        if (form) {
            return form;
        }
        const collapse = document.querySelector('#coupon-form');
        if (collapse && collapse.querySelector) {
            const inner = collapse.querySelector('form');
            if (inner) {
                return inner;
            }
        }
        const inp = document.querySelector('#couponCode, input[name="Kuponcode"], #kupon');
        return inp && inp.closest ? inp.closest('form') : null;
    }

    function aktionPrefillCartCoupon() {
        const code = (aktionSafeStorageGet(AKTION_COUPON_STORAGE_KEY) || '').trim();
        if (!code) return;

        const input = document.querySelector('#couponCode, input[name="Kuponcode"], #kupon');
        if (!input) return;

        input.value = code;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        const lockKey = AKTION_COUPON_AUTOSUBMIT_KEY + ':' + code;
        const form = aktionFindCouponForm();
        if (form && !aktionSafeStorageGet(lockKey)) {
            aktionSafeStorageSet(lockKey, '1');
            window.setTimeout(() => {
                try {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        const btn = form.querySelector('button[type="submit"], input[type="submit"]');
                        if (btn) {
                            btn.click();
                        } else {
                            form.submit();
                        }
                    }
                    aktionSafeStorageRemove(AKTION_COUPON_STORAGE_KEY);
                } catch (e) {}
            }, 150);
        }
    }

    function aktionBindDetailCartSubmit() {
        const wrap = document.querySelector('.aktion-product-detail-extras');
        if (!wrap) return;
        const couponCode = String(wrap.getAttribute('data-ap-coupon-code') || '').trim();
        if (!couponCode) return;
        // Show preview immediately on product page (before add-to-cart).
        aktionShowDetailDiscountPreview();
        const form = document.querySelector('form[id^="buy_form"]');
        if (!form) return;
        form.addEventListener('submit', () => {
            aktionSetCouponForAutoApply(couponCode);
        });
    }

    function aktionSetupTabs() {
        const buttons = document.querySelectorAll('.aktion-tab-button');
        buttons.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const banner = btn.closest('.aktion-banner');
                if (!banner) return;
                const contents = Array.from(banner.querySelectorAll('.aktion-tab-content[data-price-new]'));
                if (contents.length === 0) return;
                e.preventDefault();
                const tab = String(btn.getAttribute('data-tab') || '');
                const idx = parseInt(tab.replace('tab', ''), 10);
                const localButtons = Array.from(banner.querySelectorAll('.aktion-tab-button'));
                localButtons.forEach((b) => b.classList.toggle('active', b === btn));
                contents.forEach((c) => c.classList.toggle('active', c.id === 'aktion-tab-content-' + String(idx)));
            });
        });
    }

    function aktionInit() {
        aktionPrefillCartCoupon();
        aktionBindDetailCartSubmit();
        aktionInitStandaloneCountdowns();
        const contents = document.querySelectorAll('.aktion-tab-content[data-price-new]');
        contents.forEach((c, i) => aktionInitTabContent(c, i + 1));
        aktionSetupTabs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', aktionInit);
    } else {
        aktionInit();
    }
})();

