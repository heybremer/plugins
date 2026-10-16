<div class="messe-kalender-wrapper" data-messe-kalender="1">
  <div class="messe-kalender-container">
    <div class="messe-kalender-header" role="banner">
      <h1 style="color: white;">{$mkHeadline|default:'Messe Kalender'|escape:'html'}</h1>
      <p class="messe-kalender-subtitle">
        {$mkSubtitle|default:''|escape:'html'}
      </p>
    </div>

    <div class="messe-kalender-filter-section" id="messe-kalender-status-filters">
      <button class="messe-kalender-filter-btn active" data-status="upcoming" type="button"><span>Kommende Messen</span></button>
      <button class="messe-kalender-filter-btn" data-status="past" type="button"><span>Beendete Messen</span></button>
    </div>
    <div class="messe-kalender-filter-section" id="messe-kalender-year-filters"></div>
    <div class="messe-kalender-filter-section" id="messe-kalender-month-filters">
      <button class="messe-kalender-filter-btn active" data-month="all" type="button"><span>Alle</span></button>
      <button class="messe-kalender-filter-btn" data-month="januar" type="button"><span>Januar</span></button>
      <button class="messe-kalender-filter-btn" data-month="februar" type="button"><span>Februar</span></button>
      <button class="messe-kalender-filter-btn" data-month="märz" type="button"><span>März</span></button>
      <button class="messe-kalender-filter-btn" data-month="april" type="button"><span>April</span></button>
      <button class="messe-kalender-filter-btn" data-month="mai" type="button"><span>Mai</span></button>
      <button class="messe-kalender-filter-btn" data-month="juni" type="button"><span>Juni</span></button>
      <button class="messe-kalender-filter-btn" data-month="juli" type="button"><span>Juli</span></button>
      <button class="messe-kalender-filter-btn" data-month="august" type="button"><span>August</span></button>
      <button class="messe-kalender-filter-btn" data-month="september" type="button"><span>September</span></button>
      <button class="messe-kalender-filter-btn" data-month="oktober" type="button"><span>Oktober</span></button>
      <button class="messe-kalender-filter-btn" data-month="november" type="button"><span>November</span></button>
      <button class="messe-kalender-filter-btn" data-month="dezember" type="button"><span>Dezember</span></button>
    </div>

    <div class="messe-kalender-calendar-container">
      <div class="messe-kalender-timeline" id="messe-kalender-timeline">
        <!-- Events will be inserted here by JavaScript -->
      </div>
    </div>

    <div class="messe-kalender-event-details" id="messe-kalender-event-details">
      <div class="messe-kalender-details-content">
        <button class="messe-kalender-close-btn" id="messe-kalender-close-btn" type="button">&times;</button>

        <h2 id="messe-kalender-detail-title"></h2>

        <div class="messe-kalender-detail-info">
          <p><strong>Ort:</strong> <span id="messe-kalender-detail-location"></span></p>
          <p><strong>Termin:</strong> <span id="messe-kalender-detail-date"></span></p>
          <p><strong>Stand:</strong> <span id="messe-kalender-detail-stand"></span></p>
        </div>

        <div class="messe-kalender-detail-links" id="messe-kalender-detail-links">
          <!-- Links will be inserted here by JavaScript -->
        </div>
      </div>
    </div>
  </div>

  <script type="application/json" id="messe-kalender-data">{$mkDataJson|default:'{}' nofilter}</script>
</div>
