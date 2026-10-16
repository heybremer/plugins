<?php

declare(strict_types=1);

namespace Plugin\SalesProduct;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

/**
 * Speichert eine grobe Marketing-Kanal-Attribution pro Bestellung.
 *
 * - Erster/folgender Shop-Besuch: UTM, gclid/fbclid, Referrer → Cookie (90 Tage).
 * - Nach Bestellabschluss (DB): eine Zeile in twomosi_sales_attribution.
 */
class Bootstrap extends Bootstrapper
{
    private const COOKIE_NAME = 'womosi_sp_attr_v1';

    private const COOKIE_TTL_SEC = 7776000; // 90 Tage

    private static bool $tablesEnsured = false;

    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
            if ($this->maybeServePublicReport()) {
                return;
            }
            $this->maybeRefreshAttributionCookie();
        });

        $dispatcher->hookInto(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, function (array &$args): void {
            $this->persistAttributionForOrder($args);
        });
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_sales_attribution (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kBestellung INT NOT NULL,
                    cChannel VARCHAR(64) NOT NULL,
                    cChannelLabel VARCHAR(512) NOT NULL DEFAULT "",
                    cAttributionJson LONGTEXT NULL,
                    dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_kBestellung (kBestellung),
                    KEY idx_dCreated (dCreated),
                    KEY idx_cChannel (cChannel)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_sales_config (
                    cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                    cValue VARCHAR(512) NOT NULL DEFAULT ""
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            self::$tablesEnsured = true;
        } catch (\Throwable $e) {
            // Schema optional beim ersten Admin-Hit
        }
    }

    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }
            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin AND cDateiname = :c LIMIT 1',
                ['kPlugin' => $pluginID, 'c' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu = new \stdClass();
                $menu->kPlugin = $pluginID;
                $menu->cName = 'Sales / Kanal';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 85;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Öffentlicher HTML-Rapor: ?sp_salesreport=1&token=…&days=30
     */
    private function maybeServePublicReport(): bool
    {
        if (empty($_GET['sp_salesreport'])) {
            return false;
        }
        $token = \trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            if (!\headers_sent()) {
                \http_response_code(403);
            }
            echo '403 Forbidden';
            exit;
        }

        $this->ensureTables();
        $adminDir = $this->getPlugin()->getPaths()->getBasePath() . 'adminmenu/';
        require_once $adminDir . 'sp_helpers.php';
        require_once $adminDir . 'report_lib.php';

        $expected = \sp_get_report_token(false);
        if ($expected === '' || !\hash_equals($expected, $token)) {
            if (!\headers_sent()) {
                \http_response_code(403);
            }
            echo '403 Forbidden';
            exit;
        }

        $spReportPublic = true;
        $reportToken = $token;
        require $adminDir . 'report_view.php';
        exit;
    }

    /**
     * Aktuelle Anfrage auswerten und First-/Last-Touch im Cookie halten.
     */
    private function maybeRefreshAttributionCookie(): void
    {
        if (\headers_sent()) {
            return;
        }
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        // Backend-Installer / Wawi-Sync grob auslassen
        if (\stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false) {
            return;
        }
        // Während Checkout/Payment-Return KEIN Last-Touch überschreiben — sonst landet
        // payments.amazon.de / paypal.com / klarna.com im Attribution-Cookie.
        if ($this->isCheckoutOrPaymentReturn($uri)) {
            return;
        }

        $snap = $this->buildSnapshotFromRequest();
        if ($snap === null) {
            return;
        }

        $existing = $this->readCookiePayload();
        $now = \gmdate('c');
        $first = \is_array($existing['first'] ?? null) ? $existing['first'] : null;
        if ($first === null || ($first['channel'] ?? '') === '') {
            $first = \array_merge($snap, ['at' => $now]);
        }
        $payload = [
            'first' => $first,
            'last'  => \array_merge($snap, ['at' => $now]),
        ];

        $this->writeAttributionCookie($payload);
    }

    /**
     * @return array<string, mixed>|null channel, label, source_detail
     */
    private function buildSnapshotFromRequest(): ?array
    {
        $gclid = \trim((string)($_GET['gclid'] ?? ''));
        $gbraid = \trim((string)($_GET['gbraid'] ?? ''));
        $wbraid = \trim((string)($_GET['wbraid'] ?? ''));
        $fbclid = \trim((string)($_GET['fbclid'] ?? ''));
        // Performance-Max / Merchant-Center / Shopping-Indikatoren
        $pmax = \trim((string)($_GET['pmax'] ?? ''));
        $mid = \trim((string)($_GET['mid'] ?? ''));
        $srsltid = \trim((string)($_GET['srsltid'] ?? '')); // Google Shopping
        $utmSource = \strtolower(\trim((string)($_GET['utm_source'] ?? '')));
        $utmMedium = \strtolower(\trim((string)($_GET['utm_medium'] ?? '')));
        $utmCampaign = \trim((string)($_GET['utm_campaign'] ?? ''));
        $utmContent = \trim((string)($_GET['utm_content'] ?? ''));

        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = $ref !== '' ? \strtolower((string)\parse_url($ref, \PHP_URL_HOST)) : '';

        $refIsGooglePaid = $refHost !== '' && $this->isGoogleAdsHost($refHost);
        $hasPmaxHint = $pmax !== '' || $mid !== ''
            || \stripos($utmCampaign, 'pmax') !== false
            || \stripos($utmContent, 'pmax') !== false
            || \stripos($utmSource, 'pmax') !== false;

        $paidGoogle = $gclid !== '' || $gbraid !== '' || $wbraid !== ''
            || $refIsGooglePaid
            || $hasPmaxHint
            || $srsltid !== ''
            || ($utmMedium !== '' && \preg_match('/^(cpc|ppc|paid|display|paidsearch|ads|pmax|performance_max|shopping)$/', $utmMedium) === 1)
            // utm_source enthält "google" UND irgendein utm_medium ist gesetzt → bezahlte Google-Quelle.
            // (Organische Google-Suche bringt keine UTM-Parameter mit.)
            || ($utmSource !== '' && \str_contains($utmSource, 'google') && $utmMedium !== '');

        if ($paidGoogle) {
            $details = [];
            if ($refHost !== '') {
                $details[] = $refHost;
            }
            $utmStr = $this->utmDetail($utmSource, $utmMedium, $utmCampaign);
            if ($utmStr !== '') {
                $details[] = $utmStr;
            }
            if ($gclid !== '') {
                $details[] = 'gclid';
            }
            if ($pmax !== '' || $hasPmaxHint) {
                $details[] = 'pmax';
            }
            if ($mid !== '') {
                $details[] = 'mid=' . $mid;
            }
            if ($srsltid !== '') {
                $details[] = 'srsltid';
            }

            return [
                'channel'       => 'google_ads',
                'label'         => 'Google Ads' . ($hasPmaxHint ? ' (PMax)' : ($gclid !== '' ? ' (gclid)' : ($srsltid !== '' ? ' (Shopping)' : ''))),
                'source_detail' => \implode(' · ', $details),
            ];
        }

        if ($fbclid !== '' || \preg_match('/^(facebook|fb|meta|instagram)$/', $utmSource) === 1) {
            return [
                'channel'       => 'meta_ads',
                'label'         => 'Meta / Facebook Ads',
                'source_detail' => $this->utmDetail($utmSource, $utmMedium, $utmCampaign),
            ];
        }

        if ($utmMedium === 'email' || $utmMedium === 'newsletter') {
            return [
                'channel'       => 'email',
                'label'         => 'E-Mail / Newsletter (UTM)',
                'source_detail' => $this->utmDetail($utmSource, $utmMedium, $utmCampaign),
            ];
        }

        if ($utmSource !== '' || $utmMedium !== '' || $utmCampaign !== '') {
            return [
                'channel'       => 'utm_campaign',
                'label'         => 'Kampagne (UTM)',
                'source_detail' => $this->utmDetail($utmSource, $utmMedium, $utmCampaign),
            ];
        }

        if ($refHost !== '' && !$this->isSameShopHost($refHost)) {
            // Payment-Gateway-Redirects sind keine Akquise-Quelle — Cookie nicht überschreiben.
            if ($this->isPaymentRedirectHost($refHost)) {
                return null;
            }
            // Google-Ads-Redirect-Hosts (doubleclick, googleadservices) – sicherheitshalber,
            // falls $paidGoogle weiter oben nicht griff (z.B. wegen nicht gesetztem gclid).
            if ($this->isGoogleAdsHost($refHost)) {
                return [
                    'channel'       => 'google_ads',
                    'label'         => 'Google Ads (Referrer ' . $refHost . ')',
                    'source_detail' => $ref,
                ];
            }
            if ($this->isGoogleSearchHost($refHost)) {
                return [
                    'channel'       => 'seo',
                    'label'         => 'SEO Google (' . $refHost . ')',
                    'source_detail' => $ref,
                ];
            }
            if (\preg_match('/(^|\\.)bing\\.|(^|\\.)yahoo\\.|(^|\\.)duckduckgo\\.|(^|\\.)ecosia\\./', $refHost) === 1) {
                return [
                    'channel'       => 'seo',
                    'label'         => 'SEO (' . $refHost . ')',
                    'source_detail' => $ref,
                ];
            }

            return [
                'channel'       => 'referral',
                'label'         => 'Referral · ' . $refHost,
                'source_detail' => $ref,
            ];
        }

        return null;
    }

    /**
     * Echte Google-Such-Hosts (organisch). Subdomains wie googleads.*, googleadservices.* sind NICHT enthalten.
     */
    private function isGoogleSearchHost(string $host): bool
    {
        // www.google.de, google.de, www.google.co.uk … (nicht googleadservices.com etc.)
        return \preg_match('/^(www\\.)?google\\.[a-z.]{2,8}$/', $host) === 1;
    }

    /**
     * Google-Ads/Performance-Max-Redirect-Hosts.
     */
    private function isGoogleAdsHost(string $host): bool
    {
        $patterns = [
            '/(^|\\.)googleadservices\\.com$/',
            '/(^|\\.)googleads\\.g\\.doubleclick\\.net$/',
            '/(^|\\.)doubleclick\\.net$/',
            '/(^|\\.)googlesyndication\\.com$/',
            '/(^|\\.)googletagmanager\\.com$/', // selten als Referrer, aber zur Sicherheit
        ];
        foreach ($patterns as $p) {
            if (\preg_match($p, $host) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hosts der Payment-Provider, die nach erfolgter Zahlung zurück zum Shop redirecten.
     * Diese dürfen nicht als Akquise-Quelle erfasst werden.
     */
    private function isPaymentRedirectHost(string $host): bool
    {
        $patterns = [
            '/(^|\\.)amazon\\.([a-z.]+)$/',          // amazon.de, payments.amazon.de, www.amazon.com/ap…
            '/(^|\\.)paypal\\.([a-z.]+)$/',          // paypal.com, www.paypal.com, paypalobjects.com
            '/(^|\\.)paypalobjects\\.com$/',
            '/(^|\\.)klarna\\.com$/',
            '/(^|\\.)checkout\\.klarna\\.com$/',
            '/(^|\\.)stripe\\.com$/',
            '/(^|\\.)checkout\\.stripe\\.com$/',
            '/(^|\\.)adyen\\.com$/',
            '/(^|\\.)sofort\\.com$/',
            '/(^|\\.)mollie\\.com$/',
            '/(^|\\.)heidelpay\\.com$/',
            '/(^|\\.)unzer\\.com$/',
            '/(^|\\.)secupay\\.(com|ag)$/',
            '/(^|\\.)wirecard\\.com$/',
            '/(^|\\.)payone\\.com$/',
            '/(^|\\.)novalnet\\.de$/',
            '/(^|\\.)computop-paygate\\.com$/',
            '/(^|\\.)girosolution\\.de$/',
            '/(^|\\.)giropay\\.de$/',
            '/(^|\\.)ratepay\\.com$/',
            '/(^|\\.)billsafe\\.de$/',
        ];
        foreach ($patterns as $p) {
            if (\preg_match($p, $host) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checkout-/Bestell-/Payment-Return-Pfade — hier KEIN Cookie-Update.
     */
    private function isCheckoutOrPaymentReturn(string $uri): bool
    {
        $u = \strtolower($uri);
        $needles = [
            '/bestellvorgang', '/bestellabschluss', '/bestellab_again',
            '/warenkorb', '/checkout',
            'paypal', 'amazon', 'klarna', 'stripe', 'sofort', 'mollie',
            'unzer', 'heidelpay', 'secupay', 'payone', 'novalnet',
            'wirecard', 'adyen', 'giropay', 'ratepay',
            '/zahlungsmethode', '/payment',
        ];
        foreach ($needles as $n) {
            if (\strpos($u, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    private function utmDetail(string $utmSource, string $utmMedium, string $utmCampaign): string
    {
        $parts = \array_filter([$utmSource, $utmMedium, $utmCampaign], static fn ($v) => $v !== '');
        return \implode(' / ', $parts);
    }

    private function isSameShopHost(string $referrerHost): bool
    {
        try {
            $shopUrl = (string)Shop::getURL();
            $shopHost = \strtolower((string)\parse_url($shopUrl, \PHP_URL_HOST));
            return $shopHost !== '' && $referrerHost === $shopHost;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readCookiePayload(): array
    {
        $raw = (string)($_COOKIE[self::COOKIE_NAME] ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = \json_decode($raw, true);
        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeAttributionCookie(array $payload): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $opts = [
            'expires'  => \time() + self::COOKIE_TTL_SEC,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        $encoded = \json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        \setcookie(self::COOKIE_NAME, $encoded, $opts);
        $_COOKIE[self::COOKIE_NAME] = $encoded;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function persistAttributionForOrder(array $args): void
    {
        $this->ensureTables();
        $kBestellung = $this->extractOrderId($args);
        if ($kBestellung <= 0) {
            return;
        }

        $cookie = $this->readCookiePayload();
        $last = \is_array($cookie['last'] ?? null) ? $cookie['last'] : null;
        $first = \is_array($cookie['first'] ?? null) ? $cookie['first'] : null;
        $pick = $last ?? $first;
        if ($pick === null) {
            $pick = [
                'channel'       => 'direct',
                'label'         => 'Direkt / unbekannt',
                'source_detail' => '',
            ];
        }

        $channel = (string)($pick['channel'] ?? 'direct');
        $channel = \preg_replace('/[^a-zA-Z0-9_-]+/', '_', $channel) ?? '';
        if ($channel === '') {
            $channel = 'direct';
        }
        $channel = \substr($channel, 0, 64);
        $labelRaw = (string)($pick['label'] ?? '');
        $label = \function_exists('mb_substr')
            ? \mb_substr($labelRaw, 0, 500, 'UTF-8')
            : \substr($labelRaw, 0, 500);
        $json = \json_encode(
            ['first' => $first, 'last' => $last, 'picked' => $pick],
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        );

        try {
            $db = Shop::Container()->getDB();
            $exists = $db->getSingleObject(
                'SELECT kID FROM twomosi_sales_attribution WHERE kBestellung = :id LIMIT 1',
                ['id' => $kBestellung]
            );
            if ($exists !== null) {
                return;
            }
            $row = new \stdClass();
            $row->kBestellung = $kBestellung;
            $row->cChannel = $channel;
            $row->cChannelLabel = $label;
            $row->cAttributionJson = $json;
            $row->dCreated = \date('Y-m-d H:i:s');
            $db->insert('twomosi_sales_attribution', $row);
        } catch (\Throwable $e) {
            $this->logError(
                'persistAttributionForOrder failed for kBestellung=' . $kBestellung
                . ': ' . $e->getMessage()
            );
        }
    }

    private function logError(string $message): void
    {
        try {
            $logger = Shop::Container()->getLogService();
            if (\is_object($logger) && \method_exists($logger, 'error')) {
                $logger->error('[SalesProduct] ' . $message);
            }
        } catch (\Throwable $e) {
            // last-resort: stay silent if logger itself fails
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function extractOrderId(array $args): int
    {
        $bid = $args['bestellID'] ?? null;
        if (\is_int($bid)) {
            return $bid;
        }
        if (\is_string($bid) && \ctype_digit($bid)) {
            return (int)$bid;
        }
        if ($bid instanceof \stdClass) {
            foreach (['kBestellung', 'nBestellung', 'kBestellungID'] as $prop) {
                if (isset($bid->{$prop})) {
                    return (int)$bid->{$prop};
                }
            }
        }
        if (\is_array($bid) && isset($bid['kBestellung'])) {
            return (int)$bid['kBestellung'];
        }
        $o = $args['oBestellung'] ?? null;
        if ($o instanceof \stdClass && isset($o->kBestellung)) {
            return (int)$o->kBestellung;
        }

        return 0;
    }
}
