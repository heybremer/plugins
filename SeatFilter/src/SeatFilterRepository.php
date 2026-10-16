<?php

declare(strict_types=1);

namespace Plugin\SeatFilter;

use JTL\DB\DbInterface;
use JTL\Shop;

final class SeatFilterRepository
{
    private DbInterface $db;
    private int $langID;

    public function __construct(DbInterface $db, int $langID)
    {
        $this->db     = $db;
        $this->langID = $langID;
    }

    /**
     * Alle einzigartigen "kompatible Sitze"-Merkmal-Werte laden.
     * Kategorie 24 (Wohnmobil), Produkttyp = "Wohnmobil Schonbezüge Fahrer und Beifahrer Set".
     *
     * @return list<string>
     */
    public function loadAllSitzValues(): array
    {
        $sql = "SELECT DISTINCT tmws_sitz.cWert
                  FROM tartikel a
                " . $this->wmSegmentFilter() . "
                 INNER JOIN tartikelmerkmal tam_sitz ON tam_sitz.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_sitz ON mw_sitz.kMerkmalWert = tam_sitz.kMerkmalWert
                 INNER JOIN tmerkmal m_sitz ON m_sitz.kMerkmal = mw_sitz.kMerkmal
                 INNER JOIN tmerkmalsprache ms_sitz ON ms_sitz.kMerkmal = m_sitz.kMerkmal
                    AND ms_sitz.kSprache = :langID AND ms_sitz.cName = 'kompatible Sitze'
                 INNER JOIN tmerkmalwertsprache tmws_sitz ON tmws_sitz.kMerkmalWert = tam_sitz.kMerkmalWert
                    AND tmws_sitz.kSprache = :langID
                 WHERE a.nIstVater = 0
                   AND tmws_sitz.cWert <> ''
                 ORDER BY tmws_sitz.cWert ASC
                 LIMIT 60";

        $rows = $this->db->getObjects($sql, ['langID' => $this->langID]);
        $out  = [];
        if (\is_array($rows)) {
            foreach ($rows as $r) {
                $v = \trim((string)($r->cWert ?? ''));
                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }

        return $out;
    }

    /**
     * Serien laden, die zu einem bestimmten Sitz-Wert passen.
     * Sucht in den Merkmalen "Serie" und "Familie-Serie".
     *
     * @return list<array{serie:string, serieSeo:string}>
     */
    public function loadSeriesForSitz(string $sitzValue): array
    {
        $serieMerkmalNames = ['Serie', 'Familie-Serie'];
        $seen = [];
        $out  = [];

        foreach ($serieMerkmalNames as $merkmalName) {
            $sql = "SELECT DISTINCT serieWert.cWert AS serie,
                           COALESCE(serieSeo.cSeo, '') AS serieSeo
                      FROM tartikel a
                    " . $this->wmSegmentFilter() . "
                     INNER JOIN tartikelmerkmal tam_sitz ON tam_sitz.kArtikel = a.kArtikel
                     INNER JOIN tmerkmalwert mw_sitz ON mw_sitz.kMerkmalWert = tam_sitz.kMerkmalWert
                     INNER JOIN tmerkmal m_sitz ON m_sitz.kMerkmal = mw_sitz.kMerkmal
                     INNER JOIN tmerkmalsprache ms_sitz ON ms_sitz.kMerkmal = m_sitz.kMerkmal
                        AND ms_sitz.kSprache = :langID AND ms_sitz.cName = 'kompatible Sitze'
                     INNER JOIN tmerkmalwertsprache tmws_sitz ON tmws_sitz.kMerkmalWert = tam_sitz.kMerkmalWert
                        AND tmws_sitz.kSprache = :langID AND tmws_sitz.cWert = :sitz
                     INNER JOIN tartikelmerkmal tam_serie ON tam_serie.kArtikel = a.kArtikel
                     INNER JOIN tmerkmalwert mw_serie ON mw_serie.kMerkmalWert = tam_serie.kMerkmalWert
                     INNER JOIN tmerkmal m_serie ON m_serie.kMerkmal = mw_serie.kMerkmal
                     INNER JOIN tmerkmalsprache ms_serie ON ms_serie.kMerkmal = m_serie.kMerkmal
                        AND ms_serie.kSprache = :langID AND ms_serie.cName = :serieMerkmal
                     INNER JOIN tmerkmalwertsprache serieWert ON serieWert.kMerkmalWert = tam_serie.kMerkmalWert
                        AND serieWert.kSprache = :langID
                      LEFT JOIN tseo serieSeo
                        ON serieSeo.cKey = 'kMerkmalWert'
                       AND serieSeo.kKey = tam_serie.kMerkmalWert
                       AND serieSeo.kSprache = :langID
                     WHERE a.nIstVater = 0
                       AND serieWert.cWert <> ''
                     ORDER BY serieWert.cWert ASC
                     LIMIT 30";

            $rows = $this->db->getObjects($sql, [
                'langID'        => $this->langID,
                'sitz'          => $sitzValue,
                'serieMerkmal'  => $merkmalName,
            ]);
            if (\is_array($rows)) {
                foreach ($rows as $r) {
                    $code = (string)($r->serie ?? '');
                    $upper = \strtoupper($code);
                    if ($code !== '' && !isset($seen[$upper])) {
                        $seen[$upper] = true;
                        $out[] = [
                            'serie'    => $code,
                            'serieSeo' => (string)($r->serieSeo ?? ''),
                        ];
                    }
                }
            }

            if (!empty($out)) {
                break;
            }
        }

        \usort($out, static fn($a, $b) => \strcasecmp($a['serie'], $b['serie']));

        return $out;
    }

    /**
     * Artikel laden: Sitz-Merkmal + HAN-Präfix (z. B. Serie AH → cHAN AH10, AH9).
     * Fallback: cName/cSeo mit gleichem Präfix, danach Familie-Serie-Merkmal.
     *
     * @return list<array{kArtikel:int, cName:string, cSeo:string, cArtNr:string, cHAN:string, cBeschreibung:string, fPreis:float, cWaehrung:string, cBild:string, cUrl:string}>
     */
    public function loadArtikelForSitzAndSerie(string $sitzValue, string $serieValue): array
    {
        $serieValue = \trim($serieValue);
        if ($serieValue === '') {
            return [];
        }

        $shopUrl     = Shop::getURL();
        $hanPatterns = $this->buildHanMatchPatterns($serieValue);

        // 1) Merkmal Serie/Familie-Serie (wie auf AH-Serie-Listenseite)
        $results = $this->fetchArtikelRowsBySerieMerkmal($sitzValue, $serieValue);
        if ($results === []) {
            $results = $this->fetchArtikelRowsBySerieMerkmal($sitzValue, $serieValue, false);
        }
        // 2) HAN / Name (AH10) / cSeo
        if ($results === []) {
            $results = $this->fetchArtikelRowsByHan($sitzValue, $hanPatterns);
        }
        if ($results === []) {
            $results = $this->fetchArtikelRowsByHan($sitzValue, $hanPatterns, false);
        }

        return $this->mapArtikelRowsToPayload($results, $shopUrl, $hanPatterns['prefix']);
    }

    /**
     * @return array{like:string, regex:string, nameRegexParen:string, nameRegexSuffix:string, prefix:string}
     */
    private function buildHanMatchPatterns(string $serieValue): array
    {
        $prefix  = \strtoupper(\trim($serieValue));
        $escaped = \preg_quote($prefix, '/');

        return [
            'like'            => $prefix . '%',
            'regex'           => '^' . $escaped . '[0-9]*$',
            'nameRegexParen'  => '\\(' . $escaped . '[0-9]+\\)',
            'nameRegexSuffix' => '[[:space:]]' . $escaped . '[0-9]+$',
            'prefix'          => $prefix,
        ];
    }

    private function sitzJoinSql(): string
    {
        return " INNER JOIN tartikelmerkmal tam_sitz ON tam_sitz.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_sitz ON mw_sitz.kMerkmalWert = tam_sitz.kMerkmalWert
                 INNER JOIN tmerkmal m_sitz ON m_sitz.kMerkmal = mw_sitz.kMerkmal
                 INNER JOIN tmerkmalsprache ms_sitz ON ms_sitz.kMerkmal = m_sitz.kMerkmal
                    AND ms_sitz.kSprache = :langID AND ms_sitz.cName = 'kompatible Sitze'
                 INNER JOIN tmerkmalwertsprache tmws_sitz ON tmws_sitz.kMerkmalWert = tam_sitz.kMerkmalWert
                    AND tmws_sitz.kSprache = :langID AND tmws_sitz.cWert = :sitz";
    }

    private function serieJoinSql(): string
    {
        return " INNER JOIN tartikelmerkmal tam_serie ON tam_serie.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_serie ON mw_serie.kMerkmalWert = tam_serie.kMerkmalWert
                 INNER JOIN tmerkmal m_serie ON m_serie.kMerkmal = mw_serie.kMerkmal
                 INNER JOIN tmerkmalsprache ms_serie ON ms_serie.kMerkmal = m_serie.kMerkmal
                    AND ms_serie.kSprache = :langID AND ms_serie.cName = :serieMerkmal
                 INNER JOIN tmerkmalwertsprache tmws_serie ON tmws_serie.kMerkmalWert = tam_serie.kMerkmalWert
                    AND tmws_serie.kSprache = :langID AND tmws_serie.cWert = :serie";
    }

    private function artikelSelectSql(): string
    {
        return "SELECT a.kArtikel,
                       a.nIstVater,
                       COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                       COALESCE(a.cSeo, '') AS cSeo,
                       COALESCE(a.cArtNr, '') AS cArtNr,
                       COALESCE(a.cHAN, '') AS cHAN,
                       COALESCE(a.cBeschreibung, '') AS cBeschreibung,
                       COALESCE(tp.fVKNetto, 0) AS fVKNetto";
    }

    private function artikelFromJoinsSql(): string
    {
        return " LEFT JOIN (
                      SELECT s1.kArtikel, MIN(s1.cName) AS cName
                        FROM tartikelsprache s1
                       GROUP BY s1.kArtikel
                  ) ts ON ts.kArtikel = a.kArtikel
                  LEFT JOIN tpreise tp ON tp.kArtikel = a.kArtikel AND tp.kKundengruppe = 1";
    }

    /**
     * @param array{like:string, regex:string, nameRegexParen:string, nameRegexSuffix:string, prefix:string} $hanPatterns
     * @return list<object>
     */
    private function fetchArtikelRowsByHan(string $sitzValue, array $hanPatterns, bool $withSegment = true): array
    {
        $segmentJoin = $withSegment ? $this->wmSegmentFilter() : '';

        $sql = $this->artikelSelectSql() . "
                  FROM tartikel a
                {$segmentJoin}
                " . $this->sitzJoinSql() . "
                " . $this->artikelFromJoinsSql() . "
                 WHERE a.nIstVater = 0
                   AND (
                        (a.cHAN <> '' AND UPPER(TRIM(a.cHAN)) LIKE :hanLike)
                        OR (a.cHAN <> '' AND UPPER(TRIM(a.cHAN)) REGEXP :hanRegex)
                        OR UPPER(COALESCE(NULLIF(a.cName, ''), ts.cName, '')) REGEXP :nameRegexParen
                        OR UPPER(COALESCE(NULLIF(a.cName, ''), ts.cName, '')) REGEXP :nameRegexSuffix
                        OR UPPER(TRIM(a.cSeo)) REGEXP :hanRegex
                       )
                 ORDER BY
                    CASE
                        WHEN a.cHAN <> '' THEN UPPER(TRIM(a.cHAN))
                        ELSE UPPER(COALESCE(NULLIF(a.cName, ''), ts.cName, ''))
                    END ASC,
                    a.kArtikel DESC
                 LIMIT 80";

        $rows = $this->db->getObjects($sql, [
            'langID'          => $this->langID,
            'sitz'            => $sitzValue,
            'hanLike'         => $hanPatterns['like'],
            'hanRegex'        => $hanPatterns['regex'],
            'nameRegexParen'  => $hanPatterns['nameRegexParen'],
            'nameRegexSuffix' => $hanPatterns['nameRegexSuffix'],
        ]);

        if (!\is_array($rows) || $rows === []) {
            return [];
        }

        return $this->filterHanRowsBySeriePrefix($rows, $hanPatterns['prefix']);
    }

    /**
     * @param list<object> $rows
     * @return list<object>
     */
    private function filterHanRowsBySeriePrefix(array $rows, string $seriePrefix): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ($this->extractHanCodeFromRow($r, $seriePrefix) !== null) {
                $out[] = $r;
            }
        }

        return $out;
    }

    private function extractHanCodeFromRow(object $row, string $seriePrefix): ?string
    {
        $seriePrefix = \strtoupper(\trim($seriePrefix));
        $han         = \strtoupper(\trim((string)($row->cHAN ?? '')));
        if ($han !== '' && $this->hanMatchesSeriePrefix($han, $seriePrefix)) {
            return $han;
        }

        $name = (string)($row->cName ?? '');
        $patParen = '/\(' . \preg_quote($seriePrefix, '/') . '(\d+)\)/i';
        if ($name !== '' && \preg_match($patParen, $name, $m) === 1) {
            return $seriePrefix . $m[1];
        }

        $patSuffix = '/\b' . \preg_quote($seriePrefix, '/') . '(\d+)\s*$/i';
        if ($name !== '' && \preg_match($patSuffix, $name, $m) === 1) {
            return $seriePrefix . $m[1];
        }

        $seo = \strtoupper(\trim((string)($row->cSeo ?? '')));
        if ($seo !== '' && $this->hanMatchesSeriePrefix($seo, $seriePrefix)) {
            return $seo;
        }

        return null;
    }

    private function hanMatchesSeriePrefix(string $candidate, string $seriePrefix): bool
    {
        if ($candidate === $seriePrefix) {
            return true;
        }
        if (!\str_starts_with($candidate, $seriePrefix)) {
            return false;
        }
        $suffix = \substr($candidate, \strlen($seriePrefix));

        return $suffix === '' || \preg_match('/^[0-9]+$/', $suffix) === 1;
    }

    /**
     * @return list<object>
     */
    private function fetchArtikelRowsBySerieMerkmal(string $sitzValue, string $serieValue, bool $withSegment = true): array
    {
        $serieMerkmalNames = ['Serie', 'Familie-Serie', 'kompatible Serien'];
        $segmentJoin       = $withSegment ? $this->wmSegmentFilter() : '';

        foreach ($serieMerkmalNames as $merkmalName) {
            $sql = $this->artikelSelectSql() . "
                      FROM tartikel a
                    {$segmentJoin}
                    " . $this->sitzJoinSql() . "
                    " . $this->serieJoinSql() . "
                    " . $this->artikelFromJoinsSql() . "
                     WHERE a.nIstVater = 0
                     ORDER BY
                        CASE WHEN a.cHAN <> '' THEN UPPER(TRIM(a.cHAN)) ELSE UPPER(COALESCE(NULLIF(a.cName, ''), ts.cName, '')) END ASC,
                        a.kArtikel DESC
                     LIMIT 80";

            $rows = $this->db->getObjects($sql, [
                'langID'       => $this->langID,
                'sitz'         => $sitzValue,
                'serie'        => $serieValue,
                'serieMerkmal' => $merkmalName,
            ]);

            if (\is_array($rows) && $rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param list<object> $rows
     * @return list<array{kArtikel:int, cName:string, cSeo:string, cArtNr:string, cHAN:string, cBeschreibung:string, fPreis:float, cWaehrung:string, cBild:string, cUrl:string}>
     */
    private function mapArtikelRowsToPayload(array $rows, string $shopUrl, string $seriePrefix = ''): array
    {
        $seen    = [];
        $results = [];
        $seriePrefix = \strtoupper(\trim($seriePrefix));

        foreach ($rows as $r) {
            $kArtikel = (int)($r->kArtikel ?? 0);
            if ($kArtikel <= 0 || isset($seen[$kArtikel])) {
                continue;
            }
            $seen[$kArtikel] = true;

            $cName    = (string)($r->cName ?? '');
            $cSeo     = (string)($r->cSeo ?? '');
            $cArtNr   = (string)($r->cArtNr ?? '');
            $cHan     = (string)($r->cHAN ?? '');
            $cBeschr  = (string)($r->cBeschreibung ?? '');
            $fVKNetto = (float)($r->fVKNetto ?? 0);
            $brutto   = $fVKNetto > 0 ? \round($fVKNetto * 1.19, 2) : 0;

            if ($seriePrefix !== '') {
                $extracted = $this->extractHanCodeFromRow($r, $seriePrefix);
                if ($extracted !== null) {
                    $cHan = $extracted;
                }
            }

            $displayName = $cName !== '' ? $cName : $cHan;
            $imgUrl      = $this->buildArticleImageUrl($kArtikel, $cName, $shopUrl);
            $artikelUrl  = $cSeo !== '' ? ($shopUrl . '/' . $cSeo) : ($shopUrl . '/?a=' . $kArtikel);

            $results[] = [
                'kArtikel'      => $kArtikel,
                'cName'         => $displayName,
                'cSeo'          => $cSeo,
                'cArtNr'        => $cArtNr,
                'cHAN'          => $cHan,
                'cBeschreibung' => $this->truncateText($cBeschr, 120),
                'fPreis'        => $brutto,
                'cWaehrung'     => '€',
                'cBild'         => $imgUrl,
                'cUrl'          => $artikelUrl,
            ];
        }

        return $results;
    }

    private function loadVaterArtikelBasics(int $kArtikel): ?object
    {
        try {
            return $this->db->getSingleObject(
                "SELECT a.kArtikel,
                        COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName,
                        COALESCE(a.cSeo, '') AS cSeo,
                        COALESCE(a.cArtNr, '') AS cArtNr
                   FROM tartikel a
                   LEFT JOIN (
                       SELECT s1.kArtikel, MIN(s1.cName) AS cName
                         FROM tartikelsprache s1
                        GROUP BY s1.kArtikel
                   ) ts ON ts.kArtikel = a.kArtikel
                  WHERE a.kArtikel = :k LIMIT 1",
                ['k' => $kArtikel]
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildArticleImageUrl(int $kArtikel, string $cName, string $shopUrl): string
    {
        if ($kArtikel <= 0) {
            return '';
        }

        $cleanName = '';
        try {
            $tmp = new \stdClass();
            $tmp->kArtikel    = $kArtikel;
            $tmp->cName       = $cName;
            $tmp->cSeo        = '';
            $tmp->originalSeo = '';
            $tmp->cArtNr      = '';
            $tmp->cBarcode    = '';
            $tmp->customImgName = '';
            $cleanName = \JTL\Media\Image\Product::getCustomName($tmp);
        } catch (\Throwable $e) {
            $cleanName = (string)$kArtikel;
        }

        return $shopUrl . '/media/image/product/' . $kArtikel . '/md/' . $cleanName . '.jpg';
    }

    private function truncateText(string $text, int $max): string
    {
        $text = \strip_tags($text);
        $text = \trim(\preg_replace('/\s+/', ' ', $text));
        if (\mb_strlen($text) <= $max) {
            return $text;
        }

        return \mb_substr($text, 0, $max) . '…';
    }

    /**
     * WM-Segment-Filter: Kat 24 + Produkttyp "Wohnmobil Schonbezüge Fahrer und Beifahrer Set".
     */
    private function wmSegmentFilter(): string
    {
        return " INNER JOIN tkategorieartikel ka_tree ON ka_tree.kArtikel = a.kArtikel
                 INNER JOIN tkategorie c_tree ON c_tree.kKategorie = ka_tree.kKategorie
                 INNER JOIN tkategorie p_tree ON p_tree.kKategorie = 24
                    AND c_tree.lft BETWEEN p_tree.lft AND p_tree.rght
                 AND NOT EXISTS (
                     SELECT 1 FROM tkategorie p_excl
                     WHERE p_excl.kKategorie = 22
                       AND c_tree.lft BETWEEN p_excl.lft AND p_excl.rght
                 )
                 INNER JOIN tartikelmerkmal am_ptyp ON am_ptyp.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_ptyp ON mw_ptyp.kMerkmalWert = am_ptyp.kMerkmalWert
                 INNER JOIN tmerkmal m_ptyp ON m_ptyp.kMerkmal = mw_ptyp.kMerkmal
                 INNER JOIN tmerkmalsprache ms_ptyp ON ms_ptyp.kMerkmal = m_ptyp.kMerkmal
                    AND ms_ptyp.kSprache = :langID AND ms_ptyp.cName = 'Produkttyp'
                 INNER JOIN tmerkmalwertsprache tmws_ptyp ON tmws_ptyp.kMerkmalWert = mw_ptyp.kMerkmalWert
                    AND tmws_ptyp.kSprache = :langID AND tmws_ptyp.cWert = 'Wohnmobil Schonbezüge Fahrer und Beifahrer Set'";
    }
}
