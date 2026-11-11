<?php
declare(strict_types=1);

namespace Plugin\QueryGuard;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;

class Bootstrap extends Bootstrapper
{
    private const SKIP_PATH = '~/(admin|dbeS|install|bestellvorgang|warenkorb|jtl\.php|bestellabschluss|paypal|plugins/[^/]+/(paymentmethod|ipn))~i';

    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        try {
            if (\PHP_SAPI === 'cli' || $this->isExcluded()) {
                return;
            }
            $pdo = Shop::Container()->getDB()->getPDO();
            $timeout = $this->cfg('qg_timeout', 15);
            if ($timeout > 0) {
                // MariaDB (Sekunden) und MySQL 5.7+ (Millisekunden); nicht unterstuetzte Variable wird ignoriert.
                foreach (['SET SESSION max_statement_time=' . $timeout, 'SET SESSION max_execution_time=' . ($timeout * 1000)] as $sql) {
                    try {
                        $pdo->exec($sql);
                    } catch (\Throwable $e) {
                    }
                }
            }
            $threshold = $this->cfg('qg_threshold', 12);
            if ($threshold > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $slow = $this->cfg('qg_slow_after', 8);
                $st   = $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.PROCESSLIST
                     WHERE COMMAND = 'Query' AND TIME >= " . $slow . "
                     AND USER = SUBSTRING_INDEX(USER(), '@', 1) AND ID <> CONNECTION_ID()"
                );
                if ($st !== false && (int)$st->fetchColumn() >= $threshold) {
                    $this->overload();
                }
            }
        } catch (\Throwable $e) {
            // Guard darf nie selbst einen Fehler erzeugen.
        }

        if (\PHP_SAPI !== 'cli' && !$this->isExcluded()) {
            $dispatcher->hookInto(\HOOK_SMARTY_OUTPUTFILTER, function (array &$args): void {
                try {
                    if (isset($args['document'])) {
                        $this->optimizeFrontend($args['document']);
                    }
                } catch (\Throwable $e) {
                }
            }, 100);
        }
    }

    private const CONSENT_CSS = 'html.qg-cm #consent-manager{display:block!important}'
        . '#consent-manager{font-family:"Proxima Nova",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;color:#525252;font-size:.875rem;line-height:1.5;position:fixed;z-index:1050;bottom:2rem;left:1rem;right:1rem}'
        . '#consent-manager,#consent-manager *{box-sizing:border-box}'
        . '#consent-manager p{margin:0 0 1rem}#consent-manager p:last-child{margin-bottom:0}#consent-manager a{color:#435a6b}'
        . '#consent-banner{position:relative;border:.0625rem solid #ddd;border-radius:.125rem;padding:2rem 1rem 1rem;color:#525252;background:#fff;width:100%;max-width:75rem;margin:auto}'
        . '.consent-banner-icon{border-radius:10rem;padding:.75rem;width:3.4375rem;height:3.4375rem;background-color:inherit;border:.0625rem solid #ddd;position:absolute;left:50%;top:0;transform:translate(-50%,-50%);color:#435a6b}'
        . '.consent-banner-icon svg{width:100%;height:100%;position:relative;z-index:1}'
        . '.consent-banner-body{display:flex;align-items:center;flex-direction:column-reverse}'
        . '.consent-banner-description{flex-basis:0;flex-grow:1;max-width:100%}'
        . '.consent-banner-actions{margin-top:1rem;width:100%}'
        . '.consent-btn{display:inline-block;border-radius:.125rem;padding:.45rem .85rem;font-size:.75rem;cursor:pointer;border:.0625rem solid transparent}'
        . '.consent-btn-outline-primary{background:transparent;border-color:#435a6b;color:#435a6b}'
        . '.consent-btn-helper{margin:-.25rem;display:flex;flex-wrap:wrap}.consent-btn-helper>div{padding:0 .25rem;width:100%}'
        . '.consent-btn-helper>div>.consent-btn{margin:.25rem 0;flex-grow:1;font-weight:600;width:100%}'
        . '@media (min-width:768px){#consent-banner{padding:1rem 2rem}.consent-banner-body{flex-direction:row-reverse}.consent-banner-actions{margin-top:0;margin-left:2rem;width:16.875rem}.consent-btn{padding:.65rem 1.25rem;font-size:.875rem}}'
        . '@media (min-width:992px){.consent-banner-icon{left:0;top:50%}}';

    private const LATE_LOADER = '(function(w){var q=[],done=0,ev=["mousemove","scroll","keydown","touchstart","pointerdown"];'
        . 'function go(){if(done)return;done=1;ev.forEach(function(e){w.removeEventListener(e,go)});'
        . 'q.forEach(function(f){try{f()}catch(e){}});q=[]}'
        . 'w.qgLate=function(f){done?f():q.push(f)};'
        . 'ev.forEach(function(e){w.addEventListener(e,go,{passive:true})});'
        . 'w.addEventListener("load",function(){setTimeout(go,10000)})})(window);';

    /**
     * @param \JTL\phpQuery\phpQueryObject $doc
     */
    private function optimizeFrontend($doc): void
    {
        $head = '';

        $gtm = $doc->find('script#ws_gt_gtm');
        $tag = $doc->find('script[src^="https://www.googletagmanager.com/gtag/js"]');
        if ($gtm->length > 0 || $tag->length > 0) {
            $head .= '<script>' . self::LATE_LOADER . '</script>';
            if ($gtm->length > 0) {
                $code = (string)$gtm->text();
                if ($code !== '' && \strpos($code, 'qgLate') === false) {
                    $gtm->replaceWith('<script id="ws_gt_gtm">qgLate(function(){' . $code . '});</script>');
                }
            }
            foreach ($tag as $node) {
                $src = (string)\pq($node)->attr('src');
                \pq($node)->replaceWith(
                    '<script>qgLate(function(){var s=document.createElement("script");s.async=true;s.src='
                    . \json_encode($src, \JSON_UNESCAPED_SLASHES) . ';document.head.appendChild(s)});</script>'
                );
            }
        }

        $views = $doc->find('#consent-manager-show-banner');
        if ($views->length > 0 && $doc->find('#consent-banner')->length > 0) {
            $min = \max(1, (int)$views->attr('value'));
            $head .= '<style>' . self::CONSENT_CSS . '</style>'
                . '<script>(function(d){try{if(localStorage.getItem("consent")!==null)return;'
                . 'var s=JSON.parse(sessionStorage.getItem("consent")||"null")||{views:0};'
                . 'if((s.views||0)+1<' . $min . ')return;var h=d.documentElement;h.classList.add("qg-cm");'
                . 'd.addEventListener("consent.ready",function(){h.classList.remove("qg-cm")})}catch(e){}})(document);</script>';
        }

        if ($head !== '') {
            $doc->find('head')->prepend($head);
        }
    }

    private function cfg(string $key, int $default): int
    {
        try {
            $v = $this->getPlugin()->getConfig()->getValue($key);
        } catch (\Throwable $e) {
            return $default;
        }

        return ($v === null || $v === '') ? $default : \max(0, (int)$v);
    }

    private function isExcluded(): bool
    {
        $uri = (string)\parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), \PHP_URL_PATH);

        return $uri !== '' && \preg_match(self::SKIP_PATH, $uri) === 1;
    }

    private function overload(): void
    {
        if (!\headers_sent()) {
            \http_response_code(503);
            \header('Retry-After: 15');
            \header('Cache-Control: no-store');
            \header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Kurz ausgelastet</title></head>'
            . '<body style="font-family:sans-serif;text-align:center;padding:4rem 1rem">'
            . '<h1>Kurze Wartezeit</h1><p>Der Shop ist gerade stark ausgelastet. Bitte laden Sie die Seite in wenigen Sekunden neu.</p></body></html>';
        exit;
    }
}