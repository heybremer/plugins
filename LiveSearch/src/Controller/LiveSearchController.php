<?php

namespace Plugin\LiveSearch\Controller;

use JTL\DB\DbInterface;
use JTL\Media\Image;
use JTL\Media\Image\Product;
use JTL\Shop;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LiveSearchController
{
    /** Obergrenze gesperrter Merkmale (JOIN-Tiefe). */
    private const MAX_MERKMAL_MATCHES = 12;

    /** Maximale Suchlänge (teure LIKE-Abfragen vermeiden). */
    private const MAX_QUERY_LENGTH = 240;

    /**
     * Reihenfolge wie im Konfigurator: zuerst das erste Merkmal mit mindestens zwei Werten im gefilterten Sortiment.
     * (Gleiche tmerkmalsprache.cName wie FilterKonfiguratorSearch / MarkeFiltre.)
     *
     * @var list<string>
     */
    private const MERKMAL_FLOW_ORDER = [
        'Wohnmobil-Aufbauart',
        'Fahrzeug-Marke',
        'Wohnmobil-Fahrgestell',
        'Fahrzeug-Modell',
    ];

    private const SCOPED_SUGGEST_LIMIT = 8;

    /**
     * Schreibweisen von tmerkmalsprache.cName (MarkeFiltre / ältere Daten).
     *
     * @var array<string, string>
     */
    private const MERKMAL_NAME_ALIASES = [
        'fahrzeug marke'        => 'Fahrzeug-Marke',
        'automarken'            => 'Fahrzeug-Marke',
        'wohnmobil aufbauart'   => 'Wohnmobil-Aufbauart',
        'fahrzeug modell'       => 'Fahrzeug-Modell',
        'wohnmobil fahrgestell' => 'Wohnmobil-Fahrgestell',
    ];

    private function canonicalMerkmalCName(string $name): string
    {
        $t = \trim($name);
        if ($t === '') {
            return '';
        }
        $k = \mb_strtolower($t);

        return self::MERKMAL_NAME_ALIASES[$k] ?? $t;
    }

    private function slugify(string $text): string
    {
        $text = \preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = \iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = \preg_replace('~[^-\w]+~', '', $text);
        $text = \trim($text, '-');
        $text = \preg_replace('~-+~', '-', $text);
        $text = \strtolower($text);

        return $text === '' ? 'n-a' : $text;
    }

    public function search(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $q           = isset($queryParams['q']) ? trim((string) $queryParams['q']) : '';
        $merkmalJson = isset($queryParams['merkmale']) ? (string) $queryParams['merkmale'] : '';

        if (mb_strlen($q) > self::MAX_QUERY_LENGTH) {
            $q = mb_substr($q, 0, self::MAX_QUERY_LENGTH);
        }

        try {
            /** @var DbInterface $db */
            $db     = Shop::Container()->getDB();
            $langID = (int) Shop::getLanguageID();

            $confirmedValues = $this->resolveLockedMerkmale($db, $langID, $merkmalJson);
            if (\count($confirmedValues) > self::MAX_MERKMAL_MATCHES) {
                $confirmedValues = \array_slice($confirmedValues, 0, self::MAX_MERKMAL_MATCHES);
            }

            $qLen = mb_strlen($q);

            if ($qLen >= 2) {
                $nextMerkmal = $confirmedValues !== []
                    ? $this->findNextOpenMerkmalCName($db, $langID, $confirmedValues)
                    : null;

                if ($nextMerkmal !== null) {
                    $scopedRows = $this->findScopedMerkmalValues(
                        $db,
                        $langID,
                        $confirmedValues,
                        $nextMerkmal,
                        $q,
                        self::SCOPED_SUGGEST_LIMIT
                    );
                    $scopedFiltered = $this->filterSuggestRowsAgainstLocked($scopedRows, $confirmedValues);
                    if (!empty($scopedFiltered)) {
                        return $this->jsonResponse(
                            $this->buildSuggestResponseItems($confirmedValues, $nextMerkmal, $scopedFiltered, true)
                        );
                    }
                }

                $suggestions = $db->getObjects(
                    "SELECT DISTINCT tmws.cWert
                       FROM tmerkmalwertsprache tmws
                 INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tmws.kMerkmalWert
                 INNER JOIN tartikelmerkmal am ON am.kMerkmalWert = mw.kMerkmalWert
                      WHERE tmws.kSprache = :langID
                        AND LOWER(tmws.cWert) LIKE LOWER(:term)
                   ORDER BY LENGTH(tmws.cWert) ASC, tmws.cWert ASC
                       LIMIT 15",
                    ['langID' => $langID, 'term' => '%' . $q . '%']
                );

                if (is_array($suggestions) && !empty($suggestions)) {
                    $filtered = $this->filterSuggestRowsAgainstLocked($suggestions, $confirmedValues);

                    if (!empty($filtered)) {
                        return $this->jsonResponse(
                            $this->buildSuggestResponseItems($confirmedValues, null, $filtered, false)
                        );
                    }
                }
            }

            if (!empty($confirmedValues)) {
                $seriesItems = $this->findSeriesForValues($db, $langID, $confirmedValues);
                $nextMerkmal = $this->findNextOpenMerkmalCName($db, $langID, $confirmedValues);
                if ($nextMerkmal === null) {
                    return $this->jsonResponse($seriesItems);
                }

                $prefillRows = $this->findScopedMerkmalValues(
                    $db,
                    $langID,
                    $confirmedValues,
                    $nextMerkmal,
                    null,
                    self::SCOPED_SUGGEST_LIMIT
                );
                $prefillFiltered = $this->filterSuggestRowsAgainstLocked($prefillRows, $confirmedValues);
                if ($prefillFiltered === []) {
                    return $this->jsonResponse($seriesItems);
                }

                $out   = [];
                $out[] = [
                    'type'       => 'hint',
                    'merkmalKey' => $nextMerkmal,
                    'title'      => 'Nächster Filter: ' . $nextMerkmal,
                    'subtitle'   => 'Die folgenden Werte passen zu Ihrer aktuellen Auswahl.',
                ];
                foreach ($prefillFiltered as $s) {
                    $out[] = [
                        'type'  => 'suggest',
                        'name'  => $s->cWert,
                        'fill'  => '',
                        'scope' => 'filtered',
                    ];
                }
                foreach ($seriesItems as $row) {
                    $out[] = $row;
                }

                return $this->jsonResponse($out);
            }

            return $this->jsonResponse([]);
        } catch (\Throwable $e) {
            return $this->jsonResponse([]);
        }
    }

    /**
     * Nur über die Client-Tags (merkmale JSON) als Filter gültig — freier Text allein bestätigt kein Merkmal.
     *
     * @return array<int, array{cWert: mixed, cSeo: mixed, merkmalName: string}>
     */
    private function resolveLockedMerkmale(DbInterface $db, int $langID, string $merkmalJson): array
    {
        if ($merkmalJson === '') {
            return [];
        }

        $decoded = \json_decode($merkmalJson, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $out  = [];
        $seen = [];

        foreach ($decoded as $v) {
            if (!\is_string($v)) {
                continue;
            }
            $label = \trim($v);
            if ($label === '' || \mb_strlen($label) < 2) {
                continue;
            }
            $k = \mb_strtolower($label);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;

            $row = $db->getSingleObject(
                "SELECT tmws.cWert,
                        seo.cSeo,
                        ms.cName AS merkmalName
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
                    AND LOWER(TRIM(tmws.cWert)) = LOWER(TRIM(:phrase))
                  LIMIT 1",
                ['langID' => $langID, 'phrase' => $label]
            );

            if ($row !== null) {
                $out[] = [
                    'cWert'        => $row->cWert,
                    'cSeo'         => $row->cSeo ?? '',
                    'merkmalName'  => (string) ($row->merkmalName ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<int, array{cWert: mixed, cSeo?: mixed, merkmalName?: mixed}> $matchedValues
     * @param array<string, mixed>                                                  $params
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildArticleLockJoins(array $matchedValues, array $params): array
    {
        $joins = '';
        foreach ($matchedValues as $i => $mv) {
            $tamAlias  = 'tam' . $i;
            $tmwsAlias = 'tmws' . $i;
            $paramKey  = 'q' . $i;

            $joins .= " INNER JOIN tartikelmerkmal {$tamAlias}"
                    . "    ON {$tamAlias}.kArtikel = a.kArtikel"
                    . " INNER JOIN tmerkmalwertsprache {$tmwsAlias}"
                    . "    ON {$tmwsAlias}.kMerkmalWert = {$tamAlias}.kMerkmalWert"
                    . "   AND {$tmwsAlias}.kSprache     = :langID"
                    . "   AND {$tmwsAlias}.cWert         = :{$paramKey}";

            $params[$paramKey] = $mv['cWert'];
        }

        return [$joins, $params];
    }

    /**
     * @param array<int, object|array<string, mixed>> $rows
     * @param array<int, array{cWert: mixed}>         $confirmedValues
     *
     * @return array<int, object>
     */
    private function filterSuggestRowsAgainstLocked(array $rows, array $confirmedValues): array
    {
        $confirmedLower = [];
        foreach ($confirmedValues as $cv) {
            $confirmedLower[\mb_strtolower((string) ($cv['cWert'] ?? ''))] = true;
        }
        $filtered = [];
        foreach ($rows as $s) {
            $w = (string) ($s->cWert ?? '');
            if ($w === '' || isset($confirmedLower[\mb_strtolower($w)])) {
                continue;
            }
            $filtered[] = $s;
        }

        return $filtered;
    }

    /**
     * @param array<int, object> $filtered
     *
     * @return list<array<string, mixed>>
     */
    private function buildSuggestResponseItems(
        array $confirmedValues,
        ?string $nextMerkmalCName,
        array $filtered,
        bool $scoped
    ): array {
        $confirmedNames = [];
        foreach ($confirmedValues as $cv) {
            $w = (string) ($cv['cWert'] ?? '');
            if ($w !== '') {
                $confirmedNames[] = $w;
            }
        }
        $confirmedPrefix = $confirmedNames !== []
            ? \implode(' ', $confirmedNames) . ' '
            : '';

        $items = [];
        if ($scoped && $nextMerkmalCName !== null) {
            $items[] = [
                'type'       => 'hint',
                'merkmalKey' => $nextMerkmalCName,
                'title'      => 'Nächster Filter: ' . $nextMerkmalCName,
                'subtitle'   => 'Treffer passend zu Ihrer Auswahl.',
            ];
        }

        foreach ($filtered as $s) {
            $items[] = [
                'type'  => 'suggest',
                'name'  => $s->cWert,
                'fill'  => $confirmedPrefix . $s->cWert,
                'scope' => $scoped ? 'filtered' : 'global',
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array{cWert: mixed, merkmalName?: mixed}> $matchedValues
     */
    private function findNextOpenMerkmalCName(DbInterface $db, int $langID, array $matchedValues): ?string
    {
        if ($matchedValues === []) {
            return null;
        }

        $lockedNames = [];
        foreach ($matchedValues as $cv) {
            $n = $this->canonicalMerkmalCName((string) ($cv['merkmalName'] ?? ''));
            if ($n !== '') {
                $lockedNames[$n] = true;
            }
        }

        $openNames = [];
        foreach (self::MERKMAL_FLOW_ORDER as $cName) {
            if (!isset($lockedNames[$cName])) {
                $openNames[] = $cName;
            }
        }

        if ($openNames === []) {
            return null;
        }

        $params = ['langID' => $langID];
        [$joins, $params] = $this->buildArticleLockJoins($matchedValues, $params);

        $nameList = "'" . \implode("','", \array_map(function($n) use ($db) { 
            return \str_replace("'", "''", $n); 
        }, $openNames)) . "'";

        $sql = "SELECT msM.cName, COUNT(DISTINCT tmwsM.cWert) AS cnt
                  FROM tartikel a
                  {$joins}
            INNER JOIN tartikelmerkmal tamM
                    ON tamM.kArtikel = a.kArtikel
            INNER JOIN tmerkmalwert mwM
                    ON mwM.kMerkmalWert = tamM.kMerkmalWert
            INNER JOIN tmerkmal mM
                    ON mM.kMerkmal = mwM.kMerkmal
            INNER JOIN tmerkmalsprache msM
                    ON msM.kMerkmal = mM.kMerkmal
                   AND msM.kSprache = :langID
                   AND msM.cName IN ({$nameList})
            INNER JOIN tmerkmalwertsprache tmwsM
                    ON tmwsM.kMerkmalWert = tamM.kMerkmalWert
                   AND tmwsM.kSprache     = :langID
                 WHERE a.nIstVater = 0
                   AND TRIM(tmwsM.cWert) <> ''
              GROUP BY msM.cName";

        $rows = $db->getObjects($sql, $params);
        $counts = [];
        if (\is_array($rows)) {
            foreach ($rows as $r) {
                $counts[$r->cName] = (int)$r->cnt;
            }
        }

        foreach (self::MERKMAL_FLOW_ORDER as $cName) {
            if (isset($counts[$cName]) && $counts[$cName] >= 2) {
                return $cName;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{cWert: mixed}> $matchedValues
     *
     * @return array<int, object>
     */
    private function findScopedMerkmalValues(
        DbInterface $db,
        int $langID,
        array $matchedValues,
        string $merkmalCName,
        ?string $likeTerm,
        int $limit
    ): array {
        $limit = \max(1, \min(50, $limit));
        $params = ['langID' => $langID, 'mname' => $merkmalCName];
        [$joins, $params] = $this->buildArticleLockJoins($matchedValues, $params);

        $likeSql = '';
        if ($likeTerm !== null && \trim($likeTerm) !== '') {
            $params['term'] = '%' . $likeTerm . '%';
            $likeSql        = ' AND LOWER(tmwsM.cWert) LIKE LOWER(:term)';
        }

        $sql = "SELECT DISTINCT tmwsM.cWert
                  FROM tartikel a
                  {$joins}
            INNER JOIN tartikelmerkmal tamM
                    ON tamM.kArtikel = a.kArtikel
            INNER JOIN tmerkmalwert mwM
                    ON mwM.kMerkmalWert = tamM.kMerkmalWert
            INNER JOIN tmerkmal mM
                    ON mM.kMerkmal = mwM.kMerkmal
            INNER JOIN tmerkmalsprache msM
                    ON msM.kMerkmal = mM.kMerkmal
                   AND msM.kSprache = :langID
                   AND msM.cName    = :mname
            INNER JOIN tmerkmalwertsprache tmwsM
                    ON tmwsM.kMerkmalWert = tamM.kMerkmalWert
                   AND tmwsM.kSprache     = :langID
                 WHERE a.nIstVater = 0
                   AND TRIM(tmwsM.cWert) <> ''
                       {$likeSql}
              ORDER BY LENGTH(tmwsM.cWert) ASC, tmwsM.cWert ASC
                 LIMIT {$limit}";

        $rows = $db->getObjects($sql, $params);

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Serien-Treffer und Filter-URL aus den gesperrten Merkmalwerten.
     */
    private function findSeriesForValues(DbInterface $db, int $langID, array $matchedValues): array
    {
        $params = ['langID' => $langID];
        [$joins, $params] = $this->buildArticleLockJoins($matchedValues, $params);

        $sql = "SELECT grp.serie,
                       grp.serieSeo,
                       grp.kArtikelSample
                  FROM (
                        SELECT MIN(serieWert.cWert)              AS serie,
                               MAX(COALESCE(serieSeo.cSeo, '')) AS serieSeo,
                               MIN(a.kArtikel)                  AS kArtikelSample
                          FROM tartikel a
                          {$joins}
                    INNER JOIN tartikelmerkmal tamSerie
                            ON tamSerie.kArtikel = a.kArtikel
                    INNER JOIN tmerkmalwert mwSerie
                            ON mwSerie.kMerkmalWert = tamSerie.kMerkmalWert
                    INNER JOIN tmerkmal mSerie
                            ON mSerie.kMerkmal = mwSerie.kMerkmal
                    INNER JOIN tmerkmalsprache msSerie
                            ON msSerie.kMerkmal = mSerie.kMerkmal
                           AND msSerie.kSprache = :langID
                           AND msSerie.cName    = 'Serie'
                    INNER JOIN tmerkmalwertsprache serieWert
                            ON serieWert.kMerkmalWert = tamSerie.kMerkmalWert
                           AND serieWert.kSprache     = :langID
                     LEFT JOIN tseo serieSeo
                            ON serieSeo.cKey     = 'kMerkmalWert'
                           AND serieSeo.kKey     = tamSerie.kMerkmalWert
                           AND serieSeo.kSprache = :langID
                         WHERE a.nIstVater = 0
                      GROUP BY tamSerie.kMerkmalWert
                       ) grp
              ORDER BY grp.serie ASC
                 LIMIT 10";

        $rows = $db->getObjects($sql, $params);

        $artsById = [];
        if (\is_array($rows) && $rows !== []) {
            $idList = [];
            foreach ($rows as $r) {
                $kid = (int) ($r->kArtikelSample ?? 0);
                if ($kid > 0) {
                    $idList[$kid] = true;
                }
            }
            $ids = \array_keys($idList);
            if ($ids !== []) {
                $in = \implode(',', $ids);
                $arts = $db->getObjects(
                    "SELECT kArtikel, cSeo, cArtNr, cName, cBarcode
                       FROM tartikel
                      WHERE kArtikel IN ({$in})",
                    []
                );
                if (\is_array($arts)) {
                    foreach ($arts as $a) {
                        $artsById[(int) $a->kArtikel] = $a;
                    }
                }
            }
        }

        $filterSlugs = [];
        foreach ($matchedValues as $mv) {
            if (!empty($mv['cSeo'])) {
                $filterSlugs[] = $mv['cSeo'];
            }
        }
        $filterPath = !empty($filterSlugs) ? '__' . implode('__', $filterSlugs) : '';

        $shopURL = Shop::getURL();
        $items   = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $serieBase = !empty($row->serieSeo)
                    ? '/' . $row->serieSeo
                    : '/' . $this->slugify($row->serie) . '-serie';

                $thumbUrl = '';
                $kid      = (int) ($row->kArtikelSample ?? 0);
                if ($kid > 0 && isset($artsById[$kid])) {
                    $rel = Product::getThumb(
                        Image::TYPE_PRODUCT,
                        $kid,
                        $artsById[$kid],
                        Image::SIZE_SM,
                        1
                    );
                    if ($rel !== '') {
                        $thumbUrl = $shopURL . '/' . \ltrim(\str_replace('\\', '/', $rel), '/');
                    }
                }

                $items[] = [
                    'type'     => 'serie',
                    'name'     => $row->serie . ' Serie',
                    'filters'  => implode(', ', array_column($matchedValues, 'cWert')),
                    'url'      => $shopURL . $serieBase . $filterPath,
                    'thumbUrl' => $thumbUrl,
                ];
            }
        }

        return $items;
    }

    private function jsonResponse(array $data): ResponseInterface
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = new \Laminas\Diactoros\Stream('php://temp', 'wb+');
        $body->write($json);
        $body->rewind();

        return new \Laminas\Diactoros\Response($body, 200, [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
