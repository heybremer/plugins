<?php

declare(strict_types=1);

namespace Plugin\CardProduct;

use JTL\Events\Dispatcher;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\State;
use JTL\Shop;

/**
 * Tablo DDL yalnızca plugin gerçekten veriye ihtiyaç duyduğunda (Startseite-Inject / Admin).
 */
final class CardProductDatabase
{
    private static bool $ensured = false;

    public static function ensureAll(): void
    {
        if (self::$ensured) {
            return;
        }

        self::repairBootstrapFlagIfNeeded();

        try {
            $db = Shop::Container()->getDB();

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_cardproduct (
                        kID INT AUTO_INCREMENT PRIMARY KEY,
                        kArtikel INT NULL,
                        cTitle VARCHAR(255) NOT NULL DEFAULT '',
                        cImage VARCHAR(500) NOT NULL DEFAULT '',
                        cImages LONGTEXT NULL,
                        cUrl VARCHAR(500) NOT NULL DEFAULT '',
                        nSort INT NOT NULL DEFAULT 100,
                        nActive TINYINT(1) NOT NULL DEFAULT 1,
                        dCreated DATETIME NOT NULL,
                        dUpdated DATETIME NOT NULL,
                        KEY idx_sort_active (nActive, nSort)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $col = $db->getSingleObject("SHOW COLUMNS FROM twomosi_cardproduct LIKE 'cImages'");
            if ($col === null) {
                $db->executeQuery("ALTER TABLE twomosi_cardproduct ADD COLUMN cImages LONGTEXT NULL AFTER cImage");
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_cardproduct_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator',
                    cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after',
                    cInjectSelector VARCHAR(255) NOT NULL DEFAULT '',
                    nRefreshDom INT NOT NULL DEFAULT 1,
                    nRefreshHour INT NOT NULL DEFAULT 3,
                    dUpdated DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $colPos = $db->getSingleObject("SHOW COLUMNS FROM twomosi_cardproduct_settings LIKE 'cInjectPos'");
            if ($colPos === null) {
                $db->executeQuery("ALTER TABLE twomosi_cardproduct_settings ADD COLUMN cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after' AFTER cInjectMode");
            }
            $colDom = $db->getSingleObject("SHOW COLUMNS FROM twomosi_cardproduct_settings LIKE 'nRefreshDom'");
            if ($colDom === null) {
                $db->executeQuery("ALTER TABLE twomosi_cardproduct_settings ADD COLUMN nRefreshDom INT NOT NULL DEFAULT 1 AFTER cInjectSelector");
            }
            $colHour = $db->getSingleObject("SHOW COLUMNS FROM twomosi_cardproduct_settings LIKE 'nRefreshHour'");
            if ($colHour === null) {
                $db->executeQuery("ALTER TABLE twomosi_cardproduct_settings ADD COLUMN nRefreshHour INT NOT NULL DEFAULT 3 AFTER nRefreshDom");
            }
            $exists = $db->getSingleObject("SELECT kID FROM twomosi_cardproduct_settings LIMIT 1");
            if ($exists === null) {
                $row = new \stdClass();
                $row->cInjectMode = 'after_configurator';
                $row->cInjectPos = 'after';
                $row->cInjectSelector = '';
                $row->nRefreshDom = 1;
                $row->nRefreshHour = 3;
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_cardproduct_settings', $row);
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_cardproduct_cache (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cCardsJson LONGTEXT NOT NULL,
                    dUpdated DATETIME NOT NULL,
                    UNIQUE KEY uq_lang (kSprache),
                    KEY ix_updated (dUpdated)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_cardproduct_meta (
                    cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                    cValue VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_cardproduct_cat_artikel (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kKategorie INT NOT NULL,
                    kArtikel INT NOT NULL,
                    nSort INT NOT NULL DEFAULT 100,
                    dCreated DATETIME NOT NULL,
                    UNIQUE KEY uq_cat_art (kKategorie, kArtikel),
                    KEY ix_kat (kKategorie),
                    KEY ix_art (kArtikel)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            self::$ensured = true;
        } catch (\Throwable $e) {
            // DB yoksa sessiz
        }
    }

    /**
     * JTL: Shop::bootstrap yalnızca tplugin.bBootstrap = 1 ise Plugin\...\Bootstrap::boot çağırır.
     */
    public static function repairBootstrapFlagIfNeeded(): void
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                'SELECT kPlugin, bBootstrap FROM tplugin WHERE cPluginID = :cid AND nStatus = :st LIMIT 1',
                ['cid' => 'CardProduct', 'st' => State::ACTIVATED]
            );
            if ($row === null || (int)($row->bBootstrap ?? 0) === 1) {
                return;
            }
            $kid = (int)($row->kPlugin ?? 0);
            if ($kid <= 0) {
                return;
            }
            $db->executeQueryPrepared(
                'UPDATE tplugin SET bBootstrap = 1 WHERE kPlugin = :kid',
                ['kid' => $kid]
            );
            Shop::Container()->getCache()->flush('plgnbtstrp');
            $cache = Shop::Container()->getCache();
            $loader = PluginHelper::getLoaderByPluginID($kid, $db, $cache);
            if (($bootstrapper = PluginHelper::bootstrap($kid, $loader)) !== null) {
                $bootstrapper->boot(Dispatcher::getInstance());
                $bootstrapper->loaded();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
