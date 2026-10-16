<div class="container opc-Container">
<section class="messe-home" id="messe-home-widget" aria-label="Messe Termine">
  <div class="messe-home-inner">
    <div class="messe-home-header">
      <h2 class="messe-home-title">{$mkHomeHeadline|default:'Messe Kalender'|escape:'html'}</h2>
      {if $mkHomeSubtitle}
        <p class="messe-home-subtitle">{$mkHomeSubtitle|escape:'html'}</p>
      {/if}
    </div>

    <div class="messe-home-grid">
      {foreach $mkHomeEvents as $ev}
        <article class="messe-home-card">
          {if $ev.month|default:'' != ''}
            <div class="messe-home-card-month">{$ev.month|escape:'html'}</div>
          {/if}
          <div class="messe-home-card-body">
            <div class="messe-home-card-date">{$ev.date|default:''|escape:'html'}</div>
            <h3 class="messe-home-card-title">{$ev.title|default:''|escape:'html'}</h3>
            <p class="messe-home-card-location">
              <span class="messe-home-label">Ort:</span> {$ev.location|default:''|escape:'html'}
            </p>
            {if $ev.stand && $ev.stand != '-'}
              <p class="messe-home-card-stand">
                <span class="messe-home-label">Stand:</span> {$ev.stand|escape:'html'}
              </p>
            {/if}
            {if $ev.website|default:''|trim != ''}
              <a class="messe-home-card-link" href="{$ev.website|escape:'html'}" target="_blank" rel="noopener noreferrer">Messe-Website</a>
            {/if}
          </div>
        </article>
      {/foreach}
    </div>

    <div class="messe-home-footer">
      <a href="{$mkCalendarUrl|default:'/messe-kalender'|escape:'html'}" class="messe-home-cta">Alle Messetermine ansehen</a>
    </div>
  </div>
</section>
</div>