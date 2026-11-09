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