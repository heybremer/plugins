// Messe Kalender - Plugin Version (DB-driven)
(function() {
    'use strict';

    // Helper function to generate Google Maps directions URL
    function getDirectionsUrl(location, title) {
        const query = encodeURIComponent(`${title} ${location} Messe`);
        return `https://www.google.com/maps/dir/?api=1&destination=${query}`;
    }

    function loadEventsFromJson() {
        const el = document.getElementById('messe-kalender-data');
        if (!el) {
            return [];
        }
        try {
            const raw = (el.textContent || '').trim();
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            const events = parsed && Array.isArray(parsed.events) ? parsed.events : [];
            return events.map((e) => {
                const month = String(e.month || '');
                const title = String(e.title || '');
                const location = String(e.location || '');
                const date = String(e.date || '');
                const stand = String(e.stand || '');
                const website = String(e.website || '');
                const directions = String(e.directions || '');
                let year = parseInt(e.year, 10) || 0;
                if (!year) {
                    const ym = date.match(/(20\d{2})/);
                    year = ym ? parseInt(ym[1], 10) : 0;
                }
                return {
                    month,
                    title,
                    location,
                    date,
                    stand,
                    website,
                    year,
                    directions: directions || (location && title ? getDirectionsUrl(location, title) : '')
                };
            });
        } catch (e) {
            return [];
        }
    }

    const messeKalenderData = loadEventsFromJson();
    let currentYear = 'all';
    let currentMonth = 'all';
    let currentStatus = 'upcoming';

    // Initialize the calendar
    function initMesseKalender() {
        setupYearFilters();
        setupStatusFilters();
        renderMesseKalender();
        setupMesseKalenderFilters();
        setupMesseKalenderEventDetails();

        // Update countdowns immediately
        setTimeout(updateCountdowns, 100);

        // Update countdowns every minute
        setInterval(updateCountdowns, 60000);
    }

    // Render all events in the calendar
    function renderMesseKalender(filterMonth = currentMonth) {
        const timeline = document.getElementById('messe-kalender-timeline');
        if (!timeline) return;

        timeline.innerHTML = '';

        if (!Array.isArray(messeKalenderData) || messeKalenderData.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'messe-kalender-event-card';
            empty.innerHTML = '<div class="messe-kalender-event-content"><div class="messe-kalender-event-title">Keine Termine vorhanden</div></div>';
            timeline.appendChild(empty);
            return;
        }

        // Group events by month
        const eventsByMonth = {};
        messeKalenderData.forEach(event => {
            const past = isEventCompleted(event.date);
            if (currentStatus === 'past' ? !past : past) {
                return;
            }
            if (currentYear !== 'all' && String(event.year) !== String(currentYear)) {
                return;
            }
            if (!eventsByMonth[event.month]) {
                eventsByMonth[event.month] = [];
            }
            eventsByMonth[event.month].push(event);
        });

        // Sort months
        const monthOrder = ['januar', 'februar', 'märz', 'april', 'mai', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'dezember'];
        const sortedMonths = Object.keys(eventsByMonth).sort((a, b) => {
            const ai = monthOrder.indexOf(a);
            const bi = monthOrder.indexOf(b);
            if (ai === -1 && bi === -1) return a.localeCompare(b);
            if (ai === -1) return 1;
            if (bi === -1) return -1;
            return ai - bi;
        });

        // Render events with stagger animation
        let cardIndex = 0;
        sortedMonths.forEach(month => {
            if (filterMonth !== 'all' && filterMonth !== month) {
                return;
            }

            const monthEvents = eventsByMonth[month];
            monthEvents.forEach((event, index) => {
                const eventCard = createMesseKalenderEventCard(event, month, index === 0);
                timeline.appendChild(eventCard);

                // Stagger animation
                eventCard.style.animationDelay = `${cardIndex * 0.1}s`;
                cardIndex++;
            });
        });

        // Setup intersection observer for scroll animations
        setupMesseKalenderScrollAnimations();

        if (!timeline.children.length) {
            const empty = document.createElement('div');
            empty.className = 'messe-kalender-event-card';
            empty.innerHTML = '<div class="messe-kalender-event-content"><div class="messe-kalender-event-title">'
                + (currentStatus === 'past' ? 'Keine beendeten Messen' : 'Keine kommenden Messen')
                + '</div></div>';
            timeline.appendChild(empty);
        }

        // Update countdowns after rendering
        setTimeout(updateCountdowns, 100);
    }

    // Create an event card element
    function createMesseKalenderEventCard(event, month, isFirstInMonth) {
        const card = document.createElement('div');
        card.className = 'messe-kalender-event-card' + (isEventCompleted(event.date) ? ' is-past' : '');
        card.dataset.month = month;

        const monthLabel = `<div class="messe-kalender-event-month">${capitalizeFirst(month)}</div>`;

        const countdownText = getCountdownText(event.date);
        const countdownBadge = countdownText ? `<div class="messe-kalender-countdown">${countdownText}</div>` : '';
        const statusBadge = isEventCompleted(event.date)
            ? '<div class="messe-kalender-status-badge" aria-label="Abgeschlossen">Abgeschlossen</div>'
            : '';

        card.innerHTML = `
            ${monthLabel}
            <div class="messe-kalender-event-content" data-event-id="${escapeHtml(event.title)}">
                ${countdownBadge}
                ${statusBadge}
                <div class="messe-kalender-event-date">${escapeHtml(event.date)}</div>
                <div class="messe-kalender-event-title">${escapeHtml(event.title)}</div>
                <div class="messe-kalender-event-info">
                    <div class="messe-kalender-event-info-item">
                        <strong>Ort:</strong>
                        <span class="messe-kalender-event-location">${escapeHtml(event.location)}</span>
                    </div>
                </div>
                <div class="messe-kalender-event-stand">
                    <strong>Stand:</strong> ${escapeHtml(String(event.stand || '').substring(0, 100))}${String(event.stand || '').length > 100 ? '...' : ''}
                </div>
            </div>
        `;

        // Add click event to show details
        const content = card.querySelector('.messe-kalender-event-content');
        if (content) {
            content.addEventListener('click', () => {
                showMesseKalenderEventDetails(event);
            });
        }

        return card;
    }

    function setupYearFilters() {
        const wrap = document.getElementById('messe-kalender-year-filters');
        if (!wrap) return;
        const years = [];
        messeKalenderData.forEach(function (e) {
            const y = parseInt(e.year, 10) || 0;
            if (y >= 1970 && years.indexOf(y) === -1) {
                years.push(y);
            }
        });
        years.sort(function (a, b) { return a - b; });
        if (years.length < 2) {
            wrap.style.display = 'none';
            return;
        }
        wrap.innerHTML = '';
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = 'messe-kalender-filter-btn active';
        allBtn.dataset.year = 'all';
        allBtn.innerHTML = '<span>Alle Jahre</span>';
        wrap.appendChild(allBtn);
        years.forEach(function (y) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'messe-kalender-filter-btn';
            btn.dataset.year = String(y);
            btn.innerHTML = '<span>' + y + '</span>';
            wrap.appendChild(btn);
        });
        wrap.querySelectorAll('.messe-kalender-filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                wrap.querySelectorAll('.messe-kalender-filter-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentYear = btn.dataset.year;
                renderMesseKalender(currentMonth);
            });
        });
    }

    // Setup filter buttons
    function setupStatusFilters() {
        const wrap = document.getElementById('messe-kalender-status-filters');
        if (!wrap) return;
        wrap.querySelectorAll('[data-status]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                wrap.querySelectorAll('[data-status]').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentStatus = btn.dataset.status || 'upcoming';
                document.body.classList.toggle('messe-kalender-show-past', currentStatus === 'past');
                renderMesseKalender(currentMonth);
            });
        });
    }

    function setupMesseKalenderFilters() {
        const monthWrap = document.getElementById('messe-kalender-month-filters');
        const filterButtons = monthWrap
            ? monthWrap.querySelectorAll('.messe-kalender-filter-btn')
            : [];

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                filterButtons.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                btn.classList.add('active');

                // Filter events by month
                currentMonth = btn.dataset.month || 'all';
                renderMesseKalender(currentMonth);
            });
        });
    }

    // Setup event details modal
    function setupMesseKalenderEventDetails() {
        const closeBtn = document.getElementById('messe-kalender-close-btn');
        const eventDetails = document.getElementById('messe-kalender-event-details');

        if (!closeBtn || !eventDetails) return;

        closeBtn.addEventListener('click', () => {
            eventDetails.classList.remove('active');
        });

        eventDetails.addEventListener('click', (e) => {
            if (e.target === eventDetails) {
                eventDetails.classList.remove('active');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && eventDetails.classList.contains('active')) {
                eventDetails.classList.remove('active');
            }
        });
    }

    // Show event details in modal
    function showMesseKalenderEventDetails(event) {
        const eventDetails = document.getElementById('messe-kalender-event-details');
        if (!eventDetails) return;

        const titleEl = document.getElementById('messe-kalender-detail-title');
        const locationEl = document.getElementById('messe-kalender-detail-location');
        const dateEl = document.getElementById('messe-kalender-detail-date');
        const standEl = document.getElementById('messe-kalender-detail-stand');
        const linksEl = document.getElementById('messe-kalender-detail-links');

        if (titleEl) titleEl.textContent = event.title || '';
        if (locationEl) locationEl.textContent = event.location || '';
        if (dateEl) dateEl.textContent = event.date || '';
        if (standEl) standEl.textContent = event.stand || '';

        if (linksEl) {
            linksEl.innerHTML = '';
            if (event.website) {
                const a = document.createElement('a');
                a.href = event.website;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = 'messe-kalender-detail-link';
                a.textContent = 'Website';
                linksEl.appendChild(a);
            }
            if (event.directions) {
                const a = document.createElement('a');
                a.href = event.directions;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = 'messe-kalender-detail-link';
                a.textContent = 'Route planen';
                linksEl.appendChild(a);
            }
        }

        eventDetails.classList.add('active');
    }

    // Countdown badge helpers
    function getCountdownText(dateRangeStr) {
        // Extract first date in range (best effort)
        const m = String(dateRangeStr || '').match(/(\d{1,2})[.\-–](\d{1,2})[.\-–](\d{4})|(\d{1,2})[.\-–](\d{1,2})[.\-–](\d{4})/);
        // Keep original logic from template JS by using parseEventDate
        const eventDate = parseEventDate(String(dateRangeStr || ''));
        if (!eventDate) return '';
        const now = new Date();
        const diffMs = eventDate.getTime() - now.getTime();
        if (diffMs <= 0) return '';
        const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diffMs / (1000 * 60 * 60)) % 24);
        const mins = Math.floor((diffMs / (1000 * 60)) % 60);
        if (days > 0) return `${days} Tage`;
        if (hours > 0) return `${hours} Stunden`;
        return `${mins} Minuten`;
    }

    function dateFromParts(day, month, year) {
        day = parseInt(day, 10);
        month = parseInt(month, 10);
        year = parseInt(year, 10);
        if (!day || !month || !year || month > 12 || day > 31) return null;
        return new Date(year, month - 1, day);
    }

    function parseEventDate(dateStr) {
        return parseEventEnd(dateStr);
    }

    function parseEventEnd(dateStr) {
        const s = String(dateStr || '').trim();
        if (!s) return null;
        const dotted = Array.from(s.matchAll(/(\d{1,2})\.(\d{1,2})\.(\d{4})/g));
        if (dotted.length) {
            const last = dotted[dotted.length - 1];
            return dateFromParts(last[1], last[2], last[3]);
        }
        const range = s.match(/(\d{1,2})\s*[.\-–]+\s*(\d{1,2})[.\-–]+(\d{1,2})[.\-–]+(\d{4})/);
        if (range) {
            return dateFromParts(range[2], range[3], range[4]);
        }
        const matches = Array.from(s.matchAll(/(\d{1,2})[.\-](\d{1,2})[.\-](\d{4})/g));
        if (!matches.length) return null;
        const last = matches[matches.length - 1];
        return dateFromParts(last[1], last[2], last[3]);
    }

    function updateCountdowns() {
        const cards = document.querySelectorAll('.messe-kalender-event-card');
        cards.forEach(card => {
            const titleEl = card.querySelector('.messe-kalender-event-title');
            const dateEl = card.querySelector('.messe-kalender-event-date');
            if (!titleEl || !dateEl) return;
            const title = titleEl.textContent || '';
            const date = dateEl.textContent || '';
            const countdownText = getCountdownText(date);
            const badge = card.querySelector('.messe-kalender-countdown');
            if (countdownText) {
                if (badge) {
                    badge.textContent = countdownText;
                }
            } else if (badge) {
                badge.remove();
            }
        });
    }

    // Setup intersection observer for scroll animations
    function setupMesseKalenderScrollAnimations() {
        const eventCards = document.querySelectorAll('.messe-kalender-event-card');
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateX(0)';
                }
            });
        }, observerOptions);

        eventCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateX(-30px)';
            card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
            observer.observe(card);
        });
    }

    function capitalizeFirst(str) {
        const s = String(str || '');
        if (!s) return '';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isEventCompleted(dateStr) {
        const d = parseEventDate(String(dateStr || ''));
        if (!d) {
            return false;
        }
        const now = new Date();
        // Completed if event date is in the past (ignore time)
        return d.getTime() < now.getTime();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMesseKalender);
    } else {
        initMesseKalender();
    }

})();

