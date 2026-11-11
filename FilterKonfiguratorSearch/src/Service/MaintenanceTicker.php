<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Service;

use JTL\DB\DbInterface;
use JTL\DB\ReturnType;
use JTL\Shop;

/**
 * Pseudo-Cron (JTL registriert den Plugin-Cronjob nicht): läuft höchstens einmal pro Minute
 * nach dem Ausliefern einer Frontend-Seite und erledigt je Durchlauf einen kleinen Schritt.
 * - täglich: Suchprotokoll > 30 Tage löschen (in Batches)
 * - wöchentlich nachts (02–05 Uhr): PKW-Snapshot Hersteller/Modell → Baujahr → Serien (in Teilen)
 */
final class MaintenanceTicker
{
    private const LOG_DAYS       = 30;
    private const SELECTION_DAYS = 90;
    private const SNAPSHOT_DAYS  = 7;

    public function __construct(private readonly DbInterface $db)
    {
    }

    public static function maybeSchedule(): void
    {
        if (\PHP_SAPI === 'cli'
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
            || !\function_exists('fastcgi_finish_request')
            || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        ) {
            return;
        }
        if (\preg_match('~^/(admin|dbeS|install)|/fk-search~i', (string)($_SERVER['REQUEST_URI'] ?? ''))) {
            return;
        }
        $flag = \sys_get_temp_dir() . '/fk_maint_' . \md5(__DIR__);
        $last = @\filemtime($flag);
        if ($last !== false && $last > \time() - 60) {
            return;
        }
        @\touch($flag);
        \register_shutdown_function([self::class, 'run']);
    }

    public static function run(): void
    {
        @\fastcgi_finish_request();
        \ignore_user_abort(true);
        @\set_time_limit(600);
        try {
            require_once __DIR__ . '/SnapshotService.php';
            (new self(Shop::Container()->getDB()))->tick();
        } catch (\Throwable $e) {
            \error_log('[FilterKonfiguratorSearch] maintenance: ' . $e->getMessage());
        }
    }

    public function tick(): void
    {
        $this->db->executeQuery(
            "CREATE TABLE IF NOT EXISTS twomosi_fk_maint (
                cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                cValue VARCHAR(255) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->db->executeQuery("INSERT IGNORE INTO twomosi_fk_maint (cKey, cValue) VALUES ('lock', '0')");
        $now  = \time();
        $lock = (int)$this->db->executeQueryPrepared(
            "UPDATE twomosi_fk_maint SET cValue = :now WHERE cKey = 'lock' AND CAST(cValue AS UNSIGNED) < :stale",
            ['now' => (string)$now, 'stale' => $now - 900],
            ReturnType::AFFECTED_ROWS
        );
        if ($lock !== 1) {
            return;
        }
        // QueryGuard begrenzt Frontend-Sessions; Wartung braucht längere Statements.
        try {
            $this->db->executeQuery('SET SESSION max_statement_time = 0');
        } catch (\Throwable $e) {
        }

        try {
            $svc   = new SnapshotService($this->db);
            $today = \date('Y-m-d');
            if ($this->get('prune_day') !== $today) {
                if ($svc->pruneLogsBatch(self::LOG_DAYS, self::SELECTION_DAYS, 20.0)) {
                    $this->set('prune_day', $today);
                }
                return;
            }

            $stage = $this->get('pkw_stage');
            if ($stage === '') {
                $hour    = (int)\date('G');
                $lastDay = $this->get('pkw_day');
                $due     = $lastDay === '' || \strtotime($lastDay) < \strtotime('-' . self::SNAPSHOT_DAYS . ' days');
                if (!$due || $hour < 2 || $hour >= 5) {
                    return;
                }
                $stage = 'hm';
            }
            if ($stage === 'hm') {
                $svc->refreshPkwHerstellerModell(1);
                $this->set('pkw_stage', 'bj');
            } elseif ($stage === 'bj') {
                $svc->refreshPkwBaujahr(1);
                $this->set('pkw_offset', '0');
                $this->set('pkw_stage', 'serie');
            } else {
                $r = $svc->refreshPkwSerieChunk(1, (int)$this->get('pkw_offset'), 30.0);
                if ($r['done']) {
                    $this->set('pkw_stage', '');
                    $this->set('pkw_day', $today);
                } else {
                    $this->set('pkw_offset', (string)$r['next']);
                }
            }
        } finally {
            $this->set('lock', '0');
        }
    }

    private function get(string $key): string
    {
        $row = $this->db->getSingleObject('SELECT cValue FROM twomosi_fk_maint WHERE cKey = :k', ['k' => $key]);

        return $row !== null ? (string)$row->cValue : '';
    }

    private function set(string $key, string $value): void
    {
        $this->db->executeQueryPrepared(
            'INSERT INTO twomosi_fk_maint (cKey, cValue) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
            ['k' => $key, 'v' => $value]
        );
    }
}
