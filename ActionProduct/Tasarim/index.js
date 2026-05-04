/**
 * Aktion Script - JTL Shop Compatible
 * All functions and variables prefixed with 'aktion' to avoid conflicts
 */

(function() {
    'use strict';

    // Aktion configuration - Daten können von JTL Shop abgerufen oder manuell eingestellt werden
    const aktionConfig = {
        productName: 'Lammfell Autositzauflage – Universal Sitzbezug',
        productDesc: 'LAMMFELL – 100% Naturfell mit ca. 12–14 mm Florhöhe – weich, hautfreundlich und langlebig.',
        productImage: '', // Bild-URL von JTL Shop
        priceOld: 129.00,
        priceNew: 99.00,
        totalStock: 100,
        soldCount: 75,
        // Countdown-Zieltermin: Jahr, Monat (0-11), Tag, Stunde, Minute, Sekunde
        countdownDate: new Date(2026, 11, 31, 23, 59, 59) // 31. Dezember 2026, 23:59:59
    };

    // Aktion configuration für Tab 2
    const aktionConfig2 = {
        productName: '5 Fenster Einlagig Gardinen mit Aluprofile',
        productDesc: 'Produkt 2 Beschreibung',
        productImage: '',
        priceOld: 199.00,
        priceNew: 129.00,
        totalStock: 100,
        soldCount: 80,
        // Countdown-Zieltermin: Jahr, Monat (0-11), Tag, Stunde, Minute, Sekunde
        countdownDate: new Date(2026, 11, 31, 23, 59, 59) // 31. Dezember 2026, 23:59:59
    };

    // Slider configuration Tab 1 (HTML ile uyumlu: 2 slide)
    let aktionCurrentSlide = 0;
    const aktionImages = [
        'https://bremer-sitzbezuege.de/media/image/product/335179/lg/lammfell-sitzauflage-sitzbezug-universal-autositzauflage.webp',
        'https://bremer-sitzbezuege.de/media/image/product/335180/lg/lammfell-sitzauflage-sitzbezug-universal-autositzauflage_1.webp'
    ];
    let aktionSliderInterval = null;
    let aktionSliderInterval2 = null;
    let aktionCountdownInterval = null;

    // Slider configuration Tab 2
    let aktionCurrentSlide2 = 0;
    const aktionImages2 = [
        'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz.webp',
        'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~2.webp',
        'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~4.webp',
        'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~6.webp'
    ];

    /**
     * Aktion Initialisierungsfunktion
     */
    function aktionInit() {
        aktionUpdateProductInfo();
        aktionUpdatePricing();
        aktionUpdateStockInfo();
        aktionSetupBuyButton();
        aktionSetupThumbnails();
        aktionInitCountdown();
        aktionSetupTabs();
        
        // Event Listener für JTL Shop Integration
        if (typeof window.addEventListener !== 'undefined') {
            window.addEventListener('load', aktionOnPageLoad);
        }
    } 

    /**
     * Richtet Tab-Navigation ein
     */
    function aktionSetupTabs() {
        const tabButtons = document.querySelectorAll('.aktion-tab-button');
        const tabContents = document.querySelectorAll('.aktion-tab-content');

        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetTab = this.dataset.tab;

                // Alle Tabs deaktivieren
                tabButtons.forEach(function(btn) {
                    btn.classList.remove('active');
                });
                tabContents.forEach(function(content) {
                    content.classList.remove('active');
                });

                // Aktiven Tab aktivieren
                this.classList.add('active');
                const targetContent = document.getElementById('aktion-tab-content-' + targetTab.replace('tab', ''));
                if (targetContent) {
                    targetContent.classList.add('active');
                    
                    // Tab 2 için countdown ve slider başlat
                    if (targetTab === 'tab2') {
                        aktionUpdateProductInfo2();
                        aktionUpdatePricing2();
                        aktionUpdateStockInfo2();
                        aktionInitCountdown2();
                        aktionSetupThumbnails2();
                        aktionInitSlider2();
                        aktionSetupSliderButtons2();
                        aktionSetupSliderDots2();
                        aktionSetupSliderHover2();
                        aktionStartAutoSlide2();
                    } else if (targetTab === 'tab1') {
                        aktionStartAutoSlide();
                    }
                }
            });
        });
    }

    /**
     * Wird ausgeführt wenn die Seite geladen ist
     */
    function aktionOnPageLoad() {
        // Hier kann Code hinzugefügt werden um Produktinformationen von JTL Shop abzurufen
        aktionLoadProductFromJTL();
    }

    /**
     * Aktualisiert Produktinformationen
     */
    function aktionUpdateProductInfo() {
        const productNameEl = document.getElementById('aktion-product-name');
        const productDescEl = document.getElementById('aktion-product-desc');
        const productImageEl = document.getElementById('aktion-product-image');

        if (productNameEl) {
            productNameEl.textContent = aktionConfig.productName;
        }

        if (productDescEl) {
            productDescEl.textContent = aktionConfig.productDesc;
        }

        if (productImageEl && aktionConfig.productImage) {
            productImageEl.src = aktionConfig.productImage;
            productImageEl.alt = aktionConfig.productName;
        }
    }

    /**
     * Aktualisiert Preisinformationen
     */
    function aktionUpdatePricing() {
        const priceOldEl = document.getElementById('aktion-price-old');
        const priceNewEl = document.getElementById('aktion-price-new');
        const discountBadgeEl = document.getElementById('aktion-discount-badge');

        if (priceOldEl) {
            priceOldEl.textContent = '€' + aktionConfig.priceOld.toFixed(2).replace('.', ',');
        }

        if (priceNewEl) {
            priceNewEl.textContent = '€' + aktionConfig.priceNew.toFixed(2).replace('.', ',');
        }

        if (discountBadgeEl) {
            const discountPercent = Math.round(((aktionConfig.priceOld - aktionConfig.priceNew) / aktionConfig.priceOld) * 100);
            discountBadgeEl.textContent = '-' + discountPercent + '%';
        }
    }

    /**
     * Aktualisiert Lagerbestandsinformationen und Progress Bar
     */
    function aktionUpdateStockInfo() {
        const soldCountEl = document.getElementById('aktion-sold-count');
        const totalCountEl = document.getElementById('aktion-total-count');
        const remainingCountEl = document.getElementById('aktion-remaining-count');
        const soldPercentageEl = document.getElementById('aktion-sold-percentage');
        const urgencyTextEl = document.getElementById('aktion-urgency-text');
        const progressFillEl = document.getElementById('aktion-progress-fill');

        const remaining = aktionConfig.totalStock - aktionConfig.soldCount;
        const soldPercentage = Math.round((aktionConfig.soldCount / aktionConfig.totalStock) * 100);
        const remainingPercentage = 100 - soldPercentage;

        if (soldCountEl) {
            soldCountEl.textContent = aktionConfig.soldCount;
        }

        if (totalCountEl) {
            totalCountEl.textContent = aktionConfig.totalStock;
        }

        if (remainingCountEl) {
            remainingCountEl.textContent = remaining + ' Verbleibend';
        }

        if (soldPercentageEl) {
            soldPercentageEl.textContent = soldPercentage + '% verkauft';
        }

        const urgencyEl = document.getElementById('aktion-urgency');
        if (urgencyTextEl && urgencyEl) {
            urgencyTextEl.textContent = 'Nur noch ' + remaining + ' Stück verfügbar!';
            urgencyEl.classList.remove('hidden');
        }

        if (progressFillEl) {
            progressFillEl.style.width = soldPercentage + '%';
        }
    }

    /**
     * Richtet den Kauf-Button ein
     */
    function aktionSetupBuyButton() {
        const buyButtonEl = document.getElementById('aktion-buy-button');
        const buyButtonEl2 = document.getElementById('aktion-buy-button-2');
        
        if (buyButtonEl) {
            buyButtonEl.addEventListener('click', aktionHandleBuyClick1);
        }
        
        if (buyButtonEl2) {
            buyButtonEl2.addEventListener('click', aktionHandleBuyClick2);
        }
    }

    /**
     * Richtet Slider ein
     */
    function aktionSetupThumbnails() {
        aktionInitSlider();
        aktionSetupSliderButtons();
        aktionSetupSliderDots();
        aktionSetupThumbnailClicks();
        aktionSetupSliderHover();
        aktionStartAutoSlide();
    }

    /**
     * Richtet Hover-Verhalten für Slider ein
     */
    function aktionSetupSliderHover() {
        const container = document.getElementById('aktion-slider-container');
        if (!container) return;
        const sliderWrapper = container.closest('.aktion-slider-wrapper');
        if (sliderWrapper) {
            sliderWrapper.addEventListener('mouseenter', function() {
                aktionStopAutoSlide();
            });
            sliderWrapper.addEventListener('mouseleave', function() {
                aktionStartAutoSlide();
            });
        }
    }

    /**
     * Initialisiert Slider (nur Tab 1 Container)
     */
    function aktionInitSlider() {
        const container = document.getElementById('aktion-slider-container');
        if (!container) return;
        const slides = container.querySelectorAll('.aktion-slide');
        if (slides.length > 0) {
            slides[0].classList.add('active');
        }
    }

    /**
     * Richtet Slider-Buttons ein
     */
    function aktionSetupSliderButtons() {
        const prevBtn = document.getElementById('aktion-slider-prev');
        const nextBtn = document.getElementById('aktion-slider-next');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                aktionStopAutoSlide();
                aktionPreviousSlide();
                aktionStartAutoSlide();
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                aktionStopAutoSlide();
                aktionNextSlide();
                aktionStartAutoSlide();
            });
        }
    }

    /**
     * Richtet Slider-Dots ein
     */
    function aktionSetupSliderDots() {
        const dotsContainer = document.getElementById('aktion-slider-dots');
        if (!dotsContainer) return;
        
        dotsContainer.innerHTML = '';
        
        for (let i = 0; i < aktionImages.length; i++) {
            const dot = document.createElement('div');
            dot.className = 'aktion-slider-dot' + (i === 0 ? ' active' : '');
            dot.dataset.index = i;
            dot.addEventListener('click', function() {
                aktionStopAutoSlide();
                aktionGoToSlide(parseInt(this.dataset.index));
                aktionStartAutoSlide();
            });
            dotsContainer.appendChild(dot);
        }
    }

    /**
     * Richtet Thumbnail-Klicks ein (nur Tab 1)
     */
    function aktionSetupThumbnailClicks() {
        const tab1 = document.getElementById('aktion-tab-content-1');
        if (!tab1) return;
        const thumbnails = tab1.querySelectorAll('.aktion-thumbnail');
        
        thumbnails.forEach(function(thumbnail) {
            thumbnail.addEventListener('click', function() {
                const index = parseInt(this.dataset.index, 10);
                aktionStopAutoSlide();
                aktionGoToSlide(index);
                aktionStartAutoSlide();
            });
        });
    }

    /**
     * Geht zum nächsten Slide
     */
    function aktionNextSlide() {
        aktionCurrentSlide = (aktionCurrentSlide + 1) % aktionImages.length;
        aktionUpdateSlider();
    }

    /**
     * Geht zum vorherigen Slide
     */
    function aktionPreviousSlide() {
        aktionCurrentSlide = (aktionCurrentSlide - 1 + aktionImages.length) % aktionImages.length;
        aktionUpdateSlider();
    }

    /**
     * Geht zu einem bestimmten Slide
     */
    function aktionGoToSlide(index) {
        if (index >= 0 && index < aktionImages.length) {
            aktionCurrentSlide = index;
            aktionUpdateSlider();
        }
    }

    /**
     * Aktualisiert Slider-Anzeige (nur Tab 1)
     */
    function aktionUpdateSlider() {
        const container = document.getElementById('aktion-slider-container');
        if (!container) return;
        const slides = container.querySelectorAll('.aktion-slide');
        const wrapper = container.closest('.aktion-slider-wrapper');
        const dotsContainer = wrapper ? document.getElementById('aktion-slider-dots') : null;
        const thumbnailsContainer = wrapper && wrapper.nextElementSibling ? wrapper.nextElementSibling : null;
        const thumbnails = thumbnailsContainer ? thumbnailsContainer.querySelectorAll('.aktion-thumbnail') : [];
        const dots = dotsContainer ? dotsContainer.querySelectorAll('.aktion-slider-dot') : [];
        
        slides.forEach(function(slide, index) {
            if (index === aktionCurrentSlide) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
        
        thumbnails.forEach(function(thumb, index) {
            if (index === aktionCurrentSlide) {
                thumb.classList.add('active');
            } else {
                thumb.classList.remove('active');
            }
        });
        
        dots.forEach(function(dot, index) {
            if (index === aktionCurrentSlide) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    /**
     * Startet automatisches Sliden
     */
    function aktionStartAutoSlide() {
        aktionStopAutoSlide();
        aktionSliderInterval = setInterval(function() {
            aktionNextSlide();
        }, 4000); // Alle 4 Sekunden
    }

    /**
     * Stoppt automatisches Sliden
     */
    function aktionStopAutoSlide() {
        if (aktionSliderInterval) {
            clearInterval(aktionSliderInterval);
            aktionSliderInterval = null;
        }
    }

    /**
     * Initialisiert Countdown-Timer
     */
    function aktionInitCountdown() {
        // Elementleri kontrol et
        const countdownEl = document.getElementById('aktion-countdown');
        const daysEl = document.getElementById('aktion-countdown-days');
        const hoursEl = document.getElementById('aktion-countdown-hours');
        const minutesEl = document.getElementById('aktion-countdown-minutes');
        const secondsEl = document.getElementById('aktion-countdown-seconds');

        if (!daysEl || !hoursEl || !minutesEl || !secondsEl) {
            console.log('Aktion: Countdown-Elemente nicht gefunden');
            return;
        }

        // Önceki interval'i temizle
        if (aktionCountdownInterval) {
            clearInterval(aktionCountdownInterval);
        }

        // Countdown-Zieltermin aus HTML data-attribute lesen
        let targetDate;
        if (countdownEl && countdownEl.dataset.countdownDate) {
            targetDate = new Date(countdownEl.dataset.countdownDate).getTime();
        } else {
            // Fallback: Config verwenden falls kein data-attribute vorhanden
            targetDate = aktionConfig.countdownDate.getTime();
        }

        function aktionUpdateCountdown() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0) {
                // Countdown beendet
                daysEl.textContent = '00';
                hoursEl.textContent = '00';
                minutesEl.textContent = '00';
                secondsEl.textContent = '00';
                
                if (aktionCountdownInterval) {
                    clearInterval(aktionCountdownInterval);
                    aktionCountdownInterval = null;
                }
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Werte mit führenden Nullen formatieren
            daysEl.textContent = String(days).padStart(2, '0');
            hoursEl.textContent = String(hours).padStart(2, '0');
            minutesEl.textContent = String(minutes).padStart(2, '0');
            secondsEl.textContent = String(seconds).padStart(2, '0');
        }

        // Sofort aktualisieren
        aktionUpdateCountdown();

        // Jede Sekunde aktualisieren
        aktionCountdownInterval = setInterval(aktionUpdateCountdown, 1000);
    }

    /**
     * Initialisiert Countdown-Timer für Tab 2
     */
    function aktionInitCountdown2() {
        // Elementleri kontrol et
        const countdownEl = document.getElementById('aktion-countdown-2');
        const daysEl = document.getElementById('aktion-countdown-days-2');
        const hoursEl = document.getElementById('aktion-countdown-hours-2');
        const minutesEl = document.getElementById('aktion-countdown-minutes-2');
        const secondsEl = document.getElementById('aktion-countdown-seconds-2');

        if (!daysEl || !hoursEl || !minutesEl || !secondsEl) {
            return;
        }

        // Önceki interval'i temizle
        if (window.aktionCountdownInterval2) {
            clearInterval(window.aktionCountdownInterval2);
        }

        // Countdown-Zieltermin aus HTML data-attribute lesen
        let targetDate;
        if (countdownEl && countdownEl.dataset.countdownDate) {
            targetDate = new Date(countdownEl.dataset.countdownDate).getTime();
        } else {
            // Fallback: Config verwenden falls kein data-attribute vorhanden
            targetDate = aktionConfig2.countdownDate.getTime();
        }

        function aktionUpdateCountdown2() {
            const now = new Date().getTime();
            const distance = targetDate - now;

            if (distance < 0) {
                daysEl.textContent = '00';
                hoursEl.textContent = '00';
                minutesEl.textContent = '00';
                secondsEl.textContent = '00';
                
                if (window.aktionCountdownInterval2) {
                    clearInterval(window.aktionCountdownInterval2);
                    window.aktionCountdownInterval2 = null;
                }
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            daysEl.textContent = String(days).padStart(2, '0');
            hoursEl.textContent = String(hours).padStart(2, '0');
            minutesEl.textContent = String(minutes).padStart(2, '0');
            secondsEl.textContent = String(seconds).padStart(2, '0');
        }

        aktionUpdateCountdown2();
        window.aktionCountdownInterval2 = setInterval(aktionUpdateCountdown2, 1000);
    }

    /**
     * Richtet Slider für Tab 2 ein
     */
    function aktionSetupThumbnails2() {
        aktionInitSlider2();
        aktionSetupSliderButtons2();
        aktionSetupSliderDots2();
        aktionSetupThumbnailClicks2();
        aktionSetupSliderHover2();
    }

    /**
     * Initialisiert Slider für Tab 2
     */
    function aktionInitSlider2() {
        const sliderContainer = document.getElementById('aktion-slider-container-2');
        if (!sliderContainer) return;
        const slides = sliderContainer.querySelectorAll('.aktion-slide');
        if (slides.length > 0) {
            slides.forEach(function(slide, index) {
                if (index === 0) {
                    slide.classList.add('active');
                } else {
                    slide.classList.remove('active');
                }
            });
        }
    }

    /**
     * Richtet Slider-Buttons für Tab 2 ein
     */
    function aktionSetupSliderButtons2() {
        const prevBtn = document.getElementById('aktion-slider-prev-2');
        const nextBtn = document.getElementById('aktion-slider-next-2');
        
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                aktionStopAutoSlide2();
                aktionPreviousSlide2();
                aktionStartAutoSlide2();
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                aktionStopAutoSlide2();
                aktionNextSlide2();
                aktionStartAutoSlide2();
            });
        }
    }

    /**
     * Richtet Slider-Dots für Tab 2 ein
     */
    function aktionSetupSliderDots2() {
        const dotsContainer = document.getElementById('aktion-slider-dots-2');
        if (!dotsContainer) return;
        
        dotsContainer.innerHTML = '';
        
        for (let i = 0; i < aktionImages2.length; i++) {
            const dot = document.createElement('div');
            dot.className = 'aktion-slider-dot' + (i === 0 ? ' active' : '');
            dot.dataset.index = i;
            dot.addEventListener('click', function() {
                aktionStopAutoSlide2();
                aktionGoToSlide2(parseInt(this.dataset.index));
                aktionStartAutoSlide2();
            });
            dotsContainer.appendChild(dot);
        }
    }

    /**
     * Richtet Thumbnail-Klicks für Tab 2 ein
     */
    function aktionSetupThumbnailClicks2() {
        const sliderContainer = document.getElementById('aktion-slider-container-2');
        if (!sliderContainer) return;
        const sliderWrapper = sliderContainer.closest('.aktion-slider-wrapper');
        if (!sliderWrapper) return;
        const thumbnails = sliderWrapper.nextElementSibling.querySelectorAll('.aktion-thumbnail');
        
        thumbnails.forEach(function(thumbnail) {
            thumbnail.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                aktionStopAutoSlide2();
                aktionGoToSlide2(index);
                aktionStartAutoSlide2();
            });
        });
    }

    /**
     * Geht zum nächsten Slide für Tab 2
     */
    function aktionNextSlide2() {
        aktionCurrentSlide2 = (aktionCurrentSlide2 + 1) % aktionImages2.length;
        aktionUpdateSlider2();
    }

    /**
     * Geht zum vorherigen Slide für Tab 2
     */
    function aktionPreviousSlide2() {
        aktionCurrentSlide2 = (aktionCurrentSlide2 - 1 + aktionImages2.length) % aktionImages2.length;
        aktionUpdateSlider2();
    }

    /**
     * Geht zu einem bestimmten Slide für Tab 2
     */
    function aktionGoToSlide2(index) {
        if (index >= 0 && index < aktionImages2.length) {
            aktionCurrentSlide2 = index;
            aktionUpdateSlider2();
        }
    }

    /**
     * Aktualisiert Slider-Anzeige für Tab 2
     */
    function aktionUpdateSlider2() {
        const sliderContainer = document.getElementById('aktion-slider-container-2');
        if (!sliderContainer) return;
        const slides = sliderContainer.querySelectorAll('.aktion-slide');
        const sliderWrapper = sliderContainer.closest('.aktion-slider-wrapper');
        if (!sliderWrapper) return;
        const thumbnails = sliderWrapper.nextElementSibling.querySelectorAll('.aktion-thumbnail');
        const dots = sliderWrapper.querySelectorAll('.aktion-slider-dot');
        
        // Slides aktualisieren
        slides.forEach(function(slide, index) {
            if (index === aktionCurrentSlide2) {
                slide.classList.add('active');
            } else {
                slide.classList.remove('active');
            }
        });
        
        // Thumbnails aktualisieren
        thumbnails.forEach(function(thumb, index) {
            if (index === aktionCurrentSlide2) {
                thumb.classList.add('active');
            } else {
                thumb.classList.remove('active');
            }
        });
        
        // Dots aktualisieren
        dots.forEach(function(dot, index) {
            if (index === aktionCurrentSlide2) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    /**
     * Startet automatisches Sliden für Tab 2
     */
    function aktionStartAutoSlide2() {
        aktionStopAutoSlide2();
        aktionSliderInterval2 = setInterval(function() {
            aktionNextSlide2();
        }, 4000);
    }

    /**
     * Stoppt automatisches Sliden für Tab 2
     */
    function aktionStopAutoSlide2() {
        if (aktionSliderInterval2) {
            clearInterval(aktionSliderInterval2);
            aktionSliderInterval2 = null;
        }
    }

    /**
     * Richtet Hover-Verhalten für Slider Tab 2 ein
     */
    function aktionSetupSliderHover2() {
        const sliderContainer = document.getElementById('aktion-slider-container-2');
        if (!sliderContainer) return;
        const sliderWrapper = sliderContainer.closest('.aktion-slider-wrapper');
        if (sliderWrapper) {
            sliderWrapper.addEventListener('mouseenter', function() {
                aktionStopAutoSlide2();
            });
            sliderWrapper.addEventListener('mouseleave', function() {
                aktionStartAutoSlide2();
            });
        }
    }

    /**
     * Handler für Kauf-Button Klick Tab 1
     */
    function aktionHandleBuyClick1(event) {
        event.preventDefault();
        // Tab 1 ürünü için Auflage sayfasına yönlendir
        window.location.href = 'https://bremer-sitzbezuege.de/Auflage';
    }

    /**
     * Handler für Kauf-Button Klick Tab 2
     */
    function aktionHandleBuyClick2(event) {
        event.preventDefault();
        // Tab 2 ürünü için 5 Fenster Einlagig Gardinen sayfasına yönlendir
        window.location.href = 'https://bremer-sitzbezuege.de/5-Fenster-Einlagig-Gardinen-mit-Aluprofile';
    }

    /**
     * Ruft Produkt-ID von JTL Shop ab
     * Diese Funktion sollte entsprechend der JTL Shop Struktur angepasst werden
     */
    function aktionGetProductId() {
        // Beispiel-Methoden um Produkt-ID von JTL Shop abzurufen:
        
        // Methode 1: Von URL-Parametern
        const urlParams = new URLSearchParams(window.location.search);
        const productId = urlParams.get('id') || urlParams.get('aID');
        
        // Methode 2: Von Data-Attribut
        if (!productId) {
            const container = document.querySelector('.aktion-container');
            if (container && container.dataset.productId) {
                return container.dataset.productId;
            }
        }
        
        // Methode 3: Von JTL Shop globaler Variable
        if (!productId && typeof window.jtlProductId !== 'undefined') {
            return window.jtlProductId;
        }
        
        return productId || null;
    }

    /**
     * Lädt Produktinformationen von JTL Shop
     * Diese Funktion sollte entsprechend der JTL Shop API angepasst werden
     */
    function aktionLoadProductFromJTL() {
        // Beispiel-Methoden um Produktinformationen von JTL Shop abzurufen:
        
        // Methode 1: Von Data-Attributen
        const container = document.querySelector('.aktion-container');
        if (container) {
            if (container.dataset.productName) {
                aktionConfig.productName = container.dataset.productName;
            }
            if (container.dataset.productDesc) {
                aktionConfig.productDesc = container.dataset.productDesc;
            }
            if (container.dataset.productImage) {
                aktionConfig.productImage = container.dataset.productImage;
            }
            if (container.dataset.priceOld) {
                aktionConfig.priceOld = parseFloat(container.dataset.priceOld);
            }
            if (container.dataset.priceNew) {
                aktionConfig.priceNew = parseFloat(container.dataset.priceNew);
            }
            if (container.dataset.totalStock) {
                aktionConfig.totalStock = parseInt(container.dataset.totalStock);
            }
            if (container.dataset.soldCount) {
                aktionConfig.soldCount = parseInt(container.dataset.soldCount);
            }
            
            aktionUpdateProductInfo();
            aktionUpdatePricing();
            aktionUpdateStockInfo();
        }
        
        // Methode 2: AJAX-Abruf von JTL Shop API (Beispiel)
        // if (typeof jQuery !== 'undefined') {
        //     jQuery.ajax({
        //         url: '/api/product/' + aktionGetProductId(),
        //         method: 'GET',
        //         success: function(data) {
        //             aktionConfig.productName = data.name;
        //             aktionConfig.priceNew = data.price;
        //             // ... weitere Felder
        //             aktionUpdateProductInfo();
        //             aktionUpdatePricing();
        //             aktionUpdateStockInfo();
        //         }
        //     });
        // }
    }

    /**
     * Aktualisiert Lagerbestandszahl (für Echtzeit-Updates)
     */
    function aktionUpdateStockCount(soldCount, totalStock) {
        aktionConfig.soldCount = soldCount;
        aktionConfig.totalStock = totalStock;
        aktionUpdateStockInfo();
    }

    /**
     * Aktualisiert Preise
     */
    function aktionUpdatePrices(oldPrice, newPrice) {
        aktionConfig.priceOld = oldPrice;
        aktionConfig.priceNew = newPrice;
        aktionUpdatePricing();
    }

    /**
     * Aktualisiert Produktinformationen für Tab 2
     */
    function aktionUpdateProductInfo2() {
        const productNameEl = document.getElementById('aktion-product-name-2');
        const productDescEl = document.getElementById('aktion-product-desc-2');

        if (productNameEl) {
            productNameEl.textContent = aktionConfig2.productName;
        }

        if (productDescEl) {
            productDescEl.textContent = aktionConfig2.productDesc;
        }
    }

    /**
     * Aktualisiert Preisinformationen für Tab 2
     */
    function aktionUpdatePricing2() {
        const priceOldEl = document.getElementById('aktion-price-old-2');
        const priceNewEl = document.getElementById('aktion-price-new-2');
        const discountBadgeEl = document.getElementById('aktion-discount-badge-2');

        if (priceOldEl) {
            priceOldEl.textContent = '€' + aktionConfig2.priceOld.toFixed(2).replace('.', ',');
        }

        if (priceNewEl) {
            priceNewEl.textContent = '€' + aktionConfig2.priceNew.toFixed(2).replace('.', ',');
        }

        if (discountBadgeEl) {
            const discountPercent = Math.round(((aktionConfig2.priceOld - aktionConfig2.priceNew) / aktionConfig2.priceOld) * 100);
            discountBadgeEl.textContent = '-' + discountPercent + '%';
        }
    }

    /**
     * Aktualisiert Lagerbestandsinformationen für Tab 2
     */
    function aktionUpdateStockInfo2() {
        const soldCountEl = document.getElementById('aktion-sold-count-2');
        const totalCountEl = document.getElementById('aktion-total-count-2');
        const remainingCountEl = document.getElementById('aktion-remaining-count-2');
        const soldPercentageEl = document.getElementById('aktion-sold-percentage-2');
        const urgencyTextEl = document.getElementById('aktion-urgency-text-2');
        const progressFillEl = document.getElementById('aktion-progress-fill-2');

        const remaining = aktionConfig2.totalStock - aktionConfig2.soldCount;
        const soldPercentage = Math.round((aktionConfig2.soldCount / aktionConfig2.totalStock) * 100);
        const remainingPercentage = 100 - soldPercentage;

        if (soldCountEl) {
            soldCountEl.textContent = aktionConfig2.soldCount;
        }

        if (totalCountEl) {
            totalCountEl.textContent = aktionConfig2.totalStock;
        }

        if (remainingCountEl) {
            remainingCountEl.textContent = remaining + ' Verbleibend';
        }

        if (soldPercentageEl) {
            soldPercentageEl.textContent = soldPercentage + '% verkauft';
        }

        const urgencyEl = document.getElementById('aktion-urgency-2');
        if (urgencyTextEl && urgencyEl) {
            urgencyTextEl.textContent = 'Nur noch ' + remaining + ' Stück verfügbar!';
            urgencyEl.classList.remove('hidden');
        }

        if (progressFillEl) {
            progressFillEl.style.width = soldPercentage + '%';
        }
    }

    // Funktionen für globalen Zugriff zum window-Objekt hinzufügen (optional)
    window.aktionUpdateStockCount = aktionUpdateStockCount;
    window.aktionUpdatePrices = aktionUpdatePrices;
    window.aktionConfig = aktionConfig;

    // Beim Laden der Seite initialisieren
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', aktionInit);
    } else {
        aktionInit();
    }

})();