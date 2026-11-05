<?php
declare(strict_types=1);

namespace Plugin\CrossSell;

use JTL\Shop;

final class CrossSellRepository
{
    public const TABLE = 'twomosi_crosssell';

    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        try {
            Shop::Container()->getDB()->executeQueryPrepared(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    kID         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    kArtikel    INT UNSIGNED NOT NULL,
                    kRecArtikel INT UNSIGNED NOT NULL,
                    nSort       INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (kID),
                    UNIQUE KEY uq_pair (kArtikel, kRecArtikel),
                    KEY idx_src (kArtikel, nSort)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                []
            );
            self::$ensured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param array<int,mixed> $raw
     * @return array<int,int>
     */
    public static function ids(array $raw): array
    {
        $out = [];
        foreach ($raw as $v) {
            $id = (int)$v;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }

        return \array_values($out);
    }

    /**
     * Empfohlene Artikel-IDs fuer eine oder mehrere Quell-Artikel (Reihenfolge: nSort).
     *
     * @param array<int,int> $sourceIds
     * @param array<int,int> $excludeIds
     * @return array<int,int>
     */
    public static function getRecommendedIds(array $sourceIds, array $excludeIds, int $limit): array
    {
        $sourceIds = self::ids($sourceIds);
        if ($sourceIds === []) {
            return [];
        }
        self::ensure();
        $exclude = \array_flip(self::ids($excludeIds));
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT kRecArtikel FROM ' . self::TABLE
                . ' WHERE kArtikel IN (' . \implode(',', $sourceIds) . ') ORDER BY nSort ASC, kID ASC'
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r->kRecArtikel;
            if ($id > 0 && !isset($exclude[$id]) && !isset($out[$id])) {
                $out[$id] = $id;
                if (\count($out) >= $limit) {
                    break;
                }
            }
        }

        return \array_values($out);
    }

    /**
     * @return array<int,int>
     */
    public static function getForSource(int $source): array
    {
        self::ensure();
        $rows = Shop::Container()->getDB()->getObjects(
            'SELECT kRecArtikel FROM ' . self::TABLE . ' WHERE kArtikel = :s ORDER BY nSort ASC, kID ASC',
            ['s' => $source]
        );

        return \array_map(static fn($r): int => (int)$r->kRecArtikel, $rows);
    }

    /**
     * @param array<int,int> $recIds
     */
    public static function replaceForSource(int $source, array $recIds, bool $bidirectional): void
    {
        self::ensure();
        $db     = Shop::Container()->getDB();
        $recIds = \array_values(\array_filter(self::ids($recIds), static fn(int $i): bool => $i !== $source));
        $db->executeQueryPrepared('DELETE FROM ' . self::TABLE . ' WHERE kArtikel = :s', ['s' => $source]);
        foreach ($recIds as $pos => $rec) {
            $db->executeQueryPrepared(
                'INSERT INTO ' . self::TABLE . ' (kArtikel, kRecArtikel, nSort) VALUES (:a, :r, :n)
                 ON DUPLICATE KEY UPDATE nSort = VALUES(nSort)',
                ['a' => $source, 'r' => $rec, 'n' => $pos]
            );
            if ($bidirectional) {
                $db->executeQueryPrepared(
                    'INSERT IGNORE INTO ' . self::TABLE . ' (kArtikel, kRecArtikel, nSort) VALUES (:a, :r, 999)',
                    ['a' => $rec, 'r' => $source]
                );
            }
        }
    }

    public static function deleteSource(int $source): void
    {
        self::ensure();
        Shop::Container()->getDB()->executeQueryPrepared(
            'DELETE FROM ' . self::TABLE . ' WHERE kArtikel = :s',
            ['s' => $source]
        );
    }

    /**
     * @return array<int,object>
     */
    public static function listSources(): array
    {
        self::ensure();
        try {
            return Shop::Container()->getDB()->getObjects(
                'SELECT kArtikel, COUNT(*) AS nCount FROM ' . self::TABLE . ' GROUP BY kArtikel ORDER BY kArtikel DESC LIMIT 500'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array{id:int,name:string,artNr:string}>  indexiert nach ID
     */
    public static function articleInfo(array $ids): array
    {
        $ids = self::ids($ids);
        if ($ids === []) {
            return [];
        }
        $out = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT a.kArtikel, COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName, COALESCE(a.cArtNr, '') AS cArtNr
                   FROM tartikel a
              LEFT JOIN (SELECT kArtikel, MIN(cName) AS cName FROM tartikelsprache GROUP BY kArtikel) ts ON ts.kArtikel = a.kArtikel
                  WHERE a.kArtikel IN (" . \implode(',', $ids) . ')'
            );
            foreach ($rows as $r) {
                $out[(int)$r->kArtikel] = [
                    'id'    => (int)$r->kArtikel,
                    'name'  => (string)$r->cName,
                    'artNr' => (string)$r->cArtNr,
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }

    /**
     * @return array<int,array{id:int,name:string,artNr:string}>
     */
    public static function search(string $query, int $limit = 20): array
    {
        $q = \trim($query);
        if ($q === '') {
            return [];
        }
        $limit = \max(1, \min(50, $limit));
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT a.kArtikel, COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName, COALESCE(a.cArtNr, '') AS cArtNr
                   FROM tartikel a
              LEFT JOIN (SELECT kArtikel, MIN(cName) AS cName FROM tartikelsprache GROUP BY kArtikel) ts ON ts.kArtikel = a.kArtikel
                  WHERE a.kArtikel = :id
                     OR a.cArtNr LIKE :l1
                     OR a.cName LIKE :l2
                     OR ts.cName LIKE :l3
               ORDER BY (a.cArtNr = :e1) DESC, (a.kArtikel = :id2) DESC, a.kArtikel DESC
                  LIMIT {$limit}",
                [
                    'id'  => \ctype_digit($q) ? (int)$q : -1,
                    'id2' => \ctype_digit($q) ? (int)$q : -1,
                    'l1'  => '%' . $q . '%',
                    'l2'  => '%' . $q . '%',
                    'l3'  => '%' . $q . '%',
                    'e1'  => $q,
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r->kArtikel, 'name' => (string)$r->cName, 'artNr' => (string)$r->cArtNr];
        }

        return $out;
    }
}
