<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

if (!function_exists('md_h')) {
    function md_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_menu_design_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cAccentColor VARCHAR(16) NOT NULL DEFAULT "#1565c0",
            cStyle VARCHAR(24) NOT NULL DEFAULT "modern",
            nMegaMenu TINYINT(1) NOT NULL DEFAULT 1,
            nMobileEnhance TINYINT(1) NOT NULL DEFAULT 1,
            nBorderRadius INT NOT NULL DEFAULT 10,
            cMenuSelector VARCHAR(255) NOT NULL DEFAULT "",
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $row = $db->getSingleObject('SELECT kID FROM twomosi_menu_design_settings LIMIT 1');
    if ($row === null) {
        $ins = new \stdClass();
        $ins->nActive = 1;
        $ins->cAccentColor = '#1565c0';
        $ins->cStyle = 'modern';
        $ins->nMegaMenu = 1;
        $ins->nMobileEnhance = 1;
        $ins->nBorderRadius = 10;
        $ins->cMenuSelector = '';
        $ins->dUpdated = \date('Y-m-d H:i:s');
        $db->insert('twomosi_menu_design_settings', $ins);
    }
} catch (\Throwable $e) {
    // ignore
}

$nActive = 1;
$cAccentColor = '#1565c0';
$cStyle = 'modern';
$nMegaMenu = 1;
$nMobileEnhance = 1;
$nBorderRadius = 10;
$cMenuSelector = '';

try {
    $row = $db->getSingleObject('SELECT * FROM twomosi_menu_design_settings ORDER BY kID ASC LIMIT 1');
    if ($row !== null) {
        $nActive = (int)($row->nActive ?? 1);
        $cAccentColor = (string)($row->cAccentColor ?? $cAccentColor);
        $cStyle = (string)($row->cStyle ?? $cStyle);
        $nMegaMenu = (int)($row->nMegaMenu ?? 1);
        $nMobileEnhance = (int)($row->nMobileEnhance ?? 1);
        $nBorderRadius = (int)($row->nBorderRadius ?? 10);
        $cMenuSelector = (string)($row->cMenuSelector ?? '');
    }
} catch (\Throwable $e) {
    // ignore
}

if (Form::validateToken() && !empty($_POST['mdSave'])) {
    $nActive = !empty($_POST['nActive']) ? 1 : 0;
    $cAccentColor = \trim((string)($_POST['cAccentColor'] ?? '#1565c0'));
    $cStyle = \trim((string)($_POST['cStyle'] ?? 'modern'));
    $nMegaMenu = !empty($_POST['nMegaMenu']) ? 1 : 0;
    $nMobileEnhance = !empty($_POST['nMobileEnhance']) ? 1 : 0;
    $nBorderRadius = (int)($_POST['nBorderRadius'] ?? 10);
    $cMenuSelector = \trim((string)($_POST['cMenuSelector'] ?? ''));

    if (!\preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $cAccentColor)) {
        $message = 'Akzentfarbe bitte als Hex (#RRGGBB) angeben.';
    } elseif (!\in_array($cStyle, ['modern', 'minimal', 'bold'], true)) {
        $message = 'Ungültiger Stil.';
    } elseif ($nBorderRadius < 0 || $nBorderRadius > 24) {
        $message = 'Eckenradius muss zwischen 0 und 24 liegen.';
    } else {
        try {
            $exists = $db->getSingleObject('SELECT kID FROM twomosi_menu_design_settings LIMIT 1');
            if ($exists === null) {
                $ins = new \stdClass();
                $ins->nActive = $nActive;
                $ins->cAccentColor = $cAccentColor;
                $ins->cStyle = $cStyle;
                $ins->nMegaMenu = $nMegaMenu;
                $ins->nMobileEnhance = $nMobileEnhance;
                $ins->nBorderRadius = $nBorderRadius;
                $ins->cMenuSelector = $cMenuSelector;
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_menu_design_settings', $ins);
            } else {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_menu_design_settings
                        SET nActive = :a, cAccentColor = :c, cStyle = :s, nMegaMenu = :m,
                            nMobileEnhance = :mob, nBorderRadius = :r, cMenuSelector = :sel,
                            dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'a'   => $nActive,
                        'c'   => $cAccentColor,
                        's'   => $cStyle,
                        'm'   => $nMegaMenu,
                        'mob' => $nMobileEnhance,
                        'r'   => $nBorderRadius,
                        'sel' => $cMenuSelector,
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
        <h3 class="mb-0">Menu Design</h3>
    </div>
    <div class="card-body">
        <?php if ($message !== '') { ?>
            <div class="alert alert-info"><?= md_h($message) ?></div>
        <?php } ?>
        <p class="text-muted">
            Verbessert das Kategorie- und Megamenü im NOVA-Template: klare Struktur, moderne Dropdowns,
            bessere Touch-Ziele auf Mobilgeräten und dezente Hover-Effekte am Desktop.
        </p>
        <form method="post" action="">
            <?= Form::getTokenInput() ?>
            <input type="hidden" name="mdSave" value="1">
            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="nActive" name="nActive" value="1"<?= $nActive === 1 ? ' checked' : '' ?>>
                <label class="custom-control-label" for="nActive">Menü-Design im Frontend aktiv</label>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="cAccentColor"><strong>Akzentfarbe</strong></label>
                    <input type="color" class="form-control" id="cAccentColorPicker" value="<?= md_h($cAccentColor) ?>" style="height:38px;padding:2px;">
                    <input type="text" class="form-control mt-1" name="cAccentColor" id="cAccentColor" value="<?= md_h($cAccentColor) ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7">
                </div>
                <div class="form-group col-md-4">
                    <label for="cStyle"><strong>Stil</strong></label>
                    <select class="form-control" name="cStyle" id="cStyle">
                        <option value="modern"<?= $cStyle === 'modern' ? ' selected' : '' ?>>Modern (Standard)</option>
                        <option value="minimal"<?= $cStyle === 'minimal' ? ' selected' : '' ?>>Minimal</option>
                        <option value="bold"<?= $cStyle === 'bold' ? ' selected' : '' ?>>Bold</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="nBorderRadius"><strong>Eckenradius (px)</strong></label>
                    <input type="number" class="form-control" name="nBorderRadius" id="nBorderRadius" min="0" max="24" value="<?= (int)$nBorderRadius ?>">
                </div>
            </div>
            <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input" id="nMegaMenu" name="nMegaMenu" value="1"<?= $nMegaMenu === 1 ? ' checked' : '' ?>>
                <label class="custom-control-label" for="nMegaMenu">Megamenü: mehrspaltige Unterkategorien (ab 6 Einträgen)</label>
            </div>
            <div class="custom-control custom-checkbox mb-3">
                <input type="checkbox" class="custom-control-input" id="nMobileEnhance" name="nMobileEnhance" value="1"<?= $nMobileEnhance === 1 ? ' checked' : '' ?>>
                <label class="custom-control-label" for="nMobileEnhance">Mobile: aufklappbare Untermenüs &amp; große Klickflächen</label>
            </div>
            <div class="form-group">
                <label for="cMenuSelector"><strong>CSS-Selektor (optional)</strong></label>
                <input type="text" class="form-control" name="cMenuSelector" id="cMenuSelector" value="<?= md_h($cMenuSelector) ?>" placeholder="#mainNavigation">
                <small class="form-text text-muted">Leer lassen für automatische Erkennung (NOVA: #mainNavigation, #jtl-nav-wrapper …).</small>
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </form>
        <hr>
        <h5>Vorschau-Farben</h5>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <span style="padding:0.5rem 1rem;border-radius:<?= (int)$nBorderRadius ?>px;background:<?= md_h($cAccentColor) ?>;color:#fff;">Akzent</span>
            <span style="padding:0.5rem 1rem;border-radius:<?= (int)$nBorderRadius ?>px;border:1px solid #e2e8f0;">Stil: <?= md_h($cStyle) ?></span>
        </div>
    </div>
</div>
<script>
(function () {
    var picker = document.getElementById('cAccentColorPicker');
    var text = document.getElementById('cAccentColor');
    if (picker && text) {
        picker.addEventListener('input', function () { text.value = picker.value; });
        text.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) { picker.value = text.value; }
        });
    }
})();
</script>
