<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Service;

use JTL\DB\DbInterface;
use Plugin\FilterKonfiguratorSearch\Query\PkwSqlFragments;
use Plugin\FilterKonfiguratorSearch\Repository\FilterKonfiguratorRepository;

/**
 * Service to manage pre-calculated snapshots for high-speed filter lookup.
 * 
 * Snapshot refresh is split into independent steps to avoid HTTP timeout
 * errors (e.g. Cloudflare 524) when called from the admin dashboard via AJAX.
 */
class SnapshotService
{
    public function __construct(
        private readonly DbInterface $db
    ) {}

    /**
     * Refreshes all snapshot tables for both Wohnmobil and PKW segments.
     * NOTE: For admin UI use, prefer calling each step separately via AJAX.
     */
    public function refreshAll(int $langID): void
    {
        $this->refreshWohnmobilSnapshots($langID);
        $this->refreshPkwHerstellerModell($langID);
        $this->refreshPkwBaujahr($langID);
        $this->refreshPkwSerie($langID);
        
        $this->db->executeQueryPrepared(
            'INSERT INTO twomosi_fk_opt_meta (dRefreshed, kSprache, cNote) 
             VALUES (NOW(), :lid, :note)
             ON DUPLICATE KEY UPDATE dRefreshed = NOW(), cNote = VALUES(cNote)',
            ['lid' => $langID, 'note' => 'Automatic Refresh']
        );
    }

    /**
     * Entfernt alte Log-Zeilen, damit die Tabellen nicht unbegrenzt wachsen.
     * Wird vom wöchentlichen Cron aufgerufen.
     *
     * @param int $searchLogDays Aufbewahrung für twomosi_fk_search_log (Tage).
     * @param int $selectionDays Aufbewahrung für twomosi_fk_selection (Tage).
     */
    public function pruneLogs(int $searchLogDays = 30, int $selectionDays = 90): void
    {
        // Werte sind strikt int → kein Injection-Risiko bei Inline-Verwendung.
        $searchLogDays = \max(1, $searchLogDays);
        $selectionDays = \max(1, $selectionDays);

        try {
            $this->db->executeQuery(
                'DELETE FROM twomosi_fk_search_log WHERE dCreated < (NOW() - INTERVAL ' . $searchLogDays . ' DAY)'
            );
        } catch (\Throwable $e) {
            // Tabelle fehlt evtl. noch — ignorieren.
        }
        try {
            $this->db->executeQuery(
                'DELETE FROM twomosi_fk_selection WHERE dCreated < (NOW() - INTERVAL ' . $selectionDays . ' DAY)'
            );
        } catch (\Throwable $e) {
            // ignorieren
        }
    }

    /**
     * Compatibility wrapper — calls the 3 PKW steps sequentially.
     */
    public function refreshPkwSnapshots(int $lid): void
    {
        $this->refreshPkwHerstellerModell($lid);
        $this->refreshPkwBaujahr($lid);
        $this->refreshPkwSerie($lid);
    }

    // ---------------------------------------------------------------
    // Wohnmobil Snapshots (single step — typically fast enough)
    // ---------------------------------------------------------------

    public function refreshWohnmobilSnapshots(int $lid): void
    {
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_hersteller WHERE kSprache = :lid', ['lid' => $lid]);
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_basis WHERE kSprache = :lid', ['lid' => $lid]);
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_modell WHERE kSprache = :lid', ['lid' => $lid]);

        // 1. Hersteller by Typ
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_hersteller (kSprache, cTyp, cHersteller)
             SELECT DISTINCT :lid, TRIM(tmws_typ.cWert), TRIM(tmws_her.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 24
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_typ ON tmws_typ.kMerkmalWert = am_typ.kMerkmalWert
                                                AND tmws_typ.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = am_typ.kMerkmal
                                          AND ms_typ.kSprache = :lid
                                          AND ms_typ.cName = 'Wohnmobil-Aufbauart'
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
              WHERE a.nIstVater = 0",
            ['lid' => $lid]
        );

        // 2. Basis by Typ+Hersteller
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_basis (kSprache, cTyp, cHersteller, cBasis)
             SELECT DISTINCT :lid, TRIM(tmws_typ.cWert), TRIM(tmws_her.cWert), TRIM(tmws_basis.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 24
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_typ ON tmws_typ.kMerkmalWert = am_typ.kMerkmalWert
                                                AND tmws_typ.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = am_typ.kMerkmal
                                          AND ms_typ.kSprache = :lid
                                          AND ms_typ.cName = 'Wohnmobil-Aufbauart'
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
         INNER JOIN tartikelmerkmal am_basis ON am_basis.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_basis ON tmws_basis.kMerkmalWert = am_basis.kMerkmalWert
                                                   AND tmws_basis.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_basis ON ms_basis.kMerkmal = am_basis.kMerkmal
                                             AND ms_basis.kSprache = :lid
                                             AND ms_basis.cName = 'Wohnmobil-Fahrgestell'
              WHERE a.nIstVater = 0",
            ['lid' => $lid]
        );

        // 3. Modell by Typ+Hersteller+Basis
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_modell (kSprache, cTyp, cHersteller, cBasis, cModell)
             SELECT DISTINCT :lid, TRIM(tmws_typ.cWert), TRIM(tmws_her.cWert), TRIM(tmws_basis.cWert), TRIM(tmws_mod.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 24
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_typ ON tmws_typ.kMerkmalWert = am_typ.kMerkmalWert
                                                AND tmws_typ.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = am_typ.kMerkmal
                                          AND ms_typ.kSprache = :lid
                                          AND ms_typ.cName = 'Wohnmobil-Aufbauart'
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
         INNER JOIN tartikelmerkmal am_basis ON am_basis.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_basis ON tmws_basis.kMerkmalWert = am_basis.kMerkmalWert
                                                   AND tmws_basis.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_basis ON ms_basis.kMerkmal = am_basis.kMerkmal
                                             AND ms_basis.kSprache = :lid
                                             AND ms_basis.cName = 'Wohnmobil-Fahrgestell'
         INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = am_mod.kMerkmalWert
                                                AND tmws_mod.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = am_mod.kMerkmal
                                          AND ms_mod.kSprache = :lid
                                          AND ms_mod.cName = 'Fahrzeug-Modell'
              WHERE a.nIstVater = 0",
            ['lid' => $lid]
        );
    }

    // ---------------------------------------------------------------
    // PKW Snapshots — 3 separate steps to avoid timeout
    // ---------------------------------------------------------------

    /**
     * PKW Step 1 — Hersteller & Modell snapshots
     */
    public function refreshPkwHerstellerModell(int $lid): void
    {
        $params = $this->pkwSnapshotParams($lid);
        $from   = $this->pkwArticleFromClause();

        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_hersteller WHERE kSprache = :lid', ['lid' => $lid]);
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_modell WHERE kSprache = :lid', ['lid' => $lid]);

        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_hersteller (kSprache, cHersteller)
             SELECT DISTINCT :lid, TRIM(tmws_her.cWert)
               {$from}
               {$this->merkmalJoinSql('her', "ms_her.cName = 'Fahrzeug-Marke'")}
              WHERE a.nIstVater = 0
                AND TRIM(tmws_her.cWert) <> ''",
            $params
        );

        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_modell (kSprache, cHersteller, cModell)
             SELECT DISTINCT :lid, TRIM(tmws_her.cWert), TRIM(tmws_mod.cWert)
               {$from}
               {$this->merkmalJoinSql('her', "ms_her.cName = 'Fahrzeug-Marke'")}
               {$this->merkmalJoinSql('mod', "ms_mod.cName = 'Fahrzeug-Modell'")}
              WHERE a.nIstVater = 0
                AND TRIM(tmws_her.cWert) <> ''
                AND TRIM(tmws_mod.cWert) <> ''",
            $params
        );

        $this->touchPkwSnapshotMeta($lid);
    }

    /**
     * PKW Step 2 — Baujahr snapshot
     */
    public function refreshPkwBaujahr(int $lid): void
    {
        $params = $this->pkwSnapshotParams($lid);
        $from   = $this->pkwArticleFromClause();
        $bjName = PkwSqlFragments::merkmalNameBaujahr('ms_bj');

        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_baujahr WHERE kSprache = :lid', ['lid' => $lid]);

        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_baujahr (kSprache, cHersteller, cModell, cBaujahr)
             SELECT DISTINCT :lid, TRIM(tmws_her.cWert), TRIM(tmws_mod.cWert), TRIM(tmws_bj.cWert)
               {$from}
               {$this->merkmalJoinSql('her', "ms_her.cName = 'Fahrzeug-Marke'")}
               {$this->merkmalJoinSql('mod', "ms_mod.cName = 'Fahrzeug-Modell'")}
               {$this->merkmalJoinSql('bj', $bjName)}
              WHERE a.nIstVater = 0
                AND TRIM(tmws_her.cWert) <> ''
                AND TRIM(tmws_mod.cWert) <> ''
                AND TRIM(tmws_bj.cWert) <> ''",
            $params
        );

        $this->touchPkwSnapshotMeta($lid);
    }

    /**
     * PKW Step 3 — Serie snapshot (heaviest query, kept separate)
     */
    public function refreshPkwSerie(int $lid): void
    {
        $next = 0;
        do {
            $r    = $this->refreshPkwSerieChunk($lid, $next, 300.0);
            $next = $r['next'];
        } while (!$r['done']);
    }

    /**
     * PKW-Serien-Snapshot in Teilschritten (pro Hersteller/Modell) neu aufbauen.
     * Die Komplett-Abfrage explodiert bei universellen Artikeln (alle Marken × Modelle × Baujahre)
     * und läuft > 15 Min. — pro Modell bleibt die Zwischenmenge klein.
     *
     * @return array{done:bool,next:int,total:int,rows:int}
     */
    public function refreshPkwSerieChunk(int $lid, int $offset, float $timeBudget = 40.0): array
    {
        $lid    = $lid > 0 ? $lid : 1;
        $params = ['langID' => $lid];
        $start  = \microtime(true);

        if ($offset <= 0) {
            $offset = 0;
            $this->db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_serie (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kSprache INT NOT NULL,
                    cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                    cModell VARCHAR(255) NOT NULL DEFAULT '',
                    cBaujahr VARCHAR(16) NOT NULL DEFAULT '',
                    cSerie VARCHAR(255) NOT NULL DEFAULT '',
                    cSerieLabel VARCHAR(255) NOT NULL DEFAULT '',
                    cSerieSeo VARCHAR(255) NOT NULL DEFAULT '',
                    UNIQUE KEY uq_pkw_serie (kSprache, cHersteller, cModell, cBaujahr, cSerie),
                    KEY idx_pkw_serie (kSprache, cHersteller, cModell, cBaujahr)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $this->db->executeQuery(
                "ALTER TABLE twomosi_fk_opt_pkw_serie
                 ADD COLUMN IF NOT EXISTS cSerieSeo VARCHAR(255) NOT NULL DEFAULT ''"
            );
            $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_serie WHERE kSprache = :lid', ['lid' => $lid]);
        }

        $models = $this->db->getObjects(
            'SELECT cHersteller, cModell FROM twomosi_fk_opt_pkw_modell
              WHERE kSprache = :lid ORDER BY cHersteller, cModell',
            ['lid' => $lid]
        );
        $total = \count($models);

        $valueIds = ['Fahrzeug-Marke' => [], 'Fahrzeug-Modell' => []];
        foreach ($this->db->getObjects(
            "SELECT tmws.kMerkmalWert AS id, TRIM(tmws.cWert) AS val, ms.cName AS merkmal
               FROM tmerkmalwertsprache tmws
         INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
         INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = mw.kMerkmal
                AND ms.kSprache = :langID
                AND ms.cName IN ('Fahrzeug-Marke','Fahrzeug-Modell')
              WHERE tmws.kSprache = :langID",
            ['langID' => $lid]
        ) as $row) {
            $valueIds[(string)$row->merkmal][(string)$row->val][] = (int)$row->id;
        }

        $segJoin = PkwSqlFragments::pkwProdukttypFilter('langID') . PkwSqlFragments::articleNotAufbauart();
        $bjName  = PkwSqlFragments::merkmalNameBaujahr('ms_bj');
        $rowsIns = 0;
        $i       = $offset;

        $this->db->executeQuery('DROP TEMPORARY TABLE IF EXISTS tmp_fk_pkw_art');
        $this->db->executeQuery(
            'CREATE TEMPORARY TABLE tmp_fk_pkw_art (
                kArtikel INT NOT NULL PRIMARY KEY,
                kVaterArtikel INT NOT NULL DEFAULT 0,
                KEY idx_vater (kVaterArtikel)
            ) ENGINE=MEMORY'
        );

        for (; $i < $total; $i++) {
            if ($i > $offset && (\microtime(true) - $start) > $timeBudget) {
                break;
            }
            $h      = (string)$models[$i]->cHersteller;
            $m      = (string)$models[$i]->cModell;
            $herIds = $valueIds['Fahrzeug-Marke'][$h] ?? [];
            $modIds = $valueIds['Fahrzeug-Modell'][$m] ?? [];
            if ($herIds === [] || $modIds === []) {
                continue;
            }

            // Gleiche Artikelbasis wie FilterKonfiguratorRepository::findPkwSeriesRows (ohne LIMIT).
            $ids = [];
            foreach ($this->db->getObjects(
                'SELECT DISTINCT a.kArtikel
                   FROM tartikel a
             INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                    AND am_her.kMerkmalWert IN (' . \implode(',', $herIds) . ')
             INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
                    AND am_mod.kMerkmalWert IN (' . \implode(',', $modIds) . ')
                  WHERE a.nIstVater = 0'
            ) as $row) {
                $ids[] = (int)$row->kArtikel;
            }
            if ($ids === []) {
                continue;
            }

            $this->db->executeQuery('DELETE FROM tmp_fk_pkw_art');
            foreach (\array_chunk($ids, 2000) as $idChunk) {
                $this->db->executeQueryPrepared(
                    'INSERT IGNORE INTO tmp_fk_pkw_art (kArtikel, kVaterArtikel)
                     SELECT DISTINCT a.kArtikel, a.kVaterArtikel
                       FROM tartikel a
                       ' . $segJoin . '
                      WHERE a.kArtikel IN (' . \implode(',', $idChunk) . ')',
                    $params
                );
            }

            $bjJoin = " INNER JOIN tartikelmerkmal am_bj ON am_bj.kArtikel = t.kArtikel
                        INNER JOIN tmerkmalwert mw_bj ON mw_bj.kMerkmalWert = am_bj.kMerkmalWert
                        INNER JOIN tmerkmalsprache ms_bj ON ms_bj.kMerkmal = mw_bj.kMerkmal
                               AND ms_bj.kSprache = :langID AND {$bjName}
                        INNER JOIN tmerkmalwertsprache tmws_bj ON tmws_bj.kMerkmalWert = am_bj.kMerkmalWert
                               AND tmws_bj.kSprache = :langID";

            $found = [];
            foreach (['t.kArtikel', 't.kVaterArtikel'] as $owner) {
                foreach ($this->db->getObjects(
                    "SELECT DISTINCT TRIM(tmws_bj.cWert) AS bj, TRIM(sw.cWert) AS serie, seo.cSeo AS serieSeo
                       FROM tmp_fk_pkw_art t
                       {$bjJoin}
                 INNER JOIN tartikelmerkmal tam ON tam.kArtikel = {$owner}
                 INNER JOIN tmerkmalwert mws ON mws.kMerkmalWert = tam.kMerkmalWert
                 INNER JOIN tmerkmalsprache mss ON mss.kMerkmal = mws.kMerkmal
                        AND mss.kSprache = :langID AND mss.cName = 'Serie'
                 INNER JOIN tmerkmalwertsprache sw ON sw.kMerkmalWert = tam.kMerkmalWert
                        AND sw.kSprache = :langID
                  LEFT JOIN tseo seo ON seo.cKey = 'kMerkmalWert'
                        AND seo.kKey = tam.kMerkmalWert AND seo.kSprache = :langID",
                    $params
                ) as $row) {
                    $this->addSnapshotSerie($found, (string)$row->bj, (string)$row->serie, (string)($row->serieSeo ?? ''), true);
                }
            }
            foreach (['t.kArtikel', 't.kVaterArtikel'] as $owner) {
                foreach ($this->db->getObjects(
                    "SELECT DISTINCT TRIM(tmws_bj.cWert) AS bj,
                            COALESCE(NULLIF(ks.cName, ''), NULLIF(c.cName, '')) AS serie,
                            seo.cSeo AS serieSeo
                       FROM tmp_fk_pkw_art t
                       {$bjJoin}
                 INNER JOIN tkategorieartikel ka ON ka.kArtikel = {$owner}
                 INNER JOIN tkategorie c ON c.kKategorie = ka.kKategorie
                  LEFT JOIN tkategoriesprache ks ON ks.kKategorie = c.kKategorie
                        AND ks.kSprache = :langID
                  LEFT JOIN tseo seo ON seo.cKey = 'kKategorie'
                        AND seo.kKey = c.kKategorie AND seo.kSprache = :langID
                      WHERE COALESCE(NULLIF(ks.cName, ''), c.cName) LIKE '%Serie%'",
                    $params
                ) as $row) {
                    $this->addSnapshotSerie(
                        $found,
                        (string)$row->bj,
                        FilterKonfiguratorRepository::stripSerieSuffix((string)$row->serie),
                        (string)($row->serieSeo ?? ''),
                        false
                    );
                }
            }

            $tuples = [];
            foreach ($found as $bj => $series) {
                foreach ($series as $s) {
                    $tuples[] = [$bj, $s['serie'], $s['seo']];
                }
            }
            foreach (\array_chunk($tuples, 300) as $chunk) {
                $values = [];
                $p      = [];
                foreach ($chunk as $k => [$bj, $serie, $seo]) {
                    $values[]    = "(:l{$k}, :h{$k}, :m{$k}, :b{$k}, :s{$k}, :t{$k}, :o{$k})";
                    $p['l' . $k] = $lid;
                    $p['h' . $k] = $h;
                    $p['m' . $k] = $m;
                    $p['b' . $k] = $bj;
                    $p['s' . $k] = $serie;
                    $p['t' . $k] = $serie;
                    $p['o' . $k] = $seo;
                }
                $this->db->executeQueryPrepared(
                    'INSERT IGNORE INTO twomosi_fk_opt_pkw_serie
                        (kSprache, cHersteller, cModell, cBaujahr, cSerie, cSerieLabel, cSerieSeo)
                     VALUES ' . \implode(',', $values),
                    $p
                );
                $rowsIns += \count($chunk);
            }
        }
        $done = $i >= $total;
        if ($done) {
            $this->flushPkwStep4Cache();
            $this->touchPkwSnapshotMeta($lid);
        }

        return ['done' => $done, 'next' => $i, 'total' => $total, 'rows' => $rowsIns];
    }

    /**
     * Merkmal-Serien haben Vorrang vor gleichnamigen Serien-Kategorien (wie mergePkwSeriesRows).
     *
     * @param array<string,array<string,array{serie:string,seo:string,m:bool}>> $found
     */
    private function addSnapshotSerie(array &$found, string $bj, string $serie, string $seo, bool $isMerkmal): void
    {
        $serie = \trim($serie);
        if ($bj === '' || $serie === '') {
            return;
        }
        $key = \mb_strtolower($serie);
        if (isset($found[$bj][$key]) && ($found[$bj][$key]['m'] || !$isMerkmal)) {
            if ($found[$bj][$key]['seo'] === '' && $seo !== '' && $found[$bj][$key]['m'] === $isMerkmal) {
                $found[$bj][$key]['seo'] = $seo;
            }
            return;
        }
        $found[$bj][$key] = ['serie' => $serie, 'seo' => $seo, 'm' => $isMerkmal];
    }

    /**
     * PKW-Serienlisten im Step4-Cache verwerfen, damit nach einem Snapshot-Refresh
     * keine veralteten Serien-URLs ausgeliefert werden.
     */
    public function flushPkwStep4Cache(): void
    {
        try {
            $this->db->executeQuery(
                "DELETE FROM twomosi_step4_cache WHERE cPayload LIKE '%segment=pkw%'"
            );
        } catch (\Throwable $e) {
            // Tabelle fehlt evtl. noch — ignorieren.
        }
    }

    /**
     * @return array{lid:int,langID:int}
     */
    private function pkwSnapshotParams(int $lid): array
    {
        $langID = $lid > 0 ? $lid : 1;

        return ['lid' => $langID, 'langID' => $langID];
    }

    /** Gleiche PKW-Artikelbasis wie im Live-Konfigurator (Kategorie 19 + Produkttyp + kein WM-Aufbauart). */
    private function pkwArticleFromClause(): string
    {
        return 'FROM tartikel a'
            . PkwSqlFragments::pkwProdukttypFilter('langID')
            . PkwSqlFragments::articleNotAufbauart();
    }

    /**
     * Korrekter Merkmal-Join (über tmerkmalwert), wie im Repository — nicht über tartikelmerkmal.kMerkmal.
     */
    private function merkmalJoinSql(string $alias, string $msNameCondition): string
    {
        return " INNER JOIN tartikelmerkmal am_{$alias} ON am_{$alias}.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwert mw_{$alias} ON mw_{$alias}.kMerkmalWert = am_{$alias}.kMerkmalWert
         INNER JOIN tmerkmal m_{$alias} ON m_{$alias}.kMerkmal = mw_{$alias}.kMerkmal
         INNER JOIN tmerkmalsprache ms_{$alias} ON ms_{$alias}.kMerkmal = m_{$alias}.kMerkmal
              AND ms_{$alias}.kSprache = :langID
              AND {$msNameCondition}
         INNER JOIN tmerkmalwertsprache tmws_{$alias} ON tmws_{$alias}.kMerkmalWert = am_{$alias}.kMerkmalWert
              AND tmws_{$alias}.kSprache = :langID";
    }

    private function touchPkwSnapshotMeta(int $lid): void
    {
        try {
            $this->db->executeQueryPrepared(
                'INSERT INTO twomosi_fk_opt_pkw_meta (kSprache, dRefreshed)
                 VALUES (:lid, NOW())
                 ON DUPLICATE KEY UPDATE dRefreshed = NOW()',
                ['lid' => $lid > 0 ? $lid : 1]
            );
        } catch (\Throwable $e) {
            // Meta-Tabelle fehlt evtl. noch.
        }
    }

    /**
     * @return array{hersteller:int,modell:int,baujahr:int,serie:int}
     */
    public function getPkwSnapshotCounts(): array
    {
        $counts = ['hersteller' => 0, 'modell' => 0, 'baujahr' => 0, 'serie' => 0];
        $map    = [
            'hersteller' => 'twomosi_fk_opt_pkw_hersteller',
            'modell'     => 'twomosi_fk_opt_pkw_modell',
            'baujahr'    => 'twomosi_fk_opt_pkw_baujahr',
            'serie'      => 'twomosi_fk_opt_pkw_serie',
        ];

        foreach ($map as $key => $table) {
            try {
                $row = $this->db->getSingleObject("SELECT COUNT(*) AS cnt FROM {$table}");
                $counts[$key] = isset($row->cnt) ? (int)$row->cnt : 0;
            } catch (\Throwable $e) {
                $counts[$key] = 0;
            }
        }

        return $counts;
    }
}
