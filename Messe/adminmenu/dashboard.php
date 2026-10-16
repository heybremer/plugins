<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\Messe\src\MesseDatabase;

require_once dirname(__DIR__) . '/src/MesseDatabase.php';

$db = Shop::Container()->getDB();
MesseDatabase::ensureAll();
$message = '';

/**
 * @param string $s
 */
if (!function_exists('mk_h')) {
    function mk_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mk_slugify')) {
    function mk_slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9\\-]+/i', '-', $s) ?? $s;
        $s = trim($s, '-');
        return $s !== '' ? $s : 'messe-kalender';
    }
}

if (!function_exists('mk_month_key')) {
    function mk_month_key(DateTimeImmutable $date): string
    {
        $months = [
            1 => 'januar', 2 => 'februar', 3 => 'märz', 4 => 'april', 5 => 'mai', 6 => 'juni',
            7 => 'juli', 8 => 'august', 9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'dezember',
        ];

        return $months[(int)$date->format('n')] ?? '';
    }
}

if (!function_exists('mk_format_range')) {
    function mk_format_range(DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            return $start->format('d.m.Y');
        }
        if ($start->format('m.Y') === $end->format('m.Y')) {
            return $start->format('j.') . '–' . $end->format('j.m.Y');
        }

        return $start->format('j.m.') . '–' . $end->format('j.m.Y');
    }
}

if (!function_exists('mk_dates_from_post')) {
    /**
     * @return array{start:DateTimeImmutable,end:DateTimeImmutable,month:string,year:int,text:string}|null
     */
    function mk_dates_from_post(): ?array
    {
        $startRaw = trim((string)($_POST['dStart'] ?? ''));
        $endRaw = trim((string)($_POST['dEnde'] ?? ''));
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startRaw);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw);
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return null;
        }
        if ($end < $start) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
            'month' => mk_month_key($start),
            'year' => (int)$start->format('Y'),
            'text' => mk_format_range($start, $end),
        ];
    }
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['mkSaveSettings'])) {
            $active = !empty($_POST['nActive']) ? 1 : 0;
            $slug = mk_slugify((string)($_POST['cPageSlug'] ?? 'messe-kalender'));
            $headline = trim((string)($_POST['cHeadline'] ?? 'Messe Kalender'));
            $subtitle = trim((string)($_POST['cSubtitle'] ?? ''));
            $homepageActive = !empty($_POST['nHomepageActive']) ? 1 : 0;
            $homepageSelector = trim((string)($_POST['cHomepageInjectSelector'] ?? 'div.index-filter-konfigurator'));
            $homepageMode = trim((string)($_POST['cHomepageInjectMode'] ?? 'after'));
            $homepageLimit = max(1, min(24, (int)($_POST['nHomepageLimit'] ?? 6)));
            if ($headline === '') {
                $headline = 'Messe Kalender';
            }
            if ($homepageSelector === '') {
                $homepageSelector = 'div.index-filter-konfigurator';
            }
            if (!in_array($homepageMode, ['after', 'before', 'prepend', 'append'], true)) {
                $homepageMode = 'after';
            }

            $row = $db->getSingleObject('SELECT kID FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            if ($row !== null) {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_messe_settings
                        SET nActive = :a,
                            cPageSlug = :s,
                            cHeadline = :h,
                            cSubtitle = :sub,
                            nHomepageActive = :hpa,
                            cHomepageInjectSelector = :hps,
                            cHomepageInjectMode = :hpm,
                            nHomepageLimit = :hpl,
                            dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'id' => (int)$row->kID,
                        'a' => $active,
                        's' => $slug,
                        'h' => $headline,
                        'sub' => $subtitle,
                        'hpa' => $homepageActive,
                        'hps' => $homepageSelector,
                        'hpm' => $homepageMode,
                        'hpl' => $homepageLimit,
                    ]
                );
            } else {
                $ins = new stdClass();
                $ins->nActive = $active;
                $ins->cPageSlug = $slug;
                $ins->cHeadline = $headline;
                $ins->cSubtitle = $subtitle;
                $ins->nHomepageActive = $homepageActive;
                $ins->cHomepageInjectSelector = $homepageSelector;
                $ins->cHomepageInjectMode = $homepageMode;
                $ins->nHomepageLimit = $homepageLimit;
                $ins->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_messe_settings', $ins);
            }
            $message = 'Einstellungen gespeichert.';
        }

        if (!empty($_POST['mkAddEvent'])) {
            $dates = mk_dates_from_post();
            $month = $dates['month'] ?? '';
            $title = trim((string)($_POST['cTitle'] ?? ''));
            $location = trim((string)($_POST['cLocation'] ?? ''));
            $dateText = $dates['text'] ?? '';
            $year = (int)($dates['year'] ?? 0);
            $stand = trim((string)($_POST['cStand'] ?? ''));
            $website = trim((string)($_POST['cWebsiteUrl'] ?? ''));
            $directions = trim((string)($_POST['cDirectionsUrl'] ?? ''));
            $sort = (int)($_POST['nSort'] ?? 100);
            $active = !empty($_POST['nActiveEvent']) ? 1 : 0;

            if ($dates !== null && $title !== '' && $location !== '') {
                $e = new stdClass();
                $e->cMonth = $month;
                $e->cTitle = $title;
                $e->cLocation = $location;
                $e->cDateText = $dateText;
                $e->nJahr = $year;
                $e->dStart = $dates['start']->format('Y-m-d');
                $e->dEnde = $dates['end']->format('Y-m-d');
                $e->cStand = $stand;
                $e->cWebsiteUrl = $website;
                $e->cDirectionsUrl = $directions;
                $e->nSort = $sort;
                $e->nActive = $active;
                $e->dCreated = date('Y-m-d H:i:s');
                $e->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_messe_event', $e);
                $message = 'Event hinzugefügt.';
            } else {
                $message = 'Titel, Ort, Beginn und Ende sind erforderlich.';
            }
        }

        if (!empty($_POST['mkSaveEvent']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $dates = mk_dates_from_post();
            if ($dates === null) {
                $message = 'Beginn und Ende sind erforderlich.';
            } else {
            $month = $dates['month'];
            $title = trim((string)($_POST['cTitle'] ?? ''));
            $location = trim((string)($_POST['cLocation'] ?? ''));
            $dateText = $dates['text'];
            $year = $dates['year'];
            $stand = trim((string)($_POST['cStand'] ?? ''));
            $website = trim((string)($_POST['cWebsiteUrl'] ?? ''));
            $directions = trim((string)($_POST['cDirectionsUrl'] ?? ''));
            $sort = (int)($_POST['nSort'] ?? 100);
            $active = !empty($_POST['nActive']) ? 1 : 0;

            $db->executeQueryPrepared(
                'UPDATE twomosi_messe_event
                    SET cMonth = :m,
                        cTitle = :t,
                        cLocation = :loc,
                        cDateText = :dt,
                        nJahr = :y,
                        dStart = :ds,
                        dEnde = :de,
                        cStand = :st,
                        cWebsiteUrl = :w,
                        cDirectionsUrl = :d,
                        nSort = :s,
                        nActive = :a,
                        dUpdated = NOW()
                  WHERE kID = :id',
                [
                    'id' => $kID,
                    'm' => $month,
                    't' => $title,
                    'loc' => $location,
                    'dt' => $dateText,
                    'y' => $year,
                    'ds' => $dates['start']->format('Y-m-d'),
                    'de' => $dates['end']->format('Y-m-d'),
                    'st' => $stand,
                    'w' => $website,
                    'd' => $directions,
                    's' => $sort,
                    'a' => $active,
                ]
            );
            $message = 'Event gespeichert.';
            }
        }

        if (!empty($_POST['mkDeleteEvent']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $db->executeQueryPrepared('DELETE FROM twomosi_messe_event WHERE kID = :id', ['id' => $kID]);
            $message = 'Event gelöscht.';
        }
    } catch (\Throwable $e) {
        $message = 'Fehler: ' . $e->getMessage();
    }
}

$settings = (object)[
    'nActive' => 1,
    'cPageSlug' => 'messe-kalender',
    'cHeadline' => 'Messe Kalender',
    'cSubtitle' => 'Die Messen, an denen wir als Bremer-Sitzbezüge GmbH teilnehmen werden',
    'nHomepageActive' => 1,
    'cHomepageInjectSelector' => 'div.index-filter-konfigurator',
    'cHomepageInjectMode' => 'after',
    'nHomepageLimit' => 6,
];
try {
    $sr = $db->getSingleObject('SELECT * FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
    if ($sr !== null) {
        $settings = $sr;
    }
} catch (\Throwable $e) {
}

$events = [];
$eventError = '';
foreach ([
    'SELECT kID, cMonth, cTitle, cLocation, cDateText, nJahr, dStart, dEnde, cStand, cWebsiteUrl, cDirectionsUrl, nSort, nActive, dUpdated
       FROM twomosi_messe_event
      ORDER BY COALESCE(dStart, \'9999-12-31\') ASC, nJahr ASC, nSort ASC, kID ASC',
    'SELECT kID, cMonth, cTitle, cLocation, cDateText, nJahr, cStand, cWebsiteUrl, cDirectionsUrl, nSort, nActive, dUpdated
       FROM twomosi_messe_event
      ORDER BY nJahr ASC, nSort ASC, kID ASC',
] as $eventSql) {
    try {
        $loaded = $db->getObjects($eventSql);
        $events = is_array($loaded) ? $loaded : [];
        $eventError = '';
        break;
    } catch (\Throwable $e) {
        $events = [];
        $eventError = $e->getMessage();
    }
}

$activeCount = 0;
foreach ($events as $eventRow) {
    if (!empty($eventRow->nActive)) {
        $activeCount++;
    }
}
$isError = $message !== '' && (str_starts_with($message, 'Fehler') || str_contains($message, 'erforderlich'));
$pageSlug = mk_slugify((string)($settings->cPageSlug ?? 'messe-kalender'));

$months = [
    'januar' => 'Januar',
    'februar' => 'Februar',
    'märz' => 'März',
    'april' => 'April',
    'mai' => 'Mai',
    'juni' => 'Juni',
    'juli' => 'Juli',
    'august' => 'August',
    'september' => 'September',
    'oktober' => 'Oktober',
    'november' => 'November',
    'dezember' => 'Dezember',
];
?>
<style>
.mk-wrap{max-width:1080px;padding:8px 0 24px;color:#1f2933;font-family:"Segoe UI",system-ui,sans-serif;}
.mk-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;}
.mk-head h2{margin:0;font-size:1.45rem;}
.mk-head p{margin:4px 0 0;color:#667085;font-size:.9rem;}
.mk-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;}
.mk-stat{background:#fff;border:1px solid #e6ebf0;border-radius:12px;padding:12px 14px;}
.mk-stat b{display:block;font-size:1.35rem;line-height:1.1;}
.mk-stat span{color:#667085;font-size:.78rem;}
.mk-alert{margin:0 0 14px;padding:10px 12px;border-radius:10px;font-weight:650;}
.mk-alert.ok{background:#e8f7ef;color:#0b3e27;}
.mk-alert.bad{background:#fde8ec;color:#8d1028;}
.mk-panel{margin-bottom:14px;border:1px solid #e6ebf0;border-radius:14px;background:#fff;overflow:hidden;}
.mk-panel > summary{cursor:pointer;list-style:none;padding:14px 16px;font-weight:750;display:flex;justify-content:space-between;gap:12px;align-items:center;}
.mk-panel > summary::-webkit-details-marker{display:none;}
.mk-panel-body{padding:0 16px 16px;}
.mk-search{width:100%;box-sizing:border-box;margin:0 0 12px;padding:10px 12px;border:1px solid #d5dde6;border-radius:10px;}
.mk-year{margin:14px 0 8px;font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;color:#667085;}
.mk-event{border:1px solid #e6ebf0;border-radius:12px;margin-bottom:8px;background:#fbfcfd;}
.mk-event summary{cursor:pointer;list-style:none;display:grid;grid-template-columns:150px 1fr auto;gap:12px;align-items:center;padding:12px;}
.mk-event summary::-webkit-details-marker{display:none;}
.mk-date{font-weight:750;}
.mk-date small,.mk-place{display:block;color:#667085;font-weight:500;font-size:.82rem;}
.mk-title{font-weight:750;}
.mk-badge{display:inline-flex;align-items:center;border-radius:999px;padding:3px 8px;font-size:.72rem;font-weight:750;background:#e8f5ef;color:#197149;}
.mk-badge.off{background:#f3f4f6;color:#6b7280;}
.mk-form{display:grid;grid-template-columns:1fr 1fr;gap:10px 12px;padding:0 12px 12px;}
.mk-field label{display:block;margin-bottom:4px;font-size:.78rem;font-weight:700;color:#475467;}
.mk-field.wide,.mk-actions{grid-column:1 / -1;}
.mk-field input,.mk-field select,.mk-field textarea{width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid #d5dde6;border-radius:8px;background:#fff;}
.mk-check{display:flex;gap:8px;align-items:center;font-weight:650;}
.mk-actions{display:flex;gap:8px;flex-wrap:wrap;}
.mk-btn{border:0;border-radius:8px;padding:9px 14px;cursor:pointer;font-weight:700;}
.mk-btn-save{background:#197149;color:#fff;}
.mk-btn-del{background:#fff;color:#b00020;border:1px solid #f3c3cb;}
.mk-link{color:#197149;font-weight:700;text-decoration:none;}
@media(max-width:720px){.mk-event summary,.mk-form{grid-template-columns:1fr;}.mk-field.wide,.mk-actions{grid-column:auto;}}
</style>

<div class="mk-wrap">
  <div class="mk-head">
    <div>
      <h2>Messe Kalender</h2>
      <p>Termine zuerst prüfen, Details nur bei Bedarf öffnen.</p>
    </div>
    <a class="mk-link" href="/<?= mk_h($pageSlug) ?>" target="_blank" rel="noopener">Seite öffnen</a>
  </div>
  <?php if ($eventError !== ''): ?>
    <div class="mk-alert bad"><?= mk_h($eventError) ?></div>
  <?php endif; ?>
  <?php if ($message !== ''): ?>
    <div class="mk-alert <?= $isError ? 'bad' : 'ok' ?>"><?= mk_h($message) ?></div>
  <?php endif; ?>

  <div class="mk-stats">
    <div class="mk-stat"><b><?= count($events) ?></b><span>Termine</span></div>
    <div class="mk-stat"><b><?= $activeCount ?></b><span>Aktiv</span></div>
    <div class="mk-stat"><b><?= count($events) - $activeCount ?></b><span>Inaktiv</span></div>
    <div class="mk-stat"><b><?= !empty($settings->nHomepageActive) ? 'An' : 'Aus' ?></b><span>Startseite</span></div>
  </div>

  <details class="mk-panel" open>
    <summary>Termine <span class="mk-badge"><?= count($events) ?></span></summary>
    <div class="mk-panel-body">
      <?php if (!count($events)): ?>
        <p>Noch keine Termine angelegt.</p>
      <?php else: ?>
        <input class="mk-search" id="mkSearch" type="search" placeholder="Titel, Ort oder Termin suchen">
        <?php $lastYear = null; ?>
        <?php foreach ($events as $e): ?>
          <?php
            $id = (int)($e->kID ?? 0);
            $year = (int)($e->nJahr ?? 0);
            $monthLabel = (string)($months[(string)($e->cMonth ?? '')] ?? (string)($e->cMonth ?? ''));
            $search = strtolower((string)($e->cTitle ?? '') . ' ' . (string)($e->cLocation ?? '') . ' ' . (string)($e->cDateText ?? '') . ' ' . (string)($e->cStand ?? ''));
          ?>
          <?php if ($year !== $lastYear): ?>
            <div class="mk-year"><?= $year > 0 ? $year : 'Ohne Jahr' ?></div>
            <?php $lastYear = $year; ?>
          <?php endif; ?>
          <details class="mk-event" data-mk-event="<?= mk_h($search) ?>">
            <summary>
              <span class="mk-date"><?= mk_h((string)($e->cDateText ?? '')) ?><small><?= mk_h($monthLabel) ?></small></span>
              <span><span class="mk-title"><?= mk_h((string)($e->cTitle ?? '')) ?></span><span class="mk-place"><?= mk_h((string)($e->cLocation ?? '')) ?><?= trim((string)($e->cStand ?? '')) !== '' ? ' · ' . mk_h((string)$e->cStand) : '' ?></span></span>
              <span class="mk-badge <?= empty($e->nActive) ? 'off' : '' ?>"><?= empty($e->nActive) ? 'Inaktiv' : 'Aktiv' ?></span>
            </summary>
            <form method="post" class="mk-form">
              <?= Form::getTokenInput(); ?>
              <input type="hidden" name="kID" value="<?= $id ?>">
              <div class="mk-field wide">
                <label>Titel</label>
                <input type="text" name="cTitle" required value="<?= mk_h((string)($e->cTitle ?? '')) ?>">
              </div>
              <div class="mk-field">
                <label>Beginn</label>
                <input type="date" name="dStart" required value="<?= mk_h(isset($e->dStart) && (string)$e->dStart !== '' ? (string)$e->dStart : MesseDatabase::eventStartIso((string)($e->cDateText ?? ''))) ?>">
              </div>
              <div class="mk-field">
                <label>Ende</label>
                <input type="date" name="dEnde" required value="<?= mk_h(isset($e->dEnde) && (string)$e->dEnde !== '' ? (string)$e->dEnde : MesseDatabase::eventEndIso((string)($e->cDateText ?? ''))) ?>">
              </div>
              <div class="mk-field">
                <label>Ort</label>
                <input type="text" name="cLocation" required value="<?= mk_h((string)($e->cLocation ?? '')) ?>">
              </div>
              <div class="mk-field wide">
                <label>Stand</label>
                <input type="text" name="cStand" value="<?= mk_h((string)($e->cStand ?? '')) ?>">
              </div>
              <div class="mk-field">
                <label>Website</label>
                <input type="url" name="cWebsiteUrl" value="<?= mk_h((string)($e->cWebsiteUrl ?? '')) ?>">
              </div>
              <div class="mk-field">
                <label>Route</label>
                <input type="url" name="cDirectionsUrl" value="<?= mk_h((string)($e->cDirectionsUrl ?? '')) ?>">
              </div>
              <div class="mk-field">
                <label>Sortierung</label>
                <input type="number" name="nSort" value="<?= (int)($e->nSort ?? 100) ?>">
              </div>
              <label class="mk-check"><input type="checkbox" name="nActive" value="1" <?= !empty($e->nActive) ? 'checked' : '' ?>> Aktiv</label>
              <div class="mk-actions">
                <button class="mk-btn mk-btn-save" type="submit" name="mkSaveEvent" value="1">Speichern</button>
                <button class="mk-btn mk-btn-del" type="submit" name="mkDeleteEvent" value="1" onclick="return confirm('Termin löschen?');">Löschen</button>
              </div>
            </form>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </details>

  <details class="mk-panel">
    <summary>Neuen Termin hinzufügen</summary>
    <form method="post" class="mk-form">
      <?= Form::getTokenInput(); ?>
      <div class="mk-field wide">
        <label>Titel</label>
        <input type="text" name="cTitle" required placeholder="CMT – Die Urlaubsmesse">
      </div>
      <div class="mk-field">
        <label>Beginn</label>
        <input type="date" name="dStart" required>
      </div>
      <div class="mk-field">
        <label>Ende</label>
        <input type="date" name="dEnde" required>
      </div>
      <div class="mk-field">
        <label>Ort</label>
        <input type="text" name="cLocation" required placeholder="Stuttgart">
      </div>
      <div class="mk-field wide">
        <label>Stand</label>
        <input type="text" name="cStand" placeholder="Halle 10 | Stand Nr: 10B61">
      </div>
      <div class="mk-field">
        <label>Website</label>
        <input type="url" name="cWebsiteUrl" placeholder="https://">
      </div>
      <div class="mk-field">
        <label>Route</label>
        <input type="url" name="cDirectionsUrl" placeholder="https://www.google.com/maps/dir/?api=1&destination=">
      </div>
      <div class="mk-field">
        <label>Sortierung</label>
        <input type="number" name="nSort" value="100">
      </div>
      <label class="mk-check"><input type="checkbox" name="nActiveEvent" value="1" checked> Aktiv</label>
      <div class="mk-actions">
        <button class="mk-btn mk-btn-save" type="submit" name="mkAddEvent" value="1">Termin hinzufügen</button>
      </div>
    </form>
  </details>

  <details class="mk-panel">
    <summary>Einstellungen</summary>
    <form method="post" class="mk-form">
      <?= Form::getTokenInput(); ?>
      <label class="mk-check wide"><input type="checkbox" name="nActive" value="1" <?= !empty($settings->nActive) ? 'checked' : '' ?>> Kalenderseite aktiv</label>
      <div class="mk-field">
        <label>URL-Slug</label>
        <input type="text" name="cPageSlug" value="<?= mk_h((string)($settings->cPageSlug ?? 'messe-kalender')) ?>">
      </div>
      <div class="mk-field">
        <label>Überschrift</label>
        <input type="text" name="cHeadline" value="<?= mk_h((string)($settings->cHeadline ?? 'Messe Kalender')) ?>">
      </div>
      <div class="mk-field wide">
        <label>Untertitel</label>
        <textarea name="cSubtitle" rows="2"><?= mk_h((string)($settings->cSubtitle ?? '')) ?></textarea>
      </div>
      <label class="mk-check wide"><input type="checkbox" name="nHomepageActive" value="1" <?= !empty($settings->nHomepageActive) ? 'checked' : '' ?>> Auf der Startseite anzeigen</label>
      <div class="mk-field">
        <label>CSS-Selektor</label>
        <input type="text" name="cHomepageInjectSelector" value="<?= mk_h((string)($settings->cHomepageInjectSelector ?? 'div.index-filter-konfigurator')) ?>">
      </div>
      <div class="mk-field">
        <label>Position</label>
        <select name="cHomepageInjectMode">
          <?php $hpMode = (string)($settings->cHomepageInjectMode ?? 'after'); ?>
          <?php foreach (['after' => 'Danach', 'before' => 'Davor', 'prepend' => 'Innen (Anfang)', 'append' => 'Innen (Ende)'] as $val => $lbl): ?>
            <option value="<?= mk_h($val) ?>" <?= $hpMode === $val ? 'selected' : '' ?>><?= mk_h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mk-field">
        <label>Anzahl auf der Startseite</label>
        <input type="number" name="nHomepageLimit" min="1" max="24" value="<?= (int)($settings->nHomepageLimit ?? 6) ?>">
      </div>
      <div class="mk-actions">
        <button class="mk-btn mk-btn-save" type="submit" name="mkSaveSettings" value="1">Einstellungen speichern</button>
      </div>
    </form>
  </details>
</div>
<script>
(function () {
  var input = document.getElementById('mkSearch');
  if (!input) return;
  input.addEventListener('input', function () {
    var query = input.value.toLowerCase();
    document.querySelectorAll('[data-mk-event]').forEach(function (row) {
      row.hidden = query !== '' && (row.getAttribute('data-mk-event') || '').indexOf(query) === -1;
    });
  });
})();
</script>
