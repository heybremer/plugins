<?php

declare(strict_types=1);

namespace Plugin\ProdukteDescription;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $tablesEnsured = false;

    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (Shop::getPageType() !== \PAGE_ARTIKEL) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $css = 'plugins/ProdukteDescription/templates/css/produkte-description.css';
            $js  = 'plugins/ProdukteDescription/templates/js/produkte-description.js';
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
            if (Shop::getPageType() !== \PAGE_ARTIKEL) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            $artikel = $smarty->getTemplateVars('Artikel');
            $kArtikel = 0;
            if (\is_object($artikel) && isset($artikel->kArtikel)) {
                $kArtikel = (int)$artikel->kArtikel;
            }
            if ($kArtikel <= 0) {
                return;
            }

            $row = $this->loadArtikelRow($kArtikel);
            if ($row === null || (int)($row->nActive ?? 0) !== 1) {
                return;
            }

            $images = $this->decodeJsonList((string)($row->cImagesJson ?? ''));
            $tabs   = $this->decodeJsonTabs((string)($row->cTabsJson ?? ''), $images);
            if ($images === [] || $tabs === []) {
                return;
            }

            $tabsForTpl = [];
            foreach ($tabs as $t) {
                $urls = [];
                foreach ($t['slides'] as $si) {
                    if (isset($images[$si])) {
                        $urls[] = $images[$si];
                    }
                }
                if ($urls !== []) {
                    $tabsForTpl[] = ['title' => $t['title'], 'slideUrls' => $urls];
                }
            }
            if ($tabsForTpl === []) {
                return;
            }

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/produkte-description.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            $smarty->assign('pdTabs', $tabsForTpl);
            $smarty->assign('pdKArtikel', $kArtikel);

            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '' || \strpos($html, 'pd-root') === false) {
                return;
            }

            $nav = $doc->find('nav.nav-navitation')->eq(0);
            if ($nav->length < 1) {
                $nav = $doc->find('nav.nav-navigation')->eq(0);
            }
            if ($nav->length < 1) {
                $nav = $doc->find('nav[class*="navitation"], nav[class*="navigation"]')->eq(0);
            }
            if ($nav->length > 0) {
                $nav->before($html);

                return;
            }

            $h1 = $doc->find('h1.product-title, h1.h2.product-title, .product-head h1')->eq(0);
            if ($h1->length > 0) {
                $h1->before($html);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function decodeJsonList(string $json): array
    {
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
            if (\count($out) >= 7) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $images
     * @return list<array{title: string, slides: list<int>}>
     */
    private function decodeJsonTabs(string $json, array $images): array
    {
        $a = \json_decode($json, true);
        if (!\is_array($a)) {
            return [];
        }
        $max = \count($images);
        if ($max < 1) {
            return [];
        }
        $out = [];
        foreach ($a as $t) {
            if (!\is_array($t)) {
                continue;
            }
            $title = \trim((string)($t['title'] ?? ''));
            $slides = $t['slides'] ?? [];
            if ($title === '' || !\is_array($slides)) {
                continue;
            }
            $idx = [];
            foreach ($slides as $s) {
                $i = (int)$s;
                if ($i >= 0 && $i < $max) {
                    $idx[] = $i;
                }
            }
            $idx = \array_values(\array_unique($idx));
            if ($idx === []) {
                continue;
            }
            $out[] = ['title' => $title, 'slides' => $idx];
            if (\count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    private function loadArtikelRow(int $kArtikel): ?\stdClass
    {
        try {
            $this->ensureTables();
            $db = Shop::Container()->getDB();

            return $db->getSingleObject(
                'SELECT * FROM twomosi_prod_desc_artikel WHERE kArtikel = :k LIMIT 1',
                ['k' => $kArtikel]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_prod_desc_artikel (
                    kArtikel INT NOT NULL PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cImagesJson LONGTEXT NULL,
                    cTabsJson LONGTEXT NULL,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            self::$tablesEnsured = true;
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
            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin AND cDateiname = :c LIMIT 1',
                ['kPlugin' => $pluginID, 'c' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu = new \stdClass();
                $menu->kPlugin = $pluginID;
                $menu->cName = 'Produkte Description';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 88;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
