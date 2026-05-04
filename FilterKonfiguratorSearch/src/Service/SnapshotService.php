<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Service;

use JTL\DB\DbInterface;
use Plugin\FilterKonfiguratorSearch\Query\PkwSqlFragments;

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
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_hersteller WHERE kSprache = :lid', ['lid' => $lid]);
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_modell WHERE kSprache = :lid', ['lid' => $lid]);

        // 1. PKW Hersteller
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_hersteller (kSprache, cHersteller)
             SELECT DISTINCT :lid, TRIM(tmws_her.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 19
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
              WHERE a.nIstVater = 0
                AND NOT EXISTS (
                    SELECT 1 FROM tartikelmerkmal am_x
                    INNER JOIN tmerkmalsprache ms_x ON ms_x.kMerkmal = am_x.kMerkmal
                                                   AND ms_x.kSprache = :lid
                                                   AND ms_x.cName = 'Wohnmobil-Aufbauart'
                    WHERE am_x.kArtikel = a.kArtikel
                )",
            ['lid' => $lid]
        );

        // 2. PKW Modell
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_modell (kSprache, cHersteller, cModell)
             SELECT DISTINCT :lid, TRIM(tmws_her.cWert), TRIM(tmws_mod.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 19
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
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

    /**
     * PKW Step 2 — Baujahr snapshot
     */
    public function refreshPkwBaujahr(int $lid): void
    {
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_baujahr WHERE kSprache = :lid', ['lid' => $lid]);

        $bjNameSql = PkwSqlFragments::merkmalNameBaujahr('ms_bj');
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_baujahr (kSprache, cHersteller, cModell, cBaujahr)
             SELECT DISTINCT :lid, TRIM(tmws_her.cWert), TRIM(tmws_mod.cWert), TRIM(tmws_bj.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 19
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
         INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = am_mod.kMerkmalWert
                                                AND tmws_mod.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = am_mod.kMerkmal
                                          AND ms_mod.kSprache = :lid
                                          AND ms_mod.cName = 'Fahrzeug-Modell'
         INNER JOIN tartikelmerkmal am_bj ON am_bj.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_bj ON tmws_bj.kMerkmalWert = am_bj.kMerkmalWert
                                                AND tmws_bj.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_bj ON ms_bj.kMerkmal = am_bj.kMerkmal
                                          AND ms_bj.kSprache = :lid
                                          AND {$bjNameSql}
              WHERE a.nIstVater = 0",
            ['lid' => $lid]
        );
    }

    /**
     * PKW Step 3 — Serie snapshot (heaviest query, kept separate)
     */
    public function refreshPkwSerie(int $lid): void
    {
        // Ensure table exists (safe on first run)
        $this->db->executeQuery(
            "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_pkw_serie (
                kID INT AUTO_INCREMENT PRIMARY KEY,
                kSprache INT NOT NULL,
                cHersteller VARCHAR(255) NOT NULL DEFAULT '',
                cModell VARCHAR(255) NOT NULL DEFAULT '',
                cBaujahr VARCHAR(16) NOT NULL DEFAULT '',
                cSerie VARCHAR(255) NOT NULL DEFAULT '',
                cSerieLabel VARCHAR(255) NOT NULL DEFAULT '',
                UNIQUE KEY uq_pkw_serie (kSprache, cHersteller, cModell, cBaujahr, cSerie),
                KEY idx_pkw_serie (kSprache, cHersteller, cModell, cBaujahr)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->db->executeQueryPrepared('DELETE FROM twomosi_fk_opt_pkw_serie WHERE kSprache = :lid', ['lid' => $lid]);

        $bjNameSql = PkwSqlFragments::merkmalNameBaujahr('ms_bj');
        $this->db->executeQueryPrepared(
            "INSERT IGNORE INTO twomosi_fk_opt_pkw_serie (kSprache, cHersteller, cModell, cBaujahr, cSerie, cSerieLabel)
             SELECT DISTINCT :lid,
                    TRIM(tmws_her.cWert),
                    TRIM(tmws_mod.cWert),
                    TRIM(tmws_bj.cWert),
                    TRIM(tmws_ser.cWert),
                    TRIM(tmws_ser.cWert)
               FROM tartikel a
         INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
         INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
         INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 19
            AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
         INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = am_her.kMerkmalWert
                                                AND tmws_her.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = am_her.kMerkmal
                                          AND ms_her.kSprache = :lid
                                          AND ms_her.cName = 'Fahrzeug-Marke'
         INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = am_mod.kMerkmalWert
                                                AND tmws_mod.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = am_mod.kMerkmal
                                          AND ms_mod.kSprache = :lid
                                          AND ms_mod.cName = 'Fahrzeug-Modell'
         INNER JOIN tartikelmerkmal am_bj ON am_bj.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_bj ON tmws_bj.kMerkmalWert = am_bj.kMerkmalWert
                                                AND tmws_bj.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_bj ON ms_bj.kMerkmal = am_bj.kMerkmal
                                          AND ms_bj.kSprache = :lid
                                          AND {$bjNameSql}
         INNER JOIN tartikelmerkmal am_ser ON am_ser.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwertsprache tmws_ser ON tmws_ser.kMerkmalWert = am_ser.kMerkmalWert
                                                AND tmws_ser.kSprache = :lid
         INNER JOIN tmerkmalsprache ms_ser ON ms_ser.kMerkmal = am_ser.kMerkmal
                                          AND ms_ser.kSprache = :lid
                                          AND ms_ser.cName IN ('Serie', 'kompatible Serien', 'Familie-Serie')
              WHERE a.nIstVater = 0
                AND TRIM(tmws_ser.cWert) <> ''",
            ['lid' => $lid]
        );
    }
}
