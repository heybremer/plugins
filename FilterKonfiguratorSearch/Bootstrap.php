<?php

namespace Plugin\FilterKonfiguratorSearch;

require_once __DIR__ . '/src/PluginDatabase.php';
require_once __DIR__ . '/src/Query/PkwSqlFragments.php';
require_once __DIR__ . '/src/Repository/FilterKonfiguratorRepository.php';
require_once __DIR__ . '/src/Controller/FilterKonfiguratorController.php';
require_once __DIR__ . '/src/Service/SeatImageRecognitionService.php';
require_once __DIR__ . '/src/SeriesMetaRuntime.php';
require_once __DIR__ . '/src/Filter/CharacteristicOptionsGuard.php';

use JTL\DB\DbInterface;
use JTL\Events\Dispatcher;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Smarty\JTLSmarty;
use JTL\Shop;
use JTL\Helpers\Request;
use Plugin\FilterKonfiguratorSearch\Controller\FilterKonfiguratorController;
use Plugin\FilterKonfiguratorSearch\SeriesMetaRuntime;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    /**
     * Ensures an admin menu entry exists only once per PHP process.
     */
    private static bool $adminMenuEnsured = false;

    /**
     * Ensures banner settings table exists only once per PHP process.
     */
    private static bool $bannerTableEnsured = false;

    /**
     * Mikro-Kategorie-Whitelist (unabhängig von tplugin / twomosi_fk_banner Zeilenchaos).
     */
    private static bool $fkMicroScopeTableEnsured = false;

    /** @var string Letzter DB-Fehler bei twomosi_fk_micro_scope (nur Diagnose). */
    private static string $fkMicroScopeLastErr = '';

    /**
     * APCu / cross-request cache: schema init complete for twomosi_fk_banner (avoids DB on every request).
     */
    private const BANNER_SCHEMA_APCU_KEY = 'womosi_fk_banner_schema_v2';

    /**
     * Register /fk-search route.
     *
     * @param Dispatcher $dispatcher
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        // Tablo DDL: PluginDatabase::ensureCoreTables() — yalnızca /fk-search ve /fk-selection
        // (ve admin) yollarında; her shop isteğinde bootstrap yükünü artırmaz.
        $this->ensureAdminMenuEntry();
        try {
            $this->registerCharacteristicOptionsCache($dispatcher);
        } catch (\Throwable $e) {
        }

        // CSS/JS: sadece gereken sayfalarda yükle (global yükleme LCP/TTI/TBT'yi ciddi düşürür).
        // - Ana konfigurator CSS + JS sürümlü URL ile doğrudan kendi template'inden yüklenir.
        // - fk-liste-title + fk-manual-model-cart → liste/ürün/warenkorb/checkout + Startseite.
        // Serie-Metadaten (Dashboard) → window.FK_SERIES_CONFIG vor den Konfigurator-Skripten
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            $pt = Shop::getPageType();
            $isHome = ($pt === \PAGE_STARTSEITE);
            $needsAuxJs = $isHome
                || $pt === \PAGE_ARTIKEL
                || $pt === \PAGE_ARTIKELLISTE
                || $pt === \PAGE_LIVESUCHE
                || $pt === \PAGE_HERSTELLER
                || $pt === \PAGE_WARENKORB
                || $pt === \PAGE_BESTELLVORGANG;
            if (!$isHome && !$needsAuxJs) {
                return;
            }
            $doc = $args['document'];
            $this->injectFkSeriesConfigInDocumentHead($doc);
            try {
                $assetVersion = (string)$this->getPlugin()->getMeta()->getSemVer()->getOriginalVersion();
            } catch (\Throwable $e) {
                $assetVersion = '1.14.70';
            }
            $assetVersion = \preg_replace('/[^0-9A-Za-z._-]/', '', $assetVersion) ?: '1.14.70';
            if (\version_compare($assetVersion, '1.14.99', '<')) {
                $assetVersion = '1.14.99';
            }
            foreach ([
                ['selector' => 'script[src*="fk-micro-konfigurator.js"]', 'attr' => 'src'],
                ['selector' => 'script[src*="fk-liste-title.js"]', 'attr' => 'src'],
                ['selector' => 'script[src*="fk-manual-model-cart.js"]', 'attr' => 'src'],
                ['selector' => 'link[href*="fk-micro-filter.css"]', 'attr' => 'href'],
            ] as $asset) {
                $nodes = $doc->find($asset['selector']);
                for ($i = 0, $count = (int)$nodes->length; $i < $count; $i++) {
                    $node = $nodes->eq($i);
                    $url  = (string)$node->attr($asset['attr']);
                    if ($url === '' || \strpos($url, 'fkver=') !== false) {
                        continue;
                    }
                    $node->attr(
                        $asset['attr'],
                        $url . (\strpos($url, '?') === false ? '?' : '&') . 'fkver=' . $assetVersion
                    );
                }
            }
        });

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            $pt = Shop::getPageType();
            $isHome = ($pt === \PAGE_STARTSEITE);
            // Liste: sadece PAGE_ARTIKELLISTE değil; filtreli arama / Hersteller / Live-Suche sayfaları da ürün grid'i gösterebilir.
            $needsAuxJs = $isHome
                || $pt === \PAGE_ARTIKEL
                || $pt === \PAGE_ARTIKELLISTE
                || $pt === \PAGE_LIVESUCHE
                || $pt === \PAGE_HERSTELLER
                || $pt === \PAGE_WARENKORB
                || $pt === \PAGE_BESTELLVORGANG;

            if (!$isHome && !$needsAuxJs) {
                return;
            }

            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }

            if ($needsAuxJs) {
                if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                    $args['cPluginCss_arr'] = [];
                }
                $cssMicro = 'plugins/FilterKonfiguratorSearch/templates/css/fk-micro-filter.css';
                if (!\in_array($cssMicro, $args['cPluginCss_arr'], true)) {
                    $args['cPluginCss_arr'][] = $cssMicro;
                }
                $jsPathMicro = 'plugins/FilterKonfiguratorSearch/templates/js/fk-micro-konfigurator.js';
                if (!\in_array($jsPathMicro, $args['cPluginJsHead_arr'], true)) {
                    $args['cPluginJsHead_arr'][] = $jsPathMicro;
                }
                $jsPathListe = 'plugins/FilterKonfiguratorSearch/templates/js/fk-liste-title.js';
                if (!\in_array($jsPathListe, $args['cPluginJsHead_arr'], true)) {
                    $args['cPluginJsHead_arr'][] = $jsPathListe;
                }
                $jsPathManual = 'plugins/FilterKonfiguratorSearch/templates/js/fk-manual-model-cart.js';
                if (!\in_array($jsPathManual, $args['cPluginJsHead_arr'], true)) {
                    $args['cPluginJsHead_arr'][] = $jsPathManual;
                }
            }
        });

        // „Modell nicht in der Liste“ + manueller Text: Warenkorb-Position cName = dynamischer Titel (Wawi/Bestellung)
        $dispatcher->hookInto(\HOOK_WARENKORB_CLASS_FUEGEEIN, function (array &$args): void {
            $this->applyWomosiManualModelCartPositionName($args);
        });

        // Konfigurator bloğunu anasayfa HTML'ine enjekte et (sadece Startseite)
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (Shop::getPageType() !== \PAGE_STARTSEITE) {
                return;
            }
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            // Banner içeriğini DB'den al ve template'e ver
            try {
                $db = Shop::Container()->getDB();
                // En güncel banner satırı (kID her zaman son güncelleme olmayabilir)
                $banner = $db->getSingleObject('SELECT * FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
                if ($banner !== null) {
                    $banner->nSub1Active = (int)($banner->nSub1Active ?? 1);
                    $banner->nSub2Active = (int)($banner->nSub2Active ?? 1);
                    $banner->nSub3Active = (int)($banner->nSub3Active ?? 1);
                    $banner->nSegmentWmActive  = (int)($banner->nSegmentWmActive ?? 1);
                    $banner->nSegmentPkwActive = (int)($banner->nSegmentPkwActive ?? 1);
                    if ($banner->nSegmentWmActive !== 1 && $banner->nSegmentPkwActive !== 1) {
                        $banner->nSegmentWmActive = 1;
                    }
                    if (!isset($banner->cSub2ButtonLabel) || trim((string)$banner->cSub2ButtonLabel) === '') {
                        $banner->cSub2ButtonLabel = 'Aktivieren';
                    }
                    if (!isset($banner->cSub2ButtonUrl) || trim((string)$banner->cSub2ButtonUrl) === '') {
                        $banner->cSub2ButtonUrl = '#';
                    }
                    $smarty->assign('fkBanner', $banner);
                } else {
                    $smarty->assign('fkBanner', (object)[
                        'cHeadline' => 'Besuchen Sie uns auf der HanseMesse Rostock',
                        'cSub1'     => 'Boot & Angeln Camping',
                        'cSub2'     => '',
                        'cSub3'     => '20. bis 22. März 2026 | HanseMesse Rostock',
                        'nSub1Active' => 1,
                        'nSub2Active' => 1,
                        'nSub3Active' => 1,
                        'cSub2ButtonLabel' => 'Aktivieren',
                        'cSub2ButtonUrl' => '#',
                        'nSegmentWmActive' => 1,
                        'nSegmentPkwActive' => 1,
                    ]);
                }
            } catch (\Throwable $e) {
                $smarty->assign('fkBanner', (object)[
                    'cHeadline' => 'Besuchen Sie uns auf der HanseMesse Rostock',
                    'cSub1'     => 'Boot & Angeln Camping',
                    'cSub2'     => '',
                    'cSub3'     => '20. bis 22. März 2026 | HanseMesse Rostock',
                    'nSub1Active' => 1,
                    'nSub2Active' => 1,
                    'nSub3Active' => 1,
                    'cSub2ButtonLabel' => 'Aktivieren',
                    'cSub2ButtonUrl' => '#',
                    'nSegmentWmActive' => 1,
                    'nSegmentPkwActive' => 1,
                ]);
            }

            $seatPhotoEnabledRaw = '0';
            try {
                $seatPhotoEnabledRaw = \strtolower(\trim((string)(
                    $this->getPlugin()->getConfig()->getValue('fk_seat_photo_enabled_v2') ?? '0'
                )));
                $db = Shop::Container()->getDB();
                $pluginRow = $db->getSingleObject(
                    "SELECT kPlugin FROM tplugin
                      WHERE cPluginID = 'FilterKonfiguratorSearch'
                         OR TRIM(cVerzeichnis) = 'FilterKonfiguratorSearch'
                   ORDER BY kPlugin DESC LIMIT 1"
                );
                $pluginId = (int)($pluginRow->kPlugin ?? 0);
                if ($pluginId > 0) {
                    $settingRow = $db->getSingleObject(
                        "SELECT cWert FROM tplugineinstellungen
                          WHERE kPlugin = :pid AND cName = 'fk_seat_photo_enabled_v2'
                          LIMIT 1",
                        ['pid' => $pluginId]
                    );
                    if ($settingRow !== null) {
                        $seatPhotoEnabledRaw = \strtolower(\trim((string)($settingRow->cWert ?? '0')));
                    }
                }
            } catch (\Throwable $e) {
            }
            $smarty->assign(
                'fkSeatPhotoEnabled',
                !\in_array($seatPhotoEnabledRaw, ['0', 'n', 'no', 'false', 'off', ''], true)
            );

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/filterkonfigurator_index.tpl';
            if (!\is_file($tplPath)) {
                return;
            }
            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }

            if ($html === '' || \strpos($html, 'index-filter-konfigurator') === false) {
                return;
            }

            $injected = false;

            // 1) phpQuery ile main#main-wrapper
            $main = $doc->find('main#main-wrapper')->eq(0);
            if ($main->length > 0) {
                $main->prepend($html);
                $injected = true;
            }
            // 1.1) Fallback: sadece #main-wrapper id'si varsa ona enjekte et
            if (!$injected) {
                $mainById = $doc->find('#main-wrapper')->eq(0);
                if ($mainById->length > 0) {
                    $mainById->prepend($html);
                    $injected = true;
                }
            }
            if (!$injected) {
                $genericMain = $doc->find('main')->eq(0);
                if ($genericMain->length > 0) {
                    $genericMain->prepend($html);
                    $injected = true;
                }
            }

            // 2) Fallback: phpQuery ile enjekte edilemediyse string ile main açılış etiketinden sonra ekle
            if (!$injected) {
                $full = $doc->htmlOuter();
                $tag  = '<main id="main-wrapper"';
                $pos  = \stripos($full, $tag);
                if ($pos === false) {
                    $pos = \stripos($full, '<main ');
                }
                if ($pos !== false) {
                    $end   = \strpos($full, '>', $pos) + 1;
                    $new   = \substr($full, 0, $end) . $html . \substr($full, $end);
                    $newDoc = phpQuery::newDocumentHTML($new, \JTL_CHARSET);
                    $doc->find('body')->html($newDoc->find('body')->html());
                }
            }
        });

        // Liste + Artikel + Warenkorb + Checkout: Konfigurator-Auswahl (GET/SEO-Pfad); Warenkorb/Checkout meist per fk-liste-title.js (localStorage)
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            $pt = Shop::getPageType();
            if ($pt !== \PAGE_ARTIKEL && $pt !== \PAGE_ARTIKELLISTE && $pt !== \PAGE_LIVESUCHE && $pt !== \PAGE_HERSTELLER
                && $pt !== \PAGE_WARENKORB && $pt !== \PAGE_BESTELLVORGANG) {
                return;
            }
            if (!isset($args['document'])) {
                return;
            }
            $doc = $args['document'];
            if ($doc->find('.fk-selection-summary')->length > 0 || $doc->find('.fk-selection-micro')->length > 0) {
                return;
            }

            $items = $this->fkBuildSelectionItemsFromRequest();
            $hasItems = false;
            foreach ($items as $it) {
                if (trim((string)($it['value'] ?? '')) !== '') {
                    $hasItems = true;
                    break;
                }
            }
            $fkMicroListPage = $pt === \PAGE_ARTIKELLISTE || $pt === \PAGE_LIVESUCHE || $pt === \PAGE_HERSTELLER;

            $fkMicroDebug = isset($_GET['fk_micro_debug']) && (string)$_GET['fk_micro_debug'] !== '0';
            $fkMicroDebugNote = '';
            $fkMicroDebugBar  = '';
            if ($fkMicroListPage) {
                $allowedCatsStr = $this->fkMicroMergeAllowedCategoriesCsv();
                /** @var JTLSmarty|null $smarty */
                $smarty = isset($args['smarty']) && $args['smarty'] instanceof JTLSmarty ? $args['smarty'] : null;
                $currentCategoryId = 0;
                if ($allowedCatsStr !== '') {
                    $currentCategoryId = $this->fkMicroResolveCurrentCategoryId($smarty);
                } elseif ($fkMicroDebug) {
                    // still resolve for debugging even when unrestricted
                    $currentCategoryId = $this->fkMicroResolveCurrentCategoryId($smarty);
                }
                if ($allowedCatsStr !== '') {
                    $allowedIds = [];
                    foreach (preg_split('/[\s,;]+/', $allowedCatsStr) as $p) {
                        $p = trim((string)$p);
                        if ($p === '') {
                            continue;
                        }
                        $n = (int)$p;
                        if ($n > 0) {
                            $allowedIds[$n] = $n;
                        }
                    }
                    if ($fkMicroDebug) {
                        $dbgReq = 0;
                        $dbgState = 0;
                        $dbgSmarty = 0;
                        $dbgPFBase = 0;
                        $dbgPFFilter = 0;
                        try { $dbgReq = (int)\JTL\Helpers\Request::verifyGPCDataInt(\QUERY_PARAM_CATEGORY); } catch (\Throwable $e) {}
                        try { $dbgState = (int)Shop::getState()->categoryID; } catch (\Throwable $e) {}
                        try {
                            if ($smarty !== null) {
                                $ak = $smarty->getTemplateVars('AktuelleKategorie');
                                if (\is_object($ak)) {
                                    if (\method_exists($ak, 'getID')) {
                                        $dbgSmarty = (int)$ak->getID();
                                    } elseif ($ak instanceof \stdClass && isset($ak->kKategorie)) {
                                        $dbgSmarty = (int)$ak->kKategorie;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {}
                        try {
                            $pf = Shop::getProductFilter();
                            if ($pf !== null && $pf->hasCategory()) {
                                $v = $pf->getCategory()->getValue();
                                $dbgPFBase = \is_array($v) ? (int)(\reset($v) ?: 0) : (\is_numeric($v) ? (int)$v : 0);
                            }
                            if ($pf !== null && $pf->hasCategoryFilter()) {
                                $v = $pf->getCategoryFilter()->getValue();
                                $dbgPFFilter = \is_array($v) ? (int)(\reset($v) ?: 0) : (\is_numeric($v) ? (int)$v : 0);
                            }
                        } catch (\Throwable $e) {}
                        $fkMicroDebugNote = '<!-- fk-micro-debug: pt=' . (int)$pt
                            . ' allowed="' . \htmlspecialchars($allowedCatsStr, \ENT_QUOTES, 'UTF-8') . '"'
                            . ' currentCategoryId=' . (int)$currentCategoryId
                            . ' parsedAllowedCount=' . (int)\count($allowedIds)
                            . ' req=' . (int)$dbgReq
                            . ' state=' . (int)$dbgState
                            . ' smarty=' . (int)$dbgSmarty
                            . ' pfBase=' . (int)$dbgPFBase
                            . ' pfFilter=' . (int)$dbgPFFilter
                            . ' -->';
                        $dbgBannerCats = '';
                        $dbgConfigCats = '';
                        $dbgSqlCats    = $this->fkMicroReadCategoriesFromPluginSettingsTable();
                        $dbgScopeCats  = $this->fkMicroReadScopeTableCsv();
                        try {
                            $db2 = $this->fkMicroShopDb();
                            $row2 = $db2->getSingleObject(
                                "SELECT cActiveCategories FROM twomosi_fk_banner WHERE TRIM(cActiveCategories) <> '' ORDER BY dUpdated DESC, kID DESC LIMIT 1"
                            );
                            if ($row2 === null) {
                                $row2 = $db2->getSingleObject('SELECT cActiveCategories FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
                            }
                            $dbgBannerCats = $row2 !== null ? trim((string)($row2->cActiveCategories ?? '')) : '';
                        } catch (\Throwable $e) {}
                        try { $dbgConfigCats = trim((string)($this->getPlugin()->getConfig()->getValue('fk_micro_active_categories') ?? '')); } catch (\Throwable $e) {}
                        $dbgScopeErr = self::$fkMicroScopeLastErr;
                        $fkMicroDebugBar = '<div class="fk-micro-debug" style="position:sticky;top:0;z-index:9999;background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:6px 8px;font-size:12px;margin:6px 0;border-radius:6px;">'
                            . '<strong>fk-micro-debug</strong> '
                            . 'allowed=<code style="color:#856404;">' . \htmlspecialchars($allowedCatsStr, \ENT_QUOTES, 'UTF-8') . '</code> '
                            . 'scopeCats=<code style="color:#856404;">' . \htmlspecialchars($dbgScopeCats === '' ? '(empty)' : $dbgScopeCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                            . 'scopeErr=<code style="color:#856404;">' . \htmlspecialchars($dbgScopeErr === '' ? '(none)' : $dbgScopeErr, \ENT_QUOTES, 'UTF-8') . '</code> '
                            . 'bannerCats=<code style="color:#856404;">' . \htmlspecialchars($dbgBannerCats === '' ? '(empty)' : $dbgBannerCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                            . 'configCats=<code style="color:#856404;">' . \htmlspecialchars($dbgConfigCats === '' ? '(empty)' : $dbgConfigCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                            . 'sqlCats=<code style="color:#856404;">' . \htmlspecialchars($dbgSqlCats === '' ? '(empty)' : $dbgSqlCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                            . 'current=' . (int)$currentCategoryId . ' '
                            . 'req=' . (int)$dbgReq . ' state=' . (int)$dbgState . ' smarty=' . (int)$dbgSmarty . ' pfBase=' . (int)$dbgPFBase . ' pfFilter=' . (int)$dbgPFFilter
                            . '</div>';
                    }
                    if ($allowedIds === []) {
                        // invalid list -> do not show micro
                        return;
                    }
                    // SEO-/GET-gestützte Konfigurator-Listen (z. B. /DPL__Kastenwagen__…) haben oft keine
                    // zuverlässige Shop-Kategorie-ID — Mikro trotzdem anzeigen, wenn bereits eine Auswahl in der URL steht.
                    // Hersteller-Seiten (z. B. /Aiways, /Adria) haben auch keine Kategorie-ID, daher für diese überspringen.
                    if (!$hasItems && $pt !== \PAGE_HERSTELLER) {
                        if ($currentCategoryId <= 0) {
                            return;
                        }
                        $db = $this->fkMicroShopDb();
                        if (!$this->fkMicroCategoryIsAllowedInTree($db, $currentCategoryId, array_values($allowedIds))) {
                            return;
                        }
                    }
                } elseif ($fkMicroDebug) {
                    $fkMicroDebugNote = '<!-- fk-micro-debug: pt=' . (int)$pt . ' allowed="" (no restriction active) -->';
                    $dbgBannerCats = '';
                    $dbgConfigCats = '';
                    $dbgSqlCats    = $this->fkMicroReadCategoriesFromPluginSettingsTable();
                    $dbgScopeCats  = $this->fkMicroReadScopeTableCsv();
                    try {
                        $db2 = $this->fkMicroShopDb();
                        $row2 = $db2->getSingleObject(
                            "SELECT cActiveCategories FROM twomosi_fk_banner WHERE TRIM(cActiveCategories) <> '' ORDER BY dUpdated DESC, kID DESC LIMIT 1"
                        );
                        if ($row2 === null) {
                            $row2 = $db2->getSingleObject('SELECT cActiveCategories FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
                        }
                        $dbgBannerCats = $row2 !== null ? trim((string)($row2->cActiveCategories ?? '')) : '';
                    } catch (\Throwable $e) {}
                    try { $dbgConfigCats = trim((string)($this->getPlugin()->getConfig()->getValue('fk_micro_active_categories') ?? '')); } catch (\Throwable $e) {}
                    $dbgScopeErr = self::$fkMicroScopeLastErr;
                    $fkMicroDebugBar  = '<div class="fk-micro-debug" style="position:sticky;top:0;z-index:9999;background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:6px 8px;font-size:12px;margin:6px 0;border-radius:6px;">'
                        . '<strong>fk-micro-debug</strong> '
                        . 'allowed=<code style="color:#856404;">(empty)</code> '
                        . 'scopeCats=<code style="color:#856404;">' . \htmlspecialchars($dbgScopeCats === '' ? '(empty)' : $dbgScopeCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                        . 'scopeErr=<code style="color:#856404;">' . \htmlspecialchars($dbgScopeErr === '' ? '(none)' : $dbgScopeErr, \ENT_QUOTES, 'UTF-8') . '</code> '
                        . 'bannerCats=<code style="color:#856404;">' . \htmlspecialchars($dbgBannerCats === '' ? '(empty)' : $dbgBannerCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                        . 'configCats=<code style="color:#856404;">' . \htmlspecialchars($dbgConfigCats === '' ? '(empty)' : $dbgConfigCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                        . 'sqlCats=<code style="color:#856404;">' . \htmlspecialchars($dbgSqlCats === '' ? '(empty)' : $dbgSqlCats, \ENT_QUOTES, 'UTF-8') . '</code> '
                        . 'current=' . (int)$currentCategoryId
                        . '</div>';
                }
            }

            $countText = '';
            $microInjected = false;
            if ($fkMicroListPage) {
                if ($hasItems) {
                    try {
                        $outer = (string)$doc->htmlOuter();
                        if (preg_match('/von\s+(\d+)/i', $outer, $m)) {
                            $n = (int)($m[1] ?? 0);
                            if ($n > 0) {
                                $countText = ' (' . $n . ')';
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }

                $micro = $this->fkBuildMicroFilterHtml($items, $countText, $this->fkGetKonfiguratorChangeUrl(), (int)$currentCategoryId);
                if ($micro !== '') {
                    if ($fkMicroDebug) {
                        $micro = $fkMicroDebugNote . $fkMicroDebugBar . $micro;
                    }
                    $navRow = $doc->find('.row.productlist-page-nav-header-m')->eq(0);
                    if ($navRow->length > 0) {
                        $navRow->before($micro);
                        $microInjected = true;
                    } else {
                        $navRowPlain = $doc->find('.row.productlist-page-nav')->eq(0);
                        if ($navRowPlain->length > 0) {
                            $navRowPlain->before($micro);
                            $microInjected = true;
                        }
                    }
                    if (!$microInjected) {
                        $fallbackCol = $doc->find('.col-lg-8.col-xl-9.ml-auto-util')->eq(0);
                        if ($fallbackCol->length > 0) {
                            $fallbackCol->prepend($micro);
                            $microInjected = true;
                        }
                    }
                    if (!$microInjected) {
                        $rw = $doc->find('#result-wrapper')->eq(0);
                        if ($rw->length > 0) {
                            $rw->prepend($micro);
                            $microInjected = true;
                        }
                    }
                    if (!$microInjected) {
                        $pl = $doc->find('#product-list')->eq(0);
                        if ($pl->length > 0) {
                            $pl->before($micro);
                            $microInjected = true;
                        }
                    }
                    if (!$microInjected) {
                        $mainInner = $doc->find('main#main-wrapper .container')->eq(0);
                        if ($mainInner->length > 0) {
                            $mainInner->prepend($micro);
                            $microInjected = true;
                        }
                    }
                }

                // Hide default shop filters if configurator results are present
                if ($hasItems) {
                    $activeFilters = $doc->find('.active-filters');
                    if ($activeFilters->length > 0) {
                        $activeFilters->attr('style', 'display: none !important;');
                    }
                }
            }

            if ($pt === \PAGE_ARTIKEL) {
                /** @var JTLSmarty|null $smarty */
                $smarty = isset($args['smarty']) && $args['smarty'] instanceof JTLSmarty ? $args['smarty'] : null;
                $detailCategoryId = $this->fkMicroResolveProductCategoryId($smarty);
                if ($detailCategoryId > 0) {
                    $micro = $this->fkBuildMicroFilterHtml($items, '', $this->fkGetKonfiguratorChangeUrl(), $detailCategoryId);
                    if ($micro !== '') {
                        $ctx = $this->fkMicroProductContext($smarty, $detailCategoryId);
                        if ($ctx !== '') {
                            $micro = \preg_replace('/data-fk-micro-dynamic="1"/', 'data-fk-micro-dynamic="1" ' . $ctx, $micro, 1) ?? $micro;
                        }
                        $micro = '<div class="fk-micro-detail-wrap">' . $micro . '</div>';
                        $detail = $doc->find('#result-wrapper form#buy_form, form#buy_form')->eq(0);
                        if ($detail->length === 0) {
                            $detail = $doc->find('#result-wrapper .product-detail, .product-detail')->eq(0);
                        }
                        if ($detail->length > 0) {
                            $detail->before($micro);
                        } else {
                            $rw = $doc->find('#result-wrapper')->eq(0);
                            if ($rw->length > 0) {
                                $rw->prepend($micro);
                            }
                        }
                    }
                }
            }

            if (!$hasItems) {
                return;
            }

            $esc = static function (string $s): string {
                return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
            };
            $values = [];
            foreach ($items as $it) {
                $val = trim((string)($it['value'] ?? ''));
                $label = trim((string)($it['label'] ?? ''));
                if ($val === '') {
                    continue;
                }
                if ($label === 'kompatible Sitze') {
                    $val = $this->fkSeatDisplayName($val);
                }
                $values[] = $esc($val);
            }

            $segment = \trim((string)Request::getVar('segment', ''));
            $isPkw = ($segment === 'pkw');
            $isCustomModell = \trim((string)Request::getVar('fkCustomModell', '')) === '1'
                || \trim((string)Request::getVar('fkCustomModell', '')) === 'true'
                || \trim((string)Request::getVar('fkNichtInListe', '')) === '1'
                || \trim((string)Request::getVar('fkNichtInListe', '')) === 'true';

            $compactTitle = '';
            if (!$isPkw && $isCustomModell) {
                $compactTitle = $this->fkBuildCompactTitleLine($items);
            }
            $isArtikel           = $pt === \PAGE_ARTIKEL;
            $noGreenMessageBlock = $isArtikel || $pt === \PAGE_WARENKORB || $pt === \PAGE_BESTELLVORGANG;


            $messageBlock = '<span class="fk-selection-message"><span class="fk-selection-icon" aria-hidden="true">✔</span> Für Ihr Fahrzeug wurden passende Ergebnisse gefunden' . $countText . '</span>';

            $subtitle = '';
            if (!($microInjected && $fkMicroListPage)) {
                if ($noGreenMessageBlock) {
                    // Produkt-, Warenkorb-, Checkout: kein grüner Hinweis; nur H1-Kompaktzeile
                    $subtitle = '';
                } elseif ($compactTitle !== '') {
                    $subtitle = '<div class="fk-selection-subtitle text-muted mt-2 mb-1">'
                        . $messageBlock
                        . '</div>';
                } else {
                    $subtitle = '<div class="fk-selection-subtitle text-muted mt-2 mb-1">'
                        . $messageBlock
                        . '<div class="fk-selection-values">' . \implode(' ', $values) . '</div>'
                        . '</div>';
                }
            }

            $html = $subtitle !== ''
                ? '<div class="fk-selection-summary">' . $subtitle . '</div>'
                : '';

            $applyTitle = static function ($node) use ($compactTitle, $html): void {
                if ($compactTitle !== '' && $node->length > 0) {
                    $node->text($compactTitle);
                    $prev = (string)$node->attr('class');
                    $node->attr('class', \trim($prev . ' fk-selection-h1'));
                }
                if ($html !== '') {
                    $node->after($html);
                }
            };

            $title = $doc->find('h1.product-title.h2')->eq(0);
            if ($title->length > 0) {
                $applyTitle($title);
                return;
            }
            $title2 = $doc->find('div.title h1.h2')->eq(0);
            if ($title2->length > 0) {
                $applyTitle($title2);
                return;
            }
            $title3 = $doc->find('div.title h1')->eq(0);
            if ($title3->length > 0) {
                $applyTitle($title3);
                return;
            }
            $title4 = $doc->find('h1.basket-heading')->eq(0);
            if ($title4->length > 0) {
                $applyTitle($title4);
                return;
            }
            $title5 = $doc->find('#checkout h1')->eq(0);
            if ($title5->length > 0) {
                $applyTitle($title5);
            }
        });

        if (\class_exists(FilterKonfiguratorController::class, false)) {
            $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
                /** @var Router $router */
                $router = $args['router'];
                $router->addRoute(
                    '/fk-search',
                    function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                        $controller = new FilterKonfiguratorController();
                        return $controller->search($request, $routeArgs);
                    },
                    'fk-search',
                    ['GET']
                );

                // Step 4 - Seri seçimi sonrası kullanıcı seçimini logla (dashboard tabı için)
                $router->addRoute(
                    '/fk-selection',
                    function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                        $db = Shop::Container()->getDB();

                        $parsed = $request->getParsedBody();
                        $body   = \is_array($parsed) ? $parsed : [];
                        $query  = $request->getQueryParams();

                        $data = \array_merge($query, $body);

                        $typLabel   = (string)($data['typ'] ?? '');
                        $hersteller = (string)($data['hersteller'] ?? '');
                        $basis       = (string)($data['basis'] ?? '');
                        $modell      = (string)($data['modell'] ?? '');
                        $baujahr     = (string)($data['baujahr'] ?? '');
                        $sitz        = (string)($data['sitz'] ?? '');
                        $serie       = (string)($data['serie'] ?? '');
                        $ua          = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
                        $ip          = Request::getRealIP();

                        // Spam-/Bot-Schutz: max. 20 Selektions-Logs pro IP / 10s (fail-open ohne APCu).
                        if (PluginDatabase::throttle('sel', $ip, 20, 10)) {
                            return new \Laminas\Diactoros\Response\JsonResponse(['ok' => true], 200);
                        }

                        try {
                            PluginDatabase::ensureCoreTables();
                            $db->executeQueryPrepared(
                                "INSERT INTO twomosi_fk_selection
                                    (cIP, dCreated, cTyp, cHersteller, cBasis, cModell, cBaujahr, cSitz, cSerie, cUserAgent)
                                 VALUES
                                    (:ip, NOW(), :typ, :herst, :basis, :modell, :baujahr, :sitz, :serie, :ua)",
                                [
                                    'ip'      => $ip,
                                    'typ'     => $typLabel,
                                    'herst'   => $hersteller,
                                    'basis'   => $basis,
                                    'modell'  => $modell,
                                    'baujahr' => $baujahr,
                                    'sitz'    => $sitz,
                                    'serie'   => $serie,
                                    'ua'      => $ua,
                                ]
                            );
                        } catch (\Throwable $e) {
                            // Log başarısızsa endpoint fail etmesin.
                        }

                        return new \Laminas\Diactoros\Response\JsonResponse(['ok' => true], 200);
                    },
                    'fk-selection',
                    ['GET', 'POST']
                );

                $router->addRoute(
                    '/fk-anfrage',
                    function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                        $controller = new FilterKonfiguratorController();
                        return $controller->anfrage($request, $routeArgs);
                    },
                    'fk-anfrage',
                    ['POST']
                );

                $router->addRoute(
                    '/fk-seat-recognize',
                    function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                        $apiKey = '';
                        $model = 'gpt-5.6';
                        $enabledRaw = '0';
                        try {
                            $config = $this->getPlugin()->getConfig();
                            $apiKey = \trim((string)($config->getValue('fk_openai_api_key') ?? ''));
                            $model = \trim((string)($config->getValue('fk_openai_model') ?? 'gpt-5.6'));
                            $enabledRaw = \strtolower(\trim((string)($config->getValue('fk_seat_photo_enabled_v2') ?? '0')));
                        } catch (\Throwable $e) {
                        }
                        try {
                            $db = Shop::Container()->getDB();
                            $pluginRow = $db->getSingleObject(
                                "SELECT kPlugin FROM tplugin
                                  WHERE cPluginID = 'FilterKonfiguratorSearch'
                                     OR TRIM(cVerzeichnis) = 'FilterKonfiguratorSearch'
                               ORDER BY kPlugin DESC LIMIT 1"
                            );
                            $pluginId = (int)($pluginRow->kPlugin ?? 0);
                            if ($pluginId > 0) {
                                $settingRows = $db->getObjects(
                                    "SELECT cName, cWert FROM tplugineinstellungen
                                      WHERE kPlugin = :pid
                                        AND cName IN ('fk_seat_photo_enabled_v2', 'fk_openai_api_key', 'fk_openai_model')",
                                    ['pid' => $pluginId]
                                );
                                foreach ((array)$settingRows as $settingRow) {
                                    $name = (string)($settingRow->cName ?? '');
                                    $value = \trim((string)($settingRow->cWert ?? ''));
                                    if ($name === 'fk_openai_api_key') {
                                        $apiKey = $value;
                                    } elseif ($name === 'fk_openai_model' && $value !== '') {
                                        $model = $value;
                                    } elseif ($name === 'fk_seat_photo_enabled_v2') {
                                        $enabledRaw = \strtolower($value);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                        }
                        if ($model === '' || $model === 'gpt-4.1-mini') {
                            $model = 'gpt-5.6';
                        }
                        $enabled = !\in_array($enabledRaw, ['0', 'n', 'no', 'false', 'off'], true);
                        $controller = new FilterKonfiguratorController();

                        return $controller->recognizeSeatPhoto(
                            $request,
                            $routeArgs,
                            $apiKey,
                            $model,
                            $enabled
                        );
                    },
                    'fk-seat-recognize',
                    ['POST']
                );
            });
        }
    }

    /**
     * Creates/migrates twomosi_fk_banner only when needed.
     *
     * - Not called from global boot(): avoids work on every request (admin, API, cron, etc.).
     * - Fast path: APCu flag after successful init (no DB).
     * - Else: one cheap information_schema.tables check; CREATE only if missing; ALTER only for missing columns.
     * - Concurrent requests: CREATE IF NOT EXISTS + conditional ALTER are idempotent; seed uses NOT EXISTS guard.
     */
    private function ensureBannerTable(): void
    {
        if (self::$bannerTableEnsured) {
            return;
        }

        if (\function_exists('apcu_fetch')) {
            try {
                if (@\apcu_fetch(self::BANNER_SCHEMA_APCU_KEY) === true) {
                    self::$bannerTableEnsured = true;

                    return;
                }
            } catch (\Throwable $e) {
                // ignore APCu errors
            }
        }

        try {
            $db = Shop::Container()->getDB();

            $existsRow = $db->getSingleObject(
                "SELECT COUNT(*) AS c
                   FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name = 'twomosi_fk_banner'"
            );
            $tableExists = $existsRow !== null && (int)($existsRow->c ?? 0) > 0;

            if (!$tableExists) {
                $sql = "CREATE TABLE IF NOT EXISTS twomosi_fk_banner (
                            kID INT AUTO_INCREMENT PRIMARY KEY,
                            cHeadline VARCHAR(255) NOT NULL DEFAULT '',
                            cSub1 VARCHAR(255) NOT NULL DEFAULT '',
                            cSub2 VARCHAR(255) NOT NULL DEFAULT '',
                            cSub3 VARCHAR(255) NOT NULL DEFAULT '',
                            nSub1Active TINYINT(1) NOT NULL DEFAULT 1,
                            nSub2Active TINYINT(1) NOT NULL DEFAULT 1,
                            nSub3Active TINYINT(1) NOT NULL DEFAULT 1,
                            cSub2ButtonLabel VARCHAR(255) NOT NULL DEFAULT 'Aktif et',
                            cSub2ButtonUrl VARCHAR(500) NOT NULL DEFAULT '#',
                            nSegmentWmActive TINYINT(1) NOT NULL DEFAULT 1,
                            nSegmentPkwActive TINYINT(1) NOT NULL DEFAULT 1,
                            nSegmentOrder TINYINT(1) NOT NULL DEFAULT 0,
                            cActiveCategories VARCHAR(1000) NOT NULL DEFAULT '',
                            cSegmentPkwFirstCategories VARCHAR(1000) NOT NULL DEFAULT '',
                            dUpdated DATETIME NOT NULL
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $db->executeQuery($sql);
            } else {
                // Migrate older installs: add columns only if missing (single round-trip per column check batch).
                $cols = $db->getObjects(
                    "SELECT COLUMN_NAME AS n
                       FROM information_schema.columns
                      WHERE table_schema = DATABASE()
                        AND table_name = 'twomosi_fk_banner'"
                );
                $have = [];
                if (\is_array($cols)) {
                    foreach ($cols as $c) {
                        $have[(string)($c->n ?? '')] = true;
                    }
                }
                if (!isset($have['nSub1Active'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN nSub1Active TINYINT(1) NOT NULL DEFAULT 1 AFTER cSub3");
                }
                if (!isset($have['nSub2Active'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN nSub2Active TINYINT(1) NOT NULL DEFAULT 1 AFTER nSub1Active");
                }
                if (!isset($have['nSub3Active'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN nSub3Active TINYINT(1) NOT NULL DEFAULT 1 AFTER nSub2Active");
                }
                if (!isset($have['cSub2ButtonLabel'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN cSub2ButtonLabel VARCHAR(255) NOT NULL DEFAULT 'Aktif et' AFTER nSub3Active");
                }
                if (!isset($have['cSub2ButtonUrl'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN cSub2ButtonUrl VARCHAR(500) NOT NULL DEFAULT '#' AFTER cSub2ButtonLabel");
                }
                if (!isset($have['nSegmentWmActive'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN nSegmentWmActive TINYINT(1) NOT NULL DEFAULT 1 AFTER cSub2ButtonUrl");
                }
                if (!isset($have['nSegmentPkwActive'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN nSegmentPkwActive TINYINT(1) NOT NULL DEFAULT 1 AFTER nSegmentWmActive");
                }
                if (!isset($have['nSegmentOrder'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN nSegmentOrder TINYINT(1) NOT NULL DEFAULT 0 AFTER nSegmentPkwActive");
                }
                if (!isset($have['cActiveCategories'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN cActiveCategories VARCHAR(1000) NOT NULL DEFAULT '' AFTER nSegmentOrder");
                }
                if (!isset($have['cSegmentPkwFirstCategories'])) {
                    $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN cSegmentPkwFirstCategories VARCHAR(1000) NOT NULL DEFAULT '' AFTER cActiveCategories");
                }
            }

            // Seed defaults once (race-safe enough: duplicate rows are harmless; prefer single row)
            $cnt = $db->getSingleObject('SELECT COUNT(*) AS c FROM twomosi_fk_banner');
            if ($cnt !== null && (int)($cnt->c ?? 0) === 0) {
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_fk_banner (cHeadline, cSub1, cSub2, cSub3, nSub1Active, nSub2Active, nSub3Active, cSub2ButtonLabel, cSub2ButtonUrl, nSegmentWmActive, nSegmentPkwActive, dUpdated)
                     VALUES (:h, :s1, :s2, :s3, 1, 1, 1, :btn, :url, 1, 1, NOW())",
                    [
                        'h'  => 'Besuchen Sie uns auf der HanseMesse Rostock',
                        's1' => 'Boot & Angeln Camping',
                        's2' => 'Bremer-Sitzbezüge GmbH | Stand Nr: C19',
                        's3' => '20. bis 22. März 2026 | HanseMesse Rostock',
                        'btn' => 'Aktivieren',
                        'url' => '#',
                    ]
                );
            }

            self::$bannerTableEnsured = true;

            if (\function_exists('apcu_store')) {
                try {
                    @\apcu_store(self::BANNER_SCHEMA_APCU_KEY, true, 604800);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        } catch (\Throwable $e) {
            // Avoid breaking plugin if DB is not available yet.
        }
    }

    /**
     * Nur „Modell nicht in der Liste“ + manueller Text: Warenkorb-Position cName setzen (Anzeige + JTL-Bestellung/Wawi).
     *
     * @param array{kArtikel?:int,oPosition_arr?:mixed} $args
     */
    private function applyWomosiManualModelCartPositionName(array &$args): void
    {
        try {
            $manual = Request::postVar('fk_womosi_manual', '');
            if ($manual !== '1' && \strtolower((string)$manual) !== 'true') {
                return;
            }
            $raw = (string)Request::postVar('fk_womosi_dyn_title', '');
            $dyn = \trim($raw);
            if ($dyn === '') {
                return;
            }
            $dyn = \trim(\preg_replace('/\s+/u', ' ', \strip_tags($dyn)) ?? '');
            if ($dyn === '') {
                return;
            }
            if (\mb_strlen($dyn) > 255) {
                $dyn = \mb_substr($dyn, 0, 252) . '…';
            }
            if (!isset($args['oPosition_arr']) || !\is_array($args['oPosition_arr'])) {
                return;
            }
            $positions = &$args['oPosition_arr'];
            $kArtikel  = (int)($args['kArtikel'] ?? 0);
            if ($kArtikel <= 0) {
                return;
            }
            $target = null;
            for ($i = \count($positions) - 1; $i >= 0; $i--) {
                $p = $positions[$i];
                if (!\is_object($p)) {
                    continue;
                }
                if ((int)$p->nPosTyp !== \C_WARENKORBPOS_TYP_ARTIKEL) {
                    continue;
                }
                if ((int)$p->kArtikel !== $kArtikel) {
                    continue;
                }
                $target = $p;
                break;
            }
            if ($target === null) {
                return;
            }
            if (\is_array($target->cName)) {
                foreach (\array_keys($target->cName) as $code) {
                    $target->cName[$code] = $dyn;
                }
            } else {
                $target->cName = $dyn;
            }
        } catch (\Throwable $e) {
            // absichtlich still
        }
    }

    /**
     * Direkt aus `tplugineinstellungen` (Fallback, falls getConfig im Outputfilter-Kontext leer ist).
     */
    /**
     * Shop-DB (Frontend/Backend konsistent).
     */
    private function fkMicroShopDb(): DbInterface
    {
        return Shop::Container()->getDB();
    }

    private function fkMicroReadCategoriesFromPluginSettingsTable(): string
    {
        try {
            $db  = $this->fkMicroShopDb();
            $row = $db->getSingleObject(
                "SELECT e.cWert AS v
                   FROM tplugin p
                   JOIN tplugineinstellungen e
                     ON e.kPlugin = p.kPlugin
                    AND e.cName = 'fk_micro_active_categories'
                  WHERE p.cPluginID = 'FilterKonfiguratorSearch' OR TRIM(p.cVerzeichnis) = 'FilterKonfiguratorSearch'
                  ORDER BY p.kPlugin DESC
                  LIMIT 1"
            );

            return $row !== null ? trim((string)($row->v ?? '')) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function ensureFkMicroScopeTable(): void
    {
        if (self::$fkMicroScopeTableEnsured) {
            return;
        }
        try {
            $db = $this->fkMicroShopDb();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_micro_scope (
                    kID TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                    cCategoryIds VARCHAR(4000) NOT NULL DEFAULT '',
                    cCategoryIdsPkwFirst VARCHAR(4000) NOT NULL DEFAULT '',
                    dUpdated DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $pkwFirstCol = $db->getSingleObject("SHOW COLUMNS FROM twomosi_fk_micro_scope LIKE 'cCategoryIdsPkwFirst'");
            if ($pkwFirstCol === null) {
                $db->executeQuery("ALTER TABLE twomosi_fk_micro_scope ADD COLUMN cCategoryIdsPkwFirst VARCHAR(4000) NOT NULL DEFAULT '' AFTER cCategoryIds");
            }
            self::$fkMicroScopeTableEnsured = true;
            self::$fkMicroScopeLastErr = '';
        } catch (\Throwable $e) {
            self::$fkMicroScopeLastErr = $e->getMessage();
        }
    }

    private function fkMicroReadScopeTableCsv(): string
    {
        try {
            $db  = $this->fkMicroShopDb();
            $row = $db->getSingleObject('SELECT cCategoryIds FROM twomosi_fk_micro_scope WHERE kID = 1 LIMIT 1');

            return $row !== null ? trim((string)($row->cCategoryIds ?? '')) : '';
        } catch (\Throwable $e) {
            self::$fkMicroScopeLastErr = $e->getMessage();

            return '';
        }
    }

    /**
     * Banner `cActiveCategories` + Plugin-Einstellung `fk_micro_active_categories` zusammenführen (komma/semi/leerzeichen).
     */
    private function fkMicroMergeAllowedCategoriesCsv(): string
    {
        // Reihenfolge: dedizierter Scope (Admin) → Banner → getConfig → SQL-Pluginzeile
        $chunks = ['', '', '', ''];
        $chunks[0] = $this->fkMicroReadScopeTableCsv();
        try {
            $db = $this->fkMicroShopDb();
            // Prefer the most recent NON-empty list (table may have multiple rows)
            $bannerRow = $db->getSingleObject(
                "SELECT cActiveCategories
                   FROM twomosi_fk_banner
                  WHERE TRIM(cActiveCategories) <> ''
               ORDER BY dUpdated DESC, kID DESC
                  LIMIT 1"
            );
            if ($bannerRow === null) {
                $bannerRow = $db->getSingleObject(
                    'SELECT cActiveCategories FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1'
                );
            }
            $chunks[1] = $bannerRow !== null ? trim((string)($bannerRow->cActiveCategories ?? '')) : '';
        } catch (\Throwable $e) {
        }
        try {
            $chunks[2] = trim((string)($this->getPlugin()->getConfig()->getValue('fk_micro_active_categories') ?? ''));
        } catch (\Throwable $e) {
        }
        $chunks[3] = $this->fkMicroReadCategoriesFromPluginSettingsTable();
        $ids = [];
        foreach ($chunks as $c) {
            if ($c === '') {
                continue;
            }
            foreach (preg_split('/[\s,;]+/', $c) as $p) {
                $p = trim((string)$p);
                if ($p === '') {
                    continue;
                }
                $n = (int)$p;
                if ($n > 0) {
                    $ids[$n] = $n;
                }
            }
        }
        $envCsv = \trim((string)(\getenv('FK_MICRO_ALLOWED_CATEGORIES') ?: ''));
        if ($envCsv !== '') {
            foreach (\preg_split('/[\s,;]+/', $envCsv) as $p) {
                $p = \trim((string)$p);
                if ($p === '') {
                    continue;
                }
                $n = (int)$p;
                if ($n > 0) {
                    $ids[$n] = $n;
                }
            }
        }

        return \implode(',', \array_values($ids));
    }

    /**
     * Artikeldetail: erste Kategorie des Artikels (bzw. Vaterartikels) im erlaubten Mikro-Kategoriebaum.
     * Lager-Artikel mit eigenem Fahrzeug-Picker werden übersprungen.
     */
    private function fkMicroResolveProductCategoryId(?JTLSmarty $smarty): int
    {
        if ($smarty === null) {
            return 0;
        }
        $article = $smarty->getTemplateVars('Artikel');
        if (!\is_object($article)) {
            return 0;
        }
        $kArtikel = (int)($article->kArtikel ?? 0);
        $kVater   = (int)($article->kVaterArtikel ?? 0);
        if ($kArtikel <= 0) {
            return 0;
        }
        try {
            if (\class_exists('\Plugin\LagerProdukt\LagerProduktService')
                && \Plugin\LagerProdukt\LagerProduktService::getProductDetailVariantContext((string)($article->cArtNr ?? '')) !== null
            ) {
                return 0;
            }
        } catch (\Throwable $e) {
        }
        $allowedIds = [];
        foreach (preg_split('/[\s,;]+/', $this->fkMicroMergeAllowedCategoriesCsv()) as $p) {
            $n = (int)trim((string)$p);
            if ($n > 0) {
                $allowedIds[$n] = $n;
            }
        }
        if ($allowedIds === []) {
            return 0;
        }
        try {
            $db   = $this->fkMicroShopDb();
            $rows = $db->getObjects(
                'SELECT DISTINCT kKategorie FROM tkategorieartikel WHERE kArtikel IN (:a, :v) LIMIT 50',
                ['a' => $kArtikel, 'v' => $kVater > 0 ? $kVater : $kArtikel]
            );
            foreach ($rows as $row) {
                $cid = (int)($row->kKategorie ?? 0);
                if ($cid > 0 && $this->fkMicroCategoryIsAllowedInTree($db, $cid, \array_values($allowedIds))) {
                    return $cid;
                }
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    /**
     * Artikeldetail: Segment (PKW/Wohnmobil), Serie und PKW-Fahrzeugmerkmale des Artikels als data-Attribute.
     */
    private function fkMicroProductContext(?JTLSmarty $smarty, int $categoryId): string
    {
        $article = $smarty !== null ? $smarty->getTemplateVars('Artikel') : null;
        if (!\is_object($article)) {
            return '';
        }
        $kArtikel = (int)($article->kArtikel ?? 0);
        $kVater   = (int)($article->kVaterArtikel ?? 0);
        $kParent  = $kVater > 0 ? $kVater : $kArtikel;
        $esc = static function (string $s): string {
            return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        };
        try {
            $db = $this->fkMicroShopDb();
        } catch (\Throwable $e) {
            return '';
        }
        $segment = '';
        if ($this->fkMicroCategoryIsAllowedInTree($db, $categoryId, [19])) {
            $segment = 'pkw';
        } elseif ($this->fkMicroCategoryIsAllowedInTree($db, $categoryId, [24])) {
            $segment = 'wohnmobil';
        }
        $langID = 1;
        try {
            $langID = (int)Shop::getLanguageID() ?: 1;
        } catch (\Throwable $e) {
        }

        $serie = '';
        try {
            $row = $db->getSingleObject(
                "SELECT COALESCE(NULLIF(ks.cName, ''), k.cName) AS cName
                   FROM tkategorieartikel ka
                   JOIN tkategorie k ON k.kKategorie = ka.kKategorie
              LEFT JOIN tkategoriesprache ks ON ks.kKategorie = k.kKategorie AND ks.kSprache = :lid
                  WHERE ka.kArtikel IN (:a, :p)
                    AND COALESCE(NULLIF(ks.cName, ''), k.cName) LIKE '%Serie%'
                  LIMIT 1",
                ['lid' => $langID, 'a' => $kArtikel, 'p' => $kParent]
            );
            if ($row !== null) {
                $serie = \trim((string)\preg_replace('/[\s\-]*Serie\s*$/iu', '', (string)$row->cName));
            }
        } catch (\Throwable $e) {
        }

        $vehicle = ['marke' => [], 'modell' => [], 'baujahr' => [], 'basis' => [], 'sitz' => []];
        if ($segment !== '' && $kParent > 0) {
            try {
                $ids = [$kArtikel => $kArtikel, $kParent => $kParent];
                foreach ($db->getObjects('SELECT kArtikel FROM tartikel WHERE kVaterArtikel = :p LIMIT 300', ['p' => $kParent]) as $r) {
                    $ids[(int)$r->kArtikel] = (int)$r->kArtikel;
                }
                $in   = \implode(',', \array_map('intval', $ids));
                $rows = $db->getObjects(
                    "SELECT DISTINCT ms.cName, mws.cWert
                       FROM tartikelmerkmal am
                       JOIN tmerkmalsprache ms ON ms.kMerkmal = am.kMerkmal AND ms.kSprache = :lid
                       JOIN tmerkmalwertsprache mws ON mws.kMerkmalWert = am.kMerkmalWert AND mws.kSprache = :lid
                      WHERE am.kArtikel IN ($in)
                        AND ms.cName IN ('Fahrzeug-Marke', 'Fahrzeug-Modell', 'Baujahr', 'Wohnmobil-Fahrgestell', 'kompatible Sitze')
                      LIMIT 600",
                    ['lid' => $langID]
                );
                $map = [
                    'Fahrzeug-Marke'        => 'marke',
                    'Fahrzeug-Modell'       => 'modell',
                    'Baujahr'               => 'baujahr',
                    'Wohnmobil-Fahrgestell' => 'basis',
                    'kompatible Sitze'      => 'sitz',
                ];
                foreach ($rows as $r) {
                    $key = $map[(string)$r->cName] ?? '';
                    $val = \trim((string)$r->cWert);
                    if ($key !== '' && $val !== '') {
                        $vehicle[$key][$val] = $val;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $attrs = '';
        if ($segment !== '') {
            $attrs .= ' data-fk-page-segment="' . $segment . '"';
        }
        if ($serie !== '') {
            $attrs .= ' data-fk-page-serie="' . $esc($serie) . '"';
        }
        if ($vehicle['marke'] !== [] && $vehicle['modell'] !== []) {
            $json = \json_encode(\array_map('array_values', $vehicle), \JSON_UNESCAPED_UNICODE);
            if (\is_string($json)) {
                $attrs .= ' data-fk-page-vehicle="' . $esc($json) . '"';
            }
        }

        return \trim($attrs);
    }

    /**
     * SEO-Kategorieseiten: zuerst Request, dann Smarty-Blattkategorie; ProductFilter liefert oft die Basis-/Elternkategorie.
     */
    private function fkMicroResolveCurrentCategoryId(?JTLSmarty $smarty): int
    {
        // 0) Explicit request parameter (most deterministic on category pages)
        try {
            $cid = (int)\JTL\Helpers\Request::verifyGPCDataInt(\QUERY_PARAM_CATEGORY);
            if ($cid > 0) {
                return $cid;
            }
        } catch (\Throwable $e) {
        }
        if ($smarty !== null) {
            try {
                $ak = $smarty->getTemplateVars('AktuelleKategorie');
                if (\is_object($ak)) {
                    if (\method_exists($ak, 'getID')) {
                        $id = (int)$ak->getID();
                        if ($id > 0) {
                            return $id;
                        }
                    }
                    if ($ak instanceof \stdClass && isset($ak->kKategorie)) {
                        $id = (int)$ak->kKategorie;
                        if ($id > 0) {
                            return $id;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        try {
            $pf = Shop::getProductFilter();
            if ($pf !== null && $pf->hasCategory()) {
                $v = $pf->getCategory()->getValue();
                if (\is_array($v)) {
                    $v = (int)(\reset($v) ?: 0);
                } elseif (\is_numeric($v)) {
                    $v = (int)$v;
                } else {
                    $v = 0;
                }
                if ($v > 0) {
                    return $v;
                }
            }
        } catch (\Throwable $e) {
        }
        // 1b) Category filter can differ from base category in some routes
        try {
            $pf = Shop::getProductFilter();
            if ($pf !== null && $pf->hasCategoryFilter()) {
                $v = $pf->getCategoryFilter()->getValue();
                if (\is_array($v)) {
                    $v = (int)(\reset($v) ?: 0);
                } elseif (\is_numeric($v)) {
                    $v = (int)$v;
                } else {
                    $v = 0;
                }
                if ($v > 0) {
                    return $v;
                }
            }
        } catch (\Throwable $e) {
        }
        try {
            $cid = (int)Shop::getState()->categoryID;
            if ($cid > 0) {
                return $cid;
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    /**
     * Erlaubt exakte ID oder Unterkategorie (Nested Set: child.lft zwischen parent.lft und parent.rght).
     *
     * @param array<int,int> $allowedRootIds
     */
    private function fkMicroCategoryIsAllowedInTree($db, int $currentCategoryId, array $allowedRootIds): bool
    {
        if ($currentCategoryId <= 0) {
            return false;
        }
        $roots = [];
        foreach ($allowedRootIds as $rid) {
            $rid = (int)$rid;
            if ($rid > 0) {
                $roots[$rid] = $rid;
            }
        }
        if ($roots === []) {
            return false;
        }
        foreach ($roots as $rid) {
            if ($rid === $currentCategoryId) {
                return true;
            }
        }
        $in = \implode(',', $roots);
        try {
            $row = $db->getSingleObject(
                "SELECT 1 AS ok
                   FROM tkategorie AS child
                   JOIN tkategorie AS parent ON parent.kKategorie IN ($in)
                  WHERE child.kKategorie = :cid
                    AND child.lft BETWEEN parent.lft AND parent.rght
                  LIMIT 1",
                ['cid' => $currentCategoryId]
            );

            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Startseite: Sprungmarke zum Konfigurator („Auswahl ändern“ auf der Artikelliste).
     */
    private function fkGetKonfiguratorChangeUrl(): string
    {
        try {
            $u = Shop::getURL();
            $u = \is_string($u) ? \rtrim($u, '/') : '';

            return ($u !== '' ? $u : '') . '/#womosi-fk-konfigurator-anchor';
        } catch (\Throwable $e) {
            return '/#womosi-fk-konfigurator-anchor';
        }
    }

    /**
     * Kompakte Chip-Leiste unter der Produktlisten-Navigation (nur Artikelliste).
     *
     * @param array<int, array{label:string,value:string}> $items
     */
    private function fkBuildMicroFilterHtml(array $items, string $countText = '', string $changeUrl = '', int $currentCategoryId = 0): string
    {
        $esc = static function (string $s): string {
            return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        };

        // Segment flags (PKW/Wohnmobil tabs).
        $segWm    = 1;
        $segPkw   = 1;
        $segOrder = 0; // 0=WM first, 1=PKW first
        try {
            $db = $this->fkMicroShopDb();
            $row = $db->getSingleObject('SELECT nSegmentWmActive, nSegmentPkwActive, nSegmentOrder FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
            if ($row !== null) {
                $segWm    = (int)($row->nSegmentWmActive ?? 1);
                $segPkw   = (int)($row->nSegmentPkwActive ?? 1);
                $segOrder = (int)($row->nSegmentOrder ?? 0);
                if ($segWm !== 1 && $segPkw !== 1) {
                    $segWm = 1;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Category-specific PKW first override
        if ($currentCategoryId > 0 && $segPkw === 1) {
            try {
                $db = $this->fkMicroShopDb();
                $pkwFirstCsv = '';
                $pkwFirstRow = $db->getSingleObject('SELECT cCategoryIdsPkwFirst FROM twomosi_fk_micro_scope WHERE kID = 1 LIMIT 1');
                if ($pkwFirstRow !== null) {
                    $pkwFirstCsv = trim((string)($pkwFirstRow->cCategoryIdsPkwFirst ?? ''));
                }
                if ($pkwFirstCsv === '') {
                    $bannerRow = $db->getSingleObject("SELECT cSegmentPkwFirstCategories FROM twomosi_fk_banner WHERE TRIM(cSegmentPkwFirstCategories) <> '' ORDER BY dUpdated DESC, kID DESC LIMIT 1");
                    $pkwFirstCsv = $bannerRow !== null ? trim((string)($bannerRow->cSegmentPkwFirstCategories ?? '')) : '';
                }
                if ($pkwFirstCsv === '') {
                    $pkwFirstCsv = trim((string)($this->getPlugin()->getConfig()->getValue('fk_micro_pkw_first_categories') ?? ''));
                }

                if ($pkwFirstCsv !== '') {
                    $pkwFirstIds = [];
                    foreach (preg_split('/[\s,;]+/', $pkwFirstCsv) as $p) {
                        $p = trim((string)$p);
                        if ($p !== '') {
                            $pkwFirstIds[] = (int)$p;
                        }
                    }
                    if (!empty($pkwFirstIds) && $this->fkMicroCategoryIsAllowedInTree($db, $currentCategoryId, $pkwFirstIds)) {
                        $segOrder = 1; // Force PKW first
                    }
                }
            } catch (\Throwable $e) {}
        }

        $tabs = [];
        if ($segWm === 1) {
            $tabs[] = [
                'id' => 'wohnmobil',
                'label' => 'Wohnmobil',
                'class' => 'fk-micro-tab--wm',
                'allowed' => 1,
                'order' => ($segOrder === 0 ? 0 : 1)
            ];
        }
        if ($segPkw === 1) {
            $tabs[] = [
                'id' => 'pkw',
                'label' => 'PKW',
                'class' => 'fk-micro-tab--pkw',
                'allowed' => 1,
                'order' => ($segOrder === 1 ? 0 : 1)
            ];
        }

        // Sort tabs by order
        \usort($tabs, static function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $tabsHtml = '';
        if (\count($tabs) > 1) {
            $tabsHtml .= '<div class="fk-selection-micro-tabsbar" role="tablist" aria-label="Segment">';
            foreach ($tabs as $idx => $tab) {
                $sel = ($idx === 0) ? 'true' : 'false';
                $tabsHtml .= '<button type="button" class="fk-micro-tab ' . $tab['class'] . '" role="tab" aria-selected="' . $sel . '" data-fk-micro-segment="' . $tab['id'] . '" data-fk-seg-allowed="' . $tab['allowed'] . '">' . $tab['label'] . '</button>';
            }
            $tabsHtml .= '</div>';
        }

        return '<div class="fk-selection-micro fk-selection-micro--dynamic" data-fk-micro="1" data-fk-micro-dynamic="1" role="region" aria-label="Konfigurator-Auswahl">'
            . $tabsHtml
            . '<div class="fk-selection-micro-inner">'
            . '<div id="fkMicroStatus" class="fk-micro-status-wrap"></div>'
            . '<div class="fk-micro-form fk-micro-form--seat fk-micro-field--wm" aria-label="Sitz auswählen">'
            . '<div class="fk-micro-field"><label for="fkMicroSitz">Sitz <span class="fk-required" aria-hidden="true">*</span></label><select id="fkMicroSitz" class="custom-select custom-select-sm" required aria-required="true"><option value="">Bitte wählen</option></select></div>'
            . '</div>'
            . '<div class="fk-micro-serie-field"><label for="fkMicroSerie">Serie <span class="fk-required" aria-hidden="true">*</span></label>'
            . '<select id="fkMicroSerie" class="custom-select custom-select-sm" required aria-required="true" disabled style="display:none;"><option value="">Bitte wählen</option></select>'
            . '<div id="fkMicroSerieGrid" class="fk-micro-serie-grid"></div>'
            . '</div>'
            . '<div class="fk-micro-form fk-micro-form--vehicle" aria-label="Fahrzeug auswählen">'
            . '<div class="fk-micro-field fk-micro-field--wm" style="display:none;"><label for="fkMicroTyp">Typ</label><select id="fkMicroTyp" class="custom-select custom-select-sm"><option value="">Bitte wählen</option></select></div>'
            . '<div class="fk-micro-field"><label for="fkMicroHersteller">Marke <span class="fk-required" aria-hidden="true">*</span></label><select id="fkMicroHersteller" class="custom-select custom-select-sm" required aria-required="true"><option value="">Bitte wählen</option></select></div>'
            . '<div class="fk-micro-field fk-micro-field--wm"><label for="fkMicroBasis">Fahrgestell <span class="fk-required" aria-hidden="true">*</span></label><select id="fkMicroBasis" class="custom-select custom-select-sm" required aria-required="true"><option value="">Bitte wählen</option></select></div>'
            . '<div class="fk-micro-field"><label for="fkMicroModell">Modell <span class="fk-required" aria-hidden="true">*</span></label><select id="fkMicroModell" class="custom-select custom-select-sm" required aria-required="true"><option value="">Bitte wählen</option></select></div>'
            . '<div class="fk-micro-field fk-micro-field--pkw"><label for="fkMicroBaujahr">Baujahr</label><select id="fkMicroBaujahr" class="custom-select custom-select-sm"><option value="">Bitte wählen</option></select></div>'
            . '</div>'
            . '</div>'
            . '</div></div>';
    }

    private function fkMicroChipLabelShort(string $label): string
    {
        static $map = [
            'Fahrzeugtyp'           => 'Typ',
            'Fahrzeug-Marke'        => 'Marke',
            'Wohnmobil-Fahrgestell' => 'Gestell',
            'Fahrzeug-Modell'       => 'Modell',
            'Baujahr'               => 'Bj.',
            'kompatible Sitze'      => 'Sitze',
            'Serie'                 => 'Serie',
        ];

        return $map[$label] ?? $label;
    }

    private function fkSeatDisplayName(string $value): string
    {
        $code = \trim($value);
        if (\preg_match('/^\d{1,2}$/', $code) === 1) {
            $code = (string)(int)$code;
        }
        $names = [
            '1'  => 'Aguti Milan 12',
            '2'  => 'Aguti Liner 12',
            '3'  => 'ISRI Captain Chair',
            '4'  => 'ISRI CC mit Gurt',
            '5'  => 'ISRI Ducato / Jumper / Boxer, Bj. 2006–2014',
            '6'  => 'Aguti Milan',
            '7'  => 'Aguti GIS-Liner',
            '8'  => 'Aguti GIS-Liner 3',
            '9'  => 'Ford Transit ab Bj. 2014',
            '10' => 'Ford Transit ab Bj. 2014 mit integrierter Kopfstütze',
            '11' => 'Aguti Milan 3',
            '12' => 'SKA 1800 M23 Komfort',
            '13' => 'SKA 1800/1000 M23 Classic',
            '14' => 'Mercedes-Benz Sprinter / Hymer',
        ];

        return $names[$code] ?? $value;
    }

    /**
     * Eine Zeile für H1: Fahrzeugtyp, Marke, Fahrgestell, Modell, optional „Model {Baujahr}“ (ohne Serie/Sitze).
     *
     * @param array<int, array{label:string,value:string}> $items
     */
    private function fkBuildCompactTitleLine(array $items): string
    {
        $byLabel = [];
        foreach ($items as $it) {
            $l = (string)($it['label'] ?? '');
            $byLabel[$l] = \trim((string)($it['value'] ?? ''));
        }
        $order = ['Fahrzeugtyp', 'Fahrzeug-Marke', 'Wohnmobil-Fahrgestell', 'Fahrzeug-Modell', 'Baujahr'];
        $parts = [];
        foreach ($order as $label) {
            $val = $byLabel[$label] ?? '';
            if ($val === '') {
                continue;
            }
            if ($label === 'Baujahr') {
                $parts[] = 'Model ' . $val;
            } else {
                $parts[] = $val;
            }
        }

        return \trim(\implode(' ', $parts));
    }

    /**
     * Auswahl aus ?typ=&hersteller=… oder aus SEO-Pfad /Serie__…__… (FilterKonfiguratorController::findSeriesForValues).
     *
     * @return array<int, array{label:string,value:string}>
     */
    private function fkBuildSelectionItemsFromRequest(): array
    {
        $get = static function (string $key): string {
            try {
                $v = Request::getVar($key, '');
                return \is_string($v) ? trim($v) : trim((string)$v);
            } catch (\Throwable $e) {
                $v = $_GET[$key] ?? '';
                return \is_string($v) ? trim($v) : trim((string)$v);
            }
        };

        $map = [
            'typ'        => 'Fahrzeugtyp',
            'hersteller' => 'Fahrzeug-Marke',
            'basis'      => 'Wohnmobil-Fahrgestell',
            'modell'     => 'Fahrzeug-Modell',
            'baujahr'    => 'Baujahr',
            'sitz'       => 'kompatible Sitze',
            'serie'      => 'Serie',
        ];

        $items = [];
        foreach ($map as $key => $label) {
            $val = $get($key);
            if ($val !== '') {
                if ($key === 'sitz') {
                    $val = $this->fkSeatDisplayName($val);
                }
                $items[] = ['label' => $label, 'value' => $val];
            }
        }
        if ($items !== []) {
            return $items;
        }

        return $this->fkBuildSelectionItemsFromSeoPath();
    }

    /**
     * Pfad z. B. /DPL__Kastenwagen__Adria__Twin-Axess-…__Fiat-Ducato-250__5_6
     * Reihenfolge wie im Plugin: Serie, typ, Marke, Modell, Fahrgestell, Sitze, optional Baujahr.
     *
     * @return array<int, array{label:string,value:string}>
     */
    private function fkBuildSelectionItemsFromSeoPath(): array
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $path = \trim($path, '/');
        if ($path === '' || \strpos($path, '__') === false) {
            return [];
        }
        $parts = \explode('__', $path);
        $parts = \array_values(\array_filter($parts, static fn($p) => \trim((string)$p) !== ''));
        if (\count($parts) < 2) {
            return [];
        }

        $labels = [
            'Serie',
            'Fahrzeugtyp',
            'Fahrzeug-Marke',
            'Fahrzeug-Modell',
            'Wohnmobil-Fahrgestell',
            'kompatible Sitze',
            'Baujahr',
        ];

        $items = [];
        foreach ($parts as $i => $raw) {
            if (!isset($labels[$i])) {
                break;
            }
            $human = $this->fkHumanizeSeoSegment((string)$raw, $i);
            if ($human !== '') {
                $items[] = ['label' => $labels[$i], 'value' => $human];
            }
        }

        return $items;
    }

    private function fkHumanizeSeoSegment(string $segment, int $index): string
    {
        $segment = \trim($segment);
        if ($segment === '') {
            return '';
        }
        // Sitze: z. B. 5_6 → 5 / 6
        if ($index === 5) {
            $s = \str_replace('-', ' ', $segment);

            return \trim(\str_replace('_', ' / ', $s));
        }

        return \trim(\preg_replace('/\s+/', ' ', \str_replace('-', ' ', $segment)) ?? '');
    }

    /**
     * Lädt twomosi_fk_serie_meta und setzt window.FK_SERIES_CONFIG im &lt;head&gt; (vor den Plugin-JS-Dateien).
     *
     * @param \JTL\phpQuery\phpQueryObject|\phpQueryObject|mixed $doc
     */
    private function injectFkSeriesConfigInDocumentHead($doc): void
    {
        try {
            $head = $doc->find('head')->eq(0);
            if ($head->length === 0) {
                return;
            }
            $headInner = (string)$head->html();
            if (\stripos($headInner, 'FK_SERIES_CONFIG') !== false) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $payload  = SeriesMetaRuntime::buildPayload($db);
            $json     = \json_encode(
                $payload,
                \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS
            );
            if ($json === false) {
                return;
            }
            $script = '<script>window.FK_SERIES_CONFIG=' . $json . ';</script>';
            $head->prepend($script);
        } catch (\Throwable $e) {
        }
    }

    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }

            $file = 'dashboard.php';

            $exists = $db->getSingleObject(
                "SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin
                    AND cDateiname = :cDateiname
                  LIMIT 1",
                [
                    'kPlugin'    => $pluginID,
                    'cDateiname' => $file,
                ]
            );

            if ($exists !== null) {
                self::$adminMenuEnsured = true;
                return;
            }

            $menuItem = new \stdClass();
            $menuItem->kPlugin    = $pluginID;
            $menuItem->cName      = 'FilterKonfigurator Dashboard';
            $menuItem->cDateiname = $file;
            $menuItem->nSort      = 20;
            $menuItem->nConf      = 0;

            $db->insert('tpluginadminmenu', $menuItem);
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // No hard failure for menu insertion.
        }
    }

    private function registerCharacteristicOptionsCache(Dispatcher $dispatcher): void
    {
        // 1.14.47: Shop-Cache-Austausch und ALTER TABLE in boot() erzeugten HTTP 500.
        // Schutz bleibt vorerst deaktiviert, bis die Seite wieder läuft.
    }
}

