<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Controller;

use Plugin\FilterKonfiguratorSearch\PluginDatabase;
use Plugin\FilterKonfiguratorSearch\Repository\FilterKonfiguratorRepository;
use Plugin\FilterKonfiguratorSearch\Service\SeatRecognitionApiException;
use Plugin\FilterKonfiguratorSearch\Service\SeatImageRecognitionService;
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

    private const OPTIONS_CACHE_TTL = 6 * 60 * 60;

    private function optionsCacheGet(string $key): ?array
    {
        try {
            $hit = Shop::Container()->getCache()->get($key);

            return \is_array($hit) && $hit !== [] ? $hit : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function optionsCacheSet(string $key, array $options): void
    {
        try {
            $tags = [\defined('CACHING_GROUP_ARTICLE') ? \CACHING_GROUP_ARTICLE : 'art', 'fk_options'];
            Shop::Container()->getCache()->set($key, $options, $tags, self::OPTIONS_CACHE_TTL);
        } catch (\Throwable $e) {
        }
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
                $sitzParam = \trim((string)($params['sitz'] ?? ''));
                $optCacheKey = 'fk_opts_' . \md5((string)\json_encode([
                    $field, $term, $typParam, $herstParam, $basisParam, $modellParam,
                    $serieParam, $baujahrParam, $fkOpts, $vehicleSeg, $sitzParam, $langID,
                ]));
                $cachedOptions = $this->optionsCacheGet($optCacheKey);
                if ($cachedOptions !== null) {
                    return new JsonResponse($cachedOptions, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $options = [];
                if ($vehicleSeg !== 'pkw'
                    && $sitzParam !== ''
                    && $serieParam !== ''
                    && \in_array($field, ['typ', 'hersteller', 'basis', 'modell'], true)
                ) {
                    $options = $this->repo->loadWmVehicleOptionsForSeatSeries(
                        $field,
                        $sitzParam,
                        $serieParam,
                        $typParam,
                        $herstParam,
                        $basisParam,
                        $modellParam
                    );
                }
                // PLKT-Serie gibi değerler kategori adıdır. Merkmal "Serie" ile
                // eşleşmezse listeyi bu ada göre boşaltma.
                if ($options === []) {
                    $options = $this->repo->loadMerkmalOptions(
                        $field,
                        $term,
                        $typParam,
                        $herstParam,
                        $basisParam,
                        $modellParam,
                        $baujahrParam,
                        $fkOpts,
                        $vehicleSeg,
                        ''
                    );
                }
                if ($options !== []) {
                    $this->optionsCacheSet($optCacheKey, $options);
                }

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
                'segment'    => $isPkw ? 'pkw' : 'wohnmobil',
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
                    ? md5($langID . '|pkw2|' . $herst . '|' . $modell . '|' . $baujahr . '|' . ($fkCustomModell ? 'familie' : 'std'))
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
                                if (\is_array($decoded) && $decoded !== []) {
                                    $decoded = $this->repo->reapplyKonfiguratorQueryOnSeriesItems($decoded, $selectionQuery);
                                    if ($decoded !== []) {
                                        return new JsonResponse($decoded, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                    }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Cache okuma hatası olursa normal hesaplamaya devam.
                }
            }

            // PKW: Serienliste erst nach Baujahr (wie Anasayfa-Konfigurator). Ausnahme: Freitext-Familie-Serie.
            if ($isPkw && $baujahr === '' && !($fkCustomModell && $modell !== '')) {
                return new JsonResponse([], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            if ($useStep4Cache && $step4CacheKey !== '' && $seriesItems !== []) {
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
            // Interne Fehlerdetails (Tabellennamen, SQL) niemals an den Client geben.
            // Detail nur ins PHP-Log schreiben.
            \error_log('[FilterKonfiguratorSearch] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

            return new JsonResponse([
                'error'   => true,
                'message' => 'Es ist ein interner Fehler aufgetreten.',
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } finally {
            try {
                // Schreiblast begrenzen: bei sehr häufigen Anfragen derselben IP
                // das Logging überspringen (Ergebnis wurde bereits ausgeliefert).
                // Fail-open ohne APCu → Verhalten wie zuvor (vollständiges Logging).
                $skipLog = PluginDatabase::throttle('log', (string)$logIp, 60, 10);

                if (!$skipLog && $db !== null && $mode !== 'series_meta') {
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

    /**
     * KI-gestützte Sitz-Erkennung: Foto wird nur im Arbeitsspeicher verarbeitet.
     */
    public function recognizeSeatPhoto(
        ServerRequestInterface $request,
        array $args,
        string $apiKey,
        string $model,
        bool $enabled
    ): ResponseInterface {
        if (!$enabled || \trim($apiKey) === '') {
            return new JsonResponse([
                'ok'      => false,
                'message' => 'Die Fotoerkennung ist momentan nicht verfügbar.',
            ], 503);
        }

        $ip = Request::getRealIP();
        if (PluginDatabase::throttle('seat-photo', $ip, 3, 60)) {
            return new JsonResponse([
                'ok'      => false,
                'message' => 'Bitte warten Sie eine Minute und versuchen Sie es erneut.',
            ], 429);
        }

        $file = $_FILES['seat_photo'] ?? null;
        if (!\is_array($file)) {
            return new JsonResponse([
                'ok'      => false,
                'message' => 'Bitte wählen Sie ein Foto aus.',
            ], 422);
        }

        try {
            $result = (new SeatImageRecognitionService($apiKey, $model))->recognize($file);
            $code   = (string)$result['seat_code'];
            $score  = (int)$result['confidence'];
            $match  = $code !== '' && $score >= 55;

            return new JsonResponse([
                'ok'         => true,
                'match'      => $match,
                'seat_code'  => $match ? $code : '',
                'confidence' => $score,
                'reason'     => (string)$result['reason'],
                'message'    => $match
                    ? 'Sitz wurde erkannt.'
                    : 'Der Sitz konnte nicht sicher erkannt werden. Bitte verwenden Sie ein deutlicheres Foto.',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'ok'      => false,
                'message' => $e->getMessage(),
            ], 422, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (SeatRecognitionApiException $e) {
            \error_log('[FilterKonfiguratorSearch] seat recognition failed: ' . $e->getMessage());

            return new JsonResponse([
                'ok'              => false,
                'diagnostic'      => $e->getDiagnosticCode(),
                'provider_status' => $e->getHttpStatus(),
                'message'         => $e->getPublicMessage(),
            ], 502, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            \error_log('[FilterKonfiguratorSearch] seat recognition failed: ' . $e->getMessage());

            return new JsonResponse([
                'ok'      => false,
                'message' => 'Das Foto konnte momentan nicht analysiert werden. Bitte versuchen Sie es später erneut.',
            ], 502, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * Maßanfertigung-Formular: /fk-anfrage (POST)
     */
    public function anfrage(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $ip = Request::getRealIP();
        if (PluginDatabase::throttle('anfrage', $ip, 5, 60)) {
            return new JsonResponse(['ok' => false, 'msg' => 'Bitte warten Sie einen Moment und versuchen Sie es erneut.'], 200);
        }

        $parsed = $request->getParsedBody();
        $body   = \is_array($parsed) ? $parsed : [];

        $phone    = \trim((string)($body['phone'] ?? ''));
        $vorname  = \trim((string)($body['vorname'] ?? ''));
        $nachname = \trim((string)($body['nachname'] ?? ''));
        $email    = \trim((string)($body['email'] ?? ''));
        $textBody = \trim((string)($body['body'] ?? ''));

        if ($email === '' || $vorname === '' || $nachname === '' || $phone === '') {
            return new JsonResponse(['ok' => false, 'msg' => 'Bitte alle Pflichtfelder ausfüllen.'], 200);
        }
        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['ok' => false, 'msg' => 'Bitte eine gültige E-Mail-Adresse eingeben.'], 200);
        }

        $empfaenger = 'info@bremer-sitzbezuege.de';
        $betreff = 'Sitzbezug Maßanfertigung – Anfrage von ' . $vorname . ' ' . $nachname;

        $text  = "Eine neue Anfrage aus dem Wohnmobil-Konfigurator (Maßanfertigung).\n\n";
        $text .= "--- Kontakt ---\n";
        $text .= 'Telefon: ' . $phone . "\n";
        $text .= 'Vorname: ' . $vorname . "\n";
        $text .= 'Nachname: ' . $nachname . "\n";
        $text .= 'E-Mail: ' . $email . "\n\n";
        if ($textBody !== '') {
            $text .= "--- Konfiguration / Nachricht ---\n" . $textBody . "\n\n";
        }

        $boundary = \md5(\uniqid((string)\mt_rand(), true));
        $headers  = "From: Womosi Konfigurator <" . $empfaenger . ">\r\n";
        $headers .= 'Reply-To: ' . $email . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . "\"\r\n";

        $mailbody  = '--' . $boundary . "\r\n";
        $mailbody .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mailbody .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mailbody .= \chunk_split(\base64_encode($text));

        if (!empty($_FILES['images'])) {
            $files = $_FILES['images'];
            $count = \is_array($files['name']) ? \count($files['name']) : 1;
            if ($count === 1 && !\is_array($files['name']) && $files['name'] !== '') {
                $files = [
                    'name'     => [$files['name']],
                    'type'     => [$files['type']],
                    'tmp_name' => [$files['tmp_name']],
                    'error'    => [$files['error']],
                ];
                $count = 1;
            }
            for ($i = 0; $i < $count; $i++) {
                $name = \is_array($files['name']) ? (string)$files['name'][$i] : (string)$files['name'];
                $tmp  = \is_array($files['tmp_name']) ? (string)$files['tmp_name'][$i] : (string)$files['tmp_name'];
                $err  = \is_array($files['error']) ? (int)$files['error'][$i] : (int)$files['error'];
                $type = \is_array($files['type']) ? (string)$files['type'][$i] : (string)$files['type'];
                if ($err !== \UPLOAD_ERR_OK || $name === '' || !\is_uploaded_file($tmp)) {
                    continue;
                }
                $data = \file_get_contents($tmp);
                if ($data === false) {
                    continue;
                }
                $safeName = \preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: 'bild.jpg';
                $mailbody .= '--' . $boundary . "\r\n";
                $mailbody .= 'Content-Type: ' . ($type !== '' ? $type : 'application/octet-stream') . '; name="' . $safeName . "\"\r\n";
                $mailbody .= "Content-Transfer-Encoding: base64\r\n";
                $mailbody .= 'Content-Disposition: attachment; filename="' . $safeName . "\"\r\n\r\n";
                $mailbody .= \chunk_split(\base64_encode($data));
            }
        }

        $mailbody .= '--' . $boundary . "--\r\n";
        $sent = @\mail($empfaenger, $betreff, $mailbody, $headers);

        if ($sent) {
            return new JsonResponse(['ok' => true, 'msg' => 'Anfrage wurde gesendet.'], 200);
        }

        return new JsonResponse([
            'ok'  => false,
            'msg' => 'E-Mail konnte nicht gesendet werden. Bitte versuchen Sie es später oder kontaktieren Sie uns direkt.',
        ], 200);
    }
}
