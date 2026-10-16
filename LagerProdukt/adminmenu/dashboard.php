<?php

declare(strict_types=1);

require_once \dirname(__DIR__) . '/src/LagerProduktService.php';

use JTL\Helpers\Form;
use Plugin\LagerProdukt\LagerProduktService;

if (!\function_exists('lp_h')) {
    function lp_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

LagerProduktService::ensureTables();
LagerProduktService::ensureAllShopLinks();
$message = '';
$error   = '';

$tokenOk = \class_exists(Form::class) && \method_exists(Form::class, 'validateToken')
    ? Form::validateToken()
    : ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

$pages = LagerProduktService::listPages();
$pageId = (int)($_GET['page'] ?? ($_POST['kPage'] ?? 0));
if ($pageId <= 0 && $pages !== []) {
    $pageId = (int)$pages[0]['kID'];
}

if ($tokenOk && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!empty($_POST['lpAddPage'])) {
        $id = LagerProduktService::createPage(
            (string)($_POST['cName'] ?? ''),
            (string)($_POST['cSlug'] ?? '')
        );
        if ($id > 0) {
            $message = 'Seite angelegt.';
            $pageId  = $id;
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Seite konnte nicht angelegt werden.';
        }
    } elseif (!empty($_POST['lpDelPage'])) {
        if (LagerProduktService::deletePage((int)($_POST['kPage'] ?? 0))) {
            $message = 'Seite gelöscht.';
            $pages   = LagerProduktService::listPages();
            $pageId  = $pages[0]['kID'] ?? 0;
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Löschen fehlgeschlagen.';
        }
    } elseif (!empty($_POST['lpAddGroup'])) {
        $pageId = (int)($_POST['kPage'] ?? $pageId);
        $gid = LagerProduktService::createGroup($pageId, (string)($_POST['cGroupName'] ?? ''));
        if ($gid > 0) {
            $message = 'Gruppe angelegt.';
            $_GET['group'] = (string)$gid;
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Gruppe konnte nicht angelegt werden.';
        }
    } elseif (!empty($_POST['lpDelGroup'])) {
        $pageId = (int)($_POST['kPage'] ?? $pageId);
        if (LagerProduktService::deleteGroup((int)($_POST['kGroup'] ?? 0), $pageId)) {
            $message = 'Gruppe gelöscht. Artikel bleiben ohne Gruppe.';
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Gruppe konnte nicht gelöscht werden.';
        }
    } elseif (!empty($_POST['lpAddSku'])) {
        $pageId = (int)($_POST['kPage'] ?? $pageId);
        $res    = LagerProduktService::addSkus(
            $pageId,
            (string)($_POST['cArtNr'] ?? ''),
            (int)($_POST['kGroup'] ?? 0)
        );
        $parts  = [];
        if ($res['added'] !== []) {
            $parts[] = \count($res['added']) . ' hinzugefügt';
        }
        if ($res['exists'] !== []) {
            $parts[] = \count($res['exists']) . ' bereits vorhanden';
        }
        if ($res['missing'] !== []) {
            $error = 'Nicht gefunden: ' . \implode(', ', $res['missing']);
        }
        if ($parts !== []) {
            $message = \implode(' · ', $parts) . ($res['added'] !== [] ? ': ' . \implode(', ', $res['added']) : '');
        } elseif ($error === '') {
            $error = 'Keine Art.-Nr. hinzugefügt.';
        }
    } elseif (!empty($_POST['lpSetMain'])) {
        $pageId = (int)($_POST['kPage'] ?? $pageId);
        $gid    = (int)($_POST['kGroup'] ?? 0);
        $itemId = (int)$_POST['lpSetMain'];
        if (LagerProduktService::setMainItem($pageId, $gid, $itemId)) {
            $message = 'Hauptartikel festgelegt.';
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Hauptartikel konnte nicht gesetzt werden.';
        }
    } elseif (!empty($_POST['lpDelSku'])) {
        $itemId = (int)($_POST['kItem'] ?? $_POST['lpDelSku'] ?? 0);
        if (LagerProduktService::removeSku($itemId)) {
            $message = 'Art.-Nr. entfernt.';
        } else {
            $error = 'Entfernen fehlgeschlagen.';
        }
        $pageId = (int)($_POST['kPage'] ?? $pageId);
    } elseif (!empty($_POST['lpDelSelected'])) {
        $pageId = (int)($_POST['kPage'] ?? $pageId);
        $ids    = \array_map('intval', (array)($_POST['kItems'] ?? []));
        $n      = LagerProduktService::removeSkus($ids, $pageId);
        if ($n > 0) {
            $message = $n . ' Artikel entfernt.';
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Keine Artikel ausgewählt.';
        }
    } elseif (!empty($_POST['lpClearGroup'])) {
        $pageId = (int)($_POST['kPage'] ?? $pageId);
        $gid    = (int)($_POST['kGroup'] ?? -1);
        $n      = LagerProduktService::clearGroupItems($pageId, $gid);
        if ($n > 0) {
            $message = $n . ' Artikel aus der Gruppe entfernt.';
        } else {
            $error = LagerProduktService::$lastError !== '' ? LagerProduktService::$lastError : 'Keine Artikel in dieser Gruppe.';
        }
    }
    $pages = LagerProduktService::listPages();
}

$page    = LagerProduktService::getPage($pageId);
$groups  = $page !== null ? LagerProduktService::listGroups($pageId) : [];
$items   = $page !== null ? LagerProduktService::listItems($pageId) : [];
$groupId = (int)($_GET['group'] ?? ($_POST['kGroup'] ?? 0));
$csrf    = \class_exists(Form::class) && \method_exists(Form::class, 'getTokenInput')
    ? Form::getTokenInput()
    : '';
$itemsByGroup = [];
foreach ($items as $it) {
    $itemsByGroup[(int)($it['kGroup'] ?? 0)][] = $it;
}
?>
<style>
.lpa{--ink:#181a15;--acid:#c9fb38;--paper:#f3f2ec;color:var(--ink)}
.lpa-hero{display:flex;justify-content:space-between;align-items:end;gap:24px;padding:28px 32px;margin-bottom:22px;background:var(--ink);color:#fff;border-radius:4px;overflow:hidden;position:relative}
.lpa-hero:after{content:"";position:absolute;width:180px;height:180px;border:1px solid rgba(201,251,56,.35);border-radius:50%;right:-35px;top:-75px}
.lpa-kicker{color:var(--acid);font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.lpa-hero h2{font-family:inherit;font-size:2rem;margin:6px 0 4px;color:#fff}.lpa-hero p{margin:0;color:rgba(255,255,255,.62)}
.lpa-stat{position:relative;z-index:1;font-family:inherit;font-size:2.5rem;line-height:1;color:var(--acid);white-space:nowrap}.lpa-stat small{display:block;font-family:inherit;font-size:.7rem;color:#fff;text-align:right;margin-top:5px}
.lpa-alert{border:0;border-left:4px solid;padding:12px 16px;margin-bottom:16px}.lpa-alert-ok{border-color:#70ad32;background:#f0f8e9}.lpa-alert-bad{border-color:#d1922f;background:#fff7e6}
.lpa-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:20px}
.lpa-panel{background:#fff;border:1px solid #dedfd8;border-radius:4px;padding:20px;box-shadow:0 8px 28px rgba(24,26,21,.045)}
.lpa-panel h4,.lpa-panel h5{font-weight:800}.lpa-tabs{display:grid;gap:7px;margin-bottom:18px}
.lpa-tab{display:flex;justify-content:space-between;align-items:center;padding:11px 12px;color:var(--ink);background:var(--paper);border-left:3px solid transparent;text-decoration:none!important}
.lpa-tab:hover,.lpa-tab.is-active{color:var(--ink);border-color:var(--acid);background:#e9f5c8}.lpa-tab b{font-size:.72rem;background:#fff;padding:3px 7px;border-radius:12px}
.lpa-label{display:block;margin:0 0 6px;font-size:.7rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#64675f}
.lpa .form-control{border-radius:2px;border-color:#d5d6cf;min-height:42px}.lpa .form-control:focus{border-color:var(--ink);box-shadow:0 0 0 3px rgba(201,251,56,.45)}
.lpa-btn{border:0;border-radius:2px;background:var(--ink);color:#fff!important;font-weight:800;padding:10px 16px}.lpa-btn:hover{background:#30332a}.lpa-btn-acid{background:var(--acid);color:var(--ink)!important}
.lpa-pagehead{display:flex;justify-content:space-between;gap:20px;align-items:center;padding-bottom:16px;border-bottom:1px solid #e3e4dd;margin-bottom:18px}.lpa-pagehead h3{font-family:inherit;margin:0;font-size:1.65rem}
.lpa-url{font-size:.8rem;color:#73766d}.lpa-url a{color:inherit;text-decoration:underline}.lpa-bulk{background:var(--paper);padding:18px;margin-bottom:20px}.lpa-bulk textarea{font-family:inherit;background:#fff}
.lpa-table{margin:0}.lpa-table thead th{border-top:0;border-bottom:1px solid #cfd1c8;color:#686b62;font-size:.69rem;letter-spacing:.08em;text-transform:uppercase}
.lpa-table td{vertical-align:middle}.lpa-sku{font-family:inherit;font-weight:700}.lpa-empty{padding:28px;text-align:center;background:var(--paper);color:#777a72}
.lpa-bulkbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.lpa-main-badge{display:inline-block;padding:5px 8px;border:1px solid #9fca29;background:var(--acid);color:var(--ink);font-size:.68rem;font-weight:800;text-transform:uppercase}
@media(max-width:900px){.lpa-layout{grid-template-columns:1fr}.lpa-hero{padding:22px}.lpa-stat{display:none}.lpa-pagehead{align-items:flex-start;flex-direction:column}}
</style>
<div class="lpa">
    <div class="lpa-hero">
        <div>
            <div class="lpa-kicker">LagerProdukt Verwaltung</div>
            <h2>Seiten &amp; Produkte</h2>
            <p>Seiten anlegen, Gruppen wie „AH Gruppe“ erstellen und Artikel zuordnen.</p>
        </div>
        <div class="lpa-stat"><?= \count($pages) ?><small>aktive Seiten</small></div>
    </div>

        <?php if ($message !== '') { ?>
            <div class="lpa-alert lpa-alert-ok"><?= lp_h($message) ?></div>
        <?php } ?>
        <?php if ($error !== '') { ?>
            <div class="lpa-alert lpa-alert-bad"><?= lp_h($error) ?></div>
        <?php } ?>

    <div class="lpa-layout">
        <aside class="lpa-panel">
            <h5 class="mb-3">Lagerseiten</h5>
            <div class="lpa-tabs">
                <?php foreach ($pages as $p) { ?>
                    <a href="?page=<?= (int)$p['kID'] ?>"
                       class="lpa-tab<?= (int)$p['kID'] === $pageId ? ' is-active' : '' ?>">
                        <span><?= lp_h($p['cName']) ?></span><b><?= (int)$p['nItems'] ?></b>
                    </a>
                <?php } ?>
            </div>
            <hr>
            <h5 class="mb-3">Neue Seite</h5>
            <form method="post">
                <?= $csrf ?>
                <label class="lpa-label" for="lpaName">Seitenname</label>
                <input id="lpaName" type="text" name="cName" class="form-control mb-2" placeholder="z. B. Familie-Serie" required>
                <label class="lpa-label" for="lpaSlug">URL-Slug</label>
                <input id="lpaSlug" type="text" name="cSlug" class="form-control mb-3" placeholder="Optional">
                <button type="submit" name="lpAddPage" value="1" class="lpa-btn lpa-btn-acid w-100">+ Seite anlegen</button>
            </form>
        </aside>

        <main class="lpa-panel">
        <?php if ($page !== null) { ?>
            <div class="lpa-pagehead">
                <div>
                    <span class="lpa-label">Aktive Seite</span>
                    <h3><?= lp_h($page['cName']) ?></h3>
                    <div class="lpa-url"><?= lp_h(LagerProduktService::pageUrl($page['cSlug'])) ?></div>
                </div>
                <a class="lpa-btn" href="<?= lp_h(LagerProduktService::pageUrl($page['cSlug'])) ?>" target="_blank" rel="noopener">Seite ansehen ↗</a>
            </div>

            <form method="post" class="mb-3">
                <?= $csrf ?>
                <input type="hidden" name="kPage" value="<?= $pageId ?>">
                <label class="lpa-label" for="lpaGroup">Neue Gruppe</label>
                <div class="d-flex" style="gap:8px">
                    <input id="lpaGroup" type="text" name="cGroupName" class="form-control" placeholder="z. B. AH Gruppe" required>
                    <button type="submit" name="lpAddGroup" value="1" class="lpa-btn lpa-btn-acid">+ Gruppe</button>
                </div>
            </form>

            <?php if ($groups !== []) { ?>
                <div class="mb-3" style="display:flex;flex-wrap:wrap;gap:8px">
                    <a href="?page=<?= $pageId ?>" class="lpa-tab<?= $groupId === 0 ? ' is-active' : '' ?>">Alle</a>
                    <?php foreach ($groups as $g) { ?>
                        <a href="?page=<?= $pageId ?>&group=<?= (int)$g['kID'] ?>"
                           class="lpa-tab<?= $groupId === (int)$g['kID'] ? ' is-active' : '' ?>">
                            <?= lp_h($g['cName']) ?> <b><?= (int)$g['nItems'] ?></b>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>

            <form method="post" class="lpa-bulk">
                <?= $csrf ?>
                <input type="hidden" name="kPage" value="<?= $pageId ?>">
                <label class="lpa-label" for="lpGroupSel">Gruppe wählen</label>
                <select id="lpGroupSel" name="kGroup" class="form-control mb-2">
                    <option value="0">Ohne Gruppe</option>
                    <?php foreach ($groups as $g) { ?>
                        <option value="<?= (int)$g['kID'] ?>"<?= $groupId === (int)$g['kID'] ? ' selected' : '' ?>>
                            <?= lp_h($g['cName']) ?>
                        </option>
                    <?php } ?>
                </select>
                <label class="lpa-label" for="lpArtNr">Artikelnummern in diese Gruppe einfügen</label>
                <textarea id="lpArtNr" name="cArtNr" class="form-control" rows="6" required
                          placeholder="20345&#10;20346&#10;20347"></textarea>
                <div class="d-flex justify-content-between align-items-center flex-wrap mt-2" style="gap:10px">
                    <small class="text-muted">Eine SKU pro Zeile – auch Komma, Leerzeichen oder Semikolon möglich.</small>
                    <button type="submit" name="lpAddSku" value="1" class="lpa-btn lpa-btn-acid">Produkte hinzufügen</button>
                </div>
            </form>

            <?php
            $showGroups = $groups;
            if ($groupId > 0) {
                $showGroups = \array_values(\array_filter($groups, static fn (array $g): bool => (int)$g['kID'] === $groupId));
            }
            $showUngrouped = $groupId === 0 && !empty($itemsByGroup[0]);
            ?>
            <?php if ($items === []) { ?>
                <div class="lpa-empty">Noch keine Artikelnummer auf dieser Seite.</div>
            <?php } else { ?>
                <?php foreach ($showGroups as $g) { ?>
                    <?php $gItems = $itemsByGroup[(int)$g['kID']] ?? []; ?>
                    <?php if ($gItems === [] && $groupId === 0) { continue; } ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 mt-3">
                        <h5 class="mb-0"><?= lp_h($g['cName']) ?></h5>
                        <form method="post" onsubmit="return confirm('Gruppe löschen? Artikel bleiben ohne Gruppe.');">
                            <?= $csrf ?>
                            <input type="hidden" name="kPage" value="<?= $pageId ?>">
                            <input type="hidden" name="kGroup" value="<?= (int)$g['kID'] ?>">
                            <button type="submit" name="lpDelGroup" value="1" class="btn btn-sm btn-outline-danger">Gruppe löschen</button>
                        </form>
                    </div>
                    <?php if ($gItems === []) { ?>
                        <div class="lpa-empty mb-3">Keine Artikel in dieser Gruppe.</div>
                    <?php } else { ?>
                        <form method="post" class="mb-3">
                            <?= $csrf ?>
                            <input type="hidden" name="kPage" value="<?= $pageId ?>">
                            <input type="hidden" name="kGroup" value="<?= (int)$g['kID'] ?>">
                            <div class="table-responsive">
                                <table class="table lpa-table">
                                    <thead>
                                        <tr>
                                            <th style="width:36px">
                                                <input type="checkbox" class="lpa-check-all" title="Alle auswählen">
                                            </th>
                                            <th>Art.-Nr.</th>
                                            <th>Name</th>
                                            <th>Hauptartikel</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($gItems as $it) { ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="kItems[]" value="<?= (int)$it['kID'] ?>">
                                            </td>
                                            <td class="lpa-sku"><?= lp_h((string)$it['cArtNr']) ?></td>
                                            <td>
                                                <?= lp_h((string)($it['cName'] ?? '')) ?>
                                                <?php if (empty($it['found'])) { ?>
                                                    <span class="text-danger">nicht gefunden</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($it['nMain'])) { ?>
                                                    <span class="lpa-main-badge">Aktiv</span>
                                                <?php } else { ?>
                                                    <button type="submit" name="lpSetMain" value="<?= (int)$it['kID'] ?>" class="btn btn-sm btn-outline-dark">Als Hauptartikel</button>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <button type="submit" name="lpDelSku" value="<?= (int)$it['kID'] ?>" class="btn btn-sm btn-outline-danger">Entfernen</button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="lpa-bulkbar">
                                <button type="submit" name="lpDelSelected" value="1" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Ausgewählte Artikel entfernen?');">Ausgewählte entfernen</button>
                                <button type="submit" name="lpClearGroup" value="1" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Alle Artikel dieser Gruppe entfernen?');">Alle Artikel der Gruppe entfernen</button>
                            </div>
                        </form>
                    <?php } ?>
                <?php } ?>
                <?php if ($showUngrouped) { ?>
                    <h5 class="mt-3">Ohne Gruppe</h5>
                    <form method="post" class="mb-3">
                        <?= $csrf ?>
                        <input type="hidden" name="kPage" value="<?= $pageId ?>">
                        <input type="hidden" name="kGroup" value="0">
                        <div class="table-responsive">
                            <table class="table lpa-table">
                                <thead>
                                    <tr>
                                        <th style="width:36px">
                                            <input type="checkbox" class="lpa-check-all" title="Alle auswählen">
                                        </th>
                                        <th>Art.-Nr.</th>
                                        <th>Name</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($itemsByGroup[0] as $it) { ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="kItems[]" value="<?= (int)$it['kID'] ?>">
                                        </td>
                                        <td class="lpa-sku"><?= lp_h((string)$it['cArtNr']) ?></td>
                                        <td>
                                            <?= lp_h((string)($it['cName'] ?? '')) ?>
                                            <?php if (empty($it['found'])) { ?>
                                                <span class="text-danger">nicht gefunden</span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <button type="submit" name="lpDelSku" value="<?= (int)$it['kID'] ?>" class="btn btn-sm btn-outline-danger">Entfernen</button>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="lpa-bulkbar">
                            <button type="submit" name="lpDelSelected" value="1" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Ausgewählte Artikel entfernen?');">Ausgewählte entfernen</button>
                            <button type="submit" name="lpClearGroup" value="1" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Alle Artikel ohne Gruppe entfernen?');">Alle ohne Gruppe entfernen</button>
                        </div>
                    </form>
                <?php } ?>
            <?php } ?>

            <?php if (\count($pages) > 1) { ?>
                <form method="post" class="mt-3" onsubmit="return confirm('Seite wirklich löschen?');">
                    <?= $csrf ?>
                    <input type="hidden" name="kPage" value="<?= $pageId ?>">
                    <button type="submit" name="lpDelPage" value="1" class="btn btn-outline-danger btn-sm">Diese Seite löschen</button>
                </form>
            <?php } ?>
        <?php } ?>
        </main>
    </div>
</div>
<script>
document.querySelectorAll('.lpa-check-all').forEach(function (master) {
    master.addEventListener('change', function () {
        var form = master.closest('form');
        if (!form) { return; }
        form.querySelectorAll('input[name="kItems[]"]').forEach(function (box) {
            box.checked = master.checked;
        });
    });
});
</script>
