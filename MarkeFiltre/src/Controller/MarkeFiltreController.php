<?php

declare(strict_types=1);

namespace Plugin\MarkeFiltre\Controller;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\MarkeFiltre\BrandListLink;
use Plugin\MarkeFiltre\KonfigJumpUrl;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JSON-Endpunkt /mf-brands — gleiche PKW/Wohnmobil-Logik wie {@see \Plugin\MarkeFiltre\Bootstrap::rebuildBrandsCache}.
 */
class MarkeFiltreController
{
    public function brands(ServerRequestInterface $request): ResponseInterface
    {
        try {
            /** @var DbInterface $db */
            $db     = Shop::Container()->getDB();
            $langID = (int) Shop::getLanguageID();

            $tryLangs = [$langID];
            if ($langID !== 1) {
                $tryLangs[] = 1;
            }

            foreach ($tryLangs as $lid) {
                $wohnRows = $db->getObjects(
                    "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                       FROM tartikel a
                 INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                 INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
                 INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                                  AND ms_typ.kSprache = :lid
                                                  AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR (ms_typ.cName LIKE '%Aufbauart%'))
                 INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                 INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
                 INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                                  AND ms_her.kSprache = :lid
                                                  AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
                 INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                        AND tmws_her.kSprache     = :lid
                  WHERE a.nIstVater = 0
                    AND TRIM(tmws_her.cWert) <> ''
                    AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
               ORDER BY tmws_her.cWert ASC
                  LIMIT 2000",
                    ['lid' => $lid]
                ) ?: [];

                $pkwRows = $db->getObjects(
                    "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                       FROM tartikel a
                 INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                 INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
                 INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                                  AND ms_her.kSprache = :lid
                                                  AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
                 INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                        AND tmws_her.kSprache     = :lid
             INNER JOIN tartikelmerkmal am_mod ON am_mod.kArtikel = a.kArtikel
             INNER JOIN tmerkmalwert mw_mod    ON mw_mod.kMerkmalWert = am_mod.kMerkmalWert
             INNER JOIN tmerkmal m_mod         ON m_mod.kMerkmal = mw_mod.kMerkmal
             INNER JOIN tmerkmalsprache ms_mod ON ms_mod.kMerkmal = m_mod.kMerkmal
                                              AND ms_mod.kSprache = :lid
                                              AND (ms_mod.cName = 'Fahrzeug-Modell' OR ms_mod.cName = 'Fahrzeug Modell')
                  WHERE a.nIstVater = 0
                    AND TRIM(tmws_her.cWert) <> ''
                    AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
                    AND NOT EXISTS (
                        SELECT 1
                          FROM tartikelmerkmal am_typ
                    INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                    INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
                    INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                                     AND ms_typ.kSprache = :lid
                                                     AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR ms_typ.cName LIKE '%Aufbauart%')
                         WHERE am_typ.kArtikel = a.kArtikel
                    )
               ORDER BY tmws_her.cWert ASC
                  LIMIT 2000",
                    ['lid' => $lid]
                ) ?: [];

                $pkwItems  = $this->rowsToBrandItems(\is_array($pkwRows) ? $pkwRows : [], $db, $lid);
                $wohnItems = $this->rowsToBrandItems(\is_array($wohnRows) ? $wohnRows : [], $db, $lid);

                if ($pkwItems !== [] || $wohnItems !== []) {
                    return new JsonResponse(
                        [
                            'PKW'       => $pkwItems,
                            'Wohnmobil' => $wohnItems,
                        ],
                        200,
                        [],
                        \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
                    );
                }
            }
        } catch (\Throwable $e) {
            // leere Antwort
        }

        return new JsonResponse(
            ['PKW' => [], 'Wohnmobil' => []],
            200,
            [],
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * GET /mf-konfig-url?segment=pkw|wohnmobil&brand=Ford
     * → Konfigurator-Listen-URL Marke__Produkttyp__Serie (+ State); Fallback nie leer (synthetisch).
     */
    public function konfigUrl(ServerRequestInterface $request): ResponseInterface
    {
        $params   = $request->getQueryParams();
        $segment  = \strtolower(\trim((string) ($params['segment'] ?? '')));
        $brand    = \trim((string) ($params['brand'] ?? ''));
        $isPkw    = ($segment === 'pkw');
        $emptyOut = ['url' => '', 'state' => new \stdClass()];

        if ($segment !== 'pkw' && $segment !== 'wohnmobil') {
            return new JsonResponse($emptyOut, 200, [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }
        if ($brand === '' || \strlen($brand) > 191) {
            return new JsonResponse($emptyOut, 200, [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        try {
            /** @var DbInterface $db */
            $db     = Shop::Container()->getDB();
            $langID = (int) Shop::getLanguageID();
            $pack   = KonfigJumpUrl::forBrand($db, $langID, $isPkw, $brand);
            if ($pack === null) {
                return new JsonResponse($emptyOut, 200, [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            }

            return new JsonResponse(
                [
                    'url'   => (string) ($pack['url'] ?? ''),
                    'state' => (object) ($pack['state'] ?? []),
                ],
                200,
                [],
                \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
            );
        } catch (\Throwable $e) {
            return new JsonResponse($emptyOut, 200, [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * @param array<int, object> $rows
     *
     * @return list<array{name: string, url: string}>
     */
    private function rowsToBrandItems(array $rows, DbInterface $db, int $langID): array
    {
        $items = [];
        foreach ($rows as $r) {
            $name = \trim((string) ($r->name ?? ''));
            $kid  = (int) ($r->kMerkmalWert ?? 0);
            if ($name === '' || $kid <= 0) {
                continue;
            }
            $items[] = [
                'name' => $name,
                'url'  => BrandListLink::forMerkmalWert($db, $langID, $kid),
            ];
        }

        return $items;
    }
}
