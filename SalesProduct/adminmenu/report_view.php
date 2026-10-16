<?php

declare(strict_types=1);

/**
 * Standalone HTML-Rapor (Admin-Link oder öffentlicher Token-Link).
 * Erwartet: report_lib.php + sp_helpers.php bereits geladen.
 *
 * @var bool $spReportPublic
 */

use JTL\Shop;

$spReportPublic = $spReportPublic ?? false;
sp_report_require_auth();
$reportToken = $reportToken ?? \trim((string)($_GET['token'] ?? ''));
$reportFilters = sp_parse_list_filters();
$filterChannels = sp_get_filter_channels(Shop::Container()->getDB());

$data = sp_load_report_data($spReportPublic ? 500 : 400, $reportFilters);
$channelStatsAll = $data['channelStatsAll'];
$channelStats30d = $data['channelStats30d'];
$channelRevenue = $data['channelRevenue'];
$rows = $data['rows'];
$nTotalOrders = $data['nTotalOrders'];
$generatedAt = $data['generatedAt'];
$shopName = 'SalesProduct Kanal-Rapor';
$listRowCount = \count($rows);

$dbReport = Shop::Container()->getDB();
$shopMonths = sp_load_shop_month_revenue($dbReport, 18);
$periodChannelStats = sp_load_period_channel_stats($dbReport);
$periodTotals = $periodChannelStats['totals'];
$periodChannels = $periodChannelStats['byChannel'];
$topProductsMonth = sp_load_top_products($dbReport, 3, 'month');
$topProductsAll = sp_load_top_products($dbReport, 3, 'all');
$monthNames = [
    1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
    7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
];
$monthLabel = ($monthNames[(int)\date('n')] ?? \date('m')) . ' ' . \date('Y');

$sumByCurrency = [];
foreach ($rows as $r) {
    $sv = $r->orderSum ?? null;
    if ($sv === null || $sv === '') {
        continue;
    }
    $cur = \strtoupper(\trim((string)($r->orderCurrency ?? 'EUR')));
    if ($cur === '') {
        $cur = 'EUR';
    }
    $sumByCurrency[$cur] = ($sumByCurrency[$cur] ?? 0.0) + (float)$sv;
}

if (!\headers_sent()) {
    \header('Content-Type: text/html; charset=UTF-8');
    \header('Cache-Control: no-store, no-cache, must-revalidate');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= sp_h($shopName) ?></title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --border: #dde3ea;
            --text: #1f2933;
            --muted: #667085;
            --blue: #0d6efd;
            --green: #198754;
            --amber: #b7791f;
        }
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--text);
        }
        .page { max-width: 1480px; margin: 0 auto; padding: 24px; }
        .report-header {
            background: linear-gradient(135deg, #0d6efd, #334155);
            color: #fff;
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.14);
        }
        h1 { font-size: 1.45rem; margin: 0 0 0.35rem; }
        h2 { font-size: 1.12rem; margin: 1.45rem 0 0.75rem; }
        .meta { color: rgba(255, 255, 255, 0.82); font-size: 0.92rem; margin: 0; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 1.5rem; font-size: 0.88rem; background: #fff; }
        th, td { border: 1px solid var(--border); padding: 7px 9px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; color: #344054; }
        tbody tr:nth-child(even) { background: #fbfcfe; }
        .num { text-align: right; white-space: nowrap; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .card h2 { font-size: 1rem; margin: 0 0 10px; }
        .metric { text-align: center; }
        .metric .label { color: var(--muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .metric .value { font-size: 1.8rem; font-weight: 750; margin-top: 4px; }
        .metric .sub { color: var(--muted); font-size: 0.83rem; margin-top: 2px; }
        .metric .money { font-weight: 700; margin-top: 6px; }
        .metric.today { border-top: 4px solid var(--green); }
        .metric.yesterday { border-top: 4px solid var(--blue); }
        .metric.month { border-top: 4px solid var(--amber); }
        .section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 1rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .section h2:first-child { margin-top: 0; }
        .table-wrap { overflow-x: auto; }
        code { background: #eef2f7; padding: 1px 5px; border-radius: 4px; font-size: 0.85em; }
        .muted { color: var(--muted); font-size: 0.82rem; }
        .filter-box { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 14px; margin-bottom: 1.25rem; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; align-items: end; }
        .filter-grid label { display: flex; flex-direction: column; gap: 4px; font-size: 0.8rem; }
        .filter-grid label span { color: var(--muted); font-weight: 600; }
        .filter-grid select, .filter-grid input[type="date"], .filter-grid input[type="search"] {
            padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.88rem;
        }
        .filter-search { grid-column: span 2; min-width: 200px; }
        .filter-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .filter-actions button { padding: 7px 14px; background: #0d6efd; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .filter-actions .btn-reset { font-size: 0.85rem; color: #555; }
        .top-products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 0.5rem; }
        .top-products-grid h3 { font-size: 0.95rem; margin: 0 0 8px; }
        @media print {
            body { background: #fff; }
            .page { padding: 12px; max-width: none; }
            .report-header, .card, .section { box-shadow: none; }
            a { color: inherit; text-decoration: none; }
            .filter-box { display: none; }
        }
    </style>
</head>
<body>
<div class="page">
<div class="report-header">
    <h1><?= sp_h($shopName) ?></h1>
    <p class="meta">
        Erstellt: <strong><?= sp_h($generatedAt) ?></strong>
        <?php if ($reportFilters['active']): ?> · <strong>Filter aktiv</strong> (<?= (int)$listRowCount ?> Zeilen)<?php endif; ?>
        <?php if ($spReportPublic): ?> · <em>Öffentlicher Lese-Link (Token)</em><?php endif; ?>
    </p>
</div>

<?php require __DIR__ . '/report_filter_form.php'; ?>

<h2>Shop-Umsatz nach Kalendermonat</h2>
<p class="muted">Alle Bestellungen aus <code>tbestellung</code>, Storno ausgenommen. Umsatz = <code>fGesamtsumme</code> nach Bestelldatum.</p>
<div class="section">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Monat</th><th class="num">Bestellungen</th><th class="num">Umsatz</th></tr></thead>
            <tbody>
            <?php if ($shopMonths === []): ?>
                <tr><td colspan="3" class="muted">Keine Bestellungen.</td></tr>
            <?php else: ?>
                <?php foreach ($shopMonths as $sm): ?>
                    <tr>
                        <td><?= sp_h((string)$sm['month']) ?></td>
                        <td class="num"><?= (int)$sm['orders'] ?></td>
                        <td class="num"><?= sp_h(sp_format_money($sm['revenue'], 'EUR')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<h2>Verkauf nach Zeitraum (Kanal)</h2>
<p class="muted">
    Stück = Bestellungen · Umsatz = <code>tbestellung.fGesamtsumme</code> (nach Bestelldatum).
    Monat: <strong><?= sp_h($periodChannelStats['monthLabel']) ?></strong>
</p>
<div class="summary-grid">
    <div class="card metric today">
        <div class="label">Heute</div>
        <div class="value"><?= (int)$periodTotals['today']['n'] ?></div>
        <div class="sub">Bestellungen</div>
        <div class="money"><?= sp_h(sp_format_money($periodTotals['today']['r'], 'EUR')) ?></div>
    </div>
    <div class="card metric yesterday">
        <div class="label">Gestern</div>
        <div class="value"><?= (int)$periodTotals['yesterday']['n'] ?></div>
        <div class="sub">Bestellungen</div>
        <div class="money"><?= sp_h(sp_format_money($periodTotals['yesterday']['r'], 'EUR')) ?></div>
    </div>
    <div class="card metric month">
        <div class="label">Monat (<?= sp_h($periodChannelStats['monthLabel']) ?>)</div>
        <div class="value"><?= (int)$periodTotals['month']['n'] ?></div>
        <div class="sub">Bestellungen</div>
        <div class="money"><?= sp_h(sp_format_money($periodTotals['month']['r'], 'EUR')) ?></div>
    </div>
</div>
<div class="section">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th rowspan="2">Kanal</th>
                <th colspan="2" class="num">Heute</th>
                <th colspan="2" class="num">Gestern</th>
                <th colspan="2" class="num">Aktueller Monat</th>
            </tr>
            <tr>
                <th class="num">Stück</th>
                <th class="num">Umsatz</th>
                <th class="num">Stück</th>
                <th class="num">Umsatz</th>
                <th class="num">Stück</th>
                <th class="num">Umsatz</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($periodChannels === []): ?>
                <tr><td colspan="7" class="muted">Keine Verkäufe in diesen Zeiträumen.</td></tr>
            <?php else: ?>
                <?php foreach ($periodChannels as $pc): ?>
                    <tr>
                        <td><code><?= sp_h($pc['channel']) ?></code></td>
                        <td class="num"><?= (int)$pc['today']['n'] ?: '—' ?></td>
                        <td class="num"><?= $pc['today']['r'] > 0 ? sp_h(sp_format_money($pc['today']['r'], 'EUR')) : '—' ?></td>
                        <td class="num"><?= (int)$pc['yesterday']['n'] ?: '—' ?></td>
                        <td class="num"><?= $pc['yesterday']['r'] > 0 ? sp_h(sp_format_money($pc['yesterday']['r'], 'EUR')) : '—' ?></td>
                        <td class="num"><?= (int)$pc['month']['n'] ?: '—' ?></td>
                        <td class="num"><?= $pc['month']['r'] > 0 ? sp_h(sp_format_money($pc['month']['r'], 'EUR')) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if ($periodChannels !== []): ?>
            <tfoot>
            <tr>
                <th>Summe</th>
                <th class="num"><?= (int)$periodTotals['today']['n'] ?></th>
                <th class="num"><?= sp_h(sp_format_money($periodTotals['today']['r'], 'EUR')) ?></th>
                <th class="num"><?= (int)$periodTotals['yesterday']['n'] ?></th>
                <th class="num"><?= sp_h(sp_format_money($periodTotals['yesterday']['r'], 'EUR')) ?></th>
                <th class="num"><?= (int)$periodTotals['month']['n'] ?></th>
                <th class="num"><?= sp_h(sp_format_money($periodTotals['month']['r'], 'EUR')) ?></th>
            </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php
$topProductsPlain = true;
require __DIR__ . '/report_top_products.php';
?>

<div class="grid">
    <div class="card">
        <h2>Kanal-Statistik (gesamt)</h2>
        <?php if ($channelStatsAll === []): ?>
            <p class="muted">Keine Daten.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Kanal</th><th class="num">Bestellungen</th><th class="num">Umsatz (EUR*)</th></tr></thead>
                <tbody>
                <?php foreach ($channelStatsAll as $s):
                    $ch = (string)($s->cChannel ?? '');
                    $cnt = (int)($s->nCount ?? 0);
                    $rev = $channelRevenue[$ch]['revenue'] ?? 0.0;
                    ?>
                    <tr>
                        <td><code><?= sp_h($ch) ?></code></td>
                        <td class="num"><?= $cnt ?></td>
                        <td class="num"><?= sp_h(sp_format_money($rev, 'EUR')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr><th>Summe</th><th class="num"><?= (int)$nTotalOrders ?></th><th></th></tr>
                </tfoot>
            </table>
            <p class="muted">* Summe aus <code>tbestellung.fGesamtsumme</code> (Währungen nicht umgerechnet).</p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Kanal-Statistik (30 Tage)</h2>
        <?php if ($channelStats30d === []): ?>
            <p class="muted">Keine Einträge.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Kanal</th><th class="num">Bestellungen</th></tr></thead>
                <tbody>
                <?php foreach ($channelStats30d as $s): ?>
                    <tr>
                        <td><code><?= sp_h((string)($s->cChannel ?? '')) ?></code></td>
                        <td class="num"><?= (int)($s->nCount ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="section">
<h2>Letzte Bestellungen</h2>
<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th>Bestellnr.</th>
        <th>Datum</th>
        <?php if (!$spReportPublic): ?><th>Kunde</th><?php endif; ?>
        <th>Produkte</th>
        <th class="num">Summe</th>
        <th>Kanal</th>
        <th>Label / Quelle</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $cust = '—';
        if (!$spReportPublic) {
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
        }
        $prod = (string)($r->products_line ?? '');
        if ($prod === '') {
            $prod = '—';
        }
        $sourceInfo = sp_extract_source_info(isset($r->cAttributionJson) ? (string)$r->cAttributionJson : null);
        $sourceDetail = $sourceInfo['detail'];
        $sourceHost = sp_url_host($sourceDetail);
        ?>
        <tr>
            <td><?= sp_h((string)($r->cBestellNr ?? '')) ?></td>
            <td><?= sp_h((string)($r->dBestellt ?? '')) ?></td>
            <?php if (!$spReportPublic): ?><td><?= sp_h($cust) ?></td><?php endif; ?>
            <td><?= sp_h($prod) ?></td>
            <td class="num">
                <?php $sumVal = $r->orderSum ?? null; ?>
                <?= ($sumVal !== null && $sumVal !== '')
                    ? sp_h(sp_format_money($sumVal, isset($r->orderCurrency) ? (string)$r->orderCurrency : null))
                    : '—' ?>
            </td>
            <td><code><?= sp_h((string)($r->cChannel ?? '')) ?></code></td>
            <td>
                <?= sp_h((string)($r->cChannelLabel ?? '')) ?>
                <?php if ($sourceDetail !== ''): ?>
                    <div class="muted">↳ <?= sp_h($sourceHost !== '' ? $sourceHost : $sourceDetail) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
        <tr><td colspan="<?= $spReportPublic ? 6 : 7 ?>">Keine Zeilen.</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if ($sumByCurrency !== []): ?>
        <tfoot>
        <tr>
            <th colspan="<?= $spReportPublic ? 3 : 4 ?>" class="num">Summe (sichtbar):</th>
            <th class="num">
                <?php foreach ($sumByCurrency as $cur => $sum): ?>
                    <div><?= sp_h(sp_format_money($sum, $cur)) ?></div>
                <?php endforeach; ?>
            </th>
            <th colspan="2"></th>
        </tr>
        </tfoot>
    <?php endif; ?>
</table>
</div>
</div>
</div>
</body>
</html>
