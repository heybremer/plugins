<?php
declare(strict_types=1);

use Plugin\MesseList\MesseListService;

/** @var list<array{name:string,groups:list<array{name:string,series:list<string>}>}> $tabs */
/** @var list<string> $available */
/** @var list<array<string,mixed>> $products */
/** @var list<array{cArtNr:string,cName:string,nStock:float}> $selectableProducts */

$tabCount = \count($tabs);
$groupCount = 0;
foreach ($tabs as $tab) {
    $groupCount += \count($tab['groups']);
}
$manuals = isset($manuals) && \is_array($manuals) ? $manuals : [];
$quotes = isset($quotes) && \is_array($quotes) ? $quotes : [];
$selectedArtNr = MesseListService::getExtraArtNr();
$placeOptions = static function (int $selTab, int $selGroup) use ($tabs, $h): string {
    $html = '<option value="">Tab / Gruppe wählen</option>';
    foreach ($tabs as $ti => $tab) {
        foreach (($tab['groups'] ?? []) as $gi => $grp) {
            $val = (int)$ti . ':' . (int)$gi;
            $sel = ($selTab === (int)$ti && $selGroup === (int)$gi) ? ' selected' : '';
            $label = \trim((string)($tab['name'] ?? '')) . ' · ' . \trim((string)($grp['name'] ?? ''));
            $html .= '<option value="' . $h($val) . '"' . $sel . '>' . $h($label) . '</option>';
        }
    }

    return $html;
};
$loadError = isset($loadError) ? (string)$loadError : '';
$availableJson = \json_encode($available, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]';
$selectableJson = \json_encode($selectableProducts, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<style>
    .ml-admin {
        --ml-navy: #14251f;
        --ml-green: #176c4e;
        --ml-mint: #dff3e9;
        --ml-cream: #f7f5ef;
        --ml-line: #dce3de;
        --ml-text: #17211d;
        --ml-muted: #68736d;
        color: var(--ml-text);
        max-width: 1500px;
        margin: 0 auto 40px;
    }
    .ml-admin * { box-sizing: border-box; }
    .ml-hero {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 24px;
        align-items: end;
        padding: 28px 30px;
        margin-bottom: 18px;
        color: #fff;
        background: var(--ml-navy);
        border-radius: 18px;
    }
    .ml-hero::after {
        content: "";
        position: absolute;
        width: 240px;
        height: 240px;
        right: -55px;
        top: -95px;
        border: 40px solid rgba(132, 218, 174, .13);
        border-radius: 50%;
    }
    .ml-kicker {
        margin: 0 0 6px;
        color: #9bd5b8;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    .ml-hero h2 { margin: 0; font-size: 27px; font-weight: 750; letter-spacing: -.02em; }
    .ml-hero p { max-width: 650px; margin: 7px 0 0; color: #c9d5cf; }
    .ml-stats { position: relative; z-index: 1; display: flex; gap: 9px; }
    .ml-stat { min-width: 86px; padding: 11px 14px; background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.12); border-radius: 12px; }
    .ml-stat strong { display: block; font-size: 20px; line-height: 1; }
    .ml-stat span { display: block; margin-top: 5px; color: #b9c9c1; font-size: 11px; }
    .ml-panel {
        margin-bottom: 18px;
        background: #fff;
        border: 1px solid var(--ml-line);
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(25, 48, 39, .055);
    }
    .ml-panel-body { padding: 20px; }
    .ml-link-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px; }
    .ml-link-row input { min-height: 42px; }
    .ml-section-title { margin: 0; font-size: 17px; font-weight: 750; }
    .ml-section-help { margin: 4px 0 15px; color: var(--ml-muted); font-size: 13px; }
    .ml-workspace { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 18px; align-items: start; }
    .ml-tab-card { overflow: hidden; margin-bottom: 14px; background: var(--ml-cream); border: 1px solid var(--ml-line); border-radius: 14px; }
    .ml-tab-head { display: grid; grid-template-columns: auto minmax(180px, 1fr) auto; gap: 10px; align-items: center; padding: 13px; background: #edf1ed; }
    .ml-number { display: grid; place-items: center; width: 30px; height: 30px; color: #fff; background: var(--ml-navy); border-radius: 9px; font-size: 12px; font-weight: 800; }
    .ml-tab-name { height: 38px; font-weight: 700; border-color: transparent !important; background: rgba(255,255,255,.85) !important; }
    .ml-groups { padding: 12px; }
    .ml-group-card { padding: 0; margin-bottom: 10px; background: #fff; border: 1px solid var(--ml-line); border-radius: 12px; }
    .ml-group-card:last-of-type { margin-bottom: 0; }
    .ml-group-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }
    .ml-group-summary::-webkit-details-marker { display: none; }
    .ml-group-summary::before {
        content: "";
        flex: 0 0 8px;
        width: 8px;
        height: 8px;
        margin-right: -4px;
        border-right: 2px solid var(--ml-navy);
        border-bottom: 2px solid var(--ml-navy);
        transform: rotate(-45deg);
        transition: transform .15s ease;
    }
    .ml-group-card[open] .ml-group-summary::before { transform: rotate(45deg); }
    .ml-group-summary-title { min-width: 0; flex: 1; }
    .ml-group-summary-title .ml-label { margin-bottom: 2px; }
    .ml-group-summary-name { display: block; overflow: hidden; font-size: 15px; text-overflow: ellipsis; white-space: nowrap; }
    .ml-group-summary-count {
        flex: 0 0 auto;
        padding: 3px 8px;
        color: #15583f;
        background: var(--ml-mint);
        border-radius: 999px;
        font-size: 11px;
        font-weight: 750;
    }
    .ml-group-body { padding: 0 14px 14px; border-top: 1px solid #eef2ef; }
    .ml-group-top { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(150px, 210px) auto; gap: 8px; align-items: end; margin: 12px 0; }
    .ml-label { display: block; margin-bottom: 5px; color: var(--ml-muted); font-size: 11px; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; }
    .ml-series-toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 9px; }
    .ml-series-search { width: min(260px, 100%); }
    .ml-series-list { display: flex; flex-wrap: wrap; gap: 7px; min-height: 18px; }
    .ml-series-empty { width: 100%; margin: 0; color: var(--ml-muted); font-size: 12px; }
    .ml-series-pill { position: relative; margin: 0; cursor: pointer; }
    .ml-series-pill input { position: absolute; opacity: 0; pointer-events: none; }
    .ml-series-pill span { display: block; padding: 6px 10px; color: #415048; background: #f4f6f4; border: 1px solid #d9e1dc; border-radius: 999px; font-size: 12px; font-weight: 650; transition: .15s ease; }
    .ml-series-pill input:checked + span { color: #fff; background: var(--ml-green); border-color: var(--ml-green); box-shadow: 0 3px 10px rgba(23,108,78,.18); }
    .ml-group-manuals { margin-top: 14px; padding-top: 12px; border-top: 1px dashed #d8e2dc; }
    .ml-group-manuals-title {
        display: flex; align-items: center; gap: 7px; margin: 0 0 8px;
        color: var(--ml-muted); font-size: 11px; font-weight: 800;
        letter-spacing: .04em; text-transform: uppercase;
    }
    .ml-group-manuals-title span {
        display: inline-grid; place-items: center; min-width: 20px; height: 20px;
        padding: 0 6px; color: #fff; background: var(--ml-green); border-radius: 999px;
    }
    .ml-group-manual-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 8px; }
    .ml-group-manual-item {
        display: grid; grid-template-columns: 42px minmax(0, 1fr); gap: 9px; align-items: center;
        min-height: 58px; padding: 7px; background: #f5faf7;
        border: 1px solid #dbe9e1; border-radius: 10px;
    }
    .ml-group-manual-item img, .ml-group-manual-placeholder {
        display: grid; place-items: center; width: 42px; height: 42px;
        object-fit: cover; color: #6d7d74; background: #e4eee8; border-radius: 7px;
        font-size: 16px; font-weight: 800;
    }
    .ml-group-manual-item strong, .ml-group-manual-item small {
        display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .ml-group-manual-item strong { font-size: 12px; }
    .ml-group-manual-item small { color: var(--ml-muted); font-size: 11px; }
    .ml-add-group { margin-top: 10px; }
    .ml-side { position: sticky; top: 12px; }
    .ml-side-card { padding: 17px; margin-bottom: 12px; background: #fff; border: 1px solid var(--ml-line); border-radius: 14px; }
    .ml-side-card h3 { margin: 0 0 4px; font-size: 15px; font-weight: 750; }
    .ml-side-card p { margin: 0 0 12px; color: var(--ml-muted); font-size: 12px; }
    .ml-product-picker { max-height: 330px; overflow: auto; margin-top: 9px; border: 1px solid var(--ml-line); border-radius: 10px; }
    .ml-product-picker .ml-series-empty { padding: 12px 10px; }
    .ml-product-option { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto; gap: 8px; align-items: start; padding: 9px 10px; margin: 0; border-bottom: 1px solid #edf0ee; cursor: pointer; }
    .ml-product-option:last-child { border-bottom: 0; }
    .ml-product-option:hover { background: #f5faf7; }
    .ml-product-option strong { display: block; font-family: ui-monospace, Consolas, monospace; font-size: 12px; }
    .ml-product-option small { display: block; overflow: hidden; color: var(--ml-muted); font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
    .ml-stock { color: var(--ml-green); font-size: 11px; font-weight: 700; white-space: nowrap; }
    .ml-selected-count { display: inline-flex; min-width: 22px; justify-content: center; padding: 2px 7px; color: #fff; background: var(--ml-green); border-radius: 999px; font-size: 11px; }
    .ml-save { width: 100%; min-height: 45px; font-weight: 750; background: var(--ml-green); border-color: var(--ml-green); }
    .ml-actions { display: flex; gap: 8px; }
    .ml-table-search { max-width: 320px; }
    .ml-pager { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: space-between; margin-top: 12px; }
    .ml-pager-pages { display: flex; flex-wrap: wrap; gap: 4px; }
    .ml-pager-btn {
        min-width: 34px; height: 32px; padding: 0 10px;
        color: var(--ml-navy); background: #fff; border: 1px solid var(--ml-line);
        border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .ml-pager-btn.is-on { color: #fff; background: var(--ml-green); border-color: var(--ml-green); }
    .ml-badge { display: inline-block; padding: 4px 8px; color: #15583f; background: var(--ml-mint); border-radius: 999px; font-size: 11px; font-weight: 750; }
    .ml-empty { padding: 18px; color: var(--ml-muted); text-align: center; }
    .ml-manual-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
    }
    .ml-manual-head .ml-section-help { margin-bottom: 0; }
    .ml-manual-add-toggle {
        flex: 0 0 auto;
        min-height: 40px;
        padding: 0 16px;
        color: #fff;
        background: var(--ml-green);
        border: 0;
        border-radius: 10px;
        font-weight: 750;
        box-shadow: 0 7px 18px rgba(23,108,78,.18);
    }
    .ml-manual-add-toggle:hover { color: #fff; background: #125b41; }
    .ml-manual-list { display: grid; gap: 10px; }
    .ml-manual-row {
        display: grid;
        grid-template-columns: minmax(190px, 1.25fr) 90px 105px minmax(150px, 1fr) 80px 95px 145px auto;
        gap: 8px;
        align-items: center;
        padding: 12px;
        background: #fafbf9;
        border: 1px solid #e5ebe7;
        border-radius: 12px;
    }
    .ml-manual-row:hover { border-color: #cbd9d1; box-shadow: 0 6px 18px rgba(25,48,39,.05); }
    .ml-manual-new {
        margin-top: 14px;
        padding: 16px;
        background: linear-gradient(135deg, #f4faf7, #fbfaf6);
        border: 1px solid #cfe1d7;
        border-radius: 14px;
        box-shadow: inset 4px 0 0 var(--ml-green);
    }
    .ml-manual-new[hidden] { display: none !important; }
    .ml-manual-new-title { margin: 0 0 12px; font-size: 14px; font-weight: 800; }
    .ml-manual-grid {
        display: grid;
        grid-template-columns: minmax(200px, 1.3fr) 90px 100px minmax(120px, 1fr) 80px 90px 130px auto;
        gap: 8px;
        align-items: end;
        margin: 0;
    }
    .ml-manual-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; background: #eef2ef; }
    .ml-manual-image { display: flex; align-items: center; gap: 7px; min-width: 0; }
    .ml-manual-image input { min-width: 0; }
    @media (max-width: 900px) {
        .ml-manual-grid, .ml-manual-row { grid-template-columns: 1fr 1fr; }
        .ml-manual-head { align-items: flex-start; }
    }
    @media (max-width: 1000px) {
        .ml-workspace { grid-template-columns: 1fr; }
        .ml-side { position: static; }
        .ml-hero { grid-template-columns: 1fr; }
    }
    @media (max-width: 700px) {
        .ml-hero { padding: 22px; }
        .ml-stats { flex-wrap: wrap; }
        .ml-link-row, .ml-group-top, .ml-tab-head { grid-template-columns: 1fr; }
    }
</style>

<div class="ml-admin">
    <header class="ml-hero">
        <div>
            <p class="ml-kicker">Messe · Produktsteuerung</p>
            <h2>Messe-Liste konfigurieren</h2>
            <p>Tabs strukturieren, Serien zu Gruppen ordnen und einzelne Produkte direkt über ihre Art.-Nr. ergänzen.</p>
        </div>
        <div class="ml-stats">
            <div class="ml-stat"><strong><?= $tabCount ?></strong><span>Tabs</span></div>
            <div class="ml-stat"><strong><?= $groupCount ?></strong><span>Gruppen</span></div>
            <div class="ml-stat"><strong><?= $count ?></strong><span>Produkte</span></div>
            <div class="ml-stat"><strong><?= \count($quotes ?? []) ?></strong><span>Angebote</span></div>
        </div>
    </header>

    <?php if ($saved): ?>
        <div class="alert alert-success">✓ Änderungen wurden gespeichert.</div>
    <?php endif; ?>

    <section class="ml-panel">
        <div class="ml-panel-body">
            <h3 class="ml-section-title">Öffentlicher Messe-Link</h3>
            <p class="ml-section-help">Link kopieren oder direkt in einem neuen Tab testen.</p>
            <div class="ml-link-row">
                <input type="text" class="form-control font-monospace" readonly value="<?= $h($publicUrl) ?>" id="ml-public-url">
                <button type="button" class="btn btn-outline-secondary" id="ml-copy-link">Link kopieren</button>
                <a class="btn btn-primary" href="<?= $h($publicUrl) ?>" target="_blank" rel="noopener">Öffnen ↗</a>
            </div>
            <a class="small d-inline-block mt-2" href="?ml_new_token=1" onclick="return confirm('Alten Link ungültig machen?');">Neuen Token erzeugen</a>
        </div>
    </section>

    <section class="ml-panel">
        <div class="ml-panel-body">
            <h3 class="ml-section-title">Angebote</h3>
            <p class="ml-section-help">Auf der Messe erstellte A4-Angebote. Klick öffnet die Druckansicht.</p>
            <?php if (!isset($quotes) || $quotes === []): ?>
                <div class="ml-empty">Noch keine Angebote.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Datum</th>
                                <th>Name</th>
                                <th>Telefon</th>
                                <th>E-Mail</th>
                                <th class="text-end">Gesamt</th>
                                <th>E-Mail</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($quotes as $q): ?>
                            <tr>
                                <td class="font-monospace"><?= $h((string)$q['nr']) ?></td>
                                <td><?= $h(\date('d.m.Y H:i', \strtotime((string)$q['created']) ?: \time())) ?></td>
                                <td><?= $h(\trim((string)$q['vorname'] . ' ' . (string)$q['nachname'])) ?></td>
                                <td><?= $h((string)$q['telefon']) ?></td>
                                <td><?= $h((string)$q['email']) ?></td>
                                <td class="text-end fw-bold"><?= \number_format((float)$q['total'], 2, ',', '.') ?> €</td>
                                <td><?= !empty($q['mailed']) ? 'gesendet' : 'nicht gesendet' ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="?ml_offer=<?= (int)$q['id'] ?>" target="_blank" rel="noopener">A4 öffnen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="ml-panel">
        <div class="ml-panel-body">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <h3 class="ml-section-title">Aktive Messe-Produkte</h3>
                    <p class="ml-section-help mb-0"><?= (int)$count ?> Artikel aus Serien- und Art.-Nr.-Auswahl.</p>
                </div>
                <input type="search" class="form-control form-control-sm ml-table-search" id="ml-table-search"
                       placeholder="Liste durchsuchen …">
            </div>
            <?php if ($count === 0): ?>
                <div class="ml-empty">
                    Keine passenden Artikel gefunden.
                    <?php if ($loadError !== ''): ?>
                        <br><span class="font-monospace small"><?= $h($loadError) ?></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="ml-product-table">
                        <thead><tr><th>Serie</th><th>Art.-Nr.</th><th>Produkt</th><th class="text-end">Lager</th><th class="text-end">Brutto</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><span class="ml-badge"><?= $h((string)$product['cSerie']) ?></span><?php if (!empty($product['cManual'])): ?> <span class="small text-muted">manuell</span><?php endif; ?></td>
                                <td class="font-monospace"><?= $h((string)$product['cArtNr']) ?></td>
                                <td><?= $h((string)$product['cName']) ?></td>
                                <td class="text-end"><?= \number_format((float)$product['nStock'], 0, ',', '.') ?></td>
                                <td class="text-end fw-bold"><?= \number_format((float)$product['fGross'], 2, ',', '.') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="ml-pager" id="ml-table-pager" hidden>
                    <span class="small text-muted" id="ml-table-pager-info"></span>
                    <div class="ml-pager-pages" id="ml-table-pager-pages"></div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <form method="post" id="ml-config-form" enctype="multipart/form-data">
        <section class="ml-panel">
            <div class="ml-panel-body">
                <div class="ml-manual-head">
                    <div>
                        <h3 class="ml-section-title">Manuelle Artikel</h3>
                        <p class="ml-section-help">Tab und Gruppe wählen. Der Artikel erscheint nur dort und unter All Produkt dieses Tabs.</p>
                    </div>
                    <button type="button" class="ml-manual-add-toggle" id="ml-manual-toggle"
                            aria-controls="ml-manual-new" aria-expanded="false">+ Neuer Artikel</button>
                </div>
                <div class="ml-manual-list">
                <?php foreach ($manuals as $man): ?>
                    <div class="ml-manual-row">
                        <select class="form-select form-select-sm" name="ml_manual[<?= $h($man['id']) ?>][place]" required>
                            <?= $placeOptions((int)($man['nTab'] ?? -1), (int)($man['nGroup'] ?? -1)) ?>
                        </select>
                        <input type="text" class="form-control form-control-sm" name="ml_manual[<?= $h($man['id']) ?>][serie]"
                               value="<?= $h($man['cSerie']) ?>" placeholder="Serie" maxlength="16">
                        <input type="text" class="form-control form-control-sm" name="ml_manual[<?= $h($man['id']) ?>][artnr]"
                               value="<?= $h($man['cArtNr']) ?>" placeholder="Art.-Nr." maxlength="20">
                        <input type="text" class="form-control form-control-sm" name="ml_manual[<?= $h($man['id']) ?>][name]"
                               value="<?= $h($man['cName']) ?>" placeholder="Produkt" maxlength="80">
                        <input type="number" class="form-control form-control-sm" name="ml_manual[<?= $h($man['id']) ?>][stock]"
                               value="<?= $h((string)$man['nStock']) ?>" step="1" min="0" placeholder="Lager">
                        <input type="text" class="form-control form-control-sm" name="ml_manual[<?= $h($man['id']) ?>][gross]"
                               value="<?= $h(\number_format((float)$man['fGross'], 2, ',', '')) ?>" placeholder="Brutto">
                        <div class="ml-manual-image">
                            <?php if ($man['cImg'] !== ''): ?>
                                <img class="ml-manual-thumb" src="<?= $h($man['cImg']) ?>" alt="">
                            <?php endif; ?>
                            <input type="hidden" name="ml_manual[<?= $h($man['id']) ?>][img]" value="<?= $h($man['cImg']) ?>">
                            <input type="file" class="form-control form-control-sm" name="ml_manual_img[<?= $h($man['id']) ?>]" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        <button class="btn btn-sm btn-outline-danger" type="submit" name="ml_del_manual"
                                value="<?= $h($man['id']) ?>" onclick="return confirm('Manuellen Artikel löschen?');">Löschen</button>
                    </div>
                <?php endforeach; ?>
                <?php if ($manuals === []): ?>
                    <div class="ml-empty">Noch keine manuellen Artikel. Mit „+ Neuer Artikel“ beginnen.</div>
                <?php endif; ?>
                </div>
                <div class="ml-manual-new" id="ml-manual-new" hidden>
                    <h4 class="ml-manual-new-title">Neuen manuellen Artikel anlegen</h4>
                    <div class="ml-manual-grid">
                    <div>
                        <label class="ml-label">Tab / Gruppe</label>
                        <select class="form-select form-select-sm" name="ml_new_place" id="ml-new-place">
                            <?= $placeOptions(-1, -1) ?>
                        </select>
                    </div>
                    <div>
                        <label class="ml-label">Serie</label>
                        <input type="text" class="form-control form-control-sm" name="ml_new_serie" placeholder="z. B. KT103" maxlength="16">
                    </div>
                    <div>
                        <label class="ml-label">Art.-Nr.</label>
                        <input type="text" class="form-control form-control-sm" name="ml_new_artnr" placeholder="z. B. 185" maxlength="20">
                    </div>
                    <div>
                        <label class="ml-label">Produkt</label>
                        <input type="text" class="form-control form-control-sm" name="ml_new_name" placeholder="Produktname" maxlength="80">
                    </div>
                    <div>
                        <label class="ml-label">Lager</label>
                        <input type="number" class="form-control form-control-sm" name="ml_new_stock" value="0" step="1" min="0">
                    </div>
                    <div>
                        <label class="ml-label">Brutto €</label>
                        <input type="text" class="form-control form-control-sm" name="ml_new_gross" placeholder="149,00">
                    </div>
                    <div>
                        <label class="ml-label">Bild</label>
                        <input type="file" class="form-control form-control-sm" name="ml_new_img" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                    <button class="btn btn-sm btn-success" type="submit" name="ml_manual_add" value="1">Artikel speichern</button>
                    </div>
                </div>
            </div>
        </section>
        <div class="ml-workspace">
            <main>
                <section class="ml-panel">
                    <div class="ml-panel-body">
                        <h3 class="ml-section-title">Aufbau der Liste</h3>
                        <p class="ml-section-help">Gruppen aufklappen, gewählte Serien prüfen. Weitere Serien über die Suche hinzufügen.</p>

                        <?php foreach ($tabs as $t => $tab): ?>
                            <article class="ml-tab-card">
                                <div class="ml-tab-head">
                                    <span class="ml-number"><?= (int)$t + 1 ?></span>
                                    <input class="form-control ml-tab-name" name="ml_tab_name[<?= (int)$t ?>]"
                                           value="<?= $h((string)$tab['name']) ?>" placeholder="Tab-Name" maxlength="80">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" name="ml_del_tab"
                                            value="<?= (int)$t ?>" onclick="return confirm('Tab samt Gruppen löschen?');">Tab löschen</button>
                                </div>
                                <div class="ml-groups">
                                    <?php foreach ($tab['groups'] as $g => $grp): ?>
                                        <?php
                                        $grpName = (string)$grp['name'];
                                        $grpSel  = \count($grp['series']);
                                        $grpManuals = \array_values(\array_filter(
                                            $manuals,
                                            static fn(array $man): bool =>
                                                (int)($man['nTab'] ?? -1) === (int)$t
                                                && (int)($man['nGroup'] ?? -1) === (int)$g
                                        ));
                                        ?>
                                        <details class="ml-group-card" data-tab="<?= (int)$t ?>" data-group="<?= (int)$g ?>"<?= $t === 0 && $g === 0 ? ' open' : '' ?>>
                                            <summary class="ml-group-summary">
                                                <span class="ml-group-summary-title">
                                                    <span class="ml-label">Gruppe <?= (int)$g + 1 ?></span>
                                                    <strong class="ml-group-summary-name"><?= $h($grpName !== '' ? $grpName : 'Neue Gruppe') ?></strong>
                                                </span>
                                                <span class="ml-group-summary-count" data-manual-count="<?= \count($grpManuals) ?>">
                                                    <?= $grpSel ?> Serie<?= \count($grpManuals) > 0 ? ' · ' . \count($grpManuals) . ' manuell' : '' ?>
                                                </span>
                                            </summary>
                                            <div class="ml-group-body">
                                                <div class="ml-group-top">
                                                    <div>
                                                        <label class="ml-label">Gruppenname</label>
                                                        <input class="form-control form-control-sm ml-group-name"
                                                               name="ml_group_name[<?= (int)$t ?>][<?= (int)$g ?>]"
                                                               value="<?= $h($grpName) ?>" placeholder="z. B. LT Serie" maxlength="80">
                                                    </div>
                                                    <div>
                                                        <label class="ml-label">Verschieben nach</label>
                                                        <select class="form-select form-select-sm"
                                                                name="ml_group_move[<?= (int)$t ?>][<?= (int)$g ?>]">
                                                            <option value="">Aktueller Tab</option>
                                                            <?php foreach ($tabs as $ot => $otherTab): ?>
                                                                <?php if ((int)$ot !== (int)$t): ?>
                                                                    <option value="<?= (int)$ot ?>"><?= $h((string)$otherTab['name']) ?></option>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" name="ml_del_group"
                                                            value="<?= (int)$t ?>:<?= (int)$g ?>"
                                                            onclick="return confirm('Gruppe löschen?');">Löschen</button>
                                                </div>
                                                <div class="ml-series-toolbar">
                                                    <input type="search" class="form-control form-control-sm ml-series-search"
                                                           placeholder="Serie suchen, um weitere hinzuzufügen …" aria-label="Serie suchen">
                                                    <span class="small text-muted ml-series-count"><?= $grpSel ?> gewählt</span>
                                                </div>
                                                <div class="ml-series-list">
                                                    <?php foreach ($grp['series'] as $series): ?>
                                                        <label class="ml-series-pill" data-search="<?= $h(\strtolower($series)) ?>">
                                                            <input type="checkbox"
                                                                   name="ml_group_series[<?= (int)$t ?>][<?= (int)$g ?>][]"
                                                                   value="<?= $h($series) ?>" checked>
                                                            <span><?= $h($series) ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                    <p class="ml-series-empty"<?= $grpSel > 0 ? ' hidden' : '' ?>>Keine Serie gewählt. Suche nutzen, um Serien hinzuzufügen.</p>
                                                </div>
                                                <?php if ($grpManuals !== []): ?>
                                                    <div class="ml-group-manuals">
                                                        <p class="ml-group-manuals-title">Manuelle Artikel <span><?= \count($grpManuals) ?></span></p>
                                                        <div class="ml-group-manual-list">
                                                            <?php foreach ($grpManuals as $man): ?>
                                                                <div class="ml-group-manual-item">
                                                                    <?php if ((string)$man['cImg'] !== ''): ?>
                                                                        <img src="<?= $h((string)$man['cImg']) ?>" alt="">
                                                                    <?php else: ?>
                                                                        <span class="ml-group-manual-placeholder">M</span>
                                                                    <?php endif; ?>
                                                                    <span>
                                                                        <strong><?= $h((string)$man['cSerie']) ?> · <?= $h((string)$man['cArtNr']) ?></strong>
                                                                        <small><?= $h((string)$man['cName']) ?></small>
                                                                    </span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                    <button class="btn btn-sm btn-outline-success ml-add-group" type="submit"
                                            name="ml_add_group" value="<?= (int)$t ?>">+ Gruppe hinzufügen</button>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <button class="btn btn-outline-dark" type="submit" name="ml_add_tab" value="1">+ Neuen Tab anlegen</button>
                    </div>
                </section>
            </main>

            <aside class="ml-side">
                <section class="ml-side-card">
                    <h3>Produkte per Art.-Nr.</h3>
                    <p>Nur gewählte Artikel sind sichtbar. Weitere über die Suche hinzufügen.</p>
                    <input type="search" class="form-control form-control-sm" id="ml-product-search"
                           placeholder="Art.-Nr. oder Name suchen …">
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-muted">Ausgewählt</span>
                        <span class="ml-selected-count" id="ml-product-count"><?= \count($selectedArtNr) ?></span>
                    </div>
                    <div class="ml-product-picker" id="ml-product-picker">
                        <p class="ml-series-empty" id="ml-product-empty"<?= \count($selectedArtNr) > 0 ? ' hidden' : '' ?>>Kein Artikel gewählt. Suche nutzen, um Produkte hinzuzufügen.</p>
                        <?php foreach ($selectedArtNr as $nr): ?>
                            <?php
                            $hit = null;
                            foreach ($selectableProducts as $product) {
                                if ((string)$product['cArtNr'] === $nr) {
                                    $hit = $product;
                                    break;
                                }
                            }
                            ?>
                            <label class="ml-product-option" data-search="<?= $h(\strtolower($nr . ' ' . (string)($hit['cName'] ?? ''))) ?>">
                                <input type="checkbox" name="ml_extra_artnr[]" value="<?= $h($nr) ?>" checked>
                                <span><strong><?= $h($nr) ?></strong><small title="<?= $h((string)($hit['cName'] ?? 'Manuell')) ?>"><?= $h((string)($hit['cName'] ?? 'Manuell hinzugefügt')) ?></small></span>
                                <span class="ml-stock"><?= $hit ? \number_format((float)$hit['nStock'], 0, ',', '.') . ' Stk.' : '–' ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="ml-side-card">
                    <h3>Weitere Serien</h3>
                    <p>Nur verwenden, wenn eine Serie oben nicht angeboten wird.</p>
                    <input type="text" class="form-control form-control-sm" name="ml_extra_series"
                           value="<?= $h(\implode(', ', MesseListService::getExtraSeries())) ?>"
                           placeholder="z. B. LT3, LT4">
                </section>

                <div class="ml-actions">
                    <button class="btn btn-success ml-save" type="submit" name="ml_save_tabs" value="1">Änderungen speichern</button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script>
window.ML_AVAILABLE = <?= $availableJson ?>;
window.ML_SELECTABLE = <?= $selectableJson ?>;
(function () {
    'use strict';
    var ALL_SERIES = Array.isArray(window.ML_AVAILABLE) ? window.ML_AVAILABLE : [];
    var ALL_PRODUCTS = Array.isArray(window.ML_SELECTABLE) ? window.ML_SELECTABLE : [];
    var copy = document.getElementById('ml-copy-link');
    if (copy) {
        copy.addEventListener('click', function () {
            var input = document.getElementById('ml-public-url');
            if (!input) return;
            navigator.clipboard.writeText(input.value);
            copy.textContent = 'Kopiert ✓';
            window.setTimeout(function () { copy.textContent = 'Link kopieren'; }, 1500);
        });
    }

    var manualToggle = document.getElementById('ml-manual-toggle');
    var manualNew = document.getElementById('ml-manual-new');
    var manualPlace = document.getElementById('ml-new-place');
    if (manualToggle && manualNew) {
        manualToggle.addEventListener('click', function () {
            var opening = manualNew.hidden;
            manualNew.hidden = !opening;
            manualToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            manualToggle.textContent = opening ? '× Schließen' : '+ Neuer Artikel';
            if (manualPlace) {
                manualPlace.required = opening;
            }
            if (opening) {
                window.setTimeout(function () {
                    manualNew.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    if (manualPlace) {
                        manualPlace.focus();
                    }
                }, 20);
            }
        });
    }

    function seriesPillHtml(tab, group, series, checked) {
        var label = document.createElement('label');
        label.className = 'ml-series-pill';
        label.setAttribute('data-search', String(series).toLowerCase());
        label.innerHTML = '<input type="checkbox" name="ml_group_series[' + tab + '][' + group + '][]" value="'
            + String(series).replace(/"/g, '&quot;') + '"' + (checked ? ' checked' : '') + '><span></span>';
        label.querySelector('span').textContent = series;
        return label;
    }

    function injectSeriesMatches(card, query) {
        if (!query) {
            return;
        }
        var tab = card.getAttribute('data-tab') || '0';
        var group = card.getAttribute('data-group') || '0';
        var list = card.querySelector('.ml-series-list');
        if (!list) {
            return;
        }
        ALL_SERIES.forEach(function (series) {
            if (String(series).toLowerCase().indexOf(query) === -1) {
                return;
            }
            if (card.querySelector('.ml-series-pill input[value="' + series.replace(/"/g, '') + '"]')) {
                return;
            }
            list.appendChild(seriesPillHtml(tab, group, series, false));
        });
    }

    function productOptionHtml(p) {
        var nr = String(p.cArtNr || '');
        var name = String(p.cName || nr);
        var label = document.createElement('label');
        label.className = 'ml-product-option';
        label.setAttribute('data-search', (nr + ' ' + name).toLowerCase());
        label.innerHTML = '<input type="checkbox" name="ml_extra_artnr[]" value="' + nr.replace(/"/g, '&quot;') + '">'
            + '<span><strong></strong><small></small></span><span class="ml-stock"></span>';
        label.querySelector('strong').textContent = nr;
        label.querySelector('small').textContent = name;
        label.querySelector('small').title = name;
        label.querySelector('.ml-stock').textContent = Math.round(p.nStock || 0).toLocaleString('de-DE') + ' Stk.';
        return label;
    }

    function injectProductMatches(query) {
        if (!picker || !query) {
            return;
        }
        ALL_PRODUCTS.forEach(function (p) {
            var nr = String(p.cArtNr || '');
            var name = String(p.cName || '');
            var hay = (nr + ' ' + name).toLowerCase();
            if (hay.indexOf(query) === -1) {
                return;
            }
            if (picker.querySelector('input[value="' + nr.replace(/"/g, '') + '"]')) {
                return;
            }
            picker.appendChild(productOptionHtml(p));
        });
    }

    function syncGroupCard(card) {
        var query = '';
        var search = card.querySelector('.ml-series-search');
        if (search) {
            query = search.value.trim().toLowerCase();
        }
        injectSeriesMatches(card, query);
        var visible = 0;
        var selected = 0;
        card.querySelectorAll('.ml-series-pill').forEach(function (pill) {
            var input = pill.querySelector('input');
            var checked = !!(input && input.checked);
            if (checked) {
                selected += 1;
            }
            var match = (pill.getAttribute('data-search') || '').indexOf(query) !== -1;
            pill.hidden = !checked && (query === '' || !match);
            if (!pill.hidden) {
                visible += 1;
            }
        });
        var empty = card.querySelector('.ml-series-empty');
        if (empty) {
            empty.hidden = visible > 0;
        }
        var countEl = card.querySelector('.ml-series-count');
        if (countEl) {
            countEl.textContent = selected + ' gewählt';
        }
        var summaryCount = card.querySelector('.ml-group-summary-count');
        if (summaryCount) {
            var manualCount = parseInt(summaryCount.getAttribute('data-manual-count'), 10) || 0;
            summaryCount.textContent = selected + ' Serie' + (manualCount ? ' · ' + manualCount + ' manuell' : '');
        }
    }

    document.querySelectorAll('.ml-group-card').forEach(function (card) {
        syncGroupCard(card);
        var search = card.querySelector('.ml-series-search');
        if (search) {
            search.addEventListener('input', function () { syncGroupCard(card); });
        }
        card.addEventListener('change', function (e) {
            if (e.target && e.target.matches('.ml-series-pill input')) {
                syncGroupCard(card);
            }
        });
        var nameInput = card.querySelector('.ml-group-name');
        var nameEl = card.querySelector('.ml-group-summary-name');
        if (nameInput && nameEl) {
            nameInput.addEventListener('input', function () {
                var v = nameInput.value.trim();
                nameEl.textContent = v !== '' ? v : 'Neue Gruppe';
            });
        }
    });

    var productSearch = document.getElementById('ml-product-search');
    var picker = document.getElementById('ml-product-picker');
    var count = document.getElementById('ml-product-count');
    var productEmpty = document.getElementById('ml-product-empty');
    function syncProductPicker() {
        if (!picker) {
            return;
        }
        var query = productSearch ? productSearch.value.trim().toLowerCase() : '';
        injectProductMatches(query);
        var visible = 0;
        var selected = 0;
        picker.querySelectorAll('.ml-product-option').forEach(function (option) {
            var input = option.querySelector('input');
            var checked = !!(input && input.checked);
            if (checked) {
                selected += 1;
            }
            var match = (option.getAttribute('data-search') || '').indexOf(query) !== -1;
            option.hidden = !checked && (query === '' || !match);
            if (!option.hidden) {
                visible += 1;
            }
        });
        if (count) {
            count.textContent = selected;
        }
        if (productEmpty) {
            productEmpty.hidden = visible > 0;
        }
    }
    if (picker) {
        syncProductPicker();
        if (productSearch) {
            productSearch.addEventListener('input', syncProductPicker);
        }
        picker.addEventListener('change', syncProductPicker);
    }

    var form = document.getElementById('ml-config-form');
    if (form) {
        form.addEventListener('submit', function () {
            document.querySelectorAll('.ml-series-pill input:checked, .ml-product-option input:checked').forEach(function (input) {
                input.disabled = false;
                var wrap = input.closest('label');
                if (wrap) {
                    wrap.hidden = false;
                }
            });
        });
    }

    var tableSearch = document.getElementById('ml-table-search');
    var table = document.getElementById('ml-product-table');
    var pager = document.getElementById('ml-table-pager');
    var pagerInfo = document.getElementById('ml-table-pager-info');
    var pagerPages = document.getElementById('ml-table-pager-pages');
    var pageSize = 5;
    var tablePage = 1;
    function matchedRows() {
        if (!table) {
            return [];
        }
        var query = tableSearch ? tableSearch.value.trim().toLowerCase() : '';
        return Array.prototype.filter.call(table.querySelectorAll('tbody tr'), function (row) {
            return query === '' || row.textContent.toLowerCase().indexOf(query) !== -1;
        });
    }
    function renderTablePage() {
        if (!table) {
            return;
        }
        var rows = matchedRows();
        var pages = Math.max(1, Math.ceil(rows.length / pageSize));
        if (tablePage > pages) {
            tablePage = pages;
        }
        var start = (tablePage - 1) * pageSize;
        var end = start + pageSize;
        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.hidden = true;
        });
        rows.forEach(function (row, i) {
            row.hidden = i < start || i >= end;
        });
        if (pager && pagerInfo && pagerPages) {
            pager.hidden = rows.length <= pageSize;
            var from = rows.length ? start + 1 : 0;
            var to = Math.min(end, rows.length);
            pagerInfo.textContent = from + '–' + to + ' / ' + rows.length;
            pagerPages.innerHTML = '';
            for (var p = 1; p <= pages; p++) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ml-pager-btn' + (p === tablePage ? ' is-on' : '');
                btn.textContent = String(p);
                btn.setAttribute('data-page', String(p));
                pagerPages.appendChild(btn);
            }
        }
    }
    if (table) {
        renderTablePage();
        if (tableSearch) {
            tableSearch.addEventListener('input', function () {
                tablePage = 1;
                renderTablePage();
            });
        }
        if (pagerPages) {
            pagerPages.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-page]');
                if (!btn) {
                    return;
                }
                tablePage = parseInt(btn.getAttribute('data-page'), 10) || 1;
                renderTablePage();
            });
        }
    }
}());
</script>
