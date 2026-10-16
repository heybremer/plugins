<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Repository;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\FilterKonfiguratorSearch\Filter\CharacteristicOptionsGuard;
use Plugin\FilterKonfiguratorSearch\Query\PkwSqlFragments;

/**
 * Data-access layer for FilterKonfigurator.
 *
 * All SQL queries that were previously in FilterKonfiguratorController
 * live here.  The controller is now a thin HTTP adapter.
 */
class FilterKonfiguratorRepository
{
    /** @var array<string, array<int|string, mixed>> */
    private static array $merkmalOptionsMemo = [];

    public function __construct(
        private readonly DbInterface $db,
        private readonly int $langID
    ) {}
    
        /**
         * Returns all valid options for all dropdowns and the series list in one call.
         * 
         * @param array<string,string> $s Selections
         * @param bool $fkOpts
         * @return array{typ:array, hersteller:array, basis:array, modell:array, baujahr:array, sitz:array, series:array}
         */
        public function loadBatchOptions(array $s, bool $fkOpts = false): array
        {
            $seg = ($s['segment'] ?? '') === 'pkw' ? 'pkw' : 'wohnmobil';
            
            return [
                'typ'        => $this->loadMerkmalOptions('typ', '', '', '', '', '', '', $fkOpts, $seg),
                'hersteller' => $this->loadMerkmalOptions('hersteller', '', $s['typ'], '', '', '', '', $fkOpts, $seg),
                'basis'      => $this->loadMerkmalOptions('basis', '', $s['typ'], $s['hersteller'], '', '', '', $fkOpts, $seg),
                'modell'     => $this->loadMerkmalOptions('modell', '', $s['typ'], $s['hersteller'], $s['basis'], '', '', $fkOpts, $seg),
                'baujahr'    => $this->loadMerkmalOptions('baujahr', '', $s['typ'], $s['hersteller'], $s['basis'], $s['modell'], '', $fkOpts, $seg),
                'sitz'       => $this->loadMerkmalOptions('sitz', '', $s['typ'], $s['hersteller'], $s['basis'], $s['modell'], $s['baujahr'], $fkOpts, $seg),
                'series'     => $this->loadSeriesForBatch($s, $fkOpts),
            ];
        }
    
        private function loadSeriesForBatch(array $s, bool $fkOpts): array
        {
            $seg = ($s['segment'] ?? '') === 'pkw' ? 'pkw' : 'wohnmobil';
            $isPkw = $seg === 'pkw';
            $typ = \trim($s['typ'] ?? '');
            $herst = \trim($s['hersteller'] ?? '');
            $basis = \trim($s['basis'] ?? '');
            $modell = \trim($s['modell'] ?? '');
            $baujahr = \trim($s['baujahr'] ?? '');
            $sitz = \trim($s['sitz'] ?? '');
            
            // Replicate canLoadSeries logic from JS (PKW: Baujahr Pflicht vor Serien)
            $canLoad = false;
            if ($isPkw) {
                if ($herst !== '' && $modell !== '' && $baujahr !== '') $canLoad = true;
            } else {
                if ($typ !== '' && $herst !== '' && $basis !== '' && $modell !== '' && $sitz !== '') $canLoad = true;
            }
            
            if (!$canLoad) {
                return [];
            }

            // 0. Cache Check (Use EXACT SAME step4 cache key as Controller.php)
            $cacheKey = $isPkw
                ? \md5($this->langID . '|pkw|' . $herst . '|' . $modell . '|' . $baujahr . '|' . ($fkOpts ? 'familie' : 'std'))
                : \md5($this->langID . '|' . $typ . '|' . $herst . '|' . $basis . '|' . $modell . '|' . $sitz . '|' . ($fkOpts ? 'familie' : 'std'));
            
            try {
                $cacheRow = $this->db->getSingleObject(
                    'SELECT cPayload, dUpdated FROM twomosi_step4_cache WHERE cKey = :k LIMIT 1',
                    ['k' => $cacheKey]
                );
                if ($cacheRow !== null) {
                    $age = \time() - (int)\strtotime((string)($cacheRow->dUpdated ?? ''));
                    if ($age >= 0 && $age < 172800) { // 2 days TTL
                        $decoded = \json_decode((string)($cacheRow->cPayload ?? ''), true);
                        if (\is_array($decoded)) {
                            $selectionQuery = [
                                'segment'    => $isPkw ? 'pkw' : '',
                                'typ'        => $typ,
                                'hersteller' => $herst,
                                'basis'      => $basis,
                                'modell'     => $modell,
                                'baujahr'    => $baujahr,
                                'sitz'       => $sitz,
                            ];
                            $decoded = $this->reapplyKonfiguratorQueryOnSeriesItems($decoded, $selectionQuery);
                            return $decoded;
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // PKW-Snapshot wird in findSeriesForValues() gelesen, damit Batch- und
            // /fk-search-Weg dieselben Items (inkl. url) liefern und derselbe
            // step4_cache-Key nicht mit unterschiedlichen Payload-Formen belegt wird.
            
            $selections = [];
            // Replicate exactly the Controller.php selection builder logic
            if ($fkOpts && $modell !== '') {
                if ($isPkw) {
                    $selections[] = ['value' => $herst, 'names' => ['Fahrzeug-Marke']];
                    if ($sitz !== '') {
                        $selections[] = ['value' => $sitz, 'names' => ['kompatible Sitze']];
                    }
                    if ($baujahr !== '') {
                        $selections[] = ['value' => $baujahr, 'names' => ['Baujahr', 'Bau-Jahr', 'Bau Jahr']];
                    }
                } else {
                    if ($typ !== '') $selections[] = ['value' => $typ, 'names' => ['Wohnmobil-Aufbauart']];
                    if ($herst !== '') $selections[] = ['value' => $herst, 'names' => ['Fahrzeug-Marke']];
                    if ($basis !== '') $selections[] = ['value' => $basis, 'names' => ['Wohnmobil-Fahrgestell']];
                    if ($sitz !== '') $selections[] = ['value' => $sitz, 'names' => ['kompatible Sitze']];
                }
            } else {
                if (!$isPkw && $typ !== '') $selections[] = ['value' => $typ, 'names' => ['Wohnmobil-Aufbauart']];
                if ($herst !== '') $selections[] = ['value' => $herst, 'names' => ['Fahrzeug-Marke']];
                if ($modell !== '') $selections[] = ['value' => $modell, 'names' => ['Fahrzeug-Modell']];
                if (!$isPkw && $basis !== '') $selections[] = ['value' => $basis, 'names' => ['Wohnmobil-Fahrgestell']];
                if ($sitz !== '') $selections[] = ['value' => $sitz, 'names' => ['kompatible Sitze']];
                if ($isPkw && $baujahr !== '') $selections[] = ['value' => $baujahr, 'names' => ['Baujahr', 'Bau-Jahr', 'Bau Jahr']];
            }
    
            if (\count($selections) === 0) {
                return [];
            }
            
            $exact = $this->findExactMerkmalValuesForNames($selections);
            if (\count($exact) === 0) {
                return [];
            }
            
            $serieMerkmal = ($fkOpts && $modell !== '') ? 'Familie-Serie' : 'Serie';
            
            $res = $this->findSeriesForValues($exact, $serieMerkmal, $s);
            if ($res === []) {
                $res = $this->findSeriesForValues($exact, 'Familie-Serie', $s);
            }

            // Write Cache
            if ($res !== []) {
                try {
                    $this->db->executeQueryPrepared(
                        "INSERT INTO twomosi_step4_cache (cKey, dUpdated, cPayload)
                         VALUES (:k, NOW(), :p)
                         ON DUPLICATE KEY UPDATE dUpdated = NOW(), cPayload = VALUES(cPayload)",
                        ['k' => $cacheKey, 'p' => \json_encode($res, \JSON_UNESCAPED_UNICODE)]
                    );
                } catch (\Throwable $e) {}
            }

            return $res;
        }

        public function tryLoadOptionsFromSnapshot(
            string $field,
            string $term,
            string $typFilter,
            string $herstellerFilter,
            string $basisFilter,
            string $segment = 'wohnmobil'
        ): ?array {
            $field = \trim($field);
            $term = \trim($term);
            $lid = $this->langID > 0 ? $this->langID : 1;
            
            // 1. PKW Segment Snapshots
            if ($segment === 'pkw') {
                try {
                    if ($field === 'hersteller' && $term === '') {
                        $rows = $this->db->getObjects(
                            'SELECT cHersteller AS val FROM twomosi_fk_opt_pkw_hersteller 
                              WHERE kSprache = :lid ORDER BY cHersteller ASC',
                            ['lid' => $lid]
                        );
                    } elseif ($field === 'modell' && $term === '' && $herstellerFilter !== '') {
                        $rows = $this->db->getObjects(
                            'SELECT cModell AS val FROM twomosi_fk_opt_pkw_modell 
                              WHERE kSprache = :lid AND cHersteller = :her ORDER BY cModell ASC',
                            ['lid' => $lid, 'her' => $herstellerFilter]
                        );
                    } elseif ($field === 'baujahr' && $term === '' && $herstellerFilter !== '' && $basisFilter !== '') {
                        $rows = $this->db->getObjects(
                            'SELECT cBaujahr AS val FROM twomosi_fk_opt_pkw_baujahr 
                              WHERE kSprache = :lid AND cHersteller = :her AND cModell = :mod ORDER BY cBaujahr ASC',
                            ['lid' => $lid, 'her' => $herstellerFilter, 'mod' => $basisFilter]
                        );
                    } else {
                        return null;
                    }
                } catch (\Throwable $e) { return null; }
            } else {
                // 2. Wohnmobil Segment Snapshots
                $params = ['lid' => $lid];
                $likeHer = ($term !== '') ? ' AND cHersteller LIKE :term' : '';
                $likeBas = ($term !== '') ? ' AND cBasis LIKE :term' : '';
                $likeMod = ($term !== '') ? ' AND cModell LIKE :term' : '';
                if ($term !== '') $params['term'] = '%' . $term . '%';

                try {
                    if ($field === 'hersteller') {
                        $sql = "SELECT DISTINCT cHersteller AS val FROM twomosi_fk_opt_hersteller WHERE kSprache = :lid";
                        if ($typFilter !== '') { $sql .= " AND cTyp = :typ"; $params['typ'] = $typFilter; }
                        $rows = $this->db->getObjects($sql . $likeHer . " ORDER BY cHersteller ASC LIMIT 200", $params);
                    } elseif ($field === 'basis' && $typFilter !== '' && $herstellerFilter !== '') {
                        $params['typ'] = $typFilter; $params['her'] = $herstellerFilter;
                        $rows = $this->db->getObjects(
                            "SELECT DISTINCT cBasis AS val FROM twomosi_fk_opt_basis 
                              WHERE kSprache = :lid AND cTyp = :typ AND cHersteller = :her" . $likeBas . " ORDER BY cBasis ASC LIMIT 120",
                            $params
                        );
                    } elseif ($field === 'modell' && $typFilter !== '' && $herstellerFilter !== '') {
                        $params['typ'] = $typFilter;
                        $params['her'] = $herstellerFilter;
                        $basisSql = '';
                        if ($basisFilter !== '') {
                            $params['basis'] = $basisFilter;
                            $basisSql = ' AND cBasis = :basis';
                        }
                        $rows = $this->db->getObjects(
                            "SELECT DISTINCT cModell AS val FROM twomosi_fk_opt_modell
                              WHERE kSprache = :lid AND cTyp = :typ AND cHersteller = :her"
                            . $basisSql . $likeMod . " ORDER BY cModell ASC LIMIT 120",
                            $params
                        );
                    } else {
                        return null;
                    }
                } catch (\Throwable $e) { return null; }
            }

            if (empty($rows)) return null;
            $out = [];
            foreach ($rows as $row) {
                if (!empty($row->val)) $out[] = ['value' => $row->val];
            }
            return $out !== [] ? $out : null;
        }
    
    
        /**
         * PKW-Konfigurator: Fahrzeug-Marke / Fahrzeug-Modell / Baujahr / kompatible Sitze (ohne Aufbauart & Fahrgestell).
         *
         * @return array<int, array{value:string}>
         */
        /**
         * PKW seri önce seçilince marka/model/yıl listesini snapshot'tan süzer.
         *
         * @return array<int, array{value:string}>|null
         */
        private function loadPkwSnapshotBySerie(
            string $field,
            string $serie,
            string $hersteller,
            string $modell
        ): ?array {
            $lid = $this->langID > 0 ? $this->langID : 1;
            $params = ['lid' => $lid];
            try {
                if ($field === 'serie') {
                    $rows = $this->db->getObjects(
                        'SELECT DISTINCT cSerie AS val
                           FROM twomosi_fk_opt_pkw_serie
                          WHERE kSprache = :lid AND cSerie <> \'\'
                       ORDER BY cSerie ASC',
                        $params
                    );
                } elseif ($field === 'hersteller' && $serie !== '') {
                    $params['serie'] = $serie;
                    $rows = $this->db->getObjects(
                        'SELECT DISTINCT cHersteller AS val
                           FROM twomosi_fk_opt_pkw_serie
                          WHERE kSprache = :lid AND cSerie = :serie AND cHersteller <> \'\'
                       ORDER BY cHersteller ASC',
                        $params
                    );
                } elseif ($field === 'modell' && $serie !== '' && $hersteller !== '') {
                    $params['serie'] = $serie;
                    $params['her'] = $hersteller;
                    $rows = $this->db->getObjects(
                        'SELECT DISTINCT cModell AS val
                           FROM twomosi_fk_opt_pkw_serie
                          WHERE kSprache = :lid AND cSerie = :serie AND cHersteller = :her AND cModell <> \'\'
                       ORDER BY cModell ASC',
                        $params
                    );
                } elseif ($field === 'baujahr' && $serie !== '' && $hersteller !== '' && $modell !== '') {
                    $params['serie'] = $serie;
                    $params['her'] = $hersteller;
                    $params['mod'] = $modell;
                    $rows = $this->db->getObjects(
                        'SELECT DISTINCT cBaujahr AS val
                           FROM twomosi_fk_opt_pkw_serie
                          WHERE kSprache = :lid AND cSerie = :serie AND cHersteller = :her AND cModell = :mod AND cBaujahr <> \'\'
                       ORDER BY cBaujahr ASC',
                        $params
                    );
                } else {
                    return null;
                }
            } catch (\Throwable $e) {
                return null;
            }

            $out = [];
            foreach ((array)$rows as $row) {
                $value = \trim((string)($row->val ?? ''));
                if ($value !== '') {
                    $out[] = ['value' => $value];
                }
            }

            return $out;
        }

        public function loadPkwMerkmalOptions(
            string $field,
            string $term,
            string $herstellerFilter,
            string $modellFilter,
            string $baujahrFilter = '',
            string $serieFilter = '',
            string $vehicleSegment = 'pkw'
        ): array {
            $field = \trim($field);
            $term = \trim($term);
            $herstellerFilter = \trim($herstellerFilter);
            $modellFilter = \trim($modellFilter);
            $serieFilter = \trim($serieFilter);
            $frag = PkwSqlFragments::articleNotAufbauart();
    
            // Fast path: PKW Snapshots
            if ($serieFilter === '') {
                $snap = $this->tryLoadOptionsFromSnapshot(
                    $field,
                    $term,
                    '',
                    $herstellerFilter,
                    $modellFilter,
                    'pkw'
                );
                if (\is_array($snap)) return $snap;
            } elseif ($term === '') {
                $bySerie = $this->loadPkwSnapshotBySerie($field, $serieFilter, $herstellerFilter, $modellFilter);
                if (\is_array($bySerie)) {
                    return $bySerie;
                }
            }

            if ($field === 'serie') {
                $langID = $this->langID > 0 ? $this->langID : 1;
                if ($term === '' && $herstellerFilter === '' && $modellFilter === '' && \trim($baujahrFilter) === '') {
                    $allSeries = $this->loadPkwSnapshotBySerie('serie', '', '', '');
                    if (\is_array($allSeries) && $allSeries !== []) {
                        return $allSeries;
                    }
                }
                // 0) Cache Check (Speed up Step 4 for PKW)
                $cacheKey = '';
                if ($term === '') {
                    $cacheKey = \md5('fk_opt_serie|' . $langID . '|pkw|'
                        . \trim($herstellerFilter) . '|' . \trim($modellFilter) . '|' . \trim($baujahrFilter));
                    try {
                        $cacheRow = $this->db->getSingleObject(
                            'SELECT cPayload, dUpdated FROM twomosi_step4_cache WHERE cKey = :k LIMIT 1',
                            ['k' => $cacheKey]
                        );
                        if ($cacheRow !== null) {
                            $age = \time() - (int)\strtotime((string)($cacheRow->dUpdated ?? ''));
                            if ($age >= 0 && $age < 43200) { // 12 hours TTL
                                $decoded = \json_decode((string)($cacheRow->cPayload ?? ''), true);
                                if (\is_array($decoded)) {
                                    return $decoded;
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                $params = ['langID' => $langID];
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql = ' AND tmws_ser.cWert LIKE :term';
                }

                $joins = "";
                if ($herstellerFilter !== '') {
                    $params['hersteller'] = $herstellerFilter;
                    $joins .= " INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                                INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                                INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = mw_her.kMerkmal
                                   AND ms_her.kSprache = :langID AND ms_her.cName = 'Fahrzeug-Marke'
                                INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                   AND tmws_her.kSprache = :langID AND TRIM(tmws_her.cWert) = TRIM(:hersteller)";
                }
                if ($modellFilter !== '') {
                    $params['modell'] = $modellFilter;
                    $joins .= " INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
                                INNER JOIN tmerkmalwert mw_mod ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                                INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = mw_mod.kMerkmal
                                   AND ms_mod.kSprache = :langID AND ms_mod.cName = 'Fahrzeug-Modell'
                                INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                                   AND tmws_mod.kSprache = :langID AND TRIM(tmws_mod.cWert) = TRIM(:modell)";
                }
                if ($baujahrFilter !== '') {
                    $params['baujahr'] = $baujahrFilter;
                    $bjNameSql = PkwSqlFragments::merkmalNameBaujahr('ms_bj');
                    $joins .= " INNER JOIN tartikelmerkmal am_bj ON am_bj.kArtikel = a.kArtikel
                                INNER JOIN tmerkmalwert mw_bj ON mw_bj.kMerkmalWert = am_bj.kMerkmalWert
                                INNER JOIN tmerkmalsprache ms_bj ON ms_bj.kMerkmal = mw_bj.kMerkmal
                                   AND ms_bj.kSprache = :langID AND {$bjNameSql}
                                INNER JOIN tmerkmalwertsprache tmws_bj ON tmws_bj.kMerkmalWert = mw_bj.kMerkmalWert
                                   AND tmws_bj.kSprache = :langID AND TRIM(tmws_bj.cWert) = TRIM(:baujahr)";
                }

                $ptypJoin = PkwSqlFragments::pkwProdukttypFilter('langID');
                $sql = "SELECT DISTINCT tmws_ser.cWert
                          FROM tartikel a
                               {$joins}
                               {$ptypJoin}
                    INNER JOIN tartikelmerkmal am_ser ON am_ser.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_ser ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
                    INNER JOIN tmerkmal m_ser ON m_ser.kMerkmal = mw_ser.kMerkmal
                    INNER JOIN tmerkmalsprache ms_ser ON ms_ser.kMerkmal = m_ser.kMerkmal
                       AND ms_ser.kSprache = :langID
                       AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
                    INNER JOIN tmerkmalwertsprache tmws_ser ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                       AND tmws_ser.kSprache = :langID
                     WHERE a.nIstVater = 0
                       AND tmws_ser.cWert <> ''
                       {$frag}
                       {$likeSql}
                  ORDER BY tmws_ser.cWert ASC
                     LIMIT 120";
                try {
                    $rows = $this->db->getObjects($sql, $params);
                } catch (\Throwable $e) {
                    return [];
                }
                $out = [];
                if (\is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!isset($row->cWert) || $row->cWert === '') {
                            continue;
                        }
                        $out[] = ['value' => \trim((string)$row->cWert)];
                    }
                }

                // Cache write (best-effort)
                if ($cacheKey !== '' && $out !== []) {
                    try {
                        $this->db->executeQueryPrepared(
                            "INSERT INTO twomosi_step4_cache (cKey, dUpdated, cPayload)
                             VALUES (:k, NOW(), :p)
                             ON DUPLICATE KEY UPDATE dUpdated = NOW(), cPayload = VALUES(cPayload)",
                            ['k' => $cacheKey, 'p' => \json_encode($out)]
                        );
                    } catch (\Throwable $e) {}
                }
    
                return $out;
            }
    
            if ($field === 'hersteller') {
                $langID = $this->langID > 0 ? $this->langID : 1;

                // 1) Snapshot tablosundan oku (term varsa atla — live search)
                if ($term === '' && $serieFilter === '') {
                    try {
                        $snapRows = $this->db->getObjects(
                            'SELECT cHersteller FROM twomosi_fk_opt_pkw_hersteller
                              WHERE kSprache = :lid ORDER BY cHersteller ASC',
                            ['lid' => $langID]
                        );
                        if (\is_array($snapRows) && \count($snapRows) > 0) {
                            $out = [];
                            foreach ($snapRows as $r) {
                                if (!empty($r->cHersteller)) {
                                    $out[] = ['value' => $r->cHersteller];
                                }
                            }
                            if ($out !== []) {
                                return $out;
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                // 2) Live DB sorgusu (snapshot boş / term var / serieFilter var)
                $params  = ['langID' => $langID];
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql = ' AND tmws_her.cWert LIKE :term';
                }
                $serieJoin = '';
                if ($serieFilter !== '') {
                    $params['serie'] = $serieFilter;
                    $serieJoin = "INNER JOIN tartikelmerkmal am_ser ON am_ser.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_ser ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
                    INNER JOIN tmerkmal m_ser ON m_ser.kMerkmal = mw_ser.kMerkmal
                    INNER JOIN tmerkmalsprache ms_ser ON ms_ser.kMerkmal = m_ser.kMerkmal
                       AND ms_ser.kSprache = :langID
                       AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
                    INNER JOIN tmerkmalwertsprache tmws_ser ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                       AND tmws_ser.kSprache = :langID
                       AND tmws_ser.cWert = :serie";
                }
                $ptypJoin = PkwSqlFragments::pkwProdukttypFilter('langID');
                try {
                    $rows = $this->db->getObjects(
                        "SELECT DISTINCT tmws_her.cWert
                           FROM tartikel a {$serieJoin}
                      {$ptypJoin}
                     INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                     INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                     INNER JOIN tmerkmal m_her ON m_her.kMerkmal = mw_her.kMerkmal
                     INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                        AND ms_her.kSprache = :langID AND ms_her.cName = 'Fahrzeug-Marke'
                     INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                        AND tmws_her.kSprache = :langID
                      WHERE a.nIstVater = 0 AND tmws_her.cWert <> ''
                        AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
                        {$frag} {$likeSql}
                   ORDER BY tmws_her.cWert ASC LIMIT 200",
                        $params
                    );
                } catch (\Throwable $e) {
                    return [];
                }
                $out = [];
                foreach ((array)$rows as $row) {
                    if (!empty($row->cWert)) {
                        $out[] = ['value' => $row->cWert];
                    }
                }
                return $out;
            }
    
            if ($field === 'modell') {
                if ($herstellerFilter === '') {
                    return [];
                }
                $langID = $this->langID > 0 ? $this->langID : 1;

                // 1) Snapshot tablosundan oku
                if ($term === '' && $serieFilter === '') {
                    try {
                        $snapRows = $this->db->getObjects(
                            'SELECT cModell FROM twomosi_fk_opt_pkw_modell
                              WHERE kSprache = :lid AND cHersteller = :her
                           ORDER BY cModell ASC',
                            ['lid' => $langID, 'her' => \trim($herstellerFilter)]
                        );
                        if (\is_array($snapRows) && \count($snapRows) > 0) {
                            $out = [];
                            foreach ($snapRows as $r) {
                                if (!empty($r->cModell)) {
                                    $out[] = ['value' => $r->cModell];
                                }
                            }
                            if ($out !== []) {
                                return $out;
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                // 2) Live DB sorgusu
                $ptypJoin = PkwSqlFragments::pkwProdukttypFilter('langID');
                $params  = ['langID' => $langID, 'hersteller' => $herstellerFilter];
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql = ' AND tmws_mod.cWert LIKE :term';
                }
                $serieJoin = '';
                if ($serieFilter !== '') {
                    $params['serie'] = $serieFilter;
                    $serieJoin = "INNER JOIN tartikelmerkmal am_ser ON am_ser.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_ser ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
                    INNER JOIN tmerkmal m_ser ON m_ser.kMerkmal = mw_ser.kMerkmal
                    INNER JOIN tmerkmalsprache ms_ser ON ms_ser.kMerkmal = m_ser.kMerkmal
                       AND ms_ser.kSprache = :langID
                       AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
                    INNER JOIN tmerkmalwertsprache tmws_ser ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                       AND tmws_ser.kSprache = :langID
                       AND tmws_ser.cWert = :serie";
                }
                try {
                    $rows = $this->db->getObjects(
                        "SELECT DISTINCT tmws_mod.cWert
                           FROM tartikel a {$serieJoin}
                      {$ptypJoin}
                     INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                     INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                     INNER JOIN tmerkmal m_her ON m_her.kMerkmal = mw_her.kMerkmal
                     INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                        AND ms_her.kSprache = :langID AND ms_her.cName = 'Fahrzeug-Marke'
                     INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                        AND tmws_her.kSprache = :langID AND TRIM(tmws_her.cWert) = TRIM(:hersteller)
                     INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
                     INNER JOIN tmerkmalwert mw_mod ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                     INNER JOIN tmerkmal m_mod ON m_mod.kMerkmal = mw_mod.kMerkmal
                     INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = m_mod.kMerkmal
                        AND ms_mod.kSprache = :langID AND ms_mod.cName = 'Fahrzeug-Modell'
                     INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                        AND tmws_mod.kSprache = :langID
                      WHERE a.nIstVater = 0 AND tmws_mod.cWert <> ''
                        {$frag} {$likeSql}
                   ORDER BY tmws_mod.cWert ASC LIMIT 120",
                        $params
                    );
                } catch (\Throwable $e) {
                    return [];
                }
                $out = [];
                foreach ((array)$rows as $row) {
                    if (!empty($row->cWert)) {
                        $out[] = ['value' => $row->cWert];
                    }
                }
                return $out;
            }
    
            if ($field === 'baujahr') {
                if ($herstellerFilter === '' || $modellFilter === '') {
                    return [];
                }
                $langID = $this->langID > 0 ? $this->langID : 1;

                // 1) Snapshot tablosundan oku
                if ($term === '') {
                    try {
                        $snapRows = $this->db->getObjects(
                            'SELECT cBaujahr FROM twomosi_fk_opt_pkw_baujahr
                              WHERE kSprache = :lid
                                AND cHersteller = :her
                                AND cModell     = :mod
                           ORDER BY cBaujahr ASC',
                            ['lid' => $langID, 'her' => \trim($herstellerFilter), 'mod' => \trim($modellFilter)]
                        );
                        if (\is_array($snapRows) && \count($snapRows) > 0) {
                            $out = [];
                            foreach ($snapRows as $r) {
                                if (!empty($r->cBaujahr)) {
                                    $out[] = ['value' => $r->cBaujahr];
                                }
                            }
                            if ($out !== []) {
                                return $out;
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                // Cache: key by lang+segment+herst+modell+term.
                try {
                    $cacheKey = \md5('fk_opt_bj|' . $langID . '|' . ($vehicleSegment ?: 'pkw') . '|'
                        . \trim($herstellerFilter) . '|' . \trim($modellFilter) . '|' . \trim($term));
                    $cacheRow = $this->db->getSingleObject(
                        'SELECT cPayload, dUpdated FROM twomosi_step4_cache WHERE cKey = :k LIMIT 1',
                        ['k' => $cacheKey]
                    );
                    if ($cacheRow !== null) {
                        $age = \time() - (int)\strtotime((string)($cacheRow->dUpdated ?? ''));
                        // 6 hours TTL
                        if ($age >= 0 && $age < 21600) {
                            $decoded = \json_decode((string)($cacheRow->cPayload ?? ''), true);
                            if (\is_array($decoded)) {
                                $out = [];
                                foreach ($decoded as $v) {
                                    $s = \trim((string)($v['value'] ?? $v ?? ''));
                                    if ($s !== '') {
                                        $out[] = ['value' => $s];
                                    }
                                }
                                if ($out !== []) {
                                    return $out;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore cache read errors
                }

                $params = [
                    'langID'     => $langID,
                    'hersteller' => $herstellerFilter,
                    'modell'     => $modellFilter,
                ];
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql = ' AND tmws_bj.cWert LIKE :term';
                }
                $bjNameSql = PkwSqlFragments::merkmalNameBaujahr('ms_bj');

                $ptypJoin = PkwSqlFragments::pkwProdukttypFilter('langID');
                // Simple flat JOIN (no OR on kVaterArtikel).
                // articleNotAufbauart() frag excluded — PKW articles may not always have
                // Wohnmobil-Aufbauart but we already filter to 'pkw' segment via hersteller+modell match.
                $sql = "SELECT DISTINCT tmws_bj.cWert
                          FROM tartikel a
                               {$ptypJoin}
                    INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                    INNER JOIN tmerkmal m_her ON m_her.kMerkmal = mw_her.kMerkmal
                    INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                       AND ms_her.kSprache = :langID
                       AND ms_her.cName    = 'Fahrzeug-Marke'
                    INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                       AND tmws_her.kSprache = :langID
                       AND TRIM(tmws_her.cWert) = TRIM(:hersteller)
                    INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_mod ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                    INNER JOIN tmerkmal m_mod ON m_mod.kMerkmal = mw_mod.kMerkmal
                    INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = m_mod.kMerkmal
                       AND ms_mod.kSprache = :langID
                       AND ms_mod.cName    = 'Fahrzeug-Modell'
                    INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                       AND tmws_mod.kSprache = :langID
                       AND TRIM(tmws_mod.cWert) = TRIM(:modell)
                    INNER JOIN tartikelmerkmal am_bj ON am_bj.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_bj ON mw_bj.kMerkmalWert = am_bj.kMerkmalWert
                    INNER JOIN tmerkmal m_bj ON m_bj.kMerkmal = mw_bj.kMerkmal
                    INNER JOIN tmerkmalsprache ms_bj ON ms_bj.kMerkmal = m_bj.kMerkmal
                       AND ms_bj.kSprache = :langID
                       AND {$bjNameSql}
                    INNER JOIN tmerkmalwertsprache tmws_bj ON tmws_bj.kMerkmalWert = mw_bj.kMerkmalWert
                       AND tmws_bj.kSprache = :langID
                     WHERE a.nIstVater = 0
                       AND tmws_bj.cWert <> ''
                       {$likeSql}
                  ORDER BY tmws_bj.cWert ASC
                     LIMIT 120";
                try {
                    $rows = $this->db->getObjects($sql, $params);
                } catch (\Throwable $e) {
                    return [];
                }
                $out = [];
                if (\is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!isset($row->cWert) || $row->cWert === '') {
                            continue;
                        }
                        $out[] = ['value' => $row->cWert];
                    }
                }
                // Cache write (best-effort)
                try {
                    if (isset($cacheKey) && $cacheKey !== '') {
                        $payload = \json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if (\is_string($payload)) {
                            $this->db->executeQueryPrepared(
                                "INSERT INTO twomosi_step4_cache (cKey, dUpdated, cPayload)
                                 VALUES (:k, NOW(), :p)
                                 ON DUPLICATE KEY UPDATE dUpdated = NOW(), cPayload = VALUES(cPayload)",
                                ['k' => $cacheKey, 'p' => $payload]
                            );
                        }
                    }
                } catch (\Throwable $e) {
                }

                return $out;
            }
    
            if ($field === 'sitz') {
                if ($herstellerFilter === '' || $modellFilter === '') {
                    return [];
                }
                $params = [
                    'langID'     => $this->langID,
                    'hersteller' => $herstellerFilter,
                    'modell'     => $modellFilter,
                ];
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql = ' AND tmws_sitz.cWert LIKE :term';
                }

                $bjJoin = "";
                if ($baujahrFilter !== '') {
                    $params['baujahr'] = $baujahrFilter;
                    $bjNameSql = PkwSqlFragments::merkmalNameBaujahr('ms_bj');
                    $bjJoin = " INNER JOIN tartikelmerkmal am_bj ON am_bj.kArtikel = a.kArtikel
                                INNER JOIN tmerkmalwert mw_bj ON mw_bj.kMerkmalWert = am_bj.kMerkmalWert
                                INNER JOIN tmerkmalsprache ms_bj ON ms_bj.kMerkmal = mw_bj.kMerkmal
                                   AND ms_bj.kSprache = :langID AND {$bjNameSql}
                                INNER JOIN tmerkmalwertsprache tmws_bj ON tmws_bj.kMerkmalWert = mw_bj.kMerkmalWert
                                   AND tmws_bj.kSprache = :langID AND TRIM(tmws_bj.cWert) = TRIM(:baujahr)";
                }

                $ptypJoin = PkwSqlFragments::pkwProdukttypFilter('langID');
                $sql = "SELECT DISTINCT tmws_sitz.cWert
                          FROM tartikel a
                               {$bjJoin}
                               {$ptypJoin}
                    INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                    INNER JOIN tmerkmal m_her ON m_her.kMerkmal = mw_her.kMerkmal
                    INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                       AND ms_her.kSprache = :langID
                       AND ms_her.cName    = 'Fahrzeug-Marke'
                    INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                       AND tmws_her.kSprache = :langID
                       AND TRIM(tmws_her.cWert) = TRIM(:hersteller)
                    INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_mod ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                    INNER JOIN tmerkmal m_mod ON m_mod.kMerkmal = mw_mod.kMerkmal
                    INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = m_mod.kMerkmal
                       AND ms_mod.kSprache = :langID
                       AND ms_mod.cName    = 'Fahrzeug-Modell'
                    INNER JOIN tmerkmalwertsprache tmws_mod ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                       AND tmws_mod.kSprache = :langID
                       AND TRIM(tmws_mod.cWert) = TRIM(:modell)
                    INNER JOIN tartikelmerkmal am_sitz ON am_sitz.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mw_sitz ON mw_sitz.kMerkmalWert = am_sitz.kMerkmalWert
                    INNER JOIN tmerkmal m_sitz ON m_sitz.kMerkmal = mw_sitz.kMerkmal
                    INNER JOIN tmerkmalsprache ms_sitz ON ms_sitz.kMerkmal = m_sitz.kMerkmal
                       AND ms_sitz.kSprache = :langID
                       AND ms_sitz.cName    = 'kompatible Sitze'
                    INNER JOIN tmerkmalwertsprache tmws_sitz ON tmws_sitz.kMerkmalWert = mw_sitz.kMerkmalWert
                       AND tmws_sitz.kSprache = :langID
                     WHERE tmws_sitz.cWert <> ''
                       {$frag}
                       {$likeSql}
                  ORDER BY tmws_sitz.cWert ASC
                     LIMIT 80";
                try {
                    $rows = $this->db->getObjects($sql, $params);
                } catch (\Throwable $e) {
                    return [];
                }
                $out = [];
                if (\is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!isset($row->cWert) || $row->cWert === '') {
                            continue;
                        }
                        $out[] = ['value' => $row->cWert];
                    }
                }
    
                return $out;
            }
    
            return [];
        }
    
    
        /**
         * Wohnmobil-Konfigurator için Merkmal altyapısından distinct dropdown değerleri yükler.
         *
         * field parametresi:
         *  - typ        => Wohnmobil-Aufbauart
         *  - hersteller => Fahrzeug-Marke
         *  - basis      => Wohnmobil-Fahrgestell
         *  - modell     => Fahrzeug-Modell
         *  - sitz       => kompatible Sitze
         *  - serie      => Serie / kompatible Serien
         *
         * Sonuç yapısı: [['value' => 'Kastenwagen'], ['value' => 'Fiat Ducato 250'], ...]
         */
        public function loadMerkmalOptions(
            string $field,
            string $term = '',
            string $typFilter = '',
            string $herstellerFilter = '',
            string $basisFilter = '',
            string $modellFilter = '',
            string $baujahrFilter = '',
            bool $fkCustomModell = false,
            string $vehicleSegment = '',
            string $serieFilter = ''
        ): array
        {
            if ($field === '') {
                return [];
            }

            $serieFilter = \trim($serieFilter);
            $memoKey = \md5(\serialize([
                $this->langID,
                $field,
                $term,
                $typFilter,
                $herstellerFilter,
                $basisFilter,
                $modellFilter,
                $baujahrFilter,
                $fkCustomModell,
                $vehicleSegment,
                $serieFilter,
            ]));
            if (\array_key_exists($memoKey, self::$merkmalOptionsMemo)) {
                return self::$merkmalOptionsMemo[$memoKey];
            }
            self::$merkmalOptionsMemo[$memoKey] = CharacteristicOptionsGuard::rememberByKey(
                'fk_mopt_' . $memoKey,
                function () use (
                    $field,
                    $term,
                    $typFilter,
                    $herstellerFilter,
                    $basisFilter,
                    $modellFilter,
                    $baujahrFilter,
                    $fkCustomModell,
                    $vehicleSegment,
                    $serieFilter
                ): array {
                    return $this->loadMerkmalOptionsCompute(
                        $field,
                        $term,
                        $typFilter,
                        $herstellerFilter,
                        $basisFilter,
                        $modellFilter,
                        $baujahrFilter,
                        $fkCustomModell,
                        $vehicleSegment,
                        $serieFilter
                    );
                }
            );
            return self::$merkmalOptionsMemo[$memoKey];
        }

        /**
         * @return array<int,bool>
         */
        private function loadArticleIdsForMerkmalValue(string $merkmalName, string $value, int $langID): array
        {
            $rows = $this->db->getObjects(
                "SELECT DISTINCT am.kArtikel
                   FROM tmerkmalsprache ms
             INNER JOIN tmerkmalwert mw ON mw.kMerkmal = ms.kMerkmal
             INNER JOIN tmerkmalwertsprache ws
                     ON ws.kMerkmalWert = mw.kMerkmalWert
                    AND ws.kSprache = :langID
             INNER JOIN tartikelmerkmal am ON am.kMerkmalWert = mw.kMerkmalWert
                  WHERE ms.kSprache = :langID
                    AND ms.cName = :merkmalName
                    AND TRIM(ws.cWert) = :val",
                ['langID' => $langID, 'merkmalName' => $merkmalName, 'val' => $value]
            );
            $ids = [];
            foreach ((array)$rows as $row) {
                $ids[(int)$row->kArtikel] = true;
            }

            return $ids;
        }

        /**
         * Sitz + Serie wurden zuerst gewählt: nur Fahrzeugwerte derselben
         * tatsächlich kompatiblen Artikelkombination zurückgeben.
         *
         * @return array<int, array{value:string}>
         */
        public function loadWmVehicleOptionsForSeatSeries(
            string $field,
            string $sitz,
            string $serie,
            string $typ = '',
            string $hersteller = '',
            string $basis = '',
            string $modell = ''
        ): array {
            $targetNames = [
                'typ'        => 'Wohnmobil-Aufbauart',
                'hersteller' => 'Fahrzeug-Marke',
                'basis'      => 'Wohnmobil-Fahrgestell',
                'modell'     => 'Fahrzeug-Modell',
            ];
            if (!isset($targetNames[$field]) || \trim($sitz) === '' || \trim($serie) === '') {
                return [];
            }

            $langID = $this->langID > 0 ? $this->langID : 1;
            $sitz   = \trim($sitz);
            $serie  = \trim($serie);

            try {
                $sitzIds = $this->loadArticleIdsForMerkmalValue('kompatible Sitze', $sitz, $langID);
                if ($sitzIds === []) {
                    return [];
                }
                $serieIds = $this->loadArticleIdsForMerkmalValue('Serie', $serie, $langID);
                foreach ((array)$this->db->getObjects(
                    "SELECT DISTINCT ka.kArtikel
                       FROM tkategorieartikel ka
                 INNER JOIN tkategorie k ON k.kKategorie = ka.kKategorie
                  LEFT JOIN tkategoriesprache ks
                         ON ks.kKategorie = k.kKategorie
                        AND ks.kSprache = :langID
                      WHERE COALESCE(NULLIF(ks.cName, ''), k.cName) = :serie",
                    ['langID' => $langID, 'serie' => $serie]
                ) as $row) {
                    $serieIds[(int)$row->kArtikel] = true;
                }
                if ($serieIds === []) {
                    return [];
                }

                $sitzList = \implode(',', \array_keys($sitzIds));
                $candidates = [];
                foreach ((array)$this->db->getObjects(
                    "SELECT a.kArtikel, a.kVaterArtikel
                       FROM tartikel a
                      WHERE a.nIstVater = 0
                        AND (a.kArtikel IN ({$sitzList}) OR a.kVaterArtikel IN ({$sitzList}))"
                ) as $row) {
                    $id     = (int)$row->kArtikel;
                    $parent = (int)$row->kVaterArtikel;
                    if (isset($serieIds[$id]) || ($parent > 0 && isset($serieIds[$parent]))) {
                        $candidates[$id] = $id;
                    }
                }
                if ($candidates === []) {
                    return [];
                }

                $idList = \implode(',', $candidates);
                $segmentJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                $parents = [];
                $allowed = [];
                foreach ((array)$this->db->getObjects(
                    "SELECT DISTINCT a.kArtikel, a.kVaterArtikel
                       FROM tartikel a
                       {$segmentJoin}
                      WHERE a.kArtikel IN ({$idList})",
                    ['langID' => $langID]
                ) as $row) {
                    $id = (int)$row->kArtikel;
                    $allowed[$id] = (int)$row->kVaterArtikel;
                    if ((int)$row->kVaterArtikel > 0) {
                        $parents[(int)$row->kVaterArtikel] = (int)$row->kVaterArtikel;
                    }
                }
                if ($allowed === []) {
                    return [];
                }

                $merkmalNames = [
                    'typ'        => 'Wohnmobil-Aufbauart',
                    'hersteller' => 'Fahrzeug-Marke',
                    'basis'      => 'Wohnmobil-Fahrgestell',
                    'modell'     => 'Fahrzeug-Modell',
                ];
                $nameToKey = \array_flip($merkmalNames);
                $valueIds  = \implode(',', \array_unique(\array_merge(\array_keys($allowed), \array_values($parents))));
                $values    = [];
                foreach ((array)$this->db->getObjects(
                    "SELECT am.kArtikel, ms.cName, TRIM(ws.cWert) AS val
                       FROM tartikelmerkmal am
                 INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = am.kMerkmalWert
                 INNER JOIN tmerkmalsprache ms
                         ON ms.kMerkmal = mw.kMerkmal
                        AND ms.kSprache = :langID
                        AND ms.cName IN ('Wohnmobil-Aufbauart', 'Fahrzeug-Marke', 'Wohnmobil-Fahrgestell', 'Fahrzeug-Modell')
                 INNER JOIN tmerkmalwertsprache ws
                         ON ws.kMerkmalWert = am.kMerkmalWert
                        AND ws.kSprache = :langID
                      WHERE am.kArtikel IN ({$valueIds})",
                    ['langID' => $langID]
                ) as $row) {
                    $key = $nameToKey[(string)$row->cName] ?? '';
                    $val = (string)$row->val;
                    if ($key !== '' && $val !== '') {
                        $values[(int)$row->kArtikel][$key][$val] = true;
                    }
                }

                $wanted = [
                    'typ'        => \trim($typ),
                    'hersteller' => \trim($hersteller),
                    'basis'      => \trim($basis),
                    'modell'     => \trim($modell),
                ];
                $rows = [];
                foreach ($allowed as $id => $parent) {
                    $own = $values[$id] ?? [];
                    $inherited = $parent > 0 ? ($values[$parent] ?? []) : [];
                    $match = true;
                    foreach ($wanted as $key => $value) {
                        if ($key === $field || $value === '') {
                            continue;
                        }
                        if (!isset($own[$key][$value]) && !isset($inherited[$key][$value])) {
                            $match = false;
                            break;
                        }
                    }
                    if (!$match) {
                        continue;
                    }
                    foreach (\array_keys(($own[$field] ?? []) + ($inherited[$field] ?? [])) as $val) {
                        $rows[$val] = (object)['val' => (string)$val];
                    }
                }
                \ksort($rows, \SORT_NATURAL | \SORT_FLAG_CASE);
                $rows = \array_slice(\array_values($rows), 0, 200);
            } catch (\Throwable $e) {
                return [];
            }

            $out = [];
            foreach ((array)$rows as $row) {
                $value = \trim((string)($row->val ?? ''));
                if ($value !== '') {
                    $out[$value] = ['value' => $value];
                }
            }

            return \array_values($out);
        }

        /**
         * @return list<array<string, mixed>>|list<string>
         */
        private function loadMerkmalOptionsCompute(
            string $field,
            string $term,
            string $typFilter,
            string $herstellerFilter,
            string $basisFilter,
            string $modellFilter,
            string $baujahrFilter,
            bool $fkCustomModell,
            string $vehicleSegment,
            string $serieFilter
        ): array
        {
    
            if ($vehicleSegment === 'pkw') {
                return $this->loadPkwMerkmalOptions($field, $term, $herstellerFilter, $modellFilter, $baujahrFilter, $serieFilter, $vehicleSegment);
            }
    
            // Wohnmobil: Serien nur aus Artikeln mit Aufbauart-Merkmal (nicht globale Merkmalliste).
            if ($field === 'serie') {
                $params = ['langID' => $this->langID];
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql        = ' AND tmws_ser.cWert LIKE :term';
                }
                $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                $sql = "SELECT DISTINCT tmws_ser.cWert
                          FROM tartikel a
                        {$ptypJoin}
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tartikelmerkmal am_ser
                                ON am_ser.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_ser
                                ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
                        INNER JOIN tmerkmal m_ser
                                ON m_ser.kMerkmal = mw_ser.kMerkmal
                        INNER JOIN tmerkmalsprache ms_ser
                                ON ms_ser.kMerkmal = m_ser.kMerkmal
                               AND ms_ser.kSprache = :langID
                               AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
                        INNER JOIN tmerkmalwertsprache tmws_ser
                                ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                               AND tmws_ser.kSprache     = :langID
                         WHERE tmws_ser.cWert <> ''"
                       . $likeSql .
                       ' ORDER BY tmws_ser.cWert ASC
                         LIMIT 120';
                try {
                    $rows = $this->db->getObjects($sql, $params);
                } catch (\Throwable $e) {
                    $rows = [];
                }
                $out = [];
                if (\is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!isset($row->cWert) || $row->cWert === '') {
                            continue;
                        }
                        $out[] = ['value' => $row->cWert];
                    }
                }
    
                return $out;
            }
    
            // Fast path: use snapshot tables when available (no JOIN wait for users).
            $snap = null;
            if ($serieFilter === '' || !\in_array($field, ['hersteller', 'modell'], true)) {
                $snap = $this->tryLoadOptionsFromSnapshot($field,
                    $term,
                    $typFilter,
                    $herstellerFilter,
                    $basisFilter
                );
            }
            if (\is_array($snap) && \count($snap) > 0) {
                return $snap;
            }
    
            // Hersteller: Seçili Fahrzeugtyp varsa, onu da filtreye dahil et (JOIN ile),
            // yoksa sadece Merkmal tablosundan distinct liste döndür.
            if ($field === 'hersteller') {
                $params = ['langID' => $this->langID];
                $like   = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $like           = ' AND tmws_her.cWert LIKE :term';
                }
                $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
    
                // CardProduct / Serie zuerst: Marken nur für Artikel mit gewählter Serie (ohne Typ).
                if ($serieFilter !== '' && $typFilter === '') {
                    $params['serie'] = $serieFilter;
                    $sql = "SELECT DISTINCT tmws_her.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tartikelmerkmal am_ser
                                ON am_ser.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_ser
                                ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
                        INNER JOIN tmerkmal m_ser
                                ON m_ser.kMerkmal = mw_ser.kMerkmal
                        INNER JOIN tmerkmalsprache ms_ser
                                ON ms_ser.kMerkmal = m_ser.kMerkmal
                               AND ms_ser.kSprache = :langID
                               AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
                        INNER JOIN tmerkmalwertsprache tmws_ser
                                ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                               AND tmws_ser.kSprache     = :langID
                               AND tmws_ser.cWert        = :serie
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                         WHERE tmws_her.cWert <> ''
                           AND tmws_her.cWert NOT REGEXP '^[0-9]+$'"
                           . $like .
                           ' ORDER BY tmws_her.cWert ASC
                             LIMIT 200';
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        return [];
                    }
                    $out = [];
                    if (\is_array($rows)) {
                        foreach ($rows as $row) {
                            if (!isset($row->cWert) || $row->cWert === '') {
                                continue;
                            }
                            $out[] = ['value' => $row->cWert];
                        }
                    }
    
                    return $out;
                }
    
                // Seçili Fahrzeugtyp varsa, sadece o typ'e sahip araçların markalarını getir
                if ($typFilter !== '') {
                    $params['typ'] = $typFilter;
    
                    $sql = "SELECT DISTINCT tmws_her.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        -- Fahrzeugtyp (Wohnmobil-Aufbauart)
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tmerkmalwertsprache tmws_typ
                                ON tmws_typ.kMerkmalWert = mw_typ.kMerkmalWert
                               AND tmws_typ.kSprache     = :langID
                               AND tmws_typ.cWert        = :typ
                        -- Hersteller (Fahrzeug-Marke)
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                             WHERE tmws_her.cWert <> ''
                               AND tmws_her.cWert NOT REGEXP '^[0-9]+$'"
                           . $like .
                           ' ORDER BY tmws_her.cWert ASC
                             LIMIT 200';
                } else {
                    // Typ seçilmemişse bile: sadece Wohnmobil Produkttyp + Aufbauart olan ürünlerden markaları getir
                    $sql = "SELECT DISTINCT tmws_her.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                             WHERE tmws_her.cWert <> ''
                               AND tmws_her.cWert NOT REGEXP '^[0-9]+$'"
                          . $like .
                          ' ORDER BY tmws_her.cWert ASC
                             LIMIT 200';
                }
    
                try {
                    $rows = $this->db->getObjects($sql, $params);
                } catch (\Throwable $e) {
                    return [];
                }
    
                $out = [];
                if (\is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!isset($row->cWert) || $row->cWert === '') {
                            continue;
                        }
                        $out[] = ['value' => $row->cWert];
                    }
                }
    
                return $out;
            }
    
            // Basisfahrzeug: Seçili Typ + Hersteller kombinasyonuna göre Fahrgestell değerleri
            if ($field === 'basis') {
                if ($herstellerFilter === '' || $typFilter === '') {
                    // Filtreler eksikse, aşağıdaki generic Merkmal listesini kullan
                    $names = ['Wohnmobil-Fahrgestell'];
                } else {
                    $params = [
                        'langID'     => $this->langID,
                        'typ'        => $typFilter,
                        'hersteller' => $herstellerFilter,
                    ];
                    $likeSql = '';
                    if ($term !== '') {
                        $params['term'] = '%' . $term . '%';
                        $likeSql        = ' AND tmws_basis.cWert LIKE :term';
                    }
                    $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
    
                    $sql = "SELECT DISTINCT tmws_basis.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        -- Fahrzeugtyp (Wohnmobil-Aufbauart)
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tmerkmalwertsprache tmws_typ
                                ON tmws_typ.kMerkmalWert = mw_typ.kMerkmalWert
                               AND tmws_typ.kSprache     = :langID
                               AND tmws_typ.cWert        = :typ
                        -- Hersteller (Fahrzeug-Marke)
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                               AND tmws_her.cWert        = :hersteller
                        -- Basisfahrzeug (Wohnmobil-Fahrgestell)
                        INNER JOIN tartikelmerkmal am_basis
                                ON am_basis.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_basis
                                ON mw_basis.kMerkmalWert = am_basis.kMerkmalWert
                        INNER JOIN tmerkmal m_basis
                                ON m_basis.kMerkmal = mw_basis.kMerkmal
                        INNER JOIN tmerkmalsprache ms_basis
                                ON ms_basis.kMerkmal = m_basis.kMerkmal
                               AND ms_basis.kSprache = :langID
                               AND ms_basis.cName    = 'Wohnmobil-Fahrgestell'
                        INNER JOIN tmerkmalwertsprache tmws_basis
                                ON tmws_basis.kMerkmalWert = mw_basis.kMerkmalWert
                               AND tmws_basis.kSprache     = :langID
                             WHERE tmws_basis.cWert <> ''"
                           . $likeSql .
                           ' ORDER BY tmws_basis.cWert ASC
                             LIMIT 80';
    
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        // Özel sorgu hata verirse generic fallback'e düş
                        $rows = [];
                    }
    
                    if (\is_array($rows) && \count($rows) > 0) {
                        $out = [];
                        foreach ($rows as $row) {
                            if (!isset($row->cWert) || $row->cWert === '') {
                                continue;
                            }
                            $out[] = ['value' => $row->cWert];
                        }
    
                        return $out;
                    }
                    // Hiç sonuç yoksa, aşağıdaki generic Merkmal listelemesine düş
                }
            }
    
            // Modell: Typ + Hersteller; Basis seçilmişse sonucu ayrıca daralt.
            if ($field === 'modell') {
                // Serie → Marke → Modell (ohne Typ/Basis): Modell aus Artikeln mit Serie + Marke.
                if ($serieFilter !== '' && $herstellerFilter !== '' && $typFilter === '' && $basisFilter === '') {
                    $params = [
                        'langID'     => $this->langID,
                        'serie'      => $serieFilter,
                        'hersteller' => $herstellerFilter,
                    ];
                    $likeSql = '';
                    if ($term !== '') {
                        $params['term'] = '%' . $term . '%';
                        $likeSql        = ' AND tmws_mod.cWert LIKE :term';
                    }
                    $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                    $sql = "SELECT DISTINCT tmws_mod.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tartikelmerkmal am_ser
                                ON am_ser.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_ser
                                ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
                        INNER JOIN tmerkmal m_ser
                                ON m_ser.kMerkmal = mw_ser.kMerkmal
                        INNER JOIN tmerkmalsprache ms_ser
                                ON ms_ser.kMerkmal = m_ser.kMerkmal
                               AND ms_ser.kSprache = :langID
                               AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
                        INNER JOIN tmerkmalwertsprache tmws_ser
                                ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                               AND tmws_ser.kSprache     = :langID
                               AND tmws_ser.cWert        = :serie
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                               AND tmws_her.cWert        = :hersteller
                        INNER JOIN tartikelmerkmal am_mod
                                ON am_mod.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_mod
                                ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                        INNER JOIN tmerkmal m_mod
                                ON m_mod.kMerkmal = mw_mod.kMerkmal
                        INNER JOIN tmerkmalsprache ms_mod
                                ON ms_mod.kMerkmal = m_mod.kMerkmal
                               AND ms_mod.kSprache = :langID
                               AND ms_mod.cName    = 'Fahrzeug-Modell'
                        INNER JOIN tmerkmalwertsprache tmws_mod
                                ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                               AND tmws_mod.kSprache     = :langID
                         WHERE tmws_mod.cWert <> ''"
                           . $likeSql .
                           ' ORDER BY tmws_mod.cWert ASC
                             LIMIT 80';
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        return [];
                    }
                    $out = [];
                    if (\is_array($rows)) {
                        foreach ($rows as $row) {
                            if (!isset($row->cWert) || $row->cWert === '') {
                                continue;
                            }
                            $out[] = ['value' => $row->cWert];
                        }
                    }
    
                    return $out;
                }
    
                if ($herstellerFilter === '' || $typFilter === '') {
                    $names = ['Fahrzeug-Modell'];
                } else {
                    $params = [
                        'langID'     => $this->langID,
                        'typ'        => $typFilter,
                        'hersteller' => $herstellerFilter,
                    ];
                    $basisJoin = '';
                    if ($basisFilter !== '') {
                        $params['basis'] = $basisFilter;
                        $basisJoin = "
                        INNER JOIN tartikelmerkmal am_basis
                                ON am_basis.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_basis
                                ON mw_basis.kMerkmalWert = am_basis.kMerkmalWert
                        INNER JOIN tmerkmal m_basis
                                ON m_basis.kMerkmal = mw_basis.kMerkmal
                        INNER JOIN tmerkmalsprache ms_basis
                                ON ms_basis.kMerkmal = m_basis.kMerkmal
                               AND ms_basis.kSprache = :langID
                               AND ms_basis.cName    = 'Wohnmobil-Fahrgestell'
                        INNER JOIN tmerkmalwertsprache tmws_basis
                                ON tmws_basis.kMerkmalWert = mw_basis.kMerkmalWert
                               AND tmws_basis.kSprache     = :langID
                               AND tmws_basis.cWert        = :basis";
                    }
                    $likeSql = '';
                    if ($term !== '') {
                        $params['term'] = '%' . $term . '%';
                        $likeSql        = ' AND tmws_mod.cWert LIKE :term';
                    }
    
                    $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                    $sql = "SELECT DISTINCT tmws_mod.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        -- Fahrzeugtyp (Wohnmobil-Aufbauart)
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tmerkmalwertsprache tmws_typ
                                ON tmws_typ.kMerkmalWert = mw_typ.kMerkmalWert
                               AND tmws_typ.kSprache     = :langID
                               AND tmws_typ.cWert        = :typ
                        -- Hersteller (Fahrzeug-Marke)
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                               AND tmws_her.cWert        = :hersteller
                        {$basisJoin}
                        -- Modell (Fahrzeug-Modell)
                        INNER JOIN tartikelmerkmal am_mod
                                ON am_mod.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_mod
                                ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                        INNER JOIN tmerkmal m_mod
                                ON m_mod.kMerkmal = mw_mod.kMerkmal
                        INNER JOIN tmerkmalsprache ms_mod
                                ON ms_mod.kMerkmal = m_mod.kMerkmal
                               AND ms_mod.kSprache = :langID
                               AND ms_mod.cName    = 'Fahrzeug-Modell'
                        INNER JOIN tmerkmalwertsprache tmws_mod
                                ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                               AND tmws_mod.kSprache     = :langID
                             WHERE tmws_mod.cWert <> ''"
                           . $likeSql .
                           ' ORDER BY tmws_mod.cWert ASC
                             LIMIT 80';
    
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        return [];
                    }
    
                    $out = [];
                    if (\is_array($rows)) {
                        foreach ($rows as $row) {
                            if (!isset($row->cWert) || $row->cWert === '') {
                                continue;
                            }
                            $out[] = ['value' => $row->cWert];
                        }
                    }
    
                    return $out;
                }
            }
    
            // Sitz: Seçili Typ + Hersteller + Basis + Modell kombinasyonuna göre "kompatible Sitze"
            if ($field === 'sitz') {
                // Freitext-Modell: kein exakter Fahrzeug-Modell-Merkmal-Treffer — Sitze nur über Typ+Marke+Fahrgestell
                if ($fkCustomModell && $typFilter !== '' && $herstellerFilter !== '' && $basisFilter !== '') {
                    $params = [
                        'langID'     => $this->langID,
                        'typ'        => $typFilter,
                        'hersteller' => $herstellerFilter,
                        'basis'      => $basisFilter,
                    ];
                    $likeSql = '';
                    if ($term !== '') {
                        $params['term'] = '%' . $term . '%';
                        $likeSql        = ' AND tmws_sitz.cWert LIKE :term';
                    }
    
                    $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                    $sql = "SELECT DISTINCT tmws_sitz.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tmerkmalwertsprache tmws_typ
                                ON tmws_typ.kMerkmalWert = mw_typ.kMerkmalWert
                               AND tmws_typ.kSprache     = :langID
                               AND tmws_typ.cWert        = :typ
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                               AND tmws_her.cWert        = :hersteller
                        INNER JOIN tartikelmerkmal am_basis
                                ON am_basis.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_basis
                                ON mw_basis.kMerkmalWert = am_basis.kMerkmalWert
                        INNER JOIN tmerkmal m_basis
                                ON m_basis.kMerkmal = mw_basis.kMerkmal
                        INNER JOIN tmerkmalsprache ms_basis
                                ON ms_basis.kMerkmal = m_basis.kMerkmal
                               AND ms_basis.kSprache = :langID
                               AND ms_basis.cName    = 'Wohnmobil-Fahrgestell'
                        INNER JOIN tmerkmalwertsprache tmws_basis
                                ON tmws_basis.kMerkmalWert = mw_basis.kMerkmalWert
                               AND tmws_basis.kSprache     = :langID
                               AND tmws_basis.cWert        = :basis
                        INNER JOIN tartikelmerkmal am_sitz
                                ON am_sitz.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_sitz
                                ON mw_sitz.kMerkmalWert = am_sitz.kMerkmalWert
                        INNER JOIN tmerkmal m_sitz
                                ON m_sitz.kMerkmal = mw_sitz.kMerkmal
                        INNER JOIN tmerkmalsprache ms_sitz
                                ON ms_sitz.kMerkmal = m_sitz.kMerkmal
                               AND ms_sitz.kSprache = :langID
                               AND ms_sitz.cName    = 'kompatible Sitze'
                        INNER JOIN tmerkmalwertsprache tmws_sitz
                                ON tmws_sitz.kMerkmalWert = mw_sitz.kMerkmalWert
                               AND tmws_sitz.kSprache     = :langID
                             WHERE tmws_sitz.cWert <> ''"
                           . $likeSql .
                           ' ORDER BY tmws_sitz.cWert ASC
                             LIMIT 80';
    
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        $rows = [];
                    }
    
                    if (\is_array($rows) && \count($rows) > 0) {
                        $out = [];
                        foreach ($rows as $row) {
                            if (!isset($row->cWert) || $row->cWert === '') {
                                continue;
                            }
                            $out[] = ['value' => $row->cWert];
                        }
    
                        return $out;
                    }
                }
    
                if ($herstellerFilter === '' || $typFilter === '' || $basisFilter === '' || $modellFilter === '') {
                    $names = ['kompatible Sitze'];
                } else {
                    $params = [
                        'langID'     => $this->langID,
                        'typ'        => $typFilter,
                        'hersteller' => $herstellerFilter,
                        'basis'      => $basisFilter,
                        'modell'     => $modellFilter,
                    ];
                    $likeSql = '';
                    if ($term !== '') {
                        $params['term'] = '%' . $term . '%';
                        $likeSql        = ' AND tmws_sitz.cWert LIKE :term';
                    }

                    $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                    $sql = "SELECT DISTINCT tmws_sitz.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        -- Fahrzeugtyp (Wohnmobil-Aufbauart)
                        INNER JOIN tartikelmerkmal am_typ
                                ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ
                                ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ
                                ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ
                                ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tmerkmalwertsprache tmws_typ
                                ON tmws_typ.kMerkmalWert = mw_typ.kMerkmalWert
                               AND tmws_typ.kSprache     = :langID
                               AND tmws_typ.cWert        = :typ
                        -- Hersteller (Fahrzeug-Marke)
                        INNER JOIN tartikelmerkmal am_her
                                ON am_her.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_her
                                ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                        INNER JOIN tmerkmal m_her
                                ON m_her.kMerkmal = mw_her.kMerkmal
                        INNER JOIN tmerkmalsprache ms_her
                                ON ms_her.kMerkmal = m_her.kMerkmal
                               AND ms_her.kSprache = :langID
                               AND ms_her.cName    = 'Fahrzeug-Marke'
                        INNER JOIN tmerkmalwertsprache tmws_her
                                ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                               AND tmws_her.kSprache     = :langID
                               AND tmws_her.cWert        = :hersteller
                        -- Basisfahrzeug (Wohnmobil-Fahrgestell)
                        INNER JOIN tartikelmerkmal am_basis
                                ON am_basis.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_basis
                                ON mw_basis.kMerkmalWert = am_basis.kMerkmalWert
                        INNER JOIN tmerkmal m_basis
                                ON m_basis.kMerkmal = mw_basis.kMerkmal
                        INNER JOIN tmerkmalsprache ms_basis
                                ON ms_basis.kMerkmal = m_basis.kMerkmal
                               AND ms_basis.kSprache = :langID
                               AND ms_basis.cName    = 'Wohnmobil-Fahrgestell'
                        INNER JOIN tmerkmalwertsprache tmws_basis
                                ON tmws_basis.kMerkmalWert = mw_basis.kMerkmalWert
                               AND tmws_basis.kSprache     = :langID
                               AND tmws_basis.cWert        = :basis
                        -- Modell (Fahrzeug-Modell)
                        INNER JOIN tartikelmerkmal am_mod
                                ON am_mod.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_mod
                                ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
                        INNER JOIN tmerkmal m_mod
                                ON m_mod.kMerkmal = mw_mod.kMerkmal
                        INNER JOIN tmerkmalsprache ms_mod
                                ON ms_mod.kMerkmal = m_mod.kMerkmal
                               AND ms_mod.kSprache = :langID
                               AND ms_mod.cName    = 'Fahrzeug-Modell'
                        INNER JOIN tmerkmalwertsprache tmws_mod
                                ON tmws_mod.kMerkmalWert = mw_mod.kMerkmalWert
                               AND tmws_mod.kSprache     = :langID
                               AND tmws_mod.cWert        = :modell
                        -- Sitz (kompatible Sitze)
                        INNER JOIN tartikelmerkmal am_sitz
                                ON am_sitz.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_sitz
                                ON mw_sitz.kMerkmalWert = am_sitz.kMerkmalWert
                        INNER JOIN tmerkmal m_sitz
                                ON m_sitz.kMerkmal = mw_sitz.kMerkmal
                        INNER JOIN tmerkmalsprache ms_sitz
                                ON ms_sitz.kMerkmal = m_sitz.kMerkmal
                               AND ms_sitz.kSprache = :langID
                               AND ms_sitz.cName    = 'kompatible Sitze'
                        INNER JOIN tmerkmalwertsprache tmws_sitz
                                ON tmws_sitz.kMerkmalWert = mw_sitz.kMerkmalWert
                               AND tmws_sitz.kSprache     = :langID
                             WHERE tmws_sitz.cWert <> ''"
                            . $likeSql .
                            ' ORDER BY tmws_sitz.cWert ASC
                              LIMIT 80';
    
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        // Özel sorgu hata verirse, generic merkmal fallback'ine düş
                        $rows = [];
                    }
    
                    if (\is_array($rows) && \count($rows) > 0) {
                        $out = [];
                        foreach ($rows as $row) {
                            if (!isset($row->cWert) || $row->cWert === '') {
                                continue;
                            }
                            $out[] = ['value' => $row->cWert];
                        }
    
                        return $out;
                    }
                    // Hiç sonuc yoksa, aşağıdaki generic merkmal listelemesine düş
                }
            }
    
            // Diğer tüm alanlar için Merkmal altyapısını kullan
            switch ($field) {
                case 'typ':
                    // Typ (Aufbauart): nur aus Artikeln mit WM-Produkttyp, damit Listen segmentrein bleiben.
                    $params = ['langID' => $this->langID];
                    $likeSql = '';
                    if ($term !== '') {
                        $params['term'] = '%' . $term . '%';
                        $likeSql        = ' AND tmws_typ.cWert LIKE :term';
                    }
                    $ptypJoin = PkwSqlFragments::wmProdukttypFilter('langID');
                    $sql = "SELECT DISTINCT tmws_typ.cWert
                              FROM tartikel a
                        {$ptypJoin}
                        INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
                        INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                               AND ms_typ.kSprache = :langID
                               AND ms_typ.cName    = 'Wohnmobil-Aufbauart'
                        INNER JOIN tmerkmalwertsprache tmws_typ ON tmws_typ.kMerkmalWert = mw_typ.kMerkmalWert
                               AND tmws_typ.kSprache = :langID
                             WHERE a.nIstVater = 0
                               AND TRIM(tmws_typ.cWert) <> ''{$likeSql}
                          ORDER BY tmws_typ.cWert ASC
                             LIMIT 120";
                    try {
                        $rows = $this->db->getObjects($sql, $params);
                    } catch (\Throwable $e) {
                        return [];
                    }
                    $out = [];
                    if (\is_array($rows)) {
                        foreach ($rows as $row) {
                            $v = \trim((string)($row->cWert ?? ''));
                            if ($v !== '') {
                                $out[] = ['value' => $v];
                            }
                        }
                    }
                    return $out;
                case 'sitz':
                    $names = ['kompatible Sitze'];
                    break;
                default:
                    return [];
            }
    
            // 1. Tercihen Merkmal adına göre filtrele (varsa)
            $rows = [];
            try {
                $inPlaceholders = [];
                $params         = [];
                foreach ($names as $idx => $n) {
                    $key              = 'name' . $idx;
                    $inPlaceholders[] = ':' . $key;
                    $params[$key]     = $n;
                }
    
                $likeSql = '';
                if ($term !== '') {
                    $params['term'] = '%' . $term . '%';
                    $likeSql        = ' AND tmws.cWert LIKE :term';
                }
    
                $params['langID'] = $this->langID;
    
                $sql = "SELECT DISTINCT tmws.cWert
                          FROM tmerkmal m
                    INNER JOIN tmerkmalsprache ms
                            ON ms.kMerkmal = m.kMerkmal
                           AND ms.kSprache = :langID
                           AND ms.cName IN (" . \implode(',', $inPlaceholders) . ")
                    INNER JOIN tmerkmalwert mw
                            ON mw.kMerkmal = m.kMerkmal
                    INNER JOIN tmerkmalwertsprache tmws
                            ON tmws.kMerkmalWert = mw.kMerkmalWert
                           AND tmws.kSprache = :langID
                         WHERE tmws.cWert <> ''
                               {$likeSql}
                      ORDER BY tmws.cWert ASC
                         LIMIT 60";
    
                $rows = $this->db->getObjects($sql, $params);
            } catch (\Throwable $e) {
                $rows = [];
            }
    
            // 2. Her ihtimale karşı: üstte hiç satır dönmezse, tüm Merkmal değerlerinden al (fallback)
            if (!\is_array($rows) || \count($rows) === 0) {
                try {
                    $params2 = ['langID' => $this->langID];
                    $likeSql2 = '';
                    if ($term !== '') {
                        $params2['term'] = '%' . $term . '%';
                        $likeSql2        = ' AND tmws.cWert LIKE :term';
                    }
                    $sql2 = 'SELECT DISTINCT tmws.cWert
                               FROM tmerkmalwertsprache tmws
                              WHERE tmws.kSprache = :langID
                                    ' . $likeSql2 . '
                           ORDER BY tmws.cWert ASC
                              LIMIT 100';
                    $rows = $this->db->getObjects($sql2, $params2);
                } catch (\Throwable $e2) {
                    $rows = [];
                }
            }
    
            $out = [];
            if (\is_array($rows)) {
                foreach ($rows as $row) {
                    if (!isset($row->cWert)) {
                        continue;
                    }
                    $out[] = [
                        'value' => $row->cWert,
                    ];
                }
            }
    
            return $out;
        }
    
    
        /**
         * Seçilen string değerleri, tam Merkmal değerleriyle (cWert + cSeo) eşleştir.
         * LiveSearchController::findExactMerkmalValues mantığının sadeleştirilmiş hali.
         *
         * @param int         $langID
         * @param string[]    $values
         * @return array<int,array{cWert:string,cSeo:string}>
         */
        public function findExactMerkmalValues(array $values): array
        {
            $matched = [];
            foreach ($values as $phrase) {
                if ($phrase === '') {
                    continue;
                }
                $row = $this->db->getSingleObject(
                    "SELECT tmws.cWert,
                            seo.cSeo
                       FROM tmerkmalwertsprache tmws
                  LEFT JOIN tseo seo
                         ON seo.cKey     = 'kMerkmalWert'
                        AND seo.kKey     = tmws.kMerkmalWert
                        AND seo.kSprache = :langID
                      WHERE tmws.kSprache = :langID
                        AND tmws.cWert    = :phrase
                      LIMIT 1",
                    ['langID' => $this->langID, 'phrase' => $phrase]
                );
    
                if ($row) {
                    $matched[] = [
                        'cWert' => $row->cWert,
                        'cSeo'  => $row->cSeo ?? '',
                    ];
                }
            }
    
            return $matched;
        }
    
    
        /**
         * Seçilen değerleri, ilgili Merkmal adı(ları) ile kısıtlayarak eşleştir.
         * Böylece örn. "9" gibi değerler yanlışlıkla başka bir Merkmal (örn. Baujahr) ile eşleşmez.
         *
         * @param int         $langID
         * @param array<int,array{value:string,names:string[]}> $selections
         * @return array<int,array{cWert:string,cSeo:string}>
         */
        public function findExactMerkmalValuesForNames(array $selections): array
        {
            $matched = [];
    
            foreach ($selections as $sel) {
                $phrase = \trim($sel['value'] ?? '');
                $names  = $sel['names'] ?? [];
                if ($phrase === '' || !\is_array($names) || \count($names) === 0) {
                    continue;
                }
    
                $placeholders = [];
                $params = [
                    'langID'  => $this->langID,
                    'phrase'  => $phrase,
                ];
                foreach (\array_values($names) as $i => $n) {
                    $k = 'n' . $i;
                    $placeholders[] = ':' . $k;
                    $params[$k] = $n;
                }
    
                $row = $this->db->getSingleObject(
                    "SELECT tmws.cWert,
                            seo.cSeo
                       FROM tmerkmalwertsprache tmws
                 INNER JOIN tmerkmalwert mw
                         ON mw.kMerkmalWert = tmws.kMerkmalWert
                 INNER JOIN tmerkmal m
                         ON m.kMerkmal = mw.kMerkmal
                 INNER JOIN tmerkmalsprache ms
                         ON ms.kMerkmal = m.kMerkmal
                        AND ms.kSprache = :langID
                  LEFT JOIN tseo seo
                         ON seo.cKey     = 'kMerkmalWert'
                        AND seo.kKey     = tmws.kMerkmalWert
                        AND seo.kSprache = :langID
                      WHERE tmws.kSprache = :langID
                        AND tmws.cWert    = :phrase
                        AND ms.cName IN (" . \implode(',', $placeholders) . ")
                      LIMIT 1",
                    $params
                );
    
                if ($row) {
                    $matched[] = [
                        'cWert' => $row->cWert,
                        'cSeo'  => $row->cSeo ?? '',
                    ];
                }
            }
    
            return $matched;
        }
    
    
        /**
         * Eşleşen Merkmal değerlerine göre Serie sonuçları + filtreli URL üret.
         * LiveSearchController::findSeriesForValues fonksiyonuna yakındır.
         *
         * @param int         $langID
         * @param array<int,array{cWert:string,cSeo:string}> $matchedValues
         * @param string                                      $serieMerkmalName ms.cName (z. B. Serie oder Familie-Serie)
         * @return array<int,array<string,string>>
         */
        public function findSeriesForValues(
            array $matchedValues,
            string $serieMerkmalName = 'Serie',
            array $selectionQuery = []
        ): array {
            $allowed = ['Serie', 'Familie-Serie'];
            if (!\in_array($serieMerkmalName, $allowed, true)) {
                $serieMerkmalName = 'Serie';
            }

            // PKW-Standardfall zuerst aus dem Snapshot bedienen. Familie-Serie
            // (Freitext-Modell) und Wohnmobil laufen weiter über die Live-Abfrage.
            if ($serieMerkmalName === 'Serie'
                && \strtolower(\trim((string)($selectionQuery['segment'] ?? ''))) === 'pkw'
            ) {
                $fromSnapshot = $this->findPkwSeriesFromSnapshot($matchedValues, $selectionQuery);
                if ($fromSnapshot !== null && $fromSnapshot !== []) {
                    return $fromSnapshot;
                }
            }
    
            $joins      = '';
            $childJoins = '';
            $params     = ['langID' => $this->langID, 'serieMerkmalName' => $serieMerkmalName];
    
            foreach ($matchedValues as $i => $mv) {
                $tamAlias  = 'tam' . $i;
                $tmwsAlias = 'tmws' . $i;
                $paramKey  = 'q' . $i;
    
                $valueJoin = " INNER JOIN tmerkmalwertsprache {$tmwsAlias}"
                        . "    ON {$tmwsAlias}.kMerkmalWert = {$tamAlias}.kMerkmalWert"
                        . "   AND {$tmwsAlias}.kSprache     = :langID"
                        . "   AND {$tmwsAlias}.cWert         = :{$paramKey}";
                $joins .= " INNER JOIN tartikelmerkmal {$tamAlias}"
                        . "    ON ({$tamAlias}.kArtikel = a.kArtikel"
                        . "        OR (a.kVaterArtikel > 0 AND {$tamAlias}.kArtikel = a.kVaterArtikel))"
                        . $valueJoin;
                $childJoins .= " INNER JOIN tartikelmerkmal {$tamAlias}"
                        . "    ON {$tamAlias}.kArtikel = a.kArtikel"
                        . $valueJoin;
    
                $params[$paramKey] = $mv['cWert'];
            }
    
            $segment = \strtolower(\trim((string)($selectionQuery['segment'] ?? '')));
            $segJoin = '';
            if ($segment === 'pkw') {
                $segJoin = PkwSqlFragments::pkwProdukttypFilter('langID') . PkwSqlFragments::articleNotAufbauart();
            } else {
                $segJoin = PkwSqlFragments::wmProdukttypFilter('langID');
            }

            $sql = "SELECT DISTINCT serieWert.cWert AS serie,
                           serieSeo.cSeo            AS serieSeo
                      FROM tartikel a
                      {$segJoin}
                      {$joins}
                INNER JOIN tartikelmerkmal tamSerie
                        ON (tamSerie.kArtikel = a.kArtikel
                            OR (a.kVaterArtikel > 0 AND tamSerie.kArtikel = a.kVaterArtikel))
                INNER JOIN tmerkmalwert mwSerie
                        ON mwSerie.kMerkmalWert = tamSerie.kMerkmalWert
                INNER JOIN tmerkmal mSerie
                        ON mSerie.kMerkmal = mwSerie.kMerkmal
                INNER JOIN tmerkmalsprache msSerie
                        ON msSerie.kMerkmal = mSerie.kMerkmal
                       AND msSerie.kSprache = :langID
                       AND msSerie.cName    = :serieMerkmalName
                INNER JOIN tmerkmalwertsprache serieWert
                        ON serieWert.kMerkmalWert = tamSerie.kMerkmalWert
                       AND serieWert.kSprache     = :langID
                 LEFT JOIN tseo serieSeo
                        ON serieSeo.cKey     = 'kMerkmalWert'
                       AND serieSeo.kKey     = tamSerie.kMerkmalWert
                       AND serieSeo.kSprache = :langID
                     WHERE a.nIstVater = 0
                  ORDER BY serieWert.cWert ASC
                     LIMIT 24";
    
            $rows = $this->db->getObjects($sql, $params);
            if ((!is_array($rows) || $rows === []) && $segment !== 'pkw') {
                $rows = $this->findWmSeriesCategoryRows($childJoins, $segJoin, $params);
            }
    
            return $this->buildSerieItems($rows, $matchedValues, $selectionQuery);
        }

        /**
         * Wohnmobil serisi çoğu üründe "Serie" merkmalı değil, kategori adıdır
         * (ör. PLKT-Serie). Merkmal sorgusu boşsa aynı seçime uyan yaprak
         * kategoriyi seri olarak döndür.
         *
         * @param array<string,mixed> $params
         * @return array<int,object>
         */
        private function findWmSeriesCategoryRows(string $joins, string $segJoin, array $params): array
        {
            $sql = "SELECT DISTINCT COALESCE(NULLIF(ks.cName, ''), NULLIF(c.cName, '')) AS serie,
                           seo.cSeo AS serieSeo
                      FROM tartikel a
                      {$segJoin}
                      {$joins}
                INNER JOIN tkategorieartikel kaSerie
                        ON kaSerie.kArtikel = a.kArtikel
                INNER JOIN tkategorie c
                        ON c.kKategorie = kaSerie.kKategorie
                 LEFT JOIN tkategoriesprache ks
                        ON ks.kKategorie = c.kKategorie
                       AND ks.kSprache = :langID
                 LEFT JOIN tseo seo
                        ON seo.cKey = 'kKategorie'
                       AND seo.kKey = c.kKategorie
                       AND seo.kSprache = :langID
                     WHERE a.nIstVater = 0
                       AND COALESCE(NULLIF(ks.cName, ''), c.cName) LIKE '%Serie%'
                  ORDER BY serie ASC
                     LIMIT 24";

            unset($params['serieMerkmalName']);

            try {
                $rows = $this->db->getObjects($sql, $params);
            } catch (\Throwable $e) {
                return [];
            }

            return is_array($rows) ? $rows : [];
        }


        /**
         * Serien-URLs (SEO-Pfad + Filtersegmente) aus Ergebniszeilen bauen.
         * Wird von der Live-Abfrage und vom PKW-Snapshot genutzt, damit beide
         * Wege identische URLs liefern.
         *
         * @param mixed                                      $rows Zeilen mit serie/serieSeo
         * @param array<int,array{cWert:string,cSeo:string}>  $matchedValues
         * @param array<string,string>                       $selectionQuery
         *
         * @return array<int,array{serie:string,url:string}>
         */
        private function buildSerieItems($rows, array $matchedValues, array $selectionQuery): array
        {
            $filterSlugs = [];
            foreach ($matchedValues as $mv) {
                if (!empty($mv['cSeo'])) {
                    $filterSlugs[] = $mv['cSeo'];
                }
            }
            $filterPath = !empty($filterSlugs) ? '__' . \implode('__', $filterSlugs) : '';

            $shopURL = Shop::getURL();
            $items   = [];

            if (\is_array($rows)) {
                foreach ($rows as $row) {
                    $serie = \trim((string)($row->serie ?? ''));
                    if ($serie === '') {
                        continue;
                    }
                    $serieSeo  = \trim((string)($row->serieSeo ?? ''));
                    $serieBase = $serieSeo !== '' ? '/' . $serieSeo : '/' . $serie . '-Serie';
                    $base      = $shopURL . $serieBase . $filterPath;

                    $items[] = [
                        'serie' => $serie,
                        'url'   => $this->appendKonfiguratorQueryParams($base, $selectionQuery),
                    ];
                }
            }

            return $items;
        }


        /**
         * PKW-Serien aus dem Snapshot (twomosi_fk_opt_pkw_serie) lesen, statt die
         * teure Live-Abfrage über tartikel/tartikelmerkmal auszuführen.
         *
         * null = kein Snapshot-Treffer; der Aufrufer nutzt dann die Live-Abfrage.
         *
         * @param array<int,array{cWert:string,cSeo:string}> $matchedValues
         * @param array<string,string>                       $selectionQuery
         *
         * @return array<int,array{serie:string,url:string}>|null
         */
        private function findPkwSeriesFromSnapshot(array $matchedValues, array $selectionQuery): ?array
        {
            $hersteller = \trim((string)($selectionQuery['hersteller'] ?? ''));
            $modell     = \trim((string)($selectionQuery['modell'] ?? ''));
            $baujahr    = \trim((string)($selectionQuery['baujahr'] ?? ''));
            if ($hersteller === '' || $modell === '' || $baujahr === '') {
                return null;
            }

            try {
                $rows = $this->db->getObjects(
                    "SELECT DISTINCT cSerie
                       FROM twomosi_fk_opt_pkw_serie
                      WHERE kSprache    = :lid
                        AND cHersteller = :hersteller
                        AND cModell     = :modell
                        AND cBaujahr    = :baujahr
                   ORDER BY cSerie ASC
                      LIMIT 24",
                    [
                        'lid'        => $this->langID > 0 ? $this->langID : 1,
                        'hersteller' => $hersteller,
                        'modell'     => $modell,
                        'baujahr'    => $baujahr,
                    ]
                );
            } catch (\Throwable $e) {
                return null;
            }
            if (!\is_array($rows) || $rows === []) {
                return null;
            }

            $serieValues = [];
            foreach ($rows as $row) {
                $serie = \trim((string)($row->cSerie ?? ''));
                if ($serie !== '') {
                    $serieValues[$serie] = $serie;
                }
            }
            if ($serieValues === []) {
                return null;
            }

            $seoMap       = $this->fetchSerieSeoSlugs(\array_values($serieValues));
            $snapshotRows = [];
            foreach ($serieValues as $serie) {
                $snapshotRows[] = (object)[
                    'serie'    => $serie,
                    'serieSeo' => $seoMap[$serie] ?? '',
                ];
            }

            return $this->buildSerieItems($snapshotRows, $matchedValues, $selectionQuery);
        }


        /**
         * SEO-Slugs der Serien-Merkmalwerte — ohne Artikel-Joins, daher günstig.
         *
         * @param array<int,string> $serieValues
         *
         * @return array<string,string> cWert => cSeo
         */
        private function fetchSerieSeoSlugs(array $serieValues): array
        {
            if ($serieValues === []) {
                return [];
            }

            $placeholders = [];
            $params       = ['langID' => $this->langID];
            foreach (\array_values($serieValues) as $i => $value) {
                $key            = 'sv' . $i;
                $placeholders[] = ':' . $key;
                $params[$key]   = $value;
            }

            try {
                $rows = $this->db->getObjects(
                    "SELECT tmws.cWert AS serie,
                            seo.cSeo   AS serieSeo
                       FROM tmerkmalwertsprache tmws
                 INNER JOIN tmerkmalwert mw
                         ON mw.kMerkmalWert = tmws.kMerkmalWert
                 INNER JOIN tmerkmal m
                         ON m.kMerkmal = mw.kMerkmal
                 INNER JOIN tmerkmalsprache ms
                         ON ms.kMerkmal = m.kMerkmal
                        AND ms.kSprache = :langID
                        AND ms.cName    = 'Serie'
                  LEFT JOIN tseo seo
                         ON seo.cKey     = 'kMerkmalWert'
                        AND seo.kKey     = tmws.kMerkmalWert
                        AND seo.kSprache = :langID
                      WHERE tmws.kSprache = :langID
                        AND tmws.cWert IN (" . \implode(',', $placeholders) . ")",
                    $params
                );
            } catch (\Throwable $e) {
                return [];
            }

            $map = [];
            if (\is_array($rows)) {
                foreach ($rows as $row) {
                    $serie = \trim((string)($row->serie ?? ''));
                    $seo   = \trim((string)($row->serieSeo ?? ''));
                    if ($serie === '' || $seo === '' || isset($map[$serie])) {
                        continue;
                    }
                    $map[$serie] = $seo;
                }
            }

            return $map;
        }
    
    
        /**
         * GET-Parameter an Serien-URL hängen, damit Listen-/Artikelseiten den Konfigurator-Titel rendern können.
         *
         * @param array<string,string> $raw
         */
        public function appendKonfiguratorQueryParams(string $url, array $raw): string
        {
            $keys  = ['segment', 'typ', 'hersteller', 'basis', 'modell', 'baujahr', 'sitz', 'serie'];
            $pairs = [];
            foreach ($keys as $k) {
                $v = isset($raw[$k]) ? \trim((string)$raw[$k]) : '';
                if ($v !== '') {
                    $pairs[$k] = $v;
                }
            }
            if ($pairs === []) {
                return $url;
            }
            $qs  = \http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
            $sep = \strpos($url, '?') !== false ? '&' : '?';
    
            return $url . $sep . $qs;
        }
    
    
        /**
         * Nach Cache-Load: gleiche Pfade, aktuelle Konfigurator-GET-Parameter (alte URLs ohne ?typ=… ersetzen).
         *
         * @param array<int, array<string, string>> $items
         * @param array<string, string>             $selectionQuery
         *
         * @return array<int, array<string, string>>
         */
        public function reapplyKonfiguratorQueryOnSeriesItems(array $items, array $selectionQuery): array
        {
            $out = [];
            foreach ($items as $it) {
                if (!\is_array($it)) {
                    continue;
                }
                $serie = isset($it['serie']) ? (string)$it['serie'] : '';
                $url   = isset($it['url']) ? (string)$it['url'] : '';
                if ($url === '') {
                    continue;
                }
                $qPos = \strpos($url, '?');
                $base = $qPos !== false ? \substr($url, 0, $qPos) : $url;
                $out[] = [
                    'serie' => $serie,
                    'url'   => $this->appendKonfiguratorQueryParams($base, $selectionQuery),
                ];
            }
    
            return $out;
        }
    
    
        /**
         * Alle Werte des Merkmals „Familie-Serie“ (Sprache), unabhängig von Fahrzeug-/Artikel-Filtern.
         *
         * @param array<string,string> $selectionQuery
         *
         * @return array<int, array{serie: string, url: string}>
         */
        public function findAllFamilieSerieItems(array $selectionQuery = []): array
        {
            $shopURL = Shop::getURL();
            $rows    = $this->fetchFamilieSerieMerkmalWerte();
            if ($rows === [] && $this->langID > 1) {
                $rows = $this->fetchFamilieSerieMerkmalWerte();
            }
    
            $items = [];
            if (!\is_array($rows)) {
                return [];
            }
    
            foreach ($rows as $row) {
                $serie = \trim((string)($row->serie ?? ''));
                if ($serie === '') {
                    continue;
                }
                $serieBase = !empty($row->serieSeo)
                    ? '/' . $row->serieSeo
                    : '/' . $serie . '-Serie';
                $base = $shopURL . $serieBase;
                $items[] = [
                    'serie' => $serie,
                    'url'   => $this->appendKonfiguratorQueryParams($base, $selectionQuery),
                ];
            }
    
            return $items;
        }
    
    
        /**
         * Merkmal-Bezeichnung im Shop kann leicht abweichen (Bindestrich, Leerzeichen, EN-Dash).
         *
         * @return array<int, object{serie: string, serieSeo?: string}>
         */
        public function fetchFamilieSerieMerkmalWerte(): array
        {
            $params = ['langID' => $this->langID];
            $sql    = "SELECT DISTINCT tmws.cWert AS serie,
                              seo.cSeo AS serieSeo
                         FROM tmerkmal m
                   INNER JOIN tmerkmalsprache ms
                           ON ms.kMerkmal = m.kMerkmal
                          AND ms.kSprache = :langID
                          AND (
                                ms.cName = 'Familie-Serie'
                             OR ms.cName = 'Familie Serie'
                             OR REPLACE(REPLACE(TRIM(ms.cName), ' ', '-'), '–', '-') = 'Familie-Serie'
                             OR (ms.cName LIKE '%Familie%' AND ms.cName LIKE '%Serie%')
                          )
                   INNER JOIN tmerkmalwert mw
                           ON mw.kMerkmal = m.kMerkmal
                   INNER JOIN tmerkmalwertsprache tmws
                           ON tmws.kMerkmalWert = mw.kMerkmalWert
                          AND tmws.kSprache     = :langID
                    LEFT JOIN tseo seo
                           ON seo.cKey     = 'kMerkmalWert'
                          AND seo.kKey     = tmws.kMerkmalWert
                          AND seo.kSprache = :langID
                        WHERE TRIM(tmws.cWert) <> ''
                     ORDER BY tmws.cWert ASC
                        LIMIT 500";
    
            $rows = $this->db->getObjects($sql, $params);
    
            return \is_array($rows) ? $rows : [];
        }
    
    
        /**
         * Admin Banner: Wohnmobil/PKW sekmeleri (twomosi_fk_banner).
         *
         * @return array{wm: bool, pkw: bool}
         */
        public function getKonfiguratorSegmentFlags(): array
        {
            $wm  = true;
            $pkw = true;
            try {
                $row = $this->db->getSingleObject(
                    'SELECT nSegmentWmActive, nSegmentPkwActive FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1'
                );
                if ($row !== null) {
                    $wm  = (int)($row->nSegmentWmActive ?? 1) === 1;
                    $pkw = (int)($row->nSegmentPkwActive ?? 1) === 1;
                }
            } catch (\Throwable $e) {
                return ['wm' => true, 'pkw' => true];
            }
            if (!$wm && !$pkw) {
                $wm = true;
            }
    
            return ['wm' => $wm, 'pkw' => $pkw];
        }
    
}
