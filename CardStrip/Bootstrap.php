<?php
declare(strict_types=1);

namespace Plugin\CardStrip;

require_once __DIR__ . '/src/CardRepository.php';
require_once __DIR__ . '/src/ImageThumb.php';

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private const CSS = 'plugins/CardStrip/templates/css/cardstrip.v2.css';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isStripPage()) {
                return;
            }
            if (!isset($args['cPluginCss_arr']) || !\is_array($args['cPluginCss_arr'])) {
                $args['cPluginCss_arr'] = [];
            }
            if (!\in_array(self::CSS, $args['cPluginCss_arr'], true)) {
                $args['cPluginCss_arr'][] = self::CSS;
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!$this->isStripPage() || !isset($args['smarty'], $args['document'])) {
                return;
            }
            $doc = $args['document'];
            // Der Filter laeuft auch fuer Teil-Templates - nur Hauptdokument.
            if ($doc->find('#cs-strip')->length > 0 || $doc->find('div#content, main')->length === 0) {
                return;
            }
            $cards = $this->buildCards(CardRepository::active());
            if ($cards === []) {
                return;
            }
            $base = $this->getPlugin()->getPaths()->getBasePath() . 'templates/';
            if (!\is_file($base . 'cardstrip.tpl')) {
                return;
            }
            $smarty = $args['smarty'];
            $smarty->assign('csCards', $cards);
            $smarty->assign('csTitle', $this->cfg('cs_section_title'));
            $smarty->assign('csCardTpl', $base . 'adcard.tpl');
            try {
                $html = $smarty->fetch('file:' . $base . 'cardstrip.tpl');
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '') {
                return;
            }
            $this->inject($doc, $html);
        });
    }

    private function isStripPage(): bool
    {
        return Shop::getPageType() === \PAGE_STARTSEITE && $this->cfg('cs_active', 'Y') === 'Y';
    }

    private function cfg(string $key, string $default = ''): string
    {
        try {
            $v = $this->getPlugin()->getConfig()->getValue($key);

            return ($v === null || $v === '') ? $default : (string)$v;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * @param array<int,object> $rows
     * @return array<int,array<string,mixed>>
     */
    private function buildCards(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $variant = \in_array((string)$r->cVariant, ['A', 'B', 'C'], true) ? (string)$r->cVariant : 'A';
            $items   = [];
            if ($variant === 'C') {
                $dec = \json_decode((string)($r->cItems ?? ''), true);
                foreach (\is_array($dec) ? $dec : [] as $it) {
                    if (!\is_array($it) || \trim((string)($it['image'] ?? '')) === '') {
                        continue;
                    }
                    $items[] = [
                        'image' => ImageThumb::url(\trim((string)$it['image']), 400),
                        'badge' => \trim((string)($it['badge'] ?? '')),
                        'alt'   => \trim((string)($it['alt'] ?? '')),
                    ];
                }
                $items = \array_slice($items, 0, 4);
            }
            $out[] = [
                'variant'  => $variant,
                'title'    => \trim((string)$r->cTitle),
                'subtitle' => \trim((string)$r->cSubtitle),
                'bgColor'  => (string)\preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$r->cBgColor) ? (string)$r->cBgColor : '#197149',
                'tone'     => (string)$r->cTextTone === 'dark' ? 'dark' : 'light',
                'image'    => ImageThumb::url(\trim((string)$r->cImage), 600),
                'badge'    => \trim((string)$r->cBadge),
                'href'     => \trim((string)$r->cUrl) !== '' ? \trim((string)$r->cUrl) : '#',
                'label'    => self::linkLabel((string)$r->cTitle, (string)$r->cUrl),
                'items'    => $items,
            ];
        }

        return $out;
    }

    private static function linkLabel(string $title, string $url): string
    {
        $title = \trim($title);
        if ($title !== '') {
            return $title;
        }
        $slug = \basename((string)\parse_url(\trim($url), \PHP_URL_PATH));
        $slug = (string)\preg_replace('/_\d+$/', '', \rawurldecode($slug));

        return \trim(\str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * @param object $doc pQuery-Dokument
     */
    private function inject($doc, string $html): void
    {
        $custom = \trim($this->cfg('cs_selector'));
        if ($custom !== '') {
            $t = $doc->find($custom)->eq(0);
            if ($t->length > 0) {
                $t->after($html);

                return;
            }
        }
        $t = $doc->find('.index-filter-konfigurator')->eq(0);
        if ($t->length > 0) {
            $t->after($html);

            return;
        }
        $c = $doc->find('div#content')->eq(0);
        if ($c->length === 0) {
            $c = $doc->find('main#main-wrapper, main')->eq(0);
        }
        if ($c->length > 0) {
            $c->prepend($html);
        }
    }
}
