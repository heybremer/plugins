<?php

declare(strict_types=1);

namespace Plugin\SeatFilter;

require_once __DIR__ . '/src/SeatFilterRepository.php';
require_once __DIR__ . '/src/SeatFilterController.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    private static bool $settingsEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureSettingsTable();
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if ($this->isBackendRequest()) {
                return;
            }
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 0) !== 1) {
                return;
            }
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }

            $targetPage = \trim((string)($settings['targetPage'] ?? ''));
            if (!$this->isTargetPage($targetPage)) {
                return;
            }

            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $doc    = $args['document'];

            if ($doc->find('#seat-filter')->length > 0) {
                return;
            }

            $smarty->assign('shopURL', Shop::getURL());
            $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/seat-filter.tpl';
            if (!\is_file($tplPath)) {
                return;
            }

            try {
                $html = $smarty->fetch('file:' . $tplPath);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '' || \strpos($html, 'seat-filter') === false) {
                return;
            }

            $injectTarget = \trim((string)($settings['injectSelector'] ?? ''));
            if ($injectTarget === '') {
                $injectTarget = '#content';
            }
            $injectMethod = \trim((string)($settings['injectMethod'] ?? 'prepend'));

            $target = $doc->find($injectTarget)->eq(0);
            if ($target->length < 1) {
                $target = $doc->find('#content')->eq(0);
            }
            if ($target->length < 1) {
                $target = $doc->find('main')->eq(0);
            }
            if ($target->length < 1) {
                return;
            }

            try {
                if ($injectMethod === 'append') {
                    $target->append($html);
                } elseif ($injectMethod === 'before') {
                    $target->before($html);
                } elseif ($injectMethod === 'after') {
                    $target->after($html);
                } else {
                    $target->prepend($html);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        });

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if ($this->isBackendRequest()) {
                return;
            }
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 0) !== 1) {
                return;
            }
            $targetPage = \trim((string)($settings['targetPage'] ?? ''));
            if (!$this->isTargetPage($targetPage)) {
                return;
            }

            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }

            $css = 'plugins/SeatFilter/templates/css/seat-filter.css';
            $js  = 'plugins/SeatFilter/templates/js/seat-filter.js';

            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
            if (!\in_array($js, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $js;
            }
        });

        if (\class_exists(SeatFilterController::class, false)) {
            $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
                /** @var Router $router */
                $router = $args['router'];
                $router->addRoute(
                    '/sf-search',
                    function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty) {
                        $controller = new SeatFilterController();
                        return $controller->search($request, $routeArgs);
                    },
                    'sf-search',
                    ['GET']
                );
            });
        }
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }

    private function isTargetPage(string $target): bool
    {
        if ($target === '' || $target === 'all') {
            return true;
        }
        $pt = Shop::getPageType();
        $map = [
            'home'    => \PAGE_STARTSEITE,
            'list'    => \PAGE_ARTIKELLISTE,
            'article' => \PAGE_ARTIKEL,
        ];

        return isset($map[$target]) && $pt === $map[$target];
    }

    private function ensureSettingsTable(): void
    {
        if (self::$settingsEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_seat_filter_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cTargetPage VARCHAR(32) NOT NULL DEFAULT "all",
                    cInjectSelector VARCHAR(128) NOT NULL DEFAULT "#content",
                    cInjectMethod VARCHAR(16) NOT NULL DEFAULT "prepend",
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $row = $db->getSingleObject('SELECT kID FROM twomosi_seat_filter_settings LIMIT 1');
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nActive         = 1;
                $ins->cTargetPage     = 'all';
                $ins->cInjectSelector = '#content';
                $ins->cInjectMethod   = 'prepend';
                $ins->dUpdated        = \date('Y-m-d H:i:s');
                $db->insert('twomosi_seat_filter_settings', $ins);
            }
            self::$settingsEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{active:int, targetPage:string, injectSelector:string, injectMethod:string}
     */
    public function fetchSettings(): array
    {
        $defaults = [
            'active'         => 1,
            'targetPage'     => 'all',
            'injectSelector' => '#content',
            'injectMethod'   => 'prepend',
        ];
        try {
            $db  = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_seat_filter_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }

            return [
                'active'         => (int)($row->nActive ?? 1),
                'targetPage'     => (string)($row->cTargetPage ?? 'all'),
                'injectSelector' => (string)($row->cInjectSelector ?? '#content'),
                'injectMethod'   => (string)($row->cInjectMethod ?? 'prepend'),
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
                $menu = new \stdClass();
                $menu->kPlugin     = $pluginID;
                $menu->cName       = 'Seat Filter';
                $menu->cDateiname  = 'dashboard.php';
                $menu->nSort       = 14;
                $menu->nConf       = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
