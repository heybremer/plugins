<?php

declare(strict_types=1);

namespace Plugin\LagerProdukt;

require_once __DIR__ . '/src/LagerProduktService.php';

use JTL\Events\Dispatcher;
use JTL\Language\LanguageHelper;
use JTL\Link\Admin\LinkAdmin;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $pageEnsured      = false;
    private static bool $adminMenuEnsured = false;

    private const SLUG     = LagerProduktService::PAGE_SLUG;
    private const TITLE    = 'Lager Sitzbezüge 1+1';
    private const TEMPLATE = 'lager-produkt.tpl';
    private const DATEI    = 'lager_produkt.php';
    private const IDENT    = 'womosi_lager_produkt';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();

        if (\defined('HOOK_WARENKORB_CLASS_FUEGEEIN')) {
            $dispatcher->hookInto(\HOOK_WARENKORB_CLASS_FUEGEEIN, function (array &$args): void {
                LagerProduktService::applyDynamicCartTitle($args);
            });
        }

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'])) {
                return;
            }
            if (Shop::getPageType() === \PAGE_ARTIKEL && isset($args['document'])) {
                $this->injectProductDetailVariants($args['smarty'], $args['document']);
                return;
            }
            if (!$this->isOurPage()) {
                return;
            }
            $this->assignFrontendVars($args['smarty']);
        });

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (Shop::getPageType() === \PAGE_ARTIKEL) {
                if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                    $args['cPluginCss_arr'] = [];
                }
                foreach ([
                    'plugins/LagerProdukt/templates/css/product-detail-variants.css',
                    'plugins/LagerProdukt/templates/css/product-detail-vehicle-picker.css',
                ] as $detailCss) {
                    if (!\in_array($detailCss, $args['cPluginCss_arr'], true)) {
                        $args['cPluginCss_arr'][] = $detailCss;
                    }
                }
                if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                    $args['cPluginJsHead_arr'] = [];
                }
                $detailJs = 'plugins/LagerProdukt/templates/js/product-detail-vehicle-picker.js';
                if (!\in_array($detailJs, $args['cPluginJsHead_arr'], true)) {
                    $args['cPluginJsHead_arr'][] = $detailJs;
                }
                return;
            }
            if (!$this->isOurPage()) {
                return;
            }
            foreach (['cPluginCss_arr', 'cPluginJsHead_arr'] as $key) {
                if (!isset($args[$key]) || !\is_array($args[$key])) {
                    $args[$key] = [];
                }
            }
            $css = 'plugins/LagerProdukt/templates/css/lager-produkt.css';
            $js  = 'plugins/LagerProdukt/templates/js/lager-produkt.js';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
            if (!\in_array($js, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $js;
            }
        });
    }

    private function injectProductDetailVariants(JTLSmarty $smarty, object $doc): void
    {
        $article = $smarty->getTemplateVars('Artikel');
        $artNr   = \is_object($article) ? (string)($article->cArtNr ?? '') : '';
        $context = LagerProduktService::getProductDetailVariantContext($artNr);
        if ($context === null) {
            return;
        }
        $this->moveArticleNumberUnderTitle($doc);
        try {
            $pickerCss = $doc->find('link[href*="product-detail-vehicle-picker.css"]')->eq(0);
            if ($pickerCss->length > 0) {
                $href = (string)$pickerCss->attr('href');
                if ($href !== '' && \strpos($href, 'lpv=1.0.30') === false) {
                    $pickerCss->attr('href', $href . (\strpos($href, '?') === false ? '?' : '&') . 'lpv=1.0.30');
                }
            }
            $pickerScript = $doc->find('script[src*="product-detail-vehicle-picker.js"]')->eq(0);
            if ($pickerScript->length > 0) {
                $src = (string)$pickerScript->attr('src');
                if ($src !== '' && \strpos($src, 'lpv=1.0.30') === false) {
                    $pickerScript->attr('src', $src . (\strpos($src, '?') === false ? '?' : '&') . 'lpv=1.0.30');
                }
            }
            $smarty->assign('lpDetailVariants', $context);
            if ($doc->find('#lp-vehicle-picker')->length === 0) {
                $pickerHtml = $smarty->fetch('file:' . __DIR__ . '/templates/product-detail-vehicle-picker.tpl');
                $anchor = $doc->find('ul.info-essential, .info-essential.list-unstyled')->eq(0);
                if ($anchor->length === 0) {
                    $anchor = $doc->find('h1.product-title.h2')->eq(0);
                }
                if ($anchor->length === 0) {
                    $anchor = $doc->find('.product-title.h2, .product-title')->eq(0);
                }
                if ($anchor->length > 0 && \trim($pickerHtml) !== '') {
                    $anchor->after($pickerHtml);
                }
            }
            if ($doc->find('#lp-detail-variants')->length > 0 || empty($context['showVariants'])) {
                return;
            }
            $html = $smarty->fetch('file:' . __DIR__ . '/templates/product-detail-variants.tpl');
            if (\trim($html) === '') {
                return;
            }
            $target = $doc->find('#lp-vehicle-picker')->eq(0);
            if ($target->length === 0) {
                $target = $doc->find('#add-to-cart')->eq(0);
            }
            if ($target->length > 0) {
                $target->after($html);
                return;
            }
            $fallback = $doc->find('#product-offer .product-info-inner')->eq(0);
            if ($fallback->length > 0) {
                $fallback->append($html);
            }
        } catch (\Throwable $e) {
            // Product detail must remain usable if the optional widget cannot render.
        }
    }

    private function moveArticleNumberUnderTitle(object $doc): void
    {
        $title = $doc->find('h1.product-title.h2')->eq(0);
        if ($title->length === 0) {
            $title = $doc->find('.product-title.h2, h1.product-title')->eq(0);
        }
        $info = $doc->find('ul.info-essential.list-unstyled, ul.info-essential, .info-essential.list-unstyled')->eq(0);
        if ($title->length === 0 || $info->length === 0) {
            return;
        }
        $html = \trim((string)$info->htmlOuter());
        if ($html === '') {
            $inner = (string)$info->html();
            if ($inner === '') {
                return;
            }
            $html = '<ul class="info-essential list-unstyled">' . $inner . '</ul>';
        }
        $info->remove();
        $title->after($html);
    }

    private function assignFrontendVars(JTLSmarty $smarty): void
    {
        $shopUrl = Shop::getURL();
        $page    = LagerProduktService::currentPage();
        $pid = $page['kID'] ?? 0;
        $smarty->assign('lpCssUrl', $shopUrl . '/plugins/LagerProdukt/templates/css/lager-produkt.css?v=1.0.35');
        $smarty->assign('lpJsUrl', $shopUrl . '/plugins/LagerProdukt/templates/js/lager-produkt.js?v=1.0.34');
        $smarty->assign('lpHeadline', $page['cName'] ?? self::TITLE);
        $smarty->assign('lpCategoryName', $page['cName'] ?? '');
        $smarty->assign('lpCategoryFound', $page !== null);
        $smarty->assign('lpProducts', LagerProduktService::loadProducts($pid));
        $smarty->assign('lpGroups', LagerProduktService::loadGroupedProducts($pid));
        $smarty->assign('lpSeats', LagerProduktService::loadCompatibleSeats($pid));
    }

    private function isOurPage(): bool
    {
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        foreach (LagerProduktService::allSlugs() as $slug) {
            if ($slug !== '' && \strpos($path, $slug) !== false) {
                return true;
            }
        }

        return \strpos($path, self::SLUG) !== false;
    }

    private function ensurePluginPages(int $pluginID): void
    {
        if (self::$pageEnsured) {
            return;
        }
        self::$pageEnsured = true;
        if ($pluginID <= 0) {
            return;
        }
        foreach (LagerProduktService::listPages() as $page) {
            $this->ensureOnePluginPage($pluginID, $page['cName'], $page['cSlug']);
        }
    }

    private function ensureOnePluginPage(int $pluginID, string $title, string $slug): void
    {
        $title = \trim($title);
        $slug  = LagerProduktService::normalizeSlug($slug);
        if ($title === '' || $slug === '') {
            return;
        }
        try {
            $db    = Shop::Container()->getDB();
            $kLink = $this->findLinkBySlug($db, $slug);

            $hiddenLg = $db->select('tlinkgruppe', 'cTemplatename', 'hidden');
            $lgID     = (int)($hiddenLg->kLinkgruppe ?? 1);
            if ($lgID <= 0) {
                $lgID = 1;
            }

            if ($kLink <= 0) {
                $linkAdmin = new LinkAdmin($db, Shop::Container()->getCache());
                $data      = [
                    'kLink'              => 0,
                    'kPlugin'            => $pluginID,
                    'cName'              => $title,
                    'nLinkart'           => \LINKTYP_PLUGIN,
                    'nSort'              => 0,
                    'bSSL'               => 0,
                    'bIsActive'          => 1,
                    'bIsFluid'           => 0,
                    'cSichtbarNachLogin' => 'N',
                    'cNoFollow'          => 'N',
                    'cIdentifier'        => self::IDENT . '_' . $slug,
                    'linkTarget'         => '_self',
                    'cKundengruppen'     => '_DBNULL_',
                    'kLinkgruppe'        => $lgID,
                ];
                foreach (LanguageHelper::getAllLanguages(0, true) as $language) {
                    $iso = $language->getIso();
                    $data['cName_' . $iso]    = $title;
                    $data['cTitle_' . $iso]   = $title;
                    $data['cSeo_' . $iso]     = $slug;
                    $data['cContent_' . $iso] = '';
                }
                $linkObj = $linkAdmin->createOrUpdateLink($data);
                $kLink   = (int)$linkObj->getID();
            }

            if ($kLink <= 0) {
                return;
            }

            $pld = $db->getSingleObject(
                'SELECT kLink FROM tpluginlinkdatei WHERE kLink = :k LIMIT 1',
                ['k' => $kLink]
            );
            if ($pld === null) {
                $row                      = new \stdClass();
                $row->kPlugin             = $pluginID;
                $row->kLink               = $kLink;
                $row->cDatei              = self::DATEI;
                $row->cTemplate           = self::TEMPLATE;
                $row->cFullscreenTemplate = '_DBNULL_';
                $db->insert('tpluginlinkdatei', $row);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function findLinkBySlug(object $db, string $slug): int
    {
        $seo = $db->getSingleObject(
            "SELECT kKey FROM tseo WHERE cKey = 'kLink' AND cSeo = :seo ORDER BY kSprache ASC LIMIT 1",
            ['seo' => $slug]
        );

        return $seo !== null ? (int)($seo->kKey ?? 0) : 0;
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
                'SELECT kPluginAdminMenu FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin AND cDateiname = :c LIMIT 1',
                ['kPlugin' => $pluginID, 'c' => 'dashboard.php']
            );
            if ($exists === null) {
                $menu             = new \stdClass();
                $menu->kPlugin    = $pluginID;
                $menu->cName      = 'LagerProdukt';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort      = 10;
                $menu->nConf      = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
