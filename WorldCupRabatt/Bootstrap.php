<?php

declare(strict_types=1);

namespace Plugin\WorldCupRabatt;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

class Bootstrap extends Bootstrapper
{
    private static bool $settingsEnsured = false;

    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureSettingsTable();
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_CSS_JS_LIST, function (array &$args): void {
            if (!$this->shouldRegisterAssetsForMinify()) {
                return;
            }
            if (!isset($args['groups']) || !\is_array($args['groups'])) {
                return;
            }
            $this->registerAssetsInMinifyGroups($args['groups']);
        });

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

            if (!$this->isHomepageRequest()) {
                return;
            }

            $this->applyVersionedAssetsToMinifyArrays($args);
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
            if ($doc->find('#worldcup-rabatt-root')->length > 0) {
                return;
            }

            if (!$this->isHomepageRequest()) {
                return;
            }

            if ($this->useIndexTasarimMode()) {
                $html = $this->renderIndexHtml($settings);
            } else {
                if (!isset($args['smarty'])) {
                    return;
                }
                /** @var JTLSmarty $smarty */
                $smarty = $args['smarty'];
                $smarty->assign('wcrTitle', (string)($settings['title'] ?? ''));
                $smarty->assign('wcrIntro', (string)($settings['intro'] ?? ''));
                $smarty->assign('wcrButtonLabel', (string)($settings['buttonLabel'] ?? ''));
                $smarty->assign('wcrAttempts', (int)($settings['attempts'] ?? 1));
                $smarty->assign('wcrCode5', (string)($settings['code5'] ?? ''));
                $smarty->assign('wcrCode10', (string)($settings['code10'] ?? ''));
                $smarty->assign('wcrCode15', (string)($settings['code15'] ?? ''));
                $smarty->assign('wcrAutoOpen', (int)($settings['autoOpen'] ?? 0));
                $shopUrl = \rtrim((string)Shop::getURL(), '/');
                $smarty->assign('wcrPreshotSound', $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-preshot.mp3');
                $smarty->assign('wcrKickSound', $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-kick.wav');
                $smarty->assign('wcrGoalSong', $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-goal-song.mp3');
                $smarty->assign('wcrLogo', $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-wc2026-logo.png');

                $tplPath = $this->getPlugin()->getPaths()->getBasePath() . 'templates/worldcup-rabatt.tpl';
                if (!\is_file($tplPath)) {
                    return;
                }
                try {
                    $html = $smarty->fetch('file:' . $tplPath);
                } catch (\Throwable $e) {
                    return;
                }
            }

            if ($html === '' || \strpos($html, 'worldcup-rabatt-root') === false) {
                return;
            }

            $body = $doc->find('body')->eq(0);
            if ($body->length < 1) {
                return;
            }
            try {
                $body->append($html);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    /**
     * true = Entwicklung über Tasarim/index.* (nur Startseite).
     * Nach Freigabe auf false setzen → voller Plugin-Modus (templates/*).
     */
    private function useIndexTasarimMode(): bool
    {
        return false;
    }

    /**
     * @return array{css: string, js: string}
     */
    private function getAssetRelativePaths(): array
    {
        if ($this->useIndexTasarimMode()) {
            return [
                'css' => 'plugins/WorldCupRabatt/Tasarim/index.css',
                'js'  => 'plugins/WorldCupRabatt/Tasarim/index.js',
            ];
        }

        return [
            'css' => 'plugins/WorldCupRabatt/templates/css/worldcup-rabatt.css',
            'js'  => 'plugins/WorldCupRabatt/templates/js/worldcup-rabatt.js',
        ];
    }

    private function resolveAssetAbsolutePath(string $relativePath): string
    {
        return \PFAD_ROOT . \ltrim($relativePath, '/');
    }

    private function getAssetFilemtimeVersion(string $relativePath): int
    {
        $absolutePath = $this->resolveAssetAbsolutePath($relativePath);

        return \is_file($absolutePath) ? (int)\filemtime($absolutePath) : 0;
    }

    private function appendAssetVersionQuery(string $relativePath): string
    {
        $mtime = $this->getAssetFilemtimeVersion($relativePath);
        if ($mtime <= 0) {
            return $relativePath;
        }

        return $relativePath . '?v=' . $mtime;
    }

    private function stripAssetVersionQuery(string $path): string
    {
        $queryPos = \strpos($path, '?');
        if ($queryPos === false) {
            return $path;
        }

        return \substr($path, 0, $queryPos);
    }

    private function pluginAssetPathsMatch(string $entry, string $baseRelativePath): bool
    {
        return $this->stripAssetVersionQuery($entry) === $baseRelativePath;
    }

    private function shouldRegisterAssetsForMinify(): bool
    {
        if ($this->isBackendRequest()) {
            return false;
        }

        return (int)($this->fetchSettings()['active'] ?? 0) === 1;
    }

    /**
     * @param array<string, array<int, array{idx: string, abs: string, rel: string, vsn?: string}>> $groups
     */
    private function registerAssetsInMinifyGroups(array &$groups): void
    {
        $paths = $this->getAssetRelativePaths();

        if (!isset($groups['plugin_css']) || !\is_array($groups['plugin_css'])) {
            $groups['plugin_css'] = [];
        }
        if (!isset($groups['plugin_js_head']) || !\is_array($groups['plugin_js_head'])) {
            $groups['plugin_js_head'] = [];
        }

        $cssAbs = $this->resolveAssetAbsolutePath($paths['css']);
        if (\is_file($cssAbs)) {
            $groups['plugin_css'][] = [
                'idx' => 'WorldCupRabatt-css',
                'abs' => $cssAbs,
                'rel' => $paths['css'],
                'vsn' => (string)\filemtime($cssAbs),
            ];
        }

        $jsAbs = $this->resolveAssetAbsolutePath($paths['js']);
        if (\is_file($jsAbs)) {
            $groups['plugin_js_head'][] = [
                'idx' => 'WorldCupRabatt-js',
                'abs' => $jsAbs,
                'rel' => $paths['js'],
                'vsn' => (string)\filemtime($jsAbs),
            ];
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyVersionedAssetsToMinifyArrays(array &$args): void
    {
        $paths = $this->getAssetRelativePaths();
        $map   = [
            'cPluginCss_arr'    => $paths['css'],
            'cPluginJsHead_arr' => $paths['js'],
        ];

        foreach ($map as $argKey => $relativePath) {
            if (!isset($args[$argKey]) || !\is_array($args[$argKey])) {
                $args[$argKey] = [];
            }
            $versioned = $this->appendAssetVersionQuery($relativePath);
            $found     = false;
            foreach ($args[$argKey] as $index => $entry) {
                if (!\is_string($entry)) {
                    continue;
                }
                if ($this->pluginAssetPathsMatch($entry, $relativePath)) {
                    $args[$argKey][$index] = $versioned;
                    $found                 = true;
                    break;
                }
            }
            if (!$found) {
                $args[$argKey][] = $versioned;
            }
        }
    }

    private function isHomepageRequest(): bool
    {
        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            return true;
        }
        $uri   = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path  = (string)(\parse_url($uri, \PHP_URL_PATH) ?? '');
        $query = (string)(\parse_url($uri, \PHP_URL_QUERY) ?? '');
        $path  = \rtrim($path, '/');
        $shopUrl      = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = \rtrim((string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? ''), '/');

        if ($query !== '' && \preg_match('/(?:^|&)a=\d+(?:&|$)/', $query) === 1) {
            return false;
        }

        return $path === $shopBasePath
            || $path === ($shopBasePath . '/index.php')
            || \str_starts_with($path, $shopBasePath . '/index.php');
    }

    /**
     * @param array{title:string,intro:string,buttonLabel:string,attempts:int,code5:string,code10:string,code15:string,autoOpen:int} $settings
     */
    private function renderIndexHtml(array $settings): string
    {
        $path = $this->getPlugin()->getPaths()->getBasePath() . 'Tasarim/index.html';
        if (!\is_file($path)) {
            return '';
        }
        $html = (string)\file_get_contents($path);
        if ($html === '') {
            return '';
        }

        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $preshot = $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-preshot.mp3';
        $kick     = $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-kick.wav';
        $goalSong = $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-goal-song.mp3';
        $logo     = $shopUrl . '/plugins/WorldCupRabatt/Tasarim/wcr-wc2026-logo.png';

        $map = [
            '{{TITLE}}'        => \htmlspecialchars((string)($settings['title'] ?? ''), \ENT_QUOTES, 'UTF-8'),
            '{{INTRO}}'        => \htmlspecialchars((string)($settings['intro'] ?? ''), \ENT_QUOTES, 'UTF-8'),
            '{{BUTTON_LABEL}}' => \htmlspecialchars((string)($settings['buttonLabel'] ?? ''), \ENT_QUOTES, 'UTF-8'),
            '{{AUTOOPEN}}'     => (int)($settings['autoOpen'] ?? 0) === 1 ? '1' : '0',
            '{{CODE5}}'        => \htmlspecialchars((string)($settings['code5'] ?? ''), \ENT_QUOTES, 'UTF-8'),
            '{{CODE10}}'       => \htmlspecialchars((string)($settings['code10'] ?? ''), \ENT_QUOTES, 'UTF-8'),
            '{{CODE15}}'       => \htmlspecialchars((string)($settings['code15'] ?? ''), \ENT_QUOTES, 'UTF-8'),
            '{{PRESHOT_SOUND}}' => \htmlspecialchars($preshot, \ENT_QUOTES, 'UTF-8'),
            '{{KICK_SOUND}}'     => \htmlspecialchars($kick, \ENT_QUOTES, 'UTF-8'),
            '{{GOAL_SONG}}'      => \htmlspecialchars($goalSong, \ENT_QUOTES, 'UTF-8'),
            '{{LOGO}}'           => \htmlspecialchars($logo, \ENT_QUOTES, 'UTF-8'),
        ];

        return \str_replace(\array_keys($map), \array_values($map), $html);
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }

    private function ensureSettingsTable(): void
    {
        if (self::$settingsEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_worldcup_rabatt_settings (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    nActive TINYINT(1) NOT NULL DEFAULT 1,
                    cTitle VARCHAR(190) NOT NULL DEFAULT "",
                    cIntro VARCHAR(512) NOT NULL DEFAULT "",
                    cButtonLabel VARCHAR(120) NOT NULL DEFAULT "",
                    nAttempts INT NOT NULL DEFAULT 1,
                    cCode5 VARCHAR(64) NOT NULL DEFAULT "",
                    cCode10 VARCHAR(64) NOT NULL DEFAULT "",
                    cCode15 VARCHAR(64) NOT NULL DEFAULT "",
                    nAutoOpen TINYINT(1) NOT NULL DEFAULT 0,
                    dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $row = $db->getSingleObject('SELECT kID FROM twomosi_worldcup_rabatt_settings LIMIT 1');
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nActive = 1;
                $ins->cTitle = 'Elfmeterschießen: Gewinne deinen Rabatt!';
                $ins->cIntro = 'Triff einen der Rabatt-Kreise im Tor und sichere dir deinen Gutscheincode.';
                $ins->cButtonLabel = 'Spiele um deinen Rabatt';
                $ins->nAttempts = 3;
                $ins->cCode5 = 'BRE5';
                $ins->cCode10 = 'TOR10';
                $ins->cCode15 = 'CUP15';
                $ins->nAutoOpen = 0;
                $ins->dUpdated = \date('Y-m-d H:i:s');
                $db->insert('twomosi_worldcup_rabatt_settings', $ins);
            }
            self::$settingsEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array{
     *   active:int,
     *   title:string,
     *   intro:string,
     *   buttonLabel:string,
     *   attempts:int,
     *   code5:string,
     *   code10:string,
     *   code15:string,
     *   autoOpen:int
     * }
     */
    public function fetchSettings(): array
    {
        $defaults = [
            'active'      => 1,
            'title'       => 'Elfmeterschießen: Gewinne deinen Rabatt!',
            'intro'       => 'Triff einen der Rabatt-Kreise im Tor und sichere dir deinen Gutscheincode.',
            'buttonLabel' => 'Spiele um deinen Rabatt',
            'attempts'    => 3,
            'code5'       => 'BRE5',
            'code10'      => 'TOR10',
            'code15'      => 'CUP15',
            'autoOpen'    => 0,
        ];
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_worldcup_rabatt_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }

            $attempts = (int)($row->nAttempts ?? 1);
            if ($attempts < 1) {
                $attempts = 1;
            }
            if ($attempts > 10) {
                $attempts = 10;
            }

            return [
                'active'      => (int)($row->nActive ?? 1),
                'title'       => (string)($row->cTitle ?? $defaults['title']),
                'intro'       => (string)($row->cIntro ?? $defaults['intro']),
                'buttonLabel' => (string)($row->cButtonLabel ?? $defaults['buttonLabel']),
                'attempts'    => $attempts,
                'code5'       => (string)($row->cCode5 ?? $defaults['code5']),
                'code10'      => (string)($row->cCode10 ?? $defaults['code10']),
                'code15'      => (string)($row->cCode15 ?? $defaults['code15']),
                'autoOpen'    => (int)($row->nAutoOpen ?? 0),
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
                $menu->cName = 'World Cup Rabatt';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort = 16;
                $menu->nConf = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
