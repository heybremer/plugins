<?php

namespace Plugin\ActionProduct;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $tablesEnsured = false;
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureAdminMenuEntry();

        // CSS/JS: Startseite + Artikel + Warenkorb/Checkout (Kupon aus localStorage via actionproduct.js).
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            $pt = Shop::getPageType();
            $needsAssets = $this->isHomepageRequest()
                || $pt === \PAGE_ARTIKEL
                || $pt === \PAGE_WARENKORB
                || $pt === \PAGE_BESTELLVORGANG;
            if (!$needsAssets) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }

            $cssPath = 'plugins/ActionProduct/templates/css/actionproduct.css';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }

            $jsPath = 'plugins/ActionProduct/templates/js/actionproduct.js';
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }

            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            $pageType = Shop::getPageType();

            // Homepage inject (PAGE_STARTSEITE + Pfad-Fallback wie CSS/Hook)
            if ($this->isHomepageRequest()) {
            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/actionproduct.tpl';
            if (!\is_file($tplPath)) {
                return;
            }
            try {
                $settings = $this->fetchSettings();
                if (!empty($settings['tabs'])) {
                    $smarty->assign('apTabs', $settings['tabs']);
                }
                $smarty->assign('apActive', (int)($settings['active'] ?? 1));

                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '' || \strpos($html, 'aktion-container') === false) {
                return;
            }
            if (strpos($html, 'data-actionproduct-disabled="1"') !== false) {
                return;
            }

            $mode = (string)($settings['injectMode'] ?? 'after_configurator');
            $pos = (string)($settings['injectPos'] ?? 'after');
            $selector = trim((string)($settings['injectSelector'] ?? ''));
            $pos = \in_array($pos, ['before', 'after'], true) ? $pos : 'after';

            $injected = false;
            if ($mode === 'after_selector' && $selector !== '') {
                $target = $doc->find($selector)->eq(0);
                if ($target->length > 0) {
                    if ($pos === 'before') {
                        $target->before($html);
                    } else {
                        $target->after($html);
                    }
                    $injected = true;
                }
            }
            if (!$injected && $mode === 'after_content') {
                $content = $doc->find('div#content, main#main-wrapper, main')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                    $injected = true;
                }
            }
            if (!$injected) {
                $afterConfigurator = $doc->find('div.index-filter-konfigurator')->eq(0);
                if ($afterConfigurator->length > 0) {
                    if ($pos === 'before') {
                        $afterConfigurator->before($html);
                    } else {
                        $afterConfigurator->after($html);
                    }
                    $injected = true;
                }
            }
            if (!$injected) {
                $content = $doc->find('div#content, main#main-wrapper, main')->eq(0);
                if ($content->length > 0) {
                    $content->prepend($html);
                }
            }
                return;
            }

            // Product detail: show countdown under title for action-discounted product
            if ($pageType === \PAGE_ARTIKEL) {
                $settings = $this->fetchSettings();
                $tabs = $settings['tabs'] ?? [];
                if (!\is_array($tabs) || empty($tabs)) {
                    return;
                }
                $artikel = $smarty->getTemplateVars('Artikel');
                $kArtikel = 0;
                $cSeo = '';
                if (\is_object($artikel)) {
                    if (isset($artikel->kArtikel)) {
                        $kArtikel = (int)$artikel->kArtikel;
                    }
                    if (isset($artikel->cSeo)) {
                        $cSeo = trim((string)$artikel->cSeo);
                    }
                }
                if ($kArtikel <= 0) {
                    return;
                }

                $match = $this->actionProductPickTabForArtikel($tabs, $kArtikel, $cSeo);
                if ($match === null) {
                    return;
                }

                $rabattBadgeHtml = $this->actionProductBuildProductDetailRabattBadgeHtml($match);
                $extrasHtml = $this->actionProductBuildProductDetailExtrasHtml($match);

                if ($rabattBadgeHtml === '' && $extrasHtml === '') {
                    return;
                }

                $title = $doc->find('h1.product-title.h2')->eq(0);
                if ($extrasHtml !== '' && $title->length > 0) {
                    $title->after($extrasHtml);
                }

                if ($rabattBadgeHtml !== '') {
                    $this->actionProductAppendRabattBadgeAfterMainPrice($doc, $rabattBadgeHtml);
                }
            }
        });
    }

    /**
     * Startseite-Erkennung: PAGE_STARTSEITE und Fallback per Request-Pfad (Subfolder-Installs).
     */
    private function isHomepageRequest(): bool
    {
        $uri   = (string)($_SERVER['REQUEST_URI'] ?? '');
        $query = (string)(\parse_url($uri, \PHP_URL_QUERY) ?? '');

        // Index-URL mit Suche/Merkmal-Filter ist keine „echte“ Startseite (z. B. ?suche=…&mf=…).
        if ($query !== '' && \preg_match('/(?:^|&)(suche|mf)=/i', $query) === 1) {
            return false;
        }

        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
        }
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $path = \rtrim($path, '/');
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
        $shopBasePath = \rtrim($shopBasePath, '/');

        // JTL-Wawi/Admin öffnen Artikel oft als /index.php?a=123 – das ist keine Startseite.
        if ($query !== '' && \preg_match('/(?:^|&)a=\d+(?:&|$)/', $query) === 1) {
            return false;
        }

        return $path === $shopBasePath
            || $path === ($shopBasePath . '/index.php')
            || \str_starts_with($path, $shopBasePath . '/index.php');
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                "CREATE TABLE IF NOT EXISTS twomosi_action_product_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator',
                    cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after',
                    cInjectSelector VARCHAR(255) NOT NULL DEFAULT '',
                    cTabsJson LONGTEXT NULL,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $colMode = $db->getSingleObject("SHOW COLUMNS FROM twomosi_action_product_settings LIKE 'cInjectMode'");
            if ($colMode === null) {
                $db->executeQuery("ALTER TABLE twomosi_action_product_settings ADD COLUMN cInjectMode VARCHAR(40) NOT NULL DEFAULT 'after_configurator' AFTER nActive");
            }
            $colPos = $db->getSingleObject("SHOW COLUMNS FROM twomosi_action_product_settings LIKE 'cInjectPos'");
            if ($colPos === null) {
                $db->executeQuery("ALTER TABLE twomosi_action_product_settings ADD COLUMN cInjectPos VARCHAR(10) NOT NULL DEFAULT 'after' AFTER cInjectMode");
            }
            $colSelector = $db->getSingleObject("SHOW COLUMNS FROM twomosi_action_product_settings LIKE 'cInjectSelector'");
            if ($colSelector === null) {
                $db->executeQuery("ALTER TABLE twomosi_action_product_settings ADD COLUMN cInjectSelector VARCHAR(255) NOT NULL DEFAULT '' AFTER cInjectPos");
            }
            $exists = $db->getSingleObject("SELECT kID FROM twomosi_action_product_settings LIMIT 1");
            if ($exists === null) {
                $row = new \stdClass();
                $row->nActive = 1;
                $row->cInjectMode = 'after_configurator';
                $row->cInjectPos = 'after';
                $row->cInjectSelector = '';
                $row->cTabsJson = \json_encode($this->defaultTabs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_action_product_settings', $row);
            }
            self::$tablesEnsured = true;
        } catch (\Throwable $e) {
            // no hard fail
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
            $file = 'dashboard.php';
            $exists = $db->getSingleObject(
                "SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin
                    AND cDateiname = :cDateiname
                  LIMIT 1",
                ['kPlugin' => $pluginID, 'cDateiname' => $file]
            );
            if ($exists === null) {
                $menuItem = new \stdClass();
                $menuItem->kPlugin    = $pluginID;
                $menuItem->cName      = 'ActionProduct Dashboard';
                $menuItem->cDateiname = $file;
                $menuItem->nSort      = 30;
                $menuItem->nConf      = 0;
                $db->insert('tpluginadminmenu', $menuItem);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // no hard fail
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultTabs(): array
    {
        return [
            [
                'kArtikel' => 0,
                'tabTitle' => 'Produkt des Monats',
                'badge' => 'Produkt des Monats',
                'countdownIso' => '2026-03-30T23:59:59',
                'name' => 'Lammfell Sitzauflage Sitzbezug Universal Autositzauflage',
                'desc' => 'Ergonomisch, Waschbar & Antiallergisch',
                'urgencyActive' => 1,
                'urgencyText' => 'Nur noch {remain} Stück verfügbar!',
                'couponCode' => '',
                'priceOld' => 199.0,
                'priceNew' => 99.5,
                'totalStock' => 100,
                'soldCount' => 90,
                'buyUrl' => '/',
                'images' => [
                    'https://bremer-sitzbezuege.de/media/image/product/335179/lg/lammfell-sitzauflage-sitzbezug-universal-autositzauflage.webp',
                    'https://bremer-sitzbezuege.de/media/image/product/335180/lg/lammfell-sitzauflage-sitzbezug-universal-autositzauflage_1.webp',
                ],
            ],
            [
                'kArtikel' => 0,
                'tabTitle' => 'Produkt des Monats',
                'badge' => 'Produkt des Monats',
                'countdownIso' => '2026-02-28T23:59:59',
                'name' => '5 Fenster Einlagig Gardinen mit Aluprofile',
                'desc' => 'Maß Gardinen Set passend für Ihr Fahrzeug',
                'urgencyActive' => 1,
                'urgencyText' => 'Nur noch {remain} Stück verfügbar!',
                'couponCode' => '',
                'priceOld' => 199.0,
                'priceNew' => 99.0,
                'totalStock' => 100,
                'soldCount' => 15,
                'buyUrl' => '/',
                'images' => [
                    'https://bremer-sitzbezuege.de/media/image/product/116482/lg/mercedes-gardinenset.webp',
                    'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~2.webp',
                    'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~4.webp',
                    'https://bremer-sitzbezuege.de/media/image/product/116484/lg/mass-gardinen-fuer-crafter-mercedes-sprinter-w906-l2-726-in-schwarz~6.webp',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        try {
            // Lazy schema init: avoid DDL/meta queries on every process boot.
            $this->ensureTables();
            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject("SELECT * FROM twomosi_action_product_settings ORDER BY kID ASC LIMIT 1");
            if ($s === null) {
                return [
                    'active' => 1,
                    'injectMode' => 'after_configurator',
                    'injectPos' => 'after',
                    'injectSelector' => '',
                    'tabs' => $this->defaultTabs()
                ];
            }
            $tabs = [];
            $raw = (string)($s->cTabsJson ?? '');
            if ($raw !== '') {
                $decoded = \json_decode($raw, true);
                if (\is_array($decoded)) {
                    $tabs = $decoded;
                }
            }
            if (empty($tabs)) {
                $tabs = $this->defaultTabs();
            }

            // Fallback: if kArtikel missing but buyUrl has SEO, resolve kArtikel
            foreach ($tabs as $i => $t) {
                if (!\is_array($t)) {
                    continue;
                }
                $kArtikel = (int)($t['kArtikel'] ?? 0);
                if ($kArtikel > 0) {
                    continue;
                }
                $buyUrl = trim((string)($t['buyUrl'] ?? ''));
                if ($buyUrl === '') {
                    continue;
                }

                // 1) try query param ?a=123 (artikel.php?a=)
                $query = (string)(\parse_url($buyUrl, PHP_URL_QUERY) ?? '');
                if ($query !== '') {
                    $q = [];
                    \parse_str($query, $q);
                    if (isset($q['a']) && \is_numeric($q['a']) && (int)$q['a'] > 0) {
                        $tabs[$i]['kArtikel'] = (int)$q['a'];
                        continue;
                    }
                }

                $path = (string)(\parse_url($buyUrl, PHP_URL_PATH) ?? '');
                $slug = trim(\basename($path));
                if ($slug === '' || $slug === '/' || $slug === '.' || $slug === '..' || $slug === 'artikel.php') {
                    continue;
                }
                $slug = \preg_replace('/\.[^.]+$/', '', $slug) ?? $slug;
                try {
                    $a = $db->getSingleObject(
                        "SELECT kArtikel
                           FROM tartikel
                          WHERE cSeo = :cSeo
                          LIMIT 1",
                        ['cSeo' => $slug]
                    );
                    if ($a !== null && (int)($a->kArtikel ?? 0) > 0) {
                        $tabs[$i]['kArtikel'] = (int)$a->kArtikel;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // Enrich with coupon details (by code)
            foreach ($tabs as $i => $t) {
                if (!\is_array($t)) {
                    continue;
                }
                $code = trim((string)($t['couponCode'] ?? ''));
                if ($code === '') {
                    $tabs[$i]['couponWert'] = 0.0;
                    $tabs[$i]['couponTyp'] = '';
                    continue;
                }
                try {
                    $c = $db->getSingleObject(
                        "SELECT fWert, cWertTyp, cAktiv, cKuponTyp
                           FROM tkupon
                          WHERE cCode = :cCode
                          LIMIT 1",
                        ['cCode' => $code]
                    );
                    if ($c !== null && (string)($c->cAktiv ?? '') === 'Y' && (string)($c->cKuponTyp ?? '') === 'standard') {
                        $tabs[$i]['couponWert'] = (float)($c->fWert ?? 0.0);
                        $tabs[$i]['couponTyp']  = (string)($c->cWertTyp ?? 'festpreis');
                    } else {
                        $tabs[$i]['couponWert'] = 0.0;
                        $tabs[$i]['couponTyp']  = '';
                    }
                } catch (\Throwable $e) {
                    $tabs[$i]['couponWert'] = 0.0;
                    $tabs[$i]['couponTyp']  = '';
                }
            }

            // Always enrich prices from DB when tab has selected product:
            // - priceOld: UVP (if available)
            // - priceNew: current sale/base price (coupon will be applied in JS)
            foreach ($tabs as $i => $t) {
                if (!\is_array($t)) {
                    continue;
                }
                $kArtikel = (int)($t['kArtikel'] ?? 0);
                if ($kArtikel <= 0) {
                    continue;
                }

                try {
                    $p2 = $db->getSingleObject(
                        "SELECT fStandardpreisNetto, fMwSt, fUVP
                           FROM tartikel
                          WHERE kArtikel = :kArtikel
                          LIMIT 1",
                        ['kArtikel' => $kArtikel]
                    );
                } catch (\Throwable $e) {
                    $p2 = null;
                }
                $mwst  = (float)($p2->fMwSt ?? 0.0);
                $uvp   = (float)($p2->fUVP ?? 0.0);

                $saleNet = 0.0;
                try {
                    $vkNettoRow = $db->getSingleObject(
                        "SELECT pd.fVKNetto AS fVKNetto
                           FROM tpreis p
                           INNER JOIN tpreisdetail pd ON pd.kPreis = p.kPreis
                          WHERE p.kArtikel = :kArtikel
                            AND p.kKundengruppe = 1
                            AND (p.kKunde IS NULL OR p.kKunde = 0)
                            AND pd.nAnzahlAb = 0
                          LIMIT 1",
                        ['kArtikel' => $kArtikel]
                    );
                    if ($vkNettoRow !== null) {
                        $saleNet = (float)($vkNettoRow->fVKNetto ?? 0.0);
                    }
                } catch (\Throwable $e) {
                    $saleNet = 0.0;
                }
                if ($saleNet <= 0) {
                    $saleNet = (float)($p2->fStandardpreisNetto ?? 0.0);
                }

                $saleGross = $saleNet > 0 ? \round($saleNet * (1.0 + \max(0.0, $mwst) / 100.0), 2) : 0.0;
                if ($saleGross > 0) {
                    $tabs[$i]['priceNew'] = $saleGross;
                } elseif ($uvp > 0) {
                    $tabs[$i]['priceNew'] = $uvp;
                }
                if ($uvp > 0) {
                    $tabs[$i]['priceOld'] = $uvp;
                }
            }

            return [
                'active' => (int)($s->nActive ?? 1),
                'injectMode' => (string)($s->cInjectMode ?? 'after_configurator'),
                'injectPos' => (string)($s->cInjectPos ?? 'after'),
                'injectSelector' => (string)($s->cInjectSelector ?? ''),
                'tabs' => $tabs,
            ];
        } catch (\Throwable $e) {
            return [
                'active' => 1,
                'injectMode' => 'after_configurator',
                'injectPos' => 'after',
                'injectSelector' => '',
                'tabs' => $this->defaultTabs()
            ];
        }
    }

    /**
     * @param array<int, mixed> $tabs
     * @return array<string, mixed>|null
     */
    private function actionProductPickTabForArtikel(array $tabs, int $kArtikel, string $cSeo): ?array
    {
        $candidates = [];
        foreach ($tabs as $t) {
            if (!\is_array($t)) {
                continue;
            }
            $cw = (float)($t['couponWert'] ?? 0);
            $cc = trim((string)($t['couponCode'] ?? ''));
            if ($cw <= 0 && $cc === '') {
                continue;
            }
            if (!$this->actionProductTabMatchesArtikel($t, $kArtikel, $cSeo)) {
                continue;
            }
            $candidates[] = $t;
        }
        if ($candidates === []) {
            return null;
        }
        foreach ($candidates as $t) {
            $tk = (int)($t['kArtikel'] ?? 0);
            if ($tk > 0 && $tk === $kArtikel) {
                return $t;
            }
        }

        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $t
     */
    private function actionProductTabMatchesArtikel(array $t, int $kArtikel, string $cSeo): bool
    {
        $tk = (int)($t['kArtikel'] ?? 0);
        if ($tk > 0 && $tk === $kArtikel) {
            return true;
        }

        $buyUrl = trim((string)($t['buyUrl'] ?? ''));
        if ($buyUrl === '' || $buyUrl === '#') {
            return false;
        }

        $query = (string)(\parse_url($buyUrl, PHP_URL_QUERY) ?? '');
        if ($query !== '') {
            $q = [];
            \parse_str($query, $q);
            if (isset($q['a']) && \is_numeric($q['a']) && (int)$q['a'] === $kArtikel) {
                return true;
            }
        }

        if ($cSeo === '') {
            return false;
        }

        $path = (string)(\parse_url($buyUrl, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return false;
        }

        $seo = \strtolower($cSeo);
        $slug = \strtolower(trim(\basename($path)));
        $slug = \preg_replace('/\.[^.]+$/', '', $slug) ?? $slug;
        if ($slug !== '' && $slug === $seo) {
            return true;
        }

        $pathLower = \strtolower($path);
        if ($seo !== '' && \strpos($pathLower, '/' . $seo) !== false) {
            return true;
        }
        if ($seo !== '' && \strpos($pathLower, $seo) !== false) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $match
     */
    private function actionProductBuildProductDetailExtrasHtml(array $match): string
    {
        $iso = \trim((string)($match['countdownIso'] ?? ''));
        if ($iso === '') {
            return '';
        }
        $meta = $this->actionProductBuildDiscountMeta($match);
        $couponCode = \trim((string)($match['couponCode'] ?? ''));
        $discountAmount = $meta['discountAmount'] ?? 0.0;
        $discountedPrice = $meta['priceNew'] ?? 0.0;
        return '<div class="aktion-product-detail-extras"'
            . ' data-ap-coupon-code="' . \htmlspecialchars($couponCode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-ap-discount-amount="' . \htmlspecialchars((string)\round((float)$discountAmount, 2), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-ap-discount-price="' . \htmlspecialchars((string)\round((float)$discountedPrice, 2), ENT_QUOTES, 'UTF-8') . '">'
            . $this->actionProductBuildProductDetailCountdownHtml($iso)
            . '<div class="aktion-cart-discount-highlight" style="display:none;"></div>'
            . '</div>';
    }

    /**
     * Append a small "Rabatt" badge after the visible shop price (div.price.h1) so schema/Google still sees the real price.
     *
     * @param mixed $doc JTL output filter document (phpQuery-compatible)
     */
    private function actionProductAppendRabattBadgeAfterMainPrice($doc, string $badgeHtml): void
    {
        $selectors = [
            'div.product-info div.price.h1',
            'div.product-offer div.price.h1',
            'div.product-detail div.price.h1',
        ];
        foreach ($selectors as $sel) {
            $priceEl = $doc->find($sel)->eq(0);
            if ($priceEl->length < 1) {
                continue;
            }
            try {
                $priceEl->after($badgeHtml);
            } catch (\Throwable $e) {
                continue;
            }

            return;
        }
    }

    private function actionProductBuildProductDetailCountdownHtml(string $iso): string
    {
        return '<div class="aktion-countdown aktion-countdown--product" data-countdown-date="' . \htmlspecialchars($iso, ENT_QUOTES) . '">'
            . '<div class="aktion-countdown-label">Angebot endet in:</div>'
            . '<div class="aktion-countdown-timer">'
            . '<div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="days">00</span> <span class="aktion-countdown-label-small">Tage</span></div>'
            . '<div class="aktion-countdown-separator">:</div>'
            . '<div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="hours">00</span> <span class="aktion-countdown-label-small">Stunden</span></div>'
            . '<div class="aktion-countdown-separator">:</div>'
            . '<div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="minutes">00</span> <span class="aktion-countdown-label-small">Minuten</span></div>'
            . '<div class="aktion-countdown-separator">:</div>'
            . '<div class="aktion-countdown-item"><span class="aktion-countdown-value" data-aktion-unit="seconds">00</span> <span class="aktion-countdown-label-small">Sekunden</span></div>'
            . '</div></div>';
    }

    /**
     * Text-only badge (no percentage) when ActionProduct coupon would reduce price vs. dashboard base price.
     * Same discount check as `actionproduct.js` (base = Verkaufspreis, then coupon).
     *
     * @param array<string, mixed> $match
     */
    private function actionProductBuildProductDetailRabattBadgeHtml(array $match): string
    {
        $meta = $this->actionProductBuildDiscountMeta($match);
        if ($meta === null) {
            return '';
        }

        return '<span class="aktion-rabatt-badge" aria-label="Rabatt">Rabatt</span>';
    }

    /**
     * Same discount check as frontend `actionproduct.js`.
     *
     * @param array<string, mixed> $match
     * @return array<string, float>|null
     */
    private function actionProductBuildDiscountMeta(array $match): ?array
    {
        $basePrice = (float)($match['priceNew'] ?? 0);
        $cw = (float)($match['couponWert'] ?? 0);
        $ct = \trim((string)($match['couponTyp'] ?? ''));
        if ($basePrice <= 0 || $cw <= 0 || ($ct !== 'prozent' && $ct !== 'festpreis')) {
            return null;
        }
        if ($ct === 'prozent') {
            $priceNew = \max(0.0, $basePrice * (1 - \max(0.0, $cw) / 100.0));
        } else {
            $priceNew = \max(0.0, $basePrice - \max(0.0, $cw));
        }
        $priceNew = \round($priceNew, 2);
        if ($priceNew >= $basePrice - 0.0001) {
            return null;
        }

        return [
            'priceOld' => $basePrice,
            'priceNew' => $priceNew,
            'discountAmount' => \round($basePrice - $priceNew, 2),
        ];
    }
}

