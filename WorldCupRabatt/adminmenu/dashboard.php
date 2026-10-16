<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

if (!function_exists('wcr_h')) {
    function wcr_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wcr_admin_csrf_field')) {
    function wcr_admin_csrf_field(): string
    {
        if (\method_exists(Form::class, 'getTokenInput')) {
            return Form::getTokenInput();
        }

        return '';
    }
}

$defaults = [
    'title'       => 'Elfmeterschießen: Gewinne deinen Rabatt!',
    'intro'       => 'Triff einen der Rabatt-Kreise im Tor und sichere dir deinen Gutscheincode.',
    'buttonLabel' => 'Spiele um deinen Rabatt',
    'attempts'    => 3,
    'code5'       => 'BRE5',
    'code10'      => 'TOR10',
    'code15'      => 'CUP15',
];

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_worldcup_rabatt_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cTitle VARCHAR(190) NOT NULL DEFAULT "",
            cIntro VARCHAR(512) NOT NULL DEFAULT "",
            cButtonLabel VARCHAR(120) NOT NULL DEFAULT "",
            nAttempts INT NOT NULL DEFAULT 1,
            cCode5 VARCHAR(64) NOT NULL DEFAULT "",
            cCode10 VARCHAR(64) NOT NULL DEFAULT "",
            cCode15 VARCHAR(64) NOT NULL DEFAULT "",
            nAutoOpen TINYINT(1) NOT NULL DEFAULT 0,
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $row = $db->getSingleObject('SELECT kID FROM twomosi_worldcup_rabatt_settings LIMIT 1');
    if ($row === null) {
        $ins = new \stdClass();
        $ins->nActive = 1;
        $ins->cTitle = $defaults['title'];
        $ins->cIntro = $defaults['intro'];
        $ins->cButtonLabel = $defaults['buttonLabel'];
        $ins->nAttempts = $defaults['attempts'];
        $ins->cCode5 = $defaults['code5'];
        $ins->cCode10 = $defaults['code10'];
        $ins->cCode15 = $defaults['code15'];
        $ins->nAutoOpen = 0;
        $ins->dUpdated = \date('Y-m-d H:i:s');
        $db->insert('twomosi_worldcup_rabatt_settings', $ins);
    }
} catch (\Throwable $e) {
    // ignore
}

$nActive      = 1;
$cTitle       = $defaults['title'];
$cIntro       = $defaults['intro'];
$cButtonLabel = $defaults['buttonLabel'];
$nAttempts    = $defaults['attempts'];
$cCode5       = $defaults['code5'];
$cCode10      = $defaults['code10'];
$cCode15      = $defaults['code15'];
$nAutoOpen    = 0;

try {
    $row = $db->getSingleObject('SELECT * FROM twomosi_worldcup_rabatt_settings ORDER BY kID ASC LIMIT 1');
    if ($row !== null) {
        $nActive      = (int)($row->nActive ?? 1);
        $cTitle       = (string)($row->cTitle ?? $defaults['title']);
        $cIntro       = (string)($row->cIntro ?? $defaults['intro']);
        $cButtonLabel = (string)($row->cButtonLabel ?? $defaults['buttonLabel']);
        $nAttempts    = (int)($row->nAttempts ?? $defaults['attempts']);
        $cCode5       = (string)($row->cCode5 ?? $defaults['code5']);
        $cCode10      = (string)($row->cCode10 ?? $defaults['code10']);
        $cCode15      = (string)($row->cCode15 ?? $defaults['code15']);
        $nAutoOpen    = (int)($row->nAutoOpen ?? 0);
    }
} catch (\Throwable $e) {
    // ignore
}

if (Form::validateToken() && !empty($_POST['wcrSave'])) {
    $nActive      = !empty($_POST['nActive']) ? 1 : 0;
    $nAutoOpen    = !empty($_POST['nAutoOpen']) ? 1 : 0;
    $cTitle       = \trim((string)($_POST['cTitle'] ?? ''));
    $cIntro       = \trim((string)($_POST['cIntro'] ?? ''));
    $cButtonLabel = \trim((string)($_POST['cButtonLabel'] ?? ''));
    $nAttempts    = (int)($_POST['nAttempts'] ?? 3);
    $cCode5       = \trim((string)($_POST['cCode5'] ?? ''));
    $cCode10      = \trim((string)($_POST['cCode10'] ?? ''));
    $cCode15      = \trim((string)($_POST['cCode15'] ?? ''));

    if ($nAttempts < 1) {
        $nAttempts = 1;
    }
    if ($nAttempts > 10) {
        $nAttempts = 10;
    }

    if ($cTitle === '') {
        $message = 'Bitte einen Titel eingeben.';
    } elseif ($cCode5 === '' || $cCode10 === '' || $cCode15 === '') {
        $message = 'Bitte alle drei Gutscheincodes (5 %, 10 %, 15 %) eingeben.';
    } else {
        try {
            $exists = $db->getSingleObject('SELECT kID FROM twomosi_worldcup_rabatt_settings LIMIT 1');
            if ($exists === null) {
                $ins = new \stdClass();
                $ins->nActive = $nActive;
                $ins->cTitle = $cTitle;
                $ins->cIntro = $cIntro;
                $ins->cButtonLabel = $cButtonLabel;
                $ins->nAttempts = $nAttempts;
                $ins->cCode5 = $cCode5;
                $ins->cCode10 = $cCode10;
                $ins->cCode15 = $cCode15;
                $ins->nAutoOpen = $nAutoOpen;
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_worldcup_rabatt_settings', $ins);
            } else {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_worldcup_rabatt_settings
                        SET nActive = :ac, cTitle = :ti, cIntro = :in, cButtonLabel = :bl,
                            nAttempts = :at, cCode5 = :c5, cCode10 = :c10, cCode15 = :c15,
                            nAutoOpen = :ao, dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'ac'  => $nActive,
                        'ti'  => $cTitle,
                        'in'  => $cIntro,
                        'bl'  => $cButtonLabel,
                        'at'  => $nAttempts,
                        'c5'  => $cCode5,
                        'c10' => $cCode10,
                        'c15' => $cCode15,
                        'ao'  => $nAutoOpen,
                        'id'  => (int)($exists->kID ?? 0),
                    ]
                );
            }
            $message = 'Einstellungen gespeichert.';
        } catch (\Throwable $e) {
            $message = 'Fehler beim Speichern.';
        }
    }
}

?>
<div class="card">
    <div class="card-header">
        <h3 class="mb-0">World Cup Rabatt &ndash; Elfmeterschießen</h3>
    </div>
    <div class="card-body">
        <?php if ($message !== '') { ?>
            <div class="alert alert-info"><?= wcr_h($message) ?></div>
        <?php } ?>
        <div class="alert alert-success">
            <strong>Plugin-Modus aktiv:</strong> Das Spiel läuft über <code>templates/*</code> und wird auf der
            <strong>Startseite</strong> angezeigt. Die Sound-Dateien liegen in <code>Tasarim/</code>.
        </div>
        <p class="text-muted">
            Im Shop erscheint ein Button. Beim Klick öffnet sich ein Elfmeterschießen. Jeder Besucher hat
            <strong>3 Schüsse</strong>: Er klickt auf den Ball, der Schuss geht auf einen Rabatt-Kreis.
            Bei einem Tor wird der passende Gutscheincode freigeschaltet.
        </p>
        <form method="post" action="">
            <?= wcr_admin_csrf_field() ?>
            <input type="hidden" name="wcrSave" value="1">

            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="nActive" name="nActive" value="1"<?= $nActive === 1 ? ' checked' : '' ?>>
                <label class="custom-control-label" for="nActive">Spiel im Frontend anzeigen</label>
            </div>

            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="nAutoOpen" name="nAutoOpen" value="1"<?= $nAutoOpen === 1 ? ' checked' : '' ?>>
                <label class="custom-control-label" for="nAutoOpen">Spiel beim ersten Seitenbesuch automatisch öffnen</label>
            </div>

            <div class="form-group">
                <label for="cTitle"><strong>Titel</strong></label>
                <input type="text" class="form-control" id="cTitle" name="cTitle" maxlength="190" value="<?= wcr_h($cTitle) ?>" required>
            </div>

            <div class="form-group">
                <label for="cIntro"><strong>Beschreibung</strong></label>
                <textarea class="form-control" id="cIntro" name="cIntro" rows="2" maxlength="512"><?= wcr_h($cIntro) ?></textarea>
            </div>

            <div class="form-group">
                <label for="cButtonLabel"><strong>Button-Beschriftung</strong></label>
                <input type="text" class="form-control" id="cButtonLabel" name="cButtonLabel" maxlength="120" value="<?= wcr_h($cButtonLabel) ?>">
            </div>

            <input type="hidden" name="nAttempts" value="3">
            <div class="alert alert-secondary">
                Jeder Besucher hat <strong>3 Schüsse</strong>: Schuss 1 und 2 werden vom Torwart gehalten, Schuss 3 trifft garantiert
                (Zielwahl gewichtet zu 15&nbsp;%). Fortschritt wird im Browser gespeichert.
            </div>

            <hr>
            <h5>Gutscheincodes</h5>
            <p class="text-muted">Diese Codes müssen im Shop unter <em>Marketing &rarr; Kupons</em> als gültige Gutscheine angelegt sein.</p>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="cCode5"><strong>Code für 5&nbsp;%</strong></label>
                    <input type="text" class="form-control" id="cCode5" name="cCode5" maxlength="64" value="<?= wcr_h($cCode5) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="cCode10"><strong>Code für 10&nbsp;%</strong></label>
                    <input type="text" class="form-control" id="cCode10" name="cCode10" maxlength="64" value="<?= wcr_h($cCode10) ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="cCode15"><strong>Code für 15&nbsp;%</strong></label>
                    <input type="text" class="form-control" id="cCode15" name="cCode15" maxlength="64" value="<?= wcr_h($cCode15) ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>
