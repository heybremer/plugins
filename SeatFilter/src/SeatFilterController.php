<?php

declare(strict_types=1);

namespace Plugin\SeatFilter;

use JTL\Shop;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SeatFilterController
{
    private SeatFilterRepository $repo;

    public function __construct()
    {
        $db         = Shop::Container()->getDB();
        $this->repo = new SeatFilterRepository($db, (int)Shop::getLanguageID());
    }

    /**
     * JSON endpoint: /sf-search
     *
     * mode=sitz   → alle Sitz-Werte
     * mode=serie  → Serien für gewählten Sitz (?sitz=…)
     * mode=artikel → Artikel für Sitz+Serie (?sitz=…&serie=…)
     */
    public function search(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $params = $request->getQueryParams();
        $mode   = \trim((string)($params['mode'] ?? ''));
        $sitz   = \trim((string)($params['sitz'] ?? ''));
        $serie  = \trim((string)($params['serie'] ?? ''));

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        try {
            if ($mode === 'sitz') {
                $values = $this->repo->loadAllSitzValues();

                return new JsonResponse($values, 200, [], $flags);
            }

            if ($mode === 'serie') {
                if ($sitz === '') {
                    return new JsonResponse([], 200, [], $flags);
                }
                $series = $this->repo->loadSeriesForSitz($sitz);

                return new JsonResponse($series, 200, [], $flags);
            }

            if ($mode === 'artikel') {
                if ($sitz === '' || $serie === '') {
                    return new JsonResponse([], 200, [], $flags);
                }
                $articles = $this->repo->loadArtikelForSitzAndSerie($sitz, $serie);

                return new JsonResponse($articles, 200, [], $flags);
            }

            return new JsonResponse(['error' => true, 'message' => 'Unknown mode'], 400, [], $flags);
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => true, 'message' => 'Internal error'],
                500,
                [],
                $flags
            );
        }
    }
}
