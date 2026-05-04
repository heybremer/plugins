<?php

namespace Plugin\MarkeFiltre;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Smarty\JTLSmarty;
use JTL\Shop;
use Psr\Http\Message\ServerRequestInterface;
use Plugin\MarkeFiltre\BrandListLink;
use Plugin\MarkeFiltre\Controller\MarkeFiltreController;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;
    private static bool $settingsEnsured = false;
    private static bool $brandsCacheEnsured = false;

    /** @var array<int, true> Pro Request: Legacy-URL-Rebuild je Sprache nur einmal. */
    private array $brandCacheLegacyRebuildDone = [];

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureAdminMenuEntry();

        /**
         * Fallback: Wenn weder einzelne plugin-URLs noch Minify-Bundle im Head vorkommen,
         * werden CSS/JS direkt eingefügt (OPC, Child-Theme ohne cPluginJsHead_arr, fehlender Hook).
         */
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            $smarty = $args['smarty'] ?? null;
            $this->ensureMarkefiltreAssetsInHead($args['document'], $smarty instanceof JTLSmarty ? $smarty : null);
        });

        // Theme (z. B. page/index.tpl): {include file=$MarkeFiltreWidgetTpl}
        $dispatcher->hookInto(\HOOK_SMARTY_INC, function (array $args): void {
            if (!isset($args['smarty']) || !($args['smarty'] instanceof JTLSmarty)) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            try {
                $widgetPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/markefiltre.tpl';
                $abs        = \realpath($widgetPath);
                if ($abs !== false && \is_file($abs)) {
                    $smarty->assign('MarkeFiltreWidgetTpl', 'file:' . \str_replace('\\', '/', $abs));
                }
            } catch (\Throwable $e) {
            }
            if ($smarty->getTemplateVars('mfBrandsJson') !== null) {
                return;
            }
            if (!$this->isHomepageRequest()) {
                return;
            }
            try {
                $cfg = $this->getInjectionConfig();
                if (!$cfg['showPKW'] && !$cfg['showWohnmobil']) {
                    $smarty->assign('mfBrandsJson', '{}');

                    return;
                }
                $smarty->assign('mfBrandsJson', $this->buildBrandsJsonForTplInline());
            } catch (\Throwable $e) {
                $smarty->assign('mfBrandsJson', '{}');
            }
        });

        // CSS/JS nur Startseite: Widget wird nur dort injiziert (HOOK_SMARTY_OUTPUTFILTER unten).
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

            $cssPath = 'plugins/MarkeFiltre/templates/css/markefiltre.css';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }
            $jsPath = 'plugins/MarkeFiltre/templates/js/markefiltre.js';
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });

        // JSON endpoint: /mf-brands
        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
            /** @var Router $router */
            $router = $args['router'];
            $handler = function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                $ctl = new MarkeFiltreController();
                return $ctl->brands($request);
            };

            // Root install
            $router->addRoute('/mf-brands', $handler, 'mf-brands', ['GET']);
            // Subdir install (/WomosiWeb)
            $router->addRoute('/WomosiWeb/mf-brands', $handler, 'mf-brands-subdir', ['GET']);

            $handlerKonfig = function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                $ctl = new MarkeFiltreController();

                return $ctl->konfigUrl($request);
            };
            $router->addRoute('/mf-konfig-url', $handlerKonfig, 'mf-konfig-url', ['GET']);
            $router->addRoute('/WomosiWeb/mf-konfig-url', $handlerKonfig, 'mf-konfig-url-subdir', ['GET']);
        });

        // Startseite: MarkeFiltre-Widget gemäß Dashboard-Einstellung injizieren.
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }

            $doc = $args['document'];
            if ($doc->find('#br-caravan-root')->length > 0 || $doc->find('#markefiltre-mount')->length > 0) {
                return; // already injected
            }

            $cfg = $this->getInjectionConfig();
            $selector = \trim((string)($cfg['selector'] ?? ''));
            $mode     = \trim((string)($cfg['mode'] ?? ''));
            $showPKW = (bool)($cfg['showPKW'] ?? true);
            $showWohnmobil = (bool)($cfg['showWohnmobil'] ?? true);
            if ($selector === '') {
                // Dashboard ayarı okunamadıysa bile bileşen görünür olsun diye güvenli fallback.
                $selector = 'main';
            }
            if ($mode === '') {
                $mode = 'after';
            }
            if (!$showPKW && !$showWohnmobil) {
                return;
            }

            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/markefiltre.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            try {
                $smarty->assign('mfBrandsJson', $this->buildBrandsJsonForTplInline());
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if (\trim((string)$html) === '') {
                return;
            }

            // Wrap + client-side relocation:
            // Some targets (e.g. #index-filter-konfigurator) may be injected later by other outputfilters.
            $esc = static function (string $s): string {
                return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
            };
            $wrap = '<div id="markefiltre-mount" data-selector="' . $esc($selector) . '" data-mode="' . $esc($mode) . '">'
                . $html
                . '</div>'
                . "<script>(function(){try{if(function(){try{var c=window.WomosiHomepageInjectorConfig;if(window.WomosiHomepageInjectorEnabled&&c&&c.enabled!==false&&Array.isArray(c.order)&&c.order.indexOf('MarkeFiltre')!==-1)return 1}catch(e){}return 0}())return;function mfPlaceGo(){var m=document.getElementById('markefiltre-mount');if(!m){requestAnimationFrame(mfPlaceGo);return;}var sel=String(m.getAttribute('data-selector')||'').trim();var mode=String(m.getAttribute('data-mode')||'').trim();function normalizeSel(s){s=String(s||'').trim();if(!s)return '';var m1=s.match(/class\\s*=\\s*['\\\"]([^'\\\"]+)['\\\"]/i);if(m1&&m1[1]){var c=String(m1[1]).trim().split(/\\s+/)[0];if(c)return '.'+c;}var m2=s.match(/id\\s*=\\s*['\\\"]([^'\\\"]+)['\\\"]/i);if(m2&&m2[1]){var id=String(m2[1]).trim();if(id)return '#'+id;}var _mfT={footer:1,header:1,main:1,nav:1,aside:1,section:1,article:1,body:1};if(/^[a-z]+$/i.test(s)&&_mfT[s.toLowerCase()])return s.toLowerCase();if(/^[A-Za-z0-9_-]+$/.test(s))return '#'+s;var toks=s.split(/\\s+/);if(toks.length===2&&/^[A-Za-z]+$/.test(toks[0])&&/^[A-Za-z0-9_-]+$/.test(toks[1]))return toks[0]+'.'+toks[1];return s;}sel=normalizeSel(sel);function safeQueryAll(s){try{return document.querySelectorAll(s);}catch(e){return null;}}function findTarget(){if(!sel)return null;var nodes=safeQueryAll(sel);if(!nodes||!nodes.length)return null;for(var i=0;i<nodes.length;i++){var n=nodes[i];if(n&&n.nodeType===1&&!m.contains(n)&&n!==m)return n;}return nodes[0]||null;}function doPlace(t){if(!t)return false;try{if(mode==='prepend'){t.insertBefore(m,t.firstChild);}else if(mode==='append'){t.appendChild(m);}else if(mode==='before'){t.parentNode&&t.parentNode.insertBefore(m,t);}else{t.parentNode&&t.parentNode.insertBefore(m,t.nextSibling);}return true;}catch(e){return false;}}function place(){return doPlace(findTarget());}if(place())return;var stopAt=Date.now()+60000;var iv=setInterval(function(){if(place()||Date.now()>stopAt){clearInterval(iv);}},250);var mo=null;try{mo=new MutationObserver(function(){if(place()){try{mo.disconnect();}catch(e){} clearInterval(iv);}});mo.observe(document.documentElement||document.body,{childList:true,subtree:true});}catch(e){}}mfPlaceGo();}catch(e){}})();</script>";

            // Try server-side first if target already exists
            $target = $doc->find($selector)->eq(0);
            if ($target->length > 0) {
                if ($mode === 'prepend') {
                    $target->prepend($wrap);
                } elseif ($mode === 'append') {
                    $target->append($wrap);
                } elseif ($mode === 'before') {
                    $target->before($wrap);
                } else { // after
                    $target->after($wrap);
                }
                return;
            }

            // Fallback: append to main/body, JS will move it when target appears
            $main = $doc->find('main#main-wrapper')->eq(0);
            if ($main->length > 0) {
                $main->append($wrap);
                return;
            }
            $genericMain = $doc->find('main')->eq(0);
            if ($genericMain->length > 0) {
                $genericMain->append($wrap);
                return;
            }
            $body = $doc->find('body')->eq(0);
            if ($body->length > 0) {
                $body->append($wrap);
            }
        });

        /**
         * Theme setzt für alle Plugin-JS nur ?v={$nTemplateVersion} (z. B. 1.6.0) — nicht die Plugin-Version.
         * Dadurch bleibt markefiltre.js nach Eklenti-Updates gecacht. Wir hängen -mf{SemVer} an den v-Wert an.
         */
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            $doc = $args['document'];
            $scripts = $doc->find('script[src*="markefiltre.js"]');
            if ($scripts->length === 0) {
                return;
            }
            try {
                $mfVer = (string) $this->getPlugin()->getMeta()->getSemVer()->getOriginalVersion();
            } catch (\Throwable $e) {
                $mfVer = '';
            }
            if ($mfVer === '') {
                try {
                    $mfVer = (string) $this->getPlugin()->getMeta()->getSemVer();
                } catch (\Throwable $e) {
                    $mfVer = '0';
                }
            }
            $mfVer = \preg_replace('/[^0-9A-Za-z._-]/', '', $mfVer);
            if ($mfVer === '') {
                $mfVer = '0';
            }
            $n = (int) $scripts->length;
            for ($i = 0; $i < $n; $i++) {
                $node = $scripts->eq($i);
                $src  = (string) $node->attr('src');
                if ($src === '' || \stripos($src, '-mf') !== false) {
                    continue;
                }
                if (\preg_match('/([?&])v=([^&]+)/', $src, $m)) {
                    $newSrc = \preg_replace('/([?&])v=([^&]+)/', '$1v=$2-mf' . $mfVer, $src, 1);
                } else {
                    $sep    = \strpos($src, '?') !== false ? '&' : '?';
                    $newSrc = $src . $sep . 'v=mf' . $mfVer;
                }
                if ($newSrc !== $src) {
                    $node->attr('src', $newSrc);
                }
            }
        });
    }

    /**
     * Fehlende Asset-Tags im Head ergänzen (siehe HOOK_LETZTERINCLUDE in {@see boot()}).
     */
    private function ensureMarkefiltreAssetsInHead($doc, ?JTLSmarty $smarty): void
    {
        if (!$this->isHomepageRequest()) {
            return;
        }
        $cfg = $this->getInjectionConfig();
        if (!$cfg['showPKW'] && !$cfg['showWohnmobil']) {
            return;
        }
        $head = $doc->find('head')->eq(0);
        if ($head->length === 0) {
            return;
        }
        try {
            $headInner = (string) $head->html();
        } catch (\Throwable $e) {
            return;
        }
        if (\stripos($headInner, 'markefiltre.js') !== false) {
            return;
        }
        if (\preg_match('/<script[^>]+src=[^>]*plugin_js_head/i', $headInner)) {
            return;
        }
        $needCss = \stripos($headInner, 'markefiltre.css') === false
            && !\preg_match('/href=[^>]*plugin_css/i', $headInner);

        $tplVer = '1';
        if ($smarty !== null) {
            try {
                $tv = $smarty->getTemplateVars('nTemplateVersion');
                if ($tv !== null && $tv !== '') {
                    $tplVer = (string) $tv;
                }
            } catch (\Throwable $e) {
            }
        }
        try {
            $mfVer = (string) $this->getPlugin()->getMeta()->getSemVer()->getOriginalVersion();
        } catch (\Throwable $e) {
            $mfVer = '';
        }
        if ($mfVer === '') {
            try {
                $mfVer = (string) $this->getPlugin()->getMeta()->getSemVer();
            } catch (\Throwable $e) {
                $mfVer = '0';
            }
        }
        $mfVer = \preg_replace('/[^0-9A-Za-z._-]/', '', $mfVer);
        if ($mfVer === '') {
            $mfVer = '0';
        }
        $v = $tplVer . '-mf' . $mfVer;

        $shopUrl = \rtrim((string) Shop::getURL(), '/');
        $cssHref = $shopUrl . '/plugins/MarkeFiltre/templates/css/markefiltre.css?v=' . \rawurlencode($v);
        $jsSrc   = $shopUrl . '/plugins/MarkeFiltre/templates/js/markefiltre.js?v=' . \rawurlencode($v);

        $parts = [];
        if ($needCss) {
            $parts[] = '<link rel="stylesheet" href="' . \htmlspecialchars($cssHref, \ENT_QUOTES, 'UTF-8') . '">';
        }
        $parts[] = '<script defer src="' . \htmlspecialchars($jsSrc, \ENT_QUOTES, 'UTF-8') . '"></script>';
        try {
            $head->prepend(\implode('', $parts));
        } catch (\Throwable $e) {
        }
    }

    /**
     * Startseite-Erkennung: PAGE_STARTSEITE und Fallback per Request-Pfad (Subfolder-Installs).
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

    /**
     * JSON für Inline-Script: schließt XSS via Merkmal-Texte (z. B. {@code </script>}) aus.
     */
    private function encodeBrandsPayloadForInline(array $payload): string
    {
        $json = \json_encode(
            $payload,
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
            | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT
        );

        return \is_string($json) ? $json : '{}';
    }

    private function buildBrandsJsonForTplInline(): string
    {
        try {
            $db = Shop::Container()->getDB();
            $langID = (int)Shop::getLanguageID();
            $cfg = $this->getInjectionConfig();
            $showPKW = (bool)($cfg['showPKW'] ?? true);
            $showWohnmobil = (bool)($cfg['showWohnmobil'] ?? true);
            $tryLangs = [$langID];
            if ($langID !== 1) {
                $tryLangs[] = 1;
            }
            foreach ($tryLangs as $lid) {
                $this->maybeRefreshBrandsCache($lid);
                $payload = $this->loadBrandsPayloadFromCache($lid);
                if ($payload === null) {
                    // Some installs may not match the strict merkmal-name conditions.
                    // Force a rebuild attempt once, then try again.
                    $this->rebuildBrandsCache($lid);
                    $payload = $this->loadBrandsPayloadFromCache($lid);
                }
                if ($payload !== null) {
                    if (!$showPKW) {
                        $payload['PKW'] = [];
                    }
                    if (!$showWohnmobil) {
                        $payload['Wohnmobil'] = [];
                    }
                    return $this->encodeBrandsPayloadForInline($payload);
                }
            }

            // Last resort: live query nur Merkmal „Fahrzeug-Marke“ / „Fahrzeug Marke“ (tmerkmalsprache.cName).
            // Try current + default language IDs because some shops have merkmalsprache only in one language.
            foreach ($tryLangs as $lid2) {
                $rows = $db->getObjects(
                    "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                       FROM tartikel a
                 INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                 INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
                 INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                                  AND ms_her.kSprache = :lid
                                                  AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
                 INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                        AND tmws_her.kSprache     = :lid
                      WHERE a.nIstVater = 0
                        AND TRIM(tmws_her.cWert) <> ''
                        AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
                   ORDER BY tmws_her.cWert ASC
                      LIMIT 2000",
                    ['lid' => $lid2]
                );
                $items = [];
                if (\is_array($rows)) {
                    foreach ($rows as $r) {
                        $name = \trim((string)($r->name ?? ''));
                        $kid  = (int)($r->kMerkmalWert ?? 0);
                        if ($name !== '' && $kid > 0) {
                            $items[] = [
                                'name' => $name,
                                'url'  => BrandListLink::forMerkmalWert($db, $lid2, $kid),
                            ];
                        }
                    }
                }
                if ($items !== []) {
                    $payload = [
                        'PKW' => $showPKW ? $items : [],
                        'Wohnmobil' => $showWohnmobil ? $items : [],
                    ];
                    return $this->encodeBrandsPayloadForInline($payload);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '{}';
    }

    private function ensureBrandsCacheTables(): void
    {
        if (self::$brandsCacheEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_markefiltre_brands (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cType VARCHAR(16) NOT NULL,
                    cName VARCHAR(191) NOT NULL,
                    cUrl VARCHAR(512) NOT NULL DEFAULT '',
                    dUpdated DATETIME NOT NULL,
                    UNIQUE KEY uq_mf_brand (kSprache, cType, cName),
                    KEY ix_mf_brand_lang_type (kSprache, cType),
                    KEY ix_mf_brand_updated (dUpdated)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            try {
                $db->executeQuery(
                    'ALTER TABLE twomosi_markefiltre_brands MODIFY cUrl VARCHAR(2048) NOT NULL DEFAULT \'\''
                );
            } catch (\Throwable $e) {
                // Spalte existiert evtl. schon breiter oder ALTER nicht erlaubt
            }
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_markefiltre_meta (
                    cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                    cValue VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            self::$brandsCacheEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Cache noch mit reiner Filter-URL (?qs=, ?mf=) statt Merkmalwert-Listenroute.
     * Alte Cache-Zeilen mit ?m= einmal migrieren (neue Links nutzen /slug oder /characteristics/id/…, nicht ?m=).
     */
    private function brandCacheNeedsMerkmalUrlRefresh(int $langID): bool
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                'SELECT 1 AS x
                   FROM twomosi_markefiltre_brands
                  WHERE kSprache = :lid
                    AND (
                        cUrl LIKE :p1
                     OR cUrl LIKE :p2
                     OR cUrl LIKE :p3
                     OR cUrl LIKE :p4
                    )
                  LIMIT 1',
                [
                    'lid' => $langID,
                    'p1'  => '%?qs=%',
                    'p2'  => '%?mf=%',
                    'p3'  => '%&mf=%',
                    'p4'  => '%?m=%',
                ]
            );

            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Returns payload from cache or null if empty.
     * @return array{PKW:array<int,array{name:string,url:string}>,Wohnmobil:array<int,array{name:string,url:string}>}|null
     */
    private function loadBrandsPayloadFromCache(int $langID): ?array
    {
        $this->ensureBrandsCacheTables();
        try {
            $db = Shop::Container()->getDB();
            if (!isset($this->brandCacheLegacyRebuildDone[$langID])
                && $this->brandCacheNeedsMerkmalUrlRefresh($langID)) {
                $this->brandCacheLegacyRebuildDone[$langID] = true;
                $this->rebuildBrandsCache($langID);
            }
            $rows = $db->getObjects(
                "SELECT cType, cName, cUrl
                   FROM twomosi_markefiltre_brands
                  WHERE kSprache = :lid
               ORDER BY cType ASC, cName ASC",
                ['lid' => $langID]
            );
            $pkw = [];
            $wohn = [];
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $type = \trim((string)($r->cType ?? ''));
                    $name = \trim((string)($r->cName ?? ''));
                    $url  = (string)($r->cUrl ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $item = ['name' => $name, 'url' => $url];
                    if ($type === 'Wohnmobil') {
                        $wohn[] = $item;
                    } else {
                        $pkw[] = $item;
                    }
                }
            }
            if ($pkw === [] && $wohn === []) {
                return null;
            }
            return ['PKW' => $pkw, 'Wohnmobil' => $wohn];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function maybeRefreshBrandsCache(int $langID): void
    {
        $this->ensureBrandsCacheTables();
        // Refresh conditions:
        // - cache is empty OR
        // - scheduled weekday/hour match AND last refresh is older than ~6 days (weekly)
        try {
            $db = Shop::Container()->getDB();
            $cntRow = $db->getSingleObject(
                "SELECT COUNT(*) AS cnt FROM twomosi_markefiltre_brands WHERE kSprache = :lid",
                ['lid' => $langID]
            );
            $cnt = $cntRow !== null ? (int)($cntRow->cnt ?? 0) : 0;
            $isEmpty = ($cnt <= 0);

            $sched = $this->getRefreshSchedule();
            $dowTarget = (int)($sched['dow'] ?? 0);
            $hourTarget = (int)($sched['hour'] ?? 3);

            $now = new \DateTimeImmutable('now');
            $dowNow = (int)$now->format('w'); // 0=Sun..6=Sat
            $hourNow = (int)$now->format('G'); // 0..23

            $key = 'brands_last_refresh_lang_' . $langID;
            $row = $db->getSingleObject("SELECT cValue FROM twomosi_markefiltre_meta WHERE cKey = :k LIMIT 1", ['k' => $key]);
            $last = null;
            if ($row !== null && isset($row->cValue)) {
                try { $last = new \DateTimeImmutable((string)$row->cValue); } catch (\Throwable $e) { $last = null; }
            }

            $scheduledNow = ($dowNow === $dowTarget && $hourNow === $hourTarget);
            $olderThanWeek = true;
            if ($last instanceof \DateTimeImmutable) {
                $olderThanWeek = ($last < $now->sub(new \DateInterval('P6D')));
            }

            if ($isEmpty || ($scheduledNow && $olderThanWeek)) {
                $this->rebuildBrandsCache($langID);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{dow:int,hour:int}
     */
    private function getRefreshSchedule(): array
    {
        // Stored in settings table (latest row). Fallback: Sunday 03:00.
        try {
            $this->ensureSettingsTable();
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                "SELECT nRefreshDow, nRefreshHour
                   FROM twomosi_markefiltre_settings
               ORDER BY dUpdated DESC, kID DESC
                  LIMIT 1"
            );
            if ($row !== null) {
                $dow = (int)($row->nRefreshDow ?? 0);
                $hr  = (int)($row->nRefreshHour ?? 3);
                if ($dow < 0 || $dow > 6) { $dow = 0; }
                if ($hr < 0 || $hr > 23) { $hr = 3; }
                return ['dow' => $dow, 'hour' => $hr];
            }
        } catch (\Throwable $e) {}
        return ['dow' => 0, 'hour' => 3];
    }

    private function rebuildBrandsCache(int $langID): void
    {
        $this->ensureBrandsCacheTables();
        try {
            $db = Shop::Container()->getDB();

            // Wohnmobil brands
            $wohnRows = $db->getObjects(
                "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                   FROM tartikel a
             INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
             INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
             INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
             INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                              AND ms_typ.kSprache = :lid
                                              AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR (ms_typ.cName LIKE '%Aufbauart%'))
             INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
             INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
             INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
             INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                              AND ms_her.kSprache = :lid
                                              AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
             INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                    AND tmws_her.kSprache     = :lid
                  WHERE a.nIstVater = 0
                    AND TRIM(tmws_her.cWert) <> ''
                    AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
               ORDER BY tmws_her.cWert ASC
                  LIMIT 2000",
                ['lid' => $langID]
            ) ?: [];

            // PKW brands = brands on products without Aufbauart
            $pkwRows = $db->getObjects(
                "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                   FROM tartikel a
             INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
             INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
             INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
             INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                              AND ms_her.kSprache = :lid
                                              AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
             INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                    AND tmws_her.kSprache     = :lid
                  WHERE a.nIstVater = 0
                    AND TRIM(tmws_her.cWert) <> ''
                    AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
                    AND NOT EXISTS (
                        SELECT 1
                          FROM tartikelmerkmal am_typ
                    INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                    INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
                    INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                                     AND ms_typ.kSprache = :lid
                                                     AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR (ms_typ.cName LIKE '%Aufbauart%'))
                         WHERE am_typ.kArtikel = a.kArtikel
                    )
               ORDER BY tmws_her.cWert ASC
                  LIMIT 2000",
                ['lid' => $langID]
            ) ?: [];

            $wohnPrepared = [];
            foreach ((\is_array($wohnRows) ? $wohnRows : []) as $r) {
                $name = \trim((string) ($r->name ?? ''));
                $kid  = (int) ($r->kMerkmalWert ?? 0);
                if ($name === '' || $kid <= 0) {
                    continue;
                }
                $wohnPrepared[] = [
                    'type' => 'Wohnmobil',
                    'name' => $name,
                    'url'  => BrandListLink::forMerkmalWertStorable($db, $langID, $kid),
                ];
            }
            $pkwPrepared = [];
            foreach ((\is_array($pkwRows) ? $pkwRows : []) as $r) {
                $name = \trim((string) ($r->name ?? ''));
                $kid  = (int) ($r->kMerkmalWert ?? 0);
                if ($name === '' || $kid <= 0) {
                    continue;
                }
                $pkwPrepared[] = [
                    'type' => 'PKW',
                    'name' => $name,
                    'url'  => BrandListLink::forMerkmalWertStorable($db, $langID, $kid),
                ];
            }

            // Erst Daten berechnen, dann ersetzen — verhindert leere Tabelle bei INSERT-Fehlern.
            $db->executeQueryPrepared("DELETE FROM twomosi_markefiltre_brands WHERE kSprache = :lid", ['lid' => $langID]);

            foreach (\array_merge($wohnPrepared, $pkwPrepared) as $row) {
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_markefiltre_brands (kSprache, cType, cName, cUrl, dUpdated)
                     VALUES (:lid, :t, :n, :u, NOW())
                     ON DUPLICATE KEY UPDATE cUrl = VALUES(cUrl), dUpdated = NOW()",
                    [
                        'lid' => $langID,
                        't'   => $row['type'],
                        'n'   => $row['name'],
                        'u'   => $row['url'],
                    ]
                );
            }

            $key = 'brands_last_refresh_lang_' . $langID;
            $db->executeQueryPrepared(
                "INSERT INTO twomosi_markefiltre_meta (cKey, cValue)
                 VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)",
                ['k' => $key, 'v' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s')]
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensureSettingsTable(): void
    {
        if (self::$settingsEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_markefiltre_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    dUpdated DATETIME NOT NULL,
                    cSelector VARCHAR(255) NOT NULL DEFAULT '',
                    cMode VARCHAR(16) NOT NULL DEFAULT '',
                    nRefreshDow INT NOT NULL DEFAULT 0,
                    nRefreshHour INT NOT NULL DEFAULT 3,
                    bShowPKW TINYINT(1) NOT NULL DEFAULT 1,
                    bShowWohnmobil TINYINT(1) NOT NULL DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            // Backward compatible alters (ignore errors if columns exist)
            try { $db->executeQuery("ALTER TABLE twomosi_markefiltre_settings ADD COLUMN nRefreshDow INT NOT NULL DEFAULT 0"); } catch (\Throwable $e) {}
            try { $db->executeQuery("ALTER TABLE twomosi_markefiltre_settings ADD COLUMN nRefreshHour INT NOT NULL DEFAULT 3"); } catch (\Throwable $e) {}
            try { $db->executeQuery("ALTER TABLE twomosi_markefiltre_settings ADD COLUMN bShowPKW TINYINT(1) NOT NULL DEFAULT 1"); } catch (\Throwable $e) {}
            try { $db->executeQuery("ALTER TABLE twomosi_markefiltre_settings ADD COLUMN bShowWohnmobil TINYINT(1) NOT NULL DEFAULT 1"); } catch (\Throwable $e) {}
            $row = $db->getSingleObject("SELECT kID FROM twomosi_markefiltre_settings ORDER BY dUpdated DESC, kID DESC LIMIT 1");
            if ($row === null) {
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_markefiltre_settings (dUpdated, cSelector, cMode, nRefreshDow, nRefreshHour, bShowPKW, bShowWohnmobil)
                     VALUES (NOW(), :sel, :mode, 0, 3, 1, 1)",
                    ['sel' => 'main', 'mode' => 'after']
                );
            }
            self::$settingsEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{selector:string,mode:string,showPKW:bool,showWohnmobil:bool}
     */
    private function getInjectionConfig(): array
    {
        try {
            $this->ensureSettingsTable();
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                "SELECT cSelector, cMode, bShowPKW, bShowWohnmobil
                   FROM twomosi_markefiltre_settings
               ORDER BY dUpdated DESC, kID DESC
                  LIMIT 1"
            );
            if ($row !== null) {
                return [
                    'selector' => (string)($row->cSelector ?? ''),
                    'mode'     => (string)($row->cMode ?? ''),
                    'showPKW' => (int)($row->bShowPKW ?? 1) === 1,
                    'showWohnmobil' => (int)($row->bShowWohnmobil ?? 1) === 1,
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return ['selector' => '', 'mode' => '', 'showPKW' => true, 'showWohnmobil' => true];
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
            $menuItem->cName      = 'MarkeFiltre Dashboard';
            $menuItem->cDateiname = $file;
            $menuItem->nSort      = 30;
            $menuItem->nConf      = 0;

            $db->insert('tpluginadminmenu', $menuItem);
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // no hard failure
        }
    }
}

