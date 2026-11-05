<?php
declare(strict_types=1);

namespace Plugin\ProductLaunch;

require_once __DIR__ . '/src/LaunchRepository.php';

use JTL\Catalog\Product\Artikel;
use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Session\Frontend;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private const CSS = 'plugins/ProductLaunch/templates/css/productlaunch.v1.css';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if (!$this->isLaunchPage()) {
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
            if (!$this->isLaunchPage() || !isset($args['smarty'], $args['document'])) {
                return;
            }
            $doc = $args['document'];
            // Der Filter laeuft auch fuer Teil-Templates - nur Hauptdokument.
            if ($doc->find('#pl-launch')->length > 0 || $doc->find('div#content, main')->length === 0) {
                return;
            }
            $rows = LaunchRepository::active(\max(1, \min(10, (int)$this->cfg('pl_max_items', '5'))));
            if ($rows === []) {
                return;
            }
            $items = $this->buildItems($rows);
            if ($items === []) {
                return;
            }
            $tpl = $this->getPlugin()->getPaths()->getBasePath() . 'templates/productlaunch.tpl';
            if (!\is_file($tpl)) {
                return;
            }
            $smarty = $args['smarty'];
            $smarty->assign('plItems', $items);
            $smarty->assign('plSectionTitle', $this->cfg('pl_section_title', 'Neu eingetroffen'));
            try {
                $html = $smarty->fetch('file:' . $tpl);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '') {
                return;
            }
            $this->inject($doc, $html);
        });
    }

    private function isLaunchPage(): bool
    {
        return Shop::getPageType() === \PAGE_STARTSEITE && $this->cfg('pl_active', 'Y') === 'Y';
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

    private static function plain(string $html, int $max = 0): string
    {
        $t = \html_entity_decode(\strip_tags(\str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', $html)), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $t = \trim((string)\preg_replace('/\s+/u', ' ', $t));
        if ($max > 0 && \mb_strlen($t) > $max) {
            $cut = \mb_substr($t, 0, $max);
            $sp  = \mb_strrpos($cut, ' ');
            $t   = \rtrim(\mb_substr($cut, 0, $sp !== false && $sp > $max * 0.6 ? $sp : $max), " ,.;:-") . ' …';
        }

        return $t;
    }

    /**
     * @param array<int,object> $rows
     * @return array<int,array<string,string>>
     */
    private function buildItems(array $rows): array
    {
        try {
            $net = Frontend::getCustomerGroup()->isMerchant() ? 1 : 0;
        } catch (\Throwable $e) {
            $net = 0;
        }
        $shopUrl = \rtrim((string)Shop::getURL(), '/');
        $langId  = (int)Shop::getLanguageID();
        $items   = [];
        foreach ($rows as $r) {
            try {
                $img   = \trim((string)($r->cImage ?? ''));
                $price = '';
                $name  = '';
                $url   = \trim((string)($r->cUrl ?? ''));
                $text  = \trim((string)($r->cText ?? ''));
                if ((int)$r->kArtikel > 0) {
                    $a = new Artikel();
                    $a->fuelleArtikel((int)$r->kArtikel, Artikel::getDefaultOptions());
                    if ((int)($a->kArtikel ?? 0) > 0) {
                        if ($img === '' && !empty($a->Bilder[0])) {
                            $b   = $a->Bilder[0];
                            $img = (string)($b->cURLGross ?? $b->cURLNormal ?? $b->cURLKlein ?? '');
                        }
                        if (isset($a->Preise->cVKLocalized[$net])) {
                            $price = \html_entity_decode((string)$a->Preise->cVKLocalized[$net], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                        }
                        $name = self::plain((string)$a->cName);
                        if ($url === '') {
                            $url = (string)($a->cURLFull ?? ($shopUrl . '/' . ($a->cURL ?? '')));
                        }
                        if ($text === '') {
                            $text = self::plain((string)($a->cKurzBeschreibung ?? ''), 220);
                            if ($text === '') {
                                $text = self::plain((string)($a->cBeschreibung ?? ''), 220);
                            }
                        }
                    } elseif (\trim((string)$r->cTitle) === '') {
                        continue;
                    }
                }
                $cat = LaunchRepository::categoryLink((int)$r->kKategorie, $langId, $shopUrl);
                if ($url === '') {
                    $url = $cat['url'];
                }
                $title = \trim((string)$r->cTitle) !== '' ? \trim((string)$r->cTitle) : $name;
                if ($title === '') {
                    continue;
                }
                $items[] = [
                    'name'    => $name !== '' ? $name : $title,
                    'title'   => $title,
                    'text'    => $text,
                    'badge'   => \trim((string)$r->cBadge) !== '' ? \trim((string)$r->cBadge) : 'NEU',
                    'button'  => \trim((string)$r->cButton) !== '' ? \trim((string)$r->cButton) : 'Jetzt entdecken',
                    'url'     => $url,
                    'image'   => $img,
                    'price'   => $price,
                    'catName' => $cat['name'],
                    'catUrl'  => $cat['url'],
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $items;
    }

    /**
     * @param object $doc pQuery-Dokument
     */
    private function inject($doc, string $html): void
    {
        $custom = \trim($this->cfg('pl_selector'));
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
