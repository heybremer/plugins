<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();

if (!function_exists('pd_h')) {
    function pd_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_prod_desc_artikel (
            kArtikel INT NOT NULL PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cImagesJson LONGTEXT NULL,
            cTabsJson LONGTEXT NULL,
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {
    // ignore
}

$message = '';
$editK = (int)($_GET['kArtikel'] ?? $_POST['kArtikel'] ?? 0);
$row = null;
if ($editK > 0) {
    try {
        $row = $db->getSingleObject(
            'SELECT * FROM twomosi_prod_desc_artikel WHERE kArtikel = :k LIMIT 1',
            ['k' => $editK]
        );
    } catch (\Throwable $e) {
        $row = null;
    }
}

if (Form::validateToken() && !empty($_POST['pdSave'])) {
    $kArtikel = (int)($_POST['kArtikel'] ?? 0);
    $nActive = !empty($_POST['nActive']) ? 1 : 0;
    $imgs = [];
    for ($i = 0; $i < 7; $i++) {
        $u = \trim((string)($_POST['img' . $i] ?? ''));
        if ($u !== '') {
            $imgs[] = $u;
        }
    }
    $tabs = [];
    for ($t = 0; $t < 6; $t++) {
        $title = \trim((string)($_POST['tab_title_' . $t] ?? ''));
        $slidesRaw = \trim((string)($_POST['tab_slides_' . $t] ?? ''));
        if ($title === '' || $slidesRaw === '') {
            continue;
        }
        $slides = [];
        foreach (\preg_split('/[\s,;]+/', $slidesRaw) ?: [] as $part) {
            $part = \trim((string)$part);
            if ($part === '') {
                continue;
            }
            $idx = (int)$part;
            if ($idx >= 0 && $idx < \count($imgs)) {
                $slides[] = $idx;
            }
        }
        $slides = \array_values(\array_unique($slides));
        if ($slides !== []) {
            $tabs[] = ['title' => $title, 'slides' => $slides];
        }
    }

    if ($kArtikel <= 0) {
        $message = 'Bitte gültige Artikel-ID (kArtikel) angeben.';
    } elseif (\count($imgs) < 1) {
        $message = 'Mindestens ein Bild (URL) erforderlich.';
    } elseif ($tabs === []) {
        $message = 'Mindestens ein Tab mit Titel und Bild-Indizes (z. B. 0,1) erforderlich.';
    } else {
        try {
            $exists = $db->getSingleObject(
                'SELECT kArtikel FROM twomosi_prod_desc_artikel WHERE kArtikel = :k LIMIT 1',
                ['k' => $kArtikel]
            );
            $imgJson = \json_encode($imgs, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            $tabJson = \json_encode($tabs, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            if ($exists === null) {
                $ins = new \stdClass();
                $ins->kArtikel = $kArtikel;
                $ins->nActive = $nActive;
                $ins->cImagesJson = $imgJson;
                $ins->cTabsJson = $tabJson;
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_prod_desc_artikel', $ins);
                $message = 'Gespeichert (neu).';
            } else {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_prod_desc_artikel
                        SET nActive = :a, cImagesJson = :ij, cTabsJson = :tj, dUpdated = NOW()
                      WHERE kArtikel = :k',
                    ['k' => $kArtikel, 'a' => $nActive, 'ij' => $imgJson, 'tj' => $tabJson]
                );
                $message = 'Gespeichert.';
            }
            $editK = $kArtikel;
            $row = $db->getSingleObject(
                'SELECT * FROM twomosi_prod_desc_artikel WHERE kArtikel = :k LIMIT 1',
                ['k' => $editK]
            );
        } catch (\Throwable $e) {
            $message = 'Fehler beim Speichern.';
        }
    }
}

$imgsVal = array_fill(0, 7, '');
if ($row !== null) {
    $dec = \json_decode((string)($row->cImagesJson ?? ''), true);
    if (\is_array($dec)) {
        foreach ($dec as $i => $u) {
            if ($i < 7) {
                $imgsVal[$i] = (string)$u;
            }
        }
    }
}
$tabTitles = array_fill(0, 6, '');
$tabSlides = array_fill(0, 6, '');
if ($row !== null) {
    $tdec = \json_decode((string)($row->cTabsJson ?? ''), true);
    if (\is_array($tdec)) {
        foreach ($tdec as $ti => $t) {
            if ($ti >= 6 || !\is_array($t)) {
                continue;
            }
            $tabTitles[$ti] = (string)($t['title'] ?? '');
            if (isset($t['slides']) && \is_array($t['slides'])) {
                $tabSlides[$ti] = \implode(',', \array_map('strval', $t['slides']));
            }
        }
    }
}

?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">ProdukteDescription — Tabs &amp; Slider</h3>
    </div>
    <div class="card-body">
        <?php if ($message !== ''): ?>
            <div class="alert alert-info"><?= pd_h($message) ?></div>
        <?php endif; ?>
        <p class="text-muted">
            Bis zu <strong>7 Bild-URLs</strong> (Index <strong>0–6</strong>). Pro Tab: <strong>Titel</strong> und welche Bilder im Slider erscheinen (Indizes, z.&nbsp;B. <code>0,1,2</code>).
            Slider: <strong>nur manuell</strong> (Pfeile/Punkte), kein Autoplay. Ausgabe auf der Artikelseite <strong>über</strong>
            <code>nav.nav-navitation</code> / <code>nav.nav-navigation</code>.
        </p>

        <form method="get" class="form-inline mb-3">
            <label class="mr-2">Artikel-ID laden</label>
            <input type="number" name="kArtikel" class="form-control mr-2" min="1" value="<?= $editK > 0 ? (int)$editK : '' ?>" required>
            <button type="submit" class="btn btn-outline-secondary">Laden</button>
        </form>

        <form method="post">
            <?= Form::getTokenInput() ?>
            <input type="hidden" name="pdSave" value="1">

            <div class="form-group">
                <label>kArtikel</label>
                <input type="number" name="kArtikel" class="form-control" required min="1"
                       value="<?= $editK > 0 ? (int)$editK : '' ?>">
            </div>
            <div class="form-group form-check">
                <input type="checkbox" name="nActive" value="1" class="form-check-input" id="pdActive"
                    <?= ($row === null || (int)($row->nActive ?? 1) === 1) ? ' checked' : '' ?>>
                <label class="form-check-label" for="pdActive">Aktiv (auf Artikeldetail anzeigen)</label>
            </div>

            <h4>Bilder (max. 7 URLs)</h4>
            <div class="row">
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <div class="col-md-6 mb-2">
                        <label class="small text-muted">Bild Index <?= $i ?></label>
                        <input type="text" name="img<?= $i ?>" class="form-control form-control-sm"
                               value="<?= pd_h($imgsVal[$i]) ?>" placeholder="https://… oder /bilder/…">
                    </div>
                <?php endfor; ?>
            </div>

            <h4 class="mt-3">Tabs (bis zu 6)</h4>
            <?php for ($t = 0; $t < 6; $t++): ?>
                <div class="border rounded p-2 mb-2">
                    <div class="form-group mb-1">
                        <label class="small">Tab <?= $t + 1 ?> — Titel</label>
                        <input type="text" name="tab_title_<?= $t ?>" class="form-control form-control-sm"
                               value="<?= pd_h($tabTitles[$t]) ?>" placeholder="z. B. Übersicht">
                    </div>
                    <div class="form-group mb-0">
                        <label class="small">Bild-Indizes (0–6, Komma getrennt)</label>
                        <input type="text" name="tab_slides_<?= $t ?>" class="form-control form-control-sm"
                               value="<?= pd_h($tabSlides[$t]) ?>" placeholder="0,1,2">
                    </div>
                </div>
            <?php endfor; ?>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>
