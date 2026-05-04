<?php
declare(strict_types=1);

namespace Plugin\SideLeftFiltre\src;

use JTL\Shop;

final class SideLeftFiltreDatabase
{
    private static bool $ensured = false;

    public static function ensureAll(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_sideleftfiltre_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cOrderCsv TEXT NOT NULL,
                    cOpenCsv TEXT NULL,
                    cCloseCsv TEXT NULL,
                    dUpdated DATETIME NOT NULL,
                    KEY ix_slf_updated (dUpdated)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            // Backward-compatible column additions (silently ignored if column already exists)
            try { $db->executeQuery("ALTER TABLE twomosi_sideleftfiltre_settings ADD COLUMN cOpenCsv TEXT NULL"); } catch (\Throwable $e) {}
            try { $db->executeQuery("ALTER TABLE twomosi_sideleftfiltre_settings ADD COLUMN cCloseCsv TEXT NULL"); } catch (\Throwable $e) {}
            // Ensure existing rows have usable values (avoid NULL surprises in older rows)
            try { $db->executeQuery("UPDATE twomosi_sideleftfiltre_settings SET cOpenCsv = '' WHERE cOpenCsv IS NULL"); } catch (\Throwable $e) {}
            try { $db->executeQuery("UPDATE twomosi_sideleftfiltre_settings SET cCloseCsv = '' WHERE cCloseCsv IS NULL"); } catch (\Throwable $e) {}

            $row = $db->getSingleObject(
                'SELECT kID FROM twomosi_sideleftfiltre_settings ORDER BY dUpdated DESC, kID DESC LIMIT 1'
            );
            if ($row === null) {
                $db->executeQueryPrepared(
                    'INSERT INTO twomosi_sideleftfiltre_settings (nActive, cOrderCsv, cOpenCsv, cCloseCsv, dUpdated)
                     VALUES (1, :csv, :open, :close, NOW())',
                    [
                        'csv' => "fahrzeug-model\n",
                        'open' => '',
                        'close' => '',
                    ]
                );
            }
        } catch (\Throwable $e) {
            // do not hard-fail the shop
        }
    }

    /**
     * @return array{active:bool,order:array<int,string>,open:array<int,string>,close:array<int,string>}
     */
    public static function getConfig(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                "SELECT nActive, cOrderCsv, cOpenCsv, cCloseCsv
                   FROM twomosi_sideleftfiltre_settings
               ORDER BY dUpdated DESC, kID DESC
                  LIMIT 1"
            );
            $active = $row !== null ? ((int)($row->nActive ?? 1) === 1) : true;
            $csv = $row !== null ? (string)($row->cOrderCsv ?? '') : '';
            $openCsv = $row !== null ? (string)($row->cOpenCsv ?? '') : '';
            $closeCsv = $row !== null ? (string)($row->cCloseCsv ?? '') : '';
            $order = self::parseOrder($csv);
            $open = self::parseOrder($openCsv);
            $close = self::parseOrder($closeCsv);

            return ['active' => $active, 'order' => $order, 'open' => $open, 'close' => $close];
        } catch (\Throwable $e) {
            return ['active' => true, 'order' => [], 'open' => [], 'close' => []];
        }
    }

    /**
     * @return array<int,string>
     */
    public static function parseOrder(string $csv): array
    {
        $lines = \preg_split('/\r\n|\r|\n/', (string)$csv);
        if (!\is_array($lines)) {
            return [];
        }
        $out = [];
        foreach ($lines as $line) {
            $line = \trim((string)$line);
            if ($line === '') {
                continue;
            }
            // also allow comma-separated in a single line
            $parts = \preg_split('/\s*,\s*/', $line);
            if (!\is_array($parts)) {
                $parts = [$line];
            }
            foreach ($parts as $p) {
                $id = \trim((string)$p);
                if ($id === '') {
                    continue;
                }
                $out[] = $id;
            }
        }

        // unique, keep order
        $uniq = [];
        $seen = [];
        foreach ($out as $id) {
            $key = \strtolower($id);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $uniq[] = $id;
        }
        return $uniq;
    }
}

