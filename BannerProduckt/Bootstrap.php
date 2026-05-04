<?php

namespace Plugin\BannerProduckt;

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
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }

            $css = 'plugins/BannerProduckt/templates/css/bannerproduckt.css';
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

            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 1) !== 1) {
                return;
            }

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/bannerproduckt.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            $smarty->assign('bpBanners', $settings['banners'] ?? []);
            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '') {
                return;
            }

            // Inject based on dashboard selector + mode. Multiple selectors supported (comma-separated).
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
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_bannerproduckt_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cBannersJson LONGTEXT NULL,
                    cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
                    cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after',
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            // backward compat
            try { $db->executeQuery("ALTER TABLE twomosi_bannerproduckt_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator' AFTER cBannersJson"); } catch (\Throwable $e) {}
            try { $db->executeQuery("ALTER TABLE twomosi_bannerproduckt_settings ADD COLUMN cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after' AFTER cInjectSelector"); } catch (\Throwable $e) {}
            $exists = $db->getSingleObject("SELECT kID FROM twomosi_bannerproduckt_settings LIMIT 1");
            if ($exists === null) {
                $row = new \stdClass();
                $row->nActive = 1;
                $row->cBannersJson = json_encode($this->defaultBanners(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $row->cInjectSelector = 'div.index-filter-konfigurator';
                $row->cInjectMode = 'after';
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_bannerproduckt_settings', $row);
            }
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
                $menu->cName = 'BannerProduckt Dashboard';
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
    private function defaultBanners(): array
    {
        return [
            [
                'eyebrow' => 'NEW ARRIVALS',
                'title' => 'Flash Wireless',
                'subtitle' => 'Earphones',
                'image' => '',
                'url' => '#',
                'variant' => 'small',
            ],
            [
                'eyebrow' => 'NEW ARRIVALS',
                'title' => 'Flash Wireless',
                'subtitle' => 'Earphones',
                'image' => '',
                'url' => '#',
                'variant' => 'small',
            ],
            [
                'eyebrow' => 'New Collection',
                'title' => 'TOP CAMERA',
                'subtitle' => 'Mirrorless',
                'image' => '',
                'url' => '#',
                'variant' => 'tall',
                'cta' => 'SHOP NOW',
            ],
            [
                'eyebrow' => 'NEW ARRIVALS',
                'title' => 'Flash Wireless',
                'subtitle' => 'Earphones',
                'image' => '',
                'url' => '#',
                'variant' => 'small',
            ],
            [
                'eyebrow' => 'NEW ARRIVALS',
                'title' => 'Flash Wireless',
                'subtitle' => 'Earphones',
                'image' => '',
                'url' => '#',
                'variant' => 'small',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        try {
            // Lazy schema init: avoid DDL/meta queries on every process boot.
            $this->ensureTables();
            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject("SELECT * FROM twomosi_bannerproduckt_settings ORDER BY kID ASC LIMIT 1");
            if ($s === null) {
                return ['active' => 1, 'banners' => $this->defaultBanners()];
            }
            $banners = [];
            $raw = (string)($s->cBannersJson ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (\is_array($decoded)) {
                    $banners = $decoded;
                }
            }
            if (!\is_array($banners) || $banners === []) {
                $banners = $this->defaultBanners();
            }
            $mode = trim((string)($s->cInjectMode ?? 'after'));
            if (!\in_array($mode, ['after', 'before', 'prepend', 'append'], true)) {
                $mode = 'after';
            }
            return [
                'active' => (int)($s->nActive ?? 1),
                'banners' => $banners,
                'injectSelector' => (string)($s->cInjectSelector ?? 'div.index-filter-konfigurator'),
                'injectMode' => $mode,
            ];
        } catch (\Throwable $e) {
            return ['active' => 1, 'banners' => $this->defaultBanners()];
        }
    }
}

