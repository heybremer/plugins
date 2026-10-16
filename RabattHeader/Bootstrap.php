<?php

declare(strict_types=1);

namespace Plugin\RabattHeader;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private const AD_COOKIE_NAME = 'rh_from_ad';

    private const AD_COOKIE_DAYS = 30;

    private static bool $settingsEnsured = false;

    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureSettingsTable();
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if ($this->isBackendRequest()) {
                return;
            }
            $this->captureAdVisitorFromRequest();
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 0) !== 1) {
                return;
            }
            if (!$this->isAdVisitor()) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $css = 'plugins/RabattHeader/templates/css/rabatt-header.css?v=104';
            $js  = 'plugins/RabattHeader/templates/js/rabatt-header.js?v=104';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
            if (!\in_array($js, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $js;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if ($this->isBackendRequest()) {
                return;
            }
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            $this->captureAdVisitorFromRequest();
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 0) !== 1) {
                return;
            }
            if (!$this->isAdVisitor()) {
                return;
            }

            $bannerText = \trim((string)($settings['bannerText'] ?? ''));
            $couponCode = \trim((string)($settings['couponCode'] ?? ''));
            if ($bannerText === '' || $couponCode === '') {
                return;
            }

            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            if ($doc->find('#rabatt-header-bar')->length > 0) {
                return;
            }

            $smarty->assign('rhBannerHtml', $this->formatBannerHtml($bannerText));
            $smarty->assign('rhCouponCode', $couponCode);

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/rabatt-header.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '' || \strpos($html, 'rabatt-header-bar') === false) {
                return;
            }

            $body = $doc->find('body')->eq(0);
            if ($body->length < 1) {
                return;
            }
            try {
                $body->prepend($html);
            } catch (\Throwable $e) {
                return;
            }
        });
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }

    /**
     * Banner nur für Besucher mit bezahlter Werbung (Google/Meta/Bing etc.).
     */
    private function isAdVisitor(): bool
    {
        if ($this->requestIndicatesPaidAd()) {
            return true;
        }

        return isset($_COOKIE[self::AD_COOKIE_NAME]) && $_COOKIE[self::AD_COOKIE_NAME] === '1';
    }

    private function captureAdVisitorFromRequest(): void
    {
        if (!$this->requestIndicatesPaidAd()) {
            return;
        }
        $this->persistAdVisitorCookie();
    }

    private function persistAdVisitorCookie(): void
    {
        if (isset($_COOKIE[self::AD_COOKIE_NAME]) && $_COOKIE[self::AD_COOKIE_NAME] === '1') {
            return;
        }
        $expires = \time() + self::AD_COOKIE_DAYS * 86400;
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        \setcookie(self::AD_COOKIE_NAME, '1', [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::AD_COOKIE_NAME] = '1';
    }

    private function requestIndicatesPaidAd(): bool
    {
        $gclid = \trim((string)($_GET['gclid'] ?? ''));
        $gbraid = \trim((string)($_GET['gbraid'] ?? ''));
        $wbraid = \trim((string)($_GET['wbraid'] ?? ''));
        $fbclid = \trim((string)($_GET['fbclid'] ?? ''));
        $msclkid = \trim((string)($_GET['msclkid'] ?? ''));
        $dclid = \trim((string)($_GET['dclid'] ?? ''));
        $ttclid = \trim((string)($_GET['ttclid'] ?? ''));
        $twclid = \trim((string)($_GET['twclid'] ?? ''));
        $gadSource = \trim((string)($_GET['gad_source'] ?? ''));
        $gadCampaignId = \trim((string)($_GET['gad_campaignid'] ?? ''));
        $utmSource = \strtolower(\trim((string)($_GET['utm_source'] ?? '')));
        $utmMedium = \strtolower(\trim((string)($_GET['utm_medium'] ?? '')));
        $utmCampaign = \trim((string)($_GET['utm_campaign'] ?? ''));
        $utmContent = \trim((string)($_GET['utm_content'] ?? ''));

        if ($gclid !== '' || $gbraid !== '' || $wbraid !== '' || $fbclid !== ''
            || $msclkid !== '' || $dclid !== '' || $ttclid !== '' || $twclid !== ''
            || $gadSource !== '' || $gadCampaignId !== '') {
            return true;
        }

        $paidMediums = 'cpc|ppc|paid|display|paidsearch|ads|pmax|performance_max|shopping|social_paid|paidsocial';
        if ($utmMedium !== '' && \preg_match('/^(' . $paidMediums . ')$/', $utmMedium) === 1) {
            return true;
        }

        $hasPmaxHint = \stripos($utmCampaign, 'pmax') !== false
            || \stripos($utmContent, 'pmax') !== false
            || \stripos($utmSource, 'pmax') !== false
            || $utmMedium === 'pmax';

        if ($hasPmaxHint) {
            return true;
        }

        if ($utmSource !== '' && \preg_match('/^(facebook|fb|meta|instagram)$/', $utmSource) === 1
            && ($utmMedium === '' || \preg_match('/^(' . $paidMediums . ')$/', $utmMedium) === 1)) {
            return true;
        }

        if ($utmSource !== '' && \str_contains($utmSource, 'google') && $utmMedium !== '') {
            return true;
        }

        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $refHost = $ref !== '' ? \strtolower((string)\parse_url($ref, \PHP_URL_HOST)) : '';
        if ($refHost !== '' && $this->isGoogleAdsReferrerHost($refHost)) {
            return true;
        }

        return false;
    }

    private function isGoogleAdsReferrerHost(string $host): bool
    {
        $patterns = [
            '/(^|\\.)googleadservices\\.com$/',
            '/(^|\\.)googleads\\.g\\.doubleclick\\.net$/',
            '/(^|\\.)doubleclick\\.net$/',
        ];
        foreach ($patterns as $pattern) {
            if (\preg_match($pattern, $host) === 1) {
                return true;
            }
        }

        return false;
    }

    private function formatBannerHtml(string $text): string
    {
        $escaped = \htmlspecialchars($text, \ENT_QUOTES, 'UTF-8');
        $escaped = (string)\preg_replace(
            '/(\d+\s*%)/u',
            '<strong>$1</strong>',
            $escaped
        );

        return $escaped;
    }

    private function ensureSettingsTable(): void
    {
        if (self::$settingsEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_rabatt_header_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cBannerText VARCHAR(512) NOT NULL DEFAULT "",
                    cCouponCode VARCHAR(64) NOT NULL DEFAULT "",
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $row = $db->getSingleObject('SELECT kID FROM twomosi_rabatt_header_settings LIMIT 1');
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nActive = 1;
                $ins->cBannerText = 'Jetzt 15 % Rabatt auf hochwertige Schonbezüge sichern. Jetzt bestellen und sparen.';
                $ins->cCouponCode = 'sommer15';
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_rabatt_header_settings', $ins);
            }
            self::$settingsEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{active:int,bannerText:string,couponCode:string}
     */
    public function fetchSettings(): array
    {
        $defaults = [
            'active'     => 1,
            'bannerText' => 'Jetzt 15 % Rabatt auf hochwertige Schonbezüge sichern. Jetzt bestellen und sparen.',
            'couponCode' => 'sommer15',
        ];
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_rabatt_header_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }

            return [
                'active'     => (int)($row->nActive ?? 1),
                'bannerText' => (string)($row->cBannerText ?? $defaults['bannerText']),
                'couponCode' => (string)($row->cCouponCode ?? $defaults['couponCode']),
            ];
        } catch (\Throwable $e) {
            return $defaults;
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
                $menu->cName = 'Rabatt Header';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 12;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
