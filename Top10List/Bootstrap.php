<?php

namespace Plugin\Top10List;

require_once __DIR__ . '/src/Top10ListDatabase.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();

        // CSS/JS nur Startseite.
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $css = 'plugins/Top10List/templates/css/top10list.css';
            $js  = 'plugins/Top10List/templates/js/top10list.js';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
            if (!\in_array($js, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $js;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            if (!$this->isHomepageRequest()) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc = $args['document'];

            Top10ListDatabase::ensureAll();

            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 1) !== 1) {
                return;
            }

            // Cache: build monthly snapshot of computed tabs data (products arrays)
            $langID = (int)Shop::getLanguageID();
            $this->maybeRefreshTop10Cache($langID, $settings);
            $cached = $this->loadTop10PayloadFromCache($langID, $settings);
            $tabsData = \is_array($cached['tabs'] ?? null) ? (array)$cached['tabs'] : [];

            // Lightweight cookie: only stores cache timestamp/hash
            try {
                $v = (string)($cached['cacheVersion'] ?? '');
                if ($v !== '') {
                    $ck = (string)($_COOKIE['womosi_t10_cache_v'] ?? '');
                    if ($ck !== $v) {
                        @\setcookie('womosi_t10_cache_v', $v, [
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

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/top10list.tpl';
            if (!\is_file($tplPath)) {
                return;
            }
            $smarty->assign('t10Title', (string)($settings['title'] ?? 'En çok satılan'));
            $smarty->assign('t10Tabs', $tabsData);
            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '') {
                return;
            }

            $injected = false;
            $mode = (string)($settings['injectMode'] ?? 'after_configurator');
            $pos = (string)($settings['injectPos'] ?? 'after');
            $selector = trim((string)($settings['injectSelector'] ?? ''));
            $pos = \in_array($pos, ['before', 'after'], true) ? $pos : 'after';

            if ($mode === 'after_selector' && $selector !== '') {
                $target = $doc->find($selector)->eq(0);
                if ($target->length > 0) {
                    if ($pos === 'before') {
                        $target->before($html);
                    } else {
                        $target->after($html);
                    }
                    $injected = true;
                }
            }
            if (!$injected && $mode === 'after_content') {
                $content = $doc->find('div#content, main#main-wrapper, main')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                    $injected = true;
                }
            }
            if (!$injected) {
                $afterConfigurator = $doc->find('div.index-filter-konfigurator')->eq(0);
                if ($afterConfigurator->length > 0) {
                    if ($pos === 'before') {
                        $afterConfigurator->before($html);
                    } else {
                        $afterConfigurator->after($html);
                    }
                    $injected = true;
                }
            }
            if (!$injected) {
                $content = $doc->find('div#content, main#main-wrapper, main')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                }
            }
        });
    }

    /**
     * Startseite: PAGE_STARTSEITE + Pfad-Fallback (Subfolder-Installs).
     */
    private function isHomepageRequest(): bool
    {
        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
        }
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $query = (string)(\parse_url($uri, \PHP_URL_QUERY) ?? '');
        $path = \rtrim($path, '/');
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
        $shopBasePath = \rtrim($shopBasePath, '/');

        // JTL-Wawi/Admin öffnen Artikel oft als /index.php?a=123 – das ist keine Startseite.
        if ($query !== '' && \preg_match('/(?:^|&)a=\d+(?:&|$)/', $query) === 1) {
            return false;
        }

        return $path === $shopBasePath
            || $path === ($shopBasePath . '/index.php')
            || \str_starts_with($path, $shopBasePath . '/index.php');
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
            $exists = $db->getSingleObject(
                "SELECT kPluginAdminMenu FROM tpluginadminmenu WHERE kPlugin = :kPlugin AND cDateiname = :cDateiname LIMIT 1",
                ['kPlugin' => $pluginID, 'cDateiname' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu = new \stdClass();
                $menu->kPlugin = $pluginID;
                $menu->cName = 'Top10List Dashboard';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 30;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultTabs(): array
    {
        return [
            ['key' => 'pkw', 'label' => 'PKW', 'keyword' => 'pkw', 'articleIds' => []],
            ['key' => 'wohnmobil', 'label' => 'Wohnmobil', 'keyword' => 'wohnmobil', 'articleIds' => []],
            ['key' => 'andere', 'label' => 'Andere', 'keyword' => '', 'articleIds' => []],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject("SELECT * FROM twomosi_top10list_settings ORDER BY kID ASC LIMIT 1");
            if ($s === null) {
                return [
                    'active' => 1,
                    'title' => 'En çok satılan',
                    'sortMode' => 'newest',
                    'injectMode' => 'after_configurator',
                    'injectPos' => 'after',
                    'injectSelector' => '',
                    'refreshDom' => '1',
                    'refreshHour' => '3',
                    'tabs' => $this->defaultTabs()
                ];
            }
            $tabs = [];
            $raw = (string)($s->cTabsJson ?? '');
            if ($raw !== '') {
                $d = json_decode($raw, true);
                if (\is_array($d)) {
                    $tabs = $d;
                }
            }
            if (!\is_array($tabs) || $tabs === []) {
                $tabs = $this->defaultTabs();
            }
            return [
                'active' => (int)($s->nActive ?? 1),
                'title' => (string)($s->cTitle ?? 'En çok satılan'),
                'sortMode' => (string)($s->cSortMode ?? 'newest'),
                'injectMode' => (string)($s->cInjectMode ?? 'after_configurator'),
                'injectPos' => (string)($s->cInjectPos ?? 'after'),
                'injectSelector' => (string)($s->cInjectSelector ?? ''),
                'refreshDom' => (string)($s->nRefreshDom ?? 1),
                'refreshHour' => (string)($s->nRefreshHour ?? 3),
                'tabs' => $tabs,
            ];
        } catch (\Throwable $e) {
            return [
                'active' => 1,
                'title' => 'En çok satılan',
                'sortMode' => 'newest',
                'injectMode' => 'after_configurator',
                'injectPos' => 'after',
                'injectSelector' => '',
                'refreshDom' => '1',
                'refreshHour' => '3',
                'tabs' => $this->defaultTabs()
            ];
        }
    }

    private function settingsHash(array $settings): string
    {
        $tabs = $settings['tabs'] ?? [];
        if (!\is_array($tabs)) {
            $tabs = [];
        }
        $payload = [
            'sortMode' => (string)($settings['sortMode'] ?? 'newest'),
            'tabs' => $tabs,
        ];
        $json = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        return \hash('sha256', \is_string($json) ? $json : \serialize($payload));
    }

    /**
     * @return array{tabs:array<int,array<string,mixed>>,cacheVersion:string}
     */
    private function loadTop10PayloadFromCache(int $langID, array $settings): array
    {
        $hash = $this->settingsHash($settings);
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                "SELECT cPayloadJson, cSettingsHash, dUpdated
                   FROM twomosi_top10list_cache
                  WHERE kSprache = :lid
                  LIMIT 1",
                ['lid' => $langID]
            );
            if ($row !== null && isset($row->cPayloadJson) && (string)($row->cSettingsHash ?? '') === $hash) {
                $decoded = \json_decode((string)$row->cPayloadJson, true);
                if (\is_array($decoded) && isset($decoded['tabs']) && \is_array($decoded['tabs'])) {
                    return [
                        'tabs' => $decoded['tabs'],
                        'cacheVersion' => (string)($row->dUpdated ?? '') . ':' . $hash,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // fallback: compute now and return (also will be cached by maybeRefreshTop10Cache)
        $payload = $this->computeTop10TabsPayload($settings);
        return [
            'tabs' => $payload['tabs'],
            'cacheVersion' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s') . ':' . $hash,
        ];
    }

    private function maybeRefreshTop10Cache(int $langID, array $settings): void
    {
        try {
            $db = Shop::Container()->getDB();
            $hash = $this->settingsHash($settings);

            $row = $db->getSingleObject(
                "SELECT cSettingsHash, dUpdated
                   FROM twomosi_top10list_cache
                  WHERE kSprache = :lid
                  LIMIT 1",
                ['lid' => $langID]
            );
            $isEmpty = ($row === null);
            $hashChanged = !$isEmpty && (string)($row->cSettingsHash ?? '') !== $hash;

            $domTarget = (int)($settings['refreshDom'] ?? 1);
            $hourTarget = (int)($settings['refreshHour'] ?? 3);
            if ($domTarget < 1 || $domTarget > 28) { $domTarget = 1; }
            if ($hourTarget < 0 || $hourTarget > 23) { $hourTarget = 3; }

            $now = new \DateTimeImmutable('now');
            $domNow = (int)$now->format('j');
            $hourNow = (int)$now->format('G');
            $scheduledNow = ($domNow === $domTarget && $hourNow === $hourTarget);

            $key = 'top10_last_refresh_lang_' . $langID;
            $m = $db->getSingleObject("SELECT cValue FROM twomosi_top10list_meta WHERE cKey = :k LIMIT 1", ['k' => $key]);
            $last = null;
            if ($m !== null && isset($m->cValue)) {
                try { $last = new \DateTimeImmutable((string)$m->cValue); } catch (\Throwable $e) { $last = null; }
            }
            $olderThanMonth = true;
            if ($last instanceof \DateTimeImmutable) {
                $olderThanMonth = ($last < $now->sub(new \DateInterval('P25D')));
            }

            if ($isEmpty || $hashChanged || ($scheduledNow && $olderThanMonth)) {
                $payload = $this->computeTop10TabsPayload($settings);
                $json = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                if (!\is_string($json)) {
                    return;
                }
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_top10list_cache (kSprache, cSettingsHash, cPayloadJson, dUpdated)
                     VALUES (:lid, :h, :j, NOW())
                     ON DUPLICATE KEY UPDATE cSettingsHash = VALUES(cSettingsHash), cPayloadJson = VALUES(cPayloadJson), dUpdated = NOW()",
                    ['lid' => $langID, 'h' => $hash, 'j' => $json]
                );
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_top10list_meta (cKey, cValue)
                     VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)",
                    ['k' => $key, 'v' => $now->format('Y-m-d H:i:s')]
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{tabs:array<int,array<string,mixed>>}
     */
    private function computeTop10TabsPayload(array $settings): array
    {
        $tabs = $settings['tabs'] ?? [];
        if (!\is_array($tabs) || $tabs === []) {
            $tabs = $this->defaultTabs();
        }
        $tabsData = [];
        foreach ($tabs as $i => $tab) {
            if (!\is_array($tab)) {
                continue;
            }
            $articleIDs = $this->normalizeArticleIDs($tab['articleIds'] ?? []);
            $products = $articleIDs !== []
                ? $this->fetchProductsByIDs($articleIDs, 10)
                : $this->fetchTopProducts(
                    (string)($tab['keyword'] ?? ''),
                    10,
                    (string)($settings['sortMode'] ?? 'newest'),
                    (int)Shop::getLanguageID()
                );
            $tabsData[] = [
                'key' => (string)($tab['key'] ?? ('tab' . $i)),
                'label' => (string)($tab['label'] ?? 'Tab'),
                'products' => $products,
            ];
        }
        return ['tabs' => $tabsData];
    }

    /**
     * keyword "pkw" / "wohnmobil" (Groß/Klein): gleiche Merkmal-Logik wie MarkeFiltre (nicht LIKE im Artikelnamen).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchTopProducts(string $keyword, int $limit = 10, string $sortMode = 'newest', int $langID = 0): array
    {
        if ($langID <= 0) {
            $langID = (int)Shop::getLanguageID();
        }
        $kwNorm = \strtolower(\trim($keyword));
        if ($kwNorm === 'pkw' || $kwNorm === 'wohnmobil') {
            return $this->fetchTopProductsByMerkmalSegment($kwNorm, $limit, $sortMode, $langID);
        }

        try {
            $db = Shop::Container()->getDB();
            $kw = trim($keyword);
            $sqlWhere = "a.nIstVater = 0";
            $params = [];
            $orderBy = $sortMode === 'best_sold'
                ? "COALESCE(xk.nSales, 0) DESC, a.kArtikel DESC"
                : "a.kArtikel DESC";
            if ($kw !== '') {
                $sqlWhere .= " AND (a.cName LIKE :kw OR ts.cName LIKE :kw OR a.cArtNr LIKE :kw)";
                $params['kw'] = '%' . $kw . '%';
            }

            $rows = $db->getObjects(
                "SELECT
                    a.kArtikel,
                    COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                    COALESCE(a.cSeo, '') AS cSeo,
                    COALESCE(xk.nSales, 0) AS nSales,
                    COALESCE(a.fStandardpreisNetto, 0) AS fNet,
                    COALESCE(a.fMwSt, 0) AS fMwSt,
                    COALESCE(pic.nNr, 1) AS nNr
                 FROM tartikel a
                 LEFT JOIN (
                    SELECT s1.kArtikel, MIN(s1.cName) AS cName
                    FROM tartikelsprache s1
                    GROUP BY s1.kArtikel
                 ) ts ON ts.kArtikel = a.kArtikel
                 LEFT JOIN (
                    SELECT p1.kArtikel, MIN(p1.nNr) AS nNr
                    FROM tartikelpict p1
                    GROUP BY p1.kArtikel
                 ) pic ON pic.kArtikel = a.kArtikel
                 LEFT JOIN (
                    SELECT xk1.kArtikel, SUM(COALESCE(xk1.nAnzahl, 0)) AS nSales
                    FROM txsellkauf xk1
                    GROUP BY xk1.kArtikel
                 ) xk ON xk.kArtikel = a.kArtikel
                 WHERE {$sqlWhere}
                 ORDER BY {$orderBy}
                 LIMIT " . (int)$limit,
                $params
            );

            $shop = rtrim((string)Shop::getURL(), '/');
            $out = [];
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $kArtikel = (int)($r->kArtikel ?? 0);
                    if ($kArtikel <= 0) {
                        continue;
                    }
                    $name = (string)($r->cName ?? '');
                    $seo = trim((string)($r->cSeo ?? ''));
                    $url = $seo !== '' ? ($shop . '/' . ltrim($seo, '/')) : ($shop . '/artikel.php?a=' . $kArtikel);
                    $imgSlug = $this->slugify($name !== '' ? $name : (string)$kArtikel);
                    $img = $shop . '/media/image/product/' . $kArtikel . '/md/' . $imgSlug . '.jpg';
                    $gross = (float)($r->fNet ?? 0) * (1 + (float)($r->fMwSt ?? 0) / 100);
                    $out[] = [
                        'kArtikel' => $kArtikel,
                        'name' => $name,
                        'url' => $url,
                        'image' => $img,
                        'price' => number_format($gross, 2, ',', '.') . ' €',
                    ];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * PKW: Fahrzeug-Marke gesetzt, keine Wohnmobil-Aufbauart (wie MarkeFiltre).
     * Wohnmobil: Aufbauart + Fahrzeug-Marke (wie MarkeFiltre).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchTopProductsByMerkmalSegment(string $segment, int $limit, string $sortMode, int $langID): array
    {
        $limit = max(1, min(50, $limit));
        $orderBy = $sortMode === 'best_sold'
            ? 'COALESCE(xk.nSales, 0) DESC, a.kArtikel DESC'
            : 'a.kArtikel DESC';

        $existsMarke = "EXISTS (
            SELECT 1
              FROM tartikelmerkmal am_her
        INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
        INNER JOIN tmerkmal m_her ON m_her.kMerkmal = mw_her.kMerkmal
        INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                         AND ms_her.kSprache = :lid
                                         AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
        INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                               AND tmws_her.kSprache = :lid
             WHERE am_her.kArtikel = a.kArtikel
               AND TRIM(tmws_her.cWert) <> ''
               AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
        )";

        $existsAufbauart = "EXISTS (
            SELECT 1
              FROM tartikelmerkmal am_typ
        INNER JOIN tmerkmalwert mw_typ ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
        INNER JOIN tmerkmal m_typ ON m_typ.kMerkmal = mw_typ.kMerkmal
        INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                         AND ms_typ.kSprache = :lid
                                         AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR (ms_typ.cName LIKE '%Aufbauart%'))
             WHERE am_typ.kArtikel = a.kArtikel
        )";

        $extraWhere = $segment === 'pkw'
            ? " AND {$existsMarke} AND NOT ({$existsAufbauart}) "
            : " AND {$existsAufbauart} AND {$existsMarke} ";

        $tryLangs = [$langID];
        if ($langID !== 1) {
            $tryLangs[] = 1;
        }

        try {
            $db = Shop::Container()->getDB();
            $rows = null;
            foreach ($tryLangs as $lid) {
                $try = $db->getObjects(
                    "SELECT
                        a.kArtikel,
                        COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                        COALESCE(a.cSeo, '') AS cSeo,
                        COALESCE(xk.nSales, 0) AS nSales,
                        COALESCE(a.fStandardpreisNetto, 0) AS fNet,
                        COALESCE(a.fMwSt, 0) AS fMwSt,
                        COALESCE(pic.nNr, 1) AS nNr
                     FROM tartikel a
                     LEFT JOIN (
                        SELECT s1.kArtikel, MIN(s1.cName) AS cName
                        FROM tartikelsprache s1
                        GROUP BY s1.kArtikel
                     ) ts ON ts.kArtikel = a.kArtikel
                     LEFT JOIN (
                        SELECT p1.kArtikel, MIN(p1.nNr) AS nNr
                        FROM tartikelpict p1
                        GROUP BY p1.kArtikel
                     ) pic ON pic.kArtikel = a.kArtikel
                     LEFT JOIN (
                        SELECT xk1.kArtikel, SUM(COALESCE(xk1.nAnzahl, 0)) AS nSales
                        FROM txsellkauf xk1
                        GROUP BY xk1.kArtikel
                     ) xk ON xk.kArtikel = a.kArtikel
                     WHERE a.nIstVater = 0
                       {$extraWhere}
                     ORDER BY {$orderBy}
                     LIMIT " . (int)$limit,
                    ['lid' => $lid]
                );
                if (\is_array($try) && $try !== []) {
                    $rows = $try;
                    break;
                }
            }
            if ($rows === null) {
                $rows = [];
            }

            $shop = rtrim((string)Shop::getURL(), '/');
            $out = [];
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $kArtikel = (int)($r->kArtikel ?? 0);
                    if ($kArtikel <= 0) {
                        continue;
                    }
                    $name = (string)($r->cName ?? '');
                    $seo = trim((string)($r->cSeo ?? ''));
                    $url = $seo !== '' ? ($shop . '/' . ltrim($seo, '/')) : ($shop . '/artikel.php?a=' . $kArtikel);
                    $imgSlug = $this->slugify($name !== '' ? $name : (string)$kArtikel);
                    $img = $shop . '/media/image/product/' . $kArtikel . '/md/' . $imgSlug . '.jpg';
                    $gross = (float)($r->fNet ?? 0) * (1 + (float)($r->fMwSt ?? 0) / 100);
                    $out[] = [
                        'kArtikel' => $kArtikel,
                        'name' => $name,
                        'url' => $url,
                        'image' => $img,
                        'price' => number_format($gross, 2, ',', '.') . ' €',
                    ];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function normalizeArticleIDs(mixed $raw): array
    {
        $parts = [];
        if (\is_array($raw)) {
            $parts = $raw;
        } elseif (\is_string($raw)) {
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

    /**
     * @param array<int, int> $articleIDs
     * @return array<int, array<string, mixed>>
     */
    private function fetchProductsByIDs(array $articleIDs, int $limit = 10): array
    {
        $articleIDs = array_slice($articleIDs, 0, max(1, $limit));
        if ($articleIDs === []) {
            return [];
        }
        try {
            $db = Shop::Container()->getDB();
            $idList = implode(',', array_map(static fn(int $id): string => (string)$id, $articleIDs));
            $rows = $db->getObjects(
                "SELECT
                    a.kArtikel,
                    COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                    COALESCE(a.cSeo, '') AS cSeo,
                    COALESCE(a.fStandardpreisNetto, 0) AS fNet,
                    COALESCE(a.fMwSt, 0) AS fMwSt
                 FROM tartikel a
                 LEFT JOIN (
                    SELECT s1.kArtikel, MIN(s1.cName) AS cName
                    FROM tartikelsprache s1
                    GROUP BY s1.kArtikel
                 ) ts ON ts.kArtikel = a.kArtikel
                 WHERE a.nIstVater = 0
                   AND a.kArtikel IN ({$idList})"
            );
            $byID = [];
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $kArtikel = (int)($r->kArtikel ?? 0);
                    if ($kArtikel > 0) {
                        $byID[$kArtikel] = $r;
                    }
                }
            }

            $shop = rtrim((string)Shop::getURL(), '/');
            $out = [];
            foreach ($articleIDs as $id) {
                if (!isset($byID[$id])) {
                    continue;
                }
                $r = $byID[$id];
                $name = (string)($r->cName ?? '');
                $seo = trim((string)($r->cSeo ?? ''));
                $url = $seo !== '' ? ($shop . '/' . ltrim($seo, '/')) : ($shop . '/artikel.php?a=' . $id);
                $imgSlug = $this->slugify($name !== '' ? $name : (string)$id);
                $img = $shop . '/media/image/product/' . $id . '/md/' . $imgSlug . '.jpg';
                $gross = (float)($r->fNet ?? 0) * (1 + (float)($r->fMwSt ?? 0) / 100);
                $out[] = [
                    'kArtikel' => $id,
                    'name' => $name,
                    'url' => $url,
                    'image' => $img,
                    'price' => number_format($gross, 2, ',', '.') . ' €',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function slugify(string $v): string
    {
        $v = strtolower(trim($v));
        $v = preg_replace('/[^a-z0-9]+/i', '-', $v) ?? $v;
        $v = trim($v, '-');
        return $v !== '' ? $v : 'produkt';
    }
}

