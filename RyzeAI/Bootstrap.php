<?php

declare(strict_types=1);

namespace Plugin\RyzeAI;

use JTL\Checkout\Bestellung;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Session\Frontend;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private const DEFAULT_KEY = 'rz_pk_4f5b9795b424d693ae35cf13094f76bc';

    private const SCRIPT_BASE = 'https://px.get-ryze.ai/px.js';

    private const S2S_URL = 'https://px.get-ryze.ai/s2s';

    private const K_ACTIVE = 'ryzeai_active';

    private const K_PIXEL_KEY = 'ryzeai_pixel_key';

    private const K_S2S_ACTIVE = 'ryzeai_s2s_active';

    private const K_SECRET = 'ryzeai_secret';

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
        });

        $dispatcher->hookInto(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, function (array &$args): void {
            $orderId = (int)($args['kBestellung'] ?? $args['oBestellung']->kBestellung ?? 0);
            if ($orderId > 0) {
                $this->sendPurchase($orderId);
            }
        });
    }

    /**
     * @param mixed $doc JTL phpQuery document
     */
    private function injectPixel($doc): void
    {
        $settings = $this->loadSettings();
        $key = \trim((string)($settings[self::K_PIXEL_KEY] ?? self::DEFAULT_KEY));
        if ((int)($settings[self::K_ACTIVE] ?? 1) !== 1 || !$this->isValidKey($key)) {
            return;
        }

        try {
            if ($doc->find('#ryzeai-pixel')->length > 0) {
                return;
            }

            $src = self::SCRIPT_BASE . '?key=' . \rawurlencode($key);
            $html = '<script id="ryzeai-pixel" src="'
                . \htmlspecialchars($src, \ENT_QUOTES, 'UTF-8')
                . '"></script>' . $this->identifyScript();
            $doc->find('head')->prepend($html);
        } catch (\Throwable $e) {
            \error_log('[RyzeAI] Pixel injection failed: ' . $e->getMessage());
        }
    }

    private function identifyScript(): string
    {
        try {
            $customer = Frontend::getCustomer();
        } catch (\Throwable $e) {
            return '';
        }
        if (!\is_object($customer) || (int)($customer->kKunde ?? 0) <= 0) {
            return '';
        }

        $email = \trim((string)($customer->cMail ?? ''));
        $phone = \trim((string)($customer->cMobil ?? $customer->cTel ?? ''));
        $payload = [];
        if (\str_contains($email, '@')) {
            $payload['email'] = $email;
        }
        if ($phone !== '') {
            $payload['phone'] = $phone;
        }
        if ($payload === []) {
            return '';
        }

        $json = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);
        if (!\is_string($json)) {
            return '';
        }

        return '<script id="ryzeai-identify">(function(){var p=' . $json
            . ';var n=0;function run(){if(window.ryze&&typeof ryze.identify==="function"){ryze.identify(p);return;}'
            . 'if(++n<20){setTimeout(run,250);}}run();})();</script>';
    }

    private function sendPurchase(int $orderId): void
    {
        $settings = $this->loadSettings();
        $pixelKey = \trim((string)($settings[self::K_PIXEL_KEY] ?? self::DEFAULT_KEY));
        $secret = \trim((string)($settings[self::K_SECRET] ?? ''));
        if ((int)($settings[self::K_ACTIVE] ?? 1) !== 1
            || (int)($settings[self::K_S2S_ACTIVE] ?? 1) !== 1
            || !$this->isValidKey($pixelKey)
            || !$this->isValidSecret($secret)
        ) {
            return;
        }

        $event = $this->buildPurchaseEvent($orderId);
        if ($event === null || !$this->claimEvent($orderId, (string)$event['order_id'])) {
            return;
        }
        if (!\function_exists('curl_init')) {
            $this->finishEvent($orderId, 'failed', 0, 'PHP cURL ist nicht verfügbar.');
            return;
        }

        $body = \json_encode(['events' => [$event]], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if (!\is_string($body)) {
            $this->finishEvent($orderId, 'failed', 0, 'JSON konnte nicht erstellt werden.');
            return;
        }

        $curl = \curl_init(self::S2S_URL);
        if ($curl === false) {
            $this->finishEvent($orderId, 'failed', 0, 'cURL konnte nicht gestartet werden.');
            return;
        }
        \curl_setopt_array($curl, [
            \CURLOPT_POST => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_CONNECTTIMEOUT => 5,
            \CURLOPT_TIMEOUT => 15,
            \CURLOPT_HTTPHEADER => [
                'x-ryze-key: ' . $pixelKey,
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/json',
            ],
            \CURLOPT_POSTFIELDS => $body,
        ]);
        $raw = \curl_exec($curl);
        $status = (int)\curl_getinfo($curl, \CURLINFO_RESPONSE_CODE);
        $error = \curl_error($curl);
        \curl_close($curl);

        $message = $error !== '' ? $error : (\is_string($raw) ? $raw : 'Leere API-Antwort');
        $message = \mb_substr((string)\preg_replace('/rz_sk_[a-f0-9]+/i', 'rz_sk_***', $message), 0, 1000);
        $this->finishEvent($orderId, ($status >= 200 && $status < 300 && $error === '') ? 'sent' : 'failed', $status, $message);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPurchaseEvent(int $orderId): ?array
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
                $qty = \max(1, (int)($position->nAnzahl ?? 1));
                $line = (float)($position->fGesamtpreis ?? 0);
                $unit = $line > 0 ? $line / $qty : (float)($position->fPreis ?? 0);
                $contents[] = [
                    'id' => (string)($position->cArtNr ?? ''),
                    'quantity' => $qty,
                    'item_price' => \round($unit, 2),
                ];
                if (\count($contents) >= 100) {
                    break;
                }
            }

            $customer = $order->oKunde ?? null;
            $billing = $order->oRechnungsadresse ?? null;
            $email = \trim((string)($customer->cMail ?? $billing->cMail ?? ''));
            $phone = \trim((string)($billing->cMobil ?? $billing->cTel ?? $customer->cMobil ?? $customer->cTel ?? ''));
            $user = [];
            if (\str_contains($email, '@')) {
                $user['email'] = $email;
            }
            if ($phone !== '') {
                $user['phone'] = $phone;
            }

            $orderNumber = \trim((string)($order->cBestellNr ?? ''));
            $event = [
                'event' => 'purchase',
                'order_id' => $orderNumber !== '' ? $orderNumber : (string)$orderId,
                'value' => \round((float)($order->fGesamtsumme ?? 0), 2),
                'currency' => $currency,
                'contents' => $contents,
            ];
            if ($user !== []) {
                $event['user'] = $user;
            }

            return $event;
        } catch (\Throwable $e) {
            \error_log('[RyzeAI] Purchase payload failed: ' . $e->getMessage());
            return null;
        }
    }

    private function claimEvent(int $orderId, string $orderNumber): bool
    {
        try {
            $db = Shop::Container()->getDB();
            $existing = $db->getSingleObject(
                'SELECT kBestellung FROM twomosi_ryzeai_events WHERE kBestellung = :id LIMIT 1',
                ['id' => $orderId]
            );
            if ($existing !== null) {
                return false;
            }
            $db->executeQueryPrepared(
                'INSERT INTO twomosi_ryzeai_events
                    (kBestellung, cOrderId, cStatus, nHttpStatus, cResponse, dCreated, dUpdated)
                 VALUES (:orderId, :orderNumber, "processing", 0, "", NOW(), NOW())',
                ['orderId' => $orderId, 'orderNumber' => $orderNumber]
            );

            return true;
        } catch (\Throwable $e) {
            \error_log('[RyzeAI] Event claim failed: ' . $e->getMessage());
            return false;
        }
    }

    private function finishEvent(int $orderId, string $status, int $httpStatus, string $message): void
    {
        try {
            Shop::Container()->getDB()->executeQueryPrepared(
                'UPDATE twomosi_ryzeai_events
                    SET cStatus = :status, nHttpStatus = :httpStatus, cResponse = :response, dUpdated = NOW()
                  WHERE kBestellung = :orderId',
                ['status' => $status, 'httpStatus' => $httpStatus, 'response' => $message, 'orderId' => $orderId]
            );
        } catch (\Throwable $e) {
            \error_log('[RyzeAI] Event status update failed: ' . $e->getMessage());
        }
    }

    private function isValidKey(string $key): bool
    {
        return \preg_match('/^rz_pk_[a-f0-9]{16,128}$/', $key) === 1;
    }

    private function isValidSecret(string $secret): bool
    {
        return \preg_match('/^rz_sk_[a-f0-9]{32,256}$/', $secret) === 1;
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
                'SELECT cKey, cValue FROM twomosi_ryzeai_settings'
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
                'CREATE TABLE IF NOT EXISTS twomosi_ryzeai_settings (
                    cKey VARCHAR(100) NOT NULL,
                    cValue TEXT NOT NULL DEFAULT "",
                    PRIMARY KEY (cKey)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_ryzeai_events (
                    kBestellung INT NOT NULL,
                    cOrderId VARCHAR(64) NOT NULL DEFAULT "",
                    cStatus VARCHAR(20) NOT NULL DEFAULT "processing",
                    nHttpStatus INT NOT NULL DEFAULT 0,
                    cResponse TEXT NOT NULL DEFAULT "",
                    dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (kBestellung)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $defaults = [
                self::K_ACTIVE => '1',
                self::K_PIXEL_KEY => self::DEFAULT_KEY,
                self::K_S2S_ACTIVE => '1',
                self::K_SECRET => '',
            ];
            foreach ($defaults as $key => $value) {
                $db->executeQueryPrepared(
                    'INSERT IGNORE INTO twomosi_ryzeai_settings (cKey, cValue) VALUES (:key, :value)',
                    ['key' => $key, 'value' => $value]
                );
            }
            self::$tablesEnsured = true;
        } catch (\Throwable $e) {
            \error_log('[RyzeAI] Table setup failed: ' . $e->getMessage());
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
                $menu->cName = 'RyzeAI';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 26;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            \error_log('[RyzeAI] Admin menu setup failed: ' . $e->getMessage());
        }
    }
}
