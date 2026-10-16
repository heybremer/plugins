<?php

declare(strict_types=1);

namespace Plugin\MesseList;

require_once __DIR__ . '/src/MesseListService.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->maybeServePublicList();
        $this->ensureAdminMenuEntry();

        $dispatcher->hookInto(\HOOK_ROUTER_PRE_DISPATCH, function (): void {
            $this->maybeServePublicList();
        });
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
            if (!\is_array($rows) || $rows === []) {
                $menu             = new \stdClass();
                $menu->kPlugin    = $pluginID;
                $menu->cName      = 'Messe List';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort      = 88;
                $menu->nConf      = 0;
                $db->insert('tpluginadminmenu', $menu);
            } else {
                $keep = (int)($rows[0]->kPluginAdminMenu ?? 0);
                foreach ($rows as $i => $row) {
                    if ($i === 0 || $keep <= 0) {
                        continue;
                    }
                    $id = (int)($row->kPluginAdminMenu ?? 0);
                    if ($id > 0) {
                        $db->delete('tpluginadminmenu', 'kPluginAdminMenu', $id);
                    }
                }
            }
            self::$adminMenuEnsured = true;
        } catch (\Throwable $e) {
        }
    }

    /**
     * Öffentliche Messe-Liste: ?ml_list=1&ml_token=…
     */
    private function maybeServePublicList(): void
    {
        $req = MesseListService::requestParams();
        if ($req['ml_quote'] !== '' && $req['ml_quote'] !== '0') {
            $this->serveQuoteApi($req);
            return;
        }
        if ($req['ml_list'] === '' || $req['ml_list'] === '0') {
            return;
        }
        if ($req['token'] === '' || !MesseListService::tokenIsValid($req['token'])) {
            if (!\headers_sent()) {
                \http_response_code(403);
                \header('Content-Type: text/plain; charset=utf-8');
            }
            echo "403 Forbidden\nToken ungültig oder fehlend. Im Admin unter Messe List einen neuen Link kopieren.";
            exit;
        }

        MesseListService::ensureTrgManualProducts();
        $products = MesseListService::loadDsLagerProducts();
        $tabs = MesseListService::getTabs();
        $groups = MesseListService::getGroups();
        $assetBase = \rtrim((string)Shop::getURL(), '/') . '/plugins/MesseList/templates';
        $quoteUrl = \rtrim((string)Shop::getURL(), '/') . '/?' . \http_build_query([
            'ml_quote' => '1',
            'ml_token' => $req['token'],
        ]);
        require $this->getPlugin()->getPaths()->getBasePath() . 'templates/public_view.php';
        exit;
    }

    /**
     * @param array{ml_list:string,token:string,ml_quote:string} $req
     */
    private function serveQuoteApi(array $req): void
    {
        if ($req['token'] === '' || !MesseListService::tokenIsValid($req['token'])) {
            if (!\headers_sent()) {
                \header('Content-Type: application/json; charset=utf-8');
            }
            \http_response_code(403);
            echo \json_encode(['ok' => false, 'error' => 'Token ungültig.'], \JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($req['ml_quote'] === 'pdf') {
            $quote = MesseListService::getQuote((int)($_GET['id'] ?? 0));
            if ($quote === null) {
                \http_response_code(404);
                echo 'Angebot nicht gefunden.';
                exit;
            }
            $fileName = \preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$quote['nr']) ?: 'Angebot';
            if (!\headers_sent()) {
                \header('Content-Type: application/pdf');
                \header('Content-Disposition: attachment; filename="' . $fileName . '.pdf"');
                \header('Cache-Control: private, no-store');
            }
            echo MesseListService::renderQuotePdf($quote);
            exit;
        }
        if (!\headers_sent()) {
            \header('Content-Type: application/json; charset=utf-8');
            \header('Cache-Control: no-store');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            \http_response_code(405);
            echo \json_encode(['ok' => false, 'error' => 'Nur POST erlaubt.'], \JSON_UNESCAPED_UNICODE);
            exit;
        }
        $raw = (string)\file_get_contents('php://input');
        $input = \json_decode($raw, true);
        if (!\is_array($input)) {
            $input = $_POST;
        }
        $result = MesseListService::createAndSendQuote($input);
        if (!empty($result['ok']) && !empty($result['id'])) {
            $result['download_url'] = \rtrim((string)Shop::getURL(), '/') . '/?' . \http_build_query([
                'ml_quote' => 'pdf',
                'ml_token' => $req['token'],
                'id'       => (int)$result['id'],
            ]);
        }
        if (empty($result['ok'])) {
            \http_response_code(422);
        }
        echo \json_encode($result, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        exit;
    }
}
