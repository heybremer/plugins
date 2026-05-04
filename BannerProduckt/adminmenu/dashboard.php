<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';

try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_bannerproduckt_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cBannersJson LONGTEXT NULL,
            cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
            cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after',
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    try { $db->executeQuery("ALTER TABLE twomosi_bannerproduckt_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator' AFTER cBannersJson"); } catch (\Throwable $e) {}
    try { $db->executeQuery("ALTER TABLE twomosi_bannerproduckt_settings ADD COLUMN cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after' AFTER cInjectSelector"); } catch (\Throwable $e) {}
} catch (\Throwable $e) {
    // ignore
}

function bpDefaultBanners(): array
{
    return [
        ['eyebrow' => 'NEW ARRIVALS', 'title' => 'Flash Wireless', 'subtitle' => 'Earphones', 'image' => '', 'url' => '#', 'variant' => 'small', 'cta' => ''],
        ['eyebrow' => 'NEW ARRIVALS', 'title' => 'Flash Wireless', 'subtitle' => 'Earphones', 'image' => '', 'url' => '#', 'variant' => 'small', 'cta' => ''],
        ['eyebrow' => 'New Collection', 'title' => 'TOP CAMERA', 'subtitle' => 'Mirrorless', 'image' => '', 'url' => '#', 'variant' => 'tall', 'cta' => 'SHOP NOW'],
        ['eyebrow' => 'NEW ARRIVALS', 'title' => 'Flash Wireless', 'subtitle' => 'Earphones', 'image' => '', 'url' => '#', 'variant' => 'small', 'cta' => ''],
        ['eyebrow' => 'NEW ARRIVALS', 'title' => 'Flash Wireless', 'subtitle' => 'Earphones', 'image' => '', 'url' => '#', 'variant' => 'small', 'cta' => ''],
    ];
}

$active = 1;
$banners = bpDefaultBanners();
$injectSelector = 'div.index-filter-konfigurator';
$injectMode = 'after';
try {
    $row = $db->getSingleObject("SELECT * FROM twomosi_bannerproduckt_settings LIMIT 1");
    if ($row !== null) {
        $active = (int)($row->nActive ?? 1);
        $injectSelector = (string)($row->cInjectSelector ?? $injectSelector);
        $injectMode = (string)($row->cInjectMode ?? $injectMode);
        $raw = (string)($row->cBannersJson ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $banners = $decoded;
            }
        }
    }
} catch (\Throwable $e) {
    // ignore
}

if (!in_array($injectMode, ['after', 'before', 'prepend', 'append'], true)) {
    $injectMode = 'after';
}

if (Form::validateToken() && !empty($_POST['bpSave'])) {
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
        $banners = [];
        $titles = $_POST['title'] ?? [];
        $count = is_array($titles) ? count($titles) : 0;
        for ($i = 0; $i < $count; $i++) {
            $banners[] = [
                'eyebrow' => trim((string)($_POST['eyebrow'][$i] ?? '')),
                'title' => trim((string)($_POST['title'][$i] ?? '')),
                'subtitle' => trim((string)($_POST['subtitle'][$i] ?? '')),
                'image' => trim((string)($_POST['image'][$i] ?? '')),
                'url' => trim((string)($_POST['url'][$i] ?? '#')),
                'variant' => ($i === 2) ? 'tall' : 'small',
                'cta' => trim((string)($_POST['cta'][$i] ?? '')),
            ];
        }
        if (count($banners) !== 5) {
            $banners = bpDefaultBanners();
        }

        $payload = json_encode($banners, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = $db->getSingleObject("SELECT kID FROM twomosi_bannerproduckt_settings LIMIT 1");
        if ($exists === null) {
            $ins = new stdClass();
            $ins->nActive = $active;
            $ins->cBannersJson = $payload;
            $ins->cInjectSelector = $injectSelector;
            $ins->cInjectMode = $injectMode;
            $ins->dUpdated = date('Y-m-d H:i:s');
            $db->insert('twomosi_bannerproduckt_settings', $ins);
        } else {
            $db->executeQueryPrepared(
                "UPDATE twomosi_bannerproduckt_settings
                    SET nActive = :nActive,
                        cBannersJson = :cBannersJson,
                        cInjectSelector = :cInjectSelector,
                        cInjectMode = :cInjectMode,
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID' => (int)$exists->kID,
                    'nActive' => $active,
                    'cBannersJson' => $payload,
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
    <div class="card-header"><strong>BannerProduckt Dashboard</strong></div>
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
                Düzen: 5 banner (solda 2x2 küçük, sağda 1 büyük). Banner #3 otomatik “büyük” olarak ayarlanır.
            </p>

            <?php for ($i = 0; $i < 5; $i++):
                $b = is_array($banners[$i] ?? null) ? $banners[$i] : [];
            ?>
                <div class="card" style="margin:12px 0;">
                    <div class="card-header"><strong>Banner <?php echo $i + 1; ?> <?php echo ($i === 2) ? '(Büyük)' : ''; ?></strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Üst Başlık (eyebrow)</label>
                                <input class="form-control" type="text" name="eyebrow[]" value="<?php echo htmlspecialchars((string)($b['eyebrow'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>CTA (opsiyonel)</label>
                                <input class="form-control" type="text" name="cta[]" value="<?php echo htmlspecialchars((string)($b['cta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Başlık</label>
                                <input class="form-control" type="text" name="title[]" value="<?php echo htmlspecialchars((string)($b['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Alt Başlık</label>
                                <input class="form-control" type="text" name="subtitle[]" value="<?php echo htmlspecialchars((string)($b['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Görsel URL</label>
                                <input class="form-control" type="text" name="image[]" value="<?php echo htmlspecialchars((string)($b['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://... veya /media/...">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Link URL</label>
                                <input class="form-control" type="text" name="url[]" value="<?php echo htmlspecialchars((string)($b['url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <button class="btn btn-primary" type="submit" name="bpSave" value="1">Kaydet</button>
        </form>
    </div>
</div>

