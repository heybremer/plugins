<?php

declare(strict_types=1);

namespace Plugin\BlackFriday;

use JTL\Cart\Cart;
use JTL\Cart\CartHelper;
use JTL\Cart\CartItem;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Product\Preise;
use JTL\Checkout\Kupon;
use JTL\Events\Dispatcher;
use JTL\Helpers\Tax;
use JTL\Language\LanguageHelper;
use JTL\Link\Admin\LinkAdmin;
use JTL\Media\Image;
use JTL\Plugin\Bootstrapper;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

require_once __DIR__ . '/src/DiscountService.php';

class Bootstrap extends Bootstrapper
{
    private const ASSET_CSS = 'plugins/BlackFriday/templates/css/blackfriday.css';
    private const ASSET_JS  = 'plugins/BlackFriday/templates/js/blackfriday.js';

    private const LANDING_SLUG     = 'blackfriday';
    private const LANDING_TITLE    = 'Black Friday Deals';
    private const LANDING_IDENT    = 'womosi_blackfriday';
    private const LANDING_DATEI    = 'blackfriday_page.php';
    private const LANDING_TEMPLATE = 'bf-page.tpl';

    private static bool $adminMenuEnsured = false;

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        try {
            $this->service()->ensureSchema();
        } catch (\Throwable $e) {
            return;
        }
        $this->ensureAdminMenuEntry();
        $this->ensureLandingPage();
        $dispatcher->hookInto(\HOOK_SYNC_ADDED_PRODUCT, function (array $args): void {
            try {
                $this->service()->reapplyProduct((int)($args['productID'] ?? 0));
            } catch (\Throwable $e) {
                \error_log('[BlackFriday] sync: ' . $e->getMessage());
            }
        });
        if ($this->isBackendRequest()) {
            return;
        }
        $this->maybeScheduleTick();

        $dispatcher->hookInto(\HOOK_GLOBALINCLUDE_INC, function (): void {
            try {
                $this->blockCouponsOnDeals();
            } catch (\Throwable $e) {
                \error_log('[BlackFriday] coupon: ' . $e->getMessage());
            }
        });
        foreach ([\HOOK_WARENKORB_PAGE_KUPONANNEHMEN, \HOOK_BESTELLVORGANG_PAGE_STEPBESTAETIGUNG, \HOOK_LETZTERINCLUDE_INC] as $hook) {
            $dispatcher->hookInto($hook, function (): void {
                try {
                    $this->adjustCouponForDeals(Frontend::getCart());
                } catch (\Throwable $e) {
                    \error_log('[BlackFriday] coupon: ' . $e->getMessage());
                }
            });
        }
        $dispatcher->hookInto(\HOOK_BESTELLABSCHLUSS_INC_WARENKORBINDB, function (array $args): void {
            try {
                $cart = $args['oWarenkorb'] ?? null;
                $this->adjustCouponForDeals($cart instanceof Cart ? $cart : Frontend::getCart());
            } catch (\Throwable $e) {
                \error_log('[BlackFriday] coupon: ' . $e->getMessage());
            }
        });

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isRunning()) {
                return;
            }
            foreach (['cPluginCss_arr' => self::ASSET_CSS, 'cPluginJsHead_arr' => self::ASSET_JS] as $key => $rel) {
                if (!isset($args[$key]) || !\is_array($args[$key])) {
                    $args[$key] = [];
                }
                $abs = \PFAD_ROOT . $rel;
                $url = $rel . '?v=' . (\is_file($abs) ? \filemtime($abs) : '1');
                if (!\in_array($url, $args[$key], true)) {
                    $args[$key][] = $url;
                }
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'], $args['document']) || !$this->isRunning()) {
                return;
            }
            try {
                $this->render($args['smarty'], $args['document']);
            } catch (\Throwable $e) {
                \error_log('[BlackFriday] render: ' . $e->getMessage());
            }
        });
    }

    private function render(JTLSmarty $smarty, $doc): void
    {
        $body = $doc->find('body')->eq(0);
        if ($body->length < 1) {
            // Varkombi-Wechsel lädt nur #result-wrapper per ?isAjax nach
            if (
                (int)$this->getSettings()['productBlock'] === 1
                && Shop::getPageType() === \PAGE_ARTIKEL
                && $doc->find('#result-wrapper')->length > 0
                && $doc->find('#bf-product-deal')->length < 1
            ) {
                $this->assignBase($smarty);
                $this->injectProduct($smarty, $doc);
            }
            return;
        }
        if ($doc->find('#bf-topbar')->length > 0) {
            return;
        }
        if ($this->isPreview() && !\headers_sent()) {
            \header('Cache-Control: private, no-store');
        }
        $s   = $this->getSettings();
        $svc = $this->service();
        $this->assignBase($smarty);

        $classes = 'bf-active' . ((int)$s['theme'] === 1 ? ' bf-theme' : '');

        $series = $svc->getMeta('series_json');
        if ($series === '') {
            $series = (string)\json_encode($svc->buildSeriesMap());
            $svc->setMeta('series_json', $series);
        }
        $head = $doc->find('head')->eq(0);
        if ($head->length > 0) {
            $head->append('<script>window.BF_SERIES=' . \str_replace('</', '<\/', $series) . ';</script>');
        }

        if ((int)$s['headerBar'] === 1) {
            $html = $this->fetchTpl($smarty, 'bf-topbar.tpl');
            if ($html !== '') {
                $body->prepend($html);
            }
        }

        if ((int)$s['homeBlock'] === 1 && $this->isHomepageRequest()) {
            $smarty->assign('bfCategories', $this->categoryTiles())
                ->assign('bfShopUrl', \rtrim((string)Shop::getURL(), '/'));
            $html = $this->fetchTpl($smarty, 'bf-home.tpl');
            if ($html !== '') {
                $this->injectHome($doc, $html, (string)$s['injectSelector']);
            }
        }

        if ($doc->find('#bf-landing-slot')->length > 0 && $this->injectLanding($smarty, $doc)) {
            $classes .= ' bf-landing-page';
        }

        if ((int)$s['productBlock'] === 1 && Shop::getPageType() === \PAGE_ARTIKEL) {
            if ($this->injectProduct($smarty, $doc)) {
                $classes .= ' bf-deal-page';
            }
        }

        $this->injectCouponNote($doc);
        $this->injectCartBadges($doc);
        $this->injectKonfiguratorButton($smarty, $doc);

        $body->addClass($classes);
    }

    private function assignBase(JTLSmarty $smarty): void
    {
        $s       = $this->getSettings();
        $max     = $this->service()->maxDiscount();
        $endTs   = $s['end'] !== '' ? (int)\strtotime($s['end']) : 0;
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $btnUrl  = \trim((string)$s['buttonUrl']);
        $topUrl  = $btnUrl === '' ? $shopUrl . '/' . self::LANDING_SLUG : '';
        if ($btnUrl === '') {
            $btnUrl = $this->isHomepageRequest() ? '#bf-home' : $shopUrl . '/#bf-home';
        } elseif (!\preg_match('~^(https?:)?//|^#~i', $btnUrl)) {
            $btnUrl = $shopUrl . '/' . \ltrim($btnUrl, '/');
        } elseif ($btnUrl[0] === '#' && !$this->isHomepageRequest()) {
            // Anker existiert nur auf der Startseite
            $btnUrl = $shopUrl . '/' . $btnUrl;
        }
        if ($btnUrl === '#bf-home' && (int)$s['homeBlock'] === 1) {
            $btnUrl = '#bf-deals';
        } elseif ($btnUrl === $shopUrl . '/#bf-home' && (int)$s['homeBlock'] === 1) {
            $btnUrl = $shopUrl . '/#bf-deals';
        }

        $smarty->assign('bf', [
            'max'         => self::fmtPct($max),
            'endIso'      => $endTs > 0 && (int)($s['countdown'] ?? 1) === 1 ? \date('c', $endTs) : '',
            'topText'     => \str_replace('{max}', self::fmtPct($max), (string)$s['topText']),
            'homeTitle'   => (string)$s['homeTitle'],
            'homeSub'     => (string)$s['homeSub'],
            'buttonLabel' => (string)$s['buttonLabel'],
            'buttonUrl'   => $btnUrl,
            'topbarUrl'   => $topUrl !== '' ? $topUrl : $btnUrl,
        ]);
    }

    /**
     * Button im Konfigurator-Banner (FilterKonfiguratorSearch) -> springt zu den Black-Friday-Kategorien.
     */
    private function injectKonfiguratorButton(JTLSmarty $smarty, $doc): void
    {
        try {
            $banner = $doc->find('.konfig-banner')->eq(0);
            if ($banner->length < 1 || $doc->find('.bf-konfig-btn')->length > 0) {
                return;
            }
            $bf = $smarty->getTemplateVars('bf');
            if (!\is_array($bf) || (string)($bf['buttonUrl'] ?? '') === '') {
                return;
            }
            $label = \trim((string)($bf['buttonLabel'] ?? ''));
            $label = $label !== '' ? $label : 'Zu den Deals';
            $banner->append(
                '<div class="bf-konfig-cta"><a class="bf-konfig-btn" href="'
                . \htmlspecialchars((string)($bf['topbarUrl'] ?? $bf['buttonUrl']), \ENT_QUOTES, 'UTF-8') . '">'
                . '<span class="bf-konfig-btn__tag">BLACK FRIDAY</span> '
                . \htmlspecialchars($label, \ENT_QUOTES, 'UTF-8') . ' &rarr;</a></div>'
            );
        } catch (\Throwable $e) {
            \error_log('[BlackFriday] konfigurator button: ' . $e->getMessage());
        }
    }

    private function injectHome($doc, string $html, string $selector): void
    {
        foreach ([$selector, '#womosi-fk-konfigurator-anchor', '.index-filter-konfigurator'] as $sel) {
            if ($sel === '') {
                continue;
            }
            try {
                $node = $doc->find($sel)->eq(0);
            } catch (\Throwable $e) {
                continue;
            }
            if ($node->length > 0) {
                $node->after($html);
                return;
            }
        }
        $content = $doc->find('#content')->eq(0);
        if ($content->length > 0) {
            $content->prepend($html);
        }
    }

    private function injectProduct(JTLSmarty $smarty, $doc): bool
    {
        $product = $smarty->getTemplateVars('Artikel');
        if (!\is_object($product) || !isset($product->Preise) || !\is_object($product->Preise)) {
            return false;
        }
        $id  = (int)($product->kVaterArtikel ?? 0) > 0 ? (int)$product->kVaterArtikel : (int)($product->kArtikel ?? 0);
        $cg       = (int)Frontend::getCustomerGroup()->getID();
        $simulate = $this->isPreview() && !DiscountService::isRunning($this->getSettings());
        $pct      = $this->service()->discountForProduct($id, $cg, $simulate);
        $real     = $simulate ? $pct : (float)($product->Preise->rabatt ?? 0);
        if ($pct <= 0 || $real <= 0 || ($simulate && !empty($product->Preise->Sonderpreis_aktiv))) {
            return false;
        }
        $net = (int)$smarty->getTemplateVars('NettoPreise') === 1 ? 1 : 0;
        if ($simulate) {
            $oldPrice = (float)($product->Preise->fVK[$net] ?? 0);
            $newPrice = \round($oldPrice * (1 - $real / 100), 2);
            $oldLoc   = Preise::getLocalizedPriceString($oldPrice);
            $newLoc   = Preise::getLocalizedPriceString($newPrice);
        } else {
            $newPrice = (float)($product->Preise->fVK[$net] ?? 0);
            $oldPrice = (float)($product->Preise->alterVK[$net] ?? 0);
            $oldLoc   = (string)($product->Preise->alterVKLocalized[$net] ?? '');
            $newLoc   = (string)($product->Preise->cVKLocalized[$net] ?? '');
        }
        $smarty->assign('bfDeal', [
            'pct'  => self::fmtPct($real),
            'old'  => $oldLoc,
            'new'  => $newLoc,
            'save'    => $oldPrice > $newPrice ? Preise::getLocalizedPriceString($oldPrice - $newPrice) : '',
            'preview' => $simulate,
        ]);
        $html = $this->fetchTpl($smarty, 'bf-product.tpl');
        if ($html === '') {
            return false;
        }
        $anchor = $doc->find('#product-offer .product-headline')->eq(0);
        if ($anchor->length > 0) {
            $anchor->after($html);
        } else {
            $info = $doc->find('#product-offer .product-info')->eq(0);
            if ($info->length < 1) {
                return false;
            }
            $info->prepend($html);
        }
        $gallery = $doc->find('#gallery_wrapper')->eq(0);
        if ($gallery->length > 0) {
            $gallery->prepend(
                '<div class="bf-ribbon" aria-hidden="true"><span>BLACK FRIDAY</span><strong>-'
                . \htmlspecialchars(self::fmtPct($real), \ENT_QUOTES, 'UTF-8') . '%</strong></div>'
            );
        }

        return true;
    }

    /**
     * @return list<array{name:string,url:string,img:string,pct:string}>
     */
    private function categoryTiles(): array
    {
        $db     = Shop::Container()->getDB();
        $langID = (int)Shop::getLanguageID();
        $rows   = $db->getObjects(
            'SELECT r.kKategorie, r.fRabatt,
                    COALESCE(NULLIF(ks.cName, ""), k.cName) AS cName,
                    s.cSeo, kp.cPfad
               FROM ' . DiscountService::T_RULE . ' r
         INNER JOIN tkategorie k ON k.kKategorie = r.kKategorie
          LEFT JOIN tkategoriesprache ks ON ks.kKategorie = r.kKategorie AND ks.kSprache = :lang
          LEFT JOIN tseo s ON s.cKey = "kKategorie" AND s.kKey = r.kKategorie AND s.kSprache = :lang
          LEFT JOIN tkategoriepict kp ON kp.kKategorie = r.kKategorie
              WHERE r.nActive = 1 AND r.fRabatt > 0
           GROUP BY r.kKategorie
           ORDER BY k.lft ASC
              LIMIT 36',
            ['lang' => $langID]
        );
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $cgID    = (int)Frontend::getCustomerGroup()->getID();
        $paths   = $this->service()->categoryPaths(\array_map(static fn($r): int => (int)$r->kKategorie, $rows), $langID);
        $groups  = [];
        foreach ($rows as $r) {
            $path  = $paths[(int)$r->kKategorie] ?? [];
            $top   = $path[0] ?? ['id' => (int)$r->kKategorie, 'name' => (string)$r->cName];
            $isTop = $top['id'] === (int)$r->kKategorie;
            $sub   = \array_map(static fn(array $p): string => $p['name'], \array_slice($path, 1, -1));
            $img = '';
            $seo = (string)($r->cSeo ?? '');
            $url = $seo !== '' ? $shopUrl . '/' . $seo : $shopUrl . '/?k=' . (int)$r->kKategorie;
            if ((string)($r->cPfad ?? '') !== '') {
                try {
                    $cat = new Kategorie((int)$r->kKategorie, $langID, $cgID);
                    $img = (string)($cat->getImage(Image::SIZE_MD) ?? '');
                    if ($img !== '' && !\preg_match('~^https?://~i', $img)) {
                        $img = $shopUrl . '/' . \ltrim($img, '/');
                    }
                    if (\str_contains($img, 'keinBild') || \str_contains($img, 'gfx/')) {
                        $img = '';
                    }
                } catch (\Throwable $e) {
                    $img = '';
                }
            }
            if (!isset($groups[$top['id']])) {
                $groups[$top['id']] = [
                    'title'  => $top['name'],
                    'oneRow' => \stripos((string)$top['name'], 'PKW') !== false,
                    'items'  => [],
                ];
            }
            $groups[$top['id']]['items'][] = [
                'name' => $isTop ? 'Alle ' . $top['name'] : (string)$r->cName,
                'sub'  => \implode(' › ', $sub),
                'url'  => $url,
                'img'  => $img,
                'pct'  => self::fmtPct((float)$r->fRabatt),
            ];
        }

        return \array_values($groups);
    }

    private function injectLanding(JTLSmarty $smarty, $doc): bool
    {
        $smarty->assign('bfCategories', $this->categoryTiles())
            ->assign('bfShopUrl', \rtrim((string)Shop::getURL(), '/'));
        $html = $this->fetchTpl($smarty, 'bf-landing.tpl');
        if ($html === '') {
            return false;
        }
        $slot = $doc->find('#bf-landing-slot')->eq(0);
        $col = $slot->parent();
        foreach (['col-lg-8', 'col-xl-9', 'ml-auto-util'] as $cls) {
            $col->removeClass($cls);
        }
        $col->addClass('col-12 bf-landing-col');
        $slot->replaceWith($html);
        foreach (['.index-heading-wrapper', '#content-wrapper .breadcrumb-container', '#content aside.sidepanel-left'] as $sel) {
            $doc->find($sel)->remove();
        }
        $doc->find('#content-wrapper')->removeClass('has-left-sidebar');

        return true;
    }

    /**
     * Legt die Plugin-Seite /blackfriday (versteckte Linkgruppe) einmalig an.
     */
    private function ensureLandingPage(): void
    {
        $pluginID = (int)$this->getPlugin()->getID();
        if ($pluginID <= 0) {
            return;
        }
        try {
            $svc = $this->service();
            if ($svc->getMeta('landing_link') !== '') {
                return;
            }
            $db    = Shop::Container()->getDB();
            $kLink = 0;
            $seo   = $db->getSingleObject(
                "SELECT kKey FROM tseo WHERE cKey = 'kLink' AND cSeo = :seo ORDER BY kSprache ASC LIMIT 1",
                ['seo' => self::LANDING_SLUG]
            );
            if ($seo !== null) {
                $kLink = (int)$seo->kKey;
            }
            if ($kLink <= 0) {
                $hidden = $db->select('tlinkgruppe', 'cTemplatename', 'hidden');
                $lgID   = (int)($hidden->kLinkgruppe ?? 0) > 0 ? (int)$hidden->kLinkgruppe : 1;
                $data   = [
                    'kLink'              => 0,
                    'kPlugin'            => $pluginID,
                    'cName'              => self::LANDING_TITLE,
                    'nLinkart'           => \LINKTYP_PLUGIN,
                    'nSort'              => 0,
                    'bSSL'               => 0,
                    'bIsActive'          => 1,
                    'bIsFluid'           => 1,
                    'cSichtbarNachLogin' => 'N',
                    'cNoFollow'          => 'N',
                    'cIdentifier'        => self::LANDING_IDENT,
                    'linkTarget'         => '_self',
                    'cKundengruppen'     => '_DBNULL_',
                    'kLinkgruppe'        => $lgID,
                ];
                foreach (LanguageHelper::getAllLanguages(0, true) as $language) {
                    $iso = $language->getIso();
                    $data['cName_' . $iso]            = self::LANDING_TITLE;
                    $data['cTitle_' . $iso]           = 'Black Friday Deals | Bremer Sitzbezüge';
                    $data['cSeo_' . $iso]             = self::LANDING_SLUG;
                    $data['cContent_' . $iso]         = '';
                    $data['cMetaTitle_' . $iso]       = 'Black Friday Deals – Sitzbezüge stark reduziert | Bremer Sitzbezüge';
                    $data['cMetaDescription_' . $iso] = 'Black Friday bei Bremer Sitzbezüge: Auto- und Wohnmobil-Sitzbezüge stark reduziert. Nur für kurze Zeit – jetzt Deals sichern.';
                }
                $kLink = (int)(new LinkAdmin($db, Shop::Container()->getCache()))->createOrUpdateLink($data)->getID();
            }
            if ($kLink <= 0) {
                return;
            }
            if ($db->getSingleObject('SELECT kLink FROM tpluginlinkdatei WHERE kLink = :k LIMIT 1', ['k' => $kLink]) === null) {
                $row                      = new \stdClass();
                $row->kPlugin             = $pluginID;
                $row->kLink               = $kLink;
                $row->cDatei              = self::LANDING_DATEI;
                $row->cTemplate           = self::LANDING_TEMPLATE;
                $row->cFullscreenTemplate = '_DBNULL_';
                $db->insert('tpluginlinkdatei', $row);
            }
            $svc->setMeta('landing_link', (string)$kLink);
            Shop::Container()->getCache()->flushTags([\CACHING_GROUP_CORE]);
        } catch (\Throwable $e) {
            \error_log('[BlackFriday] landing page: ' . $e->getMessage());
        }
    }

    private function fetchTpl(JTLSmarty $smarty, string $file): string
    {
        $path = $this->getPlugin()->getPaths()->getBasePath() . 'templates/' . $file;
        if (!\is_file($path)) {
            return '';
        }
        try {
            return \trim((string)$smarty->fetch('file:' . $path));
        } catch (\Throwable $e) {
            \error_log('[BlackFriday] tpl ' . $file . ': ' . $e->getMessage());
            return '';
        }
    }

    private function maybeScheduleTick(): void
    {
        if (\PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
            || !\function_exists('fastcgi_finish_request')
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || \preg_match('~^/(dbeS|install)~i', (string)($_SERVER['REQUEST_URI'] ?? ''))
        ) {
            return;
        }
        $flag = \sys_get_temp_dir() . '/bf_tick_' . \md5(__DIR__);
        $last = @\filemtime($flag);
        if ($last !== false && $last > \time() - 120) {
            return;
        }
        @\touch($flag);
        \register_shutdown_function(static function (): void {
            @\fastcgi_finish_request();
            \ignore_user_abort(true);
            @\set_time_limit(300);
            try {
                (new DiscountService(Shop::Container()->getDB()))->tick();
            } catch (\Throwable $e) {
                \error_log('[BlackFriday] tick: ' . $e->getMessage());
            }
        });
    }

    /**
     * Aktionscoupons nicht zusätzlich zum Black-Friday-Rabatt: Enthält der Warenkorb nur Deal-Artikel,
     * wird die Eingabe verworfen; sonst gilt der Coupon nur für die übrigen Artikel.
     */
    private function blockCouponsOnDeals(): void
    {
        $code = \trim((string)($_POST['Kuponcode'] ?? ''));
        if (($code === '' && !$this->hasStandardCoupon()) || !$this->couponRuleActive()) {
            return;
        }
        $scan = $this->scanCart(Frontend::getCart());
        if ($scan['deals'] === []) {
            return;
        }
        if ($code !== '' && $scan['free'] === []) {
            $coupon = (new Kupon())->getByCode($code);
            if ($coupon !== false && (int)$coupon->kKupon > 0 && $coupon->cKuponTyp === Kupon::TYPE_STANDARD) {
                unset($_POST['Kuponcode'], $_REQUEST['Kuponcode']);
                $this->warnCouponBlocked($scan['deals']);
            }
        }
        $this->adjustCouponForDeals(Frontend::getCart());
    }

    private function hasStandardCoupon(): bool
    {
        return isset($_SESSION['Kupon']) && ($_SESSION['Kupon']->cKuponTyp ?? '') === Kupon::TYPE_STANDARD;
    }

    private function couponRuleActive(): bool
    {
        return (int)($this->getSettings()['couponOnDeals'] ?? 0) !== 1;
    }

    /**
     * Coupon-Position neu berechnen: Basis sind nur Artikel ohne Black-Friday-Rabatt.
     */
    private function adjustCouponForDeals(Cart $cart): void
    {
        if (!$this->hasStandardCoupon() || !$this->couponRuleActive()) {
            return;
        }
        $scan = $this->scanCart($cart);
        if ($scan['deals'] === []) {
            return;
        }
        /** @var Kupon $coupon */
        $coupon  = $_SESSION['Kupon'];
        $percent = ($coupon->cWertTyp ?? '') === 'prozent';
        $base    = 0.0;
        foreach ($scan['free'] as $item) {
            if ((int)($coupon->nGanzenWKRabattieren ?? 1) === 0) {
                $base += $percent
                    ? (float)CartHelper::checkSetPercentCouponWKPos($item, $coupon)->fPreis
                    : CartHelper::getCouponProductsTotal($coupon, [$item]);
            } else {
                $base += (float)$item->fPreis * (float)$item->nAnzahl * ((100 + CartItem::getTaxRate($item)) / 100);
            }
        }
        $base = \round($base, 2);
        // Festpreis wird von JTL bei Bestellabschluss auf den Warenkorbwert zurückgesetzt → nicht kürzen
        if ($base <= 0 || (!$percent && (float)$coupon->fWert > $base)) {
            $cart->loescheSpezialPos(\C_WARENKORBPOS_TYP_KUPON);
            unset($_SESSION['Kupon']);
            $this->warnCouponBlocked($scan['deals']);

            return;
        }
        if (!$percent) {
            return;
        }
        $value = \round($base * (float)$coupon->fWert / 100, 2);
        $pos   = null;
        foreach ($cart->PositionenArr as $p) {
            if ((int)$p->nPosTyp === \C_WARENKORBPOS_TYP_KUPON) {
                $pos = $p;
                break;
            }
        }
        if ($pos === null) {
            return;
        }
        $current = \round((float)$pos->fPreis * (float)$pos->nAnzahl * ((100 + (float)$pos->fMwSt) / 100), 2);
        if (\abs($current + $value) < 0.01) {
            return;
        }
        $checksumOk = !empty($cart->cChecksumme) && $cart->cChecksumme === Cart::getChecksum($cart);
        $cart->erstelleSpezialPos(
            (object)['cName' => $pos->cName],
            1,
            $value * -1,
            (int)$coupon->kSteuerklasse,
            \C_WARENKORBPOS_TYP_KUPON
        );
        if ($checksumOk) {
            $cart->cChecksumme = Cart::getChecksum($cart);
        }
        Shop::Container()->getAlertService()->addInfo(
            'Black-Friday-Artikel sind bereits reduziert und vom Aktionscoupon ausgenommen. '
            . 'Der Coupon wurde nur auf die übrigen Artikel in Ihrem Warenkorb angerechnet.',
            'bfCouponPartial',
            ['saveInSession' => true, 'dismissable' => true]
        );
    }

    /**
     * @param list<array{name:string, pct:float}> $deals
     */
    private function warnCouponBlocked(array $deals): void
    {
        $max   = \max(\array_column($deals, 'pct'));
        $names = \array_slice(\array_unique(\array_filter(\array_column($deals, 'name'))), 0, 3);
        $list  = \implode(', ', \array_map(static fn(string $n): string => '„' . $n . '“', $names));
        Shop::Container()->getAlertService()->addWarning(
            'Für ' . ($list !== '' ? $list : 'Artikel in Ihrem Warenkorb')
            . ' erhalten Sie im Rahmen unserer Black-Friday-Aktion bereits einen höheren Rabatt (bis zu -'
            . self::fmtPct($max) . ' %). Ein Aktionscoupon kann daher nicht zusätzlich eingelöst werden.',
            'bfCouponBlocked',
            ['saveInSession' => true, 'dismissable' => true]
        );
    }

    /**
     * @return array{deals: list<array{name:string, pct:float}>, free: list<CartItem>}
     */
    private function scanCart(Cart $cart): array
    {
        $ids   = [];
        $items = [];
        foreach ($cart->PositionenArr ?? [] as $idx => $item) {
            if ((int)($item->nPosTyp ?? 0) !== \C_WARENKORBPOS_TYP_ARTIKEL || empty($item->Artikel)) {
                continue;
            }
            $own    = (int)($item->Artikel->kArtikel ?? 0);
            $parent = (int)($item->Artikel->kVaterArtikel ?? 0);
            foreach ([$own, $parent] as $id) {
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
            $items[] = [$item, $own, $parent, $idx];
        }
        if ($ids === []) {
            return ['deals' => [], 'free' => []];
        }
        $pct = [];
        foreach (Shop::Container()->getDB()->getObjects(
            'SELECT kArtikel, MAX(fRabatt) AS fRabatt FROM ' . DiscountService::T_APPLIED
            . ' WHERE kKundengruppe = :g AND fRabatt > 0 AND kArtikel IN (' . \implode(',', $ids) . ')'
            . ' GROUP BY kArtikel',
            ['g' => Frontend::getCustomerGroup()->getID()]
        ) as $r) {
            $pct[(int)$r->kArtikel] = (float)$r->fRabatt;
        }
        $deals = [];
        $free  = [];
        foreach ($items as [$item, $own, $parent, $idx]) {
            $p = \max($pct[$own] ?? 0.0, $pct[$parent] ?? 0.0);
            if ($p <= 0) {
                $free[] = $item;
                continue;
            }
            $deals[] = [
                'idx'  => (int)$idx,
                'item' => $item,
                'name' => \is_array($item->cName ?? null)
                    ? (string)($item->cName[Shop::getLanguageCode()] ?? \reset($item->cName))
                    : (string)($item->Artikel->cName ?? ''),
                'pct'  => $p,
            ];
        }

        return ['deals' => $deals, 'free' => $free];
    }

    private function injectCouponNote($doc): void
    {
        $inputs = $doc->find('input[name="Kuponcode"]');
        if ($inputs->length < 1 || !$this->couponRuleActive()) {
            return;
        }
        $scan = $this->scanCart(Frontend::getCart());
        if ($scan['deals'] === []) {
            return;
        }
        $text = $scan['free'] === []
            ? 'Ihr Warenkorb enthält nur bereits reduzierte Black-Friday-Artikel. Aktionscoupons können daher nicht zusätzlich eingelöst werden.'
            : 'Black-Friday-Artikel sind bereits reduziert und vom Aktionscoupon ausgenommen. Der Coupon gilt nur für die übrigen Artikel.';
        $note = '<div class="bf-coupon-note"><span class="bf-coupon-note__tag">BLACK FRIDAY</span>'
            . \htmlspecialchars($text, \ENT_QUOTES, 'UTF-8') . '</div>';
        foreach ($inputs as $input) {
            $form = pq($input)->parents('form')->eq(0);
            if ($form->length > 0 && $form->find('.bf-coupon-note')->length < 1) {
                $form->append($note);
            }
        }
    }

    /**
     * Markiert Black-Friday-Artikel im Warenkorb (Badge, Streichpreis, Ersparnis).
     */
    private function injectCartBadges($doc): void
    {
        try {
            $scan = $this->scanCart(Frontend::getCart());
            if ($scan['deals'] === []) {
                return;
            }
            $merchant = Frontend::getCustomerGroup()->isMerchant();
            $byIdx    = [];
            $byUrl    = [];
            $total    = 0.0;
            foreach ($scan['deals'] as $deal) {
                $info = $this->dealPriceInfo($deal, $merchant);
                $byIdx[$deal['idx']] = $info;
                $url = (string)($deal['item']->Artikel->cURLFull ?? '');
                if ($url !== '') {
                    $byUrl[$url] = $info;
                }
                $total += $info['save'];
            }

            // Warenkorb-Seite: Zuordnung über anzahl[<Positionsindex>]
            foreach ($doc->find('.cart-items-body') as $node) {
                $row = pq($node);
                $idx = null;
                foreach ($row->find('input') as $input) {
                    if (\preg_match('~^anzahl\[(\d+)\]$~', (string)pq($input)->attr('name'), $m)) {
                        $idx = (int)$m[1];
                        break;
                    }
                }
                if ($idx !== null && isset($byIdx[$idx])) {
                    $this->decorateCartRow($row, $byIdx[$idx], '.cart-items-name', '.cart-items-image');
                }
            }

            // Bestellvorgang (Bestätigung)
            foreach ($doc->find('.checkout-items-item') as $node) {
                $row  = pq($node);
                $info = $this->matchDealByLink($row, $byUrl);
                if ($info !== null) {
                    $this->decorateCartRow(
                        $row,
                        $info,
                        '.checkout-items-item-main p',
                        '.checkout-items-item-image-wrapper'
                    );
                }
            }

            // Mini-Warenkorb im Header
            foreach ($doc->find('.dropdown-cart-items tbody tr') as $node) {
                $row  = pq($node);
                $info = $this->matchDealByLink($row, $byUrl);
                if ($info === null || $row->hasClass('bf-mini-item')) {
                    continue;
                }
                $row->addClass('bf-mini-item');
                $name = $row->find('td .col')->eq(2);
                $html = '<span class="bf-mini-badge"><b>BLACK FRIDAY</b> -' . $info['pct'] . '%</span>';
                if ($name->length > 0) {
                    $name->append($html);
                } else {
                    $row->find('td')->eq(0)->append($html);
                }
            }

            if ($total >= 0.01) {
                $loc = Preise::getLocalizedPriceString($total);
                $box = $doc->find('.basket-summary .card-body')->eq(0);
                if ($box->length > 0 && $box->find('.bf-cart-savings')->length < 1) {
                    $box->prepend(
                        '<div class="bf-cart-savings"><span class="bf-cart-savings__tag">BLACK FRIDAY</span>'
                        . '<span class="bf-cart-savings__label">Ihre Ersparnis</span>'
                        . '<strong class="bf-cart-savings__value">' . $loc . '</strong></div>'
                    );
                }
                $mini = $doc->find('.cart-dropdown .dropdown-body ul')->eq(0);
                if ($mini->length > 0 && $mini->find('.bf-mini-savings')->length < 1) {
                    $mini->prepend(
                        '<li class="bf-mini-savings">Black-Friday-Ersparnis'
                        . '<span class="cart-dropdown-total-item-price">' . $loc . '</span></li>'
                    );
                }
            }
        } catch (\Throwable $e) {
            \error_log('[BlackFriday] cart badges: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $deal
     * @return array{pct: string, old: float, unit: float, save: float}
     */
    private function dealPriceInfo(array $deal, bool $merchant): array
    {
        $item = $deal['item'];
        $pct  = (float)$deal['pct'];
        $unit = (float)($item->fPreis ?? 0);
        if (!$merchant) {
            $unit = Tax::getGross($unit, Tax::getSalesTax((int)($item->kSteuerklasse ?? 0)));
        }
        $old = $pct < 100 ? \round($unit / (1 - $pct / 100), 2) : 0.0;

        return [
            'pct'  => self::fmtPct($pct),
            'old'  => $old,
            'unit' => $unit,
            'save' => $old > $unit ? ($old - $unit) * (float)($item->nAnzahl ?? 1) : 0.0,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $byUrl
     */
    private function matchDealByLink($row, array $byUrl): ?array
    {
        foreach ($row->find('a') as $link) {
            $href = (string)pq($link)->attr('href');
            if (isset($byUrl[$href])) {
                return $byUrl[$href];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function decorateCartRow($row, array $info, string $nameSel, string $imgSel): void
    {
        if ($row->hasClass('bf-cart-item')) {
            return;
        }
        $badge = '<div class="bf-cart-badge"><span class="bf-cart-badge__tag">BLACK FRIDAY</span>'
            . '<span class="bf-cart-badge__pct">-' . $info['pct'] . '%</span>';
        if ($info['old'] > $info['unit']) {
            $badge .= '<span class="bf-cart-badge__old">statt <del>'
                . Preise::getLocalizedPriceString($info['old']) . '</del></span>'
                . '<span class="bf-cart-badge__save">Du sparst <strong>'
                . Preise::getLocalizedPriceString($info['save']) . '</strong></span>';
        }
        $badge .= '</div>';
        $row->addClass('bf-cart-item');
        $name = $row->find($nameSel)->eq(0);
        if ($name->length > 0) {
            $name->before($badge);
        } else {
            $row->prepend($badge);
        }
        $img = $row->find($imgSel)->eq(0);
        if ($img->length > 0) {
            $img->addClass('bf-cart-item__img')
                ->prepend('<span class="bf-cart-item__ribbon">-' . $info['pct'] . '%</span>');
        }
    }

    private function service(): DiscountService
    {
        return new DiscountService(Shop::Container()->getDB());
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return $this->settings ??= $this->service()->getSettings();
    }

    private function isRunning(): bool
    {
        try {
            return $this->isPreview() || DiscountService::isRunning($this->getSettings());
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isPreview(): bool
    {
        static $preview = null;
        if ($preview !== null) {
            return $preview;
        }
        $key = DiscountService::previewKey();
        if (isset($_GET['bfpreview'])) {
            $on = \hash_equals($key, (string)$_GET['bfpreview']);
            if (!\headers_sent()) {
                \setcookie('bf_preview', $on ? $key : '', [
                    'expires'  => $on ? \time() + 7200 : \time() - 3600,
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            return $preview = $on;
        }

        return $preview = \hash_equals($key, (string)($_COOKIE['bf_preview'] ?? ''));
    }

    public static function fmtPct(float $v): string
    {
        return \rtrim(\rtrim(\number_format($v, 1, ',', ''), '0'), ',');
    }

    private function isHomepageRequest(): bool
    {
        $pageType = Shop::getPageType();
        if ($pageType === \PAGE_STARTSEITE) {
            return true;
        }
        if ($pageType !== \PAGE_UNBEKANNT) {
            return false;
        }
        $path     = \rtrim((string)(\parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH) ?? ''), '/');
        $basePath = \rtrim((string)(\parse_url((string)Shop::getURL(), \PHP_URL_PATH) ?? ''), '/');

        return $path === $basePath || $path === $basePath . '/index.php';
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }

    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }
        try {
            $db       = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }
            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu FROM tpluginadminmenu WHERE kPlugin = :p AND cDateiname = :c LIMIT 1',
                ['p' => $pluginID, 'c' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu             = new \stdClass();
                $menu->kPlugin    = $pluginID;
                $menu->cName      = 'Black Friday';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort      = 1;
                $menu->nConf      = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
        }
    }

    public function uninstalled(bool $deleteData = true): void
    {
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery('UPDATE ' . DiscountService::T_SETTINGS . ' SET nActive = 0');
            (new DiscountService($db))->applyAll();
        } catch (\Throwable $e) {
        }
        parent::uninstalled($deleteData);
    }

    public function disabled(): void
    {
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery('UPDATE ' . DiscountService::T_SETTINGS . ' SET nActive = 0');
            (new DiscountService($db))->applyAll();
        } catch (\Throwable $e) {
        }
        parent::disabled();
    }
}
