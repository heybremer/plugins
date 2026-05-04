<?php

namespace Plugin\Bloggie;

require_once __DIR__ . '/src/BloggieDatabase.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use JTL\Router\Router;
use JTL\Router\LinkService;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;
    private static bool $dailyAttempted = false;

    /** @var array<string, mixed>|null Request-local cache for fetchSettings() */
    private static ?array $settingsCache = null;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        // ensureTables() removed from global boot: avoid schema/DDL work on every request.
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (Shop::getPageType() !== \PAGE_STARTSEITE) {
                return;
            }
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 1) !== 1) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            $css = 'plugins/Bloggie/templates/css/bloggie.css';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            if (Shop::getPageType() !== \PAGE_STARTSEITE) {
                return;
            }
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 1) !== 1) {
                return;
            }

            // Lazy daily run: first homepage request of day can trigger generation.
            if (!self::$dailyAttempted && (int)($settings['autoDaily'] ?? 1) === 1) {
                self::$dailyAttempted = true;
                $this->tryRunDailyGeneration();
            }

            $posts = $this->fetchLatestNewsPosts(10);
            if ($posts === []) {
                return;
            }

            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];
            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/bloggie.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            $smarty->assign('bgTitle', (string)($settings['sectionTitle'] ?? 'Blog'));
            $smarty->assign('bgPosts', $posts);
            $smarty->assign('bgAllNewsUrl', Shop::Container()->getLinkService()->getStaticRoute('news.php'));
            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '') {
                return;
            }

            $selectorRaw = trim((string)($settings['injectSelector'] ?? 'div.index-filter-konfigurator'));
            $mode = (string)($settings['injectMode'] ?? 'after');
            $mode = \in_array($mode, ['after', 'before', 'prepend', 'append'], true) ? $mode : 'after';
            $selectors = array_filter(array_map('trim', explode(',', $selectorRaw)), static fn($v) => $v !== '');
            if ($selectors === []) {
                $selectors = ['div.index-filter-konfigurator'];
            }

            $injected = false;
            foreach ($selectors as $sel) {
                $target = $doc->find($sel)->eq(0);
                if ($target->length < 1) {
                    continue;
                }
                try {
                    if ($mode === 'before') {
                        $target->before($html);
                    } elseif ($mode === 'prepend') {
                        $target->prepend($html);
                    } elseif ($mode === 'append') {
                        $target->append($html);
                    } else {
                        $target->after($html);
                    }
                    $injected = true;
                    break;
                } catch (\Throwable $e) {
                    // try next selector
                }
            }

            if (!$injected) {
                $content = $doc->find('div#content, main#main-wrapper, main')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                }
            }
        });

        // Blog list page (tüm paylaşımlar)
        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
            if (!isset($args['router']) || !($args['router'] instanceof Router)) {
                return;
            }
            /** @var Router $router */
            $router = $args['router'];

            $router->addRoute(
                '/bloggie',
                function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                    $settings = $this->fetchSettings();
                    if ((int)($settings['active'] ?? 1) !== 1) {
                        return new \Laminas\Diactoros\Response\EmptyResponse();
                    }

                    $bgTitle = (string)($settings['sectionTitle'] ?? 'Blog');

                    $query = $request->getQueryParams();
                    $slug = (string)($query['slug'] ?? '');

                    $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/bloggie.tpl';
                    $smarty->assign('bgTitle', $bgTitle);

                    if ($slug !== '') {
                        $post = $this->fetchPostBySlug($slug);
                        if ($post === null) {
                            return new \Laminas\Diactoros\Response\EmptyResponse();
                        }
                        $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/bloggie_detail.tpl';
                        $smarty->assign('bgPost', $post);
                    } else {
                        $posts = $this->fetchAllPublishedPosts();
                        $smarty->assign('bgPosts', $posts);
                    }

                    if (!\is_file($tplPath)) {
                        return new \Laminas\Diactoros\Response\EmptyResponse();
                    }

                    try {
                        $inner = $smarty->fetch('file:' . $tplPath);
                    } catch (\Throwable $e) {
                        return new \Laminas\Diactoros\Response\EmptyResponse();
                    }
                    if ($inner === '') {
                        return new \Laminas\Diactoros\Response\EmptyResponse();
                    }

                    $shopUrl = \rtrim((string)Shop::getURL(), '/');
                    $cssHref = htmlspecialchars($shopUrl . '/plugins/Bloggie/templates/css/bloggie.css', ENT_QUOTES, 'UTF-8');
                    $titleEsc = htmlspecialchars($bgTitle, ENT_QUOTES, 'UTF-8');

                    $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
                    $html .= '<title>' . $titleEsc . '</title>';
                    $html .= '<link rel="stylesheet" href="' . $cssHref . '">';
                    $html .= '</head><body>' . $inner . '</body></html>';

                    $body = new \Laminas\Diactoros\Stream('php://temp', 'wb+');
                    $body->write($html);
                    $body->rewind();

                    return new \Laminas\Diactoros\Response($body, 200, [
                        'Content-Type' => 'text/html; charset=utf-8',
                        'Cache-Control' => 'no-store',
                    ]);
                },
                'bloggie',
                ['GET']
            );
        });
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
                "SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin
                    AND cDateiname = :cDateiname
                  LIMIT 1",
                ['kPlugin' => $pluginID, 'cDateiname' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu = new \stdClass();
                $menu->kPlugin = $pluginID;
                $menu->cName = 'Bloggie Dashboard';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 32;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }
        try {
            BloggieDatabase::ensureAll();
            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
            if ($s === null) {
                self::$settingsCache = [];

                return self::$settingsCache;
            }
            $mode = (string)($s->cInjectMode ?? 'after');
            if (!\in_array($mode, ['after', 'before', 'prepend', 'append'], true)) {
                $mode = 'after';
            }
            self::$settingsCache = [
                'id' => (int)($s->kID ?? 0),
                'active' => (int)($s->nActive ?? 1),
                'autoDaily' => (int)($s->nAutoDaily ?? 1),
                'apiKey' => (string)($s->cApiKey ?? ''),
                'model' => (string)($s->cModel ?? 'gpt-4o-mini'),
                'language' => (string)($s->cLanguage ?? 'de'),
                'sectionTitle' => (string)($s->cSectionTitle ?? 'Blog'),
                'promptPrefix' => (string)($s->cPromptPrefix ?? ''),
                'injectSelector' => (string)($s->cInjectSelector ?? 'div.index-filter-konfigurator'),
                'injectMode' => $mode,
                'cronToken' => (string)($s->cCronToken ?? ''),
                'brandPool' => (string)($s->cBrandPool ?? ''),
                'topicPool' => (string)($s->cTopicPool ?? ''),
                'lastRunDate' => (string)($s->dLastRunDate ?? ''),
            ];

            return self::$settingsCache;
        } catch (\Throwable $e) {
            self::$settingsCache = [];

            return self::$settingsCache;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLatestPosts(int $limit = 4): array
    {
        try {
            $db = Shop::Container()->getDB();
            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $rows = $db->getObjects(
                "SELECT kID, cBlogTitle, cSlug, cExcerpt, cBodyHtml, cProductUrl, dPublish
                   FROM twomosi_bloggie_posts
                  WHERE nPublished = 1
                  ORDER BY dPublish DESC, kID DESC
                  LIMIT " . max(1, (int)$limit)
            );
            if (!\is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                $slug = (string)($r->cSlug ?? '');
                $detailUrl = $slug !== ''
                    ? ($shopUrl . '/bloggie?slug=' . urlencode($slug))
                    : ($shopUrl . '/bloggie');
                $out[] = [
                    'id' => (int)($r->kID ?? 0),
                    'title' => (string)($r->cBlogTitle ?? ''),
                    'slug' => (string)($r->cSlug ?? ''),
                    'excerpt' => (string)($r->cExcerpt ?? ''),
                    'body' => (string)($r->cBodyHtml ?? ''),
                    'productUrl' => (string)($r->cProductUrl ?? ''),
                    'detailUrl' => $detailUrl,
                    'date' => (string)($r->dPublish ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllPublishedPosts(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $rows = $db->getObjects(
                "SELECT kID, cBlogTitle, cSlug, cExcerpt, cBodyHtml, cProductUrl, dPublish
                   FROM twomosi_bloggie_posts
                  WHERE nPublished = 1
                  ORDER BY dPublish DESC, kID DESC"
            );
            if (!\is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                $slug = (string)($r->cSlug ?? '');
                $detailUrl = $slug !== ''
                    ? ($shopUrl . '/bloggie?slug=' . urlencode($slug))
                    : ($shopUrl . '/bloggie');
                $out[] = [
                    'id' => (int)($r->kID ?? 0),
                    'title' => (string)($r->cBlogTitle ?? ''),
                    'slug' => (string)($r->cSlug ?? ''),
                    'excerpt' => (string)($r->cExcerpt ?? ''),
                    'body' => (string)($r->cBodyHtml ?? ''),
                    'productUrl' => (string)($r->cProductUrl ?? ''),
                    'detailUrl' => $detailUrl,
                    'date' => (string)($r->dPublish ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchPostBySlug(string $slug): ?array
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                "SELECT kID, cBlogTitle, cSlug, cExcerpt, cBodyHtml, cProductUrl, dPublish
                   FROM twomosi_bloggie_posts
                  WHERE nPublished = 1
                    AND cSlug = :slug
                  LIMIT 1",
                ['slug' => $slug]
            );
            if ($row === null) {
                return null;
            }
            return [
                'id' => (int)($row->kID ?? 0),
                'title' => (string)($row->cBlogTitle ?? ''),
                'slug' => (string)($row->cSlug ?? ''),
                'excerpt' => (string)($row->cExcerpt ?? ''),
                'body' => (string)($row->cBodyHtml ?? ''),
                'productUrl' => (string)($row->cProductUrl ?? ''),
                'date' => (string)($row->dPublish ?? ''),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fetches latest Bloggie-generated posts from JTL news system (tnews/tnewssprache) by category name "Bloggie".
     *
     * @return array<int, array{title:string, excerpt:string, detailUrl:string, date:string}>
     */
    private function fetchLatestNewsPosts(int $limit = 10): array
    {
        try {
            $db = Shop::Container()->getDB();
            $langID = (int)Shop::getLanguageID();
            $limit = $limit > 0 ? $limit : 10;
            if ($limit > 50) {
                $limit = 50;
            }

            $rows = $db->getObjects(
                "SELECT tnews.kNews,
                        tnewssprache.title,
                        tnewssprache.preview,
                        tnews.dGueltigVon,
                        tseo.cSeo
                   FROM tnews
                   JOIN tnewssprache
                     ON tnewssprache.kNews = tnews.kNews
                    AND tnewssprache.languageID = :lid
                   JOIN tnewskategorienews
                     ON tnewskategorienews.kNews = tnews.kNews
                   JOIN tnewskategoriesprache
                     ON tnewskategoriesprache.kNewsKategorie = tnewskategorienews.kNewsKategorie
                    AND tnewskategoriesprache.languageID = :lid
                   JOIN tseo
                     ON tseo.cKey = 'kNews'
                    AND tseo.kKey = tnews.kNews
                    AND tseo.kSprache = :lid
                  WHERE tnews.nAktiv = 1
                    AND tnews.dGueltigVon <= NOW()
                    AND tnewskategoriesprache.name = 'Bloggie'
                  GROUP BY tnews.kNews
                  ORDER BY tnews.dGueltigVon DESC, tnews.dErstellt DESC
                  LIMIT " . (int)$limit,
                ['lid' => $langID]
            );
            if (!is_array($rows) || $rows === []) {
                return [];
            }
            $base = rtrim((string)Shop::getURL(), '/');
            $out = [];
            foreach ($rows as $r) {
                $seo = (string)($r->cSeo ?? '');
                $out[] = [
                    'title' => (string)($r->title ?? ''),
                    'excerpt' => trim(strip_tags((string)($r->preview ?? ''))),
                    'detailUrl' => $seo !== '' ? ($base . '/' . ltrim($seo, '/')) : ($base . '/news.php?n=' . (int)($r->kNews ?? 0)),
                    'date' => (string)($r->dGueltigVon ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function tryRunDailyGeneration(): void
    {
        $settings = $this->fetchSettings();
        if ($settings === [] || (int)($settings['id'] ?? 0) <= 0) {
            return;
        }
        $today = date('Y-m-d');
        if ((string)($settings['lastRunDate'] ?? '') === $today) {
            return;
        }
        if (trim((string)($settings['apiKey'] ?? '')) === '') {
            return;
        }
        if ((int)($settings['autoDaily'] ?? 1) !== 1) {
            return;
        }
        $this->generateAndSavePost($settings, true);
    }

    /**
     * Called from api/generate.php
     */
    public function runManualGenerationByToken(string $token): array
    {
        $settings = $this->fetchSettings();
        if ($settings === [] || (int)($settings['id'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Settings not found'];
        }
        if (!\hash_equals((string)($settings['cronToken'] ?? ''), $token)) {
            return ['ok' => false, 'message' => 'Invalid token'];
        }
        if (trim((string)($settings['apiKey'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'API key missing'];
        }
        return $this->generateAndSavePost($settings, false);
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function generateAndSavePost(array $settings, bool $daily): array
    {
        try {
            $product = $this->pickProductForToday($settings);
            if ($product === null) {
                return ['ok' => false, 'message' => 'No in-stock product found'];
            }

            $generated = $this->generateSeoBlogWithOpenAI($settings, $product);
            if ((int)($generated['ok'] ?? 0) !== 1) {
                return ['ok' => false, 'message' => (string)($generated['message'] ?? 'Generation failed')];
            }

            $slug = $this->slugify((string)$generated['title']) . '-' . date('Ymd');
            $db = Shop::Container()->getDB();
            $bloggiePostID = (int)$db->insert('twomosi_bloggie_posts', (object)[
                'kArtikel' => (int)$product['kArtikel'],
                'kNews' => 0,
                'cProductName' => (string)$product['name'],
                'cProductUrl' => (string)$product['url'],
                'cSeoTitle' => (string)$product['seoTitle'],
                'cMetaDescription' => (string)$product['metaDescription'],
                'cBlogTitle' => (string)$generated['title'],
                'cSlug' => $slug,
                'cExcerpt' => (string)$generated['excerpt'],
                'cBodyHtml' => (string)$generated['bodyHtml'],
                'nPublished' => 1,
                'dPublish' => 'now()',
                'dCreated' => 'now()',
            ]);

            // Write also into JTL news system (admin/news) so items can be managed there.
            $bodyHtml = (string)$generated['bodyHtml'];
            $productUrl = rtrim((string)($product['url'] ?? ''), '/');
            if ($productUrl !== '' && \strpos($bodyHtml, $productUrl) === false) {
                $bodyHtml .= '<p><a href="' . $productUrl . '">Zum Produkt</a></p>';
            }
            $newsSlugBase = $this->slugify((string)$generated['title']) . '-' . date('Ymd') . '-' . (int)$product['kArtikel'];
            $generatedForNews = [
                'title' => (string)$generated['title'],
                'excerpt' => (string)$generated['excerpt'],
                'bodyHtml' => $bodyHtml,
                'newsSlugBase' => $newsSlugBase,
            ];
            $productForNews = [
                'kArtikel' => (int)$product['kArtikel'],
                'name' => (string)$product['name'],
                'url' => $productUrl,
                'seoTitle' => (string)$product['seoTitle'],
                'metaDescription' => (string)$product['metaDescription'],
            ];
            require_once __DIR__ . '/includes/generate_lib.php';
            if (function_exists('bloggieInsertGeneratedPostIntoJtlNews')) {
                $kNews = (int)bloggieInsertGeneratedPostIntoJtlNews($db, $productForNews, $generatedForNews);
                if ($bloggiePostID > 0 && $kNews > 0) {
                    $db->update('twomosi_bloggie_posts', 'kID', $bloggiePostID, (object)['kNews' => $kNews]);
                }
            }
            if ($daily) {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_bloggie_settings SET dLastRunDate = :d, dUpdated = NOW() WHERE kID = :kID",
                    ['d' => date('Y-m-d'), 'kID' => (int)$settings['id']]
                );
            }
            return ['ok' => true, 'message' => 'Blog generated', 'product' => $product['name'], 'title' => $generated['title']];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>|null
     */
    private function pickProductForToday(array $settings): ?array
    {
        try {
            $lib = __DIR__ . '/includes/product_lib.php';
            if (\is_file($lib)) {
                require_once $lib;
            }
            if (!\function_exists('bloggieFetchArticleRows') || !\function_exists('bloggieSelectArticleRowForBlog')) {
                return null;
            }
            $brandPool = (string)($settings['brandPool'] ?? '');
            $rows = bloggieFetchArticleRows();
            if (!\is_array($rows) || $rows === []) {
                return null;
            }
            $p = bloggieSelectArticleRowForBlog($rows, $brandPool);
            if ($p === null) {
                return null;
            }
            $kArtikel = (int)($p->kArtikel ?? 0);
            $name = trim((string)($p->cName ?? ''));
            if ($name === '') {
                $name = trim((string)($p->cArtName ?? 'Produkt'));
            }
            $seoTitle = trim((string)($p->cMetaTitle ?? ''));
            if ($seoTitle === '') {
                $seoTitle = $name;
            }
            $meta = trim((string)($p->cMetaDescription ?? ''));
            $desc = trim(strip_tags((string)($p->cKurzBeschreibung ?? '')));
            if ($desc === '') {
                $desc = trim(strip_tags((string)($p->cBeschreibung ?? '')));
            }
            if ($meta === '') {
                $meta = mb_substr($desc, 0, 250);
            }
            $base = rtrim((string)Shop::getURL(), '/');
            $url = $base . '/index.php?a=' . $kArtikel;
            return [
                'kArtikel' => $kArtikel,
                'name' => $name,
                'seoTitle' => $seoTitle,
                'metaDescription' => $meta,
                'description' => $desc,
                'url' => $url,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $product
     * @return array<string,mixed>
     */
    private function generateSeoBlogWithOpenAI(array $settings, array $product): array
    {
        $apiKey = trim((string)($settings['apiKey'] ?? ''));
        $model = trim((string)($settings['model'] ?? 'gpt-4o-mini'));
        $lang = trim((string)($settings['language'] ?? 'de'));
        $prefix = trim((string)($settings['promptPrefix'] ?? ''));
        $brandPool = (string)($settings['brandPool'] ?? '');
        $topicPool = (string)($settings['topicPool'] ?? '');

        $pickFromPool = static function (string $pool, int $seed): string {
            $lines = preg_split('/\r\n|\r|\n/', $pool) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), static fn($v) => $v !== ''));
            if ($lines === []) {
                return '';
            }
            $idx = $seed % count($lines);
            return (string)$lines[$idx];
        };
        $lib = __DIR__ . '/includes/product_lib.php';
        if (\is_file($lib)) {
            require_once $lib;
        }
        $seed = ((int)date('z') + (int)date('W') * 13 + (int)($product['kArtikel'] ?? 0)) & 0x7fffffff;
        $brandFocus = \function_exists('bloggieBrandPoolLineForDay') ? bloggieBrandPoolLineForDay($brandPool) : '';
        $topicFocus = $pickFromPool($topicPool, $seed + 7);

        $system = "You are an expert SEO content writer. Return strictly valid JSON with keys: title, excerpt, bodyHtml.";
        $user = $prefix . "\n\n"
            . ($brandFocus !== '' ? ("Brand focus: " . $brandFocus . "\n") : '')
            . ($topicFocus !== '' ? ("Topic focus: " . $topicFocus . "\n") : '')
            . "Language: " . $lang . "\n"
            . "Product Name: " . (string)$product['name'] . "\n"
            . "SEO Title: " . (string)$product['seoTitle'] . "\n"
            . "Meta Description: " . (string)$product['metaDescription'] . "\n"
            . "Product Description: " . (string)$product['description'] . "\n"
            . "Product URL: " . (string)$product['url'] . "\n\n"
            . "Rules:\n"
            . "- Write one publish-ready SEO blog article for this product.\n"
            . "- bodyHtml must contain h2 and p tags and one bullet list.\n"
            . "- mention product URL once in an anchor tag.\n"
            . "- title max 70 chars, excerpt max 180 chars.\n";

        // Persist the exact prompts used (dashboard visibility)
        try {
            $db = Shop::Container()->getDB();
            $db->executeQueryPrepared(
                "UPDATE twomosi_bloggie_settings
                    SET cLastSystemPrompt = :sys,
                        cLastUserPrompt = :usr,
                        dLastPrompt = NOW(),
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID' => (int)($settings['id'] ?? 0),
                    'sys' => $system,
                    'usr' => $user,
                ]
            );
        } catch (\Throwable $e) {
            // ignore
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.7,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = \curl_init('https://api.openai.com/v1/chat/completions');
        if ($ch === false) {
            return ['ok' => 0, 'message' => 'curl init failed'];
        }
        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 35,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = \curl_exec($ch);
        $err = \curl_error($ch);
        $code = (int)\curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        \curl_close($ch);

        if (!\is_string($resp) || $resp === '') {
            return ['ok' => 0, 'message' => 'Empty OpenAI response: ' . $err];
        }
        if ($code < 200 || $code >= 300) {
            return ['ok' => 0, 'message' => 'OpenAI error ' . $code . ': ' . mb_substr($resp, 0, 300)];
        }
        $decoded = json_decode($resp, true);
        $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            return ['ok' => 0, 'message' => 'Invalid OpenAI payload'];
        }
        $json = json_decode($content, true);
        if (!\is_array($json)) {
            return ['ok' => 0, 'message' => 'Model JSON parse failed'];
        }
        $title = trim((string)($json['title'] ?? ''));
        $excerpt = trim((string)($json['excerpt'] ?? ''));
        $bodyHtml = trim((string)($json['bodyHtml'] ?? ''));
        if ($title === '' || $bodyHtml === '') {
            return ['ok' => 0, 'message' => 'Missing title/bodyHtml'];
        }
        return ['ok' => 1, 'title' => $title, 'excerpt' => $excerpt, 'bodyHtml' => $bodyHtml];
    }

    private function slugify(string $text): string
    {
        $s = mb_strtolower(trim($text));
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?? '';
        $s = trim($s, '-');
        if ($s === '') {
            $s = 'blog-post';
        }
        return mb_substr($s, 0, 160);
    }
}

