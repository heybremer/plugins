<?php

namespace Plugin\Messe;

require_once __DIR__ . '/src/MesseDatabase.php';
require_once __DIR__ . '/src/MesseController.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Plugin\Messe\src\MesseDatabase;

final class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        try {
            $this->ensureAdminMenuEntry();
            MesseDatabase::ensurePluginPage((int)$this->getPlugin()->getID());
        } catch (\Throwable $e) {
            // Hata durumunda JTL boot sürecini bozma
        }

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            try {
                MesseDatabase::ensureAll();
                $pageType = Shop::getPageType();
                $isHomepage = $pageType === \PAGE_STARTSEITE;
                $isMessePage = $this->isMesseCalendarPage();

                // Güvenli array kontrolü
                if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                    $args['cPluginCss_arr'] = [];
                }
                if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                    $args['cPluginJsHead_arr'] = [];
                }

                if ($isMessePage) {
                    $cssPath = 'plugins/Messe/templates/css/messe-kalender.css?v=1.0.31';
                    $jsPath  = 'plugins/Messe/templates/js/messe-kalender.js?v=1.0.31';
                    if (!\in_array($cssPath, $args['cPluginCss_arr'], true)) {
                        $args['cPluginCss_arr'][] = $cssPath;
                    }
                    if (!\in_array($jsPath, $args['cPluginJsHead_arr'], true)) {
                        $args['cPluginJsHead_arr'][] = $jsPath;
                    }
                }

                if ($isHomepage) {
                    $settings = MesseDatabase::fetchSettings();
                    if ((int)($settings['active'] ?? 1) === 1 && (int)($settings['homepageActive'] ?? 1) === 1) {
                        $homeCss = 'plugins/Messe/templates/css/messe-home.css';
                        if (!\in_array($homeCss, $args['cPluginCss_arr'], true)) {
                            $args['cPluginCss_arr'][] = $homeCss;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Hata durumunda JTL render sürecini bozma
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            try {
                if (!isset($args['smarty'], $args['document'])) {
                    return;
                }
                if (Shop::getPageType() !== \PAGE_STARTSEITE) {
                    return;
                }

                MesseDatabase::ensureAll();
                $settings = MesseDatabase::fetchSettings();
                if ((int)($settings['active'] ?? 1) !== 1 || (int)($settings['homepageActive'] ?? 1) !== 1) {
                    return;
                }

                $events = MesseDatabase::fetchUpcomingEvents((int)($settings['homepageLimit'] ?? 6));
                if ($events === []) {
                    return;
                }

                /** @var JTLSmarty $smarty */
                $smarty = $args['smarty'];
                $doc    = $args['document'];

                if ($doc->find('#messe-home-widget')->length > 0) {
                    return;
                }

                $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/messe-home.tpl';
                if (!\is_file($tplPath)) {
                    return;
                }

                $smarty->assign('mkHomeHeadline', (string)($settings['headline'] ?? 'Messe Kalender'));
                $smarty->assign('mkHomeSubtitle', (string)($settings['subtitle'] ?? ''));
                $smarty->assign('mkHomeEvents', $events);
                $smarty->assign('mkCalendarUrl', MesseDatabase::calendarPageUrl($settings));

                $html = '';
                try {
                    $html = $smarty->fetch('file:' . $tplPath);
                } catch (\Throwable $e) {
                    return;
                }
                if ($html === '' || \strpos($html, 'messe-home') === false) {
                    return;
                }

                // Güvenli DOM injection
                $selectorRaw = \trim((string)($settings['homepageInjectSelector'] ?? ''));
                $mode = (string)($settings['homepageInjectMode'] ?? 'after');
                $mode = \in_array($mode, ['after', 'before', 'prepend', 'append'], true) ? $mode : 'after';
                
                // Güvenli selector listesi
                $selectors = [];
                if ($selectorRaw !== '') {
                    $selectors = \array_filter(\array_map('trim', \explode(',', $selectorRaw)), static fn($v) => $v !== '');
                }
                
                // Fallback selector'lar - güvenli olanları (body kasıtlı olarak dışarıda,
                // tüm sayfayı etkileme riskini önlemek için)
                if ($selectors === []) {
                    $selectors = ['div#content .container', 'main .container', 'div#content', 'main'];
                }

                $injected = false;
                foreach ($selectors as $sel) {
                    try {
                        $target = $doc->find($sel)->eq(0);
                        if ($target->length < 1) {
                            continue;
                        }
                        
                        // Güvenli injection
                        if ($mode === 'before') {
                            $target->before($html);
                        } elseif ($mode === 'prepend') {
                            $target->prepend($html);
                        } elseif ($mode === 'append') {
                            $target->append($html);
                        } else {
                            $target->after($html);
                        }
                        $injected = true;
                        break;
                    } catch (\Throwable $e) {
                        // Bir sonraki selector'ı dene - hata loglamayız
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                // Hata durumunda JTL render sürecini bozma
            }
        });
    }

    private function isMesseCalendarPage(): bool
    {
        $settings = MesseDatabase::fetchSettings();
        $slug = \ltrim((string)($settings['slug'] ?? 'messe-kalender'), '/');
        if ($slug === '') {
            $slug = 'messe-kalender';
        }

        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $path = \rtrim($path, '/');

        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
        $shopBasePath = \rtrim($shopBasePath, '/');

        $expected = $shopBasePath . '/' . $slug;
        if ($expected === '/') {
            $expected = '/' . $slug;
        }

        return $path === \rtrim($expected, '/');
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
                'SELECT kPluginAdminMenu
                   FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin
                    AND cDateiname = :cDateiname
                  LIMIT 1',
                ['kPlugin' => $pluginID, 'cDateiname' => $file]
            );
            if ($exists === null) {
                $menuItem = new \stdClass();
                $menuItem->kPlugin = $pluginID;
                $menuItem->cName = 'Messe Kalender';
                $menuItem->cDateiname = $file;
                $menuItem->nSort = 25;
                $menuItem->nConf = 0;
                $db->insert('tpluginadminmenu', $menuItem);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
