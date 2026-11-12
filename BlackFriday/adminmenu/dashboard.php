<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\BlackFriday\DiscountService;

require_once \dirname(__DIR__) . '/src/DiscountService.php';

if (!function_exists('bf_h')) {
    function bf_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('bf_dt_in')) {
    function bf_dt_in(string $v): string
    {
        $ts = $v !== '' ? \strtotime($v) : false;

        return $ts ? \date('Y-m-d\TH:i', $ts) : '';
    }
}
if (!function_exists('bf_dt_db')) {
    function bf_dt_db(string $v): ?string
    {
        $ts = \trim($v) !== '' ? \strtotime($v) : false;

        return $ts ? \date('Y-m-d H:i:s', $ts) : null;
    }
}

$db  = Shop::Container()->getDB();
$svc = new DiscountService($db);
$msg = [];
$err = [];

try {
    $svc->ensureSchema();
} catch (\Throwable $e) {
    $err[] = 'Tabellen konnten nicht angelegt werden: ' . $e->getMessage();
}

$action = (string)($_POST['bfAction'] ?? '');
if ($action !== '' && Form::validateToken()) {
    try {
        if ($action === 'settings') {
            $start = bf_dt_db((string)($_POST['dStart'] ?? ''));
            $end   = bf_dt_db((string)($_POST['dEnd'] ?? ''));
            if ($start !== null && $end !== null && \strtotime($end) <= \strtotime($start)) {
                $err[] = 'Das Ende muss nach dem Start liegen.';
            } else {
                $db->executeQueryPrepared(
                    'UPDATE ' . DiscountService::T_SETTINGS . ' SET
                        nActive = :a, dStart = :s, dEnd = :e,
                        nHeaderBar = :hb, nHomeBlock = :hm, nProductBlock = :pb, nTheme = :th,
                        cTopText = :tt, cHomeTitle = :ht, cHomeSub = :hs,
                        cButtonLabel = :bl, cButtonUrl = :bu, cInjectSelector = :is, dUpdated = NOW()',
                    [
                        'a'  => !empty($_POST['nActive']) ? 1 : 0,
                        's'  => $start,
                        'e'  => $end,
                        'hb' => !empty($_POST['nHeaderBar']) ? 1 : 0,
                        'hm' => !empty($_POST['nHomeBlock']) ? 1 : 0,
                        'pb' => !empty($_POST['nProductBlock']) ? 1 : 0,
                        'th' => !empty($_POST['nTheme']) ? 1 : 0,
                        'tt' => \mb_substr(\trim((string)($_POST['cTopText'] ?? '')), 0, 255),
                        'ht' => \mb_substr(\trim((string)($_POST['cHomeTitle'] ?? '')), 0, 190),
                        'hs' => \mb_substr(\trim((string)($_POST['cHomeSub'] ?? '')), 0, 512),
                        'bl' => \mb_substr(\trim((string)($_POST['cButtonLabel'] ?? '')), 0, 120),
                        'bu' => \mb_substr(\trim((string)($_POST['cButtonUrl'] ?? '')), 0, 512),
                        'is' => \mb_substr(\trim((string)($_POST['cInjectSelector'] ?? '')), 0, 255),
                    ]
                );
                $r     = $svc->applyAll();
                $msg[] = 'Einstellungen gespeichert. Rabatt aktiv für ' . $r['target'] . ' Artikel/Kundengruppen-Kombinationen.';
            }
        } elseif ($action === 'addRule') {
            $ids = \array_values(\array_unique(\array_filter(\array_map(
                'intval',
                \is_array($_POST['kKategorie'] ?? null) ? $_POST['kKategorie'] : [(int)($_POST['kKategorie'] ?? 0)]
            ))));
            $pct = (float)\str_replace(',', '.', (string)($_POST['fRabatt'] ?? '0'));
            if ($ids === [] || $pct <= 0 || $pct >= 100) {
                $err[] = 'Bitte mindestens eine Kategorie wählen und Rabatt zwischen 0 und 100 % angeben.';
            } else {
                foreach ($ids as $cat) {
                    $db->executeQueryPrepared(
                        'INSERT INTO ' . DiscountService::T_RULE . ' (kKategorie, fRabatt, nInclSub, nActive)
                         VALUES (:k, :p, :s, 1)
                         ON DUPLICATE KEY UPDATE fRabatt = VALUES(fRabatt), nInclSub = VALUES(nInclSub), nActive = 1',
                        ['k' => $cat, 'p' => \round($pct, 2), 's' => !empty($_POST['nInclSub']) ? 1 : 0]
                    );
                }
                $r     = $svc->applyAll();
                $msg[] = \count($ids) . ' Kategorie(n) mit ' . \round($pct, 2) . ' % gespeichert (' . $r['target'] . ' Kombinationen aktiv).';
            }
        } elseif ($action === 'saveRules') {
            $rows = \is_array($_POST['rule'] ?? null) ? $_POST['rule'] : [];
            foreach ($rows as $id => $row) {
                $id = (int)$id;
                if ($id <= 0 || !\is_array($row)) {
                    continue;
                }
                if (!empty($row['delete'])) {
                    $db->executeQueryPrepared('DELETE FROM ' . DiscountService::T_RULE . ' WHERE kRule = :id', ['id' => $id]);
                    continue;
                }
                $pct = (float)\str_replace(',', '.', (string)($row['fRabatt'] ?? '0'));
                if ($pct <= 0 || $pct >= 100) {
                    continue;
                }
                $db->executeQueryPrepared(
                    'UPDATE ' . DiscountService::T_RULE . ' SET fRabatt = :p, nInclSub = :s, nActive = :a WHERE kRule = :id',
                    ['p' => \round($pct, 2), 's' => !empty($row['nInclSub']) ? 1 : 0, 'a' => !empty($row['nActive']) ? 1 : 0, 'id' => $id]
                );
            }
            $r     = $svc->applyAll();
            $msg[] = 'Regeln aktualisiert (' . $r['target'] . ' Kombinationen aktiv, ' . $r['articles'] . ' Artikel neu berechnet).';
        } elseif ($action === 'start' || $action === 'stop') {
            $db->executeQueryPrepared(
                'UPDATE ' . DiscountService::T_SETTINGS . ' SET nActive = :a, dUpdated = NOW()',
                ['a' => $action === 'start' ? 1 : 0]
            );
            $r     = $svc->applyAll();
            $msg[] = $action === 'start'
                ? 'Aktion gestartet: ' . $r['target'] . ' Artikel/Kundengruppen-Kombinationen rabattiert.'
                : 'Aktion gestoppt, Originalpreise wiederhergestellt (' . $r['articles'] . ' Artikel).';
        } elseif ($action === 'apply') {
            $r     = $svc->applyAll();
            $msg[] = 'Neu angewendet: ' . $r['target'] . ' Kombinationen aktiv, ' . $r['articles'] . ' Artikel neu berechnet.';
        }
    } catch (\Throwable $e) {
        $err[] = 'Fehler: ' . $e->getMessage();
    }
}

$s       = $svc->getSettings();
$running = DiscountService::isRunning($s);
$rules   = $svc->getRules();

$langID = 0;
try {
    $langID = (int)($db->getSingleObject("SELECT kSprache FROM tsprache WHERE cShopStandard = 'Y' LIMIT 1")->kSprache ?? 0);
} catch (\Throwable $e) {
}
$cats = $db->getObjects(
    'SELECT k.kKategorie, k.nLevel, k.lft, k.rght, COALESCE(NULLIF(ks.cName, ""), k.cName) AS cName,
            (SELECT COUNT(*) FROM tkategorieartikel ka WHERE ka.kKategorie = k.kKategorie) AS nArtikel
       FROM tkategorie k
  LEFT JOIN tkategoriesprache ks ON ks.kKategorie = k.kKategorie AND ks.kSprache = :l
   ORDER BY k.lft ASC',
    ['l' => $langID]
);
$catNames = [];
$stack    = [];
foreach ($cats as $c) {
    $level         = \max(1, (int)$c->nLevel);
    $stack         = \array_slice($stack, 0, $level - 1);
    $stack[]       = (string)$c->cName;
    $c->cPath      = \implode(' › ', $stack);
    $catNames[(int)$c->kKategorie] = $c->cPath;
}
$stats = $db->getSingleObject(
    'SELECT COUNT(*) AS nRows, COUNT(DISTINCT kArtikel) AS nArt FROM ' . DiscountService::T_APPLIED
);
$lastApply = $svc->getMeta('last_apply');
?>
<style>
    .bf-adm .bf-status{display:inline-block;padding:.2rem .6rem;border-radius:999px;font-weight:700;font-size:.85rem}
    .bf-adm .bf-on{background:#111;color:#ffd400}
    .bf-adm .bf-off{background:#eee;color:#555}
    .bf-adm .table td{vertical-align:middle}
    .bf-adm .bf-pct{max-width:110px}
    .bf-adm .bf-catlist{max-height:380px;overflow:auto;border:1px solid #dee2e6;border-radius:6px;padding:4px 0;background:#fff}
    .bf-adm .bf-catrow{display:flex;align-items:center;gap:6px;margin:0;padding:3px 8px;cursor:pointer;font-weight:400}
    .bf-adm .bf-catrow:hover{background:#f5f5f5}
    .bf-adm .bf-catrow input{margin:0}
    .bf-adm .bf-catpath{margin-left:auto;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:45%}
</style>
<div class="bf-adm">
    <?php foreach ($msg as $m) { ?><div class="alert alert-success"><?= bf_h($m) ?></div><?php } ?>
    <?php foreach ($err as $m) { ?><div class="alert alert-danger"><?= bf_h($m) ?></div><?php } ?>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0">Black Friday</h3>
            <span class="bf-status <?= $running ? 'bf-on' : 'bf-off' ?>"><?= $running ? 'LÄUFT' : 'INAKTIV' ?></span>
        </div>
        <div class="card-body">
            <?php if (!$running) { ?>
                <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap">
                    <span>
                        <strong>Aktion läuft nicht</strong> – Rabatte sind im Warenkorb <u>nicht</u> aktiv.
                        <?php if ($s['active'] === 1) { ?>Grund: Zeitraum (Start/Ende) liegt außerhalb.<?php } ?>
                        Die Vorschau zeigt nur simulierte Preise.
                    </span>
                    <?php if ($s['active'] !== 1) { ?>
                        <form method="post" class="mb-0 mt-2 mt-md-0">
                            <?= Form::getTokenInput() ?>
                            <input type="hidden" name="bfAction" value="start">
                            <button class="btn btn-dark" type="submit">Aktion jetzt starten</button>
                        </form>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap">
                    <span><strong>Aktion läuft</strong> – Rabatte gelten in Shop, Warenkorb und Bestellung.</span>
                    <form method="post" class="mb-0 mt-2 mt-md-0" onsubmit="return confirm('Aktion wirklich stoppen?');">
                        <?= Form::getTokenInput() ?>
                        <input type="hidden" name="bfAction" value="stop">
                        <button class="btn btn-outline-danger" type="submit">Aktion stoppen</button>
                    </form>
                </div>
            <?php } ?>
            <p class="text-muted mb-1">
                Rabatte werden als echte JTL-Kategorierabatte gesetzt: Preis, Streichpreis, Warenkorb und Bestellung
                sind automatisch reduziert. Nach Wawi-Abgleichen stellt das Plugin die Rabatte alle 10&nbsp;Minuten wieder her;
                nach Aktionsende werden die ursprünglichen Wawi-Kategorierabatte zurückgesetzt.
            </p>
            <p class="mb-0">
                Aktuell rabattiert: <strong><?= (int)($stats->nArt ?? 0) ?></strong> Artikel
                (<?= (int)($stats->nRows ?? 0) ?> Artikel/Kundengruppen-Zeilen)
                <?= $lastApply !== '' ? ' · zuletzt angewendet: ' . bf_h($lastApply) : '' ?>
            </p>
            <small class="text-muted">Sonderpreise und Kundenpreise haben in JTL Vorrang – dort greift der Kategorierabatt nicht.</small>
            <p class="mt-2 mb-0">
                <a class="btn btn-sm btn-dark" target="_blank" rel="noopener" href="<?= bf_h(\rtrim((string)Shop::getURL(), '/') . '/?bfpreview=' . DiscountService::previewKey()) ?>">Vorschau im Shop öffnen</a>
                <small class="text-muted ml-2">Nur für dich (Cookie, 2&nbsp;Std.): Design + simulierte Preise, auch wenn die Aktion noch nicht läuft. Beenden: <code>?bfpreview=0</code></small>
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h4 class="mb-0">Kategorie-Rabatte</h4></div>
        <div class="card-body">
            <?php
            $rulePct = [];
            foreach ($rules as $r) {
                $rulePct[(int)$r->kKategorie] = (float)$r->fRabatt;
            }
            ?>
            <form method="post" class="form-row align-items-end mb-4" id="bfAddForm">
                <?= Form::getTokenInput() ?>
                <input type="hidden" name="bfAction" value="addRule">
                <div class="col-12 mb-2">
                    <label for="bfCatSearch"><strong>Kategorien</strong> <small class="text-muted">(Mehrfachauswahl – Klick auf eine Oberkategorie wählt alle Unterkategorien mit)</small></label>
                    <div class="d-flex flex-wrap mb-2" style="gap:.5rem">
                        <input class="form-control" id="bfCatSearch" type="search" placeholder="Filtern, z. B. H-Serie oder Wohnmobil" style="max-width:340px">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="bfSelVisible">Sichtbare auswählen</button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="bfSelNone">Auswahl aufheben</button>
                        <span class="align-self-center text-muted"><strong id="bfSelCount">0</strong> ausgewählt</span>
                    </div>
                    <div class="bf-catlist" id="bfCatList">
                        <?php foreach ($cats as $c) { $kid = (int)$c->kKategorie; ?>
                            <label class="bf-catrow" style="padding-left:<?= 8 + \max(0, (int)$c->nLevel - 1) * 18 ?>px"
                                   data-lft="<?= (int)$c->lft ?>" data-rght="<?= (int)$c->rght ?>"
                                   data-path="<?= bf_h(\mb_strtolower((string)$c->cPath)) ?>">
                                <input type="checkbox" name="kKategorie[]" value="<?= $kid ?>">
                                <span class="bf-catname"><?= bf_h((string)$c->cName) ?></span>
                                <small class="text-muted">(<?= (int)$c->nArtikel ?>)</small>
                                <?php if (isset($rulePct[$kid])) { ?><span class="badge badge-dark">-<?= bf_h((string)$rulePct[$kid]) ?>%</span><?php } ?>
                                <small class="bf-catpath text-muted"><?= bf_h((string)$c->cPath) ?></small>
                            </label>
                        <?php } ?>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <label for="bfPct"><strong>Rabatt %</strong></label>
                    <input class="form-control" id="bfPct" name="fRabatt" type="number" min="1" max="99" step="0.5" value="20" required>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="bfSub" name="nInclSub" value="1" checked>
                        <label class="custom-control-label" for="bfSub">inkl. Unterkategorien</label>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-primary btn-block" type="submit">Für Auswahl speichern</button>
                </div>
            </form>
            <script>
            (function () {
                var list = document.getElementById('bfCatList');
                if (!list) { return; }
                var rows = Array.prototype.slice.call(list.querySelectorAll('.bf-catrow'));
                var count = document.getElementById('bfSelCount');
                function update() {
                    count.textContent = list.querySelectorAll('input:checked').length;
                }
                list.addEventListener('change', function (e) {
                    var row = e.target.closest('.bf-catrow');
                    if (!row) { return; }
                    var l = +row.dataset.lft, r = +row.dataset.rght, on = e.target.checked;
                    if (r - l > 1) {
                        rows.forEach(function (x) {
                            if (+x.dataset.lft > l && +x.dataset.rght < r) {
                                x.querySelector('input').checked = on;
                            }
                        });
                    }
                    update();
                });
                document.getElementById('bfCatSearch').addEventListener('input', function () {
                    var q = this.value.trim().toLowerCase();
                    rows.forEach(function (x) {
                        x.style.display = q === '' || x.dataset.path.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
                document.getElementById('bfSelVisible').addEventListener('click', function () {
                    rows.forEach(function (x) {
                        if (x.style.display !== 'none') { x.querySelector('input').checked = true; }
                    });
                    update();
                });
                document.getElementById('bfSelNone').addEventListener('click', function () {
                    rows.forEach(function (x) { x.querySelector('input').checked = false; });
                    update();
                });
                document.getElementById('bfAddForm').addEventListener('submit', function (e) {
                    if (!list.querySelector('input:checked')) {
                        e.preventDefault();
                        alert('Bitte mindestens eine Kategorie auswählen.');
                    }
                });
            })();
            </script>

            <?php if ($rules === []) { ?>
                <p class="text-muted mb-0">Noch keine Kategorie-Rabatte angelegt.</p>
            <?php } else { ?>
                <form method="post">
                    <?= Form::getTokenInput() ?>
                    <input type="hidden" name="bfAction" value="saveRules">
                    <table class="table table-sm">
                        <thead>
                        <tr><th>Kategorie</th><th>Rabatt %</th><th>Unterkat.</th><th>Aktiv</th><th>Löschen</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rules as $r) { $id = (int)$r->kRule; ?>
                            <tr>
                                <td><?= bf_h($catNames[(int)$r->kKategorie] ?? ('#' . (int)$r->kKategorie)) ?> <small class="text-muted">#<?= (int)$r->kKategorie ?></small></td>
                                <td><input class="form-control form-control-sm bf-pct" type="number" min="1" max="99" step="0.5" name="rule[<?= $id ?>][fRabatt]" value="<?= bf_h((string)(float)$r->fRabatt) ?>"></td>
                                <td><input type="checkbox" name="rule[<?= $id ?>][nInclSub]" value="1"<?= (int)$r->nInclSub === 1 ? ' checked' : '' ?>></td>
                                <td><input type="checkbox" name="rule[<?= $id ?>][nActive]" value="1"<?= (int)$r->nActive === 1 ? ' checked' : '' ?>></td>
                                <td><input type="checkbox" name="rule[<?= $id ?>][delete]" value="1"></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <button class="btn btn-primary" type="submit">Regeln speichern &amp; anwenden</button>
                </form>
            <?php } ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h4 class="mb-0">Aktion &amp; Design</h4></div>
        <div class="card-body">
            <form method="post">
                <?= Form::getTokenInput() ?>
                <input type="hidden" name="bfAction" value="settings">
                <div class="custom-control custom-checkbox mb-3">
                    <input type="checkbox" class="custom-control-input" id="bfActive" name="nActive" value="1"<?= $s['active'] === 1 ? ' checked' : '' ?>>
                    <label class="custom-control-label" for="bfActive"><strong>Black Friday aktiv</strong> (Rabatte + Design)</label>
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label for="bfStart">Start (optional)</label>
                        <input class="form-control" type="datetime-local" id="bfStart" name="dStart" value="<?= bf_h(bf_dt_in($s['start'])) ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="bfEnd">Ende (optional, für Countdown)</label>
                        <input class="form-control" type="datetime-local" id="bfEnd" name="dEnd" value="<?= bf_h(bf_dt_in($s['end'])) ?>">
                    </div>
                </div>
                <div class="form-row mb-2">
                    <?php foreach (['nHeaderBar' => ['headerBar', 'Header-Leiste'], 'nHomeBlock' => ['homeBlock', 'Startseiten-Bereich'], 'nProductBlock' => ['productBlock', 'Deal-Box auf Produktseite'], 'nTheme' => ['theme', 'Theme-Farben (Preise/Ribbons)']] as $name => [$key, $label]) { ?>
                        <div class="col-md-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="bf<?= $name ?>" name="<?= $name ?>" value="1"<?= (int)$s[$key] === 1 ? ' checked' : '' ?>>
                                <label class="custom-control-label" for="bf<?= $name ?>"><?= bf_h($label) ?></label>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="form-group">
                    <label for="bfTop">Header-Text</label>
                    <input class="form-control" id="bfTop" name="cTopText" maxlength="255" value="<?= bf_h($s['topText']) ?>">
                    <small class="form-text text-muted"><code>{max}</code> wird durch den höchsten Rabatt ersetzt.</small>
                </div>
                <div class="form-row">
                    <div class="col-md-4 form-group">
                        <label for="bfHt">Startseite: Titel</label>
                        <input class="form-control" id="bfHt" name="cHomeTitle" maxlength="190" value="<?= bf_h($s['homeTitle']) ?>">
                    </div>
                    <div class="col-md-8 form-group">
                        <label for="bfHs">Startseite: Untertitel</label>
                        <input class="form-control" id="bfHs" name="cHomeSub" maxlength="512" value="<?= bf_h($s['homeSub']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col-md-4 form-group">
                        <label for="bfBl">Button-Text</label>
                        <input class="form-control" id="bfBl" name="cButtonLabel" maxlength="120" value="<?= bf_h($s['buttonLabel']) ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="bfBu">Button-Link (leer = Startseiten-Bereich)</label>
                        <input class="form-control" id="bfBu" name="cButtonUrl" maxlength="512" value="<?= bf_h($s['buttonUrl']) ?>" placeholder="/black-friday oder https://…">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="bfIs">Startseite: einfügen nach (CSS-Selektor)</label>
                        <input class="form-control" id="bfIs" name="cInjectSelector" maxlength="255" value="<?= bf_h($s['injectSelector']) ?>">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Speichern &amp; anwenden</button>
            </form>
        </div>
    </div>

    <form method="post">
        <?= Form::getTokenInput() ?>
        <input type="hidden" name="bfAction" value="apply">
        <button class="btn btn-outline-secondary" type="submit">Rabatte jetzt neu anwenden</button>
    </form>
</div>
