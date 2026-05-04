<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\Top10List\Top10ListDatabase;

require_once dirname(__DIR__) . '/src/Top10ListDatabase.php';

$db = Shop::Container()->getDB();
Top10ListDatabase::ensureAll();
$message = '';

if (!function_exists('t10DefaultTabs')) {
    function t10DefaultTabs(): array
    {
        return [
            ['key' => 'pkw', 'label' => 'PKW', 'keyword' => 'pkw', 'articleIds' => []],
            ['key' => 'wohnmobil', 'label' => 'Wohnmobil', 'keyword' => 'wohnmobil', 'articleIds' => []],
            ['key' => 'andere', 'label' => 'Andere', 'keyword' => '', 'articleIds' => []],
        ];
    }
}

if (!function_exists('t10NormalizeArticleIds')) {
    function t10NormalizeArticleIds(mixed $raw): array
    {
        $parts = [];
        if (is_array($raw)) {
            $parts = $raw;
        } elseif (is_string($raw)) {
            $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id > 0 && !isset($ids[$id])) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
if (!function_exists('t10FindArticlesByQuery')) {
    function t10FindArticlesByQuery($db, string $query, int $limit = 20): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $like = '%' . $q . '%';
        $exact = $q;
        $exactID = ctype_digit($q) ? (int)$q : -1;
        try {
            return $db->getObjects(
                "SELECT
                    a.kArtikel,
                    COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                    COALESCE(a.cArtNr, '') AS cArtNr,
                    COALESCE(a.cBarcode, '') AS cBarcode,
                    COALESCE(a.cISBN, '') AS cISBN,
                    COALESCE(a.cHAN, '') AS cHAN,
                    COALESCE(a.cUPC, '') AS cUPC
                 FROM tartikel a
                 LEFT JOIN (
                    SELECT s1.kArtikel, MIN(s1.cName) AS cName
                    FROM tartikelsprache s1
                    GROUP BY s1.kArtikel
                 ) ts ON ts.kArtikel = a.kArtikel
                 WHERE a.nIstVater = 0
                   AND (
                        a.kArtikel = :exactID
                        OR
                        a.cName LIKE :like
                        OR
                        a.cArtNr LIKE :like
                        OR ts.cName LIKE :like
                        OR a.cSeo LIKE :like
                        OR a.cBarcode LIKE :like
                        OR a.cISBN LIKE :like
                        OR a.cHAN LIKE :like
                        OR a.cUPC LIKE :like
                        OR a.cArtNr = :exact
                        OR a.cBarcode = :exact
                        OR a.cISBN = :exact
                        OR a.cHAN = :exact
                        OR a.cUPC = :exact
                   )
                 ORDER BY
                    (a.cBarcode = :exact2) DESC,
                    (a.cArtNr = :exact3) DESC,
                    (a.kArtikel = :exactID2) DESC,
                    a.kArtikel DESC
                 LIMIT {$limit}",
                ['like' => $like, 'exact' => $exact, 'exact2' => $exact, 'exact3' => $exact, 'exactID' => $exactID, 'exactID2' => $exactID]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}

/**
 * @param array<int, int> $ids
 * @return array<int, array<string, mixed>>
 */
if (!function_exists('t10FetchArticlesByIds')) {
    function t10FetchArticlesByIds($db, array $ids): array
    {
        $ids = t10NormalizeArticleIds($ids);
        if ($ids === []) {
            return [];
        }
        $idList = implode(',', array_map(static fn(int $id): string => (string)$id, $ids));
        try {
            $rows = $db->getObjects(
                "SELECT
                    a.kArtikel,
                    COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                    COALESCE(a.cArtNr, '') AS cArtNr,
                    COALESCE(a.cBarcode, '') AS cBarcode
                 FROM tartikel a
                 LEFT JOIN (
                    SELECT s1.kArtikel, MIN(s1.cName) AS cName
                    FROM tartikelsprache s1
                    GROUP BY s1.kArtikel
                 ) ts ON ts.kArtikel = a.kArtikel
                 WHERE a.kArtikel IN ({$idList})"
            );
        } catch (\Throwable $e) {
            return [];
        }

        $byId = [];
        foreach ($rows as $r) {
            $id = (int)($r->kArtikel ?? 0);
            if ($id > 0) {
                $byId[$id] = [
                    'id' => $id,
                    'name' => (string)($r->cName ?? ''),
                    'artNr' => (string)($r->cArtNr ?? ''),
                    'barcode' => (string)($r->cBarcode ?? ''),
                ];
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }
}

if (isset($_GET['t10Search']) && (string)$_GET['t10Search'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    $q = (string)($_GET['q'] ?? '');
    $found = t10FindArticlesByQuery($db, $q, 25);
    $items = [];
    foreach ($found as $r) {
        $id = (int)($r->kArtikel ?? 0);
        if ($id <= 0) {
            continue;
        }
        $items[] = [
            'id' => $id,
            'name' => (string)($r->cName ?? ''),
            'artNr' => (string)($r->cArtNr ?? ''),
            'barcode' => (string)($r->cBarcode ?? ''),
        ];
    }
    $payload = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '__T10JSON_START__' . ($payload !== false ? $payload : '{"items":[]}') . '__T10JSON_END__';
    exit;
}

$active = 1;
$title = 'En çok satılan';
$sortMode = 'newest';
$injectMode = 'after_configurator';
$injectPos = 'after';
$injectSelector = '';
$refreshDom = 1;
$refreshHour = 3;
$tabs = t10DefaultTabs();
try {
    $row = $db->getSingleObject("SELECT * FROM twomosi_top10list_settings LIMIT 1");
    if ($row !== null) {
        $active = (int)($row->nActive ?? 1);
        $title = (string)($row->cTitle ?? $title);
        $sortMode = (string)($row->cSortMode ?? 'newest');
        $injectMode = (string)($row->cInjectMode ?? 'after_configurator');
        $injectPos = (string)($row->cInjectPos ?? 'after');
        $injectSelector = (string)($row->cInjectSelector ?? '');
        $refreshDom = (int)($row->nRefreshDom ?? 1);
        $refreshHour = (int)($row->nRefreshHour ?? 3);
        $raw = (string)($row->cTabsJson ?? '');
        if ($raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d) && count($d) >= 3) {
                $tabs = $d;
            }
        }
    }
} catch (\Throwable $e) {
    // ignore
}

if (Form::validateToken() && !empty($_POST['t10Save'])) {
    try {
        $active = !empty($_POST['nActive']) ? 1 : 0;
        $title = trim((string)($_POST['title'] ?? 'En çok satılan'));
        $sortMode = (string)($_POST['sortMode'] ?? 'newest');
        if (!in_array($sortMode, ['newest', 'best_sold'], true)) {
            $sortMode = 'newest';
        }
        $injectMode = trim((string)($_POST['cInjectMode'] ?? 'after_configurator'));
        $injectPos = trim((string)($_POST['cInjectPos'] ?? 'after'));
        $injectSelector = trim((string)($_POST['cInjectSelector'] ?? ''));
        $refreshDom = (int)($_POST['nRefreshDom'] ?? 1);
        $refreshHour = (int)($_POST['nRefreshHour'] ?? 3);
        if ($refreshDom < 1 || $refreshDom > 28) { $refreshDom = 1; }
        if ($refreshHour < 0 || $refreshHour > 23) { $refreshHour = 3; }
        if (!in_array($injectMode, ['after_configurator', 'after_content', 'after_selector'], true)) {
            $injectMode = 'after_configurator';
        }
        if (!in_array($injectPos, ['before', 'after'], true)) {
            $injectPos = 'after';
        }
        if ($title === '') {
            $title = 'En çok satılan';
        }
        $keys = $_POST['key'] ?? [];
        $labels = $_POST['label'] ?? [];
        $keywords = $_POST['keyword'] ?? [];
        $articleIds = $_POST['articleIds'] ?? [];
        $tabs = [];
        $count = max(count($labels), count($keywords), count($articleIds), count($keys));
        for ($i = 0; $i < $count; $i++) {
            $key = trim((string)($keys[$i] ?? ''));
            if ($key === '') {
                $key = 'tab' . ($i + 1);
            }
            $tabs[] = [
                'key' => $key,
                'label' => trim((string)($labels[$i] ?? '')),
                'keyword' => trim((string)($keywords[$i] ?? '')),
                'articleIds' => t10NormalizeArticleIds((string)($articleIds[$i] ?? '')),
            ];
        }
        $payload = json_encode($tabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $exists = $db->getSingleObject("SELECT kID FROM twomosi_top10list_settings LIMIT 1");
        if ($exists === null) {
            $ins = new stdClass();
            $ins->nActive = $active;
            $ins->cTitle = $title;
            $ins->cSortMode = $sortMode;
            $ins->cInjectMode = $injectMode;
            $ins->cInjectPos = $injectPos;
            $ins->cInjectSelector = $injectSelector;
            $ins->nRefreshDom = $refreshDom;
            $ins->nRefreshHour = $refreshHour;
            $ins->cTabsJson = $payload;
            $ins->dUpdated = date('Y-m-d H:i:s');
            $db->insert('twomosi_top10list_settings', $ins);
        } else {
            $db->executeQueryPrepared(
                "UPDATE twomosi_top10list_settings
                    SET nActive = :nActive,
                        cTitle = :cTitle,
                        cSortMode = :cSortMode,
                        cInjectMode = :cInjectMode,
                        cInjectPos = :cInjectPos,
                        cInjectSelector = :cInjectSelector,
                        nRefreshDom = :dom,
                        nRefreshHour = :hr,
                        cTabsJson = :cTabsJson,
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID' => (int)$exists->kID,
                    'nActive' => $active,
                    'cTitle' => $title,
                    'cSortMode' => $sortMode,
                    'cInjectMode' => $injectMode,
                    'cInjectPos' => $injectPos,
                    'cInjectSelector' => $injectSelector,
                    'dom' => $refreshDom,
                    'hr' => $refreshHour,
                    'cTabsJson' => $payload,
                ]
            );
        }
        $message = 'Kaydedildi.';
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['t10RefreshCache'])) {
    // Cache rebuild is performed automatically by frontend request (Bootstrap) when cache is empty / outdated / settings changed.
    // Here we just mark cache as stale by clearing it for current language, so next homepage view rebuilds it.
    try {
        $langID = (int)Shop::getLanguageID();
        $tryLangs = [$langID];
        if ($langID !== 1) { $tryLangs[] = 1; }
        foreach ($tryLangs as $lid) {
            $db->executeQueryPrepared("DELETE FROM twomosi_top10list_cache WHERE kSprache = :lid", ['lid' => $lid]);
            $db->executeQueryPrepared(
                "INSERT INTO twomosi_top10list_meta (cKey, cValue)
                 VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)",
                ['k' => 'top10_last_refresh_lang_' . $lid, 'v' => '']
            );
        }
        $message = 'Cache sıfırlandı. Anasayfa açıldığında otomatik yeniden oluşturulacak.';
    } catch (\Throwable $e) {
        $message = 'Cache sıfırlama hatası: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['t10AddTab'])) {
    $tabs[] = [
        'key' => 'tab' . (count($tabs) + 1),
        'label' => 'Yeni Tab',
        'keyword' => '',
        'articleIds' => [],
    ];
}

if (Form::validateToken() && isset($_POST['t10DeleteTab'])) {
    $deleteIdx = (int)$_POST['t10DeleteTab'];
    if ($deleteIdx >= 0 && $deleteIdx < count($tabs) && count($tabs) > 1) {
        array_splice($tabs, $deleteIdx, 1);
    }
}

if (Form::validateToken() && (!empty($_POST['t10AddTab']) || isset($_POST['t10DeleteTab']))) {
    try {
        $payload = json_encode(array_values($tabs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = $db->getSingleObject("SELECT kID FROM twomosi_top10list_settings LIMIT 1");
        if ($exists === null) {
            $ins = new stdClass();
            $ins->nActive = $active;
            $ins->cTitle = $title;
            $ins->cSortMode = $sortMode;
            $ins->cInjectMode = $injectMode;
            $ins->cInjectPos = $injectPos;
            $ins->cInjectSelector = $injectSelector;
            $ins->cTabsJson = $payload;
            $ins->dUpdated = date('Y-m-d H:i:s');
            $db->insert('twomosi_top10list_settings', $ins);
        } else {
            $db->executeQueryPrepared(
                "UPDATE twomosi_top10list_settings
                    SET cTabsJson = :cTabsJson,
                        cSortMode = :cSortMode,
                        cInjectMode = :cInjectMode,
                        cInjectPos = :cInjectPos,
                        cInjectSelector = :cInjectSelector,
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID' => (int)$exists->kID,
                    'cSortMode' => $sortMode,
                    'cInjectMode' => $injectMode,
                    'cInjectPos' => $injectPos,
                    'cInjectSelector' => $injectSelector,
                    'cTabsJson' => $payload,
                ]
            );
        }
        $message = !empty($_POST['t10AddTab']) ? 'Tab eklendi.' : 'Tab silindi.';
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

$selectedByTab = [];
for ($i = 0; $i < count($tabs); $i++) {
    $tab = $tabs[$i] ?? [];
    $selectedByTab[$i] = t10FetchArticlesByIds($db, t10NormalizeArticleIds($tab['articleIds'] ?? []));
}
?>
<?php if ($message !== ''): ?>
    <div class="alert alert-info" style="margin:10px 0;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<style>
    .t10-admin-wrap{max-width:1080px;background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(17,24,39,.08);overflow:hidden}
    .t10-admin-head{padding:18px 24px;background:linear-gradient(135deg,#1f2937,#111827);color:#fff}
    .t10-admin-head h3{margin:0;font-size:20px}
    .t10-admin-body{padding:20px 24px}
    .t10-tab-card{border:1px solid #e5e7eb;border-radius:12px;margin:14px 0}
    .t10-tab-title{padding:12px 14px;background:#f9fafb;border-bottom:1px solid #e5e7eb;font-weight:700}
    .t10-selector{border:1px solid #d1d5db;border-radius:10px;padding:10px;background:#fff}
    .t10-selected{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .t10-chip{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;background:#eef2ff;color:#1e3a8a;border-radius:999px;font-size:12px}
    .t10-chip button{border:0;background:transparent;color:#1e3a8a;font-weight:700;cursor:pointer;line-height:1}
    .t10-search-wrap{position:relative}
    .t10-tab-actions{display:flex;justify-content:flex-end;margin:8px 0 14px}
    .t10-results{position:absolute;z-index:20;left:0;right:0;top:100%;margin-top:6px;background:#fff;border:1px solid #d1d5db;border-radius:10px;max-height:260px;overflow:auto;box-shadow:0 10px 24px rgba(0,0,0,.1);display:none}
    .t10-result{padding:10px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6}
    .t10-result:last-child{border-bottom:0}
    .t10-result:hover{background:#f9fafb}
    .t10-result small{display:block;color:#6b7280}
</style>

<div class="t10-admin-wrap">
    <div class="t10-admin-head"><h3>Top10List Dashboard</h3></div>
    <div class="t10-admin-body">
        <form method="post">
            <?php echo Form::getTokenInput(); ?>
            <div class="form-group">
                <label style="display:flex;gap:10px;align-items:center;">
                    <input type="checkbox" name="nActive" value="1" <?php echo $active === 1 ? 'checked' : ''; ?>>
                    Aktif
                </label>
            </div>

            <div class="form-group">
                <label>Ana Başlık</label>
                <input class="form-control" type="text" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label>Sıralama</label>
                <select class="form-control" name="sortMode">
                    <option value="newest" <?php echo $sortMode === 'newest' ? 'selected' : ''; ?>>Varsayılan (ID'ye göre)</option>
                    <option value="best_sold" <?php echo $sortMode === 'best_sold' ? 'selected' : ''; ?>>En çok satılan ürün sayısına göre</option>
                </select>
                <small class="form-text text-muted">"En çok satılan" sıralaması txsellkauf tablosundaki satış sayısı toplamına göre hesaplanır.</small>
            </div>
            <div class="form-group">
                <label>Enjeksiyon konumu</label>
                <select class="form-control" name="cInjectMode">
                    <option value="after_configurator" <?php echo $injectMode === 'after_configurator' ? 'selected' : ''; ?>>Konfigurator sonrası (.index-filter-konfigurator)</option>
                    <option value="after_content" <?php echo $injectMode === 'after_content' ? 'selected' : ''; ?>>#content içine (prepend)</option>
                    <option value="after_selector" <?php echo $injectMode === 'after_selector' ? 'selected' : ''; ?>>Custom selector sonrası</option>
                </select>
            </div>
            <div class="form-group">
                <label>Önce / Sonra</label>
                <select class="form-control" name="cInjectPos">
                    <option value="after" <?php echo $injectPos === 'after' ? 'selected' : ''; ?>>Sonrasına ekle</option>
                    <option value="before" <?php echo $injectPos === 'before' ? 'selected' : ''; ?>>Öncesine ekle</option>
                </select>
            </div>
            <div class="form-group">
                <label>Custom selector</label>
                <input class="form-control" type="text" name="cInjectSelector" value="<?php echo htmlspecialchars($injectSelector, ENT_QUOTES, 'UTF-8'); ?>" placeholder="örn: div#content veya .some-class">
            </div>

            <div class="form-group">
                <label>Cache yenileme zamanı (aylık)</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:520px;">
                    <div>
                        <label style="font-weight:600;">Gün (1-28)</label>
                        <input class="form-control" type="number" min="1" max="28" name="nRefreshDom" value="<?php echo (int)$refreshDom; ?>">
                    </div>
                    <div>
                        <label style="font-weight:600;">Saat (0-23)</label>
                        <input class="form-control" type="number" min="0" max="23" name="nRefreshHour" value="<?php echo (int)$refreshHour; ?>">
                    </div>
                </div>
                <small class="form-text text-muted">Belirlediğin gün/saat geldiğinde ve 25+ gün geçtiyse cache otomatik yenilenir. Ayar değişince de otomatik güncellenir.</small>
            </div>

            <div class="t10-tab-actions">
                <button class="btn btn-outline-primary" type="submit" name="t10AddTab" value="1">+ Tab Ekle</button>
            </div>

            <?php for ($i = 0; $i < count($tabs); $i++): $t = $tabs[$i] ?? []; $selected = $selectedByTab[$i] ?? []; ?>
                <div class="t10-tab-card">
                    <div class="t10-tab-title">
                        Tab <?php echo $i + 1; ?>
                        <?php if (count($tabs) > 1): ?>
                            <button class="btn btn-sm btn-link text-danger" type="submit" name="t10DeleteTab" value="<?php echo (int)$i; ?>" style="float:right;padding:0;">Sil</button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <input type="hidden" name="key[]" value="<?php echo htmlspecialchars((string)($t['key'] ?? ('tab' . ($i + 1))), ENT_QUOTES, 'UTF-8'); ?>">
                                <label>Tab Adı</label>
                                <input class="form-control" type="text" name="label[]" value="<?php echo htmlspecialchars((string)($t['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Filtre Anahtar Kelime (opsiyonel)</label>
                                <input class="form-control" type="text" name="keyword[]" value="<?php echo htmlspecialchars((string)($t['keyword'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="örn: wohnmobil">
                            </div>
                            <div class="col-md-12 form-group">
                                <label>Ürün Seçimi (başlık / Artikelnummer / GTIN benzeri numara)</label>
                                <div class="t10-selector" data-selector>
                                    <input class="t10-selected-input" type="hidden" name="articleIds[]" value="<?php echo htmlspecialchars(implode(',', t10NormalizeArticleIds($t['articleIds'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="t10-selected" data-selected-list>
                                        <?php foreach ($selected as $item): ?>
                                            <span class="t10-chip" data-id="<?php echo (int)$item['id']; ?>" data-name="<?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?>" data-artnr="<?php echo htmlspecialchars((string)($item['artNr'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-barcode="<?php echo htmlspecialchars((string)($item['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                #<?php echo (int)$item['id']; ?> - <?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                <button type="button" data-remove-id="<?php echo (int)$item['id']; ?>">x</button>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="t10-search-wrap">
                                        <input type="text" class="form-control" data-search-input placeholder="Örn: 4070522701958, BC-Serie veya ürün adı">
                                        <div class="t10-results" data-results></div>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Seçilen ürünler bu tabda tam bu sırayla gösterilir. En fazla 10 ürün seçin.</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <button class="btn btn-primary" type="submit" name="t10Save" value="1">Kaydet</button>
                <button class="btn btn-outline-secondary" type="submit" name="t10RefreshCache" value="1">Cache şimdi yenile</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var selectors = document.querySelectorAll('[data-selector]');
    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }
    selectors.forEach(function (root) {
        var hidden = root.querySelector('.t10-selected-input');
        var list = root.querySelector('[data-selected-list]');
        var search = root.querySelector('[data-search-input]');
        var results = root.querySelector('[data-results]');
        var selected = [];

        function readInitial() {
            selected = [];
            list.querySelectorAll('.t10-chip').forEach(function (chip) {
                selected.push({
                    id: parseInt(chip.getAttribute('data-id') || '0', 10),
                    name: chip.getAttribute('data-name') || '',
                    artNr: chip.getAttribute('data-artnr') || '',
                    barcode: chip.getAttribute('data-barcode') || ''
                });
            });
            syncHidden();
        }

        function syncHidden() {
            hidden.value = selected.map(function (x) { return x.id; }).join(',');
        }

        function renderSelected() {
            list.innerHTML = '';
            selected.forEach(function (item) {
                var chip = document.createElement('span');
                chip.className = 't10-chip';
                chip.setAttribute('data-id', String(item.id));
                chip.setAttribute('data-name', item.name);
                chip.setAttribute('data-artnr', item.artNr || '');
                chip.setAttribute('data-barcode', item.barcode || '');
                chip.innerHTML = '#' + item.id + ' - ' + item.name + ' <button type="button" data-remove-id="' + item.id + '">x</button>';
                list.appendChild(chip);
            });
            syncHidden();
        }

        function addItem(item) {
            if (!item || !item.id) return;
            if (selected.some(function (x) { return x.id === item.id; })) return;
            if (selected.length >= 10) return;
            selected.push(item);
            renderSelected();
        }

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove-id]');
            if (!btn) return;
            var id = parseInt(btn.getAttribute('data-remove-id') || '0', 10);
            selected = selected.filter(function (x) { return x.id !== id; });
            renderSelected();
        });

        function renderResults(items) {
            results.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
                results.style.display = 'none';
                return;
            }
            items.forEach(function (item) {
                var row = document.createElement('div');
                row.className = 't10-result';
                row.innerHTML = '<strong>' + item.name + '</strong><small>#' + item.id + (item.artNr ? ' | ArtNr: ' + item.artNr : '') + (item.barcode ? ' | GTIN: ' + item.barcode : '') + '</small>';
                row.addEventListener('click', function () {
                    addItem(item);
                    results.style.display = 'none';
                    search.value = '';
                });
                results.appendChild(row);
            });
            results.style.display = 'block';
        }

        var runSearch = debounce(function () {
            var q = (search.value || '').trim();
            if (q.length < 2) {
                results.style.display = 'none';
                return;
            }
            var url = new URL(window.location.href);
            url.searchParams.set('t10Search', '1');
            url.searchParams.set('q', q);
            fetch(url.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (txt) {
                    var m = txt.match(/__T10JSON_START__([\s\S]*?)__T10JSON_END__/);
                    if (!m || !m[1]) {
                        renderResults([]);
                        return;
                    }
                    var json = JSON.parse(m[1]);
                    renderResults((json && json.items) ? json.items : []);
                })
                .catch(function () { results.style.display = 'none'; });
        }, 250);

        search.addEventListener('input', runSearch);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runSearch();
            }
        });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                results.style.display = 'none';
            }
        });
        readInitial();
    });
})();
</script>

