<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\Produktcard\ProduktcardDatabase;

require_once dirname(__DIR__) . '/src/ProduktcardDatabase.php';

$db = Shop::Container()->getDB();
ProduktcardDatabase::ensureAll();
$message = '';

$firstTabId = 1;
try {
    $ft = $db->getSingleObject('SELECT kID FROM twomosi_produktcard_tab ORDER BY nSort ASC, kID ASC LIMIT 1');
    if ($ft !== null) {
        $firstTabId = (int)($ft->kID ?? 1);
    }
} catch (\Throwable $e) {
    $firstTabId = 1;
}

/**
 * @return int|null tab kID if valid
 */
if (!function_exists('pc_resolve_tab_id')) {
    function pc_resolve_tab_id(\JTL\DB\DbInterface $db, int $candidate, int $fallback): ?int
    {
        if ($candidate <= 0) {
            return $fallback > 0 ? $fallback : null;
        }
        try {
            $ex = $db->getSingleObject(
                'SELECT kID FROM twomosi_produktcard_tab WHERE kID = :id LIMIT 1',
                ['id' => $candidate]
            );

            return $ex !== null ? $candidate : ($fallback > 0 ? $fallback : null);
        } catch (\Throwable $e) {
            return $fallback > 0 ? $fallback : null;
        }
    }
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['pcSaveSettings'])) {
            $hl = trim((string)($_POST['cHeadline'] ?? ''));
            $gc = (int)($_POST['nGridColumns'] ?? 4);
            $pad = (int)($_POST['nImagePadding'] ?? 8);
            $bgp = (int)($_POST['nImageBgSizePercent'] ?? 100);
            $ar = trim((string)($_POST['cAspectRatio'] ?? '1/1'));
            $allowedAr = ['1/1', '4/3', '3/4', '16/9', '3/2', '4/5'];
            if (!in_array($ar, $allowedAr, true)) {
                $ar = '1/1';
            }
            if ($hl === '') {
                $hl = 'Unsere Modelle';
            }
            if ($gc < 1) {
                $gc = 1;
            }
            if ($gc > 6) {
                $gc = 6;
            }
            if ($pad < 0) {
                $pad = 0;
            }
            if ($pad > 48) {
                $pad = 48;
            }
            if ($bgp < 50) {
                $bgp = 50;
            }
            if ($bgp > 100) {
                $bgp = 100;
            }
            $sRow = $db->getSingleObject('SELECT kID FROM twomosi_produktcard_settings ORDER BY kID ASC LIMIT 1');
            if ($sRow !== null) {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_produktcard_settings
                        SET cHeadline = :h,
                            nGridColumns = :g,
                            nImagePadding = :p,
                            nImageBgSizePercent = :b,
                            cAspectRatio = :a,
                            dUpdated = NOW()
                      WHERE kID = :id",
                    [
                        'id' => (int)$sRow->kID,
                        'h' => $hl,
                        'g' => $gc,
                        'p' => $pad,
                        'b' => $bgp,
                        'a' => $ar,
                    ]
                );
            } else {
                $rowS = new stdClass();
                $rowS->cHeadline = $hl;
                $rowS->nGridColumns = $gc;
                $rowS->nImagePadding = $pad;
                $rowS->nImageBgSizePercent = $bgp;
                $rowS->cAspectRatio = $ar;
                $rowS->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_produktcard_settings', $rowS);
            }
            $message = 'Darstellung gespeichert.';
        }

        if (!empty($_POST['pcTabAdd'])) {
            $lab = trim((string)($_POST['tabLabelNew'] ?? ''));
            $sort = (int)($_POST['tabSortNew'] ?? 100);
            if ($lab !== '') {
                $t = new stdClass();
                $t->cLabel = $lab;
                $t->nSort = $sort;
                $t->nActive = 1;
                $t->dCreated = date('Y-m-d H:i:s');
                $t->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_produktcard_tab', $t);
                $message = 'Tab hinzugefügt.';
            } else {
                $message = 'Tab-Bezeichnung fehlt.';
            }
        }

        if (!empty($_POST['pcTabSave']) && !empty($_POST['tabID'])) {
            $tabId = (int)$_POST['tabID'];
            $lab = trim((string)($_POST['tabLabel'] ?? ''));
            $allUrl = trim((string)($_POST['tabAllUrl'] ?? ''));
            $sort = (int)($_POST['tabSort'] ?? 100);
            $active = !empty($_POST['tabActive']) ? 1 : 0;
            if ($lab === '') {
                $message = 'Tab-Bezeichnung darf nicht leer sein.';
            } else {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_produktcard_tab
                        SET cLabel = :l, cAllUrl = :u, nSort = :s, nActive = :a, dUpdated = NOW()
                      WHERE kID = :id',
                    ['id' => $tabId, 'l' => $lab, 'u' => $allUrl, 's' => $sort, 'a' => $active]
                );
                $message = 'Tab gespeichert.';
            }
        }

        if (!empty($_POST['pcTabDelete']) && !empty($_POST['tabID'])) {
            $tabId = (int)$_POST['tabID'];
            $cnt = $db->getSingleObject(
                'SELECT COUNT(*) AS c FROM twomosi_produktcard WHERE kTabID = :id',
                ['id' => $tabId]
            );
            $n = $cnt !== null ? (int)($cnt->c ?? 0) : 0;
            if ($n > 0) {
                $message = 'Tab kann nicht gelöscht werden: Es sind noch Karten zugeordnet (' . $n . ').';
            } else {
                $db->executeQueryPrepared('DELETE FROM twomosi_produktcard_tab WHERE kID = :id', ['id' => $tabId]);
                $message = 'Tab gelöscht.';
            }
        }

        if (!empty($_POST['pcAdd'])) {
            $title = trim((string)($_POST['cTitle'] ?? ''));
            $bild  = trim((string)($_POST['cBildUrl'] ?? ''));
            $link  = trim((string)($_POST['cLinkUrl'] ?? ''));
            $sort  = (int)($_POST['nSort'] ?? 100);
            $tid   = pc_resolve_tab_id($db, (int)($_POST['kTabID'] ?? 0), $firstTabId);
            if ($bild !== '' && $link !== '' && $tid !== null) {
                $row = new stdClass();
                $row->kTabID = $tid;
                $row->cTitle = $title;
                $row->cBildUrl = $bild;
                $row->cLinkUrl = $link;
                $row->nSort = $sort;
                $row->nActive = 1;
                $row->dCreated = date('Y-m-d H:i:s');
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_produktcard', $row);
                $message = 'Eintrag hinzugefügt.';
            } else {
                $message = 'Bild-URL, Link und gültiger Tab sind erforderlich.';
            }
        }

        if (!empty($_POST['pcSave']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $title = trim((string)($_POST['cTitle'] ?? ''));
            $bild  = trim((string)($_POST['cBildUrl'] ?? ''));
            $link  = trim((string)($_POST['cLinkUrl'] ?? ''));
            $sort  = (int)($_POST['nSort'] ?? 100);
            $active = !empty($_POST['nActive']) ? 1 : 0;
            $tid = pc_resolve_tab_id($db, (int)($_POST['kTabID'] ?? 0), $firstTabId);
            if ($tid === null) {
                $message = 'Kein gültiger Tab.';
            } else {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_produktcard
                        SET kTabID = :tid,
                            cTitle = :t,
                            cBildUrl = :b,
                            cLinkUrl = :l,
                            nSort = :s,
                            nActive = :a,
                            dUpdated = NOW()
                      WHERE kID = :id",
                    [
                        'id' => $kID,
                        'tid' => $tid,
                        't' => $title,
                        'b' => $bild,
                        'l' => $link,
                        's' => $sort,
                        'a' => $active,
                    ]
                );
                $message = 'Gespeichert.';
            }
        }

        if (!empty($_POST['pcDelete']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $db->executeQueryPrepared(
                'DELETE FROM twomosi_produktcard WHERE kID = :id',
                ['id' => $kID]
            );
            $message = 'Gelöscht.';
        }
    } catch (\Throwable $e) {
        $message = 'Fehler: ' . $e->getMessage();
    }
}

$pcTabs = [];
try {
    $pcTabs = $db->getObjects(
        'SELECT kID, cLabel, cAllUrl, nSort, nActive, dUpdated
           FROM twomosi_produktcard_tab
       ORDER BY nSort ASC, kID ASC'
    ) ?: [];
} catch (\Throwable $e) {
    $pcTabs = [];
}

$rows = [];
try {
    $rows = $db->getObjects(
        'SELECT p.kID, p.kTabID, p.cTitle, p.cBildUrl, p.cLinkUrl, p.nSort, p.nActive, p.dUpdated,
                t.cLabel AS tabLabel
           FROM twomosi_produktcard p
           LEFT JOIN twomosi_produktcard_tab t ON t.kID = p.kTabID
       ORDER BY t.nSort ASC, t.kID ASC, p.nSort ASC, p.kID ASC'
    ) ?: [];
} catch (\Throwable $e) {
    $rows = [];
}

$pcSettings = (object)[
    'cHeadline' => 'Unsere Modelle',
    'nGridColumns' => 4,
    'nImagePadding' => 8,
    'nImageBgSizePercent' => 100,
    'cAspectRatio' => '1/1',
];
try {
    $sr = $db->getSingleObject('SELECT * FROM twomosi_produktcard_settings ORDER BY kID ASC LIMIT 1');
    if ($sr !== null) {
        $pcSettings = $sr;
    }
} catch (\Throwable $e) {
    // ignore
}

$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
?>
<style>
.pc-dash-wrap{max-width:1200px;padding:10px 0;}
.pc-dash-section{margin-bottom:24px;padding:16px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fff;}
.pc-dash-section h3{margin-top:0;}
.pc-tab-row{display:grid;grid-template-columns:1fr 100px 120px auto;gap:10px;align-items:end;padding:12px;border:1px solid rgba(0,0,0,.06);border-radius:10px;margin-bottom:10px;background:#fafafa;}
@media (max-width:900px){.pc-tab-row{grid-template-columns:1fr;align-items:stretch;}}
.pc-dash-card-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}
.pc-dash-entry{border:1px solid rgba(0,0,0,.08);border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.05);display:flex;flex-direction:column;}
.pc-dash-entry-thumb{aspect-ratio:16/10;background:#f3f4f6;overflow:hidden;}
.pc-dash-entry-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.pc-dash-entry-body{padding:12px 14px 14px;display:flex;flex-direction:column;gap:8px;flex:1;}
.pc-dash-entry-meta{font-size:12px;color:rgba(0,0,0,.5);}
.pc-dash-entry .pc-field label{font-size:12px;display:block;margin-bottom:2px;}
.pc-dash-entry input[type=text],.pc-dash-entry input[type=url],.pc-dash-entry input[type=number],.pc-dash-entry select{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid rgba(0,0,0,.12);border-radius:6px;}
.pc-dash-entry-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:4px;}
.pc-dash-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#e8f5ef;color:#197149;}
</style>
<div class="pc-dash-wrap">
    <h2 style="margin:0 0 16px;">Produktcard — Modell-Grid</h2>
    <?php if ($message !== ''): ?>
        <p style="margin:0 0 16px; font-weight:600;"><?= $h($message) ?></p>
    <?php endif; ?>

    <div class="pc-dash-section">
        <h3>Darstellung (Startseite)</h3>
        <p style="margin:0 0 12px; opacity:.85; max-width:720px;">Überschrift, Raster-Spalten (Desktop), gleichmäßiger Abstand um die Bildfläche, Bildgröße (Zoom) und Seitenverhältnis der Bildbox.</p>
        <form method="post" style="display:grid; gap:12px; max-width:720px;">
            <?= Form::getTokenInput(); ?>
            <div>
                <label>Überschrift (H2 / .t10-title)</label><br>
                <input type="text" name="cHeadline" value="<?= $h((string)($pcSettings->cHeadline ?? 'Unsere Modelle')) ?>" style="width:100%; max-width:560px; padding:8px 10px;">
            </div>
            <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
                <div>
                    <label>Spalten (Grid, ≥992px)</label><br>
                    <input type="number" name="nGridColumns" min="1" max="6" value="<?= (int)($pcSettings->nGridColumns ?? 4) ?>" style="width:100px; padding:8px 10px;">
                </div>
                <div>
                    <label>Bild-Innenabstand (px, gleich)</label><br>
                    <input type="number" name="nImagePadding" min="0" max="48" value="<?= (int)($pcSettings->nImagePadding ?? 8) ?>" style="width:100px; padding:8px 10px;">
                </div>
                <div>
                    <label>Bildgröße (%)</label><br>
                    <input type="number" name="nImageBgSizePercent" min="50" max="100" value="<?= (int)($pcSettings->nImageBgSizePercent ?? 100) ?>" style="width:100px; padding:8px 10px;" title="100 = cover (volle Fläche), 50–99 = kleiner, zentriert">
                </div>
                <div>
                    <label>Seitenverhältnis Bildfläche</label><br>
                    <?php $arCur = (string)($pcSettings->cAspectRatio ?? '1/1'); ?>
                    <select name="cAspectRatio" style="min-width:160px; padding:8px 10px;">
                        <?php foreach (['1/1' => '1 : 1', '4/3' => '4 : 3', '3/4' => '3 : 4', '16/9' => '16 : 9', '3/2' => '3 : 2', '4/5' => '4 : 5'] as $av => $al): ?>
                            <option value="<?= $h($av) ?>" <?= $arCur === $av ? 'selected' : '' ?>><?= $h($al) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <button type="submit" name="pcSaveSettings" value="1" style="padding:10px 18px; background:#197149; color:#fff; border:0; border-radius:8px; cursor:pointer;">Darstellung speichern</button>
            </div>
        </form>
    </div>

    <div class="pc-dash-section">
        <h3>Tabs (Startseite)</h3>
        <p style="margin:0 0 12px; opacity:.85; max-width:760px;">Jeder Tab erscheint nur, wenn er <strong>aktiv</strong> ist und mindestens eine <strong>aktive</strong> Karte mit Bild-URL und Link enthält. Mehrere Tabs: Reiter oben neben der Überschrift.</p>

        <?php foreach ($pcTabs as $tb): ?>
            <?php
            $tid = (int)($tb->kID ?? 0);
            $cardCount = 0;
            try {
                $cRow = $db->getSingleObject(
                    'SELECT COUNT(*) AS c FROM twomosi_produktcard WHERE kTabID = :id',
                    ['id' => $tid]
                );
                $cardCount = $cRow !== null ? (int)($cRow->c ?? 0) : 0;
            } catch (\Throwable $e) {
                $cardCount = 0;
            }
            ?>
            <form method="post" class="pc-tab-row">
                <?= Form::getTokenInput(); ?>
                <input type="hidden" name="tabID" value="<?= $tid ?>">
                <div>
                    <label style="font-size:12px;">Bezeichnung</label><br>
                    <input type="text" name="tabLabel" value="<?= $h((string)($tb->cLabel ?? '')) ?>" style="width:100%; padding:8px 10px;">
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="font-size:12px;">Alle Produkte Link (Button unten im Tab)</label><br>
                    <input type="url" name="tabAllUrl" value="<?= $h((string)($tb->cAllUrl ?? '')) ?>" style="width:100%; padding:8px 10px;" placeholder="https://...">
                </div>
                <div>
                    <label style="font-size:12px;">Sortierung</label><br>
                    <input type="number" name="tabSort" value="<?= (int)($tb->nSort ?? 100) ?>" style="width:100%; padding:8px 10px;">
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                        <input type="checkbox" name="tabActive" value="1" <?= !empty($tb->nActive) ? 'checked' : '' ?>> Aktiv
                    </label>
                    <span style="font-size:11px;opacity:.65;"><?= $cardCount ?> Karten</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <button type="submit" name="pcTabSave" value="1" style="padding:8px 14px;background:#197149;color:#fff;border:0;border-radius:8px;cursor:pointer;">Speichern</button>
                    <button type="submit" name="pcTabDelete" value="1" style="padding:8px 14px;background:#b00020;color:#fff;border:0;border-radius:8px;cursor:pointer;" onclick="return confirm('Tab wirklich löschen? Nur möglich ohne zugeordnete Karten.');">Löschen</button>
                </div>
            </form>
        <?php endforeach; ?>

        <h4 style="margin:20px 0 8px;">Neuer Tab</h4>
        <form method="post" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;max-width:640px;">
            <?= Form::getTokenInput(); ?>
            <div style="flex:1;min-width:200px;">
                <label>Bezeichnung</label><br>
                <input type="text" name="tabLabelNew" value="" style="width:100%;padding:8px 10px;" placeholder="z. B. Transporter">
            </div>
            <div>
                <label>Sortierung</label><br>
                <input type="number" name="tabSortNew" value="100" style="width:100px;padding:8px 10px;">
            </div>
            <button type="submit" name="pcTabAdd" value="1" style="padding:10px 18px;background:#197149;color:#fff;border:0;border-radius:8px;cursor:pointer;">Tab anlegen</button>
        </form>
    </div>

    <div class="pc-dash-section">
        <h3>Neuer Eintrag</h3>
        <form method="post" style="display:grid; gap:10px; max-width:720px;">
            <?= Form::getTokenInput(); ?>
            <div>
                <label>Tab</label><br>
                <select name="kTabID" style="width:100%;max-width:360px;padding:8px 10px;">
                    <?php foreach ($pcTabs as $tb): ?>
                        <option value="<?= (int)($tb->kID ?? 0) ?>" <?= (int)($tb->kID ?? 0) === $firstTabId ? 'selected' : '' ?>><?= $h((string)($tb->cLabel ?? 'Tab')) ?><?= empty($tb->nActive) ? ' (inaktiv)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Titel (optional, unter dem Bild)</label><br>
                <input type="text" name="cTitle" value="" style="width:100%; padding:8px 10px;" placeholder="z. B. Modellname">
            </div>
            <div>
                <label>Bild-URL <span style="color:#b00020;">*</span></label><br>
                <input type="url" name="cBildUrl" value="" style="width:100%; padding:8px 10px;" placeholder="https://..." required>
            </div>
            <div>
                <label>Link (Ziel-URL) <span style="color:#b00020;">*</span></label><br>
                <input type="url" name="cLinkUrl" value="" style="width:100%; padding:8px 10px;" placeholder="https://..." required>
            </div>
            <div>
                <label>Sortierung</label><br>
                <input type="number" name="nSort" value="100" style="width:120px; padding:8px 10px;">
            </div>
            <div>
                <button type="submit" name="pcAdd" value="1" style="padding:10px 18px; background:#197149; color:#fff; border:0; border-radius:8px; cursor:pointer;">Hinzufügen</button>
            </div>
        </form>
    </div>

    <div class="pc-dash-section">
        <h3 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">Einträge <span class="pc-dash-badge"><?= count($rows) ?></span></h3>
        <?php if (!count($rows)): ?>
            <p style="opacity:.75;">Noch keine Karten. Oben einen Eintrag anlegen.</p>
        <?php else: ?>
            <div class="pc-dash-card-grid">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $kid = (int)($r->kID ?? 0);
                    $bild = trim((string)($r->cBildUrl ?? ''));
                    $curTab = (int)($r->kTabID ?? $firstTabId);
                    ?>
                    <article class="pc-dash-entry">
                        <div class="pc-dash-entry-thumb">
                            <?php if ($bild !== ''): ?>
                                <img src="<?= $h($bild) ?>" alt="">
                            <?php else: ?>
                                <div style="display:grid;place-items:center;height:100%;font-size:12px;color:#999;">Kein Bild</div>
                            <?php endif; ?>
                        </div>
                        <div class="pc-dash-entry-body">
                            <div class="pc-dash-entry-meta">ID <?= $kid ?><?php if (!empty($r->tabLabel)): ?> · <?= $h((string)$r->tabLabel) ?><?php endif; ?></div>
                            <form method="post" style="display:flex;flex-direction:column;gap:8px;flex:1;">
                                <?= Form::getTokenInput(); ?>
                                <input type="hidden" name="kID" value="<?= $kid ?>">
                                <div class="pc-field">
                                    <label>Tab</label>
                                    <select name="kTabID">
                                        <?php foreach ($pcTabs as $tb): ?>
                                            <?php $optId = (int)($tb->kID ?? 0); ?>
                                            <option value="<?= $optId ?>" <?= $optId === $curTab ? 'selected' : '' ?>><?= $h((string)($tb->cLabel ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="pc-field">
                                    <label>Titel</label>
                                    <input type="text" name="cTitle" value="<?= $h((string)($r->cTitle ?? '')) ?>">
                                </div>
                                <div class="pc-field">
                                    <label>Bild-URL</label>
                                    <input type="url" name="cBildUrl" value="<?= $h((string)($r->cBildUrl ?? '')) ?>">
                                </div>
                                <div class="pc-field">
                                    <label>Link</label>
                                    <input type="url" name="cLinkUrl" value="<?= $h((string)($r->cLinkUrl ?? '')) ?>">
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                                    <div class="pc-field" style="flex:0 0 auto;">
                                        <label>Sortierung</label>
                                        <input type="number" name="nSort" value="<?= (int)($r->nSort ?? 100) ?>" style="width:100px;">
                                    </div>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin-top:16px;">
                                        <input type="checkbox" name="nActive" value="1" <?= !empty($r->nActive) ? 'checked' : '' ?>> Aktiv
                                    </label>
                                </div>
                                <div class="pc-dash-entry-actions">
                                    <button type="submit" name="pcSave" value="1" style="padding:8px 14px;background:#197149;color:#fff;border:0;border-radius:8px;cursor:pointer;">Speichern</button>
                                    <button type="submit" name="pcDelete" value="1" style="padding:8px 14px;background:#b00020;color:#fff;border:0;border-radius:8px;cursor:pointer;" onclick="return confirm('Eintrag löschen?');">Löschen</button>
                                </div>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:20px; font-size:13px; opacity:.8;">
        Das Raster erscheint auf der <strong>Startseite</strong>, sobald mindestens ein <strong>aktiver Tab</strong> mit gültigen Karten existiert.
        Reihenfolge: Tab-Sortierung, dann Karten-Sortierung. Unter 992px Breite werden automatisch 2 Spalten, unter 520px 1 Spalte — unabhängig von der Desktop-Spaltenanzahl.
        Auf schmalen Viewports: pro Tab zunächst vier Karten, weitere über „Weitere Produkte“.
    </p>
</div>
