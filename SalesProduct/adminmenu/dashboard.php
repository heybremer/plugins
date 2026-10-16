<?php

declare(strict_types=1);

use JTL\Shop;

require_once __DIR__ . '/sp_helpers.php';
require_once __DIR__ . '/report_lib.php';

$db = Shop::Container()->getDB();

if (!empty($_GET['sp_report'])) {
    $spReportPublic = false;
    require __DIR__ . '/report_view.php';
    exit;
}

// Tabelle idempotent anlegen (falls Bootstrap noch nicht lief)
try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_sales_attribution (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            kBestellung INT NOT NULL,
            cChannel VARCHAR(64) NOT NULL,
            cChannelLabel VARCHAR(512) NOT NULL DEFAULT "",
            cAttributionJson LONGTEXT NULL,
            dCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_kBestellung (kBestellung),
            KEY idx_dCreated (dCreated),
            KEY idx_cChannel (cChannel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {
    // ignore
}

/** @var list<object> $channelStatsAll */
$channelStatsAll = [];
/** @var list<object> $channelStats30d */
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

$periodChannelStats = sp_load_period_channel_stats($db);
$topProductsMonth = sp_load_top_products($db, 3, 'month');
$topProductsAll = sp_load_top_products($db, 3, 'all');

if (!function_exists('sp_reclassify_attribution')) {
    /**
     * Klassifiziert eine gespeicherte Attribution anhand des JSON-Snapshots neu.
     * Liefert ['channel'=>..., 'label'=>...] oder null wenn keine bessere Zuordnung gefunden wurde.
     *
     * @return array{channel:string,label:string}|null
     */
    function sp_reclassify_attribution(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        $data = \json_decode($json, true);
        if (!\is_array($data)) {
            return null;
        }
        // Versuche zuerst first-touch (echte Akquise), dann last/picked.
        foreach (['first', 'picked', 'last'] as $key) {
            $node = $data[$key] ?? null;
            if (!\is_array($node)) {
                continue;
            }
            $detail = \trim((string)($node['source_detail'] ?? ''));
            $channel = \strtolower(\trim((string)($node['channel'] ?? '')));
            $host = sp_url_host($detail);

            // Falls Payment-Redirect: überspringen.
            if ($host !== '' && sp_is_payment_redirect_host($host)) {
                continue;
            }
            // Google-Ads-Indikatoren in source_detail oder Host
            $isPmax = \stripos($detail, 'pmax') !== false
                || \stripos($detail, 'performance_max') !== false;
            $isGclid = \stripos($detail, 'gclid') !== false;
            $isShopping = \stripos($detail, 'srsltid') !== false;
            $isGoogleAdHost = $host !== '' && (
                \preg_match('/(^|\\.)googleadservices\\.com$/', $host) === 1
                || \preg_match('/(^|\\.)googleads\\.g\\.doubleclick\\.net$/', $host) === 1
                || \preg_match('/(^|\\.)doubleclick\\.net$/', $host) === 1
                || \preg_match('/(^|\\.)googlesyndication\\.com$/', $host) === 1
            );
            $isGoogleSearch = $host !== '' && \preg_match('/^(www\\.)?google\\.[a-z.]{2,8}$/', $host) === 1;

            // source_detail vom Format "google / <medium> / <campaign>" (utmDetail-Ausgabe).
            // Wenn utm_source "google" enthält UND ein utm_medium vorhanden ist, dann Google Ads
            // (organische Google-Suche bringt keine UTM-Parameter mit).
            $detailLower = \strtolower($detail);
            $looksLikeGoogleUtm = false;
            if ($detailLower !== '' && $host === '') {
                // erstes Segment ist die utm_source (durch " / " getrennt)
                $parts = \array_map('trim', \explode('/', $detailLower));
                $src = $parts[0] ?? '';
                $med = $parts[1] ?? '';
                if ($src !== '' && \str_contains($src, 'google') && $med !== '') {
                    $looksLikeGoogleUtm = true;
                }
            }

            if ($isPmax || $isGclid || $isShopping || $isGoogleAdHost
                || $looksLikeGoogleUtm
                || $channel === 'google_ads') {
                $extra = $isPmax ? ' (PMax)'
                    : ($isGclid ? ' (gclid)'
                    : ($isShopping ? ' (Shopping)'
                    : ($looksLikeGoogleUtm ? ' (UTM)' : '')));

                return [
                    'channel' => 'google_ads',
                    'label'   => 'Google Ads' . $extra . ($host !== '' ? ' · ' . $host : ''),
                ];
            }
            if ($isGoogleSearch) {
                return [
                    'channel' => 'seo',
                    'label'   => 'SEO Google (' . $host . ')',
                ];
            }
            if ($host !== '' && \preg_match('/(^|\\.)(bing|yahoo|duckduckgo|ecosia)\\./', $host) === 1) {
                return [
                    'channel' => 'seo',
                    'label'   => 'SEO (' . $host . ')',
                ];
            }
            if ($channel === 'meta_ads' || ($host !== '' && \preg_match('/(^|\\.)(facebook|instagram|fb)\\./', $host) === 1)) {
                return [
                    'channel' => 'meta_ads',
                    'label'   => 'Meta / Facebook Ads' . ($host !== '' ? ' · ' . $host : ''),
                ];
            }
            if ($channel === 'email') {
                return ['channel' => 'email', 'label' => 'E-Mail / Newsletter'];
            }
            if ($channel === 'utm_campaign') {
                return ['channel' => 'utm_campaign', 'label' => 'Kampagne (UTM)'];
            }
            if ($host !== '') {
                return [
                    'channel' => 'referral',
                    'label'   => 'Referral · ' . $host,
                ];
            }
        }

        return null;
    }
}

// Reklassifizierung bestehender Zeilen (nur GET-Trigger – kein Auto-Run).
$reclassifyResult = null;
if (!empty($_GET['sp_reclassify'])) {
    $changed = 0;
    $checked = 0;
    try {
        $all = $db->getObjects(
            'SELECT kID, cChannel, cChannelLabel, cAttributionJson FROM twomosi_sales_attribution'
        );
        foreach ($all as $a) {
            $checked++;
            $new = sp_reclassify_attribution(isset($a->cAttributionJson) ? (string)$a->cAttributionJson : null);
            if ($new === null) {
                continue;
            }
            if ($new['channel'] === (string)($a->cChannel ?? '')
                && $new['label'] === (string)($a->cChannelLabel ?? '')) {
                continue;
            }
            $db->executeQueryPrepared(
                'UPDATE twomosi_sales_attribution
                    SET cChannel = :c, cChannelLabel = :l
                  WHERE kID = :k',
                [
                    'c' => $new['channel'],
                    'l' => \function_exists('mb_substr')
                        ? \mb_substr($new['label'], 0, 500, 'UTF-8')
                        : \substr($new['label'], 0, 500),
                    'k' => (int)($a->kID ?? 0),
                ]
            );
            $changed++;
        }
        $reclassifyResult = ['checked' => $checked, 'changed' => $changed, 'error' => null];
    } catch (\Throwable $e) {
        $reclassifyResult = ['checked' => $checked, 'changed' => $changed, 'error' => $e->getMessage()];
    }

    // Daten frisch laden, damit Statistik die neuen Werte zeigt
    try {
        $channelStatsAll = $db->getObjects(
            'SELECT cChannel, COUNT(*) AS nCount
               FROM twomosi_sales_attribution
              GROUP BY cChannel
              ORDER BY nCount DESC'
        );
        $channelStats30d = $db->getObjects(
            'SELECT cChannel, COUNT(*) AS nCount
               FROM twomosi_sales_attribution
              WHERE dCreated >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY cChannel
              ORDER BY nCount DESC'
        );
        $nTotalOrders = 0;
        foreach ($channelStatsAll as $s) {
            $nTotalOrders += (int)($s->nCount ?? 0);
        }
        $periodChannelStats = sp_load_period_channel_stats($db);
        $topProductsMonth = sp_load_top_products($db, 3, 'month');
        $topProductsAll = sp_load_top_products($db, 3, 'all');
    } catch (\Throwable $e) {
        // ignore
    }
}

$shopMonths = sp_load_shop_month_revenue($db, 18);
$listFilters = sp_parse_list_filters();
$filterChannels = sp_get_filter_channels($db);

[$listWhere, $listParams] = sp_build_list_where($listFilters, true);
[$listWhereMin, $listParamsMin] = sp_build_list_where($listFilters, false);

$listLimit = $listFilters['q'] !== '' ? 2000 : 400;

// JTL 5: Bestellpositionen liegen in twarenkorbpos (kWarenkorb), nicht in tbestellpos (existiert nicht).
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
 LIMIT ' . $listLimit;

$sqlListFallback = 'SELECT b.kBestellung, a.cChannel, a.cChannelLabel, a.cAttributionJson, a.dCreated,
       b.cBestellNr, b.dErstellt AS dBestellt, b.kKunde,
       b.fGesamtsumme AS orderSum, NULL AS orderCurrency,
       k.cVorname AS encVorname, k.cNachname AS encNachname, k.cMail AS encMail,
       \'\' AS products_line
  FROM tbestellung b
  LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
  LEFT JOIN tkunde k ON k.kKunde = b.kKunde'
    . $listWhere
    . ' ORDER BY b.dErstellt DESC, b.kBestellung DESC
 LIMIT ' . $listLimit;

$sqlListMinimal = 'SELECT a.kBestellung, a.cChannel, a.cChannelLabel, a.cAttributionJson, a.dCreated,
       NULL AS cBestellNr, NULL AS dBestellt, 0 AS kKunde,
       NULL AS orderSum, NULL AS orderCurrency,
       NULL AS encVorname, NULL AS encNachname, NULL AS encMail,
       \'\' AS products_line
  FROM twomosi_sales_attribution a'
    . $listWhereMin
    . ' ORDER BY a.dCreated DESC, a.kBestellung DESC
 LIMIT ' . $listLimit;

/** @var list<object> $rows */
$rows = [];
$listQueryNote = '';
$listQueryError = '';
$listAttempts = [
    [$sqlList, $listParams, ''],
    [$sqlListFallback, $listParams, 'Hinweis: Produkte (twarenkorbpos) konnten nicht geladen werden — Anzeige ohne Artikelzeilen.'],
    [$sqlListMinimal, $listParamsMin, 'Hinweis: Nur Attribution-Tabelle; Bestell-/Kunden-Join nicht möglich.'],
];
foreach ($listAttempts as [$sql, $params, $note]) {
    try {
        $rows = $params === [] ? $db->getObjects($sql) : $db->getObjects($sql, $params);
        $listQueryNote = $note;
        $listQueryError = '';
        break;
    } catch (\Throwable $e) {
        $listQueryError = $e->getMessage();
        $rows = [];
    }
}
if ($rows === [] && $listQueryError !== '') {
    $listQueryNote = 'Listenabfrage fehlgeschlagen: ' . $listQueryError;
}

$rows = sp_apply_text_filter_rows($rows, $listFilters['q'], 400);
$listRowCount = \count($rows);

// --- Diagnose: warum ist die Liste evtl. leer? -------------------------------
$diag = [
    'ordersTotal'        => null,
    'attrTotal'          => $nTotalOrders,
    'pluginInstalledAt'  => null,
    'ordersSinceInstall' => null,
    'lastOrder'          => null,
    'lastAttr'           => null,
    'tableExists'        => null,
];
try {
    $row = $db->getSingleObject('SELECT COUNT(*) AS n FROM tbestellung');
    $diag['ordersTotal'] = $row !== null ? (int)($row->n ?? 0) : null;
} catch (\Throwable $e) {
    // ignore
}
try {
    $row = $db->getSingleObject(
        "SELECT TABLE_NAME AS tn FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'twomosi_sales_attribution' LIMIT 1"
    );
    $diag['tableExists'] = $row !== null;
} catch (\Throwable $e) {
    $diag['tableExists'] = null;
}
try {
    $row = $db->getSingleObject(
        "SELECT MIN(dInstalliert) AS d FROM tplugin WHERE cPluginID = 'SalesProduct'"
    );
    $diag['pluginInstalledAt'] = $row !== null ? (string)($row->d ?? '') : '';
    if ($diag['pluginInstalledAt'] !== '' && $diag['pluginInstalledAt'] !== null) {
        $row = $db->getSingleObject(
            'SELECT COUNT(*) AS n FROM tbestellung WHERE dErstellt >= :d',
            ['d' => $diag['pluginInstalledAt']]
        );
        $diag['ordersSinceInstall'] = $row !== null ? (int)($row->n ?? 0) : null;
    }
} catch (\Throwable $e) {
    // ignore
}
try {
    $row = $db->getSingleObject(
        'SELECT cBestellNr, dErstellt FROM tbestellung ORDER BY kBestellung DESC LIMIT 1'
    );
    if ($row !== null) {
        $diag['lastOrder'] = \trim((string)($row->cBestellNr ?? '') . ' (' . (string)($row->dErstellt ?? '') . ')');
    }
} catch (\Throwable $e) {
    // ignore
}
try {
    $row = $db->getSingleObject(
        'SELECT kBestellung, cChannel, dCreated FROM twomosi_sales_attribution
          ORDER BY dCreated DESC, kID DESC LIMIT 1'
    );
    if ($row !== null) {
        $diag['lastAttr'] = 'kBestellung=' . (int)($row->kBestellung ?? 0)
            . ' ' . (string)($row->cChannel ?? '')
            . ' @ ' . (string)($row->dCreated ?? '');
    }
} catch (\Throwable $e) {
    // ignore
}

/** @var list<object> $rowsExport */
$rowsExport = [];
if (!empty($_GET['sp_export'])) {
    $exportLimit = 5000;
    $exportSql = \preg_replace('/LIMIT \d+$/', 'LIMIT ' . $exportLimit, $sqlList) ?? $sqlList;
    $exportSqlFb = \preg_replace('/LIMIT \d+$/', 'LIMIT ' . $exportLimit, $sqlListFallback) ?? $sqlListFallback;
    $exportSqlMin = \preg_replace('/LIMIT \d+$/', 'LIMIT ' . $exportLimit, $sqlListMinimal) ?? $sqlListMinimal;
    try {
        $rowsExport = $listParams === [] ? $db->getObjects($exportSql) : $db->getObjects($exportSql, $listParams);
    } catch (\Throwable $e) {
        try {
            $rowsExport = $listParams === [] ? $db->getObjects($exportSqlFb) : $db->getObjects($exportSqlFb, $listParams);
        } catch (\Throwable $e2) {
            try {
                $rowsExport = $listParamsMin === [] ? $db->getObjects($exportSqlMin) : $db->getObjects($exportSqlMin, $listParamsMin);
            } catch (\Throwable $e3) {
                $rowsExport = [];
            }
        }
    }
    $rowsExport = sp_apply_text_filter_rows($rowsExport, $listFilters['q'], 5000);

    $fn = 'salesproduct_kanale_' . \date('Y-m-d_His') . '.csv';
    if (!\headers_sent()) {
        \header('Content-Type: text/csv; charset=UTF-8');
        \header('Content-Disposition: attachment; filename="' . $fn . '"');
        \header('Cache-Control: no-store');
    }
    echo "\xEF\xBB\xBF";
    echo sp_csv_row([
        'Bestellnr.',
        'Bestell-ID',
        'Bestelldatum',
        'Summe',
        'Währung',
        'Kanal',
        'Kanal-Label',
        'Quelle (Referrer/UTM)',
        'Kunde (Name)',
        'Kunde (E-Mail)',
        'Produkte',
        'Attribution gespeichert',
    ]);
    foreach ($rowsExport as $r) {
        $vor = sp_decrypt_field(isset($r->encVorname) ? (string)$r->encVorname : '');
        $nach = sp_decrypt_field(isset($r->encNachname) ? (string)$r->encNachname : '');
        $mail = sp_decrypt_field(isset($r->encMail) ? (string)$r->encMail : '');
        $name = \trim($vor . ' ' . $nach);
        if ($name === '') {
            $name = $mail !== '' ? '(' . $mail . ')' : '';
        }
        $sd = sp_extract_source_detail(isset($r->cAttributionJson) ? (string)$r->cAttributionJson : null);
        $sumRaw = $r->orderSum ?? null;
        $sumCsv = ($sumRaw === null || $sumRaw === '') ? '' : \number_format((float)$sumRaw, 2, ',', '');
        echo sp_csv_row([
            (string)($r->cBestellNr ?? ''),
            (string)(int)($r->kBestellung ?? 0),
            (string)($r->dBestellt ?? ''),
            $sumCsv,
            (string)($r->orderCurrency ?? ''),
            (string)($r->cChannel ?? ''),
            (string)($r->cChannelLabel ?? ''),
            $sd,
            $name,
            $mail,
            (string)($r->products_line ?? ''),
            (string)($r->dCreated ?? ''),
        ]);
    }
    exit;
}

$reportToken = sp_get_report_token(!empty($_GET['sp_new_token']));
$exportUrl = '?sp_export=1';
$reclassifyUrl = '?sp_reclassify=1';
$reportHtmlUrl = sp_admin_report_url($listFilters);
$reportHtml30Url = sp_admin_report_url(\array_merge($listFilters, ['days' => 30, 'active' => true]));
$publicReportUrl = sp_public_report_url($reportToken, $listFilters);
$publicReport30Url = sp_public_report_url($reportToken, \array_merge($listFilters, ['days' => 30, 'active' => true]));
$newTokenUrl = '?sp_new_token=1';
$filterResetUrl = '?';
$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$pu = \parse_url($uri);
if (\is_array($pu) && isset($pu['path'])) {
    $base = (string)$pu['path'];
    $q = [];
    if (isset($pu['query'])) {
        \parse_str((string)$pu['query'], $q);
    }
    unset(
        $q['sp_export'],
        $q['sp_reclassify'],
        $q['sp_new_token'],
        $q['sp_report'],
        $q['sp_f_channel'],
        $q['sp_f_days'],
        $q['sp_f_q'],
        $q['sp_f_from'],
        $q['sp_f_to']
    );
    if ($listFilters['channel'] !== '') {
        $q['sp_f_channel'] = $listFilters['channel'];
    }
    if ($listFilters['days'] > 0) {
        $q['sp_f_days'] = (string)$listFilters['days'];
    }
    if ($listFilters['q'] !== '') {
        $q['sp_f_q'] = $listFilters['q'];
    }
    if ($listFilters['dateFrom'] !== '') {
        $q['sp_f_from'] = $listFilters['dateFrom'];
    }
    if ($listFilters['dateTo'] !== '') {
        $q['sp_f_to'] = $listFilters['dateTo'];
    }
    $qExport = $q + ['sp_export' => '1'];
    $qReclass = $q + ['sp_reclassify' => '1'];
    $qNewToken = $q + ['sp_new_token' => '1'];
    $qFilterReset = $q;
    unset($qFilterReset['sp_f_channel'], $qFilterReset['sp_f_days'], $qFilterReset['sp_f_q'], $qFilterReset['sp_f_from'], $qFilterReset['sp_f_to']);
    $filterResetUrl = $base . '?' . \http_build_query($qFilterReset);
    $exportUrl = $base . '?' . \http_build_query($qExport);
    $reclassifyUrl = $base . '?' . \http_build_query($qReclass);
    $newTokenUrl = $base . '?' . \http_build_query($qNewToken);
    $reportHtmlUrl = sp_admin_report_url($listFilters);
    $reportHtml30Url = sp_admin_report_url(\array_merge($listFilters, ['days' => 30, 'active' => true]));
    $publicReportUrl = sp_public_report_url($reportToken, $listFilters);
    $publicReport30Url = sp_public_report_url($reportToken, \array_merge($listFilters, ['days' => 30, 'active' => true]));
} else {
    $filterResetUrl = '?';
}

?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0">SalesProduct — Kanal-Attribution</h3>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= sp_h($reclassifyUrl) ?>"
               onclick="return confirm('Alle gespeicherten Attribution-Zeilen anhand des Snapshots neu klassifizieren (z.B. payments.amazon.de → echter Channel, googleadservices.com → google_ads)?');">
                Bestehende neu klassifizieren
            </a>
            <a class="btn btn-sm btn-outline-success" href="<?= sp_h($exportUrl) ?>">Letzte Bestellungen als CSV (Excel)</a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($reclassifyResult !== null): ?>
            <?php if (!empty($reclassifyResult['error'])): ?>
                <div class="alert alert-danger">
                    Reklassifizierung fehlgeschlagen nach <?= (int)$reclassifyResult['changed'] ?>/<?= (int)$reclassifyResult['checked'] ?> Zeilen:
                    <code><?= sp_h((string)$reclassifyResult['error']) ?></code>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    Reklassifizierung abgeschlossen — geprüft: <strong><?= (int)$reclassifyResult['checked'] ?></strong>,
                    aktualisiert: <strong><?= (int)$reclassifyResult['changed'] ?></strong>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <p class="text-muted">
            Zuordnung per Cookie <code>womosi_sp_attr_v1</code> (90 Tage). Export: bis zu 5000 Zeilen, Trennzeichen <strong>;</strong> — in Excel „Daten → Aus Text/CSV“ oder Doppelklick (je nach Locale).
        </p>

        <div class="card border-primary mb-4">
            <div class="card-body py-3">
                <h5 class="card-title h6 mb-2">Rapor-Links (teilen / bookmark)</h5>
                <p class="small text-muted mb-2">
                    HTML- und Token-Links übernehmen die <strong>aktuellen Tabellen-Filter</strong> (Kanal, Zeitraum, Datum, Suche).
                    Im geöffneten Rapor können Filter erneut angepasst werden.
                </p>
                <ul class="list-unstyled small mb-2">
                    <li class="mb-2">
                        <strong>HTML-Rapor (Admin):</strong>
                        <a href="<?= sp_h($reportHtmlUrl) ?>" target="_blank" rel="noopener">Mit aktuellen Filtern öffnen</a>
                        · <a href="<?= sp_h($reportHtml30Url) ?>" target="_blank" rel="noopener">+ 30 Tage Preset</a>
                    </li>
                    <li class="mb-2">
                        <strong>CSV (Excel):</strong>
                        <a href="<?= sp_h($exportUrl) ?>">Download (mit Filtern)</a>
                    </li>
                    <li class="mb-2">
                        <strong>Öffentlicher Lese-Link (Token):</strong>
                        <div class="input-group input-group-sm mt-1" style="max-width:720px">
                            <input type="text" class="form-control font-monospace" readonly
                                   value="<?= sp_h($publicReportUrl) ?>" id="sp-public-url-all">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="navigator.clipboard.writeText(document.getElementById('sp-public-url-all').value)">Kopieren</button>
                        </div>
                        <div class="input-group input-group-sm mt-1" style="max-width:720px">
                            <input type="text" class="form-control font-monospace" readonly
                                   value="<?= sp_h($publicReport30Url) ?>" id="sp-public-url-30">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="navigator.clipboard.writeText(document.getElementById('sp-public-url-30').value)">30 Tage kopieren</button>
                        </div>
                        <p class="text-muted mb-0 mt-1">
                            Token nur an vertrauenswürdige Personen weitergeben.
                            <a href="<?= sp_h($newTokenUrl) ?>"
                               onclick="return confirm('Alten Link ungültig machen und neuen Token erzeugen?');">Neuen Token erzeugen</a>
                        </p>
                    </li>
                </ul>
            </div>
        </div>

        <?php
        $pt = $periodChannelStats['totals'];
        $pChannels = $periodChannelStats['byChannel'];
        ?>
        <h4 class="mb-3">Shop-Umsatz nach Kalendermonat</h4>
        <p class="text-muted small mb-2">Alle Bestellungen, Storno ausgenommen. Umsatz = <code>fGesamtsumme</code> nach Bestelldatum.</p>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                <tr><th>Monat</th><th class="text-end">Bestellungen</th><th class="text-end">Umsatz</th></tr>
                </thead>
                <tbody>
                <?php if ($shopMonths === []): ?>
                    <tr><td colspan="3" class="text-muted">Keine Bestellungen.</td></tr>
                <?php else: ?>
                    <?php foreach ($shopMonths as $sm): ?>
                        <tr>
                            <td><?= sp_h((string)$sm['month']) ?></td>
                            <td class="text-end"><?= (int)$sm['orders'] ?></td>
                            <td class="text-end text-nowrap"><?= sp_h(sp_format_money($sm['revenue'], 'EUR')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h4 class="mb-3">Verkauf nach Zeitraum (Kanal)</h4>
        <p class="text-muted small mb-2">
            Stück = Bestellungen · Umsatz = <code>tbestellung.fGesamtsumme</code> (nach Bestelldatum).
            Monat: <strong><?= sp_h($periodChannelStats['monthLabel']) ?></strong>
        </p>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
            <div class="col">
                <div class="card h-100 border-success">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small text-uppercase">Heute</div>
                        <div class="fs-4 fw-bold"><?= (int)$pt['today']['n'] ?></div>
                        <div class="small text-muted">Bestellungen</div>
                        <div class="fs-6 fw-semibold mt-1"><?= sp_h(sp_format_money($pt['today']['r'], 'EUR')) ?></div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 border-primary">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small text-uppercase">Gestern</div>
                        <div class="fs-4 fw-bold"><?= (int)$pt['yesterday']['n'] ?></div>
                        <div class="small text-muted">Bestellungen</div>
                        <div class="fs-6 fw-semibold mt-1"><?= sp_h(sp_format_money($pt['yesterday']['r'], 'EUR')) ?></div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center py-3">
                        <div class="text-muted small text-uppercase">Monat (<?= sp_h($periodChannelStats['monthLabel']) ?>)</div>
                        <div class="fs-4 fw-bold"><?= (int)$pt['month']['n'] ?></div>
                        <div class="small text-muted">Bestellungen</div>
                        <div class="fs-6 fw-semibold mt-1"><?= sp_h(sp_format_money($pt['month']['r'], 'EUR')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered mb-0" style="--bs-table-bg:transparent">
                <thead class="table-light">
                <tr>
                    <th rowspan="2" class="align-middle">Kanal</th>
                    <th colspan="2" class="text-center border-success">Heute</th>
                    <th colspan="2" class="text-center border-primary">Gestern</th>
                    <th colspan="2" class="text-center border-warning">Aktueller Monat</th>
                </tr>
                <tr>
                    <th class="text-center text-success">Stück</th>
                    <th class="text-center text-success">Umsatz</th>
                    <th class="text-center text-primary">Stück</th>
                    <th class="text-center text-primary">Umsatz</th>
                    <th class="text-center text-warning">Stück</th>
                    <th class="text-center text-warning">Umsatz</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($pChannels === []): ?>
                    <tr><td colspan="7" class="text-muted text-center">Keine Verkäufe in diesen Zeiträumen.</td></tr>
                <?php else: ?>
                    <?php foreach ($pChannels as $pc): ?>
                        <tr>
                            <td><code><?= sp_h($pc['channel']) ?></code></td>
                            <td class="text-center"><?= (int)$pc['today']['n'] ?: '—' ?></td>
                            <td class="text-center text-nowrap"><?= $pc['today']['r'] > 0 ? sp_h(sp_format_money($pc['today']['r'], 'EUR')) : '—' ?></td>
                            <td class="text-center"><?= (int)$pc['yesterday']['n'] ?: '—' ?></td>
                            <td class="text-center text-nowrap"><?= $pc['yesterday']['r'] > 0 ? sp_h(sp_format_money($pc['yesterday']['r'], 'EUR')) : '—' ?></td>
                            <td class="text-center"><?= (int)$pc['month']['n'] ?: '—' ?></td>
                            <td class="text-center text-nowrap"><?= $pc['month']['r'] > 0 ? sp_h(sp_format_money($pc['month']['r'], 'EUR')) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if ($pChannels !== []): ?>
                <tfoot class="table-light fw-semibold">
                <tr>
                    <td>Summe</td>
                    <td class="text-center"><?= (int)$pt['today']['n'] ?></td>
                    <td class="text-center text-nowrap"><?= sp_h(sp_format_money($pt['today']['r'], 'EUR')) ?></td>
                    <td class="text-center"><?= (int)$pt['yesterday']['n'] ?></td>
                    <td class="text-center text-nowrap"><?= sp_h(sp_format_money($pt['yesterday']['r'], 'EUR')) ?></td>
                    <td class="text-center"><?= (int)$pt['month']['n'] ?></td>
                    <td class="text-center text-nowrap"><?= sp_h(sp_format_money($pt['month']['r'], 'EUR')) ?></td>
                </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <?php
        $monthLabel = $periodChannelStats['monthLabel'];
        require __DIR__ . '/report_top_products.php';
        ?>

        <div class="row">
            <div class="col-md-6 mb-4">
                <h4>Kanal-Statistik (gesamt)</h4>
                <?php if ($channelStatsAll === []): ?>
                    <p class="text-muted">Noch keine Attribution-Daten.</p>
                <?php else: ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Kanal</th><th>Bestellungen</th><th>Anteil</th></tr></thead>
                        <tbody>
                        <?php foreach ($channelStatsAll as $s):
                            $cnt = (int)($s->nCount ?? 0);
                            $pct = $nTotalOrders > 0 ? \round(100 * $cnt / $nTotalOrders, 1) : 0.0;
                            ?>
                            <tr>
                                <td><code><?= sp_h((string)($s->cChannel ?? '')) ?></code></td>
                                <td><?= $cnt ?></td>
                                <td style="min-width:180px">
                                    <div class="progress" style="height:22px">
                                        <div class="progress-bar" role="progressbar"
                                             style="width: <?= sp_h((string)\min(100, $pct)) ?>%"
                                             aria-valuenow="<?= (int)$pct ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= sp_h((string)$pct) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr><th>Summe</th><th><?= (int)$nTotalOrders ?></th><th>100%</th></tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-4">
                <h4>Kanal-Statistik (letzte 30 Tage)</h4>
                <?php if ($channelStats30d === []): ?>
                    <p class="text-muted">Keine Einträge in den letzten 30 Tagen.</p>
                <?php else: ?>
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Kanal</th><th>Bestellungen</th></tr></thead>
                        <tbody>
                        <?php foreach ($channelStats30d as $s): ?>
                            <tr>
                                <td><code><?= sp_h((string)($s->cChannel ?? '')) ?></code></td>
                                <td><?= (int)($s->nCount ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <h4 class="mt-2">Letzte Bestellungen</h4>
        <?php if ($listQueryNote !== ''): ?>
            <p class="alert alert-warning small mb-2"><?= sp_h($listQueryNote) ?></p>
        <?php endif; ?>

        <form method="get" class="card bg-light border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <?php
                    // JTL-Plugin-Admin-Parameter (kPlugin, kPluginAdminMenu, …) beibehalten
                    foreach ($_GET as $gk => $gv) {
                        if (\str_starts_with((string)$gk, 'sp_f_') || \in_array($gk, ['sp_export', 'sp_reclassify', 'sp_new_token', 'sp_report'], true)) {
                            continue;
                        }
                        if (\is_array($gv)) {
                            continue;
                        }
                        echo '<input type="hidden" name="' . sp_h((string)$gk) . '" value="' . sp_h((string)$gv) . '">';
                    }
                    ?>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Kanal</label>
                        <select name="sp_f_channel" class="form-select form-select-sm">
                            <option value="">— alle —</option>
                            <?php foreach ($filterChannels as $chOpt): ?>
                                <option value="<?= sp_h($chOpt) ?>"<?= $listFilters['channel'] === $chOpt ? ' selected' : '' ?>><?= sp_h($chOpt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Zeitraum</label>
                        <select name="sp_f_days" class="form-select form-select-sm">
                            <?php
                            $dayOpts = [0 => 'Alle', 7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '365 Tage'];
                            foreach ($dayOpts as $dv => $dl):
                                ?>
                                <option value="<?= (int)$dv ?>"<?= $listFilters['days'] === $dv ? ' selected' : '' ?>><?= sp_h($dl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Von (Datum)</label>
                        <input type="date" name="sp_f_from" class="form-control form-control-sm"
                               value="<?= sp_h($listFilters['dateFrom']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Bis (Datum)</label>
                        <input type="date" name="sp_f_to" class="form-control form-control-sm"
                               value="<?= sp_h($listFilters['dateTo']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Suche</label>
                        <input type="search" name="sp_f_q" class="form-control form-control-sm"
                               placeholder="Bestellnr., Kunde, Produkt, Kanal…"
                               value="<?= sp_h($listFilters['q']) ?>">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center mt-2 small">
                    <?php if ($listFilters['active']): ?>
                        <span class="text-muted"><?= (int)$listRowCount ?> Treffer<?= $listRowCount >= 400 ? ' (max. 400 angezeigt)' : '' ?></span>
                        <a href="<?= sp_h($filterResetUrl ?? '?') ?>" class="btn btn-sm btn-outline-secondary">Filter zurücksetzen</a>
                    <?php else: ?>
                        <span class="text-muted">Max. 400 Zeilen · ohne Filter nach neuesten Attributionen</span>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php
        // Hinweis, wenn die Liste leer ist
        $diagHint = '';
        if ($rows === []) {
            if ($diag['tableExists'] === false) {
                $diagHint = 'Die Tabelle twomosi_sales_attribution existiert nicht. Plugin neu aktivieren oder Dashboard erneut aufrufen (sie wird beim Laden idempotent angelegt).';
            } elseif ($diag['attrTotal'] === 0) {
                if (($diag['ordersSinceInstall'] ?? null) === 0) {
                    $diagHint = 'Seit der Plugin-Installation wurden noch keine neuen Bestellungen aufgegeben. Das Plugin füllt die Tabelle nur für neue Bestellungen (Hook HOOK_BESTELLABSCHLUSS_INC_BESTELLUNGINDB_ENDE).';
                } elseif (($diag['ordersSinceInstall'] ?? null) !== null && $diag['ordersSinceInstall'] > 0) {
                    $diagHint = 'Es gibt ' . (int)$diag['ordersSinceInstall'] . ' Bestellung(en) seit der Plugin-Installation, aber keine Attribution-Zeile. Vermutlich wurde der Order-Hook nicht ausgelöst (Plugin deaktiviert beim Bestellabschluss?) — siehe JTL-Log auf "[SalesProduct]"-Einträge.';
                } else {
                    $diagHint = 'Noch keine Attribution-Einträge. Sobald eine neue Bestellung abgeschlossen wird, erscheint sie hier.';
                }
            }
        }
        ?>
        <div class="alert alert-info small mb-3">
            <strong>Diagnose:</strong>
            Bestellungen gesamt: <code><?= $diag['ordersTotal'] !== null ? (int)$diag['ordersTotal'] : '?' ?></code>
            · Attribution-Einträge: <code><?= (int)$diag['attrTotal'] ?></code>
            <?php if ($diag['pluginInstalledAt']): ?>
                · Plugin installiert: <code><?= sp_h((string)$diag['pluginInstalledAt']) ?></code>
                · Bestellungen seit Installation: <code><?= $diag['ordersSinceInstall'] !== null ? (int)$diag['ordersSinceInstall'] : '?' ?></code>
            <?php endif; ?>
            <?php if ($diag['lastOrder']): ?>
                · Letzte Bestellung: <code><?= sp_h((string)$diag['lastOrder']) ?></code>
            <?php endif; ?>
            <?php if ($diag['lastAttr']): ?>
                · Letzte Attribution: <code><?= sp_h((string)$diag['lastAttr']) ?></code>
            <?php endif; ?>
            <?php if ($diagHint !== ''): ?>
                <div class="mt-2"><?= sp_h($diagHint) ?></div>
            <?php endif; ?>
            <?php if ($listQueryError !== '' && $rows === [] && $diag['attrTotal'] > 0): ?>
                <div class="mt-2 text-danger">SQL-Fehler (letzter Versuch): <code><?= sp_h($listQueryError) ?></code></div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                <tr>
                    <th>Bestellnr.</th>
                    <th>Datum</th>
                    <th>Kunde</th>
                    <th>Produkte</th>
                    <th class="text-end">Summe</th>
                    <th>Kanal</th>
                    <th>Label</th>
                    <th>Attribution</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $vor = sp_decrypt_field(isset($r->encVorname) ? (string)$r->encVorname : '');
                    $nach = sp_decrypt_field(isset($r->encNachname) ? (string)$r->encNachname : '');
                    $mail = sp_decrypt_field(isset($r->encMail) ? (string)$r->encMail : '');
                    $cust = \trim($vor . ' ' . $nach);
                    if ($cust === '' && $mail !== '') {
                        $cust = $mail;
                    }
                    if ($cust === '') {
                        $cust = '—';
                    }
                    $prod = (string)($r->products_line ?? '');
                    if ($prod === '') {
                        $prod = '—';
                    }
                    $sourceInfo = sp_extract_source_info(isset($r->cAttributionJson) ? (string)$r->cAttributionJson : null);
                    $sourceDetail = $sourceInfo['detail'];
                    $sourceFromFirst = $sourceInfo['fromFirst'];
                    $sourceHost = sp_url_host($sourceDetail);
                    ?>
                    <tr>
                        <td><?= sp_h((string)($r->cBestellNr ?? '')) ?></td>
                        <td><?= sp_h((string)($r->dBestellt ?? '')) ?></td>
                        <td><?= sp_h($cust) ?></td>
                        <td style="max-width:420px;white-space:normal"><?= sp_h($prod) ?></td>
                        <td class="text-end text-nowrap">
                            <?php $sumVal = $r->orderSum ?? null; ?>
                            <?php if ($sumVal !== null && $sumVal !== ''): ?>
                                <?= sp_h(sp_format_money($sumVal, isset($r->orderCurrency) ? (string)$r->orderCurrency : null)) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= sp_h((string)($r->cChannel ?? '')) ?></code></td>
                        <td style="max-width:360px;white-space:normal">
                            <?= sp_h((string)($r->cChannelLabel ?? '')) ?>
                            <?php if ($sourceDetail !== ''): ?>
                                <div class="small text-muted" style="word-break:break-all">
                                    <?php if ($sourceHost !== '' && \filter_var($sourceDetail, \FILTER_VALIDATE_URL)): ?>
                                        ↳ <a href="<?= sp_h($sourceDetail) ?>" target="_blank" rel="noopener noreferrer" title="<?= sp_h($sourceDetail) ?>"><?= sp_h($sourceHost) ?></a>
                                    <?php else: ?>
                                        ↳ <span title="<?= sp_h($sourceDetail) ?>"><?= sp_h($sourceHost !== '' ? $sourceHost : $sourceDetail) ?></span>
                                    <?php endif; ?>
                                    <?php if ($sourceFromFirst): ?>
                                        <span class="badge bg-secondary ms-1" title="Last-Touch war ein Payment-Redirect – zeige First-Touch.">first-touch</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= sp_h((string)($r->dCreated ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="8">
                        <?php if ($listFilters['active']): ?>
                            Keine Bestellungen für die gewählten Filter.
                        <?php elseif ($nTotalOrders > 0): ?>
                            Keine Zeilen aus der Listenabfrage — bitte DB-Log prüfen (Join <code>tbestellung</code>/<code>twarenkorbpos</code>).
                        <?php else: ?>
                            Keine Zeilen in <code>twomosi_sales_attribution</code>.
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>
                </tbody>
                <?php
                $sumByCurrency = [];
                $countWithSum = 0;
                foreach ($rows as $r) {
                    $sv = $r->orderSum ?? null;
                    if ($sv === null || $sv === '') {
                        continue;
                    }
                    $cur = \strtoupper(\trim((string)($r->orderCurrency ?? '')));
                    if ($cur === '') {
                        $cur = 'EUR';
                    }
                    $sumByCurrency[$cur] = ($sumByCurrency[$cur] ?? 0.0) + (float)$sv;
                    $countWithSum++;
                }
                ?>
                <?php if ($countWithSum > 0): ?>
                    <tfoot>
                    <tr class="table-light">
                        <th colspan="4" class="text-end">Summe (sichtbare Zeilen):</th>
                        <th class="text-end text-nowrap">
                            <?php foreach ($sumByCurrency as $cur => $sum): ?>
                                <div><?= sp_h(sp_format_money($sum, $cur)) ?></div>
                            <?php endforeach; ?>
                        </th>
                        <th colspan="3"></th>
                    </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
