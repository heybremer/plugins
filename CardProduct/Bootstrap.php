<?php

namespace Plugin\CardProduct;

require_once __DIR__ . '/src/CardProductDatabase.php';
require_once __DIR__ . '/src/CardProductArticleHelper.php';

use JTL\Events\Dispatcher;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private const TPL_KEY = 'womosi_cardproduct';

    private const TPL_FILE = 'cardproduct_grid.tpl';

    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            $cssPath = 'plugins/CardProduct/templates/css/cardproduct.css';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }
        });

        // Priority 30: FilterKonfigurator gibi önce DOM’a yazan eklentilerden sonra
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            CardProductDatabase::ensureAll();
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            if ($doc->find('[data-cardproduct-injected="1"]')->length > 0) {
                return;
            }

            $langID = (int)Shop::getLanguageID();
            $this->maybeRefreshCardsCache($langID);
            $cards = $this->loadCardsFromCache($langID) ?? $this->fetchCards();

            try {
                $ts = $this->getCardsCacheUpdatedAt($langID);
                if ($ts !== '') {
                    $ck = (string)($_COOKIE['womosi_cp_cache_v'] ?? '');
                    if ($ck !== $ts) {
                        @\setcookie('womosi_cp_cache_v', $ts, [
                            'expires'  => \time() + 86400 * 45,
                            'path'     => '/',
                            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                            'httponly' => false,
                            'samesite' => 'Lax',
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $settings = $this->normalizeInjectSettings($this->fetchSettings());
            $smarty->assign('cardProductCards', $cards);
            try {
                $dbPt = Shop::Container()->getDB();
                $ptRows = CardProductArticleHelper::fetchPanelRowsFromCatArtikelMapping($dbPt, $langID);
                $smarty->assign('cardProductProdukttypItems', $ptRows);
                $summaryLine = CardProductArticleHelper::buildProdukttypSummaryLine($ptRows);
                if ($summaryLine === '' && $ptRows !== []) {
                    $summaryLine = 'Kategorie · Produkttyp';
                }
                $smarty->assign('cardProductProdukttypSummary', $summaryLine);
            } catch (\Throwable $e) {
                $smarty->assign('cardProductProdukttypItems', []);
                $smarty->assign('cardProductProdukttypSummary', '');
            }
            $smarty->assign(
                'cardProductAllModelsUrl',
                \rtrim((string)Shop::getURL(), '/') . '/Fahrer-Beifahrer-Set-11'
            );

            $tplPath = $this->resolveCardProductGridTplPath();
            if ($tplPath === '') {
                return;
            }
            $html = $this->fetchCardProductGridMarkup($smarty, $tplPath);
            if ($html === '' || \stripos($html, 'cardproduct-grid') === false) {
                return;
            }

            $mode = (string)($settings['injectMode'] ?? 'after_configurator');
            $pos = (string)($settings['injectPos'] ?? 'after');
            $selector = (string)($settings['injectSelector'] ?? '');

            $injected = false;
            if ($mode === 'after_selector' && $selector !== '') {
                $parts = \array_filter(\array_map('trim', \explode(',', $selector)), static fn (string $s): bool => $s !== '');
                foreach ($parts as $sel) {
                    $target = $doc->find($sel)->eq(0);
                    if ($target->length > 0) {
                        if ($pos === 'before') {
                            $target->before($html);
                        } else {
                            $target->after($html);
                        }
                        $injected = true;
                        break;
                    }
                }
            }
            if (!$injected && $mode === 'after_content') {
                $content = $doc->find('div#content')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                    $injected = true;
                }
            }
            if (!$injected) {
                $konfSelectors = [
                    'div.index-filter-konfigurator',
                    '#index-filter-konfigurator',
                    'div#index-filter-konfigurator',
                ];
                foreach ($konfSelectors as $ksel) {
                    $node = $doc->find($ksel)->eq(0);
                    if ($node->length > 0) {
                        if ($pos === 'before') {
                            $node->before($html);
                        } else {
                            $node->after($html);
                        }
                        $injected = true;
                        break;
                    }
                }
            }
            if (!$injected) {
                $fallbackSelectors = [
                    'main#main-wrapper',
                    '#main-wrapper',
                    'main',
                    'div#content',
                    '#content-wrapper',
                ];
                foreach ($fallbackSelectors as $fs) {
                    $fb = $doc->find($fs)->eq(0);
                    if ($fb->length > 0) {
                        $fb->append($html);
                        $injected = true;
                        break;
                    }
                }
            }
            if (!$injected) {
                $body = $doc->find('body')->eq(0);
                if ($body->length > 0) {
                    $body->append($html);
                }
            }
        }, 30);
    }

    private function fetchCardProductGridMarkup(JTLSmarty $smarty, string $tplPath): string
    {
        $real = \realpath($tplPath);
        $dir  = \dirname($real !== false ? $real : $tplPath);
        if (!\is_dir($dir)) {
            return '';
        }
        $keyFetch = '[' . self::TPL_KEY . ']' . self::TPL_FILE;
        try {
            $smarty->addTemplateDir($dir, self::TPL_KEY);

            return $smarty->fetch($keyFetch);
        } catch (\Throwable $e) {
            // Fallback file:
        }
        $norm = \str_replace('\\', '/', $real !== false ? $real : $tplPath);
        try {
            return $smarty->fetch('file:' . $norm);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function resolveCardProductGridTplPath(): string
    {
        $file = self::TPL_FILE;
        $sep  = \DIRECTORY_SEPARATOR;
        $bases = [];
        try {
            $paths = $this->getPlugin()->getPaths();
            $vb = \rtrim($paths->getVersionedPath(), '/\\');
            if ($vb !== '') {
                $bases[] = $vb . $sep . 'templates' . $sep . $file;
            }
            $bp = \rtrim($paths->getBasePath(), '/\\');
            if ($bp !== '') {
                $bases[] = $bp . $sep . 'templates' . $sep . $file;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $bases[] = __DIR__ . $sep . 'templates' . $sep . $file;

        $seen = [];
        foreach ($bases as $p) {
            if ($p === '' || isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            if (!\is_file($p)) {
                continue;
            }
            $rp = \realpath($p);

            return $rp !== false ? $rp : $p;
        }

        return '';
    }

    /**
     * @param array<string, string> $settings
     *
     * @return array{injectMode: string, injectPos: string, injectSelector: string, refreshDom: string, refreshHour: string}
     */
    private function normalizeInjectSettings(array $settings): array
    {
        $allowed = ['after_configurator', 'after_content', 'after_selector'];
        $mode = \strtolower(\trim((string)($settings['injectMode'] ?? 'after_configurator')));
        if (!\in_array($mode, $allowed, true)) {
            $mode = 'after_configurator';
        }
        $selector = \trim((string)($settings['injectSelector'] ?? ''));
        if ($mode === 'after_selector' && $selector === '') {
            $mode = 'after_configurator';
        }
        $pos = \strtolower(\trim((string)($settings['injectPos'] ?? 'after')));
        if (!\in_array($pos, ['before', 'after'], true)) {
            $pos = 'after';
        }

        return [
            'injectMode' => $mode,
            'injectPos' => $pos,
            'injectSelector' => $selector,
            'refreshDom' => (string)($settings['refreshDom'] ?? '1'),
            'refreshHour' => (string)($settings['refreshHour'] ?? '3'),
        ];
    }

    private function isHomepageRequest(): bool
    {
        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
        }
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $path = \rtrim($path, '/');
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
        $shopBasePath = \rtrim($shopBasePath, '/');
        if ($path === $shopBasePath
            || $path === ($shopBasePath . '/index.php')
            || \str_starts_with($path, $shopBasePath . '/index.php')) {
            return true;
        }
        if ($shopBasePath === '' && ($path === '' || $path === '/')) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function fetchSettings(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject('SELECT * FROM twomosi_cardproduct_settings ORDER BY kID ASC LIMIT 1');
            if ($s === null) {
                return ['injectMode' => 'after_configurator', 'injectPos' => 'after', 'injectSelector' => '', 'refreshDom' => '1', 'refreshHour' => '3'];
            }

            return [
                'injectMode' => \trim((string)($s->cInjectMode ?? 'after_configurator')),
                'injectPos' => \trim((string)($s->cInjectPos ?? 'after')),
                'injectSelector' => \trim((string)($s->cInjectSelector ?? '')),
                'refreshDom' => (string)($s->nRefreshDom ?? 1),
                'refreshHour' => (string)($s->nRefreshHour ?? 3),
            ];
        } catch (\Throwable $e) {
            return ['injectMode' => 'after_configurator', 'injectPos' => 'after', 'injectSelector' => '', 'refreshDom' => '1', 'refreshHour' => '3'];
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function loadCardsFromCache(int $langID): ?array
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                'SELECT cCardsJson FROM twomosi_cardproduct_cache WHERE kSprache = :lid LIMIT 1',
                ['lid' => $langID]
            );
            if ($row === null || !isset($row->cCardsJson)) {
                return null;
            }
            $decoded = \json_decode((string)$row->cCardsJson, true);

            return \is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getCardsCacheUpdatedAt(int $langID): string
    {
        CardProductDatabase::ensureAll();
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                'SELECT dUpdated FROM twomosi_cardproduct_cache WHERE kSprache = :lid LIMIT 1',
                ['lid' => $langID]
            );

            return $row !== null ? (string)($row->dUpdated ?? '') : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function maybeRefreshCardsCache(int $langID): void
    {
        try {
            $db = Shop::Container()->getDB();
            $settings = $this->fetchSettings();
            $domTarget = (int)($settings['refreshDom'] ?? 1);
            $hourTarget = (int)($settings['refreshHour'] ?? 3);
            if ($domTarget < 1 || $domTarget > 28) {
                $domTarget = 1;
            }
            if ($hourTarget < 0 || $hourTarget > 23) {
                $hourTarget = 3;
            }

            $cntRow = $db->getSingleObject(
                'SELECT COUNT(*) AS cnt FROM twomosi_cardproduct_cache WHERE kSprache = :lid',
                ['lid' => $langID]
            );
            $cnt = $cntRow !== null ? (int)($cntRow->cnt ?? 0) : 0;
            $isEmpty = $cnt <= 0;

            $now = new \DateTimeImmutable('now');
            $domNow = (int)$now->format('j');
            $hourNow = (int)$now->format('G');
            $scheduledNow = ($domNow === $domTarget && $hourNow === $hourTarget);

            $key = 'cards_last_refresh_lang_' . $langID;
            $row = $db->getSingleObject('SELECT cValue FROM twomosi_cardproduct_meta WHERE cKey = :k LIMIT 1', ['k' => $key]);
            $last = null;
            if ($row !== null && isset($row->cValue)) {
                try {
                    $last = new \DateTimeImmutable((string)$row->cValue);
                } catch (\Throwable $e) {
                    $last = null;
                }
            }
            $olderThanMonth = true;
            if ($last instanceof \DateTimeImmutable) {
                $olderThanMonth = ($last < $now->sub(new \DateInterval('P25D')));
            }

            if ($isEmpty || ($scheduledNow && $olderThanMonth)) {
                $this->rebuildCardsCache($langID);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function rebuildCardsCache(int $langID): void
    {
        try {
            $db = Shop::Container()->getDB();
            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $rows = $db->getObjects(
                "SELECT kID, kArtikel, cTitle, cImage, cImages, cUrl, nSort
                   FROM twomosi_cardproduct
                  WHERE nActive = 1
                  ORDER BY nSort ASC, kID ASC"
            );
            if (!\is_array($rows)) {
                $rows = [];
            }

            $needTitle = [];
            foreach ($rows as $r) {
                $t = \trim((string)($r->cTitle ?? ''));
                $kA = (int)($r->kArtikel ?? 0);
                if ($t === '' && $kA > 0) {
                    $needTitle[$kA] = true;
                }
            }

            $titles = [];
            if ($needTitle !== []) {
                $ids = \implode(',', \array_map('intval', \array_keys($needTitle)));
                $tRows = $db->getObjects(
                    'SELECT ts.kArtikel, ts.cName
                       FROM tartikelsprache ts
                      WHERE ts.kSprache = :lid
                        AND ts.kArtikel IN (' . $ids . ')',
                    ['lid' => $langID]
                );
                if (\is_array($tRows)) {
                    foreach ($tRows as $tr) {
                        $kA = (int)($tr->kArtikel ?? 0);
                        $nm = \trim((string)($tr->cName ?? ''));
                        if ($kA > 0 && $nm !== '') {
                            $titles[$kA] = $nm;
                        }
                    }
                }
            }

            $cards = [];
            foreach ($rows as $r) {
                $title = \trim((string)($r->cTitle ?? ''));
                $kArtikel = (int)($r->kArtikel ?? 0);
                if ($title === '' && $kArtikel > 0 && isset($titles[$kArtikel])) {
                    $title = (string)$titles[$kArtikel];
                }

                $url = \trim((string)($r->cUrl ?? ''));

                $imgList = CardProductArticleHelper::resolveImagesForCacheRow($db, $shopUrl, $r);
                $img = $imgList[0] ?? '';

                if ($title === '') {
                    $title = 'Produkt';
                }
                if ($url === '') {
                    $url = '#';
                }

                $cards[] = [
                    'id' => (int)($r->kID ?? 0),
                    'kArtikel' => $kArtikel,
                    'title' => $title,
                    'img' => $img,
                    'imgs' => $imgList,
                    'url' => $url,
                ];
            }

            $json = \json_encode($cards, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            if (!\is_string($json)) {
                return;
            }
            $db->executeQueryPrepared(
                'INSERT INTO twomosi_cardproduct_cache (kSprache, cCardsJson, dUpdated)
                 VALUES (:lid, :j, NOW())
                 ON DUPLICATE KEY UPDATE cCardsJson = VALUES(cCardsJson), dUpdated = NOW()',
                ['lid' => $langID, 'j' => $json]
            );
            $key = 'cards_last_refresh_lang_' . $langID;
            $db->executeQueryPrepared(
                'INSERT INTO twomosi_cardproduct_meta (cKey, cValue)
                 VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
                ['k' => $key, 'v' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            // ignore
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
                'SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin
                    AND cDateiname = :cDateiname
                  LIMIT 1',
                ['kPlugin' => $pluginID, 'cDateiname' => $file]
            );

            if ($exists === null) {
                $menuItem = new \stdClass();
                $menuItem->kPlugin = $pluginID;
                $menuItem->cName = 'CardProduct Dashboard';
                $menuItem->cDateiname = $file;
                $menuItem->nSort = 20;
                $menuItem->nConf = 0;
                $db->insert('tpluginadminmenu', $menuItem);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // no hard fail
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCards(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $rows = $db->getObjects(
                "SELECT kID, kArtikel, cTitle, cImage, cImages, cUrl, nSort
                   FROM twomosi_cardproduct
                  WHERE nActive = 1
                  ORDER BY nSort ASC, kID ASC"
            );
            if (!\is_array($rows)) {
                return [];
            }
            $cards = [];
            foreach ($rows as $r) {
                $title = \trim((string)($r->cTitle ?? ''));
                $url = \trim((string)($r->cUrl ?? ''));
                $kArtikel = (int)($r->kArtikel ?? 0);
                $imgList = CardProductArticleHelper::resolveImagesForCacheRow($db, $shopUrl, $r);
                $img = $imgList[0] ?? '';

                if ($title === '' && $kArtikel > 0) {
                    $langID = (int)Shop::getLanguageID();
                    $prod = $db->getSingleObject(
                        'SELECT ts.cName
                           FROM tartikelsprache ts
                          WHERE ts.kArtikel = :kArtikel
                            AND ts.kSprache = :kSprache
                          LIMIT 1',
                        ['kArtikel' => $kArtikel, 'kSprache' => $langID]
                    );
                    if ($prod !== null && !empty($prod->cName)) {
                        $title = (string)$prod->cName;
                    }
                }

                if ($title === '') {
                    $title = 'Produkt';
                }
                if ($url === '') {
                    $url = '#';
                }
                $cards[] = [
                    'id' => (int)($r->kID ?? 0),
                    'kArtikel' => $kArtikel,
                    'title' => $title,
                    'img' => $img,
                    'imgs' => $imgList,
                    'url' => $url,
                ];
            }

            return $cards;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
