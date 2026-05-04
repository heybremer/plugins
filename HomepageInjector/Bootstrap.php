<?php

namespace Plugin\HomepageInjector;

use JTL\Events\Dispatcher;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private static bool $settingsEnsured = false;
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureSettingsTable();
        $this->ensureAdminMenuEntry();

        // Ensure global flag + JS is available (homepage only)
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (Shop::getPageType() !== \PAGE_STARTSEITE) {
                return;
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $js = 'plugins/HomepageInjector/templates/js/homepage-injector.js';
            if (!\in_array($js, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $js;
            }
        });

        // Inject config on homepage HTML
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (Shop::getPageType() !== \PAGE_STARTSEITE) {
                return;
            }
            if (!isset($args['document'])) {
                return;
            }
            $cfg = $this->fetchSettings();
            $payload = [
                'enabled' => (int)($cfg['enabled'] ?? 1) === 1,
                'targetSelector' => (string)($cfg['targetSelector'] ?? 'div.index-filter-konfigurator'),
                'targetMode' => (string)($cfg['targetMode'] ?? 'after'),
                'order' => \is_array($cfg['order'] ?? null) ? $cfg['order'] : ['CardProduct', 'MarkeFiltre', 'Top10List'],
            ];
            $json = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            if (!\is_string($json)) {
                return;
            }
            $script = '<script>'
                . 'window.WomosiHomepageInjectorEnabled=1;'
                . 'window.WomosiHomepageInjectorConfig=' . $json . ';'
                . '</script>';

            $doc = $args['document'];
            if ($doc->find('script:contains("WomosiHomepageInjectorConfig")')->length > 0) {
                return;
            }
            $head = $doc->find('head')->eq(0);
            if ($head->length > 0) {
                $head->append($script);
                return;
            }
            $body = $doc->find('body')->eq(0);
            if ($body->length > 0) {
                $body->prepend($script);
            }
        });
    }

    private function ensureSettingsTable(): void
    {
        if (self::$settingsEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_homepage_injector_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nEnabled TINYINT(1) NOT NULL DEFAULT 1,
                    cTargetSelector VARCHAR(255) NOT NULL DEFAULT 'div.index-filter-konfigurator',
                    cTargetMode VARCHAR(16) NOT NULL DEFAULT 'after',
                    cOrderJson LONGTEXT NULL,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $row = $db->getSingleObject("SELECT kID FROM twomosi_homepage_injector_settings LIMIT 1");
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nEnabled = 1;
                $ins->cTargetSelector = 'div.index-filter-konfigurator';
                $ins->cTargetMode = 'after';
                $ins->cOrderJson = \json_encode(['CardProduct', 'MarkeFiltre', 'Top10List'], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_homepage_injector_settings', $ins);
            }
            self::$settingsEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{enabled:int,targetSelector:string,targetMode:string,order:array<int,string>}
     */
    private function fetchSettings(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject("SELECT * FROM twomosi_homepage_injector_settings ORDER BY kID ASC LIMIT 1");
            if ($row === null) {
                return ['enabled' => 1, 'targetSelector' => 'div.index-filter-konfigurator', 'targetMode' => 'after', 'order' => ['CardProduct', 'MarkeFiltre', 'Top10List']];
            }
            $order = [];
            $raw = (string)($row->cOrderJson ?? '');
            if ($raw !== '') {
                $d = \json_decode($raw, true);
                if (\is_array($d)) {
                    foreach ($d as $v) {
                        $s = \trim((string)$v);
                        if ($s !== '') {
                            $order[] = $s;
                        }
                    }
                }
            }
            if ($order === []) {
                $order = ['CardProduct', 'MarkeFiltre', 'Top10List'];
            }
            return [
                'enabled' => (int)($row->nEnabled ?? 1),
                'targetSelector' => (string)($row->cTargetSelector ?? 'div.index-filter-konfigurator'),
                'targetMode' => (string)($row->cTargetMode ?? 'after'),
                'order' => $order,
            ];
        } catch (\Throwable $e) {
            return ['enabled' => 1, 'targetSelector' => 'div.index-filter-konfigurator', 'targetMode' => 'after', 'order' => ['CardProduct', 'MarkeFiltre', 'Top10List']];
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
                ['kPlugin' => $pluginID, 'cDateiname' => $file]
            );
            if ($exists === null) {
                $menu = new \stdClass();
                $menu->kPlugin = $pluginID;
                $menu->cName = 'Homepage Injector';
                $menu->cDateiname = $file;
                $menu->nSort = 10;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

