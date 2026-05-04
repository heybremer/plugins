<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fourlayout_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cItemsJson LONGTEXT NULL,
            cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
            cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after',
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    try { $db->executeQuery("ALTER TABLE twomosi_fourlayout_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator' AFTER cItemsJson"); } catch (\Throwable $e) {}
    try { $db->executeQuery("ALTER TABLE twomosi_fourlayout_settings ADD COLUMN cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after' AFTER cInjectSelector"); } catch (\Throwable $e) {}
} catch (\Throwable $e) {
    // ignore
}

function flDefaultItems(): array
{
    return [
        ['title' => 'Videos', 'subtitle' => 'Unsere Produkte und Tipps entdecken', 'button' => 'Entdecken', 'image' => '', 'url' => '#', 'rating' => 0],
        ['title' => 'Kundenbewertungen', 'subtitle' => 'Echte Kundenmeinungen lesen.', 'button' => 'Entdecken', 'image' => '', 'url' => '#', 'rating' => 5],
        ['title' => 'Großhandelsanfrage', 'subtitle' => 'Partner werden und Vorteile sichern.', 'button' => 'Entdecken', 'image' => '', 'url' => '#', 'rating' => 0],
        ['title' => 'Reduzierte Produkte', 'subtitle' => 'Hochwertige Produkte günstig sichern.', 'button' => 'Entdecken', 'image' => '', 'url' => '#', 'rating' => 0],
    ];
}

$active = 1;
$items = flDefaultItems();
$injectSelector = 'div.index-filter-konfigurator';
$injectMode = 'after';

try {
    $row = $db->getSingleObject("SELECT * FROM twomosi_fourlayout_settings LIMIT 1");
    if ($row !== null) {
        $active = (int)($row->nActive ?? 1);
        $injectSelector = (string)($row->cInjectSelector ?? $injectSelector);
        $injectMode = (string)($row->cInjectMode ?? $injectMode);
        $raw = (string)($row->cItemsJson ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }
    }
} catch (\Throwable $e) {
    // ignore
}

if (!in_array($injectMode, ['after', 'before', 'prepend', 'append'], true)) {
    $injectMode = 'after';
}

// normalize
if (!is_array($items) || count($items) < 4) {
    $items = flDefaultItems();
}
$items = array_values(array_slice($items, 0, 4));

if (Form::validateToken() && !empty($_POST['flSave'])) {
    try {
        $active = !empty($_POST['nActive']) ? 1 : 0;
        $injectSelector = trim((string)($_POST['injectSelector'] ?? 'div.index-filter-konfigurator'));
        if ($injectSelector === '') {
            $injectSelector = 'div.index-filter-konfigurator';
        }
        $injectMode = (string)($_POST['injectMode'] ?? 'after');
        if (!in_array($injectMode, ['after', 'before', 'prepend', 'append'], true)) {
            $injectMode = 'after';
        }

        $items = [];
        $titles = $_POST['title'] ?? [];
        $count = is_array($titles) ? count($titles) : 0;
        for ($i = 0; $i < $count; $i++) {
            $rating = (int)($_POST['rating'][$i] ?? 0);
            if ($rating < 0) $rating = 0;
            if ($rating > 5) $rating = 5;
            $items[] = [
                'title' => trim((string)($_POST['title'][$i] ?? '')),
                'subtitle' => trim((string)($_POST['subtitle'][$i] ?? '')),
                'button' => trim((string)($_POST['button'][$i] ?? 'Entdecken')),
                'image' => trim((string)($_POST['image'][$i] ?? '')),
                'url' => trim((string)($_POST['url'][$i] ?? '#')),
                'rating' => $rating,
            ];
        }
        if (count($items) !== 4) {
            $items = flDefaultItems();
        }

        $payload = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = $db->getSingleObject("SELECT kID FROM twomosi_fourlayout_settings LIMIT 1");
        if ($exists === null) {
            $ins = new stdClass();
            $ins->nActive = $active;
            $ins->cItemsJson = $payload;
            $ins->cInjectSelector = $injectSelector;
            $ins->cInjectMode = $injectMode;
            $ins->dUpdated = date('Y-m-d H:i:s');
            $db->insert('twomosi_fourlayout_settings', $ins);
        } else {
            $db->executeQueryPrepared(
                "UPDATE twomosi_fourlayout_settings
                    SET nActive = :nActive,
                        cItemsJson = :cItemsJson,
                        cInjectSelector = :cInjectSelector,
                        cInjectMode = :cInjectMode,
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID' => (int)$exists->kID,
                    'nActive' => $active,
                    'cItemsJson' => $payload,
                    'cInjectSelector' => $injectSelector,
                    'cInjectMode' => $injectMode,
                ]
            );
        }

        $message = 'Kaydedildi.';
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}
?>

<?php if ($message !== ''): ?>
    <div class="alert alert-info" style="margin:10px 0;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width:1100px;">
    <div class="card-header"><strong>FourLayout Dashboard</strong></div>
    <div class="card-body">
        <form method="post">
            <?php echo Form::getTokenInput(); ?>

            <div class="form-group">
                <label style="display:flex;gap:10px;align-items:center;">
                    <input type="checkbox" name="nActive" value="1" <?php echo $active === 1 ? 'checked' : ''; ?>>
                    Aktif (anasayfada göster)
                </label>
            </div>

            <div class="card" style="margin: 12px 0;">
                <div class="card-header"><strong>Enjeksiyon Konumu</strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>CSS Selector (virgül ile birden fazla yazabilirsin)</label>
                            <input class="form-control" type="text" name="injectSelector" value="<?php echo htmlspecialchars($injectSelector, ENT_QUOTES, 'UTF-8'); ?>" placeholder="div.index-filter-konfigurator, div#content">
                            <small style="display:block;margin-top:6px;color:rgba(0,0,0,.6);">
                                İlk bulunan elemana enjekte edilir. Hiçbiri bulunamazsa otomatik içerik başına ekler.
                            </small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Konum</label>
                            <select class="form-control" name="injectMode">
                                <option value="after" <?php echo $injectMode === 'after' ? 'selected' : ''; ?>>Sonra (after)</option>
                                <option value="before" <?php echo $injectMode === 'before' ? 'selected' : ''; ?>>Önce (before)</option>
                                <option value="prepend" <?php echo $injectMode === 'prepend' ? 'selected' : ''; ?>>İçine başa (prepend)</option>
                                <option value="append" <?php echo $injectMode === 'append' ? 'selected' : ''; ?>>İçine sona (append)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <p style="color:rgba(0,0,0,.6);margin-bottom:10px;">
                4 kart düzeni: 2x2 grid. Her kart için başlık / açıklama / buton / görsel / link düzenlenir. (Rating: 0-5 yıldız)
            </p>

            <?php for ($i = 0; $i < 4; $i++):
                $it = is_array($items[$i] ?? null) ? $items[$i] : [];
            ?>
                <div class="card" style="margin:12px 0;">
                    <div class="card-header"><strong>Kart <?php echo $i + 1; ?></strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Başlık</label>
                                <input class="form-control" type="text" name="title[]" value="<?php echo htmlspecialchars((string)($it['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Buton Yazısı</label>
                                <input class="form-control" type="text" name="button[]" value="<?php echo htmlspecialchars((string)($it['button'] ?? 'Entdecken'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8 form-group">
                                <label>Alt Yazı</label>
                                <input class="form-control" type="text" name="subtitle[]" value="<?php echo htmlspecialchars((string)($it['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Rating (0-5)</label>
                                <input class="form-control" type="number" min="0" max="5" name="rating[]" value="<?php echo (int)($it['rating'] ?? 0); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Görsel URL</label>
                                <input class="form-control" type="text" name="image[]" value="<?php echo htmlspecialchars((string)($it['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... veya /media/...">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Link URL</label>
                                <input class="form-control" type="text" name="url[]" value="<?php echo htmlspecialchars((string)($it['url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <button class="btn btn-primary" type="submit" name="flSave" value="1">Kaydet</button>
        </form>
    </div>
</div>

