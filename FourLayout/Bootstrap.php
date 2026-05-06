<?php

namespace Plugin\FourLayout;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $tablesEnsured = false;
    private static bool $adminMenuEnsured = false;
    /**
     * Cross-request fast path: schema init complete.
     */
    private const SCHEMA_APCU_KEY = 'womosi_fourlayout_schema_v1';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();

        // CSS nur Startseite (Block wird nur dort injiziert).
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            $css = 'plugins/FourLayout/templates/css/fourlayout.css';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
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
            $doc    = $args['document'];

            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 1) !== 1) {
                return;
            }

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/fourlayout.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            $smarty->assign('flItems', $settings['items'] ?? []);
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
    }

    /**
     * Startseite: PAGE_STARTSEITE + Pfad-Fallback (Subfolder-Installs).
     */
    private function isHomepageRequest(): bool
    {
        $uri   = (string)($_SERVER['REQUEST_URI'] ?? '');
        $query = (string)(\parse_url($uri, \PHP_URL_QUERY) ?? '');

        // Index-URL mit Suche/Merkmal-Filter ist keine „echte“ Startseite (z. B. ?suche=…&mf=…).
        if ($query !== '' && \preg_match('/(?:^|&)(suche|mf)=/i', $query) === 1) {
            return false;
        }

        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
        }
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $path = \rtrim($path, '/');
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
        $shopBasePath = \rtrim($shopBasePath, '/');

        return $path === $shopBasePath
            || $path === ($shopBasePath . '/index.php')
            || \str_starts_with($path, $shopBasePath . '/index.php');
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        if (\function_exists('apcu_fetch')) {
            try {
                if (@\apcu_fetch(self::SCHEMA_APCU_KEY) === true) {
                    self::$tablesEnsured = true;

                    return;
                }
            } catch (\Throwable $e) {
                // ignore APCu errors
            }
        }
        try {
            $db = Shop::Container()->getDB();
            // Cheap existence check: avoid DDL on every request.
            $existsRow = $db->getSingleObject(
                "SELECT COUNT(*) AS c
                   FROM information_schema.tables
                  WHERE table_schema = DATABASE()
                    AND table_name = 'twomosi_fourlayout_settings'"
            );
            $tableExists = $existsRow !== null && (int)($existsRow->c ?? 0) > 0;

            if (!$tableExists) {
                $db->executeQuery(
                    "CREATE TABLE IF NOT EXISTS twomosi_fourlayout_settings (
                        kID INT AUTO_INCREMENT PRIMARY KEY,
                        nActive TINYINT(1) NOT NULL DEFAULT 1,
                        cItemsJson LONGTEXT NULL,
                        cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
                        cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after',
                        dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } else {
                // Backward compat: add columns only if missing (single info_schema scan).
                $cols = $db->getObjects(
                    "SELECT COLUMN_NAME AS n
                       FROM information_schema.columns
                      WHERE table_schema = DATABASE()
                        AND table_name = 'twomosi_fourlayout_settings'"
                );
                $have = [];
                if (\is_array($cols)) {
                    foreach ($cols as $c) {
                        $have[(string)($c->n ?? '')] = true;
                    }
                }
                if (!isset($have['cInjectSelector'])) {
                    $db->executeQuery(
                        "ALTER TABLE twomosi_fourlayout_settings
                         ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator'
                         AFTER cItemsJson"
                    );
                }
                if (!isset($have['cInjectMode'])) {
                    $db->executeQuery(
                        "ALTER TABLE twomosi_fourlayout_settings
                         ADD COLUMN cInjectMode VARCHAR(20) NOT NULL DEFAULT 'after'
                         AFTER cInjectSelector"
                    );
                }
            }

            $exists = $db->getSingleObject("SELECT kID FROM twomosi_fourlayout_settings LIMIT 1");
            if ($exists === null) {
                $row = new \stdClass();
                $row->nActive = 1;
                $row->cItemsJson = json_encode($this->defaultItems(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $row->cInjectSelector = 'div.index-filter-konfigurator';
                $row->cInjectMode = 'after';
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_fourlayout_settings', $row);
            }

            self::$tablesEnsured = true;
            if (\function_exists('apcu_store')) {
                try {
                    @\apcu_store(self::SCHEMA_APCU_KEY, true, 604800);
                } catch (\Throwable $e) {
                    // ignore APCu errors
                }
            }
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
                $menu->cName = 'FourLayout Dashboard';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 31;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultItems(): array
    {
        return [
            [
                'title' => 'Videos',
                'subtitle' => 'Unsere Produkte und Tipps entdecken',
                'button' => 'Entdecken',
                'image' => '',
                'url' => '#',
                'rating' => 0,
            ],
            [
                'title' => 'Kundenbewertungen',
                'subtitle' => 'Echte Kundenmeinungen lesen.',
                'button' => 'Entdecken',
                'image' => '',
                'url' => '#',
                'rating' => 5,
            ],
            [
                'title' => 'Großhandelsanfrage',
                'subtitle' => 'Partner werden und Vorteile sichern.',
                'button' => 'Entdecken',
                'image' => '',
                'url' => '#',
                'rating' => 0,
            ],
            [
                'title' => 'Reduzierte Produkte',
                'subtitle' => 'Hochwertige Produkte günstig sichern.',
                'button' => 'Entdecken',
                'image' => '',
                'url' => '#',
                'rating' => 0,
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
            $s = $db->getSingleObject("SELECT * FROM twomosi_fourlayout_settings ORDER BY kID ASC LIMIT 1");
            if ($s === null) {
                return ['active' => 1, 'items' => $this->defaultItems()];
            }
            $items = [];
            $raw = (string)($s->cItemsJson ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (\is_array($decoded)) {
                    $items = $decoded;
                }
            }
            if (!\is_array($items) || $items === [] || \count($items) < 4) {
                $items = $this->defaultItems();
            }

            $mode = trim((string)($s->cInjectMode ?? 'after'));
            if (!\in_array($mode, ['after', 'before', 'prepend', 'append'], true)) {
                $mode = 'after';
            }
            return [
                'active' => (int)($s->nActive ?? 1),
                'items' => array_values(array_slice($items, 0, 4)),
                'injectSelector' => (string)($s->cInjectSelector ?? 'div.index-filter-konfigurator'),
                'injectMode' => $mode,
            ];
        } catch (\Throwable $e) {
            return ['active' => 1, 'items' => $this->defaultItems()];
        }
    }
}

