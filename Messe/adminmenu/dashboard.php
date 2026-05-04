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

if (Form::validateToken()) {
    try {
        if (!empty($_POST['mkSaveSettings'])) {
            $active = !empty($_POST['nActive']) ? 1 : 0;
            $slug = mk_slugify((string)($_POST['cPageSlug'] ?? 'messe-kalender'));
            $headline = trim((string)($_POST['cHeadline'] ?? 'Messe Kalender 2026'));
            $subtitle = trim((string)($_POST['cSubtitle'] ?? ''));
            if ($headline === '') {
                $headline = 'Messe Kalender 2026';
            }

            $row = $db->getSingleObject('SELECT kID FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            if ($row !== null) {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_messe_settings
                        SET nActive = :a,
                            cPageSlug = :s,
                            cHeadline = :h,
                            cSubtitle = :sub,
                            dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'id' => (int)$row->kID,
                        'a' => $active,
                        's' => $slug,
                        'h' => $headline,
                        'sub' => $subtitle,
                    ]
                );
            } else {
                $ins = new stdClass();
                $ins->nActive = $active;
                $ins->cPageSlug = $slug;
                $ins->cHeadline = $headline;
                $ins->cSubtitle = $subtitle;
                $ins->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_messe_settings', $ins);
            }
            $message = 'Einstellungen gespeichert.';
        }

        if (!empty($_POST['mkAddEvent'])) {
            $month = trim((string)($_POST['cMonth'] ?? ''));
            $title = trim((string)($_POST['cTitle'] ?? ''));
            $location = trim((string)($_POST['cLocation'] ?? ''));
            $dateText = trim((string)($_POST['cDateText'] ?? ''));
            $stand = trim((string)($_POST['cStand'] ?? ''));
            $website = trim((string)($_POST['cWebsiteUrl'] ?? ''));
            $directions = trim((string)($_POST['cDirectionsUrl'] ?? ''));
            $sort = (int)($_POST['nSort'] ?? 100);
            $active = !empty($_POST['nActiveEvent']) ? 1 : 0;

            if ($month !== '' && $title !== '' && $location !== '' && $dateText !== '') {
                $e = new stdClass();
                $e->cMonth = $month;
                $e->cTitle = $title;
                $e->cLocation = $location;
                $e->cDateText = $dateText;
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
                $message = 'Monat, Titel, Ort und Termin sind erforderlich.';
            }
        }

        if (!empty($_POST['mkSaveEvent']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $month = trim((string)($_POST['cMonth'] ?? ''));
            $title = trim((string)($_POST['cTitle'] ?? ''));
            $location = trim((string)($_POST['cLocation'] ?? ''));
            $dateText = trim((string)($_POST['cDateText'] ?? ''));
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
                    'st' => $stand,
                    'w' => $website,
                    'd' => $directions,
                    's' => $sort,
                    'a' => $active,
                ]
            );
            $message = 'Event gespeichert.';
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
    'cHeadline' => 'Messe Kalender 2026',
    'cSubtitle' => 'Die Messen, an denen wir als Bremer-Sitzbezüge GmbH teilnehmen werden',
];
try {
    $sr = $db->getSingleObject('SELECT * FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
    if ($sr !== null) {
        $settings = $sr;
    }
} catch (\Throwable $e) {
}

$events = [];
try {
    $events = $db->getObjects(
        'SELECT kID, cMonth, cTitle, cLocation, cDateText, cStand, cWebsiteUrl, cDirectionsUrl, nSort, nActive, dUpdated
           FROM twomosi_messe_event
       ORDER BY nSort ASC, kID ASC'
    ) ?: [];
} catch (\Throwable $e) {
    $events = [];
}

$months = [
    'januar' => 'Januar',
    'februar' => 'Februar',
    'märz' => 'März',
    'august' => 'August',
    'september' => 'September',
    'oktober' => 'Oktober',
    'november' => 'November',
];
?>
<style>
.mk-wrap{max-width:1200px;padding:10px 0;}
.mk-section{margin-bottom:24px;padding:16px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fff;}
.mk-section h3{margin-top:0;}
.mk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;}
.mk-card{border:1px solid rgba(0,0,0,.08);border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;flex-direction:column;}
.mk-card-head{padding:12px 14px;background:linear-gradient(90deg,#0b3e27,#197149);color:#fff;}
.mk-card-head .mk-title{font-weight:800;}
.mk-card-head .mk-meta{font-size:12px;opacity:.9;}
.mk-card-body{padding:12px 14px;display:flex;flex-direction:column;gap:8px;flex:1;}
.mk-field label{font-size:12px;display:block;margin-bottom:2px;}
.mk-card input[type=text],.mk-card input[type=url],.mk-card input[type=number],.mk-card select, .mk-section input[type=text],.mk-section textarea{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid rgba(0,0,0,.12);border-radius:6px;}
.mk-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:6px;}
.mk-btn{padding:8px 14px;border:0;border-radius:8px;cursor:pointer;}
.mk-btn-save{background:#197149;color:#fff;}
.mk-btn-del{background:#b00020;color:#fff;}
.mk-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;background:#e8f5ef;color:#197149;}
</style>

<div class="mk-wrap">
  <h2 style="margin:0 0 16px;">Messe Kalender</h2>
  <?php if ($message !== ''): ?>
    <p style="margin:0 0 16px; font-weight:600;"><?= mk_h($message) ?></p>
  <?php endif; ?>

  <div class="mk-section">
    <h3>Seiten-Einstellungen</h3>
    <p style="margin:0 0 12px;opacity:.85;max-width:760px;">Diese Einstellungen steuern Titel/Untertitel, ob die Seite aktiv ist und unter welchem Slug sie erreichbar ist.</p>
    <form method="post" style="display:grid;gap:12px;max-width:760px;">
      <?= Form::getTokenInput(); ?>
      <label style="display:flex;gap:8px;align-items:center;">
        <input type="checkbox" name="nActive" value="1" <?= !empty($settings->nActive) ? 'checked' : '' ?>> Seite aktiv
      </label>
      <div class="mk-field">
        <label>Seiten-Slug (URL)</label>
        <input type="text" name="cPageSlug" value="<?= mk_h((string)($settings->cPageSlug ?? 'messe-kalender')) ?>" placeholder="messe-kalender">
        <div style="font-size:12px;opacity:.7;margin-top:6px;">Beispiel: <code>/<?= mk_h((string)($settings->cPageSlug ?? 'messe-kalender')) ?></code></div>
      </div>
      <div class="mk-field">
        <label>Überschrift</label>
        <input type="text" name="cHeadline" value="<?= mk_h((string)($settings->cHeadline ?? 'Messe Kalender 2026')) ?>">
      </div>
      <div class="mk-field">
        <label>Untertitel</label>
        <textarea name="cSubtitle" rows="2" style="padding:8px 10px;"><?= mk_h((string)($settings->cSubtitle ?? '')) ?></textarea>
      </div>
      <div>
        <button class="mk-btn mk-btn-save" type="submit" name="mkSaveSettings" value="1">Einstellungen speichern</button>
      </div>
    </form>
  </div>

  <div class="mk-section">
    <h3>Neues Event</h3>
    <form method="post" style="display:grid;gap:10px;max-width:760px;">
      <?= Form::getTokenInput(); ?>
      <div class="mk-field">
        <label>Monat</label>
        <select name="cMonth" required>
          <?php foreach ($months as $k => $lbl): ?>
            <option value="<?= mk_h($k) ?>"><?= mk_h($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mk-field">
        <label>Titel</label>
        <input type="text" name="cTitle" required placeholder="z. B. CMT – Die Urlaubsmesse">
      </div>
      <div class="mk-field">
        <label>Ort</label>
        <input type="text" name="cLocation" required placeholder="z. B. Stuttgart">
      </div>
      <div class="mk-field">
        <label>Termin (Text)</label>
        <input type="text" name="cDateText" required placeholder="z. B. 17.–25.01.2026">
      </div>
      <div class="mk-field">
        <label>Stand (optional)</label>
        <input type="text" name="cStand" placeholder="z. B. Halle 10 | Stand Nr: 10B61">
      </div>
      <div class="mk-field">
        <label>Website URL (optional)</label>
        <input type="url" name="cWebsiteUrl" placeholder="https://...">
      </div>
      <div class="mk-field">
        <label>Directions URL (optional)</label>
        <input type="url" name="cDirectionsUrl" placeholder="https://www.google.com/maps/dir/?api=1&destination=...">
      </div>
      <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
        <div class="mk-field" style="flex:0 0 auto;">
          <label>Sortierung</label>
          <input type="number" name="nSort" value="100" style="width:120px;">
        </div>
        <label style="display:flex;gap:8px;align-items:center;margin-top:18px;">
          <input type="checkbox" name="nActiveEvent" value="1" checked> Aktiv
        </label>
      </div>
      <div>
        <button class="mk-btn mk-btn-save" type="submit" name="mkAddEvent" value="1">Event hinzufügen</button>
      </div>
    </form>
  </div>

  <div class="mk-section">
    <h3 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">Events <span class="mk-badge"><?= count($events) ?></span></h3>
    <?php if (!count($events)): ?>
      <p style="opacity:.75;">Noch keine Events angelegt.</p>
    <?php else: ?>
      <div class="mk-grid">
        <?php foreach ($events as $e): ?>
          <?php $id = (int)($e->kID ?? 0); ?>
          <article class="mk-card">
            <div class="mk-card-head">
              <div class="mk-title"><?= mk_h((string)($e->cTitle ?? '')) ?></div>
              <div class="mk-meta">ID <?= $id ?> · <?= mk_h((string)($months[(string)($e->cMonth ?? '')] ?? (string)($e->cMonth ?? ''))) ?></div>
            </div>
            <div class="mk-card-body">
              <form method="post" style="display:flex;flex-direction:column;gap:8px;flex:1;">
                <?= Form::getTokenInput(); ?>
                <input type="hidden" name="kID" value="<?= $id ?>">
                <div class="mk-field">
                  <label>Monat</label>
                  <select name="cMonth">
                    <?php foreach ($months as $k => $lbl): ?>
                      <option value="<?= mk_h($k) ?>" <?= (string)($e->cMonth ?? '') === $k ? 'selected' : '' ?>><?= mk_h($lbl) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="mk-field">
                  <label>Ort</label>
                  <input type="text" name="cLocation" value="<?= mk_h((string)($e->cLocation ?? '')) ?>">
                </div>
                <div class="mk-field">
                  <label>Termin (Text)</label>
                  <input type="text" name="cDateText" value="<?= mk_h((string)($e->cDateText ?? '')) ?>">
                </div>
                <div class="mk-field">
                  <label>Stand</label>
                  <input type="text" name="cStand" value="<?= mk_h((string)($e->cStand ?? '')) ?>">
                </div>
                <div class="mk-field">
                  <label>Website URL</label>
                  <input type="url" name="cWebsiteUrl" value="<?= mk_h((string)($e->cWebsiteUrl ?? '')) ?>">
                </div>
                <div class="mk-field">
                  <label>Directions URL</label>
                  <input type="url" name="cDirectionsUrl" value="<?= mk_h((string)($e->cDirectionsUrl ?? '')) ?>">
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                  <div class="mk-field" style="flex:0 0 auto;">
                    <label>Sortierung</label>
                    <input type="number" name="nSort" value="<?= (int)($e->nSort ?? 100) ?>" style="width:110px;">
                  </div>
                  <label style="display:flex;gap:8px;align-items:center;margin-top:18px;">
                    <input type="checkbox" name="nActive" value="1" <?= !empty($e->nActive) ? 'checked' : '' ?>> Aktiv
                  </label>
                </div>
                <div class="mk-actions">
                  <button class="mk-btn mk-btn-save" type="submit" name="mkSaveEvent" value="1">Speichern</button>
                  <button class="mk-btn mk-btn-del" type="submit" name="mkDeleteEvent" value="1" onclick="return confirm('Event löschen?');">Löschen</button>
                </div>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <p style="margin-top:20px;font-size:13px;opacity:.8;">
    Seite aufrufbar unter: <strong>/<?= mk_h(mk_slugify((string)($settings->cPageSlug ?? 'messe-kalender'))) ?></strong>
    (nach Änderung ggf. Cache leeren). Filter-Buttons im Frontend sind aktuell auf die Standard-Monate ausgelegt.
  </p>
</div>

