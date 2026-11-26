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
            'page'     => \max(1, (int)($_GET['sp_p'] ?? 1)),
            'active'   => $channel !== '' || $days > 0 || $q !== '' || $dateFrom !== '' || $dateTo !== '',
        ];
    }
}

if (!\defined('SP_UNASSIGNED')) {
    /** Filterwert / Kanal-Schlüssel für Bestellungen ohne Attribution. */
    \define('SP_UNASSIGNED', '_none');
}

if (!function_exists('sp_channel_display')) {
    function sp_channel_display(string $channel): string
    {
        return $channel === SP_UNASSIGNED || $channel === '' ? '(nicht zugeordnet)' : $channel;
    }
}

if (!function_exists('sp_scope_range')) {
    /** @return array{0:?string,1:?string} [von inkl., bis exkl.] als Datetime-Strings */
    function sp_scope_range(array $filters): array
    {
        $from = null;
        $to = null;
        if (($filters['dateFrom'] ?? '') !== '') {
            $from = $filters['dateFrom'] . ' 00:00:00';
        }
        if (($filters['dateTo'] ?? '') !== '') {
            $to = \date('Y-m-d', (int)\strtotime($filters['dateTo'] . ' +1 day')) . ' 00:00:00';
        }
        if ($from === null && $to === null && (int)($filters['days'] ?? 0) > 0) {
            $from = \date('Y-m-d', (int)\strtotime('-' . (int)$filters['days'] . ' days')) . ' 00:00:00';
        }

        return [$from, $to];
    }
}

if (!function_exists('sp_scope_label')) {
    function sp_scope_label(array $filters): string
    {
        [$from, $to] = sp_scope_range($filters);
        if ($from === null && $to === null) {
            $label = 'Alle Zeiten';
        } elseif (($filters['dateFrom'] ?? '') === '' && ($filters['dateTo'] ?? '') === '') {
            $label = 'Letzte ' . (int)$filters['days'] . ' Tage';
        } else {
            $label = ($filters['dateFrom'] !== '' ? $filters['dateFrom'] : '…') . ' – ' . ($filters['dateTo'] !== '' ? $filters['dateTo'] : 'heute');
        }
        if (($filters['channel'] ?? '') !== '') {
            $label .= ' · Kanal ' . sp_channel_display($filters['channel']);
        }

        return $label;
    }
}

if (!function_exists('sp_report_scope')) {
    /**
     * Gemeinsame Bedingungen für alle Rapor-Abfragen auf
     * "tbestellung b LEFT JOIN twomosi_sales_attribution a".
     * Storno = cStatus < 0 (überall gleich behandelt).
     *
     * @param array{channel?:bool,date?:bool,valid?:bool,prefix?:string} $opt
     * @return array{0:string,1:array<string,mixed>} [" AND …", Parameter]
     */
    function sp_report_scope(array $filters, array $opt = []): array
    {
        $p = (string)($opt['prefix'] ?? 'sc');
        $parts = [];
        $params = [];
        if ($opt['valid'] ?? true) {
            $parts[] = 'COALESCE(b.cStatus, 0) >= 0';
        }
        if (($opt['channel'] ?? true) && ($filters['channel'] ?? '') !== '') {
            if ($filters['channel'] === SP_UNASSIGNED) {
                $parts[] = 'a.kBestellung IS NULL';
            } else {
                $parts[] = 'a.cChannel = :' . $p . 'Ch';
                $params[$p . 'Ch'] = $filters['channel'];
            }
        }
        if ($opt['date'] ?? true) {
            [$from, $to] = sp_scope_range($filters);
            if ($from !== null) {
                $parts[] = 'b.dErstellt >= :' . $p . 'From';
                $params[$p . 'From'] = $from;
            }
            if ($to !== null) {
                $parts[] = 'b.dErstellt < :' . $p . 'To';
                $params[$p . 'To'] = $to;
            }
        }

        return [$parts === [] ? '' : ' AND ' . \implode(' AND ', $parts), $params];
    }
}

if (!function_exists('sp_db_objects')) {
    /** @return list<object> */
    function sp_db_objects(\JTL\DB\DbInterface $db, string $sql, array $params = []): array
    {
        return $params === [] ? $db->getObjects($sql) : $db->getObjects($sql, $params);
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
     * Meistverkaufte Artikel (Stück + Brutto-Positionsumsatz) über alle gültigen Bestellungen.
     * Varianten werden zum Vaterartikel zusammengefasst.
     *
     * @param 'month'|'all'|'today'|'yesterday'|'scope' $period 'scope' = Datumsfilter aus $filters
     * @return list<array{rank:int,name:string,qty:float,orders:int,revenue:float}>
     */
    function sp_load_top_products(\JTL\DB\DbInterface $db, int $limit = 3, string $period = 'month', ?array $filters = null): array
    {
        $limit = \max(1, \min(50, $limit));
        $periodCond = match ($period) {
            'today'     => ' AND b.dErstellt >= CURDATE()',
            'yesterday' => ' AND b.dErstellt >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND b.dErstellt < CURDATE()',
            'month'     => ' AND b.dErstellt >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')',
            default     => '',
        };
        [$scope, $params] = sp_report_scope($filters ?? [], [
            'channel' => $filters !== null,
            'date'    => $filters !== null && $period === 'scope',
            'prefix'  => 'tp',
        ]);
        $sql = 'SELECT COALESCE(MAX(pa.cName), MAX(ar.cName), MIN(TRIM(wp.cName))) AS cName,
                       SUM(wp.nAnzahl) AS nQty,
                       COUNT(DISTINCT b.kBestellung) AS nOrders,
                       SUM(wp.fPreis * wp.nAnzahl * (1 + COALESCE(wp.fMwSt, 0) / 100)) AS fRevenue
                  FROM tbestellung b
                  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
                  INNER JOIN twarenkorbpos wp ON wp.kWarenkorb = b.kWarenkorb AND wp.nPosTyp = 1
                  LEFT JOIN tartikel ar ON ar.kArtikel = wp.kArtikel
                  LEFT JOIN tartikel pa ON pa.kArtikel = ar.kVaterArtikel AND ar.kVaterArtikel > 0
                 WHERE NULLIF(TRIM(wp.cName), \'\') IS NOT NULL
                   AND b.kWarenkorb > 0'
            . $periodCond . $scope . '
                 GROUP BY CASE WHEN wp.kArtikel > 0
                               THEN CONCAT(\'a\', COALESCE(NULLIF(ar.kVaterArtikel, 0), wp.kArtikel))
                               ELSE CONCAT(\'n\', TRIM(wp.cName)) END
                 ORDER BY nQty DESC, fRevenue DESC
                 LIMIT ' . $limit;
        try {
            $rows = sp_db_objects($db, $sql, $params);
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
                'qty'     => (float)($row->nQty ?? 0),
                'orders'  => (int)($row->nOrders ?? 0),
                'revenue' => (float)($row->fRevenue ?? 0),
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
    function sp_load_period_channel_stats(\JTL\DB\DbInterface $db, ?array $filters = null): array
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
        [$scope, $params] = sp_report_scope($filters ?? [], ['date' => false, 'prefix' => 'pc']);
        $sql = 'SELECT COALESCE(a.cChannel, \'' . SP_UNASSIGNED . '\') AS cChannel,
       SUM(CASE WHEN b.dErstellt >= CURDATE() THEN 1 ELSE 0 END) AS nToday,
       SUM(CASE WHEN b.dErstellt >= CURDATE() THEN COALESCE(b.fGesamtsumme, 0) ELSE 0 END) AS fToday,
       SUM(CASE WHEN b.dErstellt >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND b.dErstellt < CURDATE() THEN 1 ELSE 0 END) AS nYesterday,
       SUM(CASE WHEN b.dErstellt >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND b.dErstellt < CURDATE() THEN COALESCE(b.fGesamtsumme, 0) ELSE 0 END) AS fYesterday,
       SUM(CASE WHEN b.dErstellt >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\') THEN 1 ELSE 0 END) AS nMonth,
       SUM(CASE WHEN b.dErstellt >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\') THEN COALESCE(b.fGesamtsumme, 0) ELSE 0 END) AS fMonth
  FROM tbestellung b
  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
 WHERE b.dErstellt >= LEAST(DATE_FORMAT(CURDATE(), \'%Y-%m-01\'), DATE_SUB(CURDATE(), INTERVAL 1 DAY))'
            . $scope . '
 GROUP BY COALESCE(a.cChannel, \'' . SP_UNASSIGNED . '\')
 ORDER BY fMonth DESC, nMonth DESC, cChannel ASC';
        try {
            $rows = sp_db_objects($db, $sql, $params);
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
                       COALESCE(SUM(b.fGesamtsumme), 0) AS fRevenue,
                       SUM(CASE WHEN a.kBestellung IS NOT NULL THEN 1 ELSE 0 END) AS nAttr,
                       COALESCE(SUM(CASE WHEN a.kBestellung IS NOT NULL THEN b.fGesamtsumme ELSE 0 END), 0) AS fAttr
                  FROM tbestellung b
                  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
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
                'month'       => $month,
                'orders'      => (int)($row->nOrders ?? 0),
                'revenue'     => (float)($row->fRevenue ?? 0),
                'attrOrders'  => (int)($row->nAttr ?? 0),
                'attrRevenue' => (float)($row->fAttr ?? 0),
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
 * @return array{rows:list<object>,total:int,searchNote:string,page:int,pages:int,perPage:int,generatedAt:string,filters:array}
 */
function sp_load_report_data(int $listLimit = 100, ?array $filters = null, bool $publicMode = false): array
{
    $db = Shop::Container()->getDB();
    if ($filters === null) {
        $filters = sp_parse_list_filters();
    }

    $perPage = \max(10, \min(500, $listLimit));
    $page = \max(1, (int)($filters['page'] ?? 1));
    $list = sp_load_order_list($db, $filters, $perPage, ($page - 1) * $perPage, $publicMode);
    $pages = \max(1, (int)\ceil($list['total'] / $perPage));
    if ($page > $pages && $list['total'] > 0) {
        $page = $pages;
        $list = sp_load_order_list($db, $filters, $perPage, ($page - 1) * $perPage, $publicMode);
    }

    return [
        'rows'        => $list['rows'],
        'total'       => $list['total'],
        'searchNote'  => $list['note'],
        'page'        => $page,
        'pages'       => $pages,
        'perPage'     => $perPage,
        'generatedAt' => \date('Y-m-d H:i:s'),
        'filters'     => $filters,
    ];
}

if (!function_exists('sp_order_status_label')) {
    /** @return array{0:string,1:string} [Label, CSS-Klasse] */
    function sp_order_status_label($status): array
    {
        return match ((int)$status) {
            -1      => ['Storno', 'st-storno'],
            1       => ['Offen', 'st-open'],
            2       => ['In Bearbeitung', 'st-open'],
            3       => ['Bezahlt', 'st-paid'],
            4       => ['Versandt', 'st-shipped'],
            5       => ['Teilversandt', 'st-shipped'],
            default => ['Status ' . (string)$status, 'st-open'],
        };
    }
}

if (!function_exists('sp_list_select_sql')) {
    /** SELECT … FROM … für die Bestellliste (ohne WHERE/ORDER/LIMIT). */
    function sp_list_select_sql(bool $full): string
    {
        $couponTypes = \implode(',', \array_map('intval', [
            \defined('C_WARENKORBPOS_TYP_KUPON') ? \C_WARENKORBPOS_TYP_KUPON : 3,
            \defined('C_WARENKORBPOS_TYP_NEUKUNDENKUPON') ? \C_WARENKORBPOS_TYP_NEUKUNDENKUPON : 12,
            \defined('C_WARENKORBPOS_TYP_GUTSCHEIN') ? \C_WARENKORBPOS_TYP_GUTSCHEIN : 6,
        ]));
        $extra = $full
            ? ', (
         SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(wp.cName), \'\') SEPARATOR \' | \')
           FROM twarenkorbpos wp
          WHERE wp.kWarenkorb = b.kWarenkorb AND b.kWarenkorb > 0 AND wp.nPosTyp = 1
       ) AS products_line,
       (
         SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(wc.cName), \'\') SEPARATOR \' | \')
           FROM twarenkorbpos wc
          WHERE wc.kWarenkorb = b.kWarenkorb AND b.kWarenkorb > 0 AND wc.nPosTyp IN (' . $couponTypes . ')
       ) AS coupon_line,
       (
         SELECT SUM(wc2.fPreis * wc2.nAnzahl * (1 + COALESCE(wc2.fMwSt, 0) / 100))
           FROM twarenkorbpos wc2
          WHERE wc2.kWarenkorb = b.kWarenkorb AND b.kWarenkorb > 0 AND wc2.nPosTyp IN (' . $couponTypes . ')
       ) AS coupon_value,
       (
         SELECT COUNT(*) FROM tbestellung bp
          WHERE b.kKunde > 0 AND bp.kKunde = b.kKunde AND bp.kBestellung < b.kBestellung
       ) AS nPrevOrders,
       k.nRegistriert AS nRegistriert'
            : ', \'\' AS products_line, \'\' AS coupon_line, NULL AS coupon_value, NULL AS nPrevOrders, NULL AS nRegistriert';

        return 'SELECT b.kBestellung, a.cChannel, a.cChannelLabel, a.cAttributionJson, a.dCreated,
       b.cBestellNr, b.dErstellt AS dBestellt, b.kKunde, b.cStatus, b.cZahlungsartName,
       b.fGesamtsumme AS orderSum, w.cISO AS orderCurrency,
       k.cVorname AS encVorname, k.cNachname AS encNachname, k.cMail AS encMail' . $extra . '
  FROM tbestellung b
  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
  LEFT JOIN tkunde k ON k.kKunde = b.kKunde
  LEFT JOIN twaehrung w ON w.kWaehrung = b.kWaehrung';
    }
}

if (!function_exists('sp_load_order_list')) {
    /**
     * Seitenweise Bestellliste (inkl. Storno, markiert). Suche per SQL über Bestellnr./Kanal/Produkt;
     * nur wenn das nichts findet (Admin), PHP-Suche über verschlüsselte Kundennamen.
     *
     * @return array{rows:list<object>,total:int,note:string}
     */
    function sp_load_order_list(\JTL\DB\DbInterface $db, array $filters, int $limit, int $offset, bool $publicMode): array
    {
        [$scope, $params] = sp_report_scope($filters, ['valid' => false, 'prefix' => 'ls']);
        $q = (string)($filters['q'] ?? '');
        if ($q !== '') {
            $like = '%' . sp_like_escape($q) . '%';
            $scope .= ' AND (b.cBestellNr LIKE :lq1 OR a.cChannel LIKE :lq2 OR a.cChannelLabel LIKE :lq3
                OR EXISTS (SELECT 1 FROM twarenkorbpos wq WHERE wq.kWarenkorb = b.kWarenkorb AND b.kWarenkorb > 0 AND wq.cName LIKE :lq4))';
            $params += ['lq1' => $like, 'lq2' => $like, 'lq3' => $like, 'lq4' => $like];
        }
        $where = ' WHERE 1 = 1' . $scope;
        $order = ' ORDER BY b.dErstellt DESC, b.kBestellung DESC LIMIT ' . \max(1, $limit) . ' OFFSET ' . \max(0, $offset);

        $total = 0;
        try {
            $cnt = $db->getSingleObject(
                'SELECT COUNT(*) AS n FROM tbestellung b LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung' . $where,
                $params
            );
            $total = (int)($cnt->n ?? 0);
        } catch (\Throwable $e) {
            $total = 0;
        }

        $rows = [];
        foreach ([true, false] as $full) {
            try {
                $rows = sp_db_objects($db, sp_list_select_sql($full) . $where . $order, $params);
                break;
            } catch (\Throwable $e) {
                $rows = [];
            }
        }
        if ($q === '' || $total > 0 || $publicMode) {
            return ['rows' => $rows, 'total' => $total, 'note' => ''];
        }

        [$scopeNoQ, $paramsNoQ] = sp_report_scope($filters, ['valid' => false, 'prefix' => 'ls']);
        try {
            $pool = sp_db_objects(
                $db,
                sp_list_select_sql(true) . ' WHERE 1 = 1' . $scopeNoQ . ' ORDER BY b.dErstellt DESC, b.kBestellung DESC LIMIT 2000',
                $paramsNoQ
            );
        } catch (\Throwable $e) {
            $pool = [];
        }
        $hits = sp_apply_text_filter_rows($pool, $q, 2000);

        return [
            'rows'  => \array_slice($hits, $offset, $limit),
            'total' => \count($hits),
            'note'  => 'Kundensuche: nur die letzten 2000 Bestellungen im Filter durchsucht.',
        ];
    }
}

if (!function_exists('sp_load_channel_table')) {
    /**
     * Kanal-Tabelle für den Filterzeitraum: Bestellungen, Umsatz, Ø Bestellwert, Anteil, Stornos.
     *
     * @return array{rows:list<array{channel:string,n:int,f:float,aov:float,share:float,storno:int,stornoF:float}>,total:array{n:int,f:float,aov:float,storno:int,stornoF:float}}
     */
    function sp_load_channel_table(\JTL\DB\DbInterface $db, array $filters): array
    {
        $chExpr = 'COALESCE(a.cChannel, \'' . SP_UNASSIGNED . '\')';
        $base = ' FROM tbestellung b LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung WHERE 1 = 1';
        $data = [];
        try {
            [$scope, $params] = sp_report_scope($filters, ['prefix' => 'ct']);
            foreach (sp_db_objects(
                $db,
                'SELECT ' . $chExpr . ' AS ch, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme), 0) AS f' . $base . $scope
                    . ' GROUP BY ' . $chExpr,
                $params
            ) as $r) {
                $data[(string)$r->ch] = ['n' => (int)$r->n, 'f' => (float)$r->f, 'storno' => 0, 'stornoF' => 0.0];
            }
            [$scopeS, $paramsS] = sp_report_scope($filters, ['valid' => false, 'prefix' => 'cs']);
            foreach (sp_db_objects(
                $db,
                'SELECT ' . $chExpr . ' AS ch, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme), 0) AS f' . $base . $scopeS
                    . ' AND b.cStatus < 0 GROUP BY ' . $chExpr,
                $paramsS
            ) as $r) {
                $ch = (string)$r->ch;
                $data[$ch] = ($data[$ch] ?? ['n' => 0, 'f' => 0.0, 'storno' => 0, 'stornoF' => 0.0]);
                $data[$ch]['storno'] = (int)$r->n;
                $data[$ch]['stornoF'] = (float)$r->f;
            }
        } catch (\Throwable $e) {
            $data = [];
        }

        $total = ['n' => 0, 'f' => 0.0, 'aov' => 0.0, 'storno' => 0, 'stornoF' => 0.0];
        foreach ($data as $d) {
            $total['n'] += $d['n'];
            $total['f'] += $d['f'];
            $total['storno'] += $d['storno'];
            $total['stornoF'] += $d['stornoF'];
        }
        $total['aov'] = $total['n'] > 0 ? $total['f'] / $total['n'] : 0.0;

        $rows = [];
        foreach ($data as $ch => $d) {
            $rows[] = [
                'channel' => (string)$ch,
                'n'       => $d['n'],
                'f'       => $d['f'],
                'aov'     => $d['n'] > 0 ? $d['f'] / $d['n'] : 0.0,
                'share'   => $total['f'] > 0 ? $d['f'] * 100 / $total['f'] : 0.0,
                'storno'  => $d['storno'],
                'stornoF' => $d['stornoF'],
            ];
        }
        \usort($rows, static fn (array $x, array $y): int => [$y['f'], $y['n']] <=> [$x['f'], $x['n']]);

        return ['rows' => $rows, 'total' => $total];
    }
}

if (!function_exists('sp_load_kpi_compare')) {
    /**
     * KPI-Kacheln mit Vergleich: Heute vs. gleicher Wochentag Vorwoche, Gestern vs. Vorwoche,
     * Monat bis heute vs. Vormonat bis gleicher Tag und Vorjahresmonat bis gleicher Tag.
     *
     * @return array<string, array{label:string,n:int,f:float,aov:float,attr:int,cmp:list<array{label:string,n:int,f:float}>}>
     */
    function sp_load_kpi_compare(\JTL\DB\DbInterface $db, array $filters): array
    {
        $now = \time();
        $today = (int)\strtotime('today', $now);
        $monthStart = (int)\strtotime(\date('Y-m-01', $now));
        $fmt = static fn (int $ts): string => \date('Y-m-d H:i:s', $ts);
        $shift = static fn (int $ts, string $mod): int => (int)\strtotime($mod, $ts);
        $ranges = [
            'today'      => [$today, $now],
            'todayW'     => [$shift($today, '-7 days'), $shift($now, '-7 days')],
            'yest'       => [$shift($today, '-1 day'), $today],
            'yestW'      => [$shift($today, '-8 days'), $shift($today, '-7 days')],
            'month'      => [$monthStart, $now],
            'monthPrev'  => [$shift($monthStart, '-1 month'), \min($shift($now, '-1 month'), $monthStart)],
            'monthYear'  => [$shift($monthStart, '-1 year'), $shift($now, '-1 year')],
        ];
        [$scope, $params] = sp_report_scope($filters, ['date' => false, 'prefix' => 'kp']);
        $cols = [];
        $minStart = $now;
        foreach ($ranges as $key => [$s, $e]) {
            $minStart = \min($minStart, $s);
            $cond = 'b.dErstellt >= :' . $key . 'S AND b.dErstellt < :' . $key . 'E';
            $params[$key . 'S'] = $fmt($s);
            $params[$key . 'E'] = $fmt($e);
            $cols[] = 'SUM(CASE WHEN ' . $cond . ' THEN 1 ELSE 0 END) AS n_' . $key;
            $cols[] = 'COALESCE(SUM(CASE WHEN ' . $cond . ' THEN b.fGesamtsumme ELSE 0 END), 0) AS f_' . $key;
        }
        $cols[] = 'SUM(CASE WHEN b.dErstellt >= :attrS AND a.kBestellung IS NOT NULL THEN 1 ELSE 0 END) AS n_attrMonth';
        $params['attrS'] = $fmt($monthStart);
        $params['minS'] = $fmt($minStart);
        try {
            $r = $db->getSingleObject(
                'SELECT ' . \implode(', ', $cols) . '
                   FROM tbestellung b
                   LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
                  WHERE b.dErstellt >= :minS' . $scope,
                $params
            );
        } catch (\Throwable $e) {
            $r = null;
        }
        $get = static function (string $key) use ($r): array {
            $n = (int)($r->{'n_' . $key} ?? 0);
            $f = (float)($r->{'f_' . $key} ?? 0);

            return ['n' => $n, 'f' => $f];
        };
        $tile = static function (string $label, string $key, array $cmp) use ($get): array {
            $cur = $get($key);
            $out = ['label' => $label, 'n' => $cur['n'], 'f' => $cur['f'], 'aov' => $cur['n'] > 0 ? $cur['f'] / $cur['n'] : 0.0, 'attr' => 0, 'cmp' => []];
            foreach ($cmp as $cmpLabel => $cmpKey) {
                $out['cmp'][] = ['label' => $cmpLabel] + $get($cmpKey);
            }

            return $out;
        };

        $month = $tile('Monat bis heute', 'month', ['Vormonat (gleicher Zeitraum)' => 'monthPrev', 'Vorjahr (gleicher Zeitraum)' => 'monthYear']);
        $month['attr'] = (int)($r->n_attrMonth ?? 0);

        return [
            'today'     => $tile('Heute', 'today', ['Vorwoche (gleicher Tag, bis jetzt)' => 'todayW']),
            'yesterday' => $tile('Gestern', 'yest', ['Vorwoche (gleicher Tag)' => 'yestW']),
            'month'     => $month,
        ];
    }
}

if (!function_exists('sp_load_daily_revenue')) {
    /** @return list<array{day:string,n:int,f:float}> lückenlos, älteste zuerst */
    function sp_load_daily_revenue(\JTL\DB\DbInterface $db, array $filters, int $days = 30): array
    {
        $days = \max(7, \min(120, $days));
        $start = \date('Y-m-d', (int)\strtotime('-' . ($days - 1) . ' days'));
        [$scope, $params] = sp_report_scope($filters, ['date' => false, 'prefix' => 'dr']);
        $params['drStart'] = $start . ' 00:00:00';
        $byDay = [];
        try {
            foreach (sp_db_objects(
                $db,
                'SELECT DATE(b.dErstellt) AS d, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme), 0) AS f
                   FROM tbestellung b
                   LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
                  WHERE b.dErstellt >= :drStart' . $scope . '
                  GROUP BY DATE(b.dErstellt)',
                $params
            ) as $r) {
                $byDay[(string)$r->d] = ['n' => (int)$r->n, 'f' => (float)$r->f];
            }
        } catch (\Throwable $e) {
            $byDay = [];
        }
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = \date('Y-m-d', (int)\strtotime($start . ' +' . $i . ' days'));
            $out[] = ['day' => $d, 'n' => $byDay[$d]['n'] ?? 0, 'f' => $byDay[$d]['f'] ?? 0.0];
        }

        return $out;
    }
}

if (!function_exists('sp_render_bar_chart')) {
    /**
     * Einfaches Inline-SVG-Balkendiagramm (druckbar, ohne JS).
     *
     * @param list<array{label:string,value:float,title:string}> $bars
     */
    function sp_render_bar_chart(array $bars, int $height = 160): string
    {
        if ($bars === []) {
            return '<p class="muted">Keine Daten.</p>';
        }
        $max = 0.0;
        foreach ($bars as $b) {
            $max = \max($max, (float)$b['value']);
        }
        $n = \count($bars);
        $w = 1000;
        $padB = 22;
        $slot = $w / $n;
        $barW = \max(2.0, $slot * 0.72);
        $plotH = $height - $padB - 6;
        $labelEvery = (int)\max(1, \ceil($n / 16));
        $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $height . '" preserveAspectRatio="none" role="img">';
        foreach ($bars as $i => $b) {
            $h = $max > 0 ? ((float)$b['value'] / $max) * $plotH : 0.0;
            $x = $i * $slot + ($slot - $barW) / 2;
            $y = 6 + $plotH - $h;
            $svg .= '<rect x="' . \round($x, 1) . '" y="' . \round($y, 1) . '" width="' . \round($barW, 1) . '" height="' . \round(\max($h, 0.5), 1) . '" rx="2">'
                . '<title>' . sp_h($b['title']) . '</title></rect>';
            if ($i % $labelEvery === 0) {
                $svg .= '<text x="' . \round($x + $barW / 2, 1) . '" y="' . ($height - 6) . '" text-anchor="middle">' . sp_h($b['label']) . '</text>';
            }
        }

        return $svg . '</svg>';
    }
}

if (!function_exists('sp_report_csv')) {
    /** CSV der gefilterten Bestellliste (max. 5000). Öffentlich ohne Kundendaten. */
    function sp_report_csv(\JTL\DB\DbInterface $db, array $filters, bool $publicMode): void
    {
        $list = sp_load_order_list($db, $filters, 5000, 0, $publicMode);
        $fn = 'salesproduct_rapor_' . \date('Y-m-d_His') . '.csv';
        if (!\headers_sent()) {
            \header('Content-Type: text/csv; charset=UTF-8');
            \header('Content-Disposition: attachment; filename="' . $fn . '"');
            \header('Cache-Control: no-store');
        }
        echo "\xEF\xBB\xBF";
        $head = ['Bestellnr.', 'Bestelldatum', 'Status', 'Summe', 'Währung', 'Zahlungsart', 'Kupon', 'Kupon-Wert',
            'Kunde-Typ', 'Kanal', 'Kanal-Label', 'Quelle', 'Produkte'];
        if (!$publicMode) {
            $head[] = 'Kunde (Name)';
            $head[] = 'Kunde (E-Mail)';
        }
        echo sp_csv_row($head);
        foreach ($list['rows'] as $r) {
            $sum = $r->orderSum ?? null;
            $cv = $r->coupon_value ?? null;
            $line = [
                (string)($r->cBestellNr ?? ''),
                (string)($r->dBestellt ?? ''),
                sp_order_status_label($r->cStatus ?? 0)[0],
                ($sum === null || $sum === '') ? '' : \number_format((float)$sum, 2, ',', ''),
                (string)($r->orderCurrency ?? ''),
                (string)($r->cZahlungsartName ?? ''),
                (string)($r->coupon_line ?? ''),
                ($cv === null || $cv === '') ? '' : \number_format((float)$cv, 2, ',', ''),
                sp_customer_type($r),
                sp_channel_display((string)($r->cChannel ?? '')),
                (string)($r->cChannelLabel ?? ''),
                sp_extract_source_detail(isset($r->cAttributionJson) ? (string)$r->cAttributionJson : null),
                (string)($r->products_line ?? ''),
            ];
            if (!$publicMode) {
                $vor = sp_decrypt_field(isset($r->encVorname) ? (string)$r->encVorname : '');
                $nach = sp_decrypt_field(isset($r->encNachname) ? (string)$r->encNachname : '');
                $line[] = \trim($vor . ' ' . $nach);
                $line[] = sp_decrypt_field(isset($r->encMail) ? (string)$r->encMail : '');
            }
            echo sp_csv_row($line);
        }
        exit;
    }
}

if (!function_exists('sp_customer_type')) {
    function sp_customer_type(object $r): string
    {
        if (($r->nPrevOrders ?? null) === null) {
            return '';
        }
        if ((int)($r->nRegistriert ?? 1) === 0) {
            return 'Gast';
        }

        return (int)$r->nPrevOrders > 0 ? 'Stammkunde' : 'Neukunde';
    }
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
        $q['sp_f_to'],
        $q['sp_p'],
        $q['sp_csv']
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

function sp_report_password_is_default(): bool
{
    $hash = sp_get_report_password_hash();

    return $hash === '' || \password_verify('ns123', $hash);
}

function sp_set_report_password(string $plain): bool
{
    if (\mb_strlen($plain, 'UTF-8') < 8) {
        return false;
    }
    try {
        Shop::Container()->getDB()->executeQueryPrepared(
            'INSERT INTO twomosi_sales_config (cKey, cValue) VALUES (\'report_password_hash\', :v)
             ON DUPLICATE KEY UPDATE cValue = :v2',
            ['v' => \password_hash($plain, PASSWORD_DEFAULT), 'v2' => \password_hash($plain, PASSWORD_DEFAULT)]
        );
    } catch (\Throwable $e) {
        return false;
    }

    return \password_verify($plain, sp_get_report_password_hash());
}

/** Token-Link ohne zusätzliches Passwort (Standard: aus). */
function sp_report_token_nopw(): bool
{
    try {
        $row = Shop::Container()->getDB()->getSingleObject(
            'SELECT cValue FROM twomosi_sales_config WHERE cKey = \'report_token_nopw\' LIMIT 1'
        );

        return $row !== null && (string)$row->cValue === '1';
    } catch (\Throwable $e) {
        return false;
    }
}

function sp_set_report_token_nopw(bool $on): void
{
    try {
        Shop::Container()->getDB()->executeQueryPrepared(
            'INSERT INTO twomosi_sales_config (cKey, cValue) VALUES (\'report_token_nopw\', :v)
             ON DUPLICATE KEY UPDATE cValue = :v2',
            ['v' => $on ? '1' : '0', 'v2' => $on ? '1' : '0']
        );
    } catch (\Throwable $e) {
        // ignore
    }
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
