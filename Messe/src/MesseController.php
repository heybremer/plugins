<?php

declare(strict_types=1);

namespace Plugin\Messe\src;

use JTL\Link\Link;
use JTL\Plugin\PluginInterface;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MesseController
{
    public function __construct(
        private PluginInterface $plugin,
        private JTLSmarty $smarty
    ) {
    }

    public function page(ServerRequestInterface $request): ResponseInterface
    {
        MesseDatabase::ensureAll();

        $settings = MesseDatabase::fetchSettings();
        if ((int)($settings['active'] ?? 1) !== 1) {
            return new EmptyResponse();
        }

        $events = MesseDatabase::fetchActiveEvents();
        $payload = [
            'events' => $events,
        ];
        $json = \json_encode(
            $payload,
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
            | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT
        );
        if (!\is_string($json)) {
            $json = '{}';
        }

        $tplPath = $this->plugin->getPaths()->getBasePath() . 'templates/messe_kalender.tpl';
        if (!\is_file($tplPath)) {
            return new EmptyResponse();
        }

        $this->smarty->assign('mkHeadline', (string)($settings['headline'] ?? 'Messe Kalender'));
        $this->smarty->assign('mkSubtitle', (string)($settings['subtitle'] ?? ''));
        $this->smarty->assign('mkDataJson', $json);

        // Render inside theme layout (header/footer)
        $langID = (int)Shop::getLanguageID();
        $slug = (string)($settings['slug'] ?? 'messe-kalender');
        $headline = (string)($settings['headline'] ?? 'Messe Kalender');
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $shopBasePath = (string)(\parse_url($shopUrl, \PHP_URL_PATH) ?? '');
        $shopBasePath = \rtrim($shopBasePath, '/');

        $link = new Link(Shop::Container()->getDB());
        $link->setLinkType(\LINKTYP_PLUGIN);
        $link->setTitle($headline, $langID);
        $link->setName($headline, $langID);
        $link->setSEO($slug, $langID);
        $link->setURL($shopBasePath . '/' . ltrim($slug, '/'), $langID);
        $link->setIsFluid(true);
        $this->smarty->assign('Link', $link);
        $this->smarty->assign('cPluginTemplate', 'file:' . $tplPath);

        try {
            return $this->smarty->getResponse('layout/index.tpl')
                ->withHeader('Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            return new EmptyResponse();
        }
    }
}

