<?php

namespace Plugin\Produktcard;

require_once __DIR__ . '/src/ProduktcardDatabase.php';

use JTL\Events\Dispatcher;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private const TPL_KEY = 'womosi_produktcard';

    private const TPL_FILE = 'produktcard_grid.tpl';

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
            $cssPath = 'plugins/Produktcard/templates/css/produktcard.css';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }
            $jsPath = 'plugins/Produktcard/templates/js/produktcard.js';
            // Top10List nutzt cPluginJsHead_arr – dort einhängen, damit Tabs sicher funktionieren
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            ProduktcardDatabase::ensureAll();
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            if ($doc->find('[data-produktcard-injected="1"]')->length > 0) {
                return;
            }

            $tabs = $this->fetchActiveTabsWithProducts();
            if ($tabs === []) {
                return;
            }

            $settings = $this->fetchProduktcardSettings();
            $smarty->assign('produktcardTabs', $tabs);
            $smarty->assign('produktcardTitle', $settings['headline']);
            $smarty->assign('produktcardGridColumns', $settings['gridColumns']);
            $smarty->assign('produktcardImagePadding', $settings['imagePadding']);
            $smarty->assign('produktcardImageBgSizePercent', $settings['imageBgSizePercent']);
            $smarty->assign('produktcardAspectCss', $settings['aspectRatioCss']);
            $smarty->assign('produktcardUseCoverBg', $settings['imageBgSizePercent'] >= 100);

            $tplPath = $this->resolveTplPath();
            if ($tplPath === '') {
                return;
            }
            $html = $this->fetchMarkup($smarty, $tplPath);
            if ($html === '' || \stripos($html, 'produktcard-grid') === false) {
                return;
            }

            $injected = false;
            $pos = 'after';
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
            if (!$injected) {
                $content = $doc->find('div#content')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                    $injected = true;
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
        }, 35);
    }

    /**
     * @return array{headline: string, gridColumns: int, imagePadding: int, imageBgSizePercent: int, aspectRatio: string, aspectRatioCss: string}
     */
    private function fetchProduktcardSettings(): array
    {
        $defaults = [
            'headline' => 'Unsere Modelle',
            'gridColumns' => 4,
            'imagePadding' => 8,
            'imageBgSizePercent' => 100,
            'aspectRatio' => '1/1',
            'aspectRatioCss' => '1 / 1',
        ];
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_produktcard_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }
            $headline = \trim((string)($row->cHeadline ?? ''));
            if ($headline === '') {
                $headline = $defaults['headline'];
            }
            $gc = (int)($row->nGridColumns ?? 4);
            if ($gc < 1) {
                $gc = 1;
            }
            if ($gc > 6) {
                $gc = 6;
            }
            $pad = (int)($row->nImagePadding ?? 8);
            if ($pad < 0) {
                $pad = 0;
            }
            if ($pad > 48) {
                $pad = 48;
            }
            $bgp = (int)($row->nImageBgSizePercent ?? 100);
            if ($bgp < 50) {
                $bgp = 50;
            }
            if ($bgp > 100) {
                $bgp = 100;
            }
            $ar = \trim((string)($row->cAspectRatio ?? '1/1'));

            return [
                'headline' => $headline,
                'gridColumns' => $gc,
                'imagePadding' => $pad,
                'imageBgSizePercent' => $bgp,
                'aspectRatio' => $ar,
                'aspectRatioCss' => self::normalizeAspectRatioCss($ar),
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    private static function normalizeAspectRatioCss(string $ar): string
    {
        $ar = \trim($ar);
        if (\preg_match('#^(\d+(?:\.\d+)?)\s*/\s*(\d+(?:\.\d+)?)$#', $ar, $m)) {
            return $m[1] . ' / ' . $m[2];
        }

        return '1 / 1';
    }

    /**
     * Aktive Tabs mit jeweils mindestens einem gültigen, aktiven Produkt (Bild + Link).
     *
     * @return array<int, array{id: int, label: string, allUrl: string, products: array<int, array{id: int, title: string, img: string, url: string}>}>
     */
    private function fetchActiveTabsWithProducts(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $tabRows = $db->getObjects(
                'SELECT kID, cLabel, cAllUrl
                   FROM twomosi_produktcard_tab
                  WHERE nActive = 1
               ORDER BY nSort ASC, kID ASC'
            );
            if (!\is_array($tabRows) || $tabRows === []) {
                return [];
            }

            $rows = $db->getObjects(
                "SELECT p.kID, p.kTabID, p.cTitle, p.cBildUrl, p.cLinkUrl
                   FROM twomosi_produktcard p
                  INNER JOIN twomosi_produktcard_tab t ON t.kID = p.kTabID AND t.nActive = 1
                  WHERE p.nActive = 1
                    AND TRIM(p.cBildUrl) <> ''
                    AND TRIM(p.cLinkUrl) <> ''
               ORDER BY p.nSort ASC, p.kID ASC"
            );
            if (!\is_array($rows)) {
                $rows = [];
            }

            $byTab = [];
            foreach ($rows as $r) {
                $tid = (int)($r->kTabID ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $img = \trim((string)($r->cBildUrl ?? ''));
                $url = \trim((string)($r->cLinkUrl ?? ''));
                if ($img === '' || $url === '') {
                    continue;
                }
                if (!isset($byTab[$tid])) {
                    $byTab[$tid] = [];
                }
                $byTab[$tid][] = [
                    'id' => (int)($r->kID ?? 0),
                    'title' => \trim((string)($r->cTitle ?? '')),
                    'img' => $img,
                    'url' => $url,
                ];
            }

            $out = [];
            foreach ($tabRows as $tr) {
                $tid = (int)($tr->kID ?? 0);
                if ($tid <= 0 || empty($byTab[$tid])) {
                    continue;
                }
                $out[] = [
                    'id' => $tid,
                    'label' => \trim((string)($tr->cLabel ?? '')),
                    'allUrl' => \trim((string)($tr->cAllUrl ?? '')),
                    'products' => $byTab[$tid],
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchMarkup(JTLSmarty $smarty, string $tplPath): string
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
            // Fallback
        }
        $norm = \str_replace('\\', '/', $real !== false ? $real : $tplPath);
        try {
            return $smarty->fetch('file:' . $norm);
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function resolveTplPath(): string
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

        // JTL-Wawi/Admin öffnen Artikel oft als /index.php?a=123.
        // Das ist KEINE Startseite – sonst würden Homepage-Plugins auf Artikeldetailseiten injiziert.
        if ($query !== '' && \preg_match('/(?:^|&)a=\d+(?:&|$)/', $query) === 1) {
            return false;
        }

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
                $menuItem->cName = 'Produktcard';
                $menuItem->cDateiname = $file;
                $menuItem->nSort = 22;
                $menuItem->nConf = 0;
                $db->insert('tpluginadminmenu', $menuItem);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // no hard fail
        }
    }
}
