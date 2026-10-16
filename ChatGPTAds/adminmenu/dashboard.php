<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

if (\defined('CGPTADS_DASHBOARD_EXEC')) {
    return;
}
\define('CGPTADS_DASHBOARD_EXEC', true);

$db = Shop::Container()->getDB();
$message = '';
$messageType = 'success';

if (!\function_exists('cgptads_h')) {
    function cgptads_h(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
    }
}

if (!\function_exists('cgptads_csrf')) {
    function cgptads_csrf(): string
    {
        return \method_exists(Form::class, 'getTokenInput') ? Form::getTokenInput() : '';
    }
}

if (!\function_exists('cgptads_checked')) {
    function cgptads_checked(array $settings, string $key): string
    {
        return (int)($settings[$key] ?? 0) === 1 ? 'checked' : '';
    }
}

try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_chatgpt_ads_settings (
            cKey VARCHAR(100) NOT NULL,
            cValue TEXT NOT NULL DEFAULT "",
            PRIMARY KEY (cKey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_chatgpt_ads_events (
            kBestellung INT NOT NULL,
            cEventId VARCHAR(190) NOT NULL,
            cStatus VARCHAR(20) NOT NULL DEFAULT "processing",
            nHttpStatus INT NOT NULL DEFAULT 0,
            cResponse TEXT NOT NULL DEFAULT "",
            dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (kBestellung),
            UNIQUE KEY uk_event_id (cEventId),
            KEY idx_status (cStatus)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {
    $message = 'Datenbanktabellen konnten nicht vorbereitet werden.';
    $messageType = 'danger';
}

$defaults = [
    'cgptads_active' => '1',
    'cgptads_pixel_id' => 'W3bQdmcVefJnyrpLetFegL',
    'cgptads_debug' => '1',
    'cgptads_browser_conversion' => '1',
    'cgptads_api_active' => '0',
    'cgptads_api_key' => '',
];

foreach ($defaults as $key => $value) {
    try {
        $db->executeQueryPrepared(
            'INSERT IGNORE INTO twomosi_chatgpt_ads_settings (cKey, cValue) VALUES (:key, :value)',
            ['key' => $key, 'value' => $value]
        );
    } catch (\Throwable $e) {
        // Continue rendering the dashboard.
    }
}

$settings = $defaults;
try {
    $rows = $db->getObjects('SELECT cKey, cValue FROM twomosi_chatgpt_ads_settings');
    foreach ($rows as $row) {
        $settings[(string)$row->cKey] = (string)$row->cValue;
    }
} catch (\Throwable $e) {
    // Defaults remain available.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cgptads_save'])) {
    if (!Form::validateToken()) {
        $message = 'Sicherheitstoken ungültig. Bitte laden Sie die Seite neu.';
        $messageType = 'danger';
    } else {
        $pixelId = \trim((string)($_POST['cgptads_pixel_id'] ?? ''));
        if (\preg_match('/^[A-Za-z0-9_-]{8,100}$/', $pixelId) !== 1) {
            $message = 'Bitte eine gültige Pixel-ID eingeben.';
            $messageType = 'danger';
        } else {
            $save = [
                'cgptads_active' => isset($_POST['cgptads_active']) ? '1' : '0',
                'cgptads_pixel_id' => $pixelId,
                'cgptads_debug' => isset($_POST['cgptads_debug']) ? '1' : '0',
                'cgptads_browser_conversion' => isset($_POST['cgptads_browser_conversion']) ? '1' : '0',
                'cgptads_api_active' => isset($_POST['cgptads_api_active']) ? '1' : '0',
            ];

            $newApiKey = \trim((string)($_POST['cgptads_api_key'] ?? ''));
            if (!empty($_POST['cgptads_api_key_clear'])) {
                $save['cgptads_api_key'] = '';
            } elseif ($newApiKey !== '') {
                $save['cgptads_api_key'] = $newApiKey;
            }

            try {
                foreach ($save as $key => $value) {
                    $db->executeQueryPrepared(
                        'INSERT INTO twomosi_chatgpt_ads_settings (cKey, cValue)
                         VALUES (:key, :value)
                         ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
                        ['key' => $key, 'value' => $value]
                    );
                    $settings[$key] = $value;
                }
                $message = 'Einstellungen wurden gespeichert.';
                $messageType = 'success';
            } catch (\Throwable $e) {
                $message = 'Einstellungen konnten nicht gespeichert werden.';
                $messageType = 'danger';
            }
        }
    }
}

$events = [];
try {
    $events = $db->getObjects(
        'SELECT kBestellung, cEventId, cStatus, nHttpStatus, cResponse, dUpdated
           FROM twomosi_chatgpt_ads_events
          ORDER BY dUpdated DESC
          LIMIT 20'
    );
} catch (\Throwable $e) {
    $events = [];
}

$hasApiKey = \trim((string)($settings['cgptads_api_key'] ?? '')) !== '';
?>
<style>
.cgptads-wrap{max-width:980px}.cgptads-card{background:#fff;border:1px solid #dfe3e8;border-radius:12px;padding:1.4rem;margin-bottom:1.25rem}.cgptads-title{font-size:1rem;font-weight:700;margin:0 0 1rem;padding-bottom:.65rem;border-bottom:2px solid #f0f2f5}.cgptads-field{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem}.cgptads-field label,.cgptads-toggle label{font-weight:600;color:#2d3748}.cgptads-field input{border:1px solid #cbd5e0;border-radius:7px;padding:.55rem .7rem}.cgptads-toggle{display:flex;align-items:center;gap:.6rem;margin:.75rem 0}.cgptads-hint{font-size:.8rem;color:#687386}.cgptads-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.cgptads-code{padding:.8rem;background:#f7f9fb;border:1px solid #e2e8f0;border-radius:8px;font-family:monospace;font-size:.82rem;overflow:auto}.cgptads-ok{color:#198754;font-weight:700}.cgptads-off{color:#6c757d}.cgptads-table{width:100%;border-collapse:collapse;font-size:.82rem}.cgptads-table th,.cgptads-table td{padding:.55rem;border-bottom:1px solid #edf0f2;text-align:left;vertical-align:top}.cgptads-status-sent{color:#198754;font-weight:700}.cgptads-status-failed{color:#dc3545;font-weight:700}@media(max-width:700px){.cgptads-grid{grid-template-columns:1fr}}
</style>

<div class="cgptads-wrap">
    <h2 class="mb-3">ChatGPT Ads <small class="text-muted" style="font-size:.8rem">v1.0.1</small></h2>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= cgptads_h($messageType) ?>"><?= cgptads_h($message) ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <?= cgptads_csrf() ?>
        <input type="hidden" name="cgptads_save" value="1">

        <div class="cgptads-card">
            <h3 class="cgptads-title">OpenAI Ads Pixel</h3>
            <div class="cgptads-toggle">
                <input type="checkbox" id="cgptads_active" name="cgptads_active" value="1" <?= cgptads_checked($settings, 'cgptads_active') ?>>
                <label for="cgptads_active">Pixel aktivieren</label>
            </div>
            <div class="cgptads-field">
                <label for="cgptads_pixel_id">Pixel-ID</label>
                <input type="text" id="cgptads_pixel_id" name="cgptads_pixel_id" maxlength="100" value="<?= cgptads_h((string)$settings['cgptads_pixel_id']) ?>">
            </div>
            <div class="cgptads-grid">
                <div class="cgptads-toggle">
                    <input type="checkbox" id="cgptads_debug" name="cgptads_debug" value="1" <?= cgptads_checked($settings, 'cgptads_debug') ?>>
                    <label for="cgptads_debug">Debug-Modus aktivieren</label>
                </div>
                <div class="cgptads-toggle">
                    <input type="checkbox" id="cgptads_browser_conversion" name="cgptads_browser_conversion" value="1" <?= cgptads_checked($settings, 'cgptads_browser_conversion') ?>>
                    <label for="cgptads_browser_conversion">Browser-Conversion senden</label>
                </div>
            </div>
            <p class="cgptads-hint">Das Installationsskript wird einmal pro Seite möglichst weit oben in den HTML-Head eingefügt.</p>
            <div class="cgptads-code">oaiq("measure", "order_created", { type: "contents", amount: 14900, currency: "EUR", contents: [...] }, { event_id: "jtl-order-123" });</div>
        </div>

        <div class="cgptads-card">
            <h3 class="cgptads-title">Conversions API</h3>
            <div class="cgptads-toggle">
                <input type="checkbox" id="cgptads_api_active" name="cgptads_api_active" value="1" <?= cgptads_checked($settings, 'cgptads_api_active') ?>>
                <label for="cgptads_api_active">Serverseitige order_created Events senden</label>
            </div>
            <div class="cgptads-field">
                <label for="cgptads_api_key">API-Key</label>
                <input type="password" id="cgptads_api_key" name="cgptads_api_key" value="" autocomplete="new-password" placeholder="<?= $hasApiKey ? 'Gespeichert – leer lassen zum Beibehalten' : 'API-Key eingeben' ?>">
                <span class="cgptads-hint">
                    Status:
                    <span class="<?= $hasApiKey ? 'cgptads-ok' : 'cgptads-off' ?>">
                        <?= $hasApiKey ? 'API-Key gespeichert' : 'Kein API-Key gespeichert' ?>
                    </span>
                </span>
            </div>
            <?php if ($hasApiKey): ?>
                <div class="cgptads-toggle">
                    <input type="checkbox" id="cgptads_api_key_clear" name="cgptads_api_key_clear" value="1">
                    <label for="cgptads_api_key_clear">Gespeicherten API-Key löschen</label>
                </div>
            <?php endif; ?>
            <p class="cgptads-hint">Der API-Key wird nie im Frontend ausgegeben. Jede Bestellung wird serverseitig höchstens einmal registriert.</p>
        </div>

        <button type="submit" class="btn btn-primary">Speichern</button>
    </form>

    <div class="cgptads-card mt-4">
        <h3 class="cgptads-title">Letzte Server-Events</h3>
        <?php if ($events === []): ?>
            <p class="cgptads-hint mb-0">Noch keine serverseitigen Events vorhanden.</p>
        <?php else: ?>
            <div style="overflow:auto">
                <table class="cgptads-table">
                    <thead>
                        <tr>
                            <th>Bestellung</th>
                            <th>Event-ID</th>
                            <th>Status</th>
                            <th>HTTP</th>
                            <th>Aktualisiert</th>
                            <th>Antwort</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php $status = (string)($event->cStatus ?? ''); ?>
                        <tr>
                            <td><?= (int)($event->kBestellung ?? 0) ?></td>
                            <td><?= cgptads_h((string)($event->cEventId ?? '')) ?></td>
                            <td class="cgptads-status-<?= cgptads_h($status) ?>"><?= cgptads_h($status) ?></td>
                            <td><?= (int)($event->nHttpStatus ?? 0) ?></td>
                            <td><?= cgptads_h((string)($event->dUpdated ?? '')) ?></td>
                            <td><?= cgptads_h(\mb_substr((string)($event->cResponse ?? ''), 0, 180)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
