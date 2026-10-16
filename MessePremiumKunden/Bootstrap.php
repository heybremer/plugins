<?php

declare(strict_types=1);

namespace Plugin\MessePremiumKunden;

require_once __DIR__ . '/src/MpkService.php';

use JTL\Events\Dispatcher;
use JTL\Helpers\Form;
use JTL\Language\LanguageHelper;
use JTL\Link\Admin\LinkAdmin;
use JTL\Plugin\Bootstrapper;
use JTL\Router\Router;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    private static bool $tablesEnsured    = false;
    private static bool $pageEnsured      = false;
    private static bool $adminMenuEnsured = false;

    private const SLUG     = 'messe-premium-kunden';
    private const TITLE    = 'Messe Premium Kunden';
    private const TEMPLATE = 'messe-premium-kunden.tpl';
    private const DATEI    = 'messe_premium_kunden.php';
    private const IDENT    = 'womosi_messe_premium_kunden';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureTables();
        $this->ensureAdminMenuEntry();
        $this->ensurePluginPage((int)$this->getPlugin()->getID());

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty']) || !$this->isOurPage()) {
                return;
            }
            $this->assignFrontendVars($args['smarty']);
        });

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isOurPage()) {
                return;
            }
            foreach (['cPluginCss_arr', 'cPluginJsHead_arr'] as $key) {
                if (!isset($args[$key]) || !\is_array($args[$key])) {
                    $args[$key] = [];
                }
            }
            $css = 'plugins/MessePremiumKunden/templates/css/messe-premium-kunden.css';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
        });

        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
            /** @var Router $router */
            $router = $args['router'];
            $router->addRoute(
                '/' . self::SLUG,
                function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty): ResponseInterface {
                    return $this->handlePost($request);
                },
                'mpk-post',
                ['POST']
            );
            $router->addRoute(
                '/' . self::SLUG . '-modelle',
                function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty): ResponseInterface {
                    return $this->handleModelle($request);
                },
                'mpk-modelle',
                ['GET']
            );
            $router->addRoute(
                '/' . self::SLUG . '-foto',
                function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty): ResponseInterface {
                    return $this->handleFoto($request);
                },
                'mpk-foto',
                ['GET']
            );
        });
    }

    public function assignFrontendVars(JTLSmarty $smarty): void
    {
        $svc     = $this->service();
        $shopUrl = Shop::getURL();
        $set     = $svc->fetchSettings();
        $smarty->assign('mpkCssUrl', $shopUrl . '/plugins/MessePremiumKunden/templates/css/messe-premium-kunden.css');
        $smarty->assign('mpkJsUrl', $shopUrl . '/plugins/MessePremiumKunden/templates/js/messe-premium-kunden.js');
        $smarty->assign('mpkRabatt', $set['rabatt']);
        $smarty->assign('mpkGueltigTage', $set['gueltigTage']);
        $smarty->assign('mpkMarken', $svc->loadMarken());
        $smarty->assign('mpkMaxFotos', MpkService::MAX_FOTOS);
        $smarty->assign('mpkMaxFotoKb', $set['maxFotoKb']);
        $smarty->assign('mpkFormTs', \time());
        $smarty->assign('mpkFormAction', '/' . self::SLUG);
        $smarty->assign('mpkModelleUrl', '/' . self::SLUG . '-modelle');
        $smarty->assign('mpkActive', (int)$set['active']);
        if (\method_exists(Form::class, 'getTokenInput')) {
            $smarty->assign('mpkCsrf', Form::getTokenInput());
        } else {
            $smarty->assign('mpkCsrf', '');
        }
    }

    private function isOurPage(): bool
    {
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = \parse_url($uri, \PHP_URL_PATH) ?? '';
        return \strpos((string)$path, self::SLUG) !== false;
    }

    private function handlePost(ServerRequestInterface $request): ResponseInterface
    {
        $flags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        try {
            $svc = $this->service();
            $set = $svc->fetchSettings();
            if ((int)$set['active'] !== 1) {
                return new JsonResponse(['success' => false, 'errors' => ['Das Formular ist derzeit deaktiviert.']], 403, [], $flags);
            }

            $body = $request->getParsedBody();
            if (!\is_array($body) || $body === []) {
                $body = $_POST;
            }
            if (!$this->csrfOk($body)) {
                return new JsonResponse(['success' => false, 'errors' => ['Ungültiges Sicherheits-Token. Bitte Seite neu laden.']], 403, [], $flags);
            }
            if ($svc->isHoneypotTriggered($body)) {
                return new JsonResponse(['success' => true, 'code' => '', 'fake' => 1], 200, [], $flags);
            }
            if ($svc->isTooFast($body)) {
                return new JsonResponse(['success' => false, 'errors' => ['Bitte das Formular etwas langsamer absenden.']], 422, [], $flags);
            }

            $ip = $this->clientIp();
            if ($svc->isRateLimited($ip)) {
                return new JsonResponse(['success' => false, 'errors' => ['Zu viele Anfragen. Bitte später erneut versuchen.']], 429, [], $flags);
            }

            $fotos  = $svc->collectFotosFromRequest();
            $errors = $svc->validate($body, $fotos);
            if ($errors !== []) {
                return new JsonResponse(['success' => false, 'errors' => $errors], 422, [], $flags);
            }

            $saved = $svc->saveRegistration($body, $fotos, $ip);
            return new JsonResponse([
                'success' => true,
                'emailOk' => $saved['emailOk'],
            ], 200, [], $flags);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'errors' => ['Interner Fehler. Bitte später erneut versuchen.']], 500, [], $flags);
        }
    }

    private function handleModelle(ServerRequestInterface $request): ResponseInterface
    {
        $flags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        $q     = $request->getQueryParams();
        $marke = \trim((string)($q['marke'] ?? ''));
        $list  = $this->service()->loadModelle($marke);
        return new JsonResponse(['success' => true, 'modelle' => $list], 200, [], $flags);
    }

    private function handleFoto(ServerRequestInterface $request): ResponseInterface
    {
        $q    = $request->getQueryParams();
        $id   = (int)($q['id'] ?? 0);
        $name = \basename((string)($q['f'] ?? ''));
        $foto = null;
        if ($name !== '' && !\str_contains($name, '..')) {
            $foto = $this->service()->readFotoByName($name);
        } elseif ($id > 0 && $this->isBackendUser()) {
            $foto = $this->service()->readFoto($id);
        }
        if ($foto === null) {
            return new JsonResponse(['error' => 'not found'], 404);
        }
        $contents = @\file_get_contents($foto['path']);
        if ($contents === false) {
            return new JsonResponse(['error' => 'not found'], 404);
        }
        $response = new Response('php://memory', 200, [
            'Content-Type'           => $foto['mime'],
            'Content-Disposition'    => 'inline; filename="' . \basename((string)$foto['name']) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'private, max-age=3600',
        ]);
        $response->getBody()->write($contents);
        return $response;
    }

    private function isBackendUser(): bool
    {
        return isset($_SESSION['AdminAccount']) || isset($_SESSION['kAdminlog']);
    }

    private function service(): MpkService
    {
        $dir = $this->getPlugin()->getPaths()->getBasePath() . 'uploads';
        $svc = new MpkService($dir);
        $svc->ensureTables();
        return $svc;
    }

    /** @param array<string,mixed> $body */
    private function csrfOk(array $body): bool
    {
        if (\class_exists(Form::class) && \method_exists(Form::class, 'validateToken') && Form::validateToken()) {
            return true;
        }
        $posted = (string)($body['jtl_token'] ?? $_POST['jtl_token'] ?? '');
        $sess   = (string)($_SESSION['jtl_token'] ?? '');
        return $posted !== '' && $sess !== '' && \hash_equals($sess, $posted);
    }

    private function clientIp(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return \filter_var($ip, \FILTER_VALIDATE_IP) ? $ip : '';
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        try {
            $this->service()->ensureTables();
            self::$tablesEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensurePluginPage(int $pluginID): void
    {
        if (self::$pageEnsured) {
            return;
        }
        self::$pageEnsured = true;
        if ($pluginID <= 0) {
            return;
        }
        try {
            $db    = Shop::Container()->getDB();
            $kLink = $this->findExistingPluginLink($db, $pluginID);
            $this->deleteExtraPluginLinks($db, $pluginID, $kLink);

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
                    'cName'              => self::TITLE,
                    'nLinkart'           => \LINKTYP_PLUGIN,
                    'nSort'              => 0,
                    'bSSL'               => 0,
                    'bIsActive'          => 1,
                    'bIsFluid'           => 0,
                    'cSichtbarNachLogin' => 'N',
                    'cNoFollow'          => 'N',
                    'cIdentifier'        => self::IDENT,
                    'linkTarget'         => '_self',
                    'cKundengruppen'     => '_DBNULL_',
                    'kLinkgruppe'        => $lgID,
                ];
                foreach (LanguageHelper::getAllLanguages(0, true) as $language) {
                    $iso = $language->getIso();
                    $data['cName_' . $iso]    = self::TITLE;
                    $data['cTitle_' . $iso]   = self::TITLE;
                    $data['cSeo_' . $iso]     = self::SLUG;
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

    private function findExistingPluginLink($db, int $pluginID): int
    {
        $seo = $db->getSingleObject(
            "SELECT kKey FROM tseo WHERE cKey = 'kLink' AND cSeo = :seo ORDER BY kSprache ASC LIMIT 1",
            ['seo' => self::SLUG]
        );
        $fromSeo = $seo !== null ? (int)($seo->kKey ?? 0) : 0;
        if ($fromSeo > 0) {
            return $fromSeo;
        }

        $rows = $db->getObjects(
            'SELECT kLink FROM tlink WHERE kPlugin = :pid ORDER BY kLink ASC',
            ['pid' => $pluginID]
        );
        foreach ($rows as $r) {
            $id = (int)($r->kLink ?? 0);
            if ($id > 0) {
                return $id;
            }
        }
        return 0;
    }

    private function deleteExtraPluginLinks($db, int $pluginID, int $keep): void
    {
        $rows = $db->getObjects(
            'SELECT kLink FROM tlink WHERE kPlugin = :pid',
            ['pid' => $pluginID]
        );
        foreach ($rows as $r) {
            $id = (int)($r->kLink ?? 0);
            if ($id <= 0 || ($keep > 0 && $id === $keep)) {
                continue;
            }
            try {
                $db->executeQueryPrepared(
                    'UPDATE tlink SET bIsActive = 0 WHERE kLink = :k',
                    ['k' => $id]
                );
                $db->delete('tpluginlinkdatei', 'kLink', $id);
                $db->executeQueryPrepared(
                    "DELETE FROM tseo WHERE cKey = 'kLink' AND kKey = :k",
                    ['k' => $id]
                );
                $db->delete('tlinksprache', 'kLink', $id);
                try {
                    $db->delete('tlinkgroupassociations', 'kLink', $id);
                } catch (\Throwable $e) {
                    // older shops
                }
                $db->delete('tlink', 'kLink', $id);
            } catch (\Throwable $e) {
                // ignore single extra
            }
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
            $rows = $db->getObjects(
                'SELECT kPluginAdminMenu FROM tpluginadminmenu
                  WHERE kPlugin = :kPlugin
                  ORDER BY kPluginAdminMenu ASC',
                ['kPlugin' => $pluginID]
            );
            if ($rows === []) {
                $menu             = new \stdClass();
                $menu->kPlugin    = $pluginID;
                $menu->cName      = 'Übersicht';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort      = 10;
                $menu->nConf      = 0;
                $db->insert('tpluginadminmenu', $menu);
            } else {
                $keep = (int)($rows[0]->kPluginAdminMenu ?? 0);
                foreach ($rows as $i => $r) {
                    $id = (int)($r->kPluginAdminMenu ?? 0);
                    if ($i === 0 || $id <= 0 || $id === $keep) {
                        continue;
                    }
                    $db->delete('tpluginadminmenu', 'kPluginAdminMenu', $id);
                }
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
