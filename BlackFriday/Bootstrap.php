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
            $smarty->assign('bfCategories', $this->categoryTiles());
            $html = $this->fetchTpl($smarty, 'bf-home.tpl');
            if ($html !== '') {
                $this->injectHome($doc, $html, (string)$s['injectSelector']);
            }
        }

        if ((int)$s['productBlock'] === 1 && Shop::getPageType() === \PAGE_ARTIKEL) {
            if ($this->injectProduct($smarty, $doc)) {
                $classes .= ' bf-deal-page';
            }
        }

        $this->injectCouponNote($doc);

        $body->addClass($classes);
    }

    private function assignBase(JTLSmarty $smarty): void
    {
        $s       = $this->getSettings();
        $max     = $this->service()->maxDiscount();
        $endTs   = $s['end'] !== '' ? (int)\strtotime($s['end']) : 0;
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $btnUrl  = \trim((string)$s['buttonUrl']);
        if ($btnUrl === '') {
            $btnUrl = '#bf-home';
        } elseif (!\preg_match('~^(https?:)?//|^#~i', $btnUrl)) {
            $btnUrl = $shopUrl . '/' . \ltrim($btnUrl, '/');
        }

        $smarty->assign('bf', [
            'max'         => self::fmtPct($max),
            'endIso'      => $endTs > 0 ? \date('c', $endTs) : '',
            'topText'     => \str_replace('{max}', self::fmtPct($max), (string)$s['topText']),
            'homeTitle'   => (string)$s['homeTitle'],
            'homeSub'     => (string)$s['homeSub'],
            'buttonLabel' => (string)$s['buttonLabel'],
            'buttonUrl'   => $btnUrl,
        ]);
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
                $groups[$top['id']] = ['title' => $top['name'], 'items' => []];
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
        foreach ($cart->PositionenArr ?? [] as $item) {
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
            $items[] = [$item, $own, $parent];
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
        foreach ($items as [$item, $own, $parent]) {
            $p = \max($pct[$own] ?? 0.0, $pct[$parent] ?? 0.0);
            if ($p <= 0) {
                $free[] = $item;
                continue;
            }
            $deals[] = [
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
        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
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
