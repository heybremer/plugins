<?php

declare(strict_types=1);

namespace Plugin\BlackFriday;

use JTL\DB\DbInterface;
use JTL\DB\ReturnType;
use JTL\Shop;

/**
 * Black-Friday-Kategorierabatte über die JTL-eigene Tabelle tartikelkategorierabatt.
 * JTL berechnet daraus Preis, Streichpreis und Warenkorb selbst. Die Wawi-Synchronisation
 * schreibt die Tabelle je Artikel neu, daher wird regelmäßig nachgezogen (tick()).
 */
final class DiscountService
{
    public const T_SETTINGS = 'twomosi_bf_settings';
    public const T_RULE     = 'twomosi_bf_rule';
    public const T_TARGET   = 'twomosi_bf_target';
    public const T_APPLIED  = 'twomosi_bf_applied';
    public const T_META     = 'twomosi_bf_meta';

    private const TICK_SECONDS = 600;

    private static bool $schemaEnsured = false;

    public function __construct(private readonly DbInterface $db)
    {
    }

    public function ensureSchema(): void
    {
        $flag = \sys_get_temp_dir() . '/bf_schema_v2_' . \md5(__DIR__ . \DB_NAME);
        if (self::$schemaEnsured || \is_file($flag)) {
            self::$schemaEnsured = true;
            return;
        }
        $this->db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::T_SETTINGS . ' (
                kID INT AUTO_INCREMENT PRIMARY KEY,
                nActive TINYINT(1) NOT NULL DEFAULT 0,
                dStart DATETIME NULL DEFAULT NULL,
                dEnd DATETIME NULL DEFAULT NULL,
                nHeaderBar TINYINT(1) NOT NULL DEFAULT 1,
                nHomeBlock TINYINT(1) NOT NULL DEFAULT 1,
                nProductBlock TINYINT(1) NOT NULL DEFAULT 1,
                nTheme TINYINT(1) NOT NULL DEFAULT 1,
                cTopText VARCHAR(255) NOT NULL DEFAULT "",
                cHomeTitle VARCHAR(190) NOT NULL DEFAULT "",
                cHomeSub VARCHAR(512) NOT NULL DEFAULT "",
                cButtonLabel VARCHAR(120) NOT NULL DEFAULT "",
                cButtonUrl VARCHAR(512) NOT NULL DEFAULT "",
                cInjectSelector VARCHAR(255) NOT NULL DEFAULT "",
                dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::T_RULE . ' (
                kRule INT AUTO_INCREMENT PRIMARY KEY,
                kKategorie INT UNSIGNED NOT NULL,
                fRabatt DOUBLE NOT NULL DEFAULT 0,
                nInclSub TINYINT(1) NOT NULL DEFAULT 1,
                nActive TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uq_kat (kKategorie)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        foreach ([self::T_TARGET, self::T_APPLIED] as $table) {
            $this->db->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                    kArtikel INT UNSIGNED NOT NULL,
                    kKundengruppe INT UNSIGNED NOT NULL,
                    kKategorie INT UNSIGNED NOT NULL,
                    fRabatt DOUBLE NOT NULL,
                    PRIMARY KEY (kArtikel, kKundengruppe)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
        $this->db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::T_META . ' (
                cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                cValue VARCHAR(255) NOT NULL DEFAULT ""
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $col = $this->db->getSingleObject("SHOW COLUMNS FROM " . self::T_META . " LIKE 'cValue'");
        if ($col !== null && \stripos((string)($col->Type ?? ''), 'text') === false) {
            $this->db->executeQuery('ALTER TABLE ' . self::T_META . ' MODIFY cValue MEDIUMTEXT NULL');
        }
        if ($this->db->getSingleObject('SELECT kID FROM ' . self::T_SETTINGS . ' LIMIT 1') === null) {
            $ins                  = new \stdClass();
            $ins->kID             = 1;
            $ins->nActive         = 0;
            $ins->nHeaderBar      = 1;
            $ins->nHomeBlock      = 1;
            $ins->nProductBlock   = 1;
            $ins->nTheme          = 1;
            $ins->cTopText        = 'Bis zu {max}% Rabatt auf ausgewählte Kategorien';
            $ins->cHomeTitle      = 'BLACK FRIDAY';
            $ins->cHomeSub        = 'Unsere stärksten Preise des Jahres – nur für kurze Zeit.';
            $ins->cButtonLabel    = 'Jetzt Deals sichern';
            $ins->cButtonUrl      = '';
            $ins->cInjectSelector = '#womosi-fk-konfigurator-anchor';
            $ins->dUpdated        = \date('Y-m-d H:i:s');
            try {
                $this->db->insert(self::T_SETTINGS, $ins);
            } catch (\Throwable $e) {
            }
        }
        @\touch($flag);
        self::$schemaEnsured = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $row = $this->db->getSingleObject('SELECT * FROM ' . self::T_SETTINGS . ' ORDER BY kID ASC LIMIT 1');
        if ($row !== null && !\property_exists($row, 'nCouponOnDeals')) {
            try {
                $this->db->executeQuery(
                    'ALTER TABLE ' . self::T_SETTINGS . ' ADD COLUMN nCouponOnDeals TINYINT(1) NOT NULL DEFAULT 0'
                );
            } catch (\Throwable $e) {
            }
        }

        return [
            'active'         => (int)($row->nActive ?? 0),
            'start'          => (string)($row->dStart ?? ''),
            'end'            => (string)($row->dEnd ?? ''),
            'headerBar'      => (int)($row->nHeaderBar ?? 1),
            'homeBlock'      => (int)($row->nHomeBlock ?? 1),
            'productBlock'   => (int)($row->nProductBlock ?? 1),
            'theme'          => (int)($row->nTheme ?? 1),
            'couponOnDeals'  => (int)($row->nCouponOnDeals ?? 0),
            'topText'        => (string)($row->cTopText ?? ''),
            'homeTitle'      => (string)($row->cHomeTitle ?? ''),
            'homeSub'        => (string)($row->cHomeSub ?? ''),
            'buttonLabel'    => (string)($row->cButtonLabel ?? ''),
            'buttonUrl'      => (string)($row->cButtonUrl ?? ''),
            'injectSelector' => (string)($row->cInjectSelector ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $s
     */
    public static function isRunning(array $s, ?int $now = null): bool
    {
        if ((int)($s['active'] ?? 0) !== 1) {
            return false;
        }
        $now   = $now ?? \time();
        $start = (string)($s['start'] ?? '') !== '' ? \strtotime((string)$s['start']) : false;
        $end   = (string)($s['end'] ?? '') !== '' ? \strtotime((string)$s['end']) : false;
        if ($start !== false && $now < $start) {
            return false;
        }

        return !($end !== false && $now >= $end);
    }

    /**
     * @return list<object{kRule:int,kKategorie:int,fRabatt:float,nInclSub:int,nActive:int}>
     */
    public function getRules(): array
    {
        return $this->db->getObjects('SELECT * FROM ' . self::T_RULE . ' ORDER BY fRabatt DESC, kKategorie ASC');
    }

    public function maxDiscount(): float
    {
        return (float)$this->db->getSingleObject(
            'SELECT COALESCE(MAX(fRabatt), 0) AS m FROM ' . self::T_RULE . ' WHERE nActive = 1'
        )->m;
    }

    /**
     * Kategoriepfade von der obersten Ebene bis zur Kategorie selbst.
     *
     * @param int[] $ids
     * @return array<int, list<array{id:int, name:string}>>
     */
    public function categoryPaths(array $ids, int $langID): array
    {
        $ids = \array_values(\array_filter(\array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $rows = $this->db->getObjects(
            'SELECT c.kKategorie AS kCat, p.kKategorie, COALESCE(NULLIF(ps.cName, ""), p.cName) AS cName
               FROM tkategorie c
         INNER JOIN tkategorie p ON p.lft <= c.lft AND p.rght >= c.rght
          LEFT JOIN tkategoriesprache ps ON ps.kKategorie = p.kKategorie AND ps.kSprache = :l
              WHERE c.kKategorie IN (' . \implode(',', $ids) . ')
           ORDER BY c.kKategorie, p.lft',
            ['l' => $langID]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r->kCat][] = ['id' => (int)$r->kKategorie, 'name' => (string)$r->cName];
        }

        return $out;
    }

    /**
     * Rabatt je Konfigurator-Serie, getrennt nach Hauptkategorie (pkw / wm), z. B. {"pkw":{"H":20},"wm":{"C":50}}.
     *
     * @return array{pkw: array<string, float>, wm: array<string, float>, max: float}
     */
    public function buildSeriesMap(): array
    {
        $rows = $this->db->getObjects(
            'SELECT tp.kKategorie AS topID, tp.cName AS topName, c.cName, MAX(r.fRabatt) AS pct
               FROM tkategorie c
         INNER JOIN tkategorie tp ON tp.kOberKategorie = 0 AND c.lft BETWEEN tp.lft AND tp.rght
         INNER JOIN tkategorie rc ON rc.lft <= c.lft AND rc.rght >= c.rght
         INNER JOIN ' . self::T_RULE . ' r
                 ON r.kKategorie = rc.kKategorie AND r.nActive = 1 AND r.fRabatt > 0
                AND (r.nInclSub = 1 OR rc.kKategorie = c.kKategorie)
              WHERE c.cName LIKE "%serie%"
           GROUP BY tp.kKategorie, tp.cName, c.cName'
        );
        $map = ['pkw' => [], 'wm' => [], 'max' => 0.0];
        foreach ($rows as $r) {
            $top = \mb_strtolower((string)$r->topName);
            $seg = (int)$r->topID === 19 || \str_contains($top, 'pkw') ? 'pkw'
                : ((int)$r->topID === 24 || \str_contains($top, 'wohnmobil') ? 'wm' : '');
            if ($seg === '') {
                continue;
            }
            $pct = (float)$r->pct;
            foreach (self::seriesKeys((string)$r->cName) as $key) {
                $map[$seg][$key] = \max($map[$seg][$key] ?? 0.0, $pct);
            }
            $map['max'] = \max($map['max'], $pct);
        }

        return $map;
    }

    /**
     * "H-Serie" → [H], "800er-Serie" → [800ER, 800], "Serie KT3" → [KT3]
     *
     * @return list<string>
     */
    public static function seriesKeys(string $name): array
    {
        $k = \mb_strtoupper(\trim($name));
        $k = (string)\preg_replace('/[\s\-]*SERIE[\s\-]*/u', '', $k);
        $k = (string)\preg_replace('/\s*\(\d+\)$/', '', $k);
        $k = (string)\preg_replace('/[\s\-]+/', '', $k);
        if ($k === '') {
            return [];
        }
        $keys = [$k];
        if (\preg_match('/^(\d+)ER$/', $k, $m)) {
            $keys[] = $m[1];
        }

        return $keys;
    }

    public static function previewKey(): string
    {
        return \substr(\hash('sha256', 'bf-preview|' . \DB_NAME . '|' . \DB_PASS), 0, 24);
    }

    public function discountForProduct(int $productID, int $customerGroupID, bool $planned = false): float
    {
        $row = $this->db->getSingleObject(
            'SELECT fRabatt FROM ' . ($planned ? self::T_TARGET : self::T_APPLIED)
            . ' WHERE kArtikel = :a AND kKundengruppe = :g',
            ['a' => $productID, 'g' => $customerGroupID]
        );

        return $row !== null ? (float)$row->fRabatt : 0.0;
    }

    /**
     * Ziel neu berechnen und vollständig anwenden (Speichern im Dashboard, Start/Ende der Aktion).
     *
     * @return array{target:int, articles:int}
     */
    public function applyAll(): array
    {
        $this->ensureSchema();
        $running = self::isRunning($this->getSettings());
        $this->buildTarget();

        $this->db->executeQuery('DROP TEMPORARY TABLE IF EXISTS tmp_bf_aff');
        $this->db->executeQuery(
            'CREATE TEMPORARY TABLE tmp_bf_aff (kArtikel INT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=MEMORY'
        );
        $this->db->executeQuery('INSERT IGNORE INTO tmp_bf_aff SELECT DISTINCT kArtikel FROM ' . self::T_APPLIED);
        if ($running) {
            $this->db->executeQuery('INSERT IGNORE INTO tmp_bf_aff SELECT DISTINCT kArtikel FROM ' . self::T_TARGET);
        }

        $this->db->executeQuery(
            'DELETE r FROM tartikelkategorierabatt r INNER JOIN tmp_bf_aff a ON a.kArtikel = r.kArtikel'
        );
        $this->restoreNative();
        if ($running) {
            $this->upsertTarget(false);
        }

        $this->db->executeQuery('DELETE FROM ' . self::T_APPLIED);
        if ($running) {
            $this->db->executeQuery('INSERT INTO ' . self::T_APPLIED . ' SELECT * FROM ' . self::T_TARGET);
        }
        $ids = $this->db->getInts('SELECT kArtikel FROM tmp_bf_aff', 'kArtikel');
        $this->db->executeQuery('DROP TEMPORARY TABLE IF EXISTS tmp_bf_aff');
        $this->flushProducts($ids);
        $this->setMeta('state', $running ? 'on' : 'off');
        $this->setMeta('rules_hash', $this->rulesHash());
        $this->setMeta('last_apply', \date('Y-m-d H:i:s'));
        $this->setMeta('series_json', (string)\json_encode($this->buildSeriesMap()));

        return [
            'target'   => $running ? (int)$this->db->getSingleObject('SELECT COUNT(*) AS c FROM ' . self::T_APPLIED)->c : 0,
            'articles' => \count($ids),
        ];
    }

    /**
     * Pseudo-Cron: höchstens alle 10 Minuten. Wechsel des Aktionszeitraums oder neue Artikel
     * in den Kategorien → applyAll(); sonst nur von der Wawi überschriebene Zeilen reparieren.
     */
    public function tick(): void
    {
        $this->ensureSchema();
        $now  = \time();
        $lock = (int)$this->db->executeQueryPrepared(
            'INSERT INTO ' . self::T_META . ' (cKey, cValue) VALUES ("tick", :now)
             ON DUPLICATE KEY UPDATE cValue = IF(CAST(cValue AS UNSIGNED) < :due, :now, cValue)',
            ['now' => (string)$now, 'due' => $now - self::TICK_SECONDS],
            ReturnType::AFFECTED_ROWS
        );
        if ($lock < 1) {
            return;
        }
        try {
            $this->db->executeQuery('SET SESSION max_statement_time = 0');
        } catch (\Throwable $e) {
        }

        $running = self::isRunning($this->getSettings(), $now);
        $applied = (int)$this->db->getSingleObject('SELECT COUNT(*) AS c FROM ' . self::T_APPLIED)->c;
        if (!$running) {
            if ($applied > 0 || $this->getMeta('state') === 'on') {
                $this->applyAll();
            }
            return;
        }
        if ($this->getMeta('state') !== 'on' || $this->getMeta('rules_hash') !== $this->rulesHash()) {
            $this->applyAll();
            return;
        }
        $this->buildTarget();
        $diff = (int)$this->db->getSingleObject(
            'SELECT COUNT(*) AS c FROM ' . self::T_TARGET . ' t
             LEFT JOIN ' . self::T_APPLIED . ' a ON a.kArtikel = t.kArtikel AND a.kKundengruppe = t.kKundengruppe
             WHERE a.kArtikel IS NULL OR a.fRabatt <> t.fRabatt'
        )->c;
        if ($diff > 0 || (int)$this->db->getSingleObject('SELECT COUNT(*) AS c FROM ' . self::T_TARGET)->c !== $applied) {
            $this->applyAll();
            return;
        }
        $this->upsertTarget(true);
    }

    private function buildTarget(): void
    {
        $this->db->executeQuery('DELETE FROM ' . self::T_TARGET);
        $this->db->executeQuery(
            'INSERT INTO ' . self::T_TARGET . ' (kArtikel, kKundengruppe, kKategorie, fRabatt)
             SELECT ka.kArtikel, kg.kKundengruppe, r.kKategorie, r.fRabatt
               FROM ' . self::T_RULE . ' r
         INNER JOIN tkategorie rc ON rc.kKategorie = r.kKategorie
         INNER JOIN tkategorie c
                 ON c.kKategorie = r.kKategorie
                 OR (r.nInclSub = 1 AND c.lft > rc.lft AND c.rght < rc.rght)
         INNER JOIN tkategorieartikel ka ON ka.kKategorie = c.kKategorie
         CROSS JOIN tkundengruppe kg
              WHERE r.nActive = 1 AND r.fRabatt > 0
             ON DUPLICATE KEY UPDATE
                kKategorie = IF(' . self::T_TARGET . '.fRabatt < VALUES(fRabatt), VALUES(kKategorie), ' . self::T_TARGET . '.kKategorie),
                fRabatt    = GREATEST(' . self::T_TARGET . '.fRabatt, VALUES(fRabatt))'
        );
    }

    /**
     * Wawi-Kategorierabatte (tkategoriekundengruppe) für die betroffenen Artikel wiederherstellen,
     * analog zu dbeS\Sync\Products::addCategoryDiscounts().
     */
    private function restoreNative(): void
    {
        $this->db->executeQuery(
            'INSERT INTO tartikelkategorierabatt (kArtikel, kKundengruppe, kKategorie, fRabatt)
             SELECT ka.kArtikel, kkg.kKundengruppe, kkg.kKategorie, kkg.fRabatt
               FROM tmp_bf_aff a
         INNER JOIN tkategorieartikel ka ON ka.kArtikel = a.kArtikel
         INNER JOIN tkategoriekundengruppe kkg ON kkg.kKategorie = ka.kKategorie
          LEFT JOIN tkategoriesichtbarkeit ks
                 ON ks.kKategorie = kkg.kKategorie AND ks.kKundengruppe = kkg.kKundengruppe
              WHERE ks.kKategorie IS NULL AND kkg.fRabatt > 0
             ON DUPLICATE KEY UPDATE
                kKategorie = IF(tartikelkategorierabatt.fRabatt < VALUES(fRabatt), VALUES(kKategorie), tartikelkategorierabatt.kKategorie),
                fRabatt    = GREATEST(tartikelkategorierabatt.fRabatt, VALUES(fRabatt))'
        );
    }

    private function upsertTarget(bool $onlyMissing): void
    {
        $where = '';
        if ($onlyMissing) {
            $ids = $this->db->getInts(
                'SELECT DISTINCT t.kArtikel FROM ' . self::T_TARGET . ' t
                   LEFT JOIN tartikelkategorierabatt r ON r.kArtikel = t.kArtikel AND r.kKundengruppe = t.kKundengruppe
                  WHERE r.kArtikel IS NULL OR r.fRabatt < t.fRabatt',
                'kArtikel'
            );
            if ($ids === []) {
                return;
            }
            $where = ' WHERE t.kArtikel IN (' . \implode(',', $ids) . ')';
        }
        $this->db->executeQuery(
            'INSERT INTO tartikelkategorierabatt (kArtikel, kKundengruppe, kKategorie, fRabatt)
             SELECT t.kArtikel, t.kKundengruppe, t.kKategorie, t.fRabatt FROM ' . self::T_TARGET . ' t' . $where . '
             ON DUPLICATE KEY UPDATE
                kKategorie = IF(tartikelkategorierabatt.fRabatt < VALUES(fRabatt), VALUES(kKategorie), tartikelkategorierabatt.kKategorie),
                fRabatt    = GREATEST(tartikelkategorierabatt.fRabatt, VALUES(fRabatt))'
        );
        if ($onlyMissing && isset($ids)) {
            $this->flushProducts($ids);
        }
    }

    /**
     * @param int[] $ids
     */
    private function flushProducts(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        try {
            $cache = Shop::Container()->getCache();
            if (\count($ids) > 3000) {
                $cache->flushTags([\CACHING_GROUP_ARTICLE, \CACHING_GROUP_CATEGORY]);
                return;
            }
            foreach (\array_chunk($ids, 500) as $chunk) {
                $cache->flushTags(\array_map(static fn(int $id): string => \CACHING_GROUP_ARTICLE . '_' . $id, $chunk));
            }
            $cache->flushTags([\CACHING_GROUP_CATEGORY]);
        } catch (\Throwable $e) {
        }
    }

    private function rulesHash(): string
    {
        $rows = $this->db->getObjects(
            'SELECT kKategorie, fRabatt, nInclSub, nActive FROM ' . self::T_RULE . ' ORDER BY kKategorie'
        );

        return \md5((string)\json_encode($rows));
    }

    public function getMeta(string $key): string
    {
        $row = $this->db->getSingleObject('SELECT cValue FROM ' . self::T_META . ' WHERE cKey = :k', ['k' => $key]);

        return $row !== null ? (string)$row->cValue : '';
    }

    public function setMeta(string $key, string $value): void
    {
        $this->db->executeQueryPrepared(
            'INSERT INTO ' . self::T_META . ' (cKey, cValue) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
            ['k' => $key, 'v' => $value]
        );
    }
}
