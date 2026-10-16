<?php

declare(strict_types=1);

namespace Plugin\AirbagSafeFix;

use JTL\Events\Dispatcher;
use JTL\Language\LanguageHelper;
use JTL\Link\Admin\LinkAdmin;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use JTL\Router\Router;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrap extends Bootstrapper
{
    private static bool $tablesEnsured   = false;
    private static bool $pageEnsured     = false;
    private static bool $adminMenuEnsured = false;

    private const TABLE    = 'twomosi_airbag_safe_fix';
    private const SLUG     = 'airbag-safe-fix-form';
    private const TITLE    = 'Airbag Safe Fix – Sicherheitsregistrierung';
    private const TEMPLATE = 'airbag-safe-fix.tpl';
    private const DATEI    = 'airbag_safe_fix.php';
    private const IDENT    = 'womosi_airbag_safe_fix';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureTables();
        $this->ensureAdminMenuEntry();
        $this->ensurePluginPage((int)$this->getPlugin()->getID());

        // HOOK_SMARTY_OUTPUTFILTER: CSS/JS-URLs für das Template bereitstellen
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['smarty'])) {
                return;
            }
            if (!$this->isOurPage()) {
                return;
            }
            $shopUrl = Shop::getURL();
            /** @var JTLSmarty $smarty */
            $smarty = $args['smarty'];
            $smarty->assign('asfCssUrl', $shopUrl . '/plugins/AirbagSafeFix/templates/css/airbag-safe-fix.css');
            $smarty->assign('asfJsUrl',  $shopUrl . '/plugins/AirbagSafeFix/templates/js/airbag-safe-fix.js');
        });

        // HOOK_LETZTERINCLUDE_CSS_JS: CSS + JS laden
        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isOurPage()) {
                return;
            }
            foreach (['cPluginCss_arr', 'cPluginJsHead_arr'] as $key) {
                if (!isset($args[$key]) || !\is_array($args[$key])) {
                    $args[$key] = [];
                }
            }
            $css = 'plugins/AirbagSafeFix/templates/css/airbag-safe-fix.css';
            $js  = 'plugins/AirbagSafeFix/templates/js/airbag-safe-fix.js';
            if (!\in_array($css, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = $css;
            }
            if (!\in_array($js, $args['cPluginJsHead_arr'], true)) {
                $args['cPluginJsHead_arr'][] = $js;
            }
        });

        // POST-Route: Formular speichern (GET handled JTL via tlink)
        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (array $args): void {
            /** @var Router $router */
            $router = $args['router'];
            $router->addRoute(
                '/' . self::SLUG,
                function (ServerRequestInterface $request, array $routeArgs, JTLSmarty $smarty): ResponseInterface {
                    return $this->handlePost($request);
                },
                'asf-post',
                ['POST']
            );
        });
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
            $ct = $request->getHeaderLine('Content-Type');
            if (\str_contains($ct, 'application/json')) {
                $body = \json_decode((string)$request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
            } else {
                $body = $request->getParsedBody();
            }
            if (!\is_array($body)) {
                $body = [];
            }
            $errors = $this->validate($body);
            if ($errors !== []) {
                return new JsonResponse(['success' => false, 'errors' => $errors], 422, [], $flags);
            }
            $this->saveRegistration($body);
            return new JsonResponse(['success' => true], 200, [], $flags);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'errors' => ['Interner Fehler.']], 500, [], $flags);
        }
    }

    /** @param array<string,mixed> $d */
    private function validate(array $d): array
    {
        $errors = [];
        if (\trim((string)($d['vorname'] ?? '')) === '') {
            $errors[] = 'Bitte Vornamen angeben.';
        }
        if (\trim((string)($d['nachname'] ?? '')) === '') {
            $errors[] = 'Bitte Nachnamen angeben.';
        }
        $email = \trim((string)($d['email'] ?? ''));
        if ($email === '' || !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (\trim((string)($d['produktName'] ?? '')) === '') {
            $errors[] = 'Bitte Produktname/Modell angeben.';
        }
        if (empty($d['datenschutz'])) {
            $errors[] = 'Bitte die Datenschutzerklärung akzeptieren.';
        }
        return $errors;
    }

    /** @param array<string,mixed> $d */
    private function saveRegistration(array $d): void
    {
        $db  = Shop::Container()->getDB();
        $row = new \stdClass();
        $row->cVorname        = \substr(\trim((string)($d['vorname'] ?? '')), 0, 100);
        $row->cNachname       = \substr(\trim((string)($d['nachname'] ?? '')), 0, 100);
        $row->cEmail          = \substr(\trim((string)($d['email'] ?? '')), 0, 200);
        $row->cTelefon        = \substr(\trim((string)($d['telefon'] ?? '')), 0, 50);
        $row->cStrasse        = \substr(\trim((string)($d['strasse'] ?? '')), 0, 200);
        $row->cPlz            = \substr(\trim((string)($d['plz'] ?? '')), 0, 20);
        $row->cStadt          = \substr(\trim((string)($d['stadt'] ?? '')), 0, 100);
        $row->cLand           = \substr(\trim((string)($d['land'] ?? '')), 0, 100);
        $row->cProduktName    = \substr(\trim((string)($d['produktName'] ?? '')), 0, 200);
        $row->cVariante       = \substr(\trim((string)($d['variante'] ?? '')), 0, 50);
        $row->cFahrzeugMarke  = \substr(\trim((string)($d['fahrzeugMarke'] ?? '')), 0, 100);
        $row->cFahrzeugModell = \substr(\trim((string)($d['fahrzeugModell'] ?? '')), 0, 100);
        $row->cPartiNr        = \substr(\trim((string)($d['partiNr'] ?? '')), 0, 100);
        $row->cAirbagDikis1   = \substr(\trim((string)($d['airbagDikis1'] ?? '')), 0, 100);
        $row->cAirbagDikis2   = \substr(\trim((string)($d['airbagDikis2'] ?? '')), 0, 100);
        $row->cAirbagDikis3   = \substr(\trim((string)($d['airbagDikis3'] ?? '')), 0, 100);
        $row->cAirbagDikis4   = \substr(\trim((string)($d['airbagDikis4'] ?? '')), 0, 100);
        $row->cProductionTime = \substr(\trim((string)($d['productionTime'] ?? '')), 0, 50);
        $row->cPlatform       = \substr(\trim((string)($d['platform'] ?? '')), 0, 100);
        $row->cFaturaLink     = \substr(\trim((string)($d['faturaLink'] ?? '')), 0, 500);
        $row->nDatenschutz    = empty($d['datenschutz']) ? 0 : 1;
        $row->dErstellt       = \date('Y-m-d H:i:s');
        $db->insert(self::TABLE, $row);
    }

    private function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    kID              INT AUTO_INCREMENT PRIMARY KEY,
                    cVorname         VARCHAR(100)  NOT NULL DEFAULT "",
                    cNachname        VARCHAR(100)  NOT NULL DEFAULT "",
                    cEmail           VARCHAR(200)  NOT NULL DEFAULT "",
                    cTelefon         VARCHAR(50)   NOT NULL DEFAULT "",
                    cStrasse         VARCHAR(200)  NOT NULL DEFAULT "",
                    cPlz             VARCHAR(20)   NOT NULL DEFAULT "",
                    cStadt           VARCHAR(100)  NOT NULL DEFAULT "",
                    cLand            VARCHAR(100)  NOT NULL DEFAULT "",
                    cProduktName     VARCHAR(200)  NOT NULL DEFAULT "",
                    cVariante        VARCHAR(50)   NOT NULL DEFAULT "",
                    cFahrzeugMarke   VARCHAR(100)  NOT NULL DEFAULT "",
                    cFahrzeugModell  VARCHAR(100)  NOT NULL DEFAULT "",
                    cPartiNr         VARCHAR(100)  NOT NULL DEFAULT "",
                    cAirbagDikis1    VARCHAR(100)  NOT NULL DEFAULT "",
                    cAirbagDikis2    VARCHAR(100)  NOT NULL DEFAULT "",
                    cAirbagDikis3    VARCHAR(100)  NOT NULL DEFAULT "",
                    cAirbagDikis4    VARCHAR(100)  NOT NULL DEFAULT "",
                    cProductionTime  VARCHAR(50)   NOT NULL DEFAULT "",
                    cPlatform        VARCHAR(100)  NOT NULL DEFAULT "",
                    cFaturaLink      VARCHAR(500)  NOT NULL DEFAULT "",
                    nDatenschutz     TINYINT(1)    NOT NULL DEFAULT 0,
                    dErstellt        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_email (cEmail),
                    INDEX idx_erstellt (dErstellt)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
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
            $db = Shop::Container()->getDB();

            // Existiert der Link schon?
            $existing = $db->getSingleObject(
                'SELECT tlink.kLink
                   FROM tlink
                   LEFT JOIN tpluginlinkdatei pld ON pld.kLink = tlink.kLink
                  WHERE tlink.kPlugin = :pid
                    AND tlink.nLinkart = :lt
                  LIMIT 1',
                ['pid' => $pluginID, 'lt' => \LINKTYP_PLUGIN]
            );
            $kLink = $existing !== null ? (int)($existing->kLink ?? 0) : 0;

            // SEO-Eintrag prüfen
            if ($kLink <= 0) {
                $seo = $db->getSingleObject(
                    "SELECT kKey FROM tseo WHERE cKey = 'kLink' AND cSeo = :seo ORDER BY kSprache ASC LIMIT 1",
                    ['seo' => self::SLUG]
                );
                $kLink = $seo !== null ? (int)($seo->kKey ?? 0) : 0;
            }

            // Link-Gruppe "hidden"
            $hiddenLg = $db->select('tlinkgruppe', 'cTemplatename', 'hidden');
            $lgID = (int)($hiddenLg->kLinkgruppe ?? 1);
            if ($lgID <= 0) {
                $lgID = 1;
            }

            if ($kLink <= 0) {
                $linkAdmin = new LinkAdmin($db, Shop::Container()->getCache());
                $data = [
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

            // tpluginlinkdatei sicherstellen
            $pld = $db->getSingleObject(
                'SELECT kLink FROM tpluginlinkdatei WHERE kLink = :k LIMIT 1',
                ['k' => $kLink]
            );
            if ($pld === null) {
                $row                    = new \stdClass();
                $row->kPlugin           = $pluginID;
                $row->kLink             = $kLink;
                $row->cDatei            = self::DATEI;
                $row->cTemplate         = self::TEMPLATE;
                $row->cFullscreenTemplate = '_DBNULL_';
                $db->insert('tpluginlinkdatei', $row);
            }
        } catch (\Throwable $e) {
            // ignore
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
                $menu             = new \stdClass();
                $menu->kPlugin    = $pluginID;
                $menu->cName      = 'Airbag Safe Fix';
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

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }
}
