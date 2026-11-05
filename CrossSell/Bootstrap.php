<?php
declare(strict_types=1);

namespace Plugin\CrossSell;

require_once __DIR__ . '/src/CrossSellRepository.php';

use JTL\Catalog\Product\Artikel;
use JTL\Events\Dispatcher;
use JTL\Helpers\Form;
use JTL\Plugin\Bootstrapper;
use JTL\Session\Frontend;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private const CSS = 'plugins/CrossSell/templates/css/crosssell.v4.css';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            if ($this->context() === '') {
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
            if (!isset($args['smarty'], $args['document'])) {
                return;
            }
            $ctx = $this->context();
            if ($ctx === '') {
                return;
            }
            $doc = $args['document'];
            // Der Filter laeuft auch fuer Teil-Templates (Produktboxen etc.) - nur Hauptdokument.
            if ($doc->find('#cs-recs')->length > 0 || $doc->find('div#content, main')->length === 0) {
                return;
            }
            $smarty = $args['smarty'];

            $sourceIds  = [];
            $excludeIds = [];
            if ($ctx === 'product') {
                $main = (int)Shop::$kArtikel;
                if ($main <= 0) {
                    return;
                }
                $sourceIds = [$main, (int)Shop::$kVariKindArtikel];
                try {
                    $row = Shop::Container()->getDB()->getSingleObject(
                        'SELECT kVaterArtikel FROM tartikel WHERE kArtikel = :a',
                        ['a' => $main]
                    );
                    if ($row !== null) {
                        $sourceIds[] = (int)$row->kVaterArtikel;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                $excludeIds = $sourceIds;
            } else {
                foreach ($this->cartArticleIds() as $id) {
                    $sourceIds[] = $id;
                }
                $excludeIds = $sourceIds;
            }

            $max = \max(1, \min(12, (int)$this->cfg('cs_max_items', '4')));
            $ids = CrossSellRepository::getRecommendedIds($sourceIds, $excludeIds, $max);
            if ($ids === []) {
                return;
            }
            $items = $this->loadItems($ids);
            if ($items === []) {
                return;
            }

            if ($ctx === 'product') {
                // Kaufbare Empfehlungen als Checkbox unter den Variationen, der Rest als Kartenliste.
                $shown = $this->injectOffer($doc, $smarty, $items);
                if ($shown !== []) {
                    $items = \array_values(\array_filter($items, static fn(array $i): bool => !\in_array($i['id'], $shown, true)));
                }
                if ($items === []) {
                    return;
                }
            }

            if ($ctx === 'product') {
                // Kaufbare Empfehlungen als Checkbox unter den Variationen, der Rest als Kartenliste.
                $shown = $this->injectOffer($doc, $smarty, $items);
                if ($shown !== []) {
                    $items = \array_values(\array_filter($items, static fn(array $i): bool => !\in_array($i['id'], $shown, true)));
                }
                if ($items === []) {
                    return;
                }
            }

            $tpl = $this->getPlugin()->getPaths()->getBasePath() . 'templates/crosssell.tpl';
            if (!\is_file($tpl)) {
                return;
            }
            $default = $ctx === 'product' ? 'Dazu passend' : 'Das passt dazu';
            $smarty->assign('csItems', $items);
            $smarty->assign('csTitle', $this->cfg($ctx === 'product' ? 'cs_title_product' : 'cs_title_cart', $default));
            $smarty->assign('csContext', $ctx);
            $smarty->assign('csShopUrl', \rtrim((string)Shop::getURL(), '/'));
            $smarty->assign('csTokenInput', Form::getTokenInput());
            try {
                $html = $smarty->fetch('file:' . $tpl);
            } catch (\Throwable $e) {
                return;
            }
            if ($html === '') {
                return;
            }
            $this->inject($doc, $html, $ctx);
        });
    }

    /**
     * @param object                         $doc
     * @param array<int,array<string,mixed>> $items
     * @return array<int,int> IDs der in der Checkbox-Box gezeigten Artikel
     */
    private function injectOffer($doc, $smarty, array $items): array
    {
        if ($doc->find('#cs-offer')->length > 0) {
            return [];
        }
        $offer    = \array_slice(\array_values(\array_filter($items, static fn(array $i): bool => !empty($i['buyable']))), 0, 3);
        $offerTpl = $this->getPlugin()->getPaths()->getBasePath() . 'templates/crosssell-offer.tpl';
        if ($offer === [] || !\is_file($offerTpl)) {
            return [];
        }
        $anchor = $doc->find('div.variations')->eq(0);
        $before = false;
        if ($anchor->length === 0) {
            $anchor = $doc->find('#add-to-cart')->eq(0);
            $before = true;
        }
        if ($anchor->length === 0) {
            return [];
        }
        $smarty->assign('csOfferItems', $offer);
        $smarty->assign('csShopUrl', \rtrim((string)Shop::getURL(), '/'));
        try {
            $html = $smarty->fetch('file:' . $offerTpl);
        } catch (\Throwable $e) {
            return [];
        }
        if ($html === '') {
            return [];
        }
        $before ? $anchor->before($html) : $anchor->after($html);

        return \array_map(static fn(array $i): int => (int)$i['id'], $offer);
    }
    /**
     * 'product' | 'cart' | '' (nicht anzeigen)
     */
    private function context(): string
    {
        $pt = Shop::getPageType();
        if ($pt === \PAGE_ARTIKEL && $this->cfg('cs_product_active', 'Y') === 'Y') {
            return 'product';
        }
        if ($pt === \PAGE_WARENKORB && $this->cfg('cs_cart_active', 'Y') === 'Y') {
            return 'cart';
        }

        return '';
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
     * Artikel-IDs im Warenkorb (inkl. Vaterartikel).
     *
     * @return array<int,int>
     */
    private function cartArticleIds(): array
    {
        $ids = [];
        try {
            $cart = Frontend::getCart();
            foreach ((array)($cart->PositionenArr ?? []) as $pos) {
                if ((int)($pos->nPosTyp ?? 0) !== 1 || !isset($pos->Artikel)) {
                    continue;
                }
                $ids[] = (int)$pos->Artikel->kArtikel;
                $ids[] = (int)($pos->Artikel->kVaterArtikel ?? 0);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return CrossSellRepository::ids($ids);
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>>
     */
    private function loadItems(array $ids): array
    {
        $db = Shop::Container()->getDB();
        try {
            $net = Frontend::getCustomerGroup()->isMerchant() ? 1 : 0;
        } catch (\Throwable $e) {
            $net = 0;
        }
        $withVariants = [];
        try {
            $rows = $db->getObjects(
                'SELECT DISTINCT kArtikel FROM teigenschaft WHERE kArtikel IN (' . \implode(',', $ids) . ')'
            );
            foreach ($rows as $r) {
                $withVariants[(int)$r->kArtikel] = true;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $items = [];
        foreach ($ids as $id) {
            try {
                $a = new Artikel();
                $a->fuelleArtikel($id, Artikel::getDefaultOptions());
                if ((int)($a->kArtikel ?? 0) <= 0) {
                    continue;
                }
                $img = '';
                if (!empty($a->Bilder[0])) {
                    $img = (string)($a->Bilder[0]->cURLNormal ?? $a->Bilder[0]->cURLKlein ?? '');
                }
                $price = '';
                if (isset($a->Preise->cVKLocalized[$net])) {
                    $price = \html_entity_decode((string)$a->Preise->cVKLocalized[$net], \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                }
                $items[] = [
                    'id'      => $id,
                    'name'    => \html_entity_decode((string)$a->cName, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
                    'url'     => (string)($a->cURLFull ?? $a->cURL ?? ''),
                    'image'   => $img,
                    'price'   => $price,
                    'buyable' => (int)($a->nIstVater ?? 0) === 0 && !isset($withVariants[$id]),
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
    private function inject($doc, string $html, string $ctx): void
    {
        $custom = \trim($this->cfg($ctx === 'product' ? 'cs_selector_product' : 'cs_selector_cart'));
        if ($custom !== '') {
            $t = $doc->find($custom)->eq(0);
            if ($t->length > 0) {
                $t->after($html);

                return;
            }
        }
        $after = $ctx === 'product'
            ? ['nav.tab-navigation', '#tab-content-product-tabs', '.product-tabs']
            : ['#cart-form', '.cart-items', '.basket_wrapper'];
        foreach ($after as $sel) {
            $t = $doc->find($sel)->eq(0);
            if ($t->length > 0) {
                $t->after($html);

                return;
            }
        }
        $c = $doc->find('div#content, main#main-wrapper, main')->eq(0);
        if ($c->length > 0) {
            $c->append($html);
        }
    }
}
