<?php

declare(strict_types=1);

use Plugin\MesseList\MesseListService;

require_once \dirname(__DIR__) . '/src/MesseListService.php';

MesseListService::ensureTables();
MesseListService::ensureTrgManualProducts();

/**
 * @param array<int|string, mixed> $tabNames
 * @param array<int|string, mixed> $groupNames
 * @param array<int|string, mixed> $seriesBy
 * @return list<array{name:string,groups:list<array{name:string,series:list<string>}>}>
 */
$parseTabs = static function (array $tabNames, array $groupNames, array $seriesBy): array {
    $tabs = [];
    foreach ($tabNames as $t => $tabName) {
        $t = (int)$t;
        $gnames = $groupNames[$t] ?? [];
        if (!\is_array($gnames)) {
            $gnames = [];
        }
        $groups = [];
        foreach ($gnames as $g => $gname) {
            $g = (int)$g;
            $series = $seriesBy[$t][$g] ?? [];
            if (!\is_array($series)) {
                $series = [];
            }
            $groups[] = [
                'name'   => (string)$gname,
                'series' => \array_map('strval', $series),
            ];
        }
        $tabs[] = ['name' => (string)$tabName, 'groups' => $groups];
    }

    return $tabs;
};

$saved = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $tabNames = $_POST['ml_tab_name'] ?? [];
    $groupNames = $_POST['ml_group_name'] ?? [];
    $seriesBy = $_POST['ml_group_series'] ?? [];
    if (!\is_array($tabNames)) {
        $tabNames = [];
    }
    if (!\is_array($groupNames)) {
        $groupNames = [];
    }
    if (!\is_array($seriesBy)) {
        $seriesBy = [];
    }
    $parsed = $parseTabs($tabNames, $groupNames, $seriesBy);

    $moves = $_POST['ml_group_move'] ?? [];
    if (\is_array($moves)) {
        $toMove = [];
        foreach ($moves as $t => $gs) {
            if (!\is_array($gs)) {
                continue;
            }
            foreach ($gs as $g => $dest) {
                $t = (int)$t;
                $g = (int)$g;
                $dest = \trim((string)$dest);
                if ($dest === '' || (int)$dest === $t || !isset($parsed[$t]['groups'][$g])) {
                    continue;
                }
                $toMove[] = ['fromT' => $t, 'fromG' => $g, 'dest' => (int)$dest];
            }
        }
        \usort($toMove, static function (array $a, array $b): int {
            return [$b['fromT'], $b['fromG']] <=> [$a['fromT'], $a['fromG']];
        });
        foreach ($toMove as $m) {
            if (!isset($parsed[$m['fromT']]['groups'][$m['fromG']], $parsed[$m['dest']])) {
                continue;
            }
            $grp = $parsed[$m['fromT']]['groups'][$m['fromG']];
            \array_splice($parsed[$m['fromT']]['groups'], $m['fromG'], 1);
            $parsed[$m['dest']]['groups'][] = $grp;
        }
    }

    if (isset($_POST['ml_del_group'])) {
        $parts = \explode(':', (string)$_POST['ml_del_group'], 2);
        $t = (int)($parts[0] ?? -1);
        $g = (int)($parts[1] ?? -1);
        if (isset($parsed[$t]['groups'][$g])) {
            \array_splice($parsed[$t]['groups'], $g, 1);
        }
    }
    if (isset($_POST['ml_del_tab'])) {
        $t = (int)$_POST['ml_del_tab'];
        if (isset($parsed[$t])) {
            \array_splice($parsed, $t, 1);
        }
    }
    if (isset($_POST['ml_add_group'])) {
        $t = (int)$_POST['ml_add_group'];
        if (!isset($parsed[$t])) {
            $parsed[] = ['name' => 'Neuer Tab', 'groups' => []];
            $t = \count($parsed) - 1;
        }
        $parsed[$t]['groups'][] = ['name' => 'Neue Gruppe', 'series' => []];
    }
    if (!empty($_POST['ml_add_tab'])) {
        $parsed[] = ['name' => 'Neuer Tab', 'groups' => [['name' => 'Neue Gruppe', 'series' => []]]];
    }

    foreach ($parsed as &$tab) {
        $tab['name'] = \trim((string)($tab['name'] ?? '')) !== '' ? \trim((string)$tab['name']) : 'Tab';
        foreach ($tab['groups'] as &$g) {
            $g['name'] = \trim((string)($g['name'] ?? '')) !== '' ? \trim((string)$g['name']) : 'Gruppe';
        }
        unset($g);
    }
    unset($tab);

    MesseListService::saveTabs($parsed);
    $extraRaw = (string)($_POST['ml_extra_series'] ?? '');
    $extra = \preg_split('/[,\s;]+/', $extraRaw) ?: [];
    MesseListService::saveExtraSeries(\array_map('strval', $extra));
    $artRaw = $_POST['ml_extra_artnr'] ?? [];
    $artNrs = \is_array($artRaw)
        ? \array_map('strval', $artRaw)
        : (\preg_split('/[,\s;]+/', (string)$artRaw) ?: []);
    MesseListService::saveExtraArtNr(\array_map('strval', $artNrs));

    $manuals = MesseListService::getManualProducts();
    if (isset($_POST['ml_del_manual'])) {
        $manuals = MesseListService::removeManualProduct($manuals, (string)$_POST['ml_del_manual']);
    }
    $postedManual = $_POST['ml_manual'] ?? null;
    if (\is_array($postedManual)) {
        $files = $_FILES['ml_manual_img'] ?? null;
        $manuals = MesseListService::applyManualPost(
            $manuals,
            $postedManual,
            \is_array($files) ? $files : null
        );
    }
    if (!empty($_POST['ml_manual_add'])) {
        $newFile = $_FILES['ml_new_img'] ?? null;
        $manuals = MesseListService::addManualFromFields(
            $manuals,
            $_POST,
            \is_array($newFile) ? $newFile : null
        );
    }
    MesseListService::saveManualProducts($manuals);
    $saved = true;
}

if (!empty($_GET['ml_offer'])) {
    $quote = MesseListService::getQuote((int)$_GET['ml_offer']);
    if (\is_array($quote)) {
        echo MesseListService::renderQuoteHtml($quote);
        return;
    }
}

$regen = !empty($_GET['ml_new_token']);
$token = MesseListService::getToken($regen);
$publicUrl = MesseListService::publicUrl($token);
$tabs = MesseListService::getTabs();
$available = MesseListService::listAvailableSeries();
\sort($available, \SORT_NATURAL);
$products = [];
$loadError = '';
try {
    $products = MesseListService::loadDsLagerProducts();
} catch (\Throwable $e) {
    $loadError = $e->getMessage();
}
if ($loadError === '' && !empty(MesseListService::$lastLoadError)) {
    $loadError = (string)MesseListService::$lastLoadError;
}
$selectableProducts = MesseListService::listSelectableProducts();
$manuals = MesseListService::getManualProducts();
$quotes = MesseListService::getQuotes();
$count = \count($products);

$h = static function (string $s): string {
    return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
};
require __DIR__ . '/../templates/admin_dashboard.php';
return;
?>
<div class="card border-primary mb-4">
    <div class="card-body py-3">
        <h5 class="card-title h6 mb-2">Öffentlicher Lese-Link (Token)</h5>
        <p class="small text-muted mb-2">
            Öffentliche Seite: Tabs → Gruppen → Serien.
        </p>
        <div class="input-group input-group-sm mt-1" style="max-width:820px">
            <input type="text" class="form-control font-monospace" readonly
                   value="<?= $h($publicUrl) ?>" id="ml-public-url">
            <button type="button" class="btn btn-outline-secondary"
                    onclick="navigator.clipboard.writeText(document.getElementById('ml-public-url').value)">Kopieren</button>
            <a class="btn btn-outline-primary" href="<?= $h($publicUrl) ?>" target="_blank" rel="noopener">Öffnen</a>
        </div>
        <p class="text-muted mb-0 mt-2">
            Token nur an vertrauenswürdige Personen weitergeben.
            <a href="?ml_new_token=1"
               onclick="return confirm('Alten Link ungültig machen und neuen Token erzeugen?');">Neuen Token erzeugen</a>
        </p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title h6">Tabs, Gruppen und Serien</h5>
        <p class="small text-muted">Tab anlegen, Gruppen darin belassen oder per Auswahl in einen anderen Tab verschieben, dann Speichern.</p>
        <?php if ($saved): ?>
            <div class="alert alert-success py-2">Gespeichert.</div>
        <?php endif; ?>
        <form method="post">
            <?php foreach ($tabs as $t => $tab): ?>
                <div class="border rounded p-3 mb-4" style="background:#f8f9fa">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <strong class="small text-muted" style="min-width:48px">Tab <?= (int)$t + 1 ?></strong>
                        <input type="text" class="form-control form-control-sm" name="ml_tab_name[<?= (int)$t ?>]"
                               value="<?= $h((string)$tab['name']) ?>" placeholder="z. B. PKW" maxlength="80">
                        <button type="submit" name="ml_del_tab" value="<?= (int)$t ?>"
                                class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Diesen Tab und seine Gruppen löschen?');">Tab löschen</button>
                    </div>
                    <?php foreach ($tab['groups'] as $g => $grp): ?>
                        <div class="border rounded bg-white p-3 mb-2">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <label class="small text-muted mb-0" style="min-width:70px">Gruppe <?= (int)$g + 1 ?></label>
                                <input type="text" class="form-control form-control-sm"
                                       name="ml_group_name[<?= (int)$t ?>][<?= (int)$g ?>]"
                                       value="<?= $h((string)$grp['name']) ?>" placeholder="z. B. DS Serie" maxlength="80">
                                <?php if (\count($tabs) > 1): ?>
                                    <select class="form-select form-select-sm" name="ml_group_move[<?= (int)$t ?>][<?= (int)$g ?>]" style="max-width:220px">
                                        <option value="">In diesem Tab</option>
                                        <?php foreach ($tabs as $ot => $otab): ?>
                                            <?php if ((int)$ot === (int)$t) { continue; } ?>
                                            <option value="<?= (int)$ot ?>">→ <?= $h((string)$otab['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                                <button type="submit" name="ml_del_group" value="<?= (int)$t ?>:<?= (int)$g ?>"
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Diese Gruppe löschen?');">Löschen</button>
                            </div>
                            <div class="d-flex flex-wrap" style="gap:8px 14px">
                                <?php foreach ($available as $s): ?>
                                    <label class="form-check form-check-inline m-0">
                                        <input class="form-check-input" type="checkbox"
                                               name="ml_group_series[<?= (int)$t ?>][<?= (int)$g ?>][]"
                                               value="<?= $h($s) ?>"<?= \in_array($s, $grp['series'], true) ? ' checked' : '' ?>>
                                        <span class="form-check-label"><?= $h($s) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="ml_add_group" value="<?= (int)$t ?>" class="btn btn-outline-secondary btn-sm">Gruppe in diesen Tab</button>
                </div>
            <?php endforeach; ?>
            <div class="mt-3 mb-2">
                <label class="form-label small mb-1">Weitere Serien (Komma, z. B. LT3,LT4,LT5)</label>
                <input type="text" class="form-control form-control-sm" name="ml_extra_series"
                       value="<?= $h(\implode(',', MesseListService::getExtraSeries())) ?>"
                       placeholder="LT3, LT4, LT5" maxlength="200">
            </div>
            <div class="mb-3">
                <label class="form-label small mb-1">Weitere Art.-Nr. (Komma, z. B. 20277,20278,20279)</label>
                <input type="text" class="form-control form-control-sm" name="ml_extra_artnr"
                       value="<?= $h(\implode(',', MesseListService::getExtraArtNr())) ?>"
                       placeholder="20277, 20278, 20279" maxlength="400">
            </div>
            <button type="submit" name="ml_add_tab" value="1" class="btn btn-outline-secondary btn-sm">Tab hinzufügen</button>
            <button type="submit" name="ml_save_tabs" value="1" class="btn btn-primary btn-sm">Speichern</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title h6">Lagerartikel (gewählte Serien)</h5>
        <p class="small text-muted"><?= (int)$count ?> Artikel gefunden.</p>
        <?php if ($count === 0): ?>
            <div class="alert alert-warning mb-0">
                Keine passenden Lagerartikel gefunden.
                <?php if (!empty(MesseListService::$lastLoadError)): ?>
                    <br><span class="font-monospace small"><?= $h((string)MesseListService::$lastLoadError) ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Serie</th>
                            <th>Art.-Nr.</th>
                            <th>Name</th>
                            <th class="text-end">Lager</th>
                            <th class="text-end">Preis brutto</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= $h((string)$p['cSerie']) ?></td>
                            <td class="font-monospace"><?= $h((string)$p['cArtNr']) ?></td>
                            <td><?= $h((string)$p['cName']) ?></td>
                            <td class="text-end"><?= \number_format((float)$p['nStock'], 0, ',', '.') ?></td>
                            <td class="text-end"><?= \number_format((float)$p['fGross'], 2, ',', '.') ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
