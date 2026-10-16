<?php

declare(strict_types=1);

/**
 * Filter-Formular für HTML-Rapor (Admin + öffentlicher Token-Link).
 *
 * @var array{channel:string,days:int,q:string,dateFrom:string,dateTo:string,active:bool} $reportFilters
 * @var list<string> $filterChannels
 * @var bool $spReportPublic
 * @var string $reportToken
 */

$reportFilters = $reportFilters ?? sp_parse_list_filters();
$filterChannels = $filterChannels ?? sp_get_filter_channels(\JTL\Shop::Container()->getDB());
$spReportPublic = $spReportPublic ?? false;
$reportToken = $reportToken ?? '';

$resetFilters = ['channel' => '', 'days' => 0, 'q' => '', 'dateFrom' => '', 'dateTo' => '', 'active' => false];
$resetUrl = $spReportPublic
    ? sp_public_report_url($reportToken, $resetFilters)
    : sp_admin_report_url($resetFilters);
?>
<form method="get" class="filter-box">
    <?php if ($spReportPublic): ?>
        <input type="hidden" name="sp_salesreport" value="1">
        <input type="hidden" name="token" value="<?= sp_h($reportToken) ?>">
    <?php else: ?>
        <input type="hidden" name="sp_report" value="1">
        <?php
        foreach ($_GET as $gk => $gv) {
            if (\in_array($gk, ['sp_report', 'sp_salesreport', 'token', 'sp_f_channel', 'sp_f_days', 'sp_f_q', 'sp_f_from', 'sp_f_to', 'days'], true)) {
                continue;
            }
            if (\is_array($gv)) {
                continue;
            }
            echo '<input type="hidden" name="' . sp_h((string)$gk) . '" value="' . sp_h((string)$gv) . '">';
        }
        ?>
    <?php endif; ?>
    <div class="filter-grid">
        <label>
            <span>Kanal</span>
            <select name="sp_f_channel">
                <option value="">— alle —</option>
                <?php foreach ($filterChannels as $chOpt): ?>
                    <option value="<?= sp_h($chOpt) ?>"<?= $reportFilters['channel'] === $chOpt ? ' selected' : '' ?>><?= sp_h($chOpt) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Zeitraum</span>
            <select name="sp_f_days">
                <?php
                foreach ([0 => 'Alle', 7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '365 Tage'] as $dv => $dl):
                    ?>
                    <option value="<?= (int)$dv ?>"<?= $reportFilters['days'] === $dv ? ' selected' : '' ?>><?= sp_h($dl) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Von</span>
            <input type="date" name="sp_f_from" value="<?= sp_h($reportFilters['dateFrom']) ?>">
        </label>
        <label>
            <span>Bis</span>
            <input type="date" name="sp_f_to" value="<?= sp_h($reportFilters['dateTo']) ?>">
        </label>
        <label class="filter-search">
            <span>Suche</span>
            <input type="search" name="sp_f_q" placeholder="Bestellnr., Kanal, Produkt…"
                   value="<?= sp_h($reportFilters['q']) ?>">
        </label>
        <div class="filter-actions">
            <button type="submit">Filter anwenden</button>
            <?php if ($reportFilters['active']): ?>
                <a href="<?= sp_h($resetUrl) ?>" class="btn-reset">Zurücksetzen</a>
            <?php endif; ?>
        </div>
    </div>
</form>
