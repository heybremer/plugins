<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

if (\defined('RYZEAI_DASHBOARD_EXEC')) {
    return;
}
\define('RYZEAI_DASHBOARD_EXEC', true);

$db = Shop::Container()->getDB();
$message = '';
$messageType = 'success';

if (!\function_exists('ryzeai_h')) {
    function ryzeai_h(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }
}

if (!\function_exists('ryzeai_csrf')) {
    function ryzeai_csrf(): string
    {
        return \method_exists(Form::class, 'getTokenInput') ? Form::getTokenInput() : '';
    }
}

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_ryzeai_settings (
            cKey VARCHAR(100) NOT NULL,
            cValue TEXT NOT NULL DEFAULT "",
            PRIMARY KEY (cKey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {
    $message = 'Datenbanktabelle konnte nicht vorbereitet werden.';
    $messageType = 'danger';
}

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_ryzeai_events (
            kBestellung INT NOT NULL,
            cOrderId VARCHAR(64) NOT NULL DEFAULT "",
            cStatus VARCHAR(20) NOT NULL DEFAULT "processing",
            nHttpStatus INT NOT NULL DEFAULT 0,
            cResponse TEXT NOT NULL DEFAULT "",
            dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (kBestellung)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {
    // Continue rendering the dashboard.
}

$defaults = [
    'ryzeai_active' => '1',
    'ryzeai_pixel_key' => 'rz_pk_4f5b9795b424d693ae35cf13094f76bc',
    'ryzeai_s2s_active' => '1',
    'ryzeai_secret' => '',
];
foreach ($defaults as $key => $value) {
    try {
        $db->executeQueryPrepared(
            'INSERT IGNORE INTO twomosi_ryzeai_settings (cKey, cValue) VALUES (:key, :value)',
            ['key' => $key, 'value' => $value]
        );
    } catch (\Throwable $e) {
        // Continue rendering the dashboard.
    }
}

$settings = $defaults;
try {
    $rows = $db->getObjects('SELECT cKey, cValue FROM twomosi_ryzeai_settings');
    foreach ($rows as $row) {
        $settings[(string)$row->cKey] = (string)$row->cValue;
    }
} catch (\Throwable $e) {
    // Defaults remain available.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ryzeai_save'])) {
    if (!Form::validateToken()) {
        $message = 'Sicherheitstoken ungültig. Bitte laden Sie die Seite neu.';
        $messageType = 'danger';
    } else {
        $pixelKey = \trim((string)($_POST['ryzeai_pixel_key'] ?? ''));
        if (\preg_match('/^rz_pk_[a-f0-9]{16,128}$/', $pixelKey) !== 1) {
            $message = 'Bitte einen gültigen RyzeAI Pixel-Key eingeben.';
            $messageType = 'danger';
        } else {
            $save = [
                'ryzeai_active' => isset($_POST['ryzeai_active']) ? '1' : '0',
                'ryzeai_pixel_key' => $pixelKey,
                'ryzeai_s2s_active' => isset($_POST['ryzeai_s2s_active']) ? '1' : '0',
            ];
            $secret = \trim((string)($_POST['ryzeai_secret'] ?? ''));
            if ($secret !== '') {
                if (\preg_match('/^rz_sk_[a-f0-9]{32,256}$/', $secret) !== 1) {
                    $message = 'Server-Key ist ungültig.';
                    $messageType = 'danger';
                    $save = [];
                } else {
                    $save['ryzeai_secret'] = $secret;
                }
            }
            if ($save === []) {
                // Invalid secret: keep the error message.
            } else try {
                foreach ($save as $key => $value) {
                    $db->executeQueryPrepared(
                        'INSERT INTO twomosi_ryzeai_settings (cKey, cValue)
                         VALUES (:key, :value)
                         ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
                        ['key' => $key, 'value' => $value]
                    );
                    $settings[$key] = $value;
                }
                $message = 'Einstellungen wurden gespeichert.';
            } catch (\Throwable $e) {
                $message = 'Einstellungen konnten nicht gespeichert werden.';
                $messageType = 'danger';
            }
        }
    }
}

$src = 'https://px.get-ryze.ai/px.js?key=' . \rawurlencode((string)$settings['ryzeai_pixel_key']);
?>
<style>
.ryzeai-wrap{max-width:820px}.ryzeai-card{background:#fff;border:1px solid #dfe3e8;border-radius:12px;padding:1.4rem}.ryzeai-field{display:flex;flex-direction:column;gap:.35rem;margin:1rem 0}.ryzeai-field label,.ryzeai-toggle label{font-weight:600}.ryzeai-field input{border:1px solid #cbd5e0;border-radius:7px;padding:.55rem .7rem}.ryzeai-toggle{display:flex;align-items:center;gap:.6rem}.ryzeai-code{margin-top:1rem;padding:.8rem;background:#f7f9fb;border:1px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:.82rem;overflow:auto}
</style>

<div class="ryzeai-wrap">
    <h2 class="mb-3">RyzeAI <small class="text-muted" style="font-size:.8rem">v1.0.2</small></h2>
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= ryzeai_h($messageType) ?>"><?= ryzeai_h($message) ?></div>
    <?php endif; ?>

    <form method="post" action="" class="ryzeai-card">
        <?= ryzeai_csrf() ?>
        <input type="hidden" name="ryzeai_save" value="1">
        <div class="ryzeai-toggle">
            <input type="checkbox" id="ryzeai_active" name="ryzeai_active" value="1" <?= (int)$settings['ryzeai_active'] === 1 ? 'checked' : '' ?>>
            <label for="ryzeai_active">Pixel im HTML-Head aktivieren</label>
        </div>
        <div class="ryzeai-field">
            <label for="ryzeai_pixel_key">Pixel-Key</label>
            <input type="text" id="ryzeai_pixel_key" name="ryzeai_pixel_key" maxlength="140" value="<?= ryzeai_h((string)$settings['ryzeai_pixel_key']) ?>">
        </div>
        <div class="ryzeai-code">&lt;script src="<?= ryzeai_h($src) ?>"&gt;&lt;/script&gt;</div>
        <div class="ryzeai-toggle">
            <input type="checkbox" id="ryzeai_s2s_active" name="ryzeai_s2s_active" value="1" <?= (int)($settings['ryzeai_s2s_active'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label for="ryzeai_s2s_active">Server-to-server Purchase senden</label>
        </div>
        <div class="ryzeai-field">
            <label for="ryzeai_secret">Server-Key</label>
            <input type="password" id="ryzeai_secret" name="ryzeai_secret" maxlength="280" value="" autocomplete="new-password" placeholder="<?= trim((string)($settings['ryzeai_secret'] ?? '')) !== '' ? 'Gespeichert – leer lassen zum Beibehalten' : 'rz_sk_...' ?>">
        </div>
        <button type="submit" class="btn btn-primary mt-3">Speichern</button>
    </form>
</div>
