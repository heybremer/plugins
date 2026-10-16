<?php

declare(strict_types=1);

/**
 * Top-3-Produkte Block (Dashboard + HTML-Rapor).
 *
 * @var list<array{rank:int,name:string,qty:float,orders:int}> $topProductsMonth
 * @var list<array{rank:int,name:string,qty:float,orders:int}> $topProductsAll
 * @var string $monthLabel
 * @var bool $topProductsPlain
 */

$topProductsMonth = $topProductsMonth ?? [];
$topProductsAll = $topProductsAll ?? [];
$monthLabel = $monthLabel ?? \date('Y-m');
$topProductsPlain = $topProductsPlain ?? false;

$wrapStart = $topProductsPlain ? '<div class="top-products-grid">' : '<div class="row row-cols-1 row-cols-md-2 g-3 mb-2">';
$wrapEnd = $topProductsPlain ? '</div>' : '</div>';
$cardOpen = static function (string $title, string $sub = '') use ($topProductsPlain): string {
    if ($topProductsPlain) {
        return '<div class="card"><h3>' . sp_h($title) . ($sub !== '' ? ' <span class="muted">(' . sp_h($sub) . ')</span>' : '') . '</h3>';
    }
    return '<div class="col"><div class="card h-100"><div class="card-header py-2"><strong>'
        . sp_h($title) . '</strong>'
        . ($sub !== '' ? ' <span class="text-muted small">(' . sp_h($sub) . ')</span>' : '')
        . '</div><div class="card-body p-0">';
};
$cardClose = $topProductsPlain ? '</div>' : '</div></div></div>';
$tableClass = $topProductsPlain ? '' : 'table table-sm mb-0';
$theadClass = $topProductsPlain ? '' : 'table-light';

$renderTable = static function (array $items) use ($tableClass, $theadClass): void {
    if ($items === []) {
        echo '<p class="text-muted small p-3 mb-0">Keine Produktdaten.</p>';
        return;
    }
    echo '<table class="' . sp_h($tableClass) . '">';
    echo '<thead class="' . sp_h($theadClass) . '"><tr>';
    echo '<th class="text-center" style="width:42px">#</th><th>Produkt</th>';
    echo '<th class="text-end">Stück</th><th class="text-end">Bestellungen</th>';
    echo '</tr></thead><tbody>';
    foreach ($items as $tp) {
        echo '<tr>';
        echo '<td class="text-center fw-bold">' . (int)$tp['rank'] . '</td>';
        echo '<td>' . sp_h($tp['name']) . '</td>';
        echo '<td class="text-end text-nowrap">' . sp_h(\number_format($tp['qty'], 0, ',', '.')) . '</td>';
        echo '<td class="text-end">' . (int)$tp['orders'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
};
?>
<?php if ($topProductsPlain): ?>
<h2>Top 3 Produkte (meistverkauft)</h2>
<?php else: ?>
<h4 class="mb-3">Top 3 Produkte (meistverkauft)</h4>
<?php endif; ?>
<?= $wrapStart ?>
<?= $cardOpen('Aktueller Monat', $monthLabel) ?>
<?php $renderTable($topProductsMonth); ?>
<?= $cardClose ?>
<?= $cardOpen('Gesamt', 'alle Zeiten') ?>
<?php $renderTable($topProductsAll); ?>
<?= $cardClose ?>
<?= $wrapEnd ?>
<p class="text-muted small mb-4">
    Stück = Summe <code>twarenkorbpos.nAnzahl</code> · nur Bestellungen mit SalesProduct-Attribution.
</p>
