<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_homepage_injector_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nEnabled TINYINT(1) NOT NULL DEFAULT 1,
            cTargetSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
            cTargetMode VARCHAR(16) NOT NULL DEFAULT 'after',
            cOrderJson LONGTEXT NULL,
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $row = $db->getSingleObject("SELECT kID FROM twomosi_homepage_injector_settings LIMIT 1");
    if ($row === null) {
        $ins = new stdClass();
        $ins->nEnabled = 1;
        $ins->cTargetSelector = 'div.index-filter-konfigurator';
        $ins->cTargetMode = 'after';
        $ins->cOrderJson = json_encode(['CardProduct', 'MarkeFiltre', 'Top10List'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ins->dUpdated = date('Y-m-d H:i:s');
        $db->insert('twomosi_homepage_injector_settings', $ins);
    }
} catch (\Throwable $e) {}

$enabled = 1;
$targetSelector = 'div.index-filter-konfigurator';
$targetMode = 'after';
$order = ['CardProduct', 'MarkeFiltre', 'Top10List'];
try {
    $row = $db->getSingleObject("SELECT * FROM twomosi_homepage_injector_settings ORDER BY kID ASC LIMIT 1");
    if ($row !== null) {
        $enabled = (int)($row->nEnabled ?? 1);
        $targetSelector = (string)($row->cTargetSelector ?? $targetSelector);
        $targetMode = (string)($row->cTargetMode ?? $targetMode);
        $raw = (string)($row->cOrderJson ?? '');
        if ($raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) {
                $tmp = [];
                foreach ($d as $v) {
                    $s = trim((string)$v);
                    if ($s !== '') {
                        $tmp[] = $s;
                    }
                }
                if ($tmp !== []) {
                    $order = $tmp;
                }
            }
        }
    }
} catch (\Throwable $e) {}

if (Form::validateToken() && !empty($_POST['hiSave'])) {
    try {
        $enabled = !empty($_POST['nEnabled']) ? 1 : 0;
        $targetSelector = trim((string)($_POST['cTargetSelector'] ?? 'div.index-filter-konfigurator'));
        $targetMode = trim((string)($_POST['cTargetMode'] ?? 'after'));
        if (!in_array($targetMode, ['prepend', 'append', 'before', 'after'], true)) {
            $targetMode = 'after';
        }
        $orderRaw = trim((string)($_POST['cOrder'] ?? ''));
        $items = preg_split('/[\r\n,;]+/', $orderRaw) ?: [];
        $norm = [];
        foreach ($items as $it) {
            $s = trim((string)$it);
            if ($s !== '' && !in_array($s, $norm, true)) {
                $norm[] = $s;
            }
        }
        if ($norm === []) {
            $norm = ['CardProduct', 'MarkeFiltre', 'Top10List'];
        }
        $payload = json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $exists = $db->getSingleObject("SELECT kID FROM twomosi_homepage_injector_settings LIMIT 1");
        if ($exists === null) {
            $ins = new stdClass();
            $ins->nEnabled = $enabled;
            $ins->cTargetSelector = $targetSelector;
            $ins->cTargetMode = $targetMode;
            $ins->cOrderJson = $payload;
            $ins->dUpdated = date('Y-m-d H:i:s');
            $db->insert('twomosi_homepage_injector_settings', $ins);
        } else {
            $db->executeQueryPrepared(
                "UPDATE twomosi_homepage_injector_settings
                    SET nEnabled = :en,
                        cTargetSelector = :sel,
                        cTargetMode = :mode,
                        cOrderJson = :ord,
                        dUpdated = NOW()
                  WHERE kID = :id",
                ['id' => (int)$exists->kID, 'en' => $enabled, 'sel' => $targetSelector, 'mode' => $targetMode, 'ord' => $payload]
            );
        }
        $order = $norm;
        $message = 'Kaydedildi.';
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

?>
<div style="padding:10px 0;max-width:980px;">
    <h2 style="margin:0 0 12px;">Homepage Injector</h2>
    <?php if ($message !== ''): ?>
        <div style="margin:0 0 12px;padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:10px;background:rgba(204,255,0,.12);">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div style="padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;background:#fff;">
        <form method="post" style="display:grid;gap:12px;">
            <?= Form::getTokenInput(); ?>

            <label style="display:flex;gap:10px;align-items:center;">
                <input type="checkbox" name="nEnabled" value="1" <?= $enabled === 1 ? 'checked' : '' ?>>
                <b>Aktif</b> (Anasayfada widget'ları hedefe taşır ve sıraya sokar)
            </label>

            <div style="display:grid;grid-template-columns:1fr 220px;gap:10px;">
                <div>
                    <label><b>Hedef CSS selector</b></label><br>
                    <input type="text" name="cTargetSelector" value="<?= htmlspecialchars($targetSelector, ENT_QUOTES, 'UTF-8') ?>" style="width:100%;padding:8px;">
                    <div style="opacity:.75;margin-top:6px;">Örn: <code>#index-filter-konfigurator</code> veya <code>div.index-filter-konfigurator</code></div>
                </div>
                <div>
                    <label><b>Konum</b></label><br>
                    <select name="cTargetMode" style="width:100%;padding:8px;">
                        <option value="after" <?= $targetMode === 'after' ? 'selected' : '' ?>>Sonrasına (after)</option>
                        <option value="before" <?= $targetMode === 'before' ? 'selected' : '' ?>>Öncesine (before)</option>
                        <option value="append" <?= $targetMode === 'append' ? 'selected' : '' ?>>İçine sona (append)</option>
                        <option value="prepend" <?= $targetMode === 'prepend' ? 'selected' : '' ?>>İçine başa (prepend)</option>
                    </select>
                </div>
            </div>

            <div>
                <label><b>Sıra (üstten alta)</b></label><br>
                <textarea name="cOrder" rows="5" style="width:100%;padding:8px;white-space:pre;"><?= htmlspecialchars(implode("\n", $order), ENT_QUOTES, 'UTF-8') ?></textarea>
                <div style="opacity:.75;margin-top:6px;">
                    Desteklenen anahtarlar: <code>CardProduct</code>, <code>MarkeFiltre</code>, <code>Top10List</code>
                </div>
            </div>

            <div>
                <button type="submit" name="hiSave" value="1" style="padding:10px 14px;">Kaydet</button>
            </div>
        </form>
    </div>
</div>

