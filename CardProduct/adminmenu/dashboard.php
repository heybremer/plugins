<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\CardProduct\CardProductDatabase;

require_once dirname(__DIR__) . '/src/CardProductDatabase.php';
require_once dirname(__DIR__) . '/src/CardProductArticleHelper.php';

$db = Shop::Container()->getDB();
CardProductDatabase::ensureAll();
$message = '';
$shopUrl = rtrim((string)Shop::getURL(), '/');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cpAjaxArticleImages'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!Form::validateToken()) {
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $kAjax = (int)($_POST['kArtikel'] ?? 0);
    $urls = \Plugin\CardProduct\CardProductArticleHelper::collectImageUrls($db, $shopUrl, $kAjax, 24);
    echo json_encode(['ok' => true, 'images' => $urls], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Settings load
$cpSettings = (object)[
    'cInjectMode' => 'after_configurator',
    'cInjectPos' => 'after',
    'cInjectSelector' => '',
    'nRefreshDom' => 1,
    'nRefreshHour' => 3,
];
try {
    $sRow = $db->getSingleObject("SELECT * FROM twomosi_cardproduct_settings ORDER BY kID ASC LIMIT 1");
    if ($sRow !== null) {
        $cpSettings = $sRow;
    }
} catch (\Throwable $e) {
    // ignore
}

function cpBuildCategoryUrl(string $shopUrl, int $kWarengruppe, string $catSeo): string
{
    if ($catSeo !== '') {
        return $shopUrl . '/' . ltrim($catSeo, '/');
    }
    if ($kWarengruppe > 0) {
        return $shopUrl . '/navi.php?k=' . $kWarengruppe;
    }
    return '#';
}

/**
 * @return array<int, string>
 */
function cpCollectImagesFromPost(): array
{
    $images = [];
    $arr = $_POST['cpImages'] ?? [];
    if (is_array($arr)) {
        foreach ($arr as $v) {
            $u = trim((string)$v);
            if ($u !== '') {
                $images[] = $u;
            }
        }
    }
    $legacy = trim((string)($_POST['cImage'] ?? ''));
    if ($legacy !== '' && !in_array($legacy, $images, true)) {
        array_unshift($images, $legacy);
    }
    return array_values(array_unique($images));
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['cpSaveSettings'])) {
            $mode = trim((string)($_POST['cInjectMode'] ?? 'after_configurator'));
            $pos = trim((string)($_POST['cInjectPos'] ?? 'after'));
            $selector = trim((string)($_POST['cInjectSelector'] ?? ''));
            $dom = (int)($_POST['nRefreshDom'] ?? 1);
            $hour = (int)($_POST['nRefreshHour'] ?? 3);
            if ($dom < 1 || $dom > 28) { $dom = 1; }
            if ($hour < 0 || $hour > 23) { $hour = 3; }
            if (!in_array($mode, ['after_configurator', 'after_content', 'after_selector'], true)) {
                $mode = 'after_configurator';
            }
            if (!in_array($pos, ['before', 'after'], true)) {
                $pos = 'after';
            }
            $existing = $db->getSingleObject("SELECT kID FROM twomosi_cardproduct_settings LIMIT 1");
            if ($existing === null) {
                $row = new stdClass();
                $row->cInjectMode = $mode;
                $row->cInjectPos = $pos;
                $row->cInjectSelector = $selector;
                $row->nRefreshDom = $dom;
                $row->nRefreshHour = $hour;
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_cardproduct_settings', $row);
            } else {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_cardproduct_settings
                        SET cInjectMode = :m,
                            cInjectPos = :p,
                            cInjectSelector = :s,
                            nRefreshDom = :dom,
                            nRefreshHour = :hr,
                            dUpdated = NOW()
                      WHERE kID = :id",
                    [
                        'id' => (int)$existing->kID,
                        'm' => $mode,
                        'p' => $pos,
                        's' => $selector,
                        'dom' => $dom,
                        'hr' => $hour,
                    ]
                );
            }
            $message = 'Ayarlar kaydedildi.';
        }

        if (!empty($_POST['cpAddCatArt'])) {
            $kk = (int)($_POST['cpMapKategorie'] ?? 0);
            $ka = (int)($_POST['cpMapArtikel'] ?? 0);
            if ($kk > 0 && $ka > 0) {
                try {
                    $rowMap = new stdClass();
                    $rowMap->kKategorie = $kk;
                    $rowMap->kArtikel = $ka;
                    $rowMap->nSort = (int)($_POST['cpMapSort'] ?? 100);
                    $rowMap->dCreated = date('Y-m-d H:i:s');
                    $db->insert('twomosi_cardproduct_cat_artikel', $rowMap);
                    $message = 'Kategori / ürün eşlemesi eklendi.';
                } catch (\Throwable $e) {
                    $message = 'Eşleme eklenemedi (aynı kategori+ürün veya DB): ' . $e->getMessage();
                }
            } else {
                $message = 'Kategori ve ürün ID gerekli.';
            }
        }

        if (!empty($_POST['cpDelCatArt']) && isset($_POST['cpMapID'])) {
            $db->executeQueryPrepared(
                'DELETE FROM twomosi_cardproduct_cat_artikel WHERE kID = :id',
                ['id' => (int)$_POST['cpMapID']]
            );
            $message = 'Eşleme silindi.';
        }

        if (!empty($_POST['cpRefreshCache'])) {
            // Build cache now (current + default language)
            $langID = (int)Shop::getLanguageID();
            $tryLangs = [$langID];
            if ($langID !== 1) { $tryLangs[] = 1; }
            foreach ($tryLangs as $lid) {
                $rows2 = $db->getObjects(
                    "SELECT kID, kArtikel, cTitle, cImage, cImages, cUrl, nSort
                       FROM twomosi_cardproduct
                      WHERE nActive = 1
                      ORDER BY nSort ASC, kID ASC"
                ) ?: [];

                $needTitle = [];
                foreach ($rows2 as $r) {
                    $t = trim((string)($r->cTitle ?? ''));
                    $kA = (int)($r->kArtikel ?? 0);
                    if ($t === '' && $kA > 0) { $needTitle[$kA] = true; }
                }
                $titles = [];
                if ($needTitle !== []) {
                    $ids = implode(',', array_map('intval', array_keys($needTitle)));
                    $tRows = $db->getObjects(
                        "SELECT ts.kArtikel, ts.cName
                           FROM tartikelsprache ts
                          WHERE ts.kSprache = :lid
                            AND ts.kArtikel IN (" . $ids . ")",
                        ['lid' => $lid]
                    );
                    if (is_array($tRows)) {
                        foreach ($tRows as $tr) {
                            $kA = (int)($tr->kArtikel ?? 0);
                            $nm = trim((string)($tr->cName ?? ''));
                            if ($kA > 0 && $nm !== '') { $titles[$kA] = $nm; }
                        }
                    }
                }

                $cards2 = [];
                foreach ($rows2 as $r) {
                    $title = trim((string)($r->cTitle ?? ''));
                    $kArtikel = (int)($r->kArtikel ?? 0);
                    if ($title === '' && $kArtikel > 0 && isset($titles[$kArtikel])) {
                        $title = (string)$titles[$kArtikel];
                    }
                    $url   = trim((string)($r->cUrl ?? ''));
                    $imgList = \Plugin\CardProduct\CardProductArticleHelper::resolveImagesForCacheRow($db, $shopUrl, $r);
                    $img = $imgList[0] ?? '';
                    if ($title === '') { $title = 'Produkt'; }
                    if ($url === '') { $url = '#'; }
                    $cards2[] = [
                        'id' => (int)($r->kID ?? 0),
                        'kArtikel' => $kArtikel,
                        'title' => $title,
                        'img' => $img,
                        'imgs' => $imgList,
                        'url' => $url,
                    ];
                }

                $json2 = json_encode($cards2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($json2)) { continue; }

                $db->executeQuery(
                    "CREATE TABLE IF NOT EXISTS twomosi_cardproduct_cache (
                        kID INT AUTO_INCREMENT PRIMARY KEY,
                        kSprache INT NOT NULL,
                        cCardsJson LONGTEXT NOT NULL,
                        dUpdated DATETIME NOT NULL,
                        UNIQUE KEY uq_lang (kSprache),
                        KEY ix_updated (dUpdated)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $db->executeQuery(
                    "CREATE TABLE IF NOT EXISTS twomosi_cardproduct_meta (
                        cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                        cValue VARCHAR(255) NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_cardproduct_cache (kSprache, cCardsJson, dUpdated)
                     VALUES (:lid, :j, NOW())
                     ON DUPLICATE KEY UPDATE cCardsJson = VALUES(cCardsJson), dUpdated = NOW()",
                    ['lid' => $lid, 'j' => $json2]
                );
                $key = 'cards_last_refresh_lang_' . $lid;
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_cardproduct_meta (cKey, cValue)
                     VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)",
                    ['k' => $key, 'v' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]
                );
            }
            $message = 'Card cache yenilendi.';
        }
        if (!empty($_POST['cpQuickAdd'])) {
            $kArtikel = (int)($_POST['kArtikel'] ?? 0);
            $title    = trim((string)($_POST['cTitle'] ?? ''));
            $url      = trim((string)($_POST['cUrl'] ?? ''));
            $sort     = (int)($_POST['nSort'] ?? 100);
            $images   = cpCollectImagesFromPost();
            if ($kArtikel > 0 && count($images) === 0) {
                $images = \Plugin\CardProduct\CardProductArticleHelper::collectImageUrls($db, $shopUrl, $kArtikel, 24);
            }
            $firstImg = $images[0] ?? '';

            $db->executeQueryPrepared(
                "INSERT INTO twomosi_cardproduct
                    (kArtikel, cTitle, cImage, cImages, cUrl, nSort, nActive, dCreated, dUpdated)
                 VALUES
                    (:kArtikel, :cTitle, :cImage, :cImages, :cUrl, :nSort, 1, NOW(), NOW())",
                [
                    'kArtikel' => $kArtikel > 0 ? $kArtikel : null,
                    'cTitle'   => $title,
                    'cImage'   => $firstImg,
                    'cImages'  => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'cUrl'     => $url,
                    'nSort'    => $sort,
                ]
            );
            $message = 'Ürün karta eklendi.';
        }

        if (!empty($_POST['cpSave']) && !empty($_POST['kID'])) {
            $kID      = (int)$_POST['kID'];
            $kArtikel = (int)($_POST['kArtikel'] ?? 0);
            $title    = trim((string)($_POST['cTitle'] ?? ''));
            $url      = trim((string)($_POST['cUrl'] ?? ''));
            $sort     = (int)($_POST['nSort'] ?? 100);
            $active   = !empty($_POST['nActive']) ? 1 : 0;
            $images   = cpCollectImagesFromPost();
            if ($kArtikel > 0 && count($images) === 0) {
                $images = \Plugin\CardProduct\CardProductArticleHelper::collectImageUrls($db, $shopUrl, $kArtikel, 24);
            }
            $firstImg = $images[0] ?? '';

            $db->executeQueryPrepared(
                "UPDATE twomosi_cardproduct
                    SET kArtikel = :kArtikel,
                        cTitle = :cTitle,
                        cImage = :cImage,
                        cImages = :cImages,
                        cUrl = :cUrl,
                        nSort = :nSort,
                        nActive = :nActive,
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID'      => $kID,
                    'kArtikel' => $kArtikel > 0 ? $kArtikel : null,
                    'cTitle'   => $title,
                    'cImage'   => $firstImg,
                    'cImages'  => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'cUrl'     => $url,
                    'nSort'    => $sort,
                    'nActive'  => $active,
                ]
            );
            $message = 'Kart güncellendi.';
        }

        if (!empty($_POST['cpDelete']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $db->executeQueryPrepared(
                "DELETE FROM twomosi_cardproduct WHERE kID = :kID",
                ['kID' => $kID]
            );
            $message = 'Kart silindi.';
        }
    } catch (\Throwable $e) {
        $message = 'İşlem başarısız: ' . $e->getMessage();
    }
}

$cpLangId = (int)Shop::getLanguageID();
$cpCategories = [];
try {
    $cpCategories = $db->getObjects(
        "SELECT k.kKategorie, TRIM(COALESCE(ks.cName, '')) AS cName
           FROM tkategorie k
           LEFT JOIN tkategoriesprache ks ON ks.kKategorie = k.kKategorie AND ks.kSprache = :lid
       ORDER BY cName ASC, k.kKategorie ASC",
        ['lid' => $cpLangId]
    ) ?: [];
} catch (\Throwable $e) {
    $cpCategories = [];
}

$cpCatArtRows = [];
try {
    $cpCatArtRows = $db->getObjects(
        "SELECT m.kID, m.kKategorie, m.kArtikel, m.nSort,
                TRIM(COALESCE(ks.cName, '')) AS cName
           FROM twomosi_cardproduct_cat_artikel m
           LEFT JOIN tkategoriesprache ks ON ks.kKategorie = m.kKategorie AND ks.kSprache = :lid
       ORDER BY m.nSort ASC, m.kID ASC",
        ['lid' => $cpLangId]
    ) ?: [];
} catch (\Throwable $e) {
    $cpCatArtRows = [];
}

$rows = [];
$searchQ = trim((string)($_GET['cpq'] ?? ($_POST['cpq'] ?? '')));
$searchRows = [];
try {
    $rows = $db->getObjects(
        "SELECT kID, kArtikel, cTitle, cImage, cImages, cUrl, nSort, nActive, dUpdated
           FROM twomosi_cardproduct
          ORDER BY nSort ASC, kID ASC"
    );

    if ($searchQ !== '') {
        $qRaw    = trim($searchQ);
        $likeEsc = addslashes('%' . $qRaw . '%');
        $exactID = ctype_digit($qRaw) ? (int)$qRaw : -1;

        $searchRows = $db->getObjects(
            "SELECT DISTINCT
                a.kArtikel,
                a.kWarengruppe,
                COALESCE(NULLIF(a.cName, ''), tsAny.cName, '') AS cName,
                COALESCE(a.cArtNr, '') AS cArtNr,
                COALESCE(seoCat.cSeo, '') AS cCatSeo
             FROM tartikel a
             LEFT JOIN (
                SELECT s1.kArtikel, MIN(s1.cName) AS cName
                FROM tartikelsprache s1
                GROUP BY s1.kArtikel
             ) tsAny ON tsAny.kArtikel = a.kArtikel
             LEFT JOIN tseo seoCat
                    ON seoCat.kKey = a.kWarengruppe
                   AND seoCat.cKey = 'kKategorie'
             WHERE (
                a.kArtikel = " . $exactID . "
                OR a.cArtNr LIKE '" . $likeEsc . "'
                OR a.cName LIKE '" . $likeEsc . "'
                OR tsAny.cName LIKE '" . $likeEsc . "'
             )
             ORDER BY a.kArtikel DESC
             LIMIT 60"
        );
    }
} catch (\Throwable $e) {
    if ($message === '') {
        $message = 'Arama hatası: ' . $e->getMessage();
    }
}

$cacheInfo = (object)['p' => 0, 'last' => ''];
try {
    $langID = (int)Shop::getLanguageID();
    $row = $db->getSingleObject("SELECT COUNT(*) AS cnt FROM twomosi_cardproduct_cache WHERE kSprache = :lid", ['lid' => $langID]);
    $cacheInfo->p = $row !== null ? (int)($row->cnt ?? 0) : 0;
    $row = $db->getSingleObject("SELECT cValue FROM twomosi_cardproduct_meta WHERE cKey = :k LIMIT 1", ['k' => 'cards_last_refresh_lang_' . $langID]);
    $cacheInfo->last = $row !== null ? (string)($row->cValue ?? '') : '';
} catch (\Throwable $e) {}
?>

<div style="padding:10px 0;">
    <h2 style="margin:0 0 12px;"><?= htmlspecialchars((string)($message ?: 'CardProduct Dashboard')) ?></h2>

    <div style="margin-bottom:16px; padding:12px; border:1px solid rgba(0,0,0,.08); border-radius:10px; background:#fff;">
        <h3 style="margin-top:0;">Ayarlar</h3>
        <form method="post" style="display:grid; gap:10px; max-width:820px;">
            <?= Form::getTokenInput(); ?>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div>
                    <label>Enjeksiyon konumu</label><br>
                    <?php $m = (string)($cpSettings->cInjectMode ?? 'after_configurator'); ?>
                    <select name="cInjectMode" style="width:100%; padding:8px 10px;">
                        <option value="after_configurator" <?= $m === 'after_configurator' ? 'selected' : '' ?>>Konfigurator sonrası (.index-filter-konfigurator)</option>
                        <option value="after_content" <?= $m === 'after_content' ? 'selected' : '' ?>>#content içine (prepend)</option>
                        <option value="after_selector" <?= $m === 'after_selector' ? 'selected' : '' ?>>Custom selector sonrası</option>
                    </select>
                </div>
                <div>
                    <label>Önce / Sonra</label><br>
                    <?php $p = (string)($cpSettings->cInjectPos ?? 'after'); ?>
                    <select name="cInjectPos" style="width:100%; padding:8px 10px;">
                        <option value="after" <?= $p === 'after' ? 'selected' : '' ?>>Sonrasına ekle</option>
                        <option value="before" <?= $p === 'before' ? 'selected' : '' ?>>Öncesine ekle</option>
                    </select>
                </div>
                <div>
                    <label>Custom selector</label><br>
                    <input type="text" name="cInjectSelector" value="<?= htmlspecialchars((string)($cpSettings->cInjectSelector ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="örn: div#content veya .some-class" style="width:100%; padding:8px 10px;">
                </div>
                <div>
                    <label>Aylık kontrol günü (1-28)</label><br>
                    <input type="number" min="1" max="28" name="nRefreshDom"
                           value="<?= (int)($cpSettings->nRefreshDom ?? 1) ?>"
                           style="width:100%; padding:8px 10px;">
                </div>
                <div>
                    <label>Saat (0-23)</label><br>
                    <input type="number" min="0" max="23" name="nRefreshHour"
                           value="<?= (int)($cpSettings->nRefreshHour ?? 3) ?>"
                           style="width:100%; padding:8px 10px;">
                </div>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="submit" name="cpSaveSettings" value="1" style="padding:10px 14px; width: fit-content;">Kaydet</button>
                <button type="submit" name="cpRefreshCache" value="1" style="padding:10px 14px; width: fit-content;">Cache şimdi yenile</button>
                <div style="align-self:center; opacity:.8;">
                    Cache: <?= (int)$cacheInfo->p ?> / Son: <?= htmlspecialchars((string)$cacheInfo->last, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </form>
    </div>

    <div style="margin-bottom:16px; padding:12px; border:1px solid rgba(0,0,0,.08); border-radius:10px; background:#fff;">
        <h3 style="margin-top:0;">Kategorie → Produkttyp (Startseiten-Liste)</h3>
        <p style="margin:0 0 12px; opacity:.85; max-width:720px;">Unter der Überschrift erscheint nur die einklappbare Zeile mit Kategorie und Produkttyp (Merkmal). Ordnen Sie hier zu, welche Artikel-IDs zu welcher Shop-Kategorie gehören.</p>
        <form method="post" style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; margin-bottom:12px;">
            <?= Form::getTokenInput(); ?>
            <div>
                <label>Kategorie</label><br>
                <select name="cpMapKategorie" style="min-width:220px; padding:8px 10px;">
                    <option value="0">— wählen —</option>
                    <?php foreach ($cpCategories as $c): ?>
                        <?php
                        $cn = trim((string)($c->cName ?? ''));
                        $cl = $cn !== '' ? $cn : ('#' . (int)($c->kKategorie ?? 0));
                        ?>
                        <option value="<?= (int)($c->kKategorie ?? 0) ?>"><?= htmlspecialchars($cl, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>kArtikel</label><br>
                <input type="number" name="cpMapArtikel" min="1" placeholder="z. B. 12345" style="width:140px; padding:8px 10px;">
            </div>
            <div>
                <label>Sortierung</label><br>
                <input type="number" name="cpMapSort" value="100" style="width:88px; padding:8px 10px;">
            </div>
            <button type="submit" name="cpAddCatArt" value="1" style="padding:8px 14px;">Hinzufügen</button>
        </form>
        <?php if (!count($cpCatArtRows)): ?>
            <div style="opacity:.75;">Keine Zuordnungen.</div>
        <?php else: ?>
            <table style="width:100%; max-width:920px; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid rgba(0,0,0,.12);">
                        <th style="padding:6px 8px;">Kategorie</th>
                        <th style="padding:6px 8px;">kArtikel</th>
                        <th style="padding:6px 8px;">Produkttyp (Merkmal)</th>
                        <th style="padding:6px 8px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cpCatArtRows as $mar): ?>
                        <?php
                        $ptLabel = \Plugin\CardProduct\CardProductArticleHelper::fetchProdukttypLabelForArticle(
                            $db,
                            $cpLangId,
                            (int)($mar->kArtikel ?? 0)
                        );
                        $mcat = trim((string)($mar->cName ?? ''));
                        $mcatDisp = $mcat !== '' ? $mcat : ('#' . (int)($mar->kKategorie ?? 0));
                        ?>
                        <tr style="border-bottom:1px solid rgba(0,0,0,.06);">
                            <td style="padding:6px 8px;"><?= htmlspecialchars($mcatDisp, ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="padding:6px 8px;"><?= (int)($mar->kArtikel ?? 0) ?></td>
                            <td style="padding:6px 8px;"><?= htmlspecialchars($ptLabel !== '' ? $ptLabel : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="padding:6px 8px;">
                                <form method="post" style="display:inline;">
                                    <?= Form::getTokenInput(); ?>
                                    <input type="hidden" name="cpMapID" value="<?= (int)($mar->kID ?? 0) ?>">
                                    <button type="submit" name="cpDelCatArt" value="1" style="padding:4px 10px; font-size:12px;">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:16px; padding:12px; border:1px solid rgba(0,0,0,.08); border-radius:8px;">
        <h3 style="margin-top:0;">Ürün Ara ve + ile Ekle</h3>
        <form method="post" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
            <?= Form::getTokenInput(); ?>
            <input type="text" name="cpq" value="<?= htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8') ?>" placeholder="Artikel ID veya ürün adı yazın" style="width:min(520px,100%); padding:8px 10px;">
            <button type="submit" style="padding:8px 14px;">Ara</button>
        </form>

        <?php if ($searchQ !== ''): ?>
            <?php if (!count($searchRows)): ?>
                <div>Sonuç bulunamadı.</div>
            <?php else: ?>
                <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px;">
                    <?php foreach ($searchRows as $p): ?>
                        <?php
                        $kArtikel = (int)($p->kArtikel ?? 0);
                        $kWarengruppe = (int)($p->kWarengruppe ?? 0);
                        $name = trim((string)($p->cName ?? ''));
                        if ($name === '') {
                            $name = 'Ürün #' . $kArtikel;
                        }
                        $catSeo = trim((string)($p->cCatSeo ?? ''));
                        $categoryUrl = cpBuildCategoryUrl($shopUrl, $kWarengruppe, $catSeo);
                        $quickImages = $kArtikel > 0
                            ? array_slice(\Plugin\CardProduct\CardProductArticleHelper::collectImageUrls($db, $shopUrl, $kArtikel, 12), 0, 12)
                            : [];
                        ?>
                        <div style="border:1px solid rgba(0,0,0,.08); border-radius:10px; overflow:hidden; background:#fff;">
                            <div style="aspect-ratio:16/10; background:#f5f5f5; display:grid; place-items:center; overflow:hidden;">
                                <?php if (!empty($quickImages[0])): ?>
                                    <img src="<?= htmlspecialchars($quickImages[0], ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                <?php else: ?>
                                    <span style="color:rgba(0,0,0,.45); font-weight:700; padding:8px; text-align:center;">Görsel yok (kArtikel)</span>
                                <?php endif; ?>
                            </div>
                            <div style="padding:10px;">
                                <div style="font-size:12px; color:rgba(0,0,0,.6); margin-bottom:4px;">#<?= $kArtikel ?><?= !empty($p->cArtNr) ? ' · ' . htmlspecialchars((string)$p->cArtNr, ENT_QUOTES, 'UTF-8') : '' ?></div>
                                <div style="font-weight:700; line-height:1.35; min-height:36px; margin-bottom:8px;"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
                                <form method="post" style="display:flex; gap:8px; align-items:center;">
                                    <?= Form::getTokenInput(); ?>
                                    <input type="hidden" name="kArtikel" value="<?= $kArtikel ?>">
                                    <input type="hidden" name="cTitle" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="cUrl" value="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="nSort" value="100">
                                    <?php if (count($quickImages) === 0): ?>
                                        <input type="hidden" name="cpImages[]" value="">
                                    <?php else: ?>
                                        <?php foreach ($quickImages as $qu): ?>
                                            <input type="hidden" name="cpImages[]" value="<?= htmlspecialchars($qu, ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <button type="submit" name="cpQuickAdd" value="1" style="width:32px;height:32px;border-radius:8px;border:0;background:#197149;color:#fff;font-size:20px;line-height:1;cursor:pointer;" title="Bu ürünü karta ekle">+</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div style="padding:12px; border:1px solid rgba(0,0,0,.08); border-radius:8px;">
        <h3 style="margin-top:0;">Kartlar</h3>
        <div style="display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px;">
            <?php foreach ($rows as $r): ?>
                <?php
                $existingImages = [];
                $imgJson = trim((string)($r->cImages ?? ''));
                if ($imgJson !== '') {
                    $dec = json_decode($imgJson, true);
                    if (is_array($dec)) {
                        foreach ($dec as $it) {
                            $u = trim((string)$it);
                            if ($u !== '') {
                                $existingImages[] = $u;
                            }
                        }
                    }
                }
                if (count($existingImages) === 0 && !empty($r->cImage)) {
                    $existingImages[] = (string)$r->cImage;
                }
                $existingImages = array_slice(array_values(array_unique($existingImages)), 0, 12);
                if (count($existingImages) === 0 && (int)($r->kArtikel ?? 0) > 0) {
                    $existingImages = array_slice(
                        \Plugin\CardProduct\CardProductArticleHelper::collectImageUrls($db, $shopUrl, (int)$r->kArtikel, 12),
                        0,
                        12
                    );
                }
                $cpMain0 = $existingImages[0] ?? '';
                $cpMainSrc = $cpMain0 !== ''
                    ? htmlspecialchars($cpMain0, ENT_QUOTES, 'UTF-8')
                    : 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250"><rect fill="#f0f0f0" width="100%" height="100%"/></svg>');
                ?>
                <div class="cp-admin-card" style="border:1px solid rgba(0,0,0,.1); border-radius:10px; background:#fff; overflow:hidden;">
                    <div class="cp-card-main-preview" style="aspect-ratio:16/10; background:#f5f5f5; display:grid; place-items:center; overflow:hidden;">
                        <img class="cp-card-main-img" src="<?= $cpMainSrc ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                    <div style="padding:10px;">
                        <div style="font-size:12px; color:rgba(0,0,0,.6);">#<?= (int)$r->kID ?> · kArtikel: <?= (int)($r->kArtikel ?? 0) ?></div>
                        <div style="font-weight:700; line-height:1.35; min-height:36px; margin:6px 0 8px;"><?= htmlspecialchars((string)($r->cTitle ?? 'Ürün'), ENT_QUOTES, 'UTF-8') ?></div>
                        <details>
                            <summary style="cursor:pointer; font-weight:700;">Düzenle</summary>
                            <form method="post" style="margin-top:10px; display:grid; gap:8px;" class="cp-card-form" data-kid="<?= (int)$r->kID ?>">
                                <?= Form::getTokenInput(); ?>
                                <input type="hidden" name="kID" value="<?= (int)$r->kID ?>">
                                <div><label>Ürün ID (kArtikel)</label><br><input type="number" name="kArtikel" value="<?= (int)($r->kArtikel ?? 0) ?>" min="0" style="width:100%;"></div>
                                <div><label>Başlık</label><br><input type="text" name="cTitle" value="<?= htmlspecialchars((string)($r->cTitle ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:100%;"></div>
                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    <span>Görsel URL'leri (çoklu)</span>
                                    <button type="button" class="cp-load-article-images" style="padding:6px 10px; font-size:12px;">Artikel görsellerini yükle</button>
                                </div>
                                <?php
                                $cpEmptyThumb = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect fill="#fafafa" width="100%" height="100%"/><rect fill="none" stroke="#ddd" x="0.5" y="0.5" width="47" height="47"/></svg>');
                                ?>
                                <?php for ($i = 0; $i < 12; $i++): ?>
                                    <?php $rowUrl = (string)($existingImages[$i] ?? ''); ?>
                                    <?php $thumbSrc = $rowUrl !== '' ? htmlspecialchars($rowUrl, ENT_QUOTES, 'UTF-8') : $cpEmptyThumb; ?>
                                    <div class="cp-img-row" style="display:flex; gap:8px; align-items:center;">
                                        <img class="cp-row-thumb" src="<?= $thumbSrc ?>" alt="" width="48" height="48" style="flex-shrink:0; width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid rgba(0,0,0,.08); background:#fafafa;">
                                        <input type="text" class="cp-img-url" name="cpImages[]" value="<?= htmlspecialchars($rowUrl, ENT_QUOTES, 'UTF-8') ?>" placeholder="https://..." style="flex:1; min-width:0;">
                                        <button type="button" class="cp-apply-primary" data-index="<?= $i ?>" style="flex-shrink:0; padding:6px 10px; font-size:12px;">Uygula</button>
                                    </div>
                                <?php endfor; ?>
                                <div><label>Link URL</label><br><input type="text" name="cUrl" value="<?= htmlspecialchars((string)($r->cUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:100%;" required></div>
                                <div><label>Sıra</label><br><input type="number" name="nSort" value="<?= (int)($r->nSort ?? 100) ?>" style="width:100%;"></div>
                                <div><label><input type="checkbox" name="nActive" value="1" <?= !empty($r->nActive) ? 'checked' : '' ?>> Aktif</label></div>
                                <div style="display:flex; gap:8px;">
                                    <button type="submit" name="cpSave" value="1" style="padding:8px 14px;">Kaydet</button>
                                    <button type="submit" name="cpDelete" value="1" style="padding:8px 14px; background:#b00020; color:#fff; border:0;">Sil</button>
                                </div>
                            </form>
                        </details>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<script>
(function () {
  var EMPTY_THUMB = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect fill="#fafafa" width="100%" height="100%"/><rect fill="none" stroke="#ddd" x="0.5" y="0.5" width="47" height="47"/></svg>');
  var EMPTY_MAIN = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="250"><rect fill="#f0f0f0" width="100%" height="100%"/></svg>');
  function getToken() {
    var el = document.querySelector('input[name="jtl_token"]');
    return el ? el.value : '';
  }
  function syncRowThumb(input) {
    var row = input.closest('.cp-img-row');
    if (!row) return;
    var th = row.querySelector('.cp-row-thumb');
    if (!th) return;
    var u = (input.value || '').trim();
    th.src = u || EMPTY_THUMB;
  }
  function syncMainPreview(form) {
    var card = form.closest('.cp-admin-card');
    if (!card) return;
    var first = form.querySelector('input[name="cpImages[]"]');
    var u = first ? (first.value || '').trim() : '';
    var main = card.querySelector('.cp-card-main-img');
    if (main) main.src = u || EMPTY_MAIN;
  }
  function syncAllThumbs(form) {
    form.querySelectorAll('.cp-img-url').forEach(function (inp) { syncRowThumb(inp); });
    syncMainPreview(form);
  }
  document.querySelectorAll('.cp-card-form').forEach(function (form) {
    form.addEventListener('input', function (ev) {
      if (ev.target && ev.target.classList && ev.target.classList.contains('cp-img-url')) {
        syncRowThumb(ev.target);
        syncMainPreview(form);
      }
    });
  });
  document.querySelectorAll('.cp-apply-primary').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var idx = parseInt(btn.getAttribute('data-index') || '0', 10);
      var form = btn.closest('form');
      if (!form) return;
      var inputs = form.querySelectorAll('input[name="cpImages[]"]');
      var vals = Array.prototype.map.call(inputs, function (inp) { return (inp.value || '').trim(); });
      if (idx < 0 || idx >= vals.length || !vals[idx]) return;
      var picked = vals[idx];
      vals.splice(idx, 1);
      vals.unshift(picked);
      for (var i = 0; i < inputs.length; i++) {
        inputs[i].value = vals[i] || '';
      }
      syncAllThumbs(form);
    });
  });
  document.querySelectorAll('.cp-load-article-images').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('form');
      if (!form) return;
      var kInp = form.querySelector('input[name="kArtikel"]');
      var k = kInp ? parseInt(kInp.value, 10) : 0;
      if (!k) {
        alert('Önce kArtikel girin.');
        return;
      }
      var fd = new FormData();
      fd.append('cpAjaxArticleImages', '1');
      fd.append('kArtikel', String(k));
      fd.append('jtl_token', getToken());
      fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok || !data.images) return;
          var inputs = form.querySelectorAll('input[name="cpImages[]"]');
          var imgs = data.images || [];
          for (var i = 0; i < inputs.length; i++) {
            inputs[i].value = imgs[i] || '';
          }
          syncAllThumbs(form);
        })
        .catch(function () {});
    });
  });
})();
</script>
</div>
