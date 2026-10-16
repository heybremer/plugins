<?php

declare(strict_types=1);

use JTL\Shop;

if (!function_exists('sp_like_escape')) {
    function sp_like_escape(string $s): string
    {
        return \str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}

if (!function_exists('sp_parse_list_filters')) {
    /** @return array{channel:string,days:int,q:string,dateFrom:string,dateTo:string,active:bool} */
    function sp_parse_list_filters(): array
    {
        $channel = \trim((string)($_GET['sp_f_channel'] ?? ''));
        if ($channel !== '' && \preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $channel) !== 1) {
            $channel = '';
        }
        $days = (int)($_GET['sp_f_days'] ?? 0);
        // Legacy: ?days=30 auf Rapor-Links
        if ($days === 0 && isset($_GET['days'])) {
            $legacy = (int)$_GET['days'];
            if (\in_array($legacy, [7, 30, 90, 365], true)) {
                $days = $legacy;
            }
        }
        if (!\in_array($days, [0, 7, 30, 90, 365], true)) {
            $days = 0;
        }
        $q = \trim((string)($_GET['sp_f_q'] ?? ''));
        if (\strlen($q) > 120) {
            $q = \substr($q, 0, 120);
        }
        $dateFrom = \trim((string)($_GET['sp_f_from'] ?? ''));
        $dateTo = \trim((string)($_GET['sp_f_to'] ?? ''));
        if ($dateFrom !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1) {
            $dateTo = '';
        }

        return [
            'channel'  => $channel,
            'days'     => $days,
            'q'        => $q,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
            'active'   => $channel !== '' || $days > 0 || $q !== '' || $dateFrom !== '' || $dateTo !== '',
        ];
    }
}

if (!function_exists('sp_build_list_where')) {
    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    function sp_build_list_where(array $filters, bool $withOrderJoin): array
    {
        $parts = [];
        $params = [];

        if ($filters['channel'] !== '') {
            $parts[] = 'a.cChannel = :fChannel';
            $params['fChannel'] = $filters['channel'];
        }
        if ($filters['days'] > 0 && $filters['dateFrom'] === '' && $filters['dateTo'] === '') {
            $dateExpr = $withOrderJoin ? 'COALESCE(b.dErstellt, a.dCreated)' : 'a.dCreated';
            $parts[] = $dateExpr . ' >= DATE_SUB(CURDATE(), INTERVAL ' . (int)$filters['days'] . ' DAY)';
        }
        if ($filters['dateFrom'] !== '') {
            if ($withOrderJoin) {
                $parts[] = 'COALESCE(b.dErstellt, a.dCreated) >= :fFrom';
            } else {
                $parts[] = 'a.dCreated >= :fFrom';
            }
            $params['fFrom'] = $filters['dateFrom'] . ' 00:00:00';
        }
        if ($filters['dateTo'] !== '') {
            if ($withOrderJoin) {
                $parts[] = 'COALESCE(b.dErstellt, a.dCreated) <= :fTo';
            } else {
                $parts[] = 'a.dCreated <= :fTo';
            }
            $params['fTo'] = \date('Y-m-d', \strtotime($filters['dateTo'] . ' +1 day')) . ' 00:00:00';
            $parts[\count($parts) - 1] = \str_replace(' <= :fTo', ' < :fTo', $parts[\count($parts) - 1]);
        }

        $where = $parts === [] ? '' : ' WHERE ' . \implode(' AND ', $parts);

        return [$where, $params];
    }
}

if (!function_exists('sp_apply_text_filter_rows')) {
    /** @param list<object> $rows */
    function sp_apply_text_filter_rows(array $rows, string $q, int $maxRows = 400): array
    {
        if ($q === '' || $rows === []) {
            return $rows;
        }
        $qLower = \mb_strtolower($q, 'UTF-8');
        $rows = \array_values(\array_filter($rows, static function (object $r) use ($qLower): bool {
            $hay = \mb_strtolower(
                (string)($r->cBestellNr ?? '') . ' '
                . (string)($r->cChannel ?? '') . ' '
                . (string)($r->cChannelLabel ?? '') . ' '
                . (string)($r->products_line ?? '') . ' '
                . sp_decrypt_field(isset($r->encVorname) ? (string)$r->encVorname : '') . ' '
                . sp_decrypt_field(isset($r->encNachname) ? (string)$r->encNachname : '') . ' '
                . sp_decrypt_field(isset($r->encMail) ? (string)$r->encMail : ''),
                'UTF-8'
            );

            return \str_contains($hay, $qLower);
        }));
        if (\count($rows) > $maxRows) {
            $rows = \array_slice($rows, 0, $maxRows);
        }

        return $rows;
    }
}

if (!function_exists('sp_filters_to_query')) {
    /** @return array<string, string> */
    function sp_filters_to_query(array $filters): array
    {
        $q = [];
        if ($filters['channel'] !== '') {
            $q['sp_f_channel'] = $filters['channel'];
        }
        if ($filters['days'] > 0) {
            $q['sp_f_days'] = (string)$filters['days'];
        }
        if ($filters['q'] !== '') {
            $q['sp_f_q'] = $filters['q'];
        }
        if ($filters['dateFrom'] !== '') {
            $q['sp_f_from'] = $filters['dateFrom'];
        }
        if ($filters['dateTo'] !== '') {
            $q['sp_f_to'] = $filters['dateTo'];
        }

        return $q;
    }
}

if (!function_exists('sp_load_top_products')) {
    /**
     * Meistverkaufte Artikel (nach verkaufter Stückzahl) aus attribuierten Bestellungen.
     *
     * @param 'month'|'all'|'today'|'yesterday' $period
     * @return list<array{rank:int,name:string,qty:float,orders:int}>
     */
    function sp_load_top_products(\JTL\DB\DbInterface $db, int $limit = 3, string $period = 'month'): array
    {
        $limit = \max(1, \min(20, $limit));
        $periodCond = match ($period) {
            'today'     => 'AND DATE(COALESCE(b.dErstellt, a.dCreated)) = CURDATE()',
            'yesterday' => 'AND DATE(COALESCE(b.dErstellt, a.dCreated)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)',
            'month'     => 'AND YEAR(COALESCE(b.dErstellt, a.dCreated)) = YEAR(CURDATE())
                            AND MONTH(COALESCE(b.dErstellt, a.dCreated)) = MONTH(CURDATE())',
            default     => '',
        };
        $sql = 'SELECT TRIM(wp.cName) AS cName,
                       SUM(wp.nAnzahl) AS nQty,
                       COUNT(DISTINCT a.kBestellung) AS nOrders
                  FROM twomosi_sales_attribution a
                  INNER JOIN tbestellung b ON b.kBestellung = a.kBestellung
                  INNER JOIN twarenkorbpos wp ON wp.kWarenkorb = b.kWarenkorb
                   AND wp.nPosTyp = 1
                 WHERE NULLIF(TRIM(wp.cName), \'\') IS NOT NULL
                   AND b.kWarenkorb > 0
                 ' . $periodCond . '
                 GROUP BY TRIM(wp.cName)
                 ORDER BY nQty DESC
                 LIMIT ' . $limit;
        try {
            $rows = $db->getObjects($sql);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        $rank = 1;
        foreach ($rows as $row) {
            $name = \trim((string)($row->cName ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'rank'   => $rank++,
                'name'   => $name,
                'qty'    => (float)($row->nQty ?? 0),
                'orders' => (int)($row->nOrders ?? 0),
            ];
        }

        return $out;
    }
}

if (!function_exists('sp_load_period_channel_stats')) {
    /**
     * Verkäufe pro Kanal: heute, gestern, aktueller Monat (Stück + Umsatz).
     *
     * @return array{
     *   byChannel: array<string, array{channel:string,today:array{n:int,r:float},yesterday:array{n:int,r:float},month:array{n:int,r:float}>>,
     *   totals: array{today:array{n:int,r:float},yesterday:array{n:int,r:float},month:array{n:int,r:float}},
     *   monthLabel: string
     * }
     */
    function sp_load_period_channel_stats(\JTL\DB\DbInterface $db): array
    {
        $empty = [
            'byChannel'  => [],
            'totals'     => [
                'today'     => ['n' => 0, 'r' => 0.0],
                'yesterday' => ['n' => 0, 'r' => 0.0],
                'month'     => ['n' => 0, 'r' => 0.0],
            ],
            'monthLabel' => \date('Y-m'),
        ];
        $sql = <<<'SQL'
SELECT a.cChannel,
       SUM(CASE WHEN DATE(COALESCE(b.dErstellt, a.dCreated)) = CURDATE() THEN 1 ELSE 0 END) AS nToday,
       SUM(CASE WHEN DATE(COALESCE(b.dErstellt, a.dCreated)) = CURDATE() THEN COALESCE(b.fGesamtsumme, 0) ELSE 0 END) AS fToday,
       SUM(CASE WHEN DATE(COALESCE(b.dErstellt, a.dCreated)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS nYesterday,
       SUM(CASE WHEN DATE(COALESCE(b.dErstellt, a.dCreated)) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN COALESCE(b.fGesamtsumme, 0) ELSE 0 END) AS fYesterday,
       SUM(CASE WHEN YEAR(COALESCE(b.dErstellt, a.dCreated)) = YEAR(CURDATE())
                 AND MONTH(COALESCE(b.dErstellt, a.dCreated)) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS nMonth,
       SUM(CASE WHEN YEAR(COALESCE(b.dErstellt, a.dCreated)) = YEAR(CURDATE())
                 AND MONTH(COALESCE(b.dErstellt, a.dCreated)) = MONTH(CURDATE()) THEN COALESCE(b.fGesamtsumme, 0) ELSE 0 END) AS fMonth
  FROM twomosi_sales_attribution a
  LEFT JOIN tbestellung b ON b.kBestellung = a.kBestellung
 GROUP BY a.cChannel
 ORDER BY fMonth DESC, nMonth DESC, a.cChannel ASC
SQL;
        try {
            $rows = $db->getObjects($sql);
        } catch (\Throwable $e) {
            return $empty;
        }

        $byChannel = [];
        $totals = $empty['totals'];
        foreach ($rows as $row) {
            $ch = (string)($row->cChannel ?? '');
            if ($ch === '') {
                continue;
            }
            $today = ['n' => (int)($row->nToday ?? 0), 'r' => (float)($row->fToday ?? 0)];
            $yest = ['n' => (int)($row->nYesterday ?? 0), 'r' => (float)($row->fYesterday ?? 0)];
            $month = ['n' => (int)($row->nMonth ?? 0), 'r' => (float)($row->fMonth ?? 0)];
            if ($today['n'] === 0 && $yest['n'] === 0 && $month['n'] === 0) {
                continue;
            }
            $byChannel[$ch] = [
                'channel'   => $ch,
                'today'     => $today,
                'yesterday' => $yest,
                'month'     => $month,
            ];
            $totals['today']['n'] += $today['n'];
            $totals['today']['r'] += $today['r'];
            $totals['yesterday']['n'] += $yest['n'];
            $totals['yesterday']['r'] += $yest['r'];
            $totals['month']['n'] += $month['n'];
            $totals['month']['r'] += $month['r'];
        }

        $monthNames = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        $m = (int)\date('n');
        $y = \date('Y');

        return [
            'byChannel'  => $byChannel,
            'totals'     => $totals,
            'monthLabel' => ($monthNames[$m] ?? (string)$m) . ' ' . $y,
        ];
    }
}

if (!function_exists('sp_load_shop_month_revenue')) {
    /**
     * Voller Kalendermonat aus allen Bestellungen, nicht nur attribuierten Zeilen.
     * Stornos (cStatus < 0) sind ausgenommen. Umsatz = fGesamtsumme.
     *
     * @return list<array{month:string,orders:int,revenue:float}>
     */
    function sp_load_shop_month_revenue(\JTL\DB\DbInterface $db, int $months = 18): array
    {
        $months = \max(1, \min(36, $months));
        $sql = 'SELECT DATE_FORMAT(b.dErstellt, \'%Y-%m\') AS cMonth,
                       COUNT(*) AS nOrders,
                       COALESCE(SUM(b.fGesamtsumme), 0) AS fRevenue
                  FROM tbestellung b
                 WHERE b.dErstellt IS NOT NULL
                   AND b.dErstellt >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ' . ($months - 1) . ' MONTH), \'%Y-%m-01\')
                   AND b.dErstellt < DATE_ADD(DATE_FORMAT(CURDATE(), \'%Y-%m-01\'), INTERVAL 1 MONTH)
                   AND COALESCE(b.cStatus, 0) >= 0
                 GROUP BY DATE_FORMAT(b.dErstellt, \'%Y-%m\')
                 ORDER BY cMonth DESC';
        try {
            $rows = $db->getObjects($sql);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $month = (string)($row->cMonth ?? '');
            if ($month === '') {
                continue;
            }
            $out[] = [
                'month'   => $month,
                'orders'  => (int)($row->nOrders ?? 0),
                'revenue' => (float)($row->fRevenue ?? 0),
            ];
        }

        return $out;
    }
}

if (!function_exists('sp_get_filter_channels')) {
    /** @return list<string> */
    function sp_get_filter_channels(\JTL\DB\DbInterface $db): array
    {
        $out = [];
        try {
            foreach ($db->getObjects(
                'SELECT DISTINCT cChannel FROM twomosi_sales_attribution ORDER BY cChannel ASC'
            ) as $chRow) {
                $c = (string)($chRow->cChannel ?? '');
                if ($c !== '') {
                    $out[] = $c;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }
}

/**
 * @param array{channel:string,days:int,q:string,dateFrom:string,dateTo:string,active:bool}|null $filters
 * @return array{
 *   channelStatsAll: list<object>,
 *   channelStats30d: list<object>,
 *   channelRevenue: array<string, array{orders:int, revenue:float}>,
 *   rows: list<object>,
 *   nTotalOrders: int,
 *   generatedAt: string,
 *   filters: array,
 *   listLimit: int
 * }
 */
function sp_load_report_data(int $listLimit = 400, ?array $filters = null): array
{
    $db = Shop::Container()->getDB();
    if ($filters === null) {
        $filters = sp_parse_list_filters();
    }

    $channelStatsAll = [];
    $channelStats30d = [];
    try {
        $channelStatsAll = $db->getObjects(
            'SELECT cChannel, COUNT(*) AS nCount
               FROM twomosi_sales_attribution
              GROUP BY cChannel
              ORDER BY nCount DESC'
        );
    } catch (\Throwable $e) {
        $channelStatsAll = [];
    }
    try {
        $channelStats30d = $db->getObjects(
            'SELECT cChannel, COUNT(*) AS nCount
               FROM twomosi_sales_attribution
              WHERE dCreated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY cChannel
              ORDER BY nCount DESC'
        );
    } catch (\Throwable $e) {
        $channelStats30d = [];
    }

    $nTotalOrders = 0;
    foreach ($channelStatsAll as $s) {
        $nTotalOrders += (int)($s->nCount ?? 0);
    }

    $channelRevenue = [];
    try {
        $revRows = $db->getObjects(
            'SELECT a.cChannel, COUNT(*) AS nOrders, COALESCE(SUM(b.fGesamtsumme), 0) AS fRevenue
               FROM twomosi_sales_attribution a
               LEFT JOIN tbestellung b ON b.kBestellung = a.kBestellung
              GROUP BY a.cChannel
              ORDER BY fRevenue DESC'
        );
        foreach ($revRows as $rv) {
            $ch = (string)($rv->cChannel ?? '');
            if ($ch === '') {
                continue;
            }
            $channelRevenue[$ch] = [
                'orders'  => (int)($rv->nOrders ?? 0),
                'revenue' => (float)($rv->fRevenue ?? 0),
            ];
        }
    } catch (\Throwable $e) {
        $channelRevenue = [];
    }

    $fetchLimit = $filters['q'] !== '' ? \max($listLimit, 2000) : $listLimit;
    [$listWhere, $listParams] = sp_build_list_where($filters, true);
    [$listWhereMin, $listParamsMin] = sp_build_list_where($filters, false);

    $sqlList = 'SELECT b.kBestellung, a.cChannel, a.cChannelLabel, a.cAttributionJson, a.dCreated,
       b.cBestellNr, b.dErstellt AS dBestellt, b.kKunde,
       b.fGesamtsumme AS orderSum, w.cISO AS orderCurrency,
       k.cVorname AS encVorname, k.cNachname AS encNachname, k.cMail AS encMail,
       (
         SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(wp.cName), \'\') SEPARATOR \' | \')
           FROM twarenkorbpos wp
          WHERE wp.kWarenkorb = b.kWarenkorb
            AND b.kWarenkorb > 0
            AND wp.nPosTyp = 1
       ) AS products_line
  FROM tbestellung b
  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
  LEFT JOIN tkunde k ON k.kKunde = b.kKunde
  LEFT JOIN twaehrung w ON w.kWaehrung = b.kWaehrung'
        . $listWhere
        . ' ORDER BY b.dErstellt DESC, b.kBestellung DESC
 LIMIT ' . $fetchLimit;

    $sqlFallback = 'SELECT b.kBestellung, a.cChannel, a.cChannelLabel, a.cAttributionJson, a.dCreated,
       b.cBestellNr, b.dErstellt AS dBestellt, b.kKunde,
       b.fGesamtsumme AS orderSum, NULL AS orderCurrency,
       k.cVorname AS encVorname, k.cNachname AS encNachname, k.cMail AS encMail,
       \'\' AS products_line
  FROM tbestellung b
  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
  LEFT JOIN tkunde k ON k.kKunde = b.kKunde'
        . $listWhere
        . ' ORDER BY b.dErstellt DESC, b.kBestellung DESC
 LIMIT ' . $fetchLimit;

    $sqlMinimal = 'SELECT a.kBestellung, a.cChannel, a.cChannelLabel, a.cAttributionJson, a.dCreated,
       NULL AS cBestellNr, NULL AS dBestellt, 0 AS kKunde,
       NULL AS orderSum, NULL AS orderCurrency,
       NULL AS encVorname, NULL AS encNachname, NULL AS encMail,
       \'\' AS products_line
  FROM twomosi_sales_attribution a'
        . $listWhereMin
        . ' ORDER BY a.dCreated DESC, a.kBestellung DESC
 LIMIT ' . $fetchLimit;

    $rows = [];
    foreach ([[$sqlList, $listParams], [$sqlFallback, $listParams], [$sqlMinimal, $listParamsMin]] as [$sql, $params]) {
        try {
            $rows = $params === [] ? $db->getObjects($sql) : $db->getObjects($sql, $params);
            break;
        } catch (\Throwable $e) {
            $rows = [];
        }
    }

    $rows = sp_apply_text_filter_rows($rows, $filters['q'], $listLimit);

    return [
        'channelStatsAll'  => $channelStatsAll,
        'channelStats30d'  => $channelStats30d,
        'channelRevenue'   => $channelRevenue,
        'rows'             => $rows,
        'nTotalOrders'     => $nTotalOrders,
        'generatedAt'      => \date('Y-m-d H:i:s'),
        'filters'          => $filters,
        'listLimit'        => $listLimit,
    ];
}

function sp_get_report_token(bool $regenerate = false): string
{
    $db = Shop::Container()->getDB();
    try {
        $db->executeQuery(
            'CREATE TABLE IF NOT EXISTS twomosi_sales_config (
                cKey VARCHAR(64) NOT NULL PRIMARY KEY,
                cValue VARCHAR(512) NOT NULL DEFAULT ""
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    } catch (\Throwable $e) {
        // ignore
    }

    $key = 'report_token';
    if (!$regenerate) {
        try {
            $row = $db->getSingleObject(
                'SELECT cValue FROM twomosi_sales_config WHERE cKey = :k LIMIT 1',
                ['k' => $key]
            );
            $val = $row !== null ? \trim((string)($row->cValue ?? '')) : '';
            if ($val !== '' && \strlen($val) >= 32) {
                return $val;
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    $token = \bin2hex(\random_bytes(32));
    try {
        $db->executeQueryPrepared(
            'INSERT INTO twomosi_sales_config (cKey, cValue) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE cValue = :v2',
            ['k' => $key, 'v' => $token, 'v2' => $token]
        );
    } catch (\Throwable $e) {
        // ignore
    }

    return $token;
}

/** @param array{channel:string,days:int,q:string,dateFrom:string,dateTo:string,active:bool} $filters */
function sp_public_report_url(string $token, array $filters = []): string
{
    $base = \rtrim((string)Shop::getURL(), '/');
    $q = \array_merge(
        ['sp_salesreport' => '1', 'token' => $token],
        sp_filters_to_query($filters)
    );

    return $base . '/?' . \http_build_query($q);
}

/** @param array{channel:string,days:int,q:string,dateFrom:string,dateTo:string,active:bool} $filters */
function sp_admin_report_url(array $filters = []): string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $pu = \parse_url($uri);
    if (!\is_array($pu) || !isset($pu['path'])) {
        return '?' . \http_build_query(\array_merge(['sp_report' => '1'], sp_filters_to_query($filters)));
    }
    $q = [];
    if (isset($pu['query'])) {
        \parse_str((string)$pu['query'], $q);
    }
    unset(
        $q['sp_export'],
        $q['sp_reclassify'],
        $q['sp_new_token'],
        $q['sp_report'],
        $q['days'],
        $q['sp_f_channel'],
        $q['sp_f_days'],
        $q['sp_f_q'],
        $q['sp_f_from'],
        $q['sp_f_to']
    );
    $q['sp_report'] = '1';
    $q = \array_merge($q, sp_filters_to_query($filters));

    return (string)$pu['path'] . '?' . \http_build_query($q);
}

function sp_report_session_start(): void
{
    if (\session_status() === PHP_SESSION_NONE) {
        \session_start();
    }
}

function sp_ensure_report_password_hash(): void
{
    $key = 'report_password_hash';
    $db = Shop::Container()->getDB();
    try {
        $row = $db->getSingleObject(
            'SELECT cValue FROM twomosi_sales_config WHERE cKey = :k LIMIT 1',
            ['k' => $key]
        );
        $val = $row !== null ? \trim((string)($row->cValue ?? '')) : '';
        if ($val !== '' && \password_get_info($val)['algo'] !== 0) {
            return;
        }
    } catch (\Throwable $e) {
        // ignore
    }

    $hash = \password_hash('ns123', PASSWORD_DEFAULT);
    try {
        $db->executeQueryPrepared(
            'INSERT INTO twomosi_sales_config (cKey, cValue) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE cValue = IF(cValue = "" OR cValue IS NULL, :v2, cValue)',
            ['k' => $key, 'v' => $hash, 'v2' => $hash]
        );
    } catch (\Throwable $e) {
        // ignore
    }
}

function sp_get_report_password_hash(): string
{
    sp_ensure_report_password_hash();
    $db = Shop::Container()->getDB();
    try {
        $row = $db->getSingleObject(
            'SELECT cValue FROM twomosi_sales_config WHERE cKey = :k LIMIT 1',
            ['k' => 'report_password_hash']
        );

        return $row !== null ? \trim((string)($row->cValue ?? '')) : '';
    } catch (\Throwable $e) {
        return '';
    }
}

function sp_verify_report_password(string $plain): bool
{
    $hash = sp_get_report_password_hash();
    if ($hash === '') {
        return $plain === 'ns123';
    }

    return \password_verify($plain, $hash);
}

function sp_report_is_authenticated(): bool
{
    sp_report_session_start();

    return !empty($_SESSION['sp_report_auth']);
}

/** Abmelden und zur gleichen URL ohne Logout-Parameter weiterleiten. */
function sp_report_redirect_after_logout(): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $pu = \parse_url($uri);
    if (!\is_array($pu)) {
        \header('Location: /', true, 302);
        exit;
    }
    $q = [];
    if (isset($pu['query'])) {
        \parse_str((string)$pu['query'], $q);
    }
    unset($q['sp_report_logout']);
    $path = (string)($pu['path'] ?? '/');
    $target = $path . ($q !== [] ? '?' . \http_build_query($q) : '');

    if (!\headers_sent()) {
        \header('Location: ' . $target, true, 302);
    }
    exit;
}

/** Nach erfolgreichem Login: gleiche URL (GET), POST-Daten verwerfen. */
function sp_report_redirect_after_login(): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if (!\headers_sent()) {
        \header('Location: ' . $uri, true, 302);
    }
    exit;
}

/**
 * Rapor-Zugang: Session oder Passwort-Formular. Beendet die Anfrage, wenn nicht angemeldet.
 */
function sp_report_require_auth(): void
{
    sp_report_session_start();

    if (!empty($_GET['sp_report_logout'])) {
        unset($_SESSION['sp_report_auth']);
        sp_report_redirect_after_logout();
    }

    if (\strcasecmp((string)($_SERVER['REQUEST_METHOD'] ?? ''), 'POST') === 0
        && isset($_POST['sp_report_password'])
    ) {
        $pw = (string)$_POST['sp_report_password'];
        if (sp_verify_report_password($pw)) {
            $_SESSION['sp_report_auth'] = true;
            sp_report_redirect_after_login();
        }
        $spReportAuthError = 'Falsches Passwort.';
        require __DIR__ . '/report_auth_gate.php';
        exit;
    }

    if (sp_report_is_authenticated()) {
        return;
    }

    $spReportAuthError = $spReportAuthError ?? null;
    require __DIR__ . '/report_auth_gate.php';
    exit;
}
