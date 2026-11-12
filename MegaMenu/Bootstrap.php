<?php

declare(strict_types=1);

namespace Plugin\MegaMenu;

use JTL\Catalog\Category\MenuItem;
use JTL\Events\Dispatcher;
use JTL\Helpers\Category;
use JTL\Media\Image;
use JTL\Plugin\Bootstrapper;
use JTL\Session\Frontend;
use JTL\Shop;

require_once __DIR__ . '/src/ImageStore.php';

class Bootstrap extends Bootstrapper
{
    private const ASSET_CSS = 'plugins/MegaMenu/templates/css/megamenu.css';
    private const ASSET_JS  = 'plugins/MegaMenu/templates/js/megamenu.js';
    private const CACHE_TTL = 3600;
    private const MAX_TILES = 40;

    /** @var array<string, string>|null */
    private ?array $serieImages = null;

    /** @var array<int, array{d:string, m:string}> */
    private array $overrides = [];

    /** @var array<int, string> automatisch ermittelte Bilder (Vorschau im Dashboard) */
    private array $autoImages = [];

    private static bool $adminMenuEnsured = false;

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureAdminMenuEntry();
        if ($this->isBackendRequest()) {
            return;
        }

        $dispatcher->hookInto(\HOOK_LETZTERINCLUDE_CSS_JS, function (array &$args): void {
            foreach (['cPluginCss_arr' => self::ASSET_CSS, 'cPluginJsHead_arr' => self::ASSET_JS] as $key => $rel) {
                if (!isset($args[$key]) || !\is_array($args[$key])) {
                    $args[$key] = [];
                }
                $abs = \PFAD_ROOT . $rel;
                $url = $rel . '?v=' . (\is_file($abs) ? \filemtime($abs) : '1');
                if (!\in_array($url, $args[$key], true)) {
                    $args[$key][] = $url;
                }
            }
        });

        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            try {
                $this->render($args['document']);
            } catch (\Throwable $e) {
                \error_log('[MegaMenu] render: ' . $e->getMessage());
            }
        });
    }

    private function render($doc): void
    {
        $items = $doc->find('.nav-scrollbar-inner > li.dropdown-full');
        if ($items->length < 1 || $doc->find('.mm-panel')->length > 0) {
            return;
        }
        $langID = (int)Shop::getLanguageID() ?: 1;
        $cgID   = (int)Frontend::getCustomerGroup()->getID();
        $tree   = Category::getInstance($langID, $cgID)->combinedGetAll();
        $byID   = [];
        $byURL  = [];
        foreach ($tree as $root) {
            if ($root instanceof MenuItem) {
                $byID[$root->getID()]                      = $root;
                $byURL[\rtrim($root->getURL(), '/')]        = $root;
            }
        }
        if ($byID === []) {
            return;
        }
        $rules           = $this->discountRules();
        $this->overrides = (new ImageStore(Shop::Container()->getDB()))->all();
        $cacheKey        = 'mm_panels_' . \filemtime(__FILE__) . '_' . $langID . '_' . $cgID . '_'
            . \md5((string)\json_encode([$rules, $this->overrides]));
        $cache    = null;
        $panels   = null;
        try {
            $cache  = Shop::Container()->getCache();
            $panels = $cache->get($cacheKey);
        } catch (\Throwable $e) {
            $cache = null;
        }
        if (!\is_array($panels)) {
            $panels = [];
            foreach ($byID as $id => $root) {
                if ($root->hasChildren()) {
                    $panels[$id]           = $this->panelHtml($root, $rules);
                    $panels['mobile_' . $id] = $this->mobileHtml($root, $rules);
                }
                $thumb = $this->representativeImage($root, Image::SIZE_SM, 0, 'm');
                if ($thumb !== '') {
                    $panels['thumb_' . $id] = $thumb;
                }
            }
            if ($cache !== null) {
                try {
                    $cache->set(ImageStore::AUTO_CACHE, $this->autoImages, [\CACHING_GROUP_CORE], 86400 * 7);
                    $cache->set($cacheKey, $panels, [\CACHING_GROUP_CATEGORY, \CACHING_GROUP_CORE], self::CACHE_TTL);
                } catch (\Throwable $e) {
                }
            }
        }

        foreach ($items as $el) {
            $li   = \pq($el);
            $menu = $li->children('.dropdown-menu')->eq(0);
            if ($menu->length < 1) {
                continue;
            }
            $id = 0;
            if (\preg_match('/category-dropdown-(\d+)/', (string)$menu->attr('id'), $m)) {
                $id = (int)$m[1];
            }
            if (!isset($panels[$id])) {
                $href = \rtrim((string)$li->children('a')->eq(0)->attr('href'), '/');
                $id   = isset($byURL[$href]) ? $byURL[$href]->getID() : 0;
            }
            if (!isset($panels[$id]) || $panels[$id] === '') {
                continue;
            }
            $menu->addClass('mm-enhanced');
            $menu->append($panels[$id]);
            if (($panels['mobile_' . $id] ?? '') !== '') {
                $menu->append($panels['mobile_' . $id]);
            }
        }

        foreach ($doc->find('.nav-scrollbar-inner > li.nav-item') as $el) {
            if (\pq($el)->hasClass('nav-home-button')) {
                continue;
            }
            $a    = \pq($el)->children('a')->eq(0);
            $href = \rtrim((string)$a->attr('href'), '/');
            $id   = isset($byURL[$href]) ? $byURL[$href]->getID() : 0;
            if ($id < 1 || $a->find('.mm-m-thumb')->length > 0) {
                continue;
            }
            $a->addClass('mm-m-top');
            $inner = isset($panels['thumb_' . $id])
                ? '<img src="' . \htmlspecialchars($panels['thumb_' . $id], \ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" decoding="async">'
                : '<span class="mm-m-thumb__mono">' . \htmlspecialchars(\mb_strtoupper(\mb_substr($byURL[$href]->getName(), 0, 1)), \ENT_QUOTES, 'UTF-8') . '</span>';
            $a->prepend('<span class="mm-m-thumb" aria-hidden="true">' . $inner . '</span>');
        }
    }

    /**
     * Mobile Ansicht (Offcanvas): aufklappbare Gruppen mit Bildkacheln.
     *
     * @param list<array{lft:int,rght:int,pct:float,sub:int,id:int}> $rules
     */
    private function mobileHtml(MenuItem $root, array $rules): string
    {
        $e        = static fn(string $s): string => \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        $children = \array_values(\array_filter($root->getChildren(), static fn($c) => $c instanceof MenuItem));
        $rootPct  = $this->discountFor($root, $rules, true);
        $img      = $this->representativeImage($root, Image::SIZE_MD, 0, 'm');
        $hasGroup = false;
        foreach ($children as $c) {
            if ($c->hasChildren()) {
                $hasGroup = true;
                break;
            }
        }

        $html = '<div class="mm-m">'
            . '<a class="mm-m__hero' . ($rootPct > 0 ? ' mm-m__hero--deal' : '') . '" href="' . $e($root->getURL()) . '">'
            . ($img !== '' ? '<span class="mm-m__hero-img"><img src="' . $e($img) . '" alt="" loading="lazy" decoding="async"></span>' : '')
            . '<span class="mm-m__hero-body">'
            . ($rootPct > 0 ? '<span class="mm-m__kicker">Black Friday · bis zu -' . $this->pct($rootPct) . '%</span>' : '')
            . '<span class="mm-m__hero-title">' . $e($root->getName()) . '</span>'
            . '<span class="mm-m__hero-cta">Alle anzeigen →</span></span></a>';

        if (!$hasGroup) {
            return $html . $this->mobileGrid($children, $rules) . '</div>';
        }
        $first = true;
        foreach ($children as $child) {
            $pct   = $this->discountFor($child, $rules, true);
            $badge = $pct > 0 ? '<span class="mm-m__deal">-' . $this->pct($pct) . '%</span>' : '';
            if (!$child->hasChildren()) {
                $html .= '<a class="mm-m__row" href="' . $e($child->getURL()) . '"><span class="mm-m__row-name">'
                    . $e($child->getName()) . '</span>' . $badge . '<span class="mm-m__chev" aria-hidden="true">›</span></a>';
                continue;
            }
            $html .= '<details class="mm-m__group"' . ($first ? ' open' : '') . '>'
                . '<summary class="mm-m__row mm-m__row--group"><span class="mm-m__row-name">' . $e($child->getName()) . '</span>'
                . $badge . '<span class="mm-m__count">' . \count($child->getChildren()) . '</span>'
                . '<span class="mm-m__chev mm-m__chev--toggle" aria-hidden="true">›</span></summary>'
                . $this->mobileGrid($child->getChildren(), $rules)
                . '<a class="mm-m__all" href="' . $e($child->getURL()) . '">Alle ' . $e($child->getName()) . ' anzeigen →</a>'
                . '</details>';
            $first = false;
        }

        return $html . '</div>';
    }

    /**
     * @param MenuItem[] $items
     * @param list<array{lft:int,rght:int,pct:float,sub:int,id:int}> $rules
     */
    private function mobileGrid(array $items, array $rules): string
    {
        $e     = static fn(string $s): string => \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        $tiles = '';
        foreach ($items as $item) {
            if (!$item instanceof MenuItem) {
                continue;
            }
            $name   = $item->getName();
            $img    = $this->categoryImage($item, Image::SIZE_SM, 'm');
            $pct    = $this->discountFor($item, $rules, false);
            $short  = \trim((string)\preg_replace('/[\s\-]*Serie\s*$/iu', '', $name));
            $visual = $img !== ''
                ? '<img src="' . $e($img) . '" alt="" loading="lazy" decoding="async">'
                : '<span class="mm-m__mono">' . $e(\mb_strtoupper(\mb_substr($short !== '' ? $short : $name, 0, 4))) . '</span>';
            $tiles .= '<a class="mm-m__tile" href="' . $e($item->getURL()) . '">'
                . '<span class="mm-m__tile-img">' . $visual . '</span>'
                . '<span class="mm-m__tile-name">' . $e($name) . '</span>'
                . ($pct > 0 ? '<span class="mm-m__tile-deal">-' . $this->pct($pct) . '%</span>' : '')
                . '</a>';
        }

        return '<div class="mm-m__grid">' . $tiles . '</div>';
    }

    /**
     * @param list<array{lft:int,rght:int,pct:float,sub:int,id:int}> $rules
     */
    private function panelHtml(MenuItem $root, array $rules): string
    {
        $e        = static fn(string $s): string => \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        $children = \array_values(\array_filter($root->getChildren(), static fn($c) => $c instanceof MenuItem));
        if ($children === []) {
            return '';
        }
        $groups = [];
        $leaves = [];
        foreach ($children as $child) {
            if ($child->hasChildren()) {
                $groups[] = $child;
            } else {
                $leaves[] = $child;
            }
        }
        $rootPct = $this->discountFor($root, $rules, true);

        $side  = '';
        $panes = '';
        if ($groups === []) {
            $panes .= $this->paneHtml($root->getName(), $root->getURL(), $children, $rules, 0, true);
        } else {
            $i = 0;
            foreach ($children as $child) {
                $pct   = $this->discountFor($child, $rules, true);
                $badge = $pct > 0 ? '<span class="mm-tab__deal">-' . $this->pct($pct) . '%</span>' : '';
                $count = $child->hasChildren() ? '<span class="mm-tab__count">' . \count($child->getChildren()) . '</span>' : '';
                if ($child->hasChildren()) {
                    $side  .= '<a class="mm-tab' . ($i === 0 ? ' is-active' : '') . '" href="' . $e($child->getURL())
                        . '" data-mm-tab="' . $i . '"><span class="mm-tab__name">' . $e($child->getName()) . '</span>'
                        . $badge . $count . '<span class="mm-tab__arrow" aria-hidden="true">›</span></a>';
                    $panes .= $this->paneHtml($child->getName(), $child->getURL(), $child->getChildren(), $rules, $i, $i === 0);
                    $i++;
                } else {
                    $side .= '<a class="mm-tab mm-tab--leaf" href="' . $e($child->getURL()) . '"><span class="mm-tab__name">'
                        . $e($child->getName()) . '</span>' . $badge . '</a>';
                }
            }
        }

        $img   = $this->categoryImage($root, Image::SIZE_LG, 'd');
        $promo = '<a class="mm-promo' . ($rootPct > 0 ? ' mm-promo--deal' : '') . '" href="' . $e($root->getURL()) . '">'
            . ($img !== '' ? '<span class="mm-promo__img"><img src="' . $e($img) . '" alt="" loading="lazy" decoding="async"></span>' : '')
            . '<span class="mm-promo__body">'
            . ($rootPct > 0 ? '<span class="mm-promo__kicker">Black Friday · bis zu -' . $this->pct($rootPct) . '%</span>' : '<span class="mm-promo__kicker">Entdecken</span>')
            . '<span class="mm-promo__title">' . $e($root->getName()) . '</span>'
            . '<span class="mm-promo__cta">Alle ansehen <span aria-hidden="true">→</span></span>'
            . '</span></a>';

        return '<div class="mm-panel' . ($side === '' ? ' mm-panel--flat' : '') . '" data-mm-panel>'
            . '<div class="mm-panel__inner container">'
            . ($side !== '' ? '<nav class="mm-side" aria-label="' . $e($root->getName()) . '">' . $side
                . '<a class="mm-side__all" href="' . $e($root->getURL()) . '">Alle ' . $e($root->getName()) . ' <span aria-hidden="true">→</span></a></nav>' : '')
            . '<div class="mm-main">' . $panes . '</div>'
            . $promo
            . '</div></div>';
    }

    /**
     * @param MenuItem[] $items
     * @param list<array{lft:int,rght:int,pct:float,sub:int,id:int}> $rules
     */
    private function paneHtml(string $title, string $url, array $items, array $rules, int $idx, bool $active): string
    {
        $e     = static fn(string $s): string => \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
        $tiles = '';
        $n     = 0;
        foreach ($items as $item) {
            if (!$item instanceof MenuItem) {
                continue;
            }
            if (++$n > self::MAX_TILES) {
                break;
            }
            $name  = $item->getName();
            $img   = $this->categoryImage($item, Image::SIZE_SM, 'd');
            $pct   = $this->discountFor($item, $rules, false);
            $short = \trim((string)\preg_replace('/[\s\-]*Serie\s*$/iu', '', $name));
            $visual = $img !== ''
                ? '<img src="' . $e($img) . '" alt="" loading="lazy" decoding="async">'
                : '<span class="mm-tile__mono">' . $e(\mb_strtoupper(\mb_substr($short !== '' ? $short : $name, 0, 4))) . '</span>';
            $tiles .= '<a class="mm-tile' . ($pct > 0 ? ' mm-tile--deal' : '') . '" href="' . $e($item->getURL()) . '">'
                . '<span class="mm-tile__img">' . $visual . '</span>'
                . '<span class="mm-tile__name">' . $e($name) . '</span>'
                . ($pct > 0 ? '<span class="mm-tile__deal"><small>BLACK FRIDAY</small>-' . $this->pct($pct) . '%</span>' : '')
                . '</a>';
        }

        return '<section class="mm-pane' . ($active ? ' is-active' : '') . '" data-mm-pane="' . $idx . '">'
            . '<header class="mm-pane__head"><span class="mm-pane__title">' . $e($title) . '</span>'
            . '<a class="mm-pane__all" href="' . $e($url) . '">Alle anzeigen <span aria-hidden="true">→</span></a></header>'
            . '<div class="mm-grid">' . $tiles . '</div></section>';
    }

    /**
     * Eigenes Bild, sonst das erste Bild im Teilbaum (Tiefensuche).
     */
    private function representativeImage(MenuItem $item, string $size = Image::SIZE_SM, int $depth = 0, string $ctx = 'd'): string
    {
        $img = $this->categoryImage($item, $size, $ctx);
        if ($img !== '' || $depth > 2) {
            return $img;
        }
        foreach ($item->getChildren() as $child) {
            if ($child instanceof MenuItem) {
                $img = $this->representativeImage($child, $size, $depth + 1, $ctx);
                if ($img !== '') {
                    return $img;
                }
            }
        }

        return '';
    }

    /**
     * @param string $ctx 'd' = Web/Desktop, 'm' = Mobil (fällt auf Web-Auswahl zurück)
     */
    private function categoryImage(MenuItem $item, string $size = Image::SIZE_SM, string $ctx = 'd'): string
    {
        $id   = $item->getID();
        $over = $this->overrides[$id] ?? null;
        if ($over !== null) {
            $pick = $ctx === 'm' ? ($over['m'] !== '' ? $over['m'] : $over['d']) : $over['d'];
            if ($pick !== '') {
                return $pick;
            }
        }
        $auto = $this->autoImage($item, $size);
        if ($auto !== '' && !isset($this->autoImages[$id])) {
            $this->autoImages[$id] = $auto;
        }

        return $auto;
    }

    private function autoImage(MenuItem $item, string $size): string
    {
        $url = (string)($item->getImage($size) ?? '');
        if ($url !== '' && !\str_contains($url, \BILD_KEIN_KATEGORIEBILD_VORHANDEN)
            && !\str_contains($url, \BILD_KEIN_ARTIKELBILD_VORHANDEN)) {
            return $url;
        }
        $images = $this->serieImages();
        if ($images === []) {
            return '';
        }
        $name = \trim((string)\preg_replace('/[\s\-]*Serie\s*$/iu', '', $item->getName()));
        foreach ([$name, \mb_strtoupper($name), \preg_replace('/er$/i', '', $name), 'Serie ' . $name] as $key) {
            $key = \mb_strtoupper((string)$key);
            if ($key !== '' && isset($images[$key])) {
                return $images[$key];
            }
        }

        return '';
    }

    /**
     * Serienbilder aus FilterKonfiguratorSearch (Dashboard-Overrides + Defaults).
     *
     * @return array<string, string>
     */
    private function serieImages(): array
    {
        if ($this->serieImages !== null) {
            return $this->serieImages;
        }
        $this->serieImages = [];
        $class = '\Plugin\FilterKonfiguratorSearch\SeriesMetaRuntime';
        $file  = \PFAD_ROOT . 'plugins/FilterKonfiguratorSearch/src/SeriesMetaRuntime.php';
        try {
            if (!\class_exists($class, false) && \is_file($file)) {
                require_once $file;
            }
            if (\class_exists($class)) {
                $payload = $class::buildPayload(Shop::Container()->getDB());
                foreach ((array)($payload['meta'] ?? []) as $key => $meta) {
                    $img = \trim((string)($meta['img'] ?? ''));
                    if ($img !== '') {
                        $this->serieImages[\mb_strtoupper((string)$key)] = $img;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return $this->serieImages;
    }

    /**
     * Aktive Black-Friday-Regeln (nur während laufender Aktion).
     *
     * @return list<array{lft:int,rght:int,pct:float,sub:int,id:int}>
     */
    private function discountRules(): array
    {
        $class = '\Plugin\BlackFriday\DiscountService';
        if (!\class_exists($class)) {
            return [];
        }
        try {
            $db = Shop::Container()->getDB();
            if (!$class::isRunning((new $class($db))->getSettings())) {
                return [];
            }
            $rows = $db->getObjects(
                'SELECT r.kKategorie, r.fRabatt, r.nInclSub, k.lft, k.rght
                   FROM twomosi_bf_rule r
                   JOIN tkategorie k ON k.kKategorie = r.kKategorie
                  WHERE r.nActive = 1 AND r.fRabatt > 0'
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'   => (int)$r->kKategorie,
                'lft'  => (int)$r->lft,
                'rght' => (int)$r->rght,
                'pct'  => (float)$r->fRabatt,
                'sub'  => (int)$r->nInclSub,
            ];
        }

        return $out;
    }

    /**
     * @param list<array{lft:int,rght:int,pct:float,sub:int,id:int}> $rules
     * @param bool $withDescendants true: höchster Rabatt im gesamten Teilbaum („bis zu“)
     */
    private function discountFor(MenuItem $item, array $rules, bool $withDescendants): float
    {
        $max = 0.0;
        $lft = $item->getLeft();
        $rgt = $item->getRight();
        foreach ($rules as $r) {
            $applies = $r['id'] === $item->getID()
                || ($r['sub'] === 1 && $r['lft'] <= $lft && $r['rght'] >= $rgt)
                || ($withDescendants && $r['lft'] >= $lft && $r['rght'] <= $rgt);
            if ($applies && $r['pct'] > $max) {
                $max = $r['pct'];
            }
        }

        return $max;
    }

    private function pct(float $v): string
    {
        return \rtrim(\rtrim(\number_format($v, 1, ',', ''), '0'), ',');
    }

    private function ensureAdminMenuEntry(): void
    {
        if (self::$adminMenuEnsured) {
            return;
        }
        self::$adminMenuEnsured = true;
        try {
            $db       = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }
            $exists = $db->getSingleObject(
                'SELECT kPluginAdminMenu FROM tpluginadminmenu WHERE kPlugin = :p AND cDateiname = :c LIMIT 1',
                ['p' => $pluginID, 'c' => 'dashboard.php']
            );
            if ($exists === null) {
                $db->insert('tpluginadminmenu', (object)[
                    'kPlugin'    => $pluginID,
                    'cName'      => 'Mega Menü Bilder',
                    'cDateiname' => 'dashboard.php',
                    'nSort'      => 1,
                    'nConf'      => 0,
                ]);
            }
        } catch (\Throwable $e) {
        }
    }

    private function isBackendRequest(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        return \stripos($uri, '/admin') !== false || \stripos($uri, '/install') !== false;
    }
}
