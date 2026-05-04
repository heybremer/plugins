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
                return {
                    month,
                    title,
                    location,
                    date,
                    stand,
                    website,
                    directions: directions || (location && title ? getDirectionsUrl(location, title) : '')
                };
            });
        } catch (e) {
            return [];
        }
    }

    const messeKalenderData = loadEventsFromJson();

    // Initialize the calendar
    function initMesseKalender() {
        renderMesseKalender();
        setupMesseKalenderFilters();
        setupMesseKalenderEventDetails();

        // Update countdowns immediately
        setTimeout(updateCountdowns, 100);

        // Update countdowns every minute
        setInterval(updateCountdowns, 60000);
    }

    // Render all events in the calendar
    function renderMesseKalender(filterMonth = 'all') {
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
            if (!eventsByMonth[event.month]) {
                eventsByMonth[event.month] = [];
            }
            eventsByMonth[event.month].push(event);
        });

        // Sort months
        const monthOrder = ['januar', 'februar', 'märz', 'august', 'september', 'oktober', 'november'];
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

        // Update countdowns after rendering
        setTimeout(updateCountdowns, 100);
    }

    // Create an event card element
    function createMesseKalenderEventCard(event, month, isFirstInMonth) {
        const card = document.createElement('div');
        card.className = 'messe-kalender-event-card';
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

    // Setup filter buttons
    function setupMesseKalenderFilters() {
        const filterButtons = document.querySelectorAll('.messe-kalender-filter-btn');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                filterButtons.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                btn.classList.add('active');

                // Filter events by month
                const month = btn.dataset.month;
                renderMesseKalender(month);
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

    function parseEventDate(dateStr) {
        // Original template uses various formats like 9–11.01.2026 or 06.-08-11-2026
        const s = String(dateStr || '').trim();
        if (!s) return null;

        // Try to find dd.mm.yyyy at end
        let m = s.match(/(\d{1,2})\D+(\d{1,2})\D+(\d{4})/);
        if (!m) return null;
        const day = parseInt(m[1], 10);
        const month = parseInt(m[2], 10);
        const year = parseInt(m[3], 10);
        if (!day || !month || !year) return null;
        return new Date(year, month - 1, day);
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

