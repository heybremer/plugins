<?php

declare(strict_types=1);

/**
 * Top-Produkte Block (Dashboard + HTML-Rapor).
 *
 * @var list<array{rank:int,name:string,qty:float,orders:int,revenue?:float}> $topProductsMonth
 * @var list<array{rank:int,name:string,qty:float,orders:int,revenue?:float}> $topProductsAll
 * @var list<array{title:string,sub:string,items:list<array>}>|null $topProductsBlocks
 * @var string $monthLabel
 * @var bool $topProductsPlain
 * @var int $topProductsLimit
 */

$topProductsMonth = $topProductsMonth ?? [];
$topProductsAll = $topProductsAll ?? [];
$monthLabel = $monthLabel ?? \date('Y-m');
$topProductsPlain = $topProductsPlain ?? false;
$topProductsLimit = $topProductsLimit ?? 3;
$topProductsBlocks = $topProductsBlocks ?? [
    ['title' => 'Aktueller Monat', 'sub' => $monthLabel, 'items' => $topProductsMonth],
    ['title' => 'Gesamt', 'sub' => 'alle Zeiten', 'items' => $topProductsAll],
];

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
$numClass = $topProductsPlain ? 'num' : 'text-end text-nowrap';

$renderTable = static function (array $items) use ($tableClass, $theadClass, $numClass): void {
    if ($items === []) {
        echo '<p class="text-muted small muted p-3 mb-0">Keine Produktdaten.</p>';
        return;
    }
    echo '<table class="' . sp_h($tableClass) . '">';
    echo '<thead class="' . sp_h($theadClass) . '"><tr>';
    echo '<th class="text-center" style="width:42px">#</th><th>Produkt</th>';
    echo '<th class="' . $numClass . '">Stück</th><th class="' . $numClass . '">Best.</th><th class="' . $numClass . '">Umsatz</th>';
    echo '</tr></thead><tbody>';
    foreach ($items as $tp) {
        echo '<tr>';
        echo '<td class="text-center fw-bold">' . (int)$tp['rank'] . '</td>';
        echo '<td>' . sp_h($tp['name']) . '</td>';
        echo '<td class="' . $numClass . '">' . sp_h(\number_format($tp['qty'], 0, ',', '.')) . '</td>';
        echo '<td class="' . $numClass . '">' . (int)$tp['orders'] . '</td>';
        echo '<td class="' . $numClass . '">' . sp_h(sp_format_money((float)($tp['revenue'] ?? 0), 'EUR')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
};
?>
<?php if ($topProductsPlain): ?>
<h2>Top <?= (int)$topProductsLimit ?> Produkte (meistverkauft)</h2>
<?php else: ?>
<h4 class="mb-3">Top <?= (int)$topProductsLimit ?> Produkte (meistverkauft)</h4>
<?php endif; ?>
<?= $wrapStart ?>
<?php foreach ($topProductsBlocks as $block): ?>
<?= $cardOpen((string)$block['title'], (string)$block['sub']) ?>
<?php $renderTable($block['items']); ?>
<?= $cardClose ?>
<?php endforeach; ?>
<?= $wrapEnd ?>
<p class="text-muted small muted mb-4">
    Stück = Summe <code>twarenkorbpos.nAnzahl</code>, Varianten zum Vaterartikel zusammengefasst ·
    Umsatz = Positionspreis brutto · alle Bestellungen ohne Storno.
</p>
