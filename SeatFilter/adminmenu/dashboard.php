<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

if (!function_exists('sf_h')) {
    function sf_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sf_csrf')) {
    function sf_csrf(): string
    {
        if (\method_exists(Form::class, 'getTokenInput')) {
            return Form::getTokenInput();
        }

        return '';
    }
}

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_seat_filter_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cTargetPage VARCHAR(32) NOT NULL DEFAULT "all",
            cInjectSelector VARCHAR(128) NOT NULL DEFAULT "#content",
            cInjectMethod VARCHAR(16) NOT NULL DEFAULT "prepend",
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $row = $db->getSingleObject('SELECT kID FROM twomosi_seat_filter_settings LIMIT 1');
    if ($row === null) {
        $ins = new \stdClass();
        $ins->nActive         = 1;
        $ins->cTargetPage     = 'all';
        $ins->cInjectSelector = '#content';
        $ins->cInjectMethod   = 'prepend';
        $ins->dUpdated        = \date('Y-m-d H:i:s');
        $db->insert('twomosi_seat_filter_settings', $ins);
    }
} catch (\Throwable $e) {
    // ignore
}

$nActive         = 1;
$cTargetPage     = 'all';
$cInjectSelector = '#content';
$cInjectMethod   = 'prepend';

try {
    $row = $db->getSingleObject('SELECT * FROM twomosi_seat_filter_settings ORDER BY kID ASC LIMIT 1');
    if ($row !== null) {
        $nActive         = (int)($row->nActive ?? 1);
        $cTargetPage     = (string)($row->cTargetPage ?? 'all');
        $cInjectSelector = (string)($row->cInjectSelector ?? '#content');
        $cInjectMethod   = (string)($row->cInjectMethod ?? 'prepend');
    }
} catch (\Throwable $e) {
    // ignore
}

if (Form::validateToken() && !empty($_POST['sfSave'])) {
    $nActive         = !empty($_POST['nActive']) ? 1 : 0;
    $cTargetPage     = \trim((string)($_POST['cTargetPage'] ?? 'all'));
    $cInjectSelector = \trim((string)($_POST['cInjectSelector'] ?? '#content'));
    $cInjectMethod   = \trim((string)($_POST['cInjectMethod'] ?? 'prepend'));

    $allowed = ['all', 'home', 'list', 'article'];
    if (!\in_array($cTargetPage, $allowed, true)) {
        $cTargetPage = 'all';
    }
    $allowedMethods = ['prepend', 'append', 'before', 'after'];
    if (!\in_array($cInjectMethod, $allowedMethods, true)) {
        $cInjectMethod = 'prepend';
    }
    if ($cInjectSelector === '') {
        $cInjectSelector = '#content';
    }

    try {
        $exists = $db->getSingleObject('SELECT kID FROM twomosi_seat_filter_settings LIMIT 1');
        if ($exists === null) {
            $ins = new \stdClass();
            $ins->nActive         = $nActive;
            $ins->cTargetPage     = $cTargetPage;
            $ins->cInjectSelector = $cInjectSelector;
            $ins->cInjectMethod   = $cInjectMethod;
            $ins->dUpdated        = \date('Y-m-d H:i:s');
            $db->insert('twomosi_seat_filter_settings', $ins);
        } else {
            $db->executeQueryPrepared(
                'UPDATE twomosi_seat_filter_settings
                    SET nActive = :a, cTargetPage = :tp, cInjectSelector = :is, cInjectMethod = :im, dUpdated = NOW()
                  WHERE kID = :id',
                [
                    'a'  => $nActive,
                    'tp' => $cTargetPage,
                    'is' => $cInjectSelector,
                    'im' => $cInjectMethod,
                    'id' => (int)($exists->kID ?? 0),
                ]
            );
        }
        $message = 'Einstellungen gespeichert.';
    } catch (\Throwable $e) {
        $message = 'Fehler beim Speichern: ' . $e->getMessage();
    }
}

$sitzCount  = 0;
$serieCount = 0;
try {
    $r = $db->getSingleObject(
        "SELECT COUNT(DISTINCT tmws.cWert) AS cnt
           FROM tmerkmal m
           INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal AND ms.kSprache = 1 AND ms.cName = 'kompatible Sitze'
           INNER JOIN tmerkmalwert mw ON mw.kMerkmal = m.kMerkmal
           INNER JOIN tmerkmalwertsprache tmws ON tmws.kMerkmalWert = mw.kMerkmalWert AND tmws.kSprache = 1
           WHERE tmws.cWert <> ''"
    );
    $sitzCount = (int)($r->cnt ?? 0);
} catch (\Throwable $e) { /* ignore */ }

try {
    $r = $db->getSingleObject(
        "SELECT COUNT(DISTINCT tmws.cWert) AS cnt
           FROM tmerkmal m
           INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal AND ms.kSprache = 1 AND ms.cName = 'Serie'
           INNER JOIN tmerkmalwert mw ON mw.kMerkmal = m.kMerkmal
           INNER JOIN tmerkmalwertsprache tmws ON tmws.kMerkmalWert = mw.kMerkmalWert AND tmws.kSprache = 1
           WHERE tmws.cWert <> ''"
    );
    $serieCount = (int)($r->cnt ?? 0);
} catch (\Throwable $e) { /* ignore */ }

?>
<div class="card">
    <div class="card-header">
        <h3 class="mb-0">Seat Filter — Sitz &rarr; Serie &rarr; Artikel</h3>
    </div>
    <div class="card-body">
        <?php if ($message !== '') { ?>
            <div class="alert alert-info"><?= sf_h($message) ?></div>
        <?php } ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= $sitzCount ?></h5>
                        <p class="card-text text-muted">Sitz-Werte (kompatible Sitze)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= $serieCount ?></h5>
                        <p class="card-text text-muted">Serien-Werte (Serie)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?= $nActive ? '<span class="badge badge-success">Aktiv</span>' : '<span class="badge badge-secondary">Inaktiv</span>' ?></h5>
                        <p class="card-text text-muted">Plugin-Status</p>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-muted mb-3">
            Zeigt eine 3-stufige Filteroberfläche: Besucher wählen erst den Sitz, dann die Serie,
            und sehen anschließend die passenden Produkte direkt als Karten.
        </p>

        <form method="post" action="">
            <?= sf_csrf() ?>
            <input type="hidden" name="sfSave" value="1">

            <div class="form-group">
                <div class="custom-control custom-checkbox mb-3">
                    <input type="checkbox" class="custom-control-input" id="nActive" name="nActive" value="1"<?= $nActive === 1 ? ' checked' : '' ?>>
                    <label class="custom-control-label" for="nActive">Filter im Frontend anzeigen</label>
                </div>
            </div>

            <div class="form-group">
                <label for="cTargetPage"><strong>Zielseite</strong></label>
                <select class="form-control" id="cTargetPage" name="cTargetPage">
                    <option value="all"<?= $cTargetPage === 'all' ? ' selected' : '' ?>>Alle Seiten</option>
                    <option value="home"<?= $cTargetPage === 'home' ? ' selected' : '' ?>>Nur Startseite</option>
                    <option value="list"<?= $cTargetPage === 'list' ? ' selected' : '' ?>>Nur Artikelliste</option>
                    <option value="article"<?= $cTargetPage === 'article' ? ' selected' : '' ?>>Nur Artikelseite</option>
                </select>
                <small class="form-text text-muted">Auf welcher Seitenart soll der Filter erscheinen?</small>
            </div>

            <div class="form-group">
                <label for="cInjectSelector"><strong>CSS-Selektor (Ziel-Element)</strong></label>
                <input type="text" class="form-control" id="cInjectSelector" name="cInjectSelector"
                       value="<?= sf_h($cInjectSelector) ?>" placeholder="#content">
                <small class="form-text text-muted">CSS-Selektor des Elements, in das der Filter eingefügt wird (z.&nbsp;B. <code>#content</code>, <code>.main-wrapper</code>).</small>
            </div>

            <div class="form-group">
                <label for="cInjectMethod"><strong>Einfüge-Methode</strong></label>
                <select class="form-control" id="cInjectMethod" name="cInjectMethod">
                    <option value="prepend"<?= $cInjectMethod === 'prepend' ? ' selected' : '' ?>>prepend (am Anfang)</option>
                    <option value="append"<?= $cInjectMethod === 'append' ? ' selected' : '' ?>>append (am Ende)</option>
                    <option value="before"<?= $cInjectMethod === 'before' ? ' selected' : '' ?>>before (vor dem Element)</option>
                    <option value="after"<?= $cInjectMethod === 'after' ? ' selected' : '' ?>>after (nach dem Element)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>

        <hr>
        <h5>API-Endpunkte</h5>
        <table class="table table-sm table-bordered">
            <thead><tr><th>URL</th><th>Beschreibung</th></tr></thead>
            <tbody>
                <tr><td><code>/sf-search?mode=sitz</code></td><td>Alle Sitz-Werte</td></tr>
                <tr><td><code>/sf-search?mode=serie&amp;sitz=5</code></td><td>Serien für Sitz&nbsp;5</td></tr>
                <tr><td><code>/sf-search?mode=artikel&amp;sitz=5&amp;serie=ZM</code></td><td>Produkte für Sitz&nbsp;5 + Serie&nbsp;ZM</td></tr>
            </tbody>
        </table>
    </div>
</div>
