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

        $settings = $this->fetchSettings();
        if ((int)($settings['active'] ?? 1) !== 1) {
            return new EmptyResponse();
        }

        $events = $this->fetchActiveEvents();
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

        $this->smarty->assign('mkHeadline', (string)($settings['headline'] ?? 'Messe Kalender 2026'));
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

    /**
     * @return array{active:int,slug:string,headline:string,subtitle:string}
     */
    private function fetchSettings(): array
    {
        $defaults = [
            'active' => 1,
            'slug' => 'messe-kalender',
            'headline' => 'Messe Kalender 2026',
            'subtitle' => 'Die Messen, an denen wir als Bremer-Sitzbezüge GmbH teilnehmen werden',
        ];
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT * FROM twomosi_messe_settings ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                return $defaults;
            }
            $slug = \trim((string)($row->cPageSlug ?? 'messe-kalender'));
            if ($slug === '') {
                $slug = $defaults['slug'];
            }
            return [
                'active' => (int)($row->nActive ?? 1),
                'slug' => $slug,
                'headline' => (string)($row->cHeadline ?? $defaults['headline']),
                'subtitle' => (string)($row->cSubtitle ?? $defaults['subtitle']),
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    /**
     * @return array<int, array{month:string,title:string,location:string,date:string,stand:string,website:string,directions:string}>
     */
    private function fetchActiveEvents(): array
    {
        try {
            $db = Shop::Container()->getDB();
            $rows = $db->getObjects(
                'SELECT cMonth, cTitle, cLocation, cDateText, cStand, cWebsiteUrl, cDirectionsUrl
                   FROM twomosi_messe_event
                  WHERE nActive = 1
               ORDER BY nSort ASC, kID ASC'
            );
            if (!\is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'month' => (string)($r->cMonth ?? ''),
                    'title' => (string)($r->cTitle ?? ''),
                    'location' => (string)($r->cLocation ?? ''),
                    'date' => (string)($r->cDateText ?? ''),
                    'stand' => (string)($r->cStand ?? ''),
                    'website' => (string)($r->cWebsiteUrl ?? ''),
                    'directions' => (string)($r->cDirectionsUrl ?? ''),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}

