<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

$db = Shop::Container()->getDB();
$message = '';
$shopUrl = rtrim((string)Shop::getURL(), '/');

// Ensure settings table exists (admin context safety)
try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_action_product_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            nActive TINYINT(1) NOT NULL DEFAULT 1,
            cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator',
            cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after',
            cInjectSelector VARCHAR(255) NOT NULL DEFAULT '',
            cTabsJson LONGTEXT NULL,
            dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $colMode = $db->getSingleObject("SHOW COLUMNS FROM twomosi_action_product_settings LIKE 'cInjectMode'");
    if ($colMode === null) {
        $db->executeQuery("ALTER TABLE twomosi_action_product_settings ADD COLUMN cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator' AFTER nActive");
    }
    $colPos = $db->getSingleObject("SHOW COLUMNS FROM twomosi_action_product_settings LIKE 'cInjectPos'");
    if ($colPos === null) {
        $db->executeQuery("ALTER TABLE twomosi_action_product_settings ADD COLUMN cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after' AFTER cInjectMode");
    }
    $colSelector = $db->getSingleObject("SHOW COLUMNS FROM twomosi_action_product_settings LIKE 'cInjectSelector'");
    if ($colSelector === null) {
        $db->executeQuery("ALTER TABLE twomosi_action_product_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT '' AFTER cInjectPos");
    }
} catch (\Throwable $e) {
    // ignore
}

// Coupon list
$couponRows = [];
try {
    $couponRows = $db->getObjects(
        "SELECT kKupon, cName, cCode, fWert, cWertTyp, dGueltigAb, dGueltigBis, cAktiv, cKuponTyp
           FROM tkupon
          WHERE cAktiv = 'Y'
            AND cKuponTyp = 'standard'
          ORDER BY kKupon DESC
          LIMIT 200"
    );
} catch (\Throwable $e) {
    $couponRows = [];
}

/**
 * @return array<int, array<string, mixed>>
 */
if (!function_exists('apDefaultTabs')) {
function apDefaultTabs(): array
{
    return [
        [
            'kArtikel' => 0,
            'tabTitle' => 'Produkt des Monats',
            'badge' => 'Produkt des Monats',
            'countdownIso' => '2026-03-30T23:59:59',
            'name' => 'Lammfell Sitzauflage Sitzbezug Universal Autositzauflage',
            'desc' => 'Ergonomisch, Waschbar & Antiallergisch',
            'urgencyActive' => 1,
            'urgencyText' => 'Nur noch {remain} Stück verfügbar!',
            'couponCode' => '',
            'priceOld' => 199.0,
            'priceNew' => 99.5,
            'totalStock' => 100,
            'soldCount' => 90,
            'buyUrl' => '/',
            'images' => [
                'https://bremer-sitzbezuege.de/media/image/product/335179/lg/lammfell-sitzauflage-sitzbezug-universal-autositzauflage.webp',
                'https://bremer-sitzbezuege.de/media/image/product/335180/lg/lammfell-sitzauflage-sitzbezug-universal-autositzauflage_1.webp',
            ],
        ],
        [
            'kArtikel' => 0,
            'tabTitle' => 'Produkt des Monats',
            'badge' => 'Produkt des Monats',
            'countdownIso' => '2026-02-28T23:59:59',
            'name' => '5 Fenster Einlagig Gardinen mit Aluprofile',
            'desc' => 'Maß Gardinen Set passend für Ihr Fahrzeug',
            'urgencyActive' => 1,
            'urgencyText' => 'Nur noch {remain} Stück verfügbar!',
            'couponCode' => '',
            'priceOld' => 199.0,
            'priceNew' => 99.0,
            'totalStock' => 100,
            'soldCount' => 15,
            'buyUrl' => '/',
            'images' => [
                'https://bremer-sitzbezuege.de/media/image/product/116482/lg/mercedes-gardinenset.webp',
                'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~2.webp',
                'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~4.webp',
                'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~6.webp',
            ],
        ],
    ];
}
}

/**
 * @return array<int, string>
 */
if (!function_exists('apCollectImagesFromPost')) {
function apCollectImagesFromPost(string $prefix): array
{
    $raw = (string)($_POST[$prefix] ?? '');
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = array_filter(array_map('trim', explode("\n", $raw)), static fn($v) => $v !== '');
    return array_values(array_unique($lines));
}
}

if (!function_exists('apAbsUrl')) {
function apAbsUrl(string $shopUrl, string $path): string
{
    $p = trim($path);
    if ($p === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $p)) {
        return $p;
    }
    if (str_starts_with($p, '/')) {
        return $shopUrl . $p;
    }
    return $shopUrl . '/' . ltrim($p, '/');
}
}

/**
 * Stored ISO / free text → value for HTML5 datetime-local (Y-m-d\TH:i:s, step=1).
 */
if (!function_exists('apCountdownIsoToDatetimeLocal')) {
function apCountdownIsoToDatetimeLocal(string $iso): string
{
    $iso = trim($iso);
    if ($iso === '') {
        return '';
    }
    try {
        $d = new \DateTimeImmutable($iso);

        return $d->format('Y-m-d\TH:i:s');
    } catch (\Throwable $e) {
        return '';
    }
}
}

/**
 * POST from datetime-local → normalized ISO for JSON (Y-m-d\TH:i:s).
 */
if (!function_exists('apNormalizeCountdownIsoFromPost')) {
function apNormalizeCountdownIsoFromPost(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    try {
        $d = new \DateTimeImmutable($raw);

        return $d->format('Y-m-d\TH:i:s');
    } catch (\Throwable $e) {
        return $raw;
    }
}
}

if (!function_exists('apBuildJtlProductImageUrl')) {
function apBuildJtlProductImageUrl(string $shopUrl, int $kArtikel, string $cleanName, int $nNr, string $size = 'lg', string $ext = 'jpg'): string
{
    $size = in_array($size, ['xs', 'sm', 'md', 'lg', 'xl'], true) ? $size : 'lg';
    $ext  = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true) ? $ext : 'jpg';
    $suffix = $nNr > 1 ? ('~' . $nNr) : '';
    $cleanName = trim($cleanName);
    if ($cleanName === '') {
        $cleanName = (string)$kArtikel;
    }
    return $shopUrl . '/media/image/product/' . $kArtikel . '/' . $size . '/' . $cleanName . $suffix . '.' . $ext;
}
}

if (!function_exists('apBuildProductUrl')) {
function apBuildProductUrl(string $shopUrl, string $seo, int $kArtikel): string
{
    $seo = trim($seo);
    if ($seo !== '') {
        return $shopUrl . '/' . ltrim($seo, '/');
    }
    if ($kArtikel > 0) {
        return $shopUrl . '/artikel.php?a=' . $kArtikel;
    }
    return '#';
}
}

/**
 * @return array<string, mixed>|null
 */
if (!function_exists('apFetchProductById')) {
function apFetchProductById($db, string $shopUrl, int $kArtikel): ?array
{
    if ($kArtikel <= 0) {
        return null;
    }
    try {
        $p = $db->getSingleObject(
            "SELECT
                a.kArtikel,
                COALESCE(NULLIF(a.cName, ''), tsAny.cName, '') AS cName,
                COALESCE(NULLIF(a.cKurzBeschreibung, ''), '') AS cKurzBeschreibung,
                COALESCE(a.cSeo, '') AS cSeo,
                COALESCE(a.cArtNr, '') AS cArtNr,
                COALESCE(a.cBarcode, '') AS cBarcode
             FROM tartikel a
             LEFT JOIN (
                SELECT s1.kArtikel, MIN(s1.cName) AS cName
                FROM tartikelsprache s1
                GROUP BY s1.kArtikel
             ) tsAny ON tsAny.kArtikel = a.kArtikel
             WHERE a.kArtikel = :kArtikel
             LIMIT 1",
            ['kArtikel' => $kArtikel]
        );
        if ($p === null) {
            return null;
        }

        // Sales price + stock
        $p2 = null;
        try {
            $p2 = $db->getSingleObject(
                "SELECT fLagerbestand, fStandardpreisNetto, fMwSt, fUVP
                   FROM tartikel
                  WHERE kArtikel = :kArtikel
                  LIMIT 1",
                ['kArtikel' => $kArtikel]
            );
        } catch (\Throwable $e) {
            $p2 = null;
        }
        $mwst  = (float)($p2->fMwSt ?? 0.0);
        $uvp   = (float)($p2->fUVP ?? 0.0);
        $stock = (float)($p2->fLagerbestand ?? 0.0);

        $saleNet = 0.0;
        try {
            $vkNettoRow = $db->getSingleObject(
                "SELECT pd.fVKNetto AS fVKNetto
                   FROM tpreis p
                   INNER JOIN tpreisdetail pd ON pd.kPreis = p.kPreis
                  WHERE p.kArtikel = :kArtikel
                    AND p.kKundengruppe = 1
                    AND (p.kKunde IS NULL OR p.kKunde = 0)
                    AND pd.nAnzahlAb = 0
                  LIMIT 1",
                ['kArtikel' => $kArtikel]
            );
            if ($vkNettoRow !== null) {
                $saleNet = (float)($vkNettoRow->fVKNetto ?? 0.0);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        if ($saleNet <= 0) {
            $saleNet = (float)($p2->fStandardpreisNetto ?? 0.0);
        }
        $saleGross = $saleNet > 0 ? round($saleNet * (1.0 + max(0.0, $mwst) / 100.0), 2) : 0.0;
        $cleanName = '';
        try {
            $tmp = new stdClass();
            $tmp->kArtikel = (int)$p->kArtikel;
            $tmp->cName = (string)($p->cName ?? '');
            $tmp->cSeo = (string)($p->cSeo ?? '');
            $tmp->originalSeo = (string)($p->cSeo ?? '');
            $tmp->cArtNr = (string)($p->cArtNr ?? '');
            $tmp->cBarcode = (string)($p->cBarcode ?? '');
            $tmp->customImgName = '';
            $cleanName = \JTL\Media\Image\Product::getCustomName($tmp);
        } catch (\Throwable $e) {
            $cleanName = (string)$kArtikel;
        }

        $imgs = [];
        try {
            $imgRows = $db->getObjects(
                "SELECT nNr
                   FROM tartikelpict
                  WHERE kArtikel = :kArtikel
                  GROUP BY cPfad, nNr
                  ORDER BY nNr ASC
                  LIMIT 12",
                ['kArtikel' => $kArtikel]
            );
            if (is_array($imgRows)) {
                foreach ($imgRows as $ir) {
                    $nr = (int)($ir->nNr ?? 1);
                    $imgs[] = apBuildJtlProductImageUrl($shopUrl, $kArtikel, $cleanName, $nr, 'lg', 'jpg');
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $imgs = array_values(array_unique(array_filter($imgs, static fn($u) => $u !== '')));

        return [
            'kArtikel' => (int)$p->kArtikel,
            'cName' => (string)($p->cName ?? ''),
            'cKurzBeschreibung' => (string)($p->cKurzBeschreibung ?? ''),
            'cSeo' => (string)($p->cSeo ?? ''),
            'cArtNr' => (string)($p->cArtNr ?? ''),
            'cBarcode' => (string)($p->cBarcode ?? ''),
            'buyUrl' => apBuildProductUrl($shopUrl, (string)($p->cSeo ?? ''), (int)$p->kArtikel),
            'images' => $imgs,
            'saleGross' => $saleGross,
            'uvp' => $uvp,
            'stock' => $stock,
        ];
    } catch (\Throwable $e) {
        return null;
    }
}
}

// Load settings
$apActive = 1;
$apInjectMode = 'after_configurator';
$apInjectPos = 'after';
$apInjectSelector = '';
$apTabs = apDefaultTabs();
$row = null;
try {
    $row = $db->getSingleObject("SELECT * FROM twomosi_action_product_settings ORDER BY kID ASC LIMIT 1");
    if ($row !== null) {
        $apActive = (int)($row->nActive ?? 1);
        $apInjectMode = (string)($row->cInjectMode ?? 'after_configurator');
        $apInjectPos = (string)($row->cInjectPos ?? 'after');
        $apInjectSelector = (string)($row->cInjectSelector ?? '');
        $decoded = json_decode((string)($row->cTabsJson ?? ''), true);
        if (is_array($decoded) && count($decoded) > 0) {
            $apTabs = $decoded;
        }
    }
} catch (\Throwable $e) {
    // ignore
}

/**
 * @return array<string, mixed>
 */
if (!function_exists('apNewEmptyTab')) {
function apNewEmptyTab(): array
{
    return [
        'kArtikel' => 0,
        'tabTitle' => 'Produkt des Monats',
        'badge' => 'Produkt des Monats',
        'countdownIso' => '',
        'name' => '',
        'desc' => '',
        'urgencyActive' => 1,
        'urgencyText' => 'Nur noch {remain} Stück verfügbar!',
        'couponCode' => '',
        'priceNew' => 0.0,
        'totalStock' => 0,
        'soldCount' => 0,
        'buyUrl' => '#',
        'images' => [],
    ];
}
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['apAddTab'])) {
            $apTabs[] = apNewEmptyTab();
            $payload = json_encode($apTabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existing = $db->getSingleObject("SELECT kID FROM twomosi_action_product_settings LIMIT 1");
            if ($existing === null) {
                $ins = new stdClass();
                $ins->nActive = $apActive;
                $ins->cInjectMode = $apInjectMode;
                $ins->cInjectPos = $apInjectPos;
                $ins->cInjectSelector = $apInjectSelector;
                $ins->cTabsJson = $payload;
                $ins->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_action_product_settings', $ins);
            } else {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_action_product_settings
                        SET cInjectMode = :cInjectMode,
                            cInjectPos = :cInjectPos,
                            cInjectSelector = :cInjectSelector,
                            cTabsJson = :cTabsJson,
                            dUpdated = NOW()
                      WHERE kID = :kID",
                    [
                        'kID' => (int)$existing->kID,
                        'cInjectMode' => $apInjectMode,
                        'cInjectPos' => $apInjectPos,
                        'cInjectSelector' => $apInjectSelector,
                        'cTabsJson' => $payload,
                    ]
                );
            }
            $message = 'Yeni Tab eklendi.';
        }

        if (!empty($_POST['apDeleteTab'])) {
            $delIdx = (int)($_POST['apDeleteTab'] ?? -1);
            if ($delIdx >= 0 && $delIdx < count($apTabs)) {
                array_splice($apTabs, $delIdx, 1);
                if (count($apTabs) === 0) {
                    $apTabs = [apNewEmptyTab()];
                }
                $payload = json_encode($apTabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $existing = $db->getSingleObject("SELECT kID FROM twomosi_action_product_settings LIMIT 1");
                if ($existing === null) {
                    $ins = new stdClass();
                    $ins->nActive = $apActive;
                    $ins->cInjectMode = $apInjectMode;
                    $ins->cInjectPos = $apInjectPos;
                    $ins->cInjectSelector = $apInjectSelector;
                    $ins->cTabsJson = $payload;
                    $ins->dUpdated = date('Y-m-d H:i:s');
                    $db->insert('twomosi_action_product_settings', $ins);
                } else {
                    $db->executeQueryPrepared(
                        "UPDATE twomosi_action_product_settings
                            SET cInjectMode = :cInjectMode,
                                cInjectPos = :cInjectPos,
                                cInjectSelector = :cInjectSelector,
                                cTabsJson = :cTabsJson,
                                dUpdated = NOW()
                          WHERE kID = :kID",
                        [
                            'kID' => (int)$existing->kID,
                            'cInjectMode' => $apInjectMode,
                            'cInjectPos' => $apInjectPos,
                            'cInjectSelector' => $apInjectSelector,
                            'cTabsJson' => $payload,
                        ]
                    );
                }
                $message = 'Tab silindi.';
            }
        }

        if (!empty($_POST['apFillFromProduct'])) {
            $tabIndex = (int)($_POST['tabIndex'] ?? 1);
            $kArtikel = (int)($_POST['kArtikel'] ?? 0);
            if ($tabIndex < 1 || $tabIndex > count($apTabs)) {
                $tabIndex = 1;
            }
            $prod = apFetchProductById($db, $shopUrl, $kArtikel);
            if ($prod !== null) {
                $apTabs[$tabIndex - 1]['kArtikel'] = (int)($prod['kArtikel'] ?? $kArtikel);
                $apTabs[$tabIndex - 1]['name'] = $prod['cName'] ?? '';
                $apTabs[$tabIndex - 1]['desc'] = $prod['cKurzBeschreibung'] ?? '';
                $apTabs[$tabIndex - 1]['buyUrl'] = $prod['buyUrl'] ?? '#';
                if (!empty($prod['images']) && is_array($prod['images'])) {
                    $apTabs[$tabIndex - 1]['images'] = $prod['images'];
                }

                $saleGross = (float)($prod['saleGross'] ?? 0.0);
                if ($saleGross > 0) {
                    $apTabs[$tabIndex - 1]['priceNew'] = $saleGross;
                }
                $uvp = (float)($prod['uvp'] ?? 0.0);
                if ($saleGross <= 0 && $uvp > 0) {
                    $apTabs[$tabIndex - 1]['priceNew'] = $uvp;
                }
                if ($uvp > 0 && ($saleGross <= 0 || $uvp > $saleGross)) {
                    $apTabs[$tabIndex - 1]['priceOld'] = $uvp;
                }
                $stock = (float)($prod['stock'] ?? 0.0);
                if ($stock >= 0) {
                    $apTabs[$tabIndex - 1]['totalStock'] = (int)round($stock);
                    $curSold = (int)($apTabs[$tabIndex - 1]['soldCount'] ?? 0);
                    $apTabs[$tabIndex - 1]['soldCount'] = max(0, min($curSold, (int)$apTabs[$tabIndex - 1]['totalStock']));
                }

                $payload = json_encode($apTabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $existing = $db->getSingleObject("SELECT kID FROM twomosi_action_product_settings LIMIT 1");
                if ($existing === null) {
                    $ins = new stdClass();
                    $ins->nActive = $apActive;
                    $ins->cInjectMode = $apInjectMode;
                    $ins->cInjectPos = $apInjectPos;
                    $ins->cInjectSelector = $apInjectSelector;
                    $ins->cTabsJson = $payload;
                    $ins->dUpdated = date('Y-m-d H:i:s');
                    $db->insert('twomosi_action_product_settings', $ins);
                } else {
                    $db->executeQueryPrepared(
                        "UPDATE twomosi_action_product_settings
                            SET cInjectMode = :cInjectMode,
                                cInjectPos = :cInjectPos,
                                cInjectSelector = :cInjectSelector,
                                cTabsJson = :cTabsJson,
                                dUpdated = NOW()
                          WHERE kID = :kID",
                        [
                            'kID' => (int)$existing->kID,
                            'cInjectMode' => $apInjectMode,
                            'cInjectPos' => $apInjectPos,
                            'cInjectSelector' => $apInjectSelector,
                            'cTabsJson' => $payload,
                        ]
                    );
                }
                $message = 'Ürün bilgileri Tab ' . $tabIndex . ' için dolduruldu ve kaydedildi.';
            } else {
                $message = 'Ürün bulunamadı (kArtikel=' . $kArtikel . ').';
            }
        }

        if (!empty($_POST['apSaveSettings'])) {
            $apActive = !empty($_POST['nActive']) ? 1 : 0;
            $apInjectMode = trim((string)($_POST['cInjectMode'] ?? 'after_configurator'));
            $apInjectPos = trim((string)($_POST['cInjectPos'] ?? 'after'));
            $apInjectSelector = trim((string)($_POST['cInjectSelector'] ?? ''));
            if (!in_array($apInjectMode, ['after_configurator', 'after_content', 'after_selector'], true)) {
                $apInjectMode = 'after_configurator';
            }
            if (!in_array($apInjectPos, ['before', 'after'], true)) {
                $apInjectPos = 'after';
            }

            $tabs = [];
            $tabTitleArr = $_POST['tabTitle'] ?? [];
            $count = is_array($tabTitleArr) ? count($tabTitleArr) : 0;
            for ($i = 0; $i < $count; $i++) {
                $tabs[] = [
                    'kArtikel' => (int)(($_POST['kArtikel'][$i] ?? 0)),
                    'tabTitle' => trim((string)($_POST['tabTitle'][$i] ?? 'Produkt des Monats')),
                    'badge' => trim((string)($_POST['badge'][$i] ?? 'Produkt des Monats')),
                    'countdownIso' => apNormalizeCountdownIsoFromPost((string)($_POST['countdownIso'][$i] ?? '')),
                    'name' => trim((string)($_POST['name'][$i] ?? '')),
                    'desc' => trim((string)($_POST['desc'][$i] ?? '')),
                    'urgencyActive' => !empty($_POST['urgencyActive'][$i]) ? 1 : 0,
                    'urgencyText' => trim((string)($_POST['urgencyText'][$i] ?? 'Nur noch {remain} Stück verfügbar!')),
                    'couponCode' => trim((string)($_POST['couponCode'][$i] ?? '')),
                    'priceOld' => (float)str_replace(',', '.', (string)($_POST['priceOld'][$i] ?? '0')),
                    'priceNew' => (float)str_replace(',', '.', (string)($_POST['priceNew'][$i] ?? '0')),
                    'totalStock' => (int)($_POST['totalStock'][$i] ?? 0),
                    'soldCount' => (int)($_POST['soldCount'][$i] ?? 0),
                    'buyUrl' => trim((string)($_POST['buyUrl'][$i] ?? '#')),
                    'images' => apCollectImagesFromPost('images_' . $i),
                ];
                if (($tabs[$i]['priceNew'] ?? 0) <= 0 && ($tabs[$i]['priceOld'] ?? 0) > 0) {
                    $tabs[$i]['priceNew'] = (float)$tabs[$i]['priceOld'];
                    $tabs[$i]['priceOld'] = 0.0;
                }
            }
            if (count($tabs) === 0) {
                $tabs = [apNewEmptyTab()];
            }

            $payload = json_encode($tabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existing = $db->getSingleObject("SELECT kID FROM twomosi_action_product_settings LIMIT 1");
            if ($existing === null) {
                $ins = new stdClass();
                $ins->nActive = $apActive;
                $ins->cInjectMode = $apInjectMode;
                $ins->cInjectPos = $apInjectPos;
                $ins->cInjectSelector = $apInjectSelector;
                $ins->cTabsJson = $payload;
                $ins->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_action_product_settings', $ins);
            } else {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_action_product_settings
                        SET nActive = :nActive,
                            cInjectMode = :cInjectMode,
                            cInjectPos = :cInjectPos,
                            cInjectSelector = :cInjectSelector,
                            cTabsJson = :cTabsJson,
                            dUpdated = NOW()
                      WHERE kID = :kID",
                    [
                        'kID' => (int)$existing->kID,
                        'nActive' => $apActive,
                        'cInjectMode' => $apInjectMode,
                        'cInjectPos' => $apInjectPos,
                        'cInjectSelector' => $apInjectSelector,
                        'cTabsJson' => $payload,
                    ]
                );
            }

            $apTabs = $tabs;
            $message = 'Ayarlar kaydedildi.';
        }
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

$searchQ = trim((string)($_GET['apq'] ?? ''));
$searchRows = [];
try {
    if ($searchQ !== '') {
        $qRaw = trim($searchQ);
        $likeEsc = addslashes('%' . $qRaw . '%');
        $exactID = ctype_digit($qRaw) ? (int)$qRaw : -1;

        $searchRows = $db->getObjects(
            "SELECT DISTINCT
                a.kArtikel,
                COALESCE(NULLIF(a.cName, ''), tsAny.cName, '') AS cName,
                COALESCE(a.cArtNr, '') AS cArtNr,
                COALESCE(a.cBarcode, '') AS cBarcode,
                COALESCE(a.cSeo, '') AS cSeo,
                COALESCE(pic1.nNr, 1) AS nNr
             FROM tartikel a
             LEFT JOIN (
                SELECT s1.kArtikel, MIN(s1.cName) AS cName
                FROM tartikelsprache s1
                GROUP BY s1.kArtikel
             ) tsAny ON tsAny.kArtikel = a.kArtikel
             LEFT JOIN (
                SELECT p1.kArtikel, MIN(p1.nNr) AS nNr
                FROM tartikelpict p1
                WHERE p1.nNr = 1
                GROUP BY p1.kArtikel
             ) pic1 ON pic1.kArtikel = a.kArtikel
             WHERE (
                a.kArtikel = " . $exactID . "
                OR a.cArtNr LIKE '" . $likeEsc . "'
                OR a.cBarcode LIKE '" . $likeEsc . "'
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

?>

<?php if ($message !== ''): ?>
    <div class="alert alert-info" style="margin: 10px 0;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<style>
    .ap-wrap{max-width:1200px}
    .ap-topbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:10px 0 14px}
    .ap-title{margin:0;font-weight:800;letter-spacing:.2px}
    .ap-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid rgba(0,0,0,.1);padding:6px 10px;border-radius:999px;font-size:12px;color:rgba(0,0,0,.7);background:#fafafa}
    .ap-grid{display:grid;grid-template-columns:1fr;gap:14px}
    @media(min-width:992px){.ap-grid{grid-template-columns:460px 1fr}}
    .ap-card{background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05)}
    .ap-card-h{padding:12px 14px;border-bottom:1px solid rgba(0,0,0,.06);display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}
    .ap-card-b{padding:14px}
    .ap-tabs-list{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px}
    .ap-tab-btn{border:1px solid rgba(0,0,0,.12);background:#fff;border-radius:999px;padding:8px 12px;font-weight:700;font-size:13px;cursor:pointer}
    .ap-tab-btn.active{background:#111;color:#fff;border-color:#111}
    .ap-tab-panel{display:none}
    .ap-tab-panel.active{display:block}
    .ap-row{display:grid;grid-template-columns:1fr;gap:10px}
    @media(min-width:768px){.ap-row{grid-template-columns:1fr 1fr}}
    .ap-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style>

<div class="ap-wrap">
    <div class="ap-topbar">
        <h2 class="ap-title">ActionProduct Dashboard</h2>
        <span class="ap-pill">Tab sayısı: <strong><?php echo (int)count($apTabs); ?></strong></span>
    </div>

    <div class="ap-grid">
        <div class="ap-card">
            <div class="ap-card-h">
                <strong>Ürün Ara</strong>
            </div>
            <div class="ap-card-b">
                <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <input class="form-control" type="text" name="apq" placeholder="Artikelnummer / GTIN / Başlık" value="<?php echo htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8'); ?>" style="max-width:520px;">
                    <button class="btn btn-secondary" type="submit">Ara</button>
                    <?php if ($searchQ !== ''): ?>
                        <a class="btn btn-link" href="?">Temizle</a>
                    <?php endif; ?>
                </form>

                <?php if ($searchQ !== ''): ?>
                    <div style="margin-top:12px;">
                        <?php if (empty($searchRows)): ?>
                            <div class="alert alert-warning" style="margin:0;">Sonuç bulunamadı.</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($searchRows as $p):
                                    $kArtikel = (int)($p->kArtikel ?? 0);
                                    $tmp = new stdClass();
                                    $tmp->kArtikel = $kArtikel;
                                    $tmp->cName = (string)($p->cName ?? '');
                                    $tmp->cSeo = (string)($p->cSeo ?? '');
                                    $tmp->originalSeo = (string)($p->cSeo ?? '');
                                    $tmp->cArtNr = (string)($p->cArtNr ?? '');
                                    $tmp->cBarcode = (string)($p->cBarcode ?? '');
                                    $tmp->customImgName = '';
                                    $cleanName = \JTL\Media\Image\Product::getCustomName($tmp);
                                    $img = $kArtikel > 0 ? apBuildJtlProductImageUrl($shopUrl, $kArtikel, $cleanName, 1, 'xs', 'jpg') : '';
                                    $name = (string)($p->cName ?? '');
                                    $artnr = (string)($p->cArtNr ?? '');
                                    $barcode = (string)($p->cBarcode ?? '');
                                ?>
                                    <div class="col-md-6" style="margin-bottom:12px;">
                                        <div class="card">
                                            <div class="card-body" style="display:flex; gap:12px;">
                                                <div style="width:72px; height:72px; border:1px solid rgba(0,0,0,.08); border-radius:8px; overflow:hidden; background:#f9fafb; flex:0 0 auto;">
                                                    <?php if ($img !== ''): ?>
                                                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                                    <?php endif; ?>
                                                </div>
                                                <div style="flex:1 1 auto;">
                                                    <div style="font-size:12px; color:rgba(0,0,0,.6); margin-bottom:4px;">
                                                        #<?php echo $kArtikel; ?>
                                                        <?php if ($artnr !== ''): ?> · <?php echo htmlspecialchars($artnr, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                        <?php if ($barcode !== ''): ?> · GTIN: <?php echo htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                    </div>
                                                    <div style="font-weight:700; margin-bottom:10px;">
                                                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                        <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                                            <?php echo Form::getTokenInput(); ?>
                                                            <input type="hidden" name="kArtikel" value="<?php echo $kArtikel; ?>">
                                                            <select class="form-control form-control-sm" name="tabIndex" style="max-width:160px;">
                                                                <?php foreach ($apTabs as $ti => $_): ?>
                                                                    <option value="<?php echo (int)($ti + 1); ?>">Tab <?php echo (int)($ti + 1); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button class="btn btn-sm btn-outline-primary" type="submit" name="apFillFromProduct" value="1">Aktar</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ap-card">
            <div class="ap-card-h">
                <strong>Ayarlar</strong>
                <form method="post" style="margin:0;">
                    <?php echo Form::getTokenInput(); ?>
                    <button class="btn btn-sm btn-outline-primary" type="submit" name="apAddTab" value="1">+ Tab ekle</button>
                </form>
            </div>
            <div class="ap-card-b">
                <form method="post">
                    <?php echo Form::getTokenInput(); ?>

                    <div class="form-group">
                        <label style="display:flex;gap:10px;align-items:center;">
                            <input type="checkbox" name="nActive" value="1" <?php echo ($apActive === 1) ? 'checked' : ''; ?>>
                            Aktif (anasayfada göster)
                        </label>
                    </div>

                    <div class="ap-row">
                        <div class="form-group">
                            <label>Enjeksiyon konumu</label>
                            <select class="form-control" name="cInjectMode">
                                <option value="after_configurator" <?php echo $apInjectMode === 'after_configurator' ? 'selected' : ''; ?>>Konfigurator sonrası (.index-filter-konfigurator)</option>
                                <option value="after_content" <?php echo $apInjectMode === 'after_content' ? 'selected' : ''; ?>>#content içine (prepend)</option>
                                <option value="after_selector" <?php echo $apInjectMode === 'after_selector' ? 'selected' : ''; ?>>Custom selector sonrası</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Önce / Sonra</label>
                            <select class="form-control" name="cInjectPos">
                                <option value="after" <?php echo $apInjectPos === 'after' ? 'selected' : ''; ?>>Sonrasına ekle</option>
                                <option value="before" <?php echo $apInjectPos === 'before' ? 'selected' : ''; ?>>Öncesine ekle</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Custom selector</label>
                        <input class="form-control" type="text" name="cInjectSelector" value="<?php echo htmlspecialchars($apInjectSelector, ENT_QUOTES, 'UTF-8'); ?>" placeholder="örn: div#content veya .some-class">
                    </div>

                    <div class="ap-tabs-list" id="apTabsNav">
                        <?php foreach ($apTabs as $i => $t): ?>
                            <button type="button" class="ap-tab-btn <?php echo ($i === 0) ? 'active' : ''; ?>" data-ap-tab="<?php echo (int)$i; ?>">Tab <?php echo (int)($i + 1); ?></button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($apTabs as $i => $t):
                        $imagesText = '';
                        if (!empty($t['images']) && is_array($t['images'])) {
                            $imagesText = implode("\n", array_map('strval', $t['images']));
                        }
                    ?>
                        <div class="ap-tab-panel <?php echo ($i === 0) ? 'active' : ''; ?>" data-ap-panel="<?php echo (int)$i; ?>">
                            <div class="ap-actions" style="margin-bottom:10px;">
                                <span class="ap-pill">Tab <?php echo (int)($i + 1); ?></span>
                                <span class="ap-pill">kArtikel: <strong><?php echo (int)($t['kArtikel'] ?? 0); ?></strong></span>
                                <button class="btn btn-sm btn-outline-danger"
                                        type="submit"
                                        name="apDeleteTab"
                                        value="<?php echo (int)$i; ?>"
                                        <?php echo (count($apTabs) <= 1) ? 'disabled' : ''; ?>>
                                    Tab sil
                                </button>
                            </div>

                            <div class="ap-row">
                                <div class="form-group">
                                    <label>Tab Başlığı</label>
                                    <input class="form-control" type="text" name="tabTitle[]" value="<?php echo htmlspecialchars((string)($t['tabTitle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Badge</label>
                                    <input class="form-control" type="text" name="badge[]" value="<?php echo htmlspecialchars((string)($t['badge'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Countdown (tarih ve saat)</label>
                                <input class="form-control"
                                       type="datetime-local"
                                       name="countdownIso[]"
                                       step="1"
                                       autocomplete="off"
                                       value="<?php echo htmlspecialchars(apCountdownIsoToDatetimeLocal((string)($t['countdownIso'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                <small class="text-muted" style="display:block;margin-top:6px;">Takvim simgesine tıklayarak tarih ve saati seçin (saniye hassasiyeti mümkün).</small>
                            </div>

                            <div class="form-group">
                                <label>Ürün Adı</label>
                                <input class="form-control" type="text" name="name[]" value="<?php echo htmlspecialchars((string)($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="kArtikel[]" value="<?php echo (int)($t['kArtikel'] ?? 0); ?>">
                            </div>

                            <div class="form-group">
                                <label>Ürün Açıklama</label>
                                <input class="form-control" type="text" name="desc[]" value="<?php echo htmlspecialchars((string)($t['desc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="ap-row">
                                <div class="form-group">
                                    <label style="display:flex;gap:10px;align-items:center;margin:0;">
                                        <input type="hidden" name="urgencyActive[<?php echo (int)$i; ?>]" value="0">
                                        <input type="checkbox" name="urgencyActive[<?php echo (int)$i; ?>]" value="1" <?php echo !empty($t['urgencyActive']) ? 'checked' : ''; ?>>
                                        ⏰ Uyarı göster (Nur noch …)
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Uyarı metni (`{remain}` otomatik kalan adet)</label>
                                    <input class="form-control" type="text" name="urgencyText[]" value="<?php echo htmlspecialchars((string)($t['urgencyText'] ?? 'Nur noch {remain} Stück verfügbar!'), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>

                            <div class="ap-row">
                                <div class="form-group">
                                    <label>Eski Fiyat (UVP)</label>
                                    <input class="form-control" type="text" name="priceOld[]" value="<?php echo htmlspecialchars((string)($t['priceOld'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Yeni Fiyat</label>
                                    <input class="form-control" type="text" name="priceNew[]" value="<?php echo htmlspecialchars((string)($t['priceNew'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Toplam Stok</label>
                                    <input class="form-control" type="number" name="totalStock[]" value="<?php echo (int)($t['totalStock'] ?? 0); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Satılan</label>
                                    <input class="form-control" type="number" name="soldCount[]" value="<?php echo (int)($t['soldCount'] ?? 0); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Kupon Kodu (seçince otomatik kopyalanır)</label>
                                    <select class="form-control ap-coupon-select" name="couponCode[]">
                                        <option value="">— Kupon seçme —</option>
                                        <?php foreach ($couponRows as $c):
                                            $code = trim((string)($c->cCode ?? ''));
                                            if ($code === '') { continue; }
                                            $label = trim((string)($c->cName ?? $code));
                                            $wert = (float)($c->fWert ?? 0.0);
                                            $typ  = (string)($c->cWertTyp ?? 'festpreis');
                                            $suffix = ($typ === 'prozent') ? (' - ' . rtrim(rtrim(number_format($wert, 2, '.', ''), '0'), '.') . '%') : (' - ' . rtrim(rtrim(number_format($wert, 2, '.', ''), '0'), '.') . '€');
                                            $isSel = ((string)($t['couponCode'] ?? '') === $code) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSel; ?>>
                                                <?php echo htmlspecialchars($label . ' (' . $code . ')' . $suffix, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Jetzt kaufen URL</label>
                                <input class="form-control" type="text" name="buyUrl[]" value="<?php echo htmlspecialchars((string)($t['buyUrl'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="form-group">
                                <label>Görseller (her satıra 1 URL)</label>
                                <textarea class="form-control" name="images_<?php echo (int)$i; ?>" rows="5"><?php echo htmlspecialchars($imagesText, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button class="btn btn-primary" type="submit" name="apSaveSettings" value="1">Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function copyText(text) {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
                return;
            }
        } catch (e) {}
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    document.querySelectorAll('.ap-coupon-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const v = (sel.value || '').trim();
            if (v) {
                copyText(v);
            }
        });
    });

    const nav = document.getElementById('apTabsNav');
    if (nav) {
        nav.addEventListener('click', function (e) {
            const btn = e.target.closest('.ap-tab-btn');
            if (!btn) return;
            const idx = btn.getAttribute('data-ap-tab');
            document.querySelectorAll('.ap-tab-btn').forEach(b => b.classList.toggle('active', b === btn));
            document.querySelectorAll('.ap-tab-panel').forEach(p => p.classList.toggle('active', p.getAttribute('data-ap-panel') === idx));
        });
    }
})();
</script>