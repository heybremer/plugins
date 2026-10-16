<?php

declare(strict_types=1);

namespace Plugin\MeGoogleTracking;

use JTL\Checkout\Bestellung;
use JTL\Events\Dispatcher;
use JTL\phpQuery\phpQuery;
use JTL\Plugin\Bootstrapper;
use JTL\Session\Frontend;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private static bool $adminMenuEnsured = false;

    // ─── Settings keys ───────────────────────────────────────────────────────
    private const K_GA4_ACTIVE      = 'megt_ga4_active';
    private const K_GA4_ID          = 'megt_ga4_id';
    private const K_ADS_ACTIVE      = 'megt_ads_active';
    private const K_ADS_ID          = 'megt_ads_id';
    private const K_ADS_LABEL       = 'megt_ads_label';
    private const K_ADS_VALUE       = 'megt_ads_value';
    private const K_GTM_ACTIVE      = 'megt_gtm_active';
    private const K_GTM_ID          = 'megt_gtm_id';
    private const K_CONSENT_ACTIVE  = 'megt_consent_mode';
    private const K_ANON_IP         = 'megt_anonymize_ip';
    private const K_ECOM_ACTIVE     = 'megt_ecommerce';

    // ─── Boot ────────────────────────────────────────────────────────────────
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);
        $this->ensureSettingsTable();
        $this->ensureAdminMenuEntry();
        $this->ensureConsentItems();

        // ── Head tag injection (gtag / GTM / Consent Mode) ───────────────────
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            /** @var \JTL\phpQuery\phpQueryObject $doc */
            $doc = $args['document'];
            $this->injectHeadTags($doc);
        });

        // ── purchase: store order id after it is written to the DB ────────────
        $dispatcher->hookInto(\HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE, function (array $args): void {
            $orderId = (int)($args['kBestellung'] ?? $args['oBestellung']->kBestellung ?? 0);
            if ($orderId > 0) {
                $_SESSION['megt_purchase_order_id'] = $orderId;
            }
        });

        // ── Body injection: real-time client-side ecommerce + purchase ────────
        // view_item / add_to_cart are handled client-side (gtag event) so they
        // fire in real time and survive JTL's AJAX cart. purchase is rendered
        // server-side on the order-completion page load.
        $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
            if (!isset($args['document'])) {
                return;
            }
            /** @var \JTL\phpQuery\phpQueryObject $doc */
            $doc = $args['document'];

            if ($this->isEcomActive()) {
                $this->injectEcommerceClientScript($doc);
            }

            if (!empty($_SESSION['megt_purchase_order_id'])) {
                $this->injectDataLayerEvents($doc);
            }
        });
    }

    // ─── Head tag injection ───────────────────────────────────────────────────

    private function injectHeadTags(\JTL\phpQuery\phpQueryObject $doc): void
    {
        $cfg = $this->loadSettings();

        // gtag.js (GA4 + Ads)
        $ga4Id = \trim((string)($cfg[self::K_GA4_ID] ?? ''));
        $adsId = \trim((string)($cfg[self::K_ADS_ID] ?? ''));
        $ga4On = (int)($cfg[self::K_GA4_ACTIVE] ?? 0) === 1 && $ga4Id !== '' && $ga4Id !== 'G-';
        $adsOn = (int)($cfg[self::K_ADS_ACTIVE] ?? 0) === 1 && $adsId !== '' && $adsId !== 'AW-';

        // Google Tag Manager
        $gtmId = \trim((string)($cfg[self::K_GTM_ID] ?? ''));
        $gtmOn = (int)($cfg[self::K_GTM_ACTIVE] ?? 0) === 1 && $gtmId !== '' && $gtmId !== 'GTM-';

        // Build single head block in correct order:
        // 1. Consent Mode (must be first)
        // 2. GTM script (optional)
        // 3. gtag.js + config
        $headBlock = '';
        if ((int)($cfg[self::K_CONSENT_ACTIVE] ?? 0) === 1) {
            $headBlock .= $this->renderConsentModeTag();
        }
        if ($gtmOn) {
            $headBlock .= $this->renderGtmScript($gtmId);
        }
        if ($ga4On || $adsOn) {
            $headBlock .= $this->renderGtagScript($ga4Id, $adsId, $ga4On, $adsOn, $cfg);
        }

        try {
            // Prepend directly into <head> — guaranteed absolute first position
            if ($headBlock !== '') {
                $doc->find('head')->prepend($headBlock);
            }

            // GTM <noscript> after opening <body>
            if ($gtmOn) {
                $doc->find('body')->prepend($this->renderGtmNoscript($gtmId));
            }
        } catch (\Throwable $e) {
            // ignore DOM errors
        }
    }

    private function renderConsentModeTag(): string
    {
        return <<<'HTML'
<!-- MeGoogleTracking: Consent Mode v2 -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    functionality_storage: 'granted',
    personalization_storage: 'denied',
    security_storage: 'granted',
    wait_for_update: 2000
});

// JTL consent.updated → gtag consent update bridge
(function(){
    var MAP = {
        // MeGoogleTracking own consent keys
        'megt_ga4_consent':  ['analytics_storage'],
        'megt_ads_consent':  ['ad_storage', 'ad_user_data', 'ad_personalization'],
        // ws5_googletracking keys (fallback if ws5 also registered)
        'ws5_googletracking_consent':     ['analytics_storage'],
        'ws5_googletracking_consent_ads': ['ad_storage', 'ad_user_data', 'ad_personalization'],
        'ws5_googletracking_consent_gtm': ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage'],
        // Generic fallbacks
        'google-analytics':   ['analytics_storage'],
        'google-ads':         ['ad_storage', 'ad_user_data', 'ad_personalization'],
        'google-tag-manager': ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage']
    };
    function applyConsent(detail) {
        var granted = {};
        var denied  = {};
        if (!detail) return;
        Object.keys(MAP).forEach(function(service) {
            var keys = MAP[service];
            if (!keys.length) return;
            var isGranted = detail[service] === true || detail[service] === 1 || detail[service] === 'true';
            keys.forEach(function(k) {
                if (isGranted) { granted[k] = 'granted'; }
                else           { denied[k]  = 'denied';  }
            });
        });
        // Merge: granted overrides denied
        var update = Object.assign({}, denied, granted);
        if (Object.keys(update).length) {
            gtag('consent', 'update', update);
        }
    }
    // Listen for JTL consent.updated event
    document.addEventListener('consent.updated', function(e) {
        applyConsent(e.detail || {});
    });
    // Also handle consent.ready (page load with existing consent)
    document.addEventListener('consent.ready', function(e) {
        applyConsent(e.detail || {});
    });
})();
</script>

HTML;
    }

    private function renderGtmScript(string $gtmId): string
    {
        $id = \htmlspecialchars($gtmId, \ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- MeGoogleTracking: GTM -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>

HTML;
    }

    private function renderGtmNoscript(string $gtmId): string
    {
        $id = \htmlspecialchars($gtmId, \ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!-- MeGoogleTracking: GTM noscript -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={$id}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
HTML;
    }

    private function renderGtagScript(
        string $ga4Id,
        string $adsId,
        bool $ga4On,
        bool $adsOn,
        array $cfg
    ): string {
        $primaryId    = $ga4On ? $ga4Id : $adsId;
        $primaryIdEsc = \htmlspecialchars($primaryId, \ENT_QUOTES, 'UTF-8');
        $anonIp       = (int)($cfg[self::K_ANON_IP] ?? 0) === 1;

        $configs = '';
        if ($ga4On) {
            $esc     = \htmlspecialchars($ga4Id, \ENT_QUOTES, 'UTF-8');
            $opts    = $anonIp ? "{'anonymize_ip': true}" : '{}';
            $configs .= "gtag('config', '{$esc}', {$opts});\n";
        }
        if ($adsOn) {
            $esc     = \htmlspecialchars($adsId, \ENT_QUOTES, 'UTF-8');
            $configs .= "gtag('config', '{$esc}');\n";
        }

        return <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$primaryIdEsc}"></script>
<script>
gtag('js', new Date());
{$configs}</script>

HTML;
    }

    // ─── dataLayer event injection ────────────────────────────────────────────

    private function injectDataLayerEvents(\JTL\phpQuery\phpQueryObject $doc): void
    {
        if (empty($_SESSION['megt_purchase_order_id'])) {
            return;
        }

        $orderId = (int)$_SESSION['megt_purchase_order_id'];
        unset($_SESSION['megt_purchase_order_id']);

        $purchaseData = $this->buildPurchasePayload($orderId);
        if ($purchaseData === null) {
            return;
        }

        $scripts = [];
        if ($this->isEcomActive()) {
            $scripts[] = $this->wrapGtagEvent('purchase', $purchaseData);
        }
        $scripts[] = $this->renderAdsConversionScript($purchaseData);

        $merged = \implode("\n", \array_filter($scripts));
        if ($merged === '') {
            return;
        }

        try {
            $doc->find('body')->append($merged);
        } catch (\Throwable $e) {}
    }

    /**
     * Emit a GA4 event via gtag('event', …). This is the correct send method for
     * gtag.js (a plain dataLayer.push would not be picked up without GTM); when a
     * GTM container is present, gtag('event', …) is forwarded to it as well.
     */
    private function wrapGtagEvent(string $event, array $data): string
    {
        $payload = \json_encode(
            $data,
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG
        );
        return "<script>if(typeof gtag==='function'){gtag('event','{$event}',{$payload});}</script>\n";
    }

    // ─── Client-side ecommerce (view_item / add_to_cart) ──────────────────────

    private function injectEcommerceClientScript(\JTL\phpQuery\phpQueryObject $doc): void
    {
        try {
            $doc->find('body')->append($this->renderEcommerceClientScript($this->getCurrency()));
        } catch (\Throwable $e) {}
    }

    /**
     * Real-time ecommerce tracking that reads the product's JSON-LD (schema.org
     * Product) already present on JTL product pages and sends GA4 events via
     * gtag. This fires on the actual user action and is therefore compatible with
     * JTL's AJAX add-to-cart (the previous session/next-pageview approach was not).
     */
    private function renderEcommerceClientScript(string $currency): string
    {
        $cur = \htmlspecialchars($currency, \ENT_QUOTES, 'UTF-8');
        $js  = <<<'JS'
<script>
(function(){
  var CURRENCY = '%%CURRENCY%%';
  if (window.__megtEcomBound) { return; }
  window.__megtEcomBound = true;

  function getProduct(){
    var els = document.querySelectorAll('script[type="application/ld+json"]');
    for (var i = 0; i < els.length; i++){
      var nodes;
      try {
        var parsed = JSON.parse(els[i].textContent);
        nodes = Array.isArray(parsed) ? parsed : (parsed['@graph'] ? parsed['@graph'] : [parsed]);
      } catch (e) { continue; }
      for (var k = 0; k < nodes.length; k++){
        var o = nodes[k];
        if (!o || o['@type'] !== 'Product') { continue; }
        var offer = Array.isArray(o.offers) ? o.offers[0] : o.offers;
        var price = 0, cur = CURRENCY;
        if (offer){
          var p = (offer.price != null) ? offer.price : ((offer.priceSpecification && offer.priceSpecification.price) || 0);
          price = parseFloat(p) || 0;
          if (offer.priceCurrency) { cur = offer.priceCurrency; }
        }
        var item = {
          item_id: String(o.sku || o.mpn || o.productID || o.gtin13 || o.gtin || ''),
          item_name: String(o.name || '').replace(/\s+/g, ' ').trim(),
          price: price
        };
        if (o.brand && o.brand.name) { item.item_brand = String(o.brand.name); }
        if (o.category) { item.item_category = String(o.category); }
        return { item: item, currency: cur };
      }
    }
    return null;
  }

  function send(name, prod, qty){
    if (!prod || typeof window.gtag !== 'function') { return; }
    var it = {};
    for (var key in prod.item){ if (prod.item[key] !== '' && prod.item[key] != null) { it[key] = prod.item[key]; } }
    it.quantity = qty || 1;
    window.gtag('event', name, {
      currency: prod.currency || CURRENCY,
      value: Math.round(((prod.item.price || 0) * (qty || 1)) * 100) / 100,
      items: [it]
    });
  }

  var PRODUCT = getProduct();
  if (PRODUCT && PRODUCT.item.item_id){
    send('view_item', PRODUCT, 1);
  }

  document.addEventListener('click', function(e){
    var el = e.target;
    var btn = el && el.closest ? el.closest('button, a, input[type="submit"]') : null;
    if (!btn) { return; }
    var label = ((btn.textContent || '') + ' ' + (btn.value || '') + ' ' + (btn.className || '')).toLowerCase();
    var isAdd = /in den warenkorb|add-to-cart|product-buy|btn-add-to-cart|tocart/.test(label);
    if (!isAdd || !PRODUCT) { return; }
    var qtyEl = document.querySelector('input[name="anzahl"], input#quantity, input.product-qty, [name="nAnzahl"]');
    var qty = qtyEl ? (parseInt(qtyEl.value, 10) || 1) : 1;
    send('add_to_cart', PRODUCT, qty);
  }, true);
})();
</script>

JS;
        return \str_replace('%%CURRENCY%%', $cur, $js);
    }

    private function renderAdsConversionScript(array $purchaseData): string
    {
        $cfg = $this->loadSettings();
        if ((int)($cfg[self::K_ADS_ACTIVE] ?? 0) !== 1) {
            return '';
        }
        $adsId    = \trim((string)($cfg[self::K_ADS_ID] ?? ''));
        $adsLabel = \trim((string)($cfg[self::K_ADS_LABEL] ?? ''));
        if ($adsId === '' || $adsId === 'AW-' || $adsLabel === '') {
            return '';
        }

        $value    = \round((float)($purchaseData['value'] ?? 0), 2);
        $currency = \htmlspecialchars((string)($purchaseData['currency'] ?? 'EUR'), \ENT_QUOTES, 'UTF-8');
        $txId     = \htmlspecialchars((string)($purchaseData['transaction_id'] ?? ''), \ENT_QUOTES, 'UTF-8');
        $sendTo   = \htmlspecialchars($adsId . '/' . $adsLabel, \ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script>
gtag('event', 'conversion', {
    'send_to': '{$sendTo}',
    'value': {$value},
    'currency': '{$currency}',
    'transaction_id': '{$txId}'
});
</script>

HTML;
    }

    // ─── Payload builders ─────────────────────────────────────────────────────

    /** @param object $pos Warenkorb-Position (kept for reference / future use) */
    private function buildCartItemPayload(object $pos): array
    {
        $name  = (string)($pos->cName ?? $pos->Artikel->cName ?? '');
        $artNr = (string)($pos->cArtNr ?? $pos->Artikel->cArtNr ?? '');
        $qty   = (int)($pos->nAnzahl ?? 1);
        $price = (float)($pos->fPreis ?? $pos->Artikel->fPreis ?? 0.0);

        return [
            'currency' => $this->getCurrency(),
            'value'    => \round($price * $qty, 2),
            'items'    => [[
                'item_id'   => $artNr,
                'item_name' => $name,
                'price'     => \round($price, 2),
                'quantity'  => $qty,
            ]],
        ];
    }

    private function buildPurchasePayload(int $orderId): ?array
    {
        try {
            $db    = Shop::Container()->getDB();
            $order = new Bestellung($orderId, true, $db);
            if ((int)($order->kBestellung ?? 0) <= 0) {
                return null;
            }

            $cfg       = $this->loadSettings();
            $valueMode = \trim((string)($cfg[self::K_ADS_VALUE] ?? 'gesamt_netto'));

            $gross    = (float)($order->fGesamtsumme ?? 0);
            $net      = (float)($order->fGesamtsummeNetto ?? $gross);
            $warenG   = (float)($order->fWarensumme ?? 0);
            $warenN   = (float)($order->fWarensummeNetto ?? $warenG);

            $value = match ($valueMode) {
                'gesamt_brutto' => $gross,
                'waren_brutto'  => $warenG,
                'waren_netto'   => $warenN,
                default         => $net,
            };

            $items = [];
            foreach ((array)($order->Positionen ?? []) as $pos) {
                if ((int)($pos->nPosTyp ?? 0) !== 1) {
                    continue;
                }
                $posName = \is_array($pos->cName) ? ($pos->cName[0] ?? '') : (string)($pos->cName ?? '');
                $items[] = [
                    'item_id'   => (string)($pos->cArtNr ?? ''),
                    'item_name' => $posName,
                    'price'     => \round((float)($pos->fPreis ?? 0), 2),
                    'quantity'  => (int)($pos->nAnzahl ?? 1),
                ];
            }

            return [
                'transaction_id' => (string)($order->cBestellNr ?? $orderId),
                'value'          => \round($value, 2),
                'currency'       => isset($order->Waehrung) ? $this->currencyIso($order->Waehrung) : $this->getCurrency(),
                'tax'            => \round(\max(0, $gross - $net), 2),
                'shipping'       => \round((float)($order->fVersandkosten ?? 0), 2),
                'items'          => $items,
            ];
        } catch (\Throwable $e) {
            \error_log('[MeGoogleTracking] buildPurchasePayload: ' . $e->getMessage());
            return null;
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function getCurrency(): string
    {
        try {
            return $this->currencyIso(Frontend::getCurrency());
        } catch (\Throwable $e) {
            return 'EUR';
        }
    }

    /** Extract the ISO code from a JTL Currency object using its getter (avoids deprecated magic property access). */
    private function currencyIso(mixed $cur): string
    {
        if (\is_object($cur)) {
            if (\method_exists($cur, 'getCode')) {
                $iso = (string)$cur->getCode();
                if ($iso !== '') {
                    return $iso;
                }
            }
            if (\method_exists($cur, 'getISO')) {
                $iso = (string)$cur->getISO();
                if ($iso !== '') {
                    return $iso;
                }
            }
        }
        return 'EUR';
    }

    private function isEcomActive(): bool
    {
        return (int)($this->loadSettings()[self::K_ECOM_ACTIVE] ?? 1) === 1;
    }

    /** @var array<string,string>|null */
    private ?array $settingsCache = null;

    /** @return array<string,string> */
    public function loadSettings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT cKey, cValue FROM twomosi_megoogletracking_settings'
            );
            $out = [];
            foreach ($rows as $r) {
                $out[(string)$r->cKey] = (string)$r->cValue;
            }
            $this->settingsCache = $out;
        } catch (\Throwable $e) {
            $this->settingsCache = [];
        }
        return $this->settingsCache;
    }

    // ─── DB / Admin init ─────────────────────────────────────────────────────

    private function ensureSettingsTable(): void
    {
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_megoogletracking_settings (
                    cKey   VARCHAR(100) NOT NULL,
                    cValue TEXT         NOT NULL DEFAULT "",
                    PRIMARY KEY (cKey)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            $defaults = [
                self::K_GA4_ACTIVE     => '0',
                self::K_GA4_ID         => 'G-',
                self::K_ADS_ACTIVE     => '0',
                self::K_ADS_ID         => 'AW-',
                self::K_ADS_LABEL      => '',
                self::K_ADS_VALUE      => 'gesamt_netto',
                self::K_GTM_ACTIVE     => '0',
                self::K_GTM_ID         => 'GTM-',
                self::K_CONSENT_ACTIVE => '0',
                self::K_ANON_IP        => '0',
                self::K_ECOM_ACTIVE    => '1',
            ];
            foreach ($defaults as $key => $val) {
                $db->executeQueryPrepared(
                    'INSERT IGNORE INTO twomosi_megoogletracking_settings (cKey, cValue) VALUES (:k, :v)',
                    ['k' => $key, 'v' => $val]
                );
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
        self::$adminMenuEnsured = true;

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
                $menu->cName      = 'Me Google Tracking';
                $menu->cDateiname = 'dashboard.php';
                $menu->nSort      = 10;
                $menu->nConf      = 0;
                $db->insert('tpluginadminmenu', $menu);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function ensureConsentItems(): void
    {
        try {
            $db       = Shop::Container()->getDB();
            $pluginID = (int)$this->getPlugin()->getID();
            if ($pluginID <= 0) {
                return;
            }

            $items = [
                [
                    'itemID'        => 'megt_ga4_consent',
                    'company'       => 'Google Ireland Limited',
                    'templateID'    => '',
                    'privacyPolicy' => 'https://policies.google.com/privacy',
                    'name'          => 'Google Analytics 4 (MeGoogleTracking)',
                    'description'   => 'Ermöglicht die Auswertung des Nutzerverhaltens durch Google Analytics 4.',
                    'purpose'       => 'Analytics',
                ],
                [
                    'itemID'        => 'megt_ads_consent',
                    'company'       => 'Google Ireland Limited',
                    'templateID'    => '',
                    'privacyPolicy' => 'https://policies.google.com/privacy',
                    'name'          => 'Google Ads Conversion (MeGoogleTracking)',
                    'description'   => 'Ermöglicht das Conversion-Tracking für Google Ads Kampagnen.',
                    'purpose'       => 'Marketing',
                ],
            ];

            // Language IDs from tsprache (column: kSprache)
            $languages = $db->getObjects('SELECT kSprache FROM tsprache');

            foreach ($items as $item) {
                // Check by itemID column
                $existing = $db->getSingleObject(
                    'SELECT id FROM tconsent WHERE itemID = :id LIMIT 1',
                    ['id' => $item['itemID']]
                );
                if ($existing !== null) {
                    continue;
                }

                // Insert into tconsent (columns: itemID, templateID, company, pluginID, active)
                $row             = new \stdClass();
                $row->itemID     = $item['itemID'];
                $row->templateID = $item['templateID'];
                $row->company    = $item['company'];
                $row->pluginID   = $pluginID;
                $row->active     = 1;
                $consentId       = $db->insert('tconsent', $row);

                if ($consentId <= 0) {
                    continue;
                }

                // Insert into tconsentlocalization (columns: consentID, languageID, name, description, purpose, privacyPolicy)
                foreach ($languages as $lang) {
                    $loc                = new \stdClass();
                    $loc->consentID     = $consentId;
                    $loc->languageID    = (int)$lang->kSprache;
                    $loc->name          = $item['name'];
                    $loc->description   = $item['description'];
                    $loc->purpose       = $item['purpose'];
                    $loc->privacyPolicy = $item['privacyPolicy'];
                    $db->insert('tconsentlocalization', $loc);
                }
            }
        } catch (\Throwable $e) {
            \error_log('[MeGoogleTracking] ensureConsentItems: ' . $e->getMessage());
        }
    }
}
