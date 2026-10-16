<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

if (!function_exists('rh_h')) {
    function rh_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rh_admin_csrf_field')) {
    function rh_admin_csrf_field(): string
    {
        if (\method_exists(Form::class, 'getTokenInput')) {
            return Form::getTokenInput();
        }

        return '';
    }
}

$defaultBanner = 'Jetzt 15 % Rabatt auf hochwertige Schonbezüge sichern. Jetzt bestellen und sparen.';
$defaultCode = 'sommer15';

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_rabatt_header_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cBannerText VARCHAR(512) NOT NULL DEFAULT "",
            cCouponCode VARCHAR(64) NOT NULL DEFAULT "",
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $row = $db->getSingleObject('SELECT kID FROM twomosi_rabatt_header_settings LIMIT 1');
    if ($row === null) {
        $ins = new \stdClass();
        $ins->nActive = 1;
        $ins->cBannerText = $defaultBanner;
        $ins->cCouponCode = $defaultCode;
        $ins->dUpdated = \date('Y-m-d H:i:s');
        $db->insert('twomosi_rabatt_header_settings', $ins);
    }
} catch (\Throwable $e) {
    // ignore
}

$nActive = 1;
$cBannerText = $defaultBanner;
$cCouponCode = $defaultCode;

try {
    $row = $db->getSingleObject('SELECT * FROM twomosi_rabatt_header_settings ORDER BY kID ASC LIMIT 1');
    if ($row !== null) {
        $nActive = (int)($row->nActive ?? 1);
        $cBannerText = (string)($row->cBannerText ?? $defaultBanner);
        $cCouponCode = (string)($row->cCouponCode ?? $defaultCode);
    }
} catch (\Throwable $e) {
    // ignore
}

if (Form::validateToken() && !empty($_POST['rhSave'])) {
    $nActive = !empty($_POST['nActive']) ? 1 : 0;
    $cBannerText = \trim((string)($_POST['cBannerText'] ?? ''));
    $cCouponCode = \trim((string)($_POST['cCouponCode'] ?? ''));

    if ($cBannerText === '') {
        $message = 'Bitte einen Header-Text eingeben.';
    } elseif ($cCouponCode === '') {
        $message = 'Bitte einen Kampagnen-/Gutscheincode eingeben.';
    } elseif (\strlen($cBannerText) > 512) {
        $message = 'Header-Text ist zu lang (max. 512 Zeichen).';
    } elseif (\strlen($cCouponCode) > 64) {
        $message = 'Gutscheincode ist zu lang (max. 64 Zeichen).';
    } else {
        try {
            $exists = $db->getSingleObject('SELECT kID FROM twomosi_rabatt_header_settings LIMIT 1');
            if ($exists === null) {
                $ins = new \stdClass();
                $ins->nActive = $nActive;
                $ins->cBannerText = $cBannerText;
                $ins->cCouponCode = $cCouponCode;
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_rabatt_header_settings', $ins);
            } else {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_rabatt_header_settings
                        SET nActive = :a, cBannerText = :t, cCouponCode = :c, dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'a'  => $nActive,
                        't'  => $cBannerText,
                        'c'  => $cCouponCode,
                        'id' => (int)($exists->kID ?? 0),
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
        <h3 class="mb-0">Rabatt Header</h3>
    </div>
    <div class="card-body">
        <?php if ($message !== '') { ?>
            <div class="alert alert-info"><?= rh_h($message) ?></div>
        <?php } ?>
        <p class="text-muted">
            Zeigt im Shop-Header einen Rabatt-Hinweis mit kopierbarem Gutscheincode.
            Besucher können auf den Code klicken, um ihn in die Zwischenablage zu kopieren.
        </p>
        <p class="text-muted mb-3">
            <strong>Nur Werbe-Traffic:</strong> Der Banner wird nur angezeigt, wenn Besucher über bezahlte Anzeigen
            kommen (z.&nbsp;B. Google Ads mit <code>gclid</code>/<code>gbraid</code>, Meta mit <code>fbclid</code>,
            UTM <code>utm_medium=cpc|pmax|paid</code> usw.). Ein Cookie merkt sich das 30 Tage für weitere Seitenaufrufe.
            Organische Suche und direkte Besucher sehen den Banner nicht.
        </p>
        <form method="post" action="">
            <?= rh_admin_csrf_field() ?>
            <input type="hidden" name="rhSave" value="1">
            <div class="form-group">
                <div class="custom-control custom-checkbox mb-3">
                    <input type="checkbox" class="custom-control-input" id="nActive" name="nActive" value="1"<?= $nActive === 1 ? ' checked' : '' ?>>
                    <label class="custom-control-label" for="nActive">Banner im Frontend anzeigen</label>
                </div>
            </div>
            <div class="form-group">
                <label for="cBannerText"><strong>Header-Text</strong></label>
                <textarea class="form-control" id="cBannerText" name="cBannerText" rows="3" maxlength="512" required><?= rh_h($cBannerText) ?></textarea>
                <small class="form-text text-muted">Wird oberhalb des Headers angezeigt. Prozentangaben (z.&nbsp;B. 15&nbsp;%) werden automatisch hervorgehoben.</small>
            </div>
            <div class="form-group">
                <label for="cCouponCode"><strong>Kampagnen- / Gutscheincode</strong></label>
                <input type="text" class="form-control" id="cCouponCode" name="cCouponCode" maxlength="64" value="<?= rh_h($cCouponCode) ?>" required>
                <small class="form-text text-muted">Klick auf den Code im Shop kopiert ihn (z.&nbsp;B. für den Warenkorb).</small>
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
        <hr>
        <h5>Vorschau (Text)</h5>
        <div style="padding:0.6rem 1rem;background:linear-gradient(90deg,#0d5c4a,#1a9d6e);color:#fff;border-radius:6px;">
            <?= \preg_replace('/(\d+\s*%)/u', '<strong style="color:#fef08a">$1</strong>', rh_h($cBannerText)) ?>
            <span style="margin-left:0.75rem;padding:0.2rem 0.6rem;border:1px dashed rgba(255,255,255,.5);border-radius:999px;font-family:monospace;"><?= rh_h($cCouponCode) ?></span>
        </div>
    </div>
</div>
