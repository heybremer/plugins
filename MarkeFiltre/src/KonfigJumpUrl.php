<?php

declare(strict_types=1);

namespace Plugin\MarkeFiltre;

use JTL\DB\DbInterface;
use JTL\Shop;

/**
 * Wohnmobil: /{Marke}__{Produkttyp}__{Serie} (z. B. Adria__Wohnmobil-Schonbezuege-Fahrer-und-Beifahrer-Set__C).
 * PKW:       /{Marke}__{Serie}__{Produkttyp} (z. B. BMW__C__Fahrer-Beifahrer-Set-Schonbezuege).
 */
final class KonfigJumpUrl
{
    /** Wohnmobil: Produkttyp-Segment-Fallback (SEO). */
    private const FALLBACK_WM_PRODUKTYP_PATH = 'Wohnmobil-Schonbezuege-Fahrer-und-Beifahrer-Set';

    /** PKW: Produkttyp-Segment-Fallback (SEO). */
    private const FALLBACK_PKW_PRODUKTYP_PATH = 'Fahrer-Beifahrer-Set-Schonbezuege';

    /**
     * @return array{url:string,state:array<string,string>}|null
     */
    public static function forBrand(DbInterface $db, int $langID, bool $isPkw, string $brandCWert): ?array
    {
        $brandCWert = \trim($brandCWert);
        if ($brandCWert === '') {
            return null;
        }

        foreach (\array_unique([$langID, 1]) as $lid) {
            if ($lid <= 0) {
                continue;
            }
            $row = $isPkw
                ? self::fetchPkwTripleRow($db, $lid, $brandCWert)
                : self::fetchWohnmobilTripleRow($db, $lid, $brandCWert);
            if ($row !== null) {
                $built = self::buildMarkeProdukttypSerieUrl($db, $lid, $isPkw, $row);
                if ($built !== null) {
                    return $built;
                }
            }
        }

        return self::buildSyntheticTriple($db, $langID, $isPkw, $brandCWert);
    }

    /**
     * Kein passender Artikel in der DB: trotzdem kanonische 3-Segment-URL bauen,
     * damit der Klick nicht auf die reine Merkmalwert-Route (/Marke) fällt.
     *
     * @return array{url:string,state:array<string,string>}
     */
    private static function buildSyntheticTriple(DbInterface $db, int $langID, bool $isPkw, string $brandCWert): array
    {
        $lid = $langID > 0 ? $langID : 1;
        $her  = self::resolveBrandMerkmal($db, $lid, $brandCWert);
        $kHer = (int) ($her['kMerkmalWert'] ?? 0);
        $herCWert = \trim((string) ($her['cWert'] ?? $brandCWert));

        $markeSeg = $kHer > 0 ? BrandListLink::merkmalWertSeoSlug($db, $lid, $kHer) : '';
        if ($markeSeg === '') {
            $markeSeg = self::hyphenizeForPath($brandCWert);
        }

        $kPt = self::resolveProdukttypMerkmalWertId($db, $lid, $isPkw);
        $ptCWert = self::resolveProdukttypCWertForFallback($db, $lid, $isPkw);
        $ptFallback = $isPkw ? self::FALLBACK_PKW_PRODUKTYP_PATH : self::FALLBACK_WM_PRODUKTYP_PATH;
        if ($kPt > 0) {
            $ptSeg = BrandListLink::merkmalWertSeoSlug($db, $lid, $kPt);
            if ($ptSeg === '') {
                $ptSeg = self::hyphenizeForPath($ptCWert) ?: $ptFallback;
            }
        } else {
            $ptSeg = $ptFallback;
        }

        $kSerie = self::resolveSerieCMerkmalWertId($db, $lid, $isPkw, $brandCWert);
        if ($kSerie > 0) {
            $serieSeg = BrandListLink::merkmalWertSeoSlug($db, $lid, $kSerie);
        } else {
            $serieSeg = 'C';
        }
        if ($serieSeg === '') {
            $serieSeg = 'C';
        }

        $shop = \rtrim((string) Shop::getURL(), '/');
        $path  = $isPkw
            ? ($markeSeg . '__' . $serieSeg . '__' . $ptSeg)
            : ($markeSeg . '__' . $ptSeg . '__' . $serieSeg);
        $url = $shop . '/' . $path;

        $serieKey = 'C';
        if ($kSerie > 0) {
            $sr = self::resolveSerieCWertFromId($db, $lid, $kSerie);
            if ($sr !== '' && \preg_match('/^([A-Za-z0-9]+)/u', $sr, $m)) {
                $serieKey = \strtoupper($m[1]);
            }
        }

        $state = [
            'segment'    => $isPkw ? 'pkw' : 'wohnmobil',
            'typKey'     => $ptCWert,
            'typLabel'   => $ptCWert,
            'hersteller' => $herCWert,
            'basis'      => '',
            'modell'     => '',
            'serie'      => $serieKey,
        ];

        return ['url' => $url, 'state' => $state];
    }

    private static function resolveSerieCWertFromId(DbInterface $db, int $lid, int $kSerie): string
    {
        if ($kSerie <= 0) {
            return '';
        }
        $row = $db->getSingleObject(
            'SELECT cWert FROM tmerkmalwertsprache WHERE kMerkmalWert = :k AND kSprache = :lid LIMIT 1',
            ['k' => $kSerie, 'lid' => $lid]
        );

        return $row !== null ? \trim((string) ($row->cWert ?? '')) : '';
    }

    /**
     * @return array{kMerkmalWert:int,cWert:string}
     */
    private static function resolveBrandMerkmal(DbInterface $db, int $lid, string $brandCWert): array
    {
        $row = $db->getSingleObject(
            "SELECT mw.kMerkmalWert AS kMerkmalWert, tmws.cWert AS cWert
               FROM tmerkmalwertsprache tmws
         INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
         INNER JOIN tmerkmal m ON m.kMerkmal = mw.kMerkmal
         INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal
              AND ms.kSprache = :lid
              AND (ms.cName = 'Fahrzeug-Marke' OR ms.cName = 'Fahrzeug Marke')
              WHERE tmws.kSprache = :lid
                AND LOWER(TRIM(tmws.cWert)) = LOWER(TRIM(:brand))
              LIMIT 1",
            ['lid' => $lid, 'brand' => $brandCWert]
        );
        if ($row === null) {
            return ['kMerkmalWert' => 0, 'cWert' => $brandCWert];
        }

        return [
            'kMerkmalWert' => (int) ($row->kMerkmalWert ?? 0),
            'cWert'        => \trim((string) ($row->cWert ?? $brandCWert)),
        ];
    }

    private static function resolveProdukttypMerkmalWertId(DbInterface $db, int $lid, bool $isPkw): int
    {
        $needle = $isPkw ? self::FALLBACK_PKW_PRODUKTYP_PATH : self::FALLBACK_WM_PRODUKTYP_PATH;
        $row    = $db->getSingleObject(
            "SELECT tmws.kMerkmalWert AS kid
               FROM tmerkmalwertsprache tmws
         INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
         INNER JOIN tmerkmal m ON m.kMerkmal = mw.kMerkmal
         INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal
              AND ms.kSprache = :lid
              AND ms.cName IN ('Produkttyp','Produkt typ','Produkt-Typ')
         LEFT JOIN tseo seo ON seo.cKey = 'kMerkmalWert' AND seo.kKey = tmws.kMerkmalWert AND seo.kSprache = :lid
              WHERE tmws.kSprache = :lid
                AND (seo.cSeo = :needle OR tmws.cSeo = :needle2)
              LIMIT 1",
            ['lid' => $lid, 'needle' => $needle, 'needle2' => $needle]
        );

        return $row !== null ? (int) ($row->kid ?? 0) : 0;
    }

    private static function resolveSerieCMerkmalWertId(DbInterface $db, int $lid, bool $isPkw, string $brandCWert): int
    {
        $wmSql = $isPkw ? '' : "AND EXISTS (
            SELECT 1 FROM tartikelmerkmal amx
            INNER JOIN tmerkmalwert mwx ON mwx.kMerkmalWert = amx.kMerkmalWert
            INNER JOIN tmerkmal mx ON mx.kMerkmal = mwx.kMerkmal
            INNER JOIN tmerkmalsprache msx ON msx.kMerkmal = mx.kMerkmal
               AND msx.kSprache = :lid
               AND (msx.cName = 'Wohnmobil-Aufbauart' OR msx.cName = 'Wohnmobil Aufbauart' OR msx.cName LIKE '%Aufbauart%')
            WHERE amx.kArtikel = a.kArtikel
        )";
        $pkwSql = $isPkw ? "AND NOT EXISTS (
            SELECT 1 FROM tartikelmerkmal amx
            INNER JOIN tmerkmalwert mwx ON mwx.kMerkmalWert = amx.kMerkmalWert
            INNER JOIN tmerkmal mx ON mx.kMerkmal = mwx.kMerkmal
            INNER JOIN tmerkmalsprache msx ON msx.kMerkmal = mx.kMerkmal
               AND msx.kSprache = :lid
               AND (msx.cName IN ('Wohnmobil-Aufbauart','Wohnmobil Aufbauart') OR msx.cName LIKE '%Aufbauart%')
            WHERE amx.kArtikel = a.kArtikel
        )" : '';

        $sql = "SELECT mw_ser.kMerkmalWert AS kid
                  FROM tartikel a
            INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
            INNER JOIN tmerkmalwert mw_her ON mw_her.kMerkmalWert = am_her.kMerkmalWert
            INNER JOIN tmerkmal m_her ON m_her.kMerkmal = mw_her.kMerkmal
            INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
               AND ms_her.kSprache = :lid
               AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
            INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
               AND tmws_her.kSprache = :lid
               AND LOWER(TRIM(tmws_her.cWert)) = LOWER(TRIM(:brand))
            INNER JOIN tartikelmerkmal am_ser ON am_ser.kArtikel = a.kArtikel
            INNER JOIN tmerkmalwert mw_ser ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
            INNER JOIN tmerkmal m_ser ON m_ser.kMerkmal = mw_ser.kMerkmal
            INNER JOIN tmerkmalsprache ms_ser ON ms_ser.kMerkmal = m_ser.kMerkmal
               AND ms_ser.kSprache = :lid
               AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
            INNER JOIN tmerkmalwertsprache tmws_ser ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
               AND tmws_ser.kSprache = :lid
               AND TRIM(tmws_ser.cWert) = 'C'
                 WHERE a.nIstVater = 0
                   {$wmSql}
                   {$pkwSql}
              LIMIT 1";

        $row = $db->getSingleObject($sql, ['lid' => $lid, 'brand' => $brandCWert]);

        return $row !== null ? (int) ($row->kid ?? 0) : 0;
    }

    /**
     * @return object{kHer:int,herCWert:string,kPt:int,ptCWert:string,kSerie:int,serieCWert:string}|null
     */
    private static function fetchWohnmobilTripleRow(DbInterface $db, int $lid, string $brandCWert): ?object
    {
        return $db->getSingleObject(
            "SELECT
                    mw_her.kMerkmalWert  AS kHer,
                    tmws_her.cWert       AS herCWert,
                    COALESCE(mw_pt.kMerkmalWert, 0) AS kPt,
                    COALESCE(tmws_pt.cWert, '')    AS ptCWert,
                    mw_ser.kMerkmalWert  AS kSerie,
                    tmws_ser.cWert       AS serieCWert
               FROM tartikel a
         INNER JOIN tartikelmerkmal am_her
                 ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwert mw_her
                 ON mw_her.kMerkmalWert = am_her.kMerkmalWert
         INNER JOIN tmerkmal m_her
                 ON m_her.kMerkmal = mw_her.kMerkmal
         INNER JOIN tmerkmalsprache ms_her
                 ON ms_her.kMerkmal = m_her.kMerkmal
                AND ms_her.kSprache = :lid
                AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
         INNER JOIN tmerkmalwertsprache tmws_her
                 ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                AND tmws_her.kSprache     = :lid
                AND LOWER(TRIM(tmws_her.cWert)) = LOWER(TRIM(:brand))
         INNER JOIN tartikelmerkmal am_wm
                 ON am_wm.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwert mw_wm
                 ON mw_wm.kMerkmalWert = am_wm.kMerkmalWert
         INNER JOIN tmerkmal m_wm
                 ON m_wm.kMerkmal = mw_wm.kMerkmal
         INNER JOIN tmerkmalsprache ms_wm
                 ON ms_wm.kMerkmal = m_wm.kMerkmal
                AND ms_wm.kSprache = :lid
                AND (ms_wm.cName = 'Wohnmobil-Aufbauart' OR ms_wm.cName = 'Wohnmobil Aufbauart' OR ms_wm.cName LIKE '%Aufbauart%')
          LEFT JOIN tartikelmerkmal am_pt
                 ON am_pt.kArtikel = a.kArtikel
          LEFT JOIN tmerkmalwert mw_pt
                 ON mw_pt.kMerkmalWert = am_pt.kMerkmalWert
          LEFT JOIN tmerkmal m_pt
                 ON m_pt.kMerkmal = mw_pt.kMerkmal
          LEFT JOIN tmerkmalsprache ms_pt
                 ON ms_pt.kMerkmal = m_pt.kMerkmal
                AND ms_pt.kSprache = :lid
                AND ms_pt.cName IN ('Produkttyp','Produkt typ','Produkt-Typ')
          LEFT JOIN tmerkmalwertsprache tmws_pt
                 ON tmws_pt.kMerkmalWert = mw_pt.kMerkmalWert
                AND tmws_pt.kSprache     = :lid
         INNER JOIN tartikelmerkmal am_ser
                 ON am_ser.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwert mw_ser
                 ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
         INNER JOIN tmerkmal m_ser
                 ON m_ser.kMerkmal = mw_ser.kMerkmal
         INNER JOIN tmerkmalsprache ms_ser
                 ON ms_ser.kMerkmal = m_ser.kMerkmal
                AND ms_ser.kSprache = :lid
                AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
         INNER JOIN tmerkmalwertsprache tmws_ser
                 ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                AND tmws_ser.kSprache     = :lid
              WHERE a.nIstVater = 0
           ORDER BY
                    CASE
                        WHEN TRIM(tmws_ser.cWert) = 'C' THEN 0
                        WHEN TRIM(tmws_ser.cWert) LIKE 'C %' OR TRIM(tmws_ser.cWert) LIKE 'C-%' THEN 1
                        ELSE 2
                    END,
                    tmws_ser.cWert ASC,
                    a.kArtikel ASC
              LIMIT 1",
            ['lid' => $lid, 'brand' => $brandCWert]
        );
    }

    /**
     * @return object{kHer:int,herCWert:string,kPt:int,ptCWert:string,kSerie:int,serieCWert:string}|null
     */
    private static function fetchPkwTripleRow(DbInterface $db, int $lid, string $brandCWert): ?object
    {
        return $db->getSingleObject(
            "SELECT
                    mw_her.kMerkmalWert  AS kHer,
                    tmws_her.cWert       AS herCWert,
                    COALESCE(mw_pt.kMerkmalWert, 0) AS kPt,
                    COALESCE(tmws_pt.cWert, '')    AS ptCWert,
                    mw_ser.kMerkmalWert  AS kSerie,
                    tmws_ser.cWert       AS serieCWert
               FROM tartikel a
         INNER JOIN tartikelmerkmal am_her
                 ON am_her.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwert mw_her
                 ON mw_her.kMerkmalWert = am_her.kMerkmalWert
         INNER JOIN tmerkmal m_her
                 ON m_her.kMerkmal = mw_her.kMerkmal
         INNER JOIN tmerkmalsprache ms_her
                 ON ms_her.kMerkmal = m_her.kMerkmal
                AND ms_her.kSprache = :lid
                AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
         INNER JOIN tmerkmalwertsprache tmws_her
                 ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                AND tmws_her.kSprache     = :lid
                AND LOWER(TRIM(tmws_her.cWert)) = LOWER(TRIM(:brand))
          LEFT JOIN tartikelmerkmal am_pt
                 ON am_pt.kArtikel = a.kArtikel
          LEFT JOIN tmerkmalwert mw_pt
                 ON mw_pt.kMerkmalWert = am_pt.kMerkmalWert
          LEFT JOIN tmerkmal m_pt
                 ON m_pt.kMerkmal = mw_pt.kMerkmal
          LEFT JOIN tmerkmalsprache ms_pt
                 ON ms_pt.kMerkmal = m_pt.kMerkmal
                AND ms_pt.kSprache = :lid
                AND ms_pt.cName IN ('Produkttyp','Produkt typ','Produkt-Typ')
          LEFT JOIN tmerkmalwertsprache tmws_pt
                 ON tmws_pt.kMerkmalWert = mw_pt.kMerkmalWert
                AND tmws_pt.kSprache     = :lid
         INNER JOIN tartikelmerkmal am_ser
                 ON am_ser.kArtikel = a.kArtikel
         INNER JOIN tmerkmalwert mw_ser
                 ON mw_ser.kMerkmalWert = am_ser.kMerkmalWert
         INNER JOIN tmerkmal m_ser
                 ON m_ser.kMerkmal = mw_ser.kMerkmal
         INNER JOIN tmerkmalsprache ms_ser
                 ON ms_ser.kMerkmal = m_ser.kMerkmal
                AND ms_ser.kSprache = :lid
                AND ms_ser.cName IN ('Serie','kompatible Serien','Familie-Serie')
         INNER JOIN tmerkmalwertsprache tmws_ser
                 ON tmws_ser.kMerkmalWert = mw_ser.kMerkmalWert
                AND tmws_ser.kSprache     = :lid
              WHERE a.nIstVater = 0
                AND NOT EXISTS (
                        SELECT 1
                          FROM tartikelmerkmal am_pkwx
                    INNER JOIN tmerkmalwert mw_pkwx ON mw_pkwx.kMerkmalWert = am_pkwx.kMerkmalWert
                    INNER JOIN tmerkmal m_pkwx ON m_pkwx.kMerkmal = mw_pkwx.kMerkmal
                    INNER JOIN tmerkmalsprache ms_pkwx ON ms_pkwx.kMerkmal = m_pkwx.kMerkmal
                       AND ms_pkwx.kSprache = :lid
                       AND (ms_pkwx.cName IN ('Wohnmobil-Aufbauart','Wohnmobil Aufbauart') OR ms_pkwx.cName LIKE '%Aufbauart%')
                         WHERE am_pkwx.kArtikel = a.kArtikel
                    )
           ORDER BY
                    CASE
                        WHEN TRIM(tmws_ser.cWert) = 'C' THEN 0
                        WHEN TRIM(tmws_ser.cWert) LIKE 'C %' OR TRIM(tmws_ser.cWert) LIKE 'C-%' THEN 1
                        ELSE 2
                    END,
                    tmws_ser.cWert ASC,
                    a.kArtikel ASC
              LIMIT 1",
            ['lid' => $lid, 'brand' => $brandCWert]
        );
    }

    /**
     * @param object{kHer:int,herCWert:string,kPt:int,ptCWert:string,kSerie:int,serieCWert:string} $row
     *
     * @return array{url:string,state:array<string,string>}|null
     */
    private static function buildMarkeProdukttypSerieUrl(DbInterface $db, int $lid, bool $isPkw, object $row): ?array
    {
        $kHer   = (int) ($row->kHer ?? 0);
        $kSerie = (int) ($row->kSerie ?? 0);
        if ($kHer <= 0 || $kSerie <= 0) {
            return null;
        }

        $herCWert   = \trim((string) ($row->herCWert ?? ''));
        $serieCWert = \trim((string) ($row->serieCWert ?? ''));
        $kPt        = (int) ($row->kPt ?? 0);
        $ptCWert    = \trim((string) ($row->ptCWert ?? ''));

        $markeSeg = BrandListLink::merkmalWertSeoSlug($db, $lid, $kHer);
        if ($markeSeg === '') {
            $markeSeg = self::hyphenizeForPath($herCWert);
        }
        if ($markeSeg === '') {
            return null;
        }

        $ptFallback = $isPkw ? self::FALLBACK_PKW_PRODUKTYP_PATH : self::FALLBACK_WM_PRODUKTYP_PATH;
        if ($kPt > 0) {
            $ptSeg = BrandListLink::merkmalWertSeoSlug($db, $lid, $kPt);
            if ($ptSeg === '') {
                $ptSeg = self::hyphenizeForPath($ptCWert);
            }
        } else {
            $ptSeg   = $ptFallback;
            $ptCWert = self::resolveProdukttypCWertForFallback($db, $lid, $isPkw) ?: $ptCWert;
        }
        if ($ptSeg === '') {
            return null;
        }

        $serieSeg = BrandListLink::merkmalWertSeoSlug($db, $lid, $kSerie);
        if ($serieSeg === '') {
            $serieSeg = self::hyphenizeForPath($serieCWert);
        }
        if ($serieSeg === '') {
            return null;
        }

        $path = $isPkw
            ? ($markeSeg . '__' . $serieSeg . '__' . $ptSeg)
            : ($markeSeg . '__' . $ptSeg . '__' . $serieSeg);
        $shop = \rtrim((string) Shop::getURL(), '/');
        $url  = $shop . '/' . $path;

        $serieKey = $serieCWert;
        if (\preg_match('/^([A-Za-z0-9]+)/u', $serieCWert, $m)) {
            $serieKey = \strtoupper($m[1]);
        }

        $state = [
            'segment'    => $isPkw ? 'pkw' : 'wohnmobil',
            'typKey'     => $ptCWert,
            'typLabel'   => $ptCWert,
            'hersteller' => $herCWert,
            'basis'      => '',
            'modell'     => '',
            'serie'      => $serieKey,
        ];

        return ['url' => $url, 'state' => $state];
    }

    private static function hyphenizeForPath(string $s): string
    {
        $s = \trim($s);
        if ($s === '') {
            return '';
        }
        $s = \preg_replace('/[\s\x{00A0}]+/u', '-', $s) ?? $s;
        $s = \preg_replace('/-+/u', '-', $s) ?? $s;

        return \trim($s, '-');
    }

    private static function resolveProdukttypCWertForFallback(DbInterface $db, int $lid, bool $isPkw): string
    {
        $needle = $isPkw ? self::FALLBACK_PKW_PRODUKTYP_PATH : self::FALLBACK_WM_PRODUKTYP_PATH;
        $row    = $db->getSingleObject(
            "SELECT tmws.cWert AS cWert
               FROM tmerkmalwertsprache tmws
         INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
         INNER JOIN tmerkmal m ON m.kMerkmal = mw.kMerkmal
         INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal
              AND ms.kSprache = :lid
              AND ms.cName IN ('Produkttyp','Produkt typ','Produkt-Typ')
         LEFT JOIN tseo seo ON seo.cKey = 'kMerkmalWert' AND seo.kKey = tmws.kMerkmalWert AND seo.kSprache = :lid
              WHERE tmws.kSprache = :lid
                AND (seo.cSeo = :needle OR tmws.cSeo = :needle2)
              LIMIT 1",
            ['lid' => $lid, 'needle' => $needle, 'needle2' => $needle]
        );
        if ($row !== null && \trim((string) ($row->cWert ?? '')) !== '') {
            return \trim((string) $row->cWert);
        }

        if ($isPkw) {
            $rowP = $db->getSingleObject(
                "SELECT tmws.cWert AS cWert
                   FROM tmerkmalwertsprache tmws
             INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
             INNER JOIN tmerkmal m ON m.kMerkmal = mw.kMerkmal
             INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal
                  AND ms.kSprache = :lid
                  AND ms.cName IN ('Produkttyp','Produkt typ','Produkt-Typ')
                  WHERE tmws.kSprache = :lid
                    AND (tmws.cWert LIKE :l1 AND tmws.cWert LIKE :l2)
                  LIMIT 1",
                ['lid' => $lid, 'l1' => '%Fahrer%', 'l2' => '%Beifahrer%']
            );
            if ($rowP !== null && \trim((string) ($rowP->cWert ?? '')) !== '') {
                return \trim((string) $rowP->cWert);
            }
        } else {
            $row2 = $db->getSingleObject(
                "SELECT tmws.cWert AS cWert
                   FROM tmerkmalwertsprache tmws
             INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
             INNER JOIN tmerkmal m ON m.kMerkmal = mw.kMerkmal
             INNER JOIN tmerkmalsprache ms ON ms.kMerkmal = m.kMerkmal
                  AND ms.kSprache = :lid
                  AND ms.cName IN ('Produkttyp','Produkt typ','Produkt-Typ')
                  WHERE tmws.kSprache = :lid
                    AND (tmws.cWert LIKE :like1 OR tmws.cWert LIKE :like2)
                  LIMIT 1",
                [
                    'lid'   => $lid,
                    'like1' => '%Schonbezug%',
                    'like2' => '%Schonbezüg%',
                ]
            );
            if ($row2 !== null && \trim((string) ($row2->cWert ?? '')) !== '') {
                return \trim((string) $row2->cWert);
            }
        }

        return '';
    }
}
