<?php

namespace Plugin\SideLeftFiltre;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use Plugin\SideLeftFiltre\src\SideLeftFiltreDatabase;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            $cfg = SideLeftFiltreDatabase::getConfig();
            if (!$cfg['active']) {
                return;
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $jsPath = 'plugins/SideLeftFiltre/templates/js/sideleftfiltre.js';
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });

        // Provide config as JSON in the DOM (no template needed)
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            $cfg = SideLeftFiltreDatabase::getConfig();
            if (!$cfg['active']) {
                return;
            }
            $payload = [
                'order' => $cfg['order'],
                'open'  => $cfg['open'],
                'close' => $cfg['close'],
                // default selector for NOVA (can be extended later)
                'rootSelector' => '#sidepanel_left',
            ];
            $json = \json_encode(
                $payload,
                \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
                | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT
            );
            if (!\is_string($json) || $json === '') {
                $json = '{}';
            }

            try {
                $body = $args['document']->find('body')->eq(0);
                if ($body->length <= 0) {
                    return;
                }
                // avoid duplicates
                if ($args['document']->find('#sideleftfiltre-config')->length > 0) {
                    return;
                }
                $body->append('<script type="application/json" id="sideleftfiltre-config">' . $json . '</script>');
            } catch (\Throwable $e) {
                // ignore
            }
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
            $menuItem->cName      = 'SideLeftFiltre';
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

