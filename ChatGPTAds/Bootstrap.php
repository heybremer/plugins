<?php

declare(strict_types=1);

namespace Plugin\ChatGPTAds;

use JTL\Checkout\Bestellung;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private const DEFAULT_PIXEL_ID = 'W3bQdmcVefJnyrpLetFegL';

    private const SDK_URL = 'https://bzrcdn.openai.com/sdk/oaiq.min.js';

    private const EVENTS_URL = 'https://bzr.openai.com/v1/events';

    private const K_ACTIVE = 'cgptads_active';
    private const K_PIXEL_ID = 'cgptads_pixel_id';
    private const K_DEBUG = 'cgptads_debug';
    private const K_BROWSER_CONVERSION = 'cgptads_browser_conversion';
    private const K_API_ACTIVE = 'cgptads_api_active';
    private const K_API_KEY = 'cgptads_api_key';

    private static bool $tablesEnsured = false;

    private static bool $adminMenuEnsured = false;

    /** @var array<string, string>|null */
    private ?array $settingsCache = null;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureTables();
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if ($this->isBackendRequest() || !isset($args['document'])) {
                return;
            }

            $this->injectPixel($args['document']);
            $this->injectBrowserConversion($args['document']);
        });

        $dispatcher->hookInto(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, function (array &$args): void {
            $orderId = (int)($args['kBestellung'] ?? $args['oBestellung']->kBestellung ?? 0);
            if ($orderId <= 0) {
                return;
            }

            $_SESSION['cgptads_order_created'] = $orderId;
            $this->sendOrderCreatedServerEvent($orderId);
        });
    }

    /**
     * @param mixed $doc JTL phpQuery document
     */
    private function injectPixel($doc): void
    {
        $settings = $this->loadSettings();
        $pixelId = \trim((string)($settings[self::K_PIXEL_ID] ?? self::DEFAULT_PIXEL_ID));
        if ((int)($settings[self::K_ACTIVE] ?? 1) !== 1 || !$this->isValidPixelId($pixelId)) {
            return;
        }

        try {
            if ($doc->find('#chatgpt-ads-pixel')->length > 0) {
                return;
            }

            $pixelJson = \json_encode($pixelId, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);
            $sdkJson = \json_encode(self::SDK_URL, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);
            $debug = (int)($settings[self::K_DEBUG] ?? 1) === 1 ? 'true' : 'false';
            if (!\is_string($pixelJson) || !\is_string($sdkJson)) {
                return;
            }

            $html = '<script id="chatgpt-ads-pixel">'
                . '!function(w,d,s,u){if(w.oaiq)return;var q=function(){q.q.push(arguments)};'
                . 'q.q=[];w.oaiq=q;var j=d.createElement(s);j.async=1;j.src=u;'
                . 'var f=d.getElementsByTagName(s)[0];f.parentNode.insertBefore(j,f)}'
                . '(window,document,"script",' . $sdkJson . ');'
                . 'oaiq("init",{pixelId:' . $pixelJson . ',debug:' . $debug . '});'
                . '</script>';

            $doc->find('head')->prepend($html);
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Pixel injection failed: ' . $e->getMessage());
        }
    }

    /**
     * @param mixed $doc JTL phpQuery document
     */
    private function injectBrowserConversion($doc): void
    {
        $orderId = (int)($_SESSION['cgptads_order_created'] ?? 0);
        if ($orderId <= 0) {
            return;
        }
        unset($_SESSION['cgptads_order_created']);

        $settings = $this->loadSettings();
        if ((int)($settings[self::K_ACTIVE] ?? 1) !== 1
            || (int)($settings[self::K_BROWSER_CONVERSION] ?? 1) !== 1
        ) {
            return;
        }

        $eventData = $this->buildOrderEventData($orderId);
        if ($eventData === null) {
            return;
        }
        $eventId = 'jtl-order-' . $orderId;
        $dataJson = \json_encode(
            $eventData,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
            | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT
        );
        $eventIdJson = \json_encode(
            $eventId,
            \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT
        );
        if (!\is_string($dataJson) || !\is_string($eventIdJson)) {
            return;
        }

        $script = '<script id="chatgpt-ads-order-created">'
            . '(function(){if(window.__cgptAdsOrderCreated)return;'
            . 'window.__cgptAdsOrderCreated=true;'
            . 'if(typeof window.oaiq==="function"){'
            . 'window.oaiq("measure","order_created",' . $dataJson
            . ',{event_id:' . $eventIdJson . '});'
            . '}})();'
            . '</script>';

        try {
            if ($doc->find('#chatgpt-ads-order-created')->length < 1) {
                $doc->find('body')->append($script);
            }
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Browser conversion injection failed: ' . $e->getMessage());
        }
    }

    private function sendOrderCreatedServerEvent(int $orderId): void
    {
        $settings = $this->loadSettings();
        $pixelId = \trim((string)($settings[self::K_PIXEL_ID] ?? self::DEFAULT_PIXEL_ID));
        $apiKey = \trim((string)($settings[self::K_API_KEY] ?? ''));

        if ((int)($settings[self::K_ACTIVE] ?? 1) !== 1
            || (int)($settings[self::K_API_ACTIVE] ?? 0) !== 1
            || !$this->isValidPixelId($pixelId)
            || $apiKey === ''
        ) {
            return;
        }

        $eventData = $this->buildOrderEventData($orderId);
        if ($eventData === null) {
            return;
        }

        $eventId = 'jtl-order-' . $orderId;
        if (!$this->claimServerEvent($orderId, $eventId)) {
            return;
        }

        if (!\function_exists('curl_init')) {
            $this->finishServerEvent($orderId, 'failed', 0, 'PHP cURL ist nicht verfügbar.');
            return;
        }

        $payload = [
            'validate_only' => false,
            'events' => [[
                'id' => $eventId,
                'type' => 'order_created',
                'timestamp_ms' => (int)\floor(\microtime(true) * 1000),
                'source_url' => $this->currentSourceUrl(),
                'action_source' => 'web',
                'data' => $eventData,
            ]],
        ];
        $body = \json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (!\is_string($body)) {
            $this->finishServerEvent($orderId, 'failed', 0, 'JSON konnte nicht erstellt werden.');
            return;
        }

        $curl = \curl_init(self::EVENTS_URL . '?pid=' . \rawurlencode($pixelId));
        if ($curl === false) {
            $this->finishServerEvent($orderId, 'failed', 0, 'cURL konnte nicht gestartet werden.');
            return;
        }

        \curl_setopt_array($curl, [
            \CURLOPT_POST => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CONNECTTIMEOUT => 5,
            \CURLOPT_TIMEOUT => 15,
            \CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            \CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = \curl_exec($curl);
        $status = (int)\curl_getinfo($curl, \CURLINFO_RESPONSE_CODE);
        $curlError = \curl_error($curl);
        \curl_close($curl);

        $message = $curlError !== ''
            ? $curlError
            : (\is_string($raw) ? $raw : 'Leere API-Antwort');
        $message = $this->sanitizeLogMessage($message);

        if ($status >= 200 && $status < 300 && $curlError === '') {
            $this->finishServerEvent($orderId, 'sent', $status, $message);
            return;
        }

        $this->finishServerEvent($orderId, 'failed', $status, $message);
    }

    /**
     * OpenAI Ads expects monetary amounts in the currency's minor unit.
     *
     * @return array{type:string, amount:int, currency:string, contents:array<int, array<string, int|string>>}|null
     */
    private function buildOrderEventData(int $orderId): ?array
    {
        try {
            $order = new Bestellung($orderId, true, Shop::Container()->getDB());
            if ((int)($order->kBestellung ?? 0) <= 0) {
                return null;
            }

            $currency = 'EUR';
            $orderCurrency = $order->Waehrung ?? null;
            if (\is_object($orderCurrency)) {
                if (\method_exists($orderCurrency, 'getCode')) {
                    $currency = (string)$orderCurrency->getCode();
                } elseif (\method_exists($orderCurrency, 'getISO')) {
                    $currency = (string)$orderCurrency->getISO();
                }
            }
            $currency = \strtoupper(\trim($currency));
            if (\preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                $currency = 'EUR';
            }

            $contents = [];
            foreach ((array)($order->Positionen ?? []) as $position) {
                if ((int)($position->nPosTyp ?? 0) !== 1) {
                    continue;
                }

                $name = $position->cName ?? '';
                if (\is_array($name)) {
                    $name = (string)($name[0] ?? '');
                }
                $item = [
                    'id' => (string)($position->cArtNr ?? ''),
                    'name' => \trim((string)$name),
                    'content_type' => 'product',
                    'quantity' => \max(1, (int)($position->nAnzahl ?? 1)),
                ];
                if ($item['id'] === '' && $item['name'] === '') {
                    continue;
                }
                $contents[] = $item;
                if (\count($contents) >= 100) {
                    break;
                }
            }

            return [
                'type' => 'contents',
                'amount' => \max(0, (int)\round((float)($order->fGesamtsumme ?? 0) * 100)),
                'currency' => $currency,
                'contents' => $contents,
            ];
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Order payload failed: ' . $e->getMessage());
            return null;
        }
    }

    private function claimServerEvent(int $orderId, string $eventId): bool
    {
        try {
            $db = Shop::Container()->getDB();
            $existing = $db->getSingleObject(
                'SELECT kBestellung FROM twomosi_chatgpt_ads_events WHERE kBestellung = :id LIMIT 1',
                ['id' => $orderId]
            );
            if ($existing !== null) {
                return false;
            }

            $db->executeQueryPrepared(
                'INSERT INTO twomosi_chatgpt_ads_events
                    (kBestellung, cEventId, cStatus, nHttpStatus, cResponse, dCreated, dUpdated)
                 VALUES (:orderId, :eventId, "processing", 0, "", NOW(), NOW())',
                ['orderId' => $orderId, 'eventId' => $eventId]
            );

            return true;
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Event claim failed: ' . $e->getMessage());
            return false;
        }
    }

    private function finishServerEvent(int $orderId, string $status, int $httpStatus, string $message): void
    {
        try {
            Shop::Container()->getDB()->executeQueryPrepared(
                'UPDATE twomosi_chatgpt_ads_events
                    SET cStatus = :status,
                        nHttpStatus = :httpStatus,
                        cResponse = :response,
                        dUpdated = NOW()
                  WHERE kBestellung = :orderId',
                [
                    'status' => $status,
                    'httpStatus' => $httpStatus,
                    'response' => $message,
                    'orderId' => $orderId,
                ]
            );
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Event status update failed: ' . $e->getMessage());
        }
    }

    private function sanitizeLogMessage(string $message): string
    {
        $message = (string)\preg_replace('/Bearer\s+[^\s",]+/i', 'Bearer ***', $message);
        $message = (string)\preg_replace('/\b(sk|oai)-[A-Za-z0-9_-]+\b/', '$1-***', $message);

        return \mb_substr($message, 0, 1000);
    }

    private function currentSourceUrl(): string
    {
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        if ($path === '' || !\str_starts_with($path, '/')) {
            return $shopUrl;
        }

        $shopParts = \parse_url($shopUrl);
        $origin = '';
        if (\is_array($shopParts) && !empty($shopParts['host'])) {
            $origin = (string)($shopParts['scheme'] ?? 'https') . '://' . (string)$shopParts['host'];
            if (isset($shopParts['port'])) {
                $origin .= ':' . (int)$shopParts['port'];
            }
        }
        $query = (string)(\parse_url($uri, \PHP_URL_QUERY) ?? '');

        return ($origin !== '' ? $origin : $shopUrl) . $path . ($query !== '' ? '?' . $query : '');
    }

    private function isValidPixelId(string $pixelId): bool
    {
        return \preg_match('/^[A-Za-z0-9_-]{8,100}$/', $pixelId) === 1;
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }

    /** @return array<string, string> */
    public function loadSettings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT cKey, cValue FROM twomosi_chatgpt_ads_settings'
            );
            $settings = [];
            foreach ($rows as $row) {
                $settings[(string)$row->cKey] = (string)$row->cValue;
            }
            $this->settingsCache = $settings;
        } catch (\Throwable $e) {
            $this->settingsCache = [];
        }

        return $this->settingsCache;
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_chatgpt_ads_settings (
                    cKey VARCHAR(100) NOT NULL,
                    cValue TEXT NOT NULL DEFAULT "",
                    PRIMARY KEY (cKey)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_chatgpt_ads_events (
                    kBestellung INT NOT NULL,
                    cEventId VARCHAR(190) NOT NULL,
                    cStatus VARCHAR(20) NOT NULL DEFAULT "processing",
                    nHttpStatus INT NOT NULL DEFAULT 0,
                    cResponse TEXT NOT NULL DEFAULT "",
                    dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (kBestellung),
                    UNIQUE KEY uk_event_id (cEventId),
                    KEY idx_status (cStatus)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $defaults = [
                self::K_ACTIVE => '1',
                self::K_PIXEL_ID => self::DEFAULT_PIXEL_ID,
                self::K_DEBUG => '1',
                self::K_BROWSER_CONVERSION => '1',
                self::K_API_ACTIVE => '0',
                self::K_API_KEY => '',
            ];
            foreach ($defaults as $key => $value) {
                $db->executeQueryPrepared(
                    'INSERT IGNORE INTO twomosi_chatgpt_ads_settings (cKey, cValue) VALUES (:key, :value)',
                    ['key' => $key, 'value' => $value]
                );
            }

            self::$tablesEnsured = true;
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Table setup failed: ' . $e->getMessage());
        }
    }

    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }

        try {
            $db = Shop::Container()->getDB();
            $pluginId = (int)$this->getPlugin()->getID();
            if ($pluginId <= 0) {
                return;
            }

            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin = :pluginId
                    AND cDateiname = :file
                  LIMIT 1',
                ['pluginId' => $pluginId, 'file' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu = new \stdClass();
                $menu->kPlugin = $pluginId;
                $menu->cName = 'ChatGPT Ads';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 25;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }

            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            \error_log('[ChatGPTAds] Admin menu setup failed: ' . $e->getMessage());
        }
    }
}
