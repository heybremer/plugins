<?php

namespace Plugin\FilterKonfiguratorSearch\Service;

final class SeatRecognitionApiException extends \RuntimeException
{
    private string $diagnosticCode;
    private string $publicMessage;
    private int $httpStatus;

    public function __construct(
        string $diagnosticCode,
        string $publicMessage,
        int $httpStatus,
        string $providerDetail = ''
    ) {
        parent::__construct(
            $diagnosticCode . ' (HTTP ' . $httpStatus . ')'
            . ($providerDetail !== '' ? ': ' . $providerDetail : '')
        );
        $this->diagnosticCode = $diagnosticCode;
        $this->publicMessage = $publicMessage;
        $this->httpStatus = $httpStatus;
    }

    public function getDiagnosticCode(): string
    {
        return $this->diagnosticCode;
    }

    public function getPublicMessage(): string
    {
        return $this->publicMessage;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class SeatImageRecognitionService
{
    private const MAX_FILE_SIZE = 8 * 1024 * 1024;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpeg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** @var array<string, string> */
    private const SEATS = [
        '1'  => 'Aguti Milan 12',
        '2'  => 'Aguti Liner 12',
        '3'  => 'ISRI Captain Chair',
        '4'  => 'ISRI CC mit Gurt',
        '5'  => 'ISRI Ducato / Jumper / Boxer, Bj. 2006–2014',
        '6'  => 'Aguti Milan',
        '7'  => 'Aguti GIS-Liner',
        '8'  => 'Aguti GIS-Liner 3',
        '9'  => 'Ford Transit ab Bj. 2014',
        '10' => 'Ford Transit ab Bj. 2014 mit integrierter Kopfstütze',
        '11' => 'Aguti Milan 3',
        '12' => 'SKA 1800 M23 Komfort',
        '13' => 'SKA 1800/1000 M23 Classic',
        '14' => 'Mercedes-Benz Sprinter / Hymer',
    ];

    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-5.6')
    {
        $this->apiKey = \trim($apiKey);
        $this->model  = \trim($model) !== '' ? \trim($model) : 'gpt-5.6';
    }

    /**
     * @param array<string, mixed> $file
     *
     * @return array{seat_code:string, confidence:int, reason:string}
     */
    public function recognize(array $file): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('OpenAI API anahtarı yapılandırılmamış.');
        }
        if (!\function_exists('curl_init')) {
            throw new \RuntimeException('Sunucuda cURL desteği bulunamadı.');
        }

        [$tmpPath, $mimeType] = $this->validateUpload($file);
        $imageData = \file_get_contents($tmpPath);
        if ($imageData === false || $imageData === '') {
            throw new \InvalidArgumentException('Fotoğraf okunamadı.');
        }

        $payload  = $this->buildPayload(
            'data:' . $mimeType . ';base64,' . \base64_encode($imageData)
        );
        $response = $this->requestOpenAi($payload);

        return self::parseResponse($response);
    }

    /**
     * @param array<string, mixed> $file
     *
     * @return array{0:string,1:string}
     */
    private function validateUpload(array $file): array
    {
        $error = (int)($file['error'] ?? \UPLOAD_ERR_NO_FILE);
        if ($error !== \UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(
                $error === \UPLOAD_ERR_INI_SIZE || $error === \UPLOAD_ERR_FORM_SIZE
                    ? 'Das Foto ist zu groß.'
                    : 'Bitte wählen Sie ein Foto aus.'
            );
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size    = (int)($file['size'] ?? 0);
        if ($tmpPath === '' || !\is_uploaded_file($tmpPath)) {
            throw new \InvalidArgumentException('Ungültiger Datei-Upload.');
        }
        if ($size < 1 || $size > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Das Foto darf maximal 8 MB groß sein.');
        }

        $finfo = \finfo_open(\FILEINFO_MIME_TYPE);
        $mime  = $finfo !== false ? (string)\finfo_file($finfo, $tmpPath) : '';
        if ($finfo !== false) {
            \finfo_close($finfo);
        }
        if (!isset(self::ALLOWED_MIME_TYPES[$mime]) || @\getimagesize($tmpPath) === false) {
            throw new \InvalidArgumentException('Bitte verwenden Sie ein gültiges JPG-, PNG- oder WebP-Foto.');
        }

        return [$tmpPath, $mime];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $userImage): array
    {
        $content = [
            [
                'type' => 'input_text',
                'text' => 'Vergleiche das erste Foto eines Fahrzeugsitzes mit der anschließend gezeigten Referenztafel. '
                    . 'Bewerte JEDE Referenz unabhängig mit einem visuellen Ähnlichkeitswert von 0 bis 100. '
                    . 'Bevorzuge niemals die erste Referenz und verwende kein allgemeines Markenwissen. '
                    . 'Achte besonders auf Kopfstütze, Armlehnen, Gurtführung, Rückenlehne, Sitzkontur und Nähte. '
                    . 'Die blauen REFERENCE-Nummern sind verbindlich und gehören jeweils zum Bild direkt darunter. '
                    . 'Identische Bilder müssen exakt den auf der Tafel aufgedruckten Referenzcode mit 100 erhalten. '
                    . 'Antworte kurz auf Deutsch.',
            ],
            [
                'type'      => 'input_image',
                'image_url' => $userImage,
                'detail'    => 'high',
            ],
        ];

        $content[] = [
            'type'      => 'input_image',
            'image_url' => 'https://bremer-sitzbezuege.de/plugins/FilterKonfiguratorSearch/templates/sitz-reference-sheet.jpg',
            'detail'    => 'high',
        ];

        $scoreProperties = [];
        $scoreRequired = [];
        foreach (\array_keys(self::SEATS) as $code) {
            $code = (string)$code;
            $scoreProperties[$code] = [
                'type'    => 'integer',
                'minimum' => 0,
                'maximum' => 100,
            ];
            $scoreRequired[] = $code;
        }

        $payload = [
            'model' => $this->model,
            'input' => [[
                'role'    => 'user',
                'content' => $content,
            ]],
            'text' => [
                'format' => [
                    'type'   => 'json_schema',
                    'name'   => 'seat_recognition',
                    'strict' => true,
                    'schema' => [
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'properties'           => [
                            'scores' => [
                                'type'                 => 'object',
                                'additionalProperties' => false,
                                'properties'           => (object)$scoreProperties,
                                'required'             => $scoreRequired,
                            ],
                            'reason' => [
                                'type'      => 'string',
                                'maxLength' => 240,
                            ],
                        ],
                        'required' => ['scores', 'reason'],
                    ],
                ],
            ],
            'max_output_tokens' => 4000,
        ];
        if (\str_starts_with(\strtolower($this->model), 'gpt-5')) {
            $payload['reasoning'] = ['effort' => 'high'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function requestOpenAi(array $payload): array
    {
        $body = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if (!\is_string($body)) {
            throw new \RuntimeException('OpenAI isteği hazırlanamadı.');
        }

        $curl = \curl_init('https://api.openai.com/v1/responses');
        if ($curl === false) {
            throw new \RuntimeException('OpenAI bağlantısı başlatılamadı.');
        }
        \curl_setopt_array($curl, [
            \CURLOPT_POST           => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CONNECTTIMEOUT => 8,
            \CURLOPT_TIMEOUT        => 40,
            \CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            \CURLOPT_POSTFIELDS     => $body,
        ]);
        $raw    = \curl_exec($curl);
        $status = (int)\curl_getinfo($curl, \CURLINFO_RESPONSE_CODE);
        $error  = \curl_error($curl);
        \curl_close($curl);

        if (!\is_string($raw) || $raw === '' || $error !== '') {
            throw new \RuntimeException('OpenAI ist nicht erreichbar.');
        }
        $decoded = \json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !\is_array($decoded)) {
            $errorData = \is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
            $providerCode = \strtolower(\trim((string)($errorData['code'] ?? '')));
            $providerType = \strtolower(\trim((string)($errorData['type'] ?? '')));
            $providerMessage = \trim((string)($errorData['message'] ?? ''));
            $providerMessage = \mb_substr(
                (string)\preg_replace('/sk-[A-Za-z0-9_-]+/', 'sk-***', $providerMessage),
                0,
                500
            );

            [$diagnosticCode, $publicMessage] = self::classifyApiError(
                $status,
                $providerCode,
                $providerType
            );
            throw new SeatRecognitionApiException(
                $diagnosticCode,
                $publicMessage,
                $status,
                $providerMessage
            );
        }

        return $decoded;
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function classifyApiError(int $status, string $providerCode, string $providerType): array
    {
        $needle = \strtolower($providerCode . ' ' . $providerType);
        if ($status === 401 || \str_contains($needle, 'invalid_api_key')) {
            return ['openai_auth', 'Der OpenAI API-Key ist ungültig oder wurde deaktiviert.'];
        }
        if (\str_contains($needle, 'insufficient_quota')) {
            return ['openai_quota', 'Das OpenAI API-Kontingent ist aufgebraucht oder für das Projekt nicht freigegeben.'];
        }
        if ($status === 429 || \str_contains($needle, 'rate_limit')) {
            return ['openai_rate_limit', 'OpenAI hat das Anfragelimit erreicht. Bitte versuchen Sie es später erneut.'];
        }
        if (\str_contains($needle, 'model_not_found')) {
            return ['openai_model', 'Das konfigurierte OpenAI-Modell ist für dieses Projekt nicht verfügbar.'];
        }
        if ($status === 400 || \str_contains($needle, 'invalid_request')) {
            return ['openai_request', 'OpenAI hat die Analyse-Anfrage als ungültig abgelehnt.'];
        }
        if ($status >= 500) {
            return ['openai_server', 'OpenAI ist momentan nicht verfügbar.'];
        }

        return ['openai_unknown', 'OpenAI konnte die Analyse nicht ausführen.'];
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array{seat_code:string, confidence:int, reason:string}
     */
    public static function parseResponse(array $response): array
    {
        $text = isset($response['output_text']) ? \trim((string)$response['output_text']) : '';
        if ($text === '' && isset($response['output']) && \is_array($response['output'])) {
            foreach ($response['output'] as $output) {
                if (!\is_array($output) || !isset($output['content']) || !\is_array($output['content'])) {
                    continue;
                }
                foreach ($output['content'] as $content) {
                    if (\is_array($content) && isset($content['text'])) {
                        $text = \trim((string)$content['text']);
                        if ($text !== '') {
                            break 2;
                        }
                    }
                }
            }
        }

        $result = \json_decode($text, true);
        if (!\is_array($result)) {
            throw new \RuntimeException('OpenAI yanıtı doğrulanamadı.');
        }

        $reason     = \trim((string)($result['reason'] ?? ''));
        if (isset($result['scores']) && \is_array($result['scores'])) {
            $scores = [];
            foreach (\array_keys(self::SEATS) as $knownCode) {
                $knownCode = (string)$knownCode;
                $scores[$knownCode] = \max(0, \min(100, (int)($result['scores'][$knownCode] ?? 0)));
            }
            \arsort($scores, \SORT_NUMERIC);
            $codes = \array_keys($scores);
            $code = (string)($codes[0] ?? '');
            $best = (int)($scores[$code] ?? 0);
            $second = isset($codes[1]) ? (int)($scores[(string)$codes[1]] ?? 0) : 0;
            $confidence = ($best - $second) >= 6 ? $best : \min(54, $best);
        } else {
            // Rückwärtskompatibilität für bereits zwischengespeicherte Antworten.
            $code = \trim((string)($result['seat_code'] ?? ''));
            $confidence = \max(0, \min(100, (int)($result['confidence'] ?? 0)));
        }
        if ($code !== '' && !isset(self::SEATS[$code])) {
            throw new \RuntimeException('OpenAI geçersiz koltuk kodu döndürdü.');
        }

        return [
            'seat_code' => $code,
            'confidence' => $confidence,
            'reason' => $reason,
        ];
    }
}
