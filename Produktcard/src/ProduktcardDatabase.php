<?php

declare(strict_types=1);

namespace Plugin\Produktcard;

use JTL\Shop;

/**
 * Tabellen inkl. Tabs (Wohnmobil / PKW) und kTabID auf Karten.
 */
final class ProduktcardDatabase
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
                "CREATE TABLE IF NOT EXISTS twomosi_produktcard_tab (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cLabel VARCHAR(128) NOT NULL DEFAULT '',
                    cAllUrl VARCHAR(1000) NOT NULL DEFAULT '',
                    nSort INT NOT NULL DEFAULT 100,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    dCreated DATETIME NOT NULL,
                    dUpdated DATETIME NOT NULL,
                    KEY idx_sort_active (nActive, nSort)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $colAllUrl = $db->getSingleObject("SHOW COLUMNS FROM twomosi_produktcard_tab LIKE 'cAllUrl'");
            if ($colAllUrl === null) {
                $db->executeQuery(
                    "ALTER TABLE twomosi_produktcard_tab ADD COLUMN cAllUrl VARCHAR(1000) NOT NULL DEFAULT '' AFTER cLabel"
                );
            }

            $tabCnt = $db->getSingleObject('SELECT COUNT(*) AS c FROM twomosi_produktcard_tab');
            if ($tabCnt !== null && (int)($tabCnt->c ?? 0) === 0) {
                $t1 = new \stdClass();
                $t1->cLabel = 'Wohnmobil';
                $t1->nSort = 10;
                $t1->nActive = 1;
                $t1->dCreated = date('Y-m-d H:i:s');
                $t1->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_produktcard_tab', $t1);
                $t2 = new \stdClass();
                $t2->cLabel = 'PKW';
                $t2->nSort = 20;
                $t2->nActive = 1;
                $t2->dCreated = date('Y-m-d H:i:s');
                $t2->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_produktcard_tab', $t2);
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_produktcard (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kTabID INT NOT NULL DEFAULT 1,
                    cTitle VARCHAR(255) NOT NULL DEFAULT '',
                    cBildUrl VARCHAR(1000) NOT NULL DEFAULT '',
                    cLinkUrl VARCHAR(1000) NOT NULL DEFAULT '',
                    nSort INT NOT NULL DEFAULT 100,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    dCreated DATETIME NOT NULL,
                    dUpdated DATETIME NOT NULL,
                    KEY idx_sort_active (nActive, nSort),
                    KEY idx_pcard_tab (kTabID)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $colTab = $db->getSingleObject("SHOW COLUMNS FROM twomosi_produktcard LIKE 'kTabID'");
            if ($colTab === null) {
                $db->executeQuery('ALTER TABLE twomosi_produktcard ADD COLUMN kTabID INT NULL AFTER kID');
                $first = $db->getSingleObject(
                    'SELECT kID FROM twomosi_produktcard_tab ORDER BY nSort ASC, kID ASC LIMIT 1'
                );
                $fid = $first !== null ? (int)($first->kID ?? 1) : 1;
                $db->executeQueryPrepared(
                    'UPDATE twomosi_produktcard SET kTabID = :fid WHERE kTabID IS NULL',
                    ['fid' => $fid]
                );
                $db->executeQuery('ALTER TABLE twomosi_produktcard MODIFY kTabID INT NOT NULL DEFAULT 1');
                try {
                    $db->executeQuery('ALTER TABLE twomosi_produktcard ADD INDEX idx_pcard_tab (kTabID)');
                } catch (\Throwable $e) {
                    // Index existiert ggf. schon
                }
            }

            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_produktcard_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cHeadline VARCHAR(255) NOT NULL DEFAULT 'Unsere Modelle',
                    nGridColumns TINYINT NOT NULL DEFAULT 4,
                    nImagePadding TINYINT NOT NULL DEFAULT 8,
                    nImageBgSizePercent TINYINT NOT NULL DEFAULT 100,
                    cAspectRatio VARCHAR(16) NOT NULL DEFAULT '1/1',
                    dUpdated DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $colBg = $db->getSingleObject("SHOW COLUMNS FROM twomosi_produktcard_settings LIKE 'nImageBgSizePercent'");
            if ($colBg === null) {
                $db->executeQuery(
                    "ALTER TABLE twomosi_produktcard_settings ADD COLUMN nImageBgSizePercent TINYINT NOT NULL DEFAULT 100 AFTER nImagePadding"
                );
            }
            $setRow = $db->getSingleObject('SELECT kID FROM twomosi_produktcard_settings LIMIT 1');
            if ($setRow === null) {
                $ins = new \stdClass();
                $ins->cHeadline = 'Unsere Modelle';
                $ins->nGridColumns = 4;
                $ins->nImagePadding = 8;
                $ins->nImageBgSizePercent = 100;
                $ins->cAspectRatio = '1/1';
                $ins->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_produktcard_settings', $ins);
            }

            self::$ensured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
