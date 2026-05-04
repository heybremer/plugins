<?php

namespace Plugin\Messe;

require_once __DIR__ . '/src/MesseDatabase.php';
require_once __DIR__ . '/src/MesseController.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use Plugin\Messe\src\MesseDatabase;

final class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureAdminMenuEntry();
        MesseDatabase::ensurePluginPage((int)$this->getPlugin()->getID());

        // Assets only on messe page
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            MesseDatabase::ensureAll();
            $slug = $this->getConfiguredSlug();
            $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
            $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
            $path = \rtrim($path, '/');

            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
            $shopBasePath = \rtrim($shopBasePath, '/');

            $expected = $shopBasePath . '/' . $slug;
            if ($expected === '/') {
                $expected = '/' . $slug;
            }
            if ($path !== \rtrim($expected, '/')) {
                return;
            }

            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $cssPath = 'plugins/Messe/templates/css/messe-kalender.css';
            $jsPath  = 'plugins/Messe/templates/js/messe-kalender.js';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });
    }

    private function getConfiguredSlug(): string
    {
        $slug = 'messe-kalender';
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT cPageSlug FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            $s = $row !== null ? \trim((string)($row->cPageSlug ?? '')) : '';
            if ($s !== '') {
                $slug = $s;
            }
        } catch (\Throwable $e) {
            $slug = 'messe-kalender';
        }

        return ltrim($slug, '/');
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
                $menuItem->cName = 'Messe Kalender';
                $menuItem->cDateiname = $file;
                $menuItem->nSort = 25;
                $menuItem->nConf = 0;
                $db->insert('tpluginadminmenu', $menuItem);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}

