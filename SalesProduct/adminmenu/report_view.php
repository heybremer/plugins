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
if (!($spReportPublic && sp_report_token_nopw())) {
    sp_report_require_auth();
}
$reportToken = $reportToken ?? \trim((string)($_GET['token'] ?? ''));
$reportFilters = sp_parse_list_filters();
$dbReport = Shop::Container()->getDB();
$filterChannels = sp_get_filter_channels($dbReport);

if (!empty($_GET['sp_csv'])) {
    sp_report_csv($dbReport, $reportFilters, $spReportPublic);
}

require_once __DIR__ . '/sp_insights.php';

$data = sp_load_report_data(100, $reportFilters, $spReportPublic);
$rows = $data['rows'];
$generatedAt = $data['generatedAt'];
$shopName = 'SalesProduct Kanal-Rapor';
$scopeLabel = sp_scope_label($reportFilters);

$kpi = sp_load_kpi_compare($dbReport, $reportFilters);
$shopMonths = sp_load_shop_month_revenue($dbReport, 18);
$daily = sp_load_daily_revenue($dbReport, $reportFilters, 30);
$periodChannelStats = sp_load_period_channel_stats($dbReport, $reportFilters);
$periodTotals = $periodChannelStats['totals'];
$periodChannels = $periodChannelStats['byChannel'];
$channelTable = sp_load_channel_table($dbReport, $reportFilters);
$topProductsLimit = 10;
$topProductsBlocks = [
    ['title' => 'Filterzeitraum', 'sub' => $scopeLabel, 'items' => sp_load_top_products($dbReport, $topProductsLimit, 'scope', $reportFilters)],
    ['title' => 'Aktueller Monat', 'sub' => $periodChannelStats['monthLabel'], 'items' => sp_load_top_products($dbReport, $topProductsLimit, 'month', $reportFilters)],
];
$showPwWarning = !$spReportPublic && sp_report_password_is_default();

$sumByCurrency = [];
foreach ($rows as $r) {
    $sv = $r->orderSum ?? null;
    if ($sv === null || $sv === '' || (int)($r->cStatus ?? 0) < 0) {
        continue;
    }
    $cur = \strtoupper(\trim((string)($r->orderCurrency ?? 'EUR')));
    $sumByCurrency[$cur !== '' ? $cur : 'EUR'] = ($sumByCurrency[$cur !== '' ? $cur : 'EUR'] ?? 0.0) + (float)$sv;
}

$fmtDelta = static function (float $cur, float $prev): string {
    if ($prev <= 0.0) {
        return $cur > 0 ? '<span class="delta up">neu</span>' : '<span class="delta">—</span>';
    }
    $pct = ($cur - $prev) * 100 / $prev;
    $cls = $pct > 0.5 ? 'up' : ($pct < -0.5 ? 'down' : '');

    return '<span class="delta ' . $cls . '">' . ($pct > 0 ? '+' : '') . \number_format($pct, 1, ',', '.') . ' %</span>';
};
$money = static fn (float $v): string => sp_format_money($v, 'EUR');

$pageUrl = static function (int $page) use ($spReportPublic, $reportToken, $reportFilters): string {
    $base = $spReportPublic ? sp_public_report_url($reportToken, $reportFilters) : sp_admin_report_url($reportFilters);

    return $base . '&sp_p=' . $page;
};

$dailyBars = [];
foreach ($daily as $d) {
    $dailyBars[] = [
        'label' => \date('d.m', (int)\strtotime($d['day'])),
        'value' => $d['f'],
        'title' => $d['day'] . ': ' . $d['n'] . ' Best. · ' . $money($d['f']),
    ];
}
$monthBars = [];
foreach (\array_reverse($shopMonths) as $sm) {
    $monthBars[] = [
        'label' => \substr((string)$sm['month'], 2),
        'value' => $sm['revenue'],
        'title' => $sm['month'] . ': ' . $sm['orders'] . ' Best. · ' . $money($sm['revenue']),
    ];
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
            --red: #c0392b;
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
        .warn { background: #fff4e5; border: 1px solid #f5c27a; color: #7a4b00; border-radius: 10px; padding: 10px 14px; margin-bottom: 1rem; font-size: 0.9rem; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 1rem; font-size: 0.88rem; background: #fff; }
        th, td { border: 1px solid var(--border); padding: 7px 9px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; color: #344054; }
        tbody tr:nth-child(even) { background: #fbfcfe; }
        tr.is-storno td { color: #98a2b3; text-decoration: line-through; text-decoration-color: rgba(192, 57, 43, 0.5); }
        tr.is-storno td.keep { text-decoration: none; }
        .num { text-align: right; white-space: nowrap; }
        .text-center { text-align: center; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            overflow-x: auto;
        }
        .card h2 { font-size: 1rem; margin: 0 0 10px; }
        .metric .label { color: var(--muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .metric .value { font-size: 1.7rem; font-weight: 750; margin-top: 4px; }
        .metric .money { font-weight: 700; }
        .metric .sub { color: var(--muted); font-size: 0.83rem; margin-top: 2px; }
        .metric .cmp { margin-top: 8px; border-top: 1px dashed var(--border); padding-top: 6px; font-size: 0.8rem; color: var(--muted); }
        .metric .cmp div { display: flex; justify-content: space-between; gap: 8px; }
        .metric.today { border-top: 4px solid var(--green); }
        .metric.yesterday { border-top: 4px solid var(--blue); }
        .metric.month { border-top: 4px solid var(--amber); }
        .metric.cover { border-top: 4px solid #6f42c1; }
        .delta { font-weight: 700; white-space: nowrap; }
        .delta.up { color: var(--green); }
        .delta.down { color: var(--red); }
        .bar { background: #eef2f7; border-radius: 4px; height: 8px; min-width: 60px; }
        .bar i { display: block; height: 8px; border-radius: 4px; background: var(--blue); }
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
        .badge { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .st-storno { background: #fde2e1; color: var(--red); }
        .st-open { background: #eef2f7; color: #475467; }
        .st-paid { background: #e3f4ea; color: var(--green); }
        .st-shipped { background: #e1ecff; color: var(--blue); }
        .chart { width: 100%; height: 160px; display: block; }
        .chart rect { fill: var(--blue); opacity: 0.85; }
        .chart rect:hover { opacity: 1; }
        .chart text { font-size: 11px; fill: var(--muted); }
        .pager { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 8px; font-size: 0.88rem; }
        .pager a, .pager span { padding: 4px 10px; border: 1px solid var(--border); border-radius: 6px; background: #fff; color: var(--text); text-decoration: none; }
        .pager .cur { background: var(--blue); color: #fff; border-color: var(--blue); }
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
        .filter-actions button.btn-csv { background: var(--green); }
        .filter-actions .btn-reset { font-size: 0.85rem; color: #555; }
        .top-products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 1rem; margin-bottom: 0.5rem; }
        .top-products-grid h3 { font-size: 0.95rem; margin: 0 0 8px; }
        @media print {
            body { background: #fff; }
            .page { padding: 12px; max-width: none; }
            .report-header, .card, .section { box-shadow: none; }
            a { color: inherit; text-decoration: none; }
            .filter-box, .pager { display: none; }
        }
    </style>
</head>
<body>
<div class="page">
<div class="report-header">
    <h1><?= sp_h($shopName) ?></h1>
    <p class="meta">
        Erstellt: <strong><?= sp_h($generatedAt) ?></strong>
        · Zeitraum: <strong><?= sp_h($scopeLabel) ?></strong>
        <?php if ($spReportPublic): ?> · <em>Öffentlicher Lese-Link (Token)</em><?php endif; ?>
    </p>
</div>

<?php if ($showPwWarning): ?>
    <div class="warn">Der Rapor ist noch mit dem <strong>Standard-Passwort</strong> geschützt. Bitte im Admin-Dashboard (SalesProduct → Rapor-Links) ein eigenes Passwort setzen.</div>
<?php endif; ?>

<?php require __DIR__ . '/report_filter_form.php'; ?>

<h2>Kennzahlen<?= $reportFilters['channel'] !== '' ? ' · Kanal ' . sp_h(sp_channel_display($reportFilters['channel'])) : '' ?></h2>
<div class="summary-grid">
    <?php foreach (['today' => 'today', 'yesterday' => 'yesterday', 'month' => 'month'] as $key => $cls):
        $t = $kpi[$key]; ?>
        <div class="card metric <?= $cls ?>">
            <div class="label"><?= sp_h($t['label']) ?></div>
            <div class="value"><?= (int)$t['n'] ?> <span class="sub">Best.</span></div>
            <div class="money"><?= sp_h($money($t['f'])) ?></div>
            <div class="sub">Ø Bestellwert <?= sp_h($money($t['aov'])) ?></div>
            <div class="cmp">
                <?php foreach ($t['cmp'] as $c): ?>
                    <div><span><?= sp_h($c['label']) ?>: <?= (int)$c['n'] ?> · <?= sp_h($money($c['f'])) ?></span><?= $fmtDelta($t['f'], $c['f']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php $mo = $kpi['month']; $cover = $mo['n'] > 0 ? $mo['attr'] * 100 / $mo['n'] : 0.0; ?>
    <div class="card metric cover">
        <div class="label">Attribution-Abdeckung (Monat)</div>
        <div class="value"><?= sp_h(\number_format($cover, 1, ',', '.')) ?> %</div>
        <div class="sub"><?= (int)$mo['attr'] ?> von <?= (int)$mo['n'] ?> Bestellungen mit Kanal</div>
        <div class="bar" style="margin-top:10px"><i style="width:<?= \min(100, \round($cover)) ?>%"></i></div>
        <div class="cmp">Ohne Kanal = Bestellung ohne SalesProduct-Cookie (z. B. Altbestand, Telefon, Cookie abgelehnt).</div>
    </div>
</div>

<div class="grid">
    <div class="card">
        <h2>Umsatz pro Tag <span class="muted">(30 Tage<?= $reportFilters['channel'] !== '' ? ', Kanalfilter' : '' ?>)</span></h2>
        <?= sp_render_bar_chart($dailyBars) ?>
    </div>
    <div class="card">
        <h2>Shop-Umsatz pro Monat <span class="muted">(18 Monate, alle Kanäle)</span></h2>
        <?= sp_render_bar_chart($monthBars) ?>
    </div>
</div>

<h2>Umsatz nach Kanal <span class="muted">(<?= sp_h($scopeLabel) ?>)</span></h2>
<div class="section">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Kanal</th>
                <th class="num">Bestellungen</th>
                <th class="num">Umsatz</th>
                <th class="num">Ø Bestellwert</th>
                <th>Anteil Umsatz</th>
                <th class="num">Storno</th>
                <th class="num">Storno-Wert</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($channelTable['rows'] === []): ?>
                <tr><td colspan="7" class="muted">Keine Bestellungen im Zeitraum.</td></tr>
            <?php endif; ?>
            <?php foreach ($channelTable['rows'] as $c): ?>
                <tr>
                    <td><code><?= sp_h(sp_channel_display($c['channel'])) ?></code></td>
                    <td class="num"><?= (int)$c['n'] ?></td>
                    <td class="num"><?= sp_h($money($c['f'])) ?></td>
                    <td class="num"><?= $c['n'] > 0 ? sp_h($money($c['aov'])) : '—' ?></td>
                    <td>
                        <div class="bar"><i style="width:<?= \min(100, \round($c['share'])) ?>%"></i></div>
                        <span class="muted"><?= sp_h(\number_format($c['share'], 1, ',', '.')) ?> %</span>
                    </td>
                    <td class="num"><?= $c['storno'] > 0 ? (int)$c['storno'] : '—' ?></td>
                    <td class="num"><?= $c['storno'] > 0 ? sp_h($money($c['stornoF'])) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php $ct = $channelTable['total']; ?>
            <tfoot>
            <tr>
                <th>Summe</th>
                <th class="num"><?= (int)$ct['n'] ?></th>
                <th class="num"><?= sp_h($money($ct['f'])) ?></th>
                <th class="num"><?= $ct['n'] > 0 ? sp_h($money($ct['aov'])) : '—' ?></th>
                <th>100 %</th>
                <th class="num"><?= (int)$ct['storno'] ?></th>
                <th class="num"><?= sp_h($money($ct['stornoF'])) ?></th>
            </tr>
            </tfoot>
        </table>
        <p class="muted">Umsatz = <code>tbestellung.fGesamtsumme</code> (brutto inkl. Versand), ohne Storno. Storno-Spalten separat.</p>
    </div>
</div>

<h2>Verkauf nach Zeitraum (Kanal)</h2>
<div class="section">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th rowspan="2">Kanal</th>
                <th colspan="2" class="num">Heute</th>
                <th colspan="2" class="num">Gestern</th>
                <th colspan="2" class="num"><?= sp_h($periodChannelStats['monthLabel']) ?></th>
            </tr>
            <tr>
                <th class="num">Best.</th><th class="num">Umsatz</th>
                <th class="num">Best.</th><th class="num">Umsatz</th>
                <th class="num">Best.</th><th class="num">Umsatz</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($periodChannels === []): ?>
                <tr><td colspan="7" class="muted">Keine Verkäufe in diesen Zeiträumen.</td></tr>
            <?php else: ?>
                <?php foreach ($periodChannels as $pc): ?>
                    <tr>
                        <td><code><?= sp_h(sp_channel_display($pc['channel'])) ?></code></td>
                        <td class="num"><?= (int)$pc['today']['n'] ?: '—' ?></td>
                        <td class="num"><?= $pc['today']['r'] > 0 ? sp_h($money($pc['today']['r'])) : '—' ?></td>
                        <td class="num"><?= (int)$pc['yesterday']['n'] ?: '—' ?></td>
                        <td class="num"><?= $pc['yesterday']['r'] > 0 ? sp_h($money($pc['yesterday']['r'])) : '—' ?></td>
                        <td class="num"><?= (int)$pc['month']['n'] ?: '—' ?></td>
                        <td class="num"><?= $pc['month']['r'] > 0 ? sp_h($money($pc['month']['r'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if ($periodChannels !== []): ?>
            <tfoot>
            <tr>
                <th>Summe</th>
                <th class="num"><?= (int)$periodTotals['today']['n'] ?></th>
                <th class="num"><?= sp_h($money($periodTotals['today']['r'])) ?></th>
                <th class="num"><?= (int)$periodTotals['yesterday']['n'] ?></th>
                <th class="num"><?= sp_h($money($periodTotals['yesterday']['r'])) ?></th>
                <th class="num"><?= (int)$periodTotals['month']['n'] ?></th>
                <th class="num"><?= sp_h($money($periodTotals['month']['r'])) ?></th>
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

<h2>Shop-Umsatz nach Kalendermonat</h2>
<div class="section">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Monat</th>
                <th class="num">Bestellungen</th>
                <th class="num">Umsatz</th>
                <th class="num">Ø Bestellwert</th>
                <th class="num">Mit Kanal</th>
                <th class="num">Umsatz mit Kanal</th>
                <th class="num">Ohne Kanal</th>
                <th class="num">Abdeckung</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($shopMonths === []): ?>
                <tr><td colspan="8" class="muted">Keine Bestellungen.</td></tr>
            <?php endif; ?>
            <?php foreach ($shopMonths as $sm):
                $cov = $sm['orders'] > 0 ? $sm['attrOrders'] * 100 / $sm['orders'] : 0.0; ?>
                <tr>
                    <td><?= sp_h((string)$sm['month']) ?></td>
                    <td class="num"><?= (int)$sm['orders'] ?></td>
                    <td class="num"><?= sp_h($money($sm['revenue'])) ?></td>
                    <td class="num"><?= $sm['orders'] > 0 ? sp_h($money($sm['revenue'] / $sm['orders'])) : '—' ?></td>
                    <td class="num"><?= (int)$sm['attrOrders'] ?></td>
                    <td class="num"><?= sp_h($money($sm['attrRevenue'])) ?></td>
                    <td class="num"><?= (int)$sm['orders'] - (int)$sm['attrOrders'] ?></td>
                    <td class="num"><?= sp_h(\number_format($cov, 1, ',', '.')) ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted">Alle Bestellungen ohne Storno, nach Bestelldatum. „Mit Kanal“ = SalesProduct-Attribution vorhanden.</p>
    </div>
</div>

<?php sp_render_insights_plain($dbReport); ?>

<div class="section">
<h2>Bestellungen <span class="muted">(<?= (int)$data['total'] ?> im Filter · Seite <?= (int)$data['page'] ?>/<?= (int)$data['pages'] ?>)</span></h2>
<?php if ($data['searchNote'] !== ''): ?><p class="muted"><?= sp_h($data['searchNote']) ?></p><?php endif; ?>
<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th>Bestellnr.</th>
        <th>Datum</th>
        <th>Status</th>
        <?php if (!$spReportPublic): ?><th>Kunde</th><?php endif; ?>
        <th>Produkte</th>
        <th class="num">Summe</th>
        <th>Zahlung / Kupon</th>
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
        $sourceInfo = sp_extract_source_info(isset($r->cAttributionJson) ? (string)$r->cAttributionJson : null);
        $sourceDetail = $sourceInfo['detail'];
        $sourceHost = sp_url_host($sourceDetail);
        [$stLabel, $stClass] = sp_order_status_label($r->cStatus ?? 0);
        $isStorno = (int)($r->cStatus ?? 0) < 0;
        $custType = sp_customer_type($r);
        $coupon = (string)($r->coupon_line ?? '');
        $couponVal = $r->coupon_value ?? null;
        ?>
        <tr class="<?= $isStorno ? 'is-storno' : '' ?>">
            <td><?= sp_h((string)($r->cBestellNr ?? '')) ?></td>
            <td><?= sp_h((string)($r->dBestellt ?? '')) ?></td>
            <td class="keep"><span class="badge <?= sp_h($stClass) ?>"><?= sp_h($stLabel) ?></span></td>
            <?php if (!$spReportPublic): ?>
                <td><?= sp_h($cust) ?><?php if ($custType !== ''): ?><div class="muted"><?= sp_h($custType) ?></div><?php endif; ?></td>
            <?php endif; ?>
            <td><?= sp_h($prod !== '' ? $prod : '—') ?></td>
            <td class="num">
                <?php $sumVal = $r->orderSum ?? null; ?>
                <?= ($sumVal !== null && $sumVal !== '')
                    ? sp_h(sp_format_money($sumVal, isset($r->orderCurrency) ? (string)$r->orderCurrency : null))
                    : '—' ?>
            </td>
            <td>
                <?= sp_h((string)($r->cZahlungsartName ?? '') !== '' ? (string)$r->cZahlungsartName : '—') ?>
                <?php if ($coupon !== ''): ?>
                    <div class="muted">Kupon: <?= sp_h($coupon) ?><?= ($couponVal !== null && $couponVal !== '') ? ' (' . sp_h(sp_format_money((float)$couponVal, 'EUR')) . ')' : '' ?></div>
                <?php endif; ?>
                <?php if ($spReportPublic && $custType !== ''): ?><div class="muted"><?= sp_h($custType) ?></div><?php endif; ?>
            </td>
            <td><code><?= sp_h(sp_channel_display((string)($r->cChannel ?? ''))) ?></code></td>
            <td>
                <?= sp_h((string)($r->cChannelLabel ?? '')) ?>
                <?php if ($sourceDetail !== ''): ?>
                    <div class="muted">↳ <?= sp_h($sourceHost !== '' ? $sourceHost : $sourceDetail) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
        <tr><td colspan="<?= $spReportPublic ? 8 : 9 ?>">Keine Zeilen.</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if ($sumByCurrency !== []): ?>
        <tfoot>
        <tr>
            <th colspan="<?= $spReportPublic ? 4 : 5 ?>" class="num">Summe dieser Seite (ohne Storno):</th>
            <th class="num">
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
<?php if ($data['pages'] > 1): ?>
    <div class="pager">
        <?php if ($data['page'] > 1): ?><a href="<?= sp_h($pageUrl($data['page'] - 1)) ?>">‹ Zurück</a><?php endif; ?>
        <?php
        $from = \max(1, $data['page'] - 3);
        $to = \min($data['pages'], $data['page'] + 3);
        for ($p = $from; $p <= $to; $p++): ?>
            <?php if ($p === $data['page']): ?>
                <span class="cur"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= sp_h($pageUrl($p)) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($data['page'] < $data['pages']): ?><a href="<?= sp_h($pageUrl($data['page'] + 1)) ?>">Weiter ›</a><?php endif; ?>
    </div>
<?php endif; ?>
</div>
</div>
</body>
</html>
