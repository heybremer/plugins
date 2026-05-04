<?php

declare(strict_types=1);

namespace Plugin\Top10List;

use JTL\Shop;

final class Top10ListDatabase
{
    private static bool $ensured = false;

    public static function ensureAll(): void
    {
        if (self::$ensured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_top10list_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cTitle VARCHAR(191) NOT NULL DEFAULT 'En cok satilan',
                    cSortMode VARCHAR(32) NOT NULL DEFAULT 'newest',
                    cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator',
                    cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after',
                    cInjectSelector VARCHAR(255) NOT NULL DEFAULT '',
                    nRefreshDom INT NOT NULL DEFAULT 1,
                    nRefreshHour INT NOT NULL DEFAULT 3,
                    cTabsJson LONGTEXT NULL,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $colSort = $db->getSingleObject("SHOW COLUMNS FROM twomosi_top10list_settings LIKE 'cSortMode'");
            if ($colSort === null) {
                $db->executeQuery("ALTER TABLE twomosi_top10list_settings ADD COLUMN cSortMode VARCHAR(32) NOT NULL DEFAULT 'newest' AFTER cTitle");
            }
            $colMode = $db->getSingleObject("SHOW COLUMNS FROM twomosi_top10list_settings LIKE 'cInjectMode'");
            if ($colMode === null) {
                $db->executeQuery("ALTER TABLE twomosi_top10list_settings ADD COLUMN cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator' AFTER cSortMode");
            }
            $colPos = $db->getSingleObject("SHOW COLUMNS FROM twomosi_top10list_settings LIKE 'cInjectPos'");
            if ($colPos === null) {
                $db->executeQuery("ALTER TABLE twomosi_top10list_settings ADD COLUMN cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after' AFTER cInjectMode");
            }
            $colSelector = $db->getSingleObject("SHOW COLUMNS FROM twomosi_top10list_settings LIKE 'cInjectSelector'");
            if ($colSelector === null) {
                $db->executeQuery("ALTER TABLE twomosi_top10list_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT '' AFTER cInjectPos");
            }
            $colDom = $db->getSingleObject("SHOW COLUMNS FROM twomosi_top10list_settings LIKE 'nRefreshDom'");
            if ($colDom === null) {
                $db->executeQuery("ALTER TABLE twomosi_top10list_settings ADD COLUMN nRefreshDom INT NOT NULL DEFAULT 1 AFTER cInjectSelector");
            }
            $colHour = $db->getSingleObject("SHOW COLUMNS FROM twomosi_top10list_settings LIKE 'nRefreshHour'");
            if ($colHour === null) {
                $db->executeQuery("ALTER TABLE twomosi_top10list_settings ADD COLUMN nRefreshHour INT NOT NULL DEFAULT 3 AFTER nRefreshDom");
            }
            $row = $db->getSingleObject("SELECT kID FROM twomosi_top10list_settings LIMIT 1");
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nActive = 1;
                $ins->cTitle = 'En çok satılan';
                $ins->cSortMode = 'newest';
                $ins->cInjectMode = 'after_configurator';
                $ins->cInjectPos = 'after';
                $ins->cInjectSelector = '';
                $ins->nRefreshDom = 1;
                $ins->nRefreshHour = 3;
                $ins->cTabsJson = \json_encode(self::defaultTabsArray(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                $ins->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_top10list_settings', $ins);
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_top10list_cache (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cSettingsHash VARCHAR(64) NOT NULL,
                    cPayloadJson LONGTEXT NOT NULL,
                    dUpdated DATETIME NOT NULL,
                    UNIQUE KEY uq_lang (kSprache),
                    KEY ix_updated (dUpdated)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_top10list_meta (
                    cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                    cValue VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            self::$ensured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function defaultTabsArray(): array
    {
        return [
            ['key' => 'pkw', 'label' => 'PKW', 'keyword' => 'pkw', 'articleIds' => []],
            ['key' => 'wohnmobil', 'label' => 'Wohnmobil', 'keyword' => 'wohnmobil', 'articleIds' => []],
            ['key' => 'andere', 'label' => 'Andere', 'keyword' => '', 'articleIds' => []],
        ];
    }
}
