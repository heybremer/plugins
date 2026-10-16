<?php

declare(strict_types=1);

namespace Plugin\BremerDesc;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    /* ── Migration + Admin Menu: install/update hook'larında da çalıştır ── */
    public function installed(): void
    {
        parent::installed();
        $this->runMigration();
        $this->ensureAdminMenuEntry();
    }

    public function updated($oldVersion, $newVersion): void
    {
        parent::updated($oldVersion, $newVersion);
        $this->runMigration();
        $this->ensureAdminMenuEntry();
    }

    /* ── Boot ─────────────────────────────────────────────────── */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        // Admin menü kaydını sağla (her boot'ta — ucuz DB check)
        $this->ensureAdminMenuEntry();

        // Migration'ı sadece tablo yoksa çalıştır (ucuz bir kontrol)
        $this->ensureTableExists();

        $dispatcher->hookInto(\HOOK_CSS_JS_LIST, function (array &$args): void {
            if ($this->isBackendRequest()) {
                return;
            }
            if (!isset($args['groups']) || !\is_array($args['groups'])) {
                return;
            }
            $this->registerAssetsInMinifyGroups($args['groups']);
        });

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if ($this->isBackendRequest()) {
                return;
            }
            $pageType = Shop::getPageType();
            if ($pageType !== \PAGE_ARTIKEL && $pageType !== \PAGE_STARTSEITE) {
                return;
            }
            if (!$this->hasActiveShowcaseForPage($pageType)) {
                return;
            }
            $this->applyVersionedAssetsToMinifyArrays($args);
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            $pageType = Shop::getPageType();
            if ($pageType !== \PAGE_ARTIKEL && $pageType !== \PAGE_STARTSEITE) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            if ($pageType === \PAGE_ARTIKEL) {
                $this->injectArtikel($smarty, $doc);
            } else {
                $this->injectHomepage($smarty, $doc);
            }
        });
    }

    /* ══════════════════════════════════════════════════════════
       TABLO KONTROLÜ — sadece yoksa CREATE, migration sonrası
    ══════════════════════════════════════════════════════════ */

    /**
     * Runtime'da sadece tablonun var olup olmadığını kontrol eder.
     * CREATE IF NOT EXISTS her boot'ta çalıştırmak yerine
     * bir kez kontrol edip cache'leriz.
     */
    /* ══════════════════════════════════════════════════════════
       ADMIN MENU ENTRY
    ══════════════════════════════════════════════════════════ */
    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }
        try {
            $db       = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }
            $file   = 'dashboard.php';
            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin    = :kPlugin
                    AND cDateiname = :cDateiname
                  LIMIT 1',
                ['kPlugin' => $pluginID, 'cDateiname' => $file]
            );
            if ($exists === null) {
                $menuItem              = new \stdClass();
                $menuItem->kPlugin    = $pluginID;
                $menuItem->cName      = 'BremerDesc Dashboard';
                $menuItem->cDateiname = $file;
                $menuItem->nSort      = 30;
                $menuItem->nConf      = 0;
                $db->insert('tpluginadminmenu', $menuItem);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // No hard fail — admin menü olmasa da frontend çalışmaya devam eder
        }
    }

    private function ensureTableExists(): void
    {
        try {
            $db = Shop::Container()->getDB();
            $exists = $db->getSingleObject(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'twomosi_bremer_desc'
                 LIMIT 1"
            );
            if ($exists === null) {
                $this->runMigration();
            }
        } catch (\Throwable $e) {
            // ignore — tablo yoksa inject zaten boş döner
        }
    }

    private function runMigration(): void
    {
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_bremer_desc (
                    kID              INT AUTO_INCREMENT PRIMARY KEY,
                    cType            VARCHAR(20)  NOT NULL DEFAULT "artikel",
                    kArtikel         INT          NOT NULL DEFAULT 0,
                    nActive          TINYINT(1)   NOT NULL DEFAULT 1,
                    cVideoUrl        VARCHAR(512) NOT NULL DEFAULT "",
                    cBilderTabLabel  VARCHAR(120) NOT NULL DEFAULT "Produktbilder",
                    cImagesJson      LONGTEXT     NULL,
                    cTabsJson        LONGTEXT     NULL,
                    cCategoriesJson  LONGTEXT     NULL,
                    cInjectPosition  VARCHAR(200) NOT NULL DEFAULT "content_top",
                    dUpdated         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_type_artikel (cType, kArtikel)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            // Güvenli ALTER: sadece eksik sütunları ekle
            $existingCols = [];
            $colRows = $db->getObjects('SHOW COLUMNS FROM twomosi_bremer_desc') ?: [];
            foreach ($colRows as $c) {
                $existingCols[] = strtolower((string)($c->Field ?? ''));
            }
            $needed = [
                'cTabsJson'       => 'LONGTEXT NULL',
                'cCategoriesJson' => 'LONGTEXT NULL',
                'cInjectPosition' => 'VARCHAR(200) NOT NULL DEFAULT "content_top"',
            ];
            foreach ($needed as $col => $def) {
                if (!\in_array(strtolower($col), $existingCols, true)) {
                    try {
                        $db->executeQuery("ALTER TABLE twomosi_bremer_desc ADD COLUMN `{$col}` {$def}");
                    } catch (\Throwable $e) {}
                }
            }
        } catch (\Throwable $e) {}
    }

    /* ══════════════════════════════════════════════════════════
       HIZLI KONTROL — CSS/JS yüklemek için showcase aktif mi?
    ══════════════════════════════════════════════════════════ */

    /**
     * Mevcut sayfa için aktif bir showcase kaydı var mı?
     * Sadece COUNT sorgusu — çok ucuz.
     */
    private function hasActiveShowcaseForPage(int $pageType): bool
    {
        try {
            $db = Shop::Container()->getDB();

            if ($pageType === \PAGE_STARTSEITE) {
                $r = $db->getSingleObject(
                    'SELECT 1 FROM twomosi_bremer_desc
                     WHERE cType = "homepage" AND nActive = 1 LIMIT 1'
                );
                return $r !== null;
            }

            // PAGE_ARTIKEL: direkt kayıt veya aktif kategori kaydı var mı?
            $r = $db->getSingleObject(
                'SELECT 1 FROM twomosi_bremer_desc
                 WHERE nActive = 1
                   AND cType IN ("artikel","kategorie")
                 LIMIT 1'
            );
            return $r !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /* ══════════════════════════════════════════════════════════
       INJECT: ARTIKEL
    ══════════════════════════════════════════════════════════ */

    private function injectArtikel(JTLSmarty $smarty, object $doc): void
    {
        $artikel  = $smarty->getTemplateVars('Artikel');
        $kArtikel = 0;
        if (\is_object($artikel) && isset($artikel->kArtikel)) {
            $kArtikel = (int)$artikel->kArtikel;
        }
        if ($kArtikel <= 0) {
            return;
        }

        $row = $this->findRowForArtikel($kArtikel);

        if ($row === null || (int)($row->nActive ?? 0) !== 1) {
            return;
        }

        $html = $this->renderHtml($smarty, $row, $kArtikel, false);
        if ($html === '') {
            return;
        }

        // Ürün sayfası: .product-attributes div'inin hemen üstüne
        foreach ([
            '.product-attributes',
            '#product-attributes',
            'div.product-attributes',
        ] as $sel) {
            $el = $doc->find($sel)->eq(0);
            if ($el->length > 0) {
                $el->before($html);
                return;
            }
        }

        // Fallback: tab-navigation sonrası
        $tabNav = $doc->find('.tab-navigation')->eq(0);
        if ($tabNav->length > 0) {
            $tabNav->after($html);
            return;
        }

        $tabContent = $doc->find('.tab-content')->eq(0);
        if ($tabContent->length > 0) {
            $tabContent->after($html);
            return;
        }

        $productOffer = $doc->find('#product-offer')->eq(0);
        if ($productOffer->length > 0) {
            $productOffer->after($html);
        }
    }

    /**
     * Bir ürün için showcase kaydını bulur.
     * Öncelik: direkt artikel kaydı > kategori kaydı.
     *
     * Kategori kayıt modeli (yeni): cType='kategorie', kArtikel=kKategorie
     * Eski fallback: cCategoriesJson içinde kategori ID listesi
     */
    private function findRowForArtikel(int $kArtikel): ?\stdClass
    {
        try {
            $db = Shop::Container()->getDB();

            // 1. Direkt kayıt
            $row = $db->getSingleObject(
                'SELECT * FROM twomosi_bremer_desc
                 WHERE cType = "artikel" AND kArtikel = :k AND nActive = 1
                 LIMIT 1',
                ['k' => $kArtikel]
            );
            if ($row !== null) {
                return $row;
            }

            // 2. Ürünün kategorilerini bul
            $catRows = $db->getObjects(
                'SELECT kKategorie FROM tkategorieartikel WHERE kArtikel = :k',
                ['k' => $kArtikel]
            );
            if (empty($catRows)) {
                return null;
            }
            $directCatIds = \array_map(static fn($r) => (int)$r->kKategorie, $catRows);

            // 2a. Yeni model: ürünün kategorisi + tüm üst kategoriler
            $row = $this->findCategoryRowForProduct($db, $directCatIds);
            if ($row !== null) {
                return $row;
            }

            // 2b. Eski model fallback: cCategoriesJson içinde kategori ID listesi
            $allCatIds = $this->expandCategoryIdsWithAncestors($db, $directCatIds);
            $konfigs = $db->getObjects(
                "SELECT * FROM twomosi_bremer_desc
                 WHERE cType = 'kategorie' AND nActive = 1
                   AND cCategoriesJson IS NOT NULL AND cCategoriesJson != ''"
            ) ?: [];
            foreach ($konfigs as $konfig) {
                $saved = \json_decode((string)($konfig->cCategoriesJson ?? ''), true);
                if (!\is_array($saved) || empty($saved)) {
                    continue;
                }
                $savedIds = \array_map('intval', $saved);
                if (!empty(\array_intersect($allCatIds, $savedIds))) {
                    return $konfig;
                }
            }
        } catch (\Throwable $e) {}

        return null;
    }

    /**
     * Ürünün doğrudan kategorileri + üst kategori zinciri.
     *
     * @param int[] $catIds
     * @return int[]
     */
    private function expandCategoryIdsWithAncestors(\JTL\DB\DbInterface $db, array $catIds): array
    {
        $catIds = \array_values(\array_unique(\array_filter(\array_map('intval', $catIds))));
        if (empty($catIds)) {
            return [];
        }

        $parentMap = $this->getCategoryParentMap($db);
        $all       = [];

        foreach ($catIds as $catId) {
            $cur   = $catId;
            $guard = 0;
            while ($cur > 0 && $guard < 64) {
                $all[$cur] = true;
                $parent    = $parentMap[$cur] ?? 0;
                if ($parent <= 0) {
                    break;
                }
                $cur = $parent;
                $guard++;
            }
        }

        return \array_map('intval', \array_keys($all));
    }

    /**
     * @return array<int, int> kKategorie => kOberKategorie
     */
    private function getCategoryParentMap(\JTL\DB\DbInterface $db): array
    {
        static $cache = null;
        if (\is_array($cache)) {
            return $cache;
        }

        $cache = [];
        try {
            $rows = $db->getObjects('SELECT kKategorie, kOberKategorie FROM tkategorie') ?: [];
            foreach ($rows as $row) {
                $cache[(int)$row->kKategorie] = (int)$row->kOberKategorie;
            }
        } catch (\Throwable $e) {
            $cache = [];
        }

        return $cache;
    }

    /**
     * Kategori showcase kaydı bulur.
     * Öncelik: doğrudan ürün kategorisi > en yakın üst kategori.
     *
     * @param int[] $directCatIds
     */
    private function findCategoryRowForProduct(\JTL\DB\DbInterface $db, array $directCatIds): ?\stdClass
    {
        $directCatIds = \array_values(\array_unique(\array_filter(\array_map('intval', $directCatIds))));
        if (empty($directCatIds)) {
            return null;
        }

        $allCatIds = $this->expandCategoryIdsWithAncestors($db, $directCatIds);
        if (empty($allCatIds)) {
            return null;
        }

        $inList = \implode(',', $allCatIds);
        $rows   = $db->getObjects(
            "SELECT * FROM twomosi_bremer_desc
             WHERE cType = 'kategorie'
               AND nActive = 1
               AND kArtikel IN ({$inList})"
        ) ?: [];
        if (empty($rows)) {
            return null;
        }

        $byCatId = [];
        foreach ($rows as $row) {
            $byCatId[(int)$row->kArtikel] = $row;
        }

        // Önce ürünün doğrudan kategorilerindeki kayıt
        foreach ($directCatIds as $catId) {
            if (isset($byCatId[$catId])) {
                return $byCatId[$catId];
            }
        }

        // Sonra üst kategori zincirinde en yakın eşleşme
        $parentMap = $this->getCategoryParentMap($db);
        foreach ($directCatIds as $catId) {
            $cur   = $catId;
            $guard = 0;
            while ($cur > 0 && $guard < 64) {
                $parent = $parentMap[$cur] ?? 0;
                if ($parent <= 0) {
                    break;
                }
                if (isset($byCatId[$parent])) {
                    return $byCatId[$parent];
                }
                $cur = $parent;
                $guard++;
            }
        }

        return null;
    }

    /* ══════════════════════════════════════════════════════════
       INJECT: HOMEPAGE
    ══════════════════════════════════════════════════════════ */

    private function injectHomepage(JTLSmarty $smarty, object $doc): void
    {
        try {
            $db  = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                'SELECT * FROM twomosi_bremer_desc
                 WHERE cType = "homepage" AND kArtikel = 0 AND nActive = 1
                 LIMIT 1'
            );
        } catch (\Throwable $e) {
            return;
        }
        if (!isset($row) || $row === null) {
            return;
        }

        $html = $this->renderHtml($smarty, $row, 0, true);
        if ($html === '') {
            return;
        }

        $position = \trim((string)($row->cInjectPosition ?? 'content_top'));
        $this->injectByPosition($doc, $html, $position);
    }

    private function normalizeCustomSelector(string $raw): string
    {
        $s = \trim($raw);
        while (\str_starts_with(\strtolower($s), 'custom:')) {
            $s = \trim(\substr($s, 7));
        }

        return $s;
    }

    private function injectByPosition(object $doc, string $html, string $position): void
    {
        if (\str_starts_with($position, 'custom:')) {
            $selector = $this->normalizeCustomSelector(\substr($position, 7));
            if ($selector === '') {
                $html = '<!-- BremerDesc: custom CSS selector empty (fallback content_top) -->' . "\n" . $html;
            } else {
                $el = $doc->find($selector)->eq(0);
                if ($el->length > 0) {
                    $el->after($html);
                    return;
                }
                $safe = \htmlspecialchars($selector, ENT_QUOTES, 'UTF-8');
                $html = '<!-- BremerDesc: custom selector not found: ' . $safe . ' (fallback content_top) -->' . "\n" . $html;
            }
        }

        switch ($position) {
            case 'after_header':
                $el = $doc->find('header, .header, #header')->eq(0);
                if ($el->length > 0) { $el->after($html); return; }
                break;
            case 'before_footer':
                $el = $doc->find('footer, .footer, #footer')->eq(0);
                if ($el->length > 0) { $el->before($html); return; }
                break;
            case 'after_hero':
                $el = $doc->find('.hero, .slider, .cms-banner, .startseite-slider')->eq(0);
                if ($el->length > 0) { $el->after($html); return; }
                break;
            case 'after_cms':
                $el = $doc->find('.cms-content, .cmszone, .cms-zone')->eq(0);
                if ($el->length > 0) { $el->after($html); return; }
                break;
            case 'content_bottom':
                $el = $doc->find('main .container, main, #content, .content-wrapper')->eq(0);
                if ($el->length > 0) { $el->append($html); return; }
                break;
            case 'content_top':
            default:
                $el = $doc->find('main .container, main, #content, .content-wrapper')->eq(0);
                if ($el->length > 0) { $el->prepend($html); return; }
                break;
        }

        $body = $doc->find('body')->eq(0);
        if ($body->length > 0) { $body->prepend($html); }
    }

    /* ══════════════════════════════════════════════════════════
       RENDER HTML
    ══════════════════════════════════════════════════════════ */

    private function renderHtml(JTLSmarty $smarty, \stdClass $row, int $kArtikel, bool $useContainer = false): string
    {
        $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/bremer-desc.tpl';
        if (!\is_file($tplPath)) {
            return '';
        }

        $tabs = $this->buildTabs($row);
        if (empty($tabs)) {
            return '';
        }

        $smarty->assign('bdKArtikel', $kArtikel);
        $smarty->assign('bdTabs', $tabs);
        $smarty->assign('bdUseContainer', $useContainer);

        try {
            return $smarty->fetch('file:' . $tplPath);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * DB row'undan tab dizisi oluşturur.
     * Geriye dönük uyumluluk: eski cVideoUrl/cImagesJson alanlarını da okur.
     */
    private function buildTabs(\stdClass $row): array
    {
        $tabsRaw = \trim((string)($row->cTabsJson ?? ''));
        $tabs    = [];

        if ($tabsRaw !== '') {
            $decoded = \json_decode($tabsRaw, true);
            if (\is_array($decoded) && !empty($decoded)) {
                foreach ($decoded as &$tab) {
                    if (($tab['type'] ?? '') === 'video') {
                        $tab['videoId'] = $this->extractYoutubeId(
                            (string)($tab['videoUrl'] ?? $tab['videoId'] ?? '')
                        );
                    } elseif (!isset($tab['images']) || !\is_array($tab['images'])) {
                        $tab['images'] = [];
                    }
                }
                unset($tab);
                return $decoded;
            }
        }

        // Legacy fallback
        $videoId = $this->extractYoutubeId(\trim((string)($row->cVideoUrl ?? '')));
        if ($videoId !== '') {
            $tabs[] = ['type' => 'video', 'label' => 'Video', 'videoId' => $videoId, 'images' => []];
        }
        $images = $this->decodeImages(\trim((string)($row->cImagesJson ?? '')));
        if (!empty($images)) {
            $label  = \trim((string)($row->cBilderTabLabel ?? '')) ?: 'Produktbilder';
            $tabs[] = ['type' => 'images', 'label' => $label, 'videoId' => '', 'images' => $images];
        }

        return $tabs;
    }

    /* ══════════════════════════════════════════════════════════
       ASSET VERSIONING (Cloudflare / cookie cache bypass)
    ══════════════════════════════════════════════════════════ */

    /** @return array{css: string, js: string} */
    private function getAssetRelativePaths(): array
    {
        return [
            'css' => 'plugins/BremerDesc/templates/css/bremer-desc.css',
            'js'  => 'plugins/BremerDesc/templates/js/bremer-desc.js',
        ];
    }

    private function resolveAssetAbsolutePath(string $relativePath): string
    {
        return \PFAD_ROOT . \ltrim($relativePath, '/');
    }

    private function getAssetFilemtimeVersion(string $relativePath): int
    {
        $absolutePath = $this->resolveAssetAbsolutePath($relativePath);

        return \is_file($absolutePath) ? (int)\filemtime($absolutePath) : 0;
    }

    private function appendAssetVersionQuery(string $relativePath): string
    {
        $mtime = $this->getAssetFilemtimeVersion($relativePath);
        if ($mtime <= 0) {
            return $relativePath;
        }

        return $relativePath . '?v=' . $mtime;
    }

    private function stripAssetVersionQuery(string $path): string
    {
        $queryPos = \strpos($path, '?');
        if ($queryPos === false) {
            return $path;
        }

        return \substr($path, 0, $queryPos);
    }

    private function pluginAssetPathsMatch(string $entry, string $baseRelativePath): bool
    {
        return $this->stripAssetVersionQuery($entry) === $baseRelativePath;
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false;
    }

    /**
     * @param array<string, array<int, array{idx: string, abs: string, rel: string, vsn?: string}>> $groups
     */
    private function registerAssetsInMinifyGroups(array &$groups): void
    {
        $paths = $this->getAssetRelativePaths();

        if (!isset($groups['plugin_css']) || !\is_array($groups['plugin_css'])) {
            $groups['plugin_css'] = [];
        }
        if (!isset($groups['plugin_js_head']) || !\is_array($groups['plugin_js_head'])) {
            $groups['plugin_js_head'] = [];
        }

        $cssAbs = $this->resolveAssetAbsolutePath($paths['css']);
        if (\is_file($cssAbs)) {
            $groups['plugin_css'][] = [
                'idx' => 'BremerDesc-css',
                'abs' => $cssAbs,
                'rel' => $paths['css'],
                'vsn' => (string)\filemtime($cssAbs),
            ];
        }

        $jsAbs = $this->resolveAssetAbsolutePath($paths['js']);
        if (\is_file($jsAbs)) {
            $groups['plugin_js_head'][] = [
                'idx' => 'BremerDesc-js',
                'abs' => $jsAbs,
                'rel' => $paths['js'],
                'vsn' => (string)\filemtime($jsAbs),
            ];
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyVersionedAssetsToMinifyArrays(array &$args): void
    {
        $paths = $this->getAssetRelativePaths();
        $map   = [
            'cPluginCss_arr'    => $paths['css'],
            'cPluginJsHead_arr' => $paths['js'],
        ];

        foreach ($map as $argKey => $relativePath) {
            if (!isset($args[$argKey]) || !\is_array($args[$argKey])) {
                $args[$argKey] = [];
            }
            $versioned = $this->appendAssetVersionQuery($relativePath);
            $found     = false;
            foreach ($args[$argKey] as $index => $entry) {
                if (!\is_string($entry)) {
                    continue;
                }
                if ($this->pluginAssetPathsMatch($entry, $relativePath)) {
                    $args[$argKey][$index] = $versioned;
                    $found                 = true;
                    break;
                }
            }
            if (!$found) {
                $args[$argKey][] = $versioned;
            }
        }
    }

    /* ══════════════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════════════ */

    private function extractYoutubeId(string $url): string
    {
        if ($url === '') {
            return '';
        }
        if (\preg_match(
            '/(?:youtu\.be\/|youtube\.com\/(?:embed\/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{11})/',
            $url, $m
        )) {
            return $m[1];
        }
        if (\preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }
        return '';
    }

    /** @return list<string> */
    private function decodeImages(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $a = \json_decode($json, true);
        if (!\is_array($a)) {
            return [];
        }
        $out = [];
        foreach ($a as $v) {
            $u = \trim((string)$v);
            if ($u !== '') {
                $out[] = $u;
            }
        }
        return $out;
    }
}
