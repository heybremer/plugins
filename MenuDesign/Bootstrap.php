<?php

declare(strict_types=1);

namespace Plugin\MenuDesign;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
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
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 0) !== 1) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!isset($args['cPluginJsHead_arr']) || !\is_array($args['cPluginJsHead_arr'])) {
                $args['cPluginJsHead_arr'] = [];
            }
            $css = 'plugins/MenuDesign/templates/css/menu-design.css?v=1.0.12';
            $js  = 'plugins/MenuDesign/templates/js/menu-design.js';
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
            if (!isset($args['document'])) {
                return;
            }
            $settings = $this->fetchSettings();
            if ((int)($settings['active'] ?? 0) !== 1) {
                return;
            }

            $doc = $args['document'];
            $body = $doc->find('body')->eq(0);
            if ($body->length < 1) {
                return;
            }

            $classes = 'womosi-menu-design md-style-' . \preg_replace('/[^a-z0-9_-]/i', '', (string)($settings['style'] ?? 'modern'));
            if ($body->length > 0) {
                $existing = (string)$body->attr('class');
                if (\strpos($existing, 'womosi-menu-design') === false) {
                    $body->attr('class', \trim($existing . ' ' . $classes));
                }
                $body->attr('data-md-style', (string)($settings['style'] ?? 'modern'));
                $body->attr('data-md-mega', (int)($settings['megaMenu'] ?? 1) === 1 ? '1' : '0');
                $body->attr('data-md-mobile', (int)($settings['mobileEnhance'] ?? 1) === 1 ? '1' : '0');
                $sel = \trim((string)($settings['menuSelector'] ?? ''));
                if ($sel !== '') {
                    $body->attr('data-md-selector', $sel);
                }
            }

            // Remove JS-added attributes that Smarty may have cached — reset each request
            // so the JS portal logic always starts fresh.
            $mainNav = $doc->find('#mainNavigation')->eq(0);
            if ($mainNav->length > 0) {
                $mainNav->removeAttr('data-md-ready');
                $existingNavClass = (string)$mainNav->attr('class');
                $cleanNavClass = \trim(\preg_replace('/\bmd-enhanced-nav\b/', '', $existingNavClass));
                $mainNav->attr('class', $cleanNavClass);
            }

            $accent = $this->sanitizeColor((string)($settings['accentColor'] ?? '#1565c0'));
            $radius = (int)($settings['borderRadius'] ?? 10);
            if ($radius < 0) {
                $radius = 0;
            }
            if ($radius > 24) {
                $radius = 24;
            }

            $cssVars = ':root{'
                . '--md-accent:' . $accent . ';'
                . '--md-accent-soft:' . $accent . '1a;'
                . '--md-radius:' . $radius . 'px;'
                . '}';

            $head = $doc->find('head')->eq(0);
            if ($head->length > 0 && $doc->find('style#menu-design-vars')->length < 1) {
                $head->append('<style id="menu-design-vars">' . $cssVars . '</style>');
            }
            if ($doc->find('style#menu-design-critical')->length < 1 && $head->length > 0) {
                $head->append(
                    '<style id="menu-design-critical">'
                    . 'body.womosi-menu-design #mainNavigation.nav-scrollbar,'
                    . 'body.womosi-menu-design #mainNavigation,.nav-scrollbar,.nav-scrollbar-inner{overflow:visible!important}'
                    . '#mainNavigation.navbar-collapse{height:auto!important;max-height:none!important}'
                    . 'body.womosi-menu-design #main-wrapper,body.womosi-menu-design .index-filter-konfigurator,'
                    . 'body.womosi-menu-design .womosi-konfigurator,body.womosi-menu-design .videoblinder,'
                    . 'body.womosi-menu-design #rabatt-header-bar'
                    . '{z-index:0!important;position:relative}'
                    . '.md-dropdown-portal.md-dropdown-portal-visible{display:block!important;visibility:visible!important;'
                    . 'opacity:1!important;z-index:2147483646!important;position:fixed!important;'
                    . 'left:0!important;width:100vw!important;max-width:100vw!important}'
                    . 'body.md-nav-dropdown-open .index-filter-konfigurator,'
                    . 'body.md-nav-dropdown-open .womosi-konfigurator,'
                    . 'body.md-nav-dropdown-open .videoblinder,'
                    . 'body.md-nav-dropdown-open #rabatt-header-bar{z-index:0!important;pointer-events:none}'
                    . '</style>'
                );
            }
            if ($doc->find('script#menu-design-flag')->length < 1 && $head->length > 0) {
                $head->append('<script id="menu-design-flag">window.WomosiMenuDesignActive=1;</script>');
            }
        });
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }

    private function sanitizeColor(string $color): string
    {
        $color = \trim($color);
        if (\preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            return $color;
        }

        return '#1565c0';
    }

    private function ensureSettingsTable(): void
    {
        if (self::$settingsEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_menu_design_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cAccentColor VARCHAR(16) NOT NULL DEFAULT "#1565c0",
                    cStyle VARCHAR(24) NOT NULL DEFAULT "modern",
                    nMegaMenu TINYINT(1) NOT NULL DEFAULT 1,
                    nMobileEnhance TINYINT(1) NOT NULL DEFAULT 1,
                    nBorderRadius INT NOT NULL DEFAULT 10,
                    cMenuSelector VARCHAR(255) NOT NULL DEFAULT "",
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $row = $db->getSingleObject('SELECT kID FROM twomosi_menu_design_settings LIMIT 1');
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nActive = 1;
                $ins->cAccentColor = '#1565c0';
                $ins->cStyle = 'modern';
                $ins->nMegaMenu = 1;
                $ins->nMobileEnhance = 1;
                $ins->nBorderRadius = 10;
                $ins->cMenuSelector = '';
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_menu_design_settings', $ins);
            }
            self::$settingsEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{
     *   active:int,
     *   accentColor:string,
     *   style:string,
     *   megaMenu:int,
     *   mobileEnhance:int,
     *   borderRadius:int,
     *   menuSelector:string
     * }
     */
    public function fetchSettings(): array
    {
        $defaults = [
            'active'        => 1,
            'accentColor'   => '#1565c0',
            'style'         => 'modern',
            'megaMenu'      => 1,
            'mobileEnhance' => 1,
            'borderRadius'  => 10,
            'menuSelector'  => '',
        ];
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_menu_design_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }

            $style = (string)($row->cStyle ?? 'modern');
            if (!\in_array($style, ['modern', 'minimal', 'bold'], true)) {
                $style = 'modern';
            }

            return [
                'active'        => (int)($row->nActive ?? 1),
                'accentColor'   => (string)($row->cAccentColor ?? $defaults['accentColor']),
                'style'         => $style,
                'megaMenu'      => (int)($row->nMegaMenu ?? 1),
                'mobileEnhance' => (int)($row->nMobileEnhance ?? 1),
                'borderRadius'  => (int)($row->nBorderRadius ?? 10),
                'menuSelector'  => (string)($row->cMenuSelector ?? ''),
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
                $menu->cName = 'Menu Design';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 14;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
