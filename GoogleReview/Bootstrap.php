<?php

namespace Plugin\GoogleReview;

require_once __DIR__ . '/src/GoogleReviewDatabase.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $this->ensureAdminMenuEntry();

        // CSS/JS nur Startseite (Widget nur dort injiziert).
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            // CSS
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }

            $cssPath = 'plugins/GoogleReview/templates/css/google_review.css';
            if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $cssPath;
            }

            // JS (head)
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }

            $jsPath = 'plugins/GoogleReview/templates/js/google_review.js';
            if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $jsPath;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!$this->isHomepageRequest()) {
                return;
            }
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            GoogleReviewDatabase::ensureAll();

            // Fallback: bazı temalarda HOOK_LETZTERINCLUDE_CSS_JS listeleri basılmayabiliyor.
            // Bu durumda CSS/JS'yi doğrudan HTML'e enjekte et.
            try {
                $shopUrl = rtrim((string)Shop::getURL(), '/');
                $cssHref = $shopUrl . '/plugins/GoogleReview/templates/css/google_review.css';
                $jsSrc   = $shopUrl . '/plugins/GoogleReview/templates/js/google_review.js';

                $head = $doc->find('head')->eq(0);
                if ($head->length > 0 && stripos($doc->htmlOuter(), 'google_review.css') === false) {
                    $head->append('<link rel="stylesheet" href="' . htmlspecialchars($cssHref, ENT_QUOTES) . '">');
                }
                if (stripos($doc->htmlOuter(), 'google_review.js') === false) {
                    $body = $doc->find('body')->eq(0);
                    if ($body->length > 0) {
                        $body->append('<script src="' . htmlspecialchars($jsSrc, ENT_QUOTES) . '" defer></script>');
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $settings = $this->fetchSettings();
            $reviews  = $this->fetchReviews();
            // "Letzte Bewertung" alanı: yorumlarda en son güncelleme tarihi
            $lastReviewTime = '';
            try {
                $db = Shop::Container()->getDB();
                $maxRow = $db->getSingleObject("SELECT MAX(dUpdated) AS dMax FROM twomosi_google_review_items");
                $dMax = isset($maxRow->dMax) ? (string)$maxRow->dMax : '';
                if ($dMax !== '' && $dMax !== '0000-00-00 00:00:00') {
                    $ts = strtotime($dMax);
                    if ($ts !== false) {
                        $lastReviewTime = date('d.m.Y H:i', $ts);
                    }
                }
            } catch (\Throwable $e) {
                $lastReviewTime = '';
            }
            if ($lastReviewTime === '') {
                // Fallback: settings güncelleme zamanı
                $lastReviewTime = (string)($settings['updatedAt'] ?? '');
            }

            $smarty->assign('googleReviewSettings', $settings);
            $smarty->assign('googleReviewItems', $reviews);
            $smarty->assign('googleReviewLastTimeText', $lastReviewTime);

            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/google_review.tpl';
            if (!\is_file($tplPath)) {
                return;
            }
            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '' || \strpos($html, 'google-review-section') === false) {
                return;
            }

            // Enjeksiyon hedefi admin ayarından seçilebilir
            $mode = (string)($settings['injectMode'] ?? 'after_cardproduct');
            $selector = trim((string)($settings['injectSelector'] ?? ''));

            $injected = false;
            if ($mode === 'after_selector' && $selector !== '') {
                $target = $doc->find($selector)->eq(0);
                if ($target->length > 0) {
                    $target->after($html);
                    $injected = true;
                }
            }
            if (!$injected && $mode === 'after_configurator') {
                $afterConfigurator = $doc->find('div.index-filter-konfigurator')->eq(0);
                if ($afterConfigurator->length > 0) {
                    $afterConfigurator->after($html);
                    $injected = true;
                }
            }
            if (!$injected) {
                // default/fallback: CardProduct sonrası, yoksa konfigurator sonrası
                $afterCardProduct = $doc->find('[data-cardproduct-injected="1"]')->eq(0);
                if ($afterCardProduct->length > 0) {
                    $afterCardProduct->after($html);
                    $injected = true;
                }
            }
            if (!$injected) {
                $afterConfigurator = $doc->find('div.index-filter-konfigurator')->eq(0);
                if ($afterConfigurator->length > 0) {
                    $afterConfigurator->after($html);
                }
            }
        });
    }

    /**
     * Startseite: PAGE_STARTSEITE + Pfad-Fallback (Subfolder-Installs).
     */
    private function isHomepageRequest(): bool
    {
        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
        }
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $query = (string)(\parse_url($uri, \PHP_URL_QUERY) ?? '');
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
                $menuItem->cName      = 'GoogleReview Dashboard';
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
     * @return array<string, mixed>
     */
    private function fetchSettings(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $s = $db->getSingleObject("SELECT * FROM twomosi_google_review_settings ORDER BY kID ASC LIMIT 1");
            if ($s === null) {
                return [];
            }
            $rating = (float)($s->fRating ?? 0);
            $ratingFull = (int)\floor($rating);
            $ratingHalf = ($rating - $ratingFull >= 0.45) ? 1 : 0; // küçük sapmalara tolerans

            return [
                'business' => (string)($s->cBusinessName ?? ''),
                'rating'   => $rating,
                'ratingFull' => $ratingFull,
                'ratingHalf' => $ratingHalf,
                'total'    => (int)($s->nTotalReviews ?? 0),
                'writeUrl' => (string)($s->cWriteUrl ?? ''),
                'allUrl'   => (string)($s->cAllUrl ?? ''),
                'heading'  => (string)($s->cHeading ?? ''),
                'sub'      => (string)($s->cSubheading ?? ''),
                'updatedAt' => (string)($s->dUpdated ?? ''),
                'injectMode' => (string)($s->cInjectMode ?? 'after_cardproduct'),
                'injectSelector' => (string)($s->cInjectSelector ?? ''),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchReviews(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $rows = $db->getObjects(
                "SELECT kID, cAuthor, cTimeText, nStars, cText, cAvatarUrl
                   FROM twomosi_google_review_items
                  WHERE nActive = 1
                  ORDER BY nSort ASC, kID ASC
                  LIMIT 60"
            );
            if (!\is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id'     => (int)($r->kID ?? 0),
                    'author' => (string)($r->cAuthor ?? ''),
                    'time'   => (string)($r->cTimeText ?? ''),
                    'stars'  => max(1, min(5, (int)($r->nStars ?? 5))),
                    'text'   => (string)($r->cText ?? ''),
                    'avatar' => (string)($r->cAvatarUrl ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}

