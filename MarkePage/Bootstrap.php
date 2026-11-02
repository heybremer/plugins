<?php

declare(strict_types=1);

namespace Plugin\MarkePage;

require_once __DIR__ . '/src/MarkePageService.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    private static bool $linksEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();
        $this->ensureLinks();
    }

    /**
     * Seiten-Links neu anlegen, falls sie z.B. nach einem Plugin-Update fehlen.
     */
    private function ensureLinks(): void
    {
        if (self::$linksEnsured) {
            return;
        }
        self::$linksEnsured = true;
        try {
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }
            $svc = new MarkePageService(Shop::Container()->getDB(), $pluginID);
            $svc->ensureTables();
            $svc->ensureLinks();
        } catch (\Throwable $e) {
            // Frontend nie blockieren
        }
    }

    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }
        try {
            $db       = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }
            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin AND cDateiname = :cDateiname
                  LIMIT 1',
                ['kPlugin' => $pluginID, 'cDateiname' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu             = new \stdClass();
                $menu->kPlugin    = $pluginID;
                $menu->cName      = 'Seiten';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort      = 10;
                $menu->nConf      = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
