<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Controller;

use Plugin\FilterKonfiguratorSearch\PluginDatabase;
use Plugin\FilterKonfiguratorSearch\Repository\FilterKonfiguratorRepository;
use Plugin\FilterKonfiguratorSearch\SeriesMetaRuntime;
use JTL\DB\DbInterface;
use JTL\Helpers\Request;
use JTL\Shop;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Thin HTTP adapter.  All DB logic is delegated to FilterKonfiguratorRepository.
 */
class FilterKonfiguratorController
{
    private FilterKonfiguratorRepository $repo;

    public function __construct()
    {
        $db = Shop::Container()->getDB();
        $this->repo = new FilterKonfiguratorRepository($db, (int)Shop::getLanguageID());
    }

    /**
     * JSON endpoint: /fk-search
     * Beklenen query parametreleri (hepsi opsiyonel):
     * typ, hersteller, basis, modell, baujahr, sitz, serie
     * mode=options: field=serie|hersteller|modell|… optional serie=… für Serie→Marke→Modell (Wohnmobil/PKW).
     * mode=cardlink: serie, hersteller, modell (+ optional segment=pkw) → JSON { url }.
     */
    public function search(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $params    = $request->getQueryParams();
        $mode      = trim($params['mode'] ?? '');
        $fieldForLog = trim($params['field'] ?? '');
        $qForLog     = trim($params['q'] ?? '');
        $typ       = trim($params['typ']       ?? '');
        $herst     = trim($params['hersteller']?? '');
        $basis     = trim($params['basis']     ?? '');
        $modell    = trim($params['modell']    ?? '');
        $baujahr   = trim($params['baujahr']   ?? '');
        $sitz      = trim($params['sitz']      ?? '');
        $serie     = trim($params['serie']     ?? '');
        $segment   = \strtolower(\trim((string)($params['segment'] ?? '')));
        $isPkw     = ($segment === 'pkw');

        /** @var DbInterface|null $db */
        $db = null;

        $logStart = \microtime(true);
        $logIp    = Request::getRealIP();
        $logUA    = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $logRef   = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $logUri   = (string)($_SERVER['REQUEST_URI'] ?? '');
        $logCtx   = [
            'mode'       => $mode,
            'field'      => $fieldForLog,
            'q'          => $qForLog,
            'segment'    => $segment,
            'typ'        => $typ,
            'hersteller' => $herst,
            'basis'      => $basis,
            'modell'     => $modell,
            'baujahr'    => $baujahr,
            'sitz'       => $sitz,
            'serie'      => $serie,
            'referer'    => $logRef,
            'uri'        => $logUri,
        ];

        try {
            PluginDatabase::ensureCoreTables();

            $db = Shop::Container()->getDB();

            if ($mode === 'series_meta') {
                $payload = SeriesMetaRuntime::buildPayload($db);

                return new JsonResponse($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $segFlags = $this->repo->getKonfiguratorSegmentFlags();
            if ($isPkw && !$segFlags['pkw']) {
                return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (!$isPkw && !$segFlags['wm']) {
                return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // BATCH MODE: Reduce roundtrips by loading all downstream options in one go.
            if ($mode === 'batch') {
                $selections = [
                    'typ'        => \trim((string)($params['typ'] ?? '')),
                    'hersteller' => \trim((string)($params['hersteller'] ?? '')),
                    'basis'      => \trim((string)($params['basis'] ?? '')),
                    'modell'     => \trim((string)($params['modell'] ?? '')),
                    'baujahr'    => \trim((string)($params['baujahr'] ?? '')),
                    'sitz'       => \trim((string)($params['sitz'] ?? '')),
                    'serie'      => \trim((string)($params['serie'] ?? '')),
                    'segment'    => \strtolower(\trim((string)($params['segment'] ?? ''))),
                ];
                $fkOpts = \trim((string)($params['fkCustomModell'] ?? '')) === '1'
                    || \strtolower(\trim((string)($params['fkCustomModell'] ?? ''))) === 'true';

                $batch = $this->repo->loadBatchOptions($selections, $fkOpts);

                return new JsonResponse($batch, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Dropdown verileri için SADECE Merkmal tabanlı JSON endpoint
            // (twomosi_filterkonfigurator tablosunu burada hiç kullanmıyoruz)
            if ($mode === 'options') {
                $field      = trim($params['field'] ?? '');
                $term       = trim($params['q'] ?? '');
                $typParam   = trim($params['typ'] ?? '');
                $herstParam = trim($params['hersteller'] ?? '');
                $basisParam = trim($params['basis'] ?? '');
                $modellParam = trim($params['modell'] ?? '');
                $serieParam  = trim($params['serie'] ?? '');
                $langID     = (int)Shop::getLanguageID();

                $baujahrParam = trim($params['baujahr'] ?? '');
                $fkOpts = \trim((string)($params['fkCustomModell'] ?? '')) === '1'
                    || \strtolower(\trim((string)($params['fkCustomModell'] ?? ''))) === 'true';
                $segParam = \strtolower(\trim((string)($params['segment'] ?? '')));
                $vehicleSeg = ($segParam === 'pkw') ? 'pkw' : '';
                $options = $this->repo->loadMerkmalOptions($field, $term, $typParam, $herstParam, $basisParam, $modellParam, $baujahrParam, $fkOpts, $vehicleSeg, $serieParam);

                return new JsonResponse($options, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($mode === 'cardlink') {
                $langID = (int)Shop::getLanguageID();
                $serieL  = \trim((string)($params['serie'] ?? ''));
                $herstL  = \trim((string)($params['hersteller'] ?? ''));
                $modellL = \trim((string)($params['modell'] ?? ''));
                if ($serieL === '' || $herstL === '' || $modellL === '') {
                    return new JsonResponse(['url' => ''], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                
                // Do NOT include serieL in selections, otherwise its SEO slug gets incorrectly added to the filter path!
                $selections = [
                    ['value' => $herstL, 'names' => ['Fahrzeug-Marke']],
                    ['value' => $modellL, 'names' => ['Fahrzeug-Modell']],
                ];
                
                $typL = \trim((string)($params['typ'] ?? ''));
                $basisL = \trim((string)($params['basis'] ?? ''));
                $sitzL = \trim((string)($params['sitz'] ?? ''));
                
                if (!$isPkw && $typL !== '') {
                    $selections[] = ['value' => $typL, 'names' => ['Wohnmobil-Aufbauart']];
                }
                if (!$isPkw && $basisL !== '') {
                    $selections[] = ['value' => $basisL, 'names' => ['Wohnmobil-Fahrgestell']];
                }
                if ($sitzL !== '') {
                    $selections[] = ['value' => $sitzL, 'names' => ['kompatible Sitze']];
                }
                
                $matched = $this->repo->findExactMerkmalValuesForNames($selections);
                if (\count($matched) === 0) {
                    return new JsonResponse(['url' => ''], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $selectionQuery = [
                    'segment'    => $isPkw ? 'pkw' : '',
                    'typ'        => $typL,
                    'hersteller' => $herstL,
                    'basis'      => $basisL,
                    'modell'     => $modellL,
                    'sitz'       => $sitzL,
                    'serie'      => $serieL,
                ];
                
                $items = $this->repo->findSeriesForValues($matched, 'Serie', $selectionQuery);
                if ($items === []) {
                    $items = $this->repo->findSeriesForValues($matched, 'Familie-Serie', $selectionQuery);
                }
                $url = '';
                foreach ($items as $it) {
                    if (\strcasecmp($it['serie'], $serieL) === 0 && !empty($it['url'])) {
                        $url = (string)$it['url'];
                        break;
                    }
                }
                if ($url === '' && isset($items[0]) && \is_array($items[0]) && !empty($items[0]['url'])) {
                    $url = (string)$items[0]['url'];
                }

                return new JsonResponse(['url' => $url], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Artık twomosi_filterkonfigurator veya data/records.php fallback KULLANMIYORUZ.
            // Seçilen Merkmal değerlerine göre, LiveSearch mantığına benzer şekilde
            // doğrudan veritabanından Serie URL'leri üret.

            $langID = (int)Shop::getLanguageID();

            $fkCustomModellRaw = \trim((string)($params['fkCustomModell'] ?? ''));
            $fkCustomModell      = ($fkCustomModellRaw === '1'
                || \strtolower($fkCustomModellRaw) === 'true'
                || \strtolower($fkCustomModellRaw) === 'yes');

            // „Nicht in der Liste“ + Freitext: komplette Familie-Serie-Liste (ohne Artikel-Filter)
            $fkNichtInListeRaw = \trim((string)($params['fkNichtInListe'] ?? ''));
            $fkNichtInListe    = ($fkNichtInListeRaw === '1'
                || \strtolower($fkNichtInListeRaw) === 'true'
                || \strtolower($fkNichtInListeRaw) === 'yes');
            // Vollständige Familie-Serie-Liste: kein modell-Text nötig (nur Flags + Sentinel-Pfad im Frontend)
            if ($fkCustomModell && $fkNichtInListe) {
                $seriesItems = $this->repo->findAllFamilieSerieItems([
                    'segment'    => $isPkw ? 'pkw' : '',
                    'typ'        => $typ,
                    'hersteller' => $herst,
                    'basis'      => $basis,
                    'modell'     => $modell,
                    'baujahr'    => $baujahr,
                    'sitz'       => $sitz,
                ]);

                return new JsonResponse($seriesItems, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $selectionQuery = [
                'segment'    => $isPkw ? 'pkw' : '',
                'typ'        => $typ,
                'hersteller' => $herst,
                'basis'      => $basis,
                'modell'     => $modell,
                'baujahr'    => $baujahr,
                'sitz'       => $sitz,
            ];

            // Step 4 cache: aynı seçim kombinasyonunda seri listesini tekrar DB'den hesaplama.
            // Cache sadece tablo halinde yazılan seri listesi için kullanılır.
            $step4CacheKey = '';
            $useStep4Cache = $isPkw
                ? ($herst !== '' && $modell !== '' && !$fkCustomModell)
                : ($typ !== '' && $herst !== '' && $basis !== '' && $modell !== '' && $sitz !== '');
            if ($useStep4Cache) {
                $cacheTtlSeconds = 2 * 24 * 60 * 60; // 2 gün
                $step4CacheKey    = $isPkw
                    ? md5($langID . '|pkw|' . $herst . '|' . $modell . '|' . $baujahr . '|' . ($fkCustomModell ? 'familie' : 'std'))
                    : md5($langID . '|' . $typ . '|' . $herst . '|' . $basis . '|' . $modell . '|' . $sitz . '|' . ($fkCustomModell ? 'familie' : 'std'));

                try {
                    $row = $db->getSingleObject(
                        "SELECT cPayload, dUpdated
                           FROM twomosi_step4_cache
                          WHERE cKey = :cKey
                          LIMIT 1",
                        ['cKey' => $step4CacheKey]
                    );

                    if ($row && !empty($row->cPayload) && !empty($row->dUpdated)) {
                        $updatedTs = \strtotime((string)$row->dUpdated);
                        if ($updatedTs !== false) {
                            $age = \time() - $updatedTs;
                            if ($age >= 0 && $age <= $cacheTtlSeconds) {
                                $decoded = \json_decode((string)$row->cPayload, true);
                                if (\is_array($decoded)) {
                                    $decoded = $this->repo->reapplyKonfiguratorQueryOnSeriesItems($decoded, $selectionQuery);

                                    return new JsonResponse($decoded, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Cache okuma hatası olursa normal hesaplamaya devam.
                }
            }

            $selections = [];
            // Manuel Modell (nicht in der Liste): Merkmal "Fahrzeug-Modell" nicht matchen — Serie aus "Familie-Serie".
            if ($fkCustomModell && $modell !== '') {
                if (!$isPkw && $sitz === '') {
                    return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if ($isPkw) {
                    if ($herst === '') {
                        return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    $selections[] = ['value' => $herst, 'names' => ['Fahrzeug-Marke']];
                    if ($sitz !== '') {
                        $selections[] = ['value' => $sitz, 'names' => ['kompatible Sitze']];
                    }
                    if ($serie !== '') {
                        $selections[] = ['value' => $serie, 'names' => ['Serie']];
                    }
                    if ($baujahr !== '') {
                        $selections[] = ['value' => $baujahr, 'names' => ['Baujahr', 'Bau-Jahr', 'Bau Jahr']];
                    }
                } else {
                    if ($typ !== '') {
                        $selections[] = ['value' => $typ, 'names' => ['Wohnmobil-Aufbauart']];
                    }
                    if ($herst !== '') {
                        $selections[] = ['value' => $herst, 'names' => ['Fahrzeug-Marke']];
                    }
                    if ($basis !== '') {
                        $selections[] = ['value' => $basis, 'names' => ['Wohnmobil-Fahrgestell']];
                    }
                    if ($sitz !== '') {
                        $selections[] = ['value' => $sitz, 'names' => ['kompatible Sitze']];
                    }
                    if ($serie !== '') {
                        $selections[] = ['value' => $serie, 'names' => ['Serie']];
                    }
                }
            } else {
                if (!$isPkw && $typ !== '') {
                    $selections[] = ['value' => $typ, 'names' => ['Wohnmobil-Aufbauart']];
                }
                if ($herst !== '') {
                    $selections[] = ['value' => $herst, 'names' => ['Fahrzeug-Marke']];
                }
                if ($modell !== '') {
                    $selections[] = ['value' => $modell, 'names' => ['Fahrzeug-Modell']];
                }
                if (!$isPkw && $basis !== '') {
                    $selections[] = ['value' => $basis, 'names' => ['Wohnmobil-Fahrgestell']];
                }
                if ($sitz !== '') {
                    $selections[] = ['value' => $sitz, 'names' => ['kompatible Sitze']];
                }
                if ($serie !== '') {
                    $selections[] = ['value' => $serie, 'names' => ['Serie']];
                }
                if ($isPkw && $baujahr !== '') {
                    $selections[] = ['value' => $baujahr, 'names' => ['Baujahr', 'Bau-Jahr', 'Bau Jahr']];
                }
            }

            if (\count($selections) === 0) {
                return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Seçilen değerleri, ilgili Merkmal adına göre kısıtlayarak eşleştir
            $matchedValues = $this->repo->findExactMerkmalValuesForNames($selections);

            if (\count($matchedValues) === 0) {
                return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Bu Merkmal değerlerine göre Serie sonuçlarını üret (manuell: Familie-Serie)
            $serieMerkmalName = ($fkCustomModell && $modell !== '') ? 'Familie-Serie' : 'Serie';
            $seriesItems      = $this->repo->findSeriesForValues($matchedValues, $serieMerkmalName, $selectionQuery);

            // Cache miss -> sonucu cache'e yaz.
            if ($useStep4Cache && $step4CacheKey !== '') {
                try {
                    $payload = \json_encode($seriesItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (is_string($payload)) {
                        $db->executeQueryPrepared(
                            "INSERT INTO twomosi_step4_cache (cKey, dUpdated, cPayload)
                             VALUES (:cKey, NOW(), :cPayload)
                             ON DUPLICATE KEY UPDATE
                                dUpdated = NOW(),
                                cPayload = VALUES(cPayload)",
                            [
                                'cKey'     => $step4CacheKey,
                                'cPayload' => $payload,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Cache yazma hatası olursa normal response dön.
                }
            }

            return new JsonResponse($seriesItems, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error'   => true,
                'message' => $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } finally {
            try {
                if ($db !== null && $mode !== 'series_meta') {
                    $durationMs = (int)\round((\microtime(true) - $logStart) * 1000);
                    $payload    = \json_encode($logCtx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (!\is_string($payload) || $payload === '') {
                        $payload = '{}';
                    }
                    $db->executeQueryPrepared(
                        "INSERT INTO twomosi_fk_search_log
                            (cIP, dCreated, cMode, cField, cParams, nDurationMs, cUserAgent)
                         VALUES
                            (:ip, NOW(), :mode, :field, :params, :dur, :ua)",
                        [
                            'ip'     => $logIp,
                            'mode'   => $mode,
                            'field'  => $fieldForLog,
                            'params' => $payload,
                            'dur'    => $durationMs,
                            'ua'     => $logUA,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                // Never break endpoint due to logging.
            }
        }
    }
}
