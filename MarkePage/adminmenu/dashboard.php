<?php

declare(strict_types=1);

require_once \dirname(__DIR__) . '/src/MarkePageService.php';

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\MarkePage\MarkePageService;

$db  = Shop::Container()->getDB();
$svc = new MarkePageService($db, MarkePageService::resolvePluginID($db));
$svc->ensureTables();

if (!\function_exists('mp_h')) {
    function mp_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
$error   = '';
$warning = '';
$isPost  = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Form::validateToken();
$formId  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$showNew = isset($_GET['new']);
$form    = null;

if ($isPost && !empty($_POST['mpDelete'])) {
    $svc->delete((int)($_POST['id'] ?? 0));
    $message = 'Seite gelöscht.';
    $formId  = 0;
    $showNew = false;
}

if ($isPost && !empty($_POST['mpSave'])) {
    $postId = (int)($_POST['id'] ?? 0);
    $result = $svc->save($_POST, $postId);
    if ($result['ok']) {
        $message = 'Seite gespeichert.';
        $warning = $result['warning'];
        $formId  = $result['id'];
        $showNew = false;
    } else {
        $error   = $result['error'];
        $formId  = $postId;
        $showNew = $postId === 0;
        $form    = (object)[
            'kID'              => $postId,
            'cSlug'            => (string)($_POST['slug'] ?? ''),
            'cTitle'           => (string)($_POST['title'] ?? ''),
            'cMetaTitle'       => (string)($_POST['metaTitle'] ?? ''),
            'cMetaDescription' => (string)($_POST['metaDescription'] ?? ''),
            'cIntro'           => (string)($_POST['intro'] ?? ''),
            'cArtNrs'          => (string)($_POST['artNrs'] ?? ''),
            'nActive'          => !empty($_POST['active']) ? 1 : 0,
        ];
    }
}

$shopUrl = \rtrim((string)Shop::getURL(), '/');

/* ── Formular ── */
if ($showNew || $formId > 0) {
    if ($form === null) {
        $form = $formId > 0 ? $svc->find($formId) : null;
        if ($form === null) {
            $form = (object)[
                'kID' => 0, 'cSlug' => '', 'cTitle' => '', 'cMetaTitle' => '', 'cMetaDescription' => '',
                'cIntro' => '', 'cArtNrs' => '', 'nActive' => 1,
            ];
        }
    }
    $artNrs = MarkePageService::parseArtNrs((string)$form->cArtNrs);
    $found  = [];
    $miss   = [];
    if ($artNrs !== []) {
        $ids = $svc->resolveIds($artNrs);
        foreach ($artNrs as $nr) {
            if (isset($ids[\mb_strtolower($nr)])) {
                $found[] = $nr;
            } else {
                $miss[] = $nr;
            }
        }
    }
    ?>
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="mb-0"><?= (int)$form->kID > 0 ? 'Seite bearbeiten' : 'Neue Seite' ?></h3>
            <a href="?" class="btn btn-sm btn-secondary">← Zurück zur Liste</a>
        </div>
        <div class="card-body">
            <?php if ($message !== '') { ?>
                <div class="alert alert-success"><?= mp_h($message) ?></div>
            <?php } ?>
            <?php if ($warning !== '') { ?>
                <div class="alert alert-warning"><?= mp_h($warning) ?></div>
            <?php } ?>
            <?php if ($error !== '') { ?>
                <div class="alert alert-danger"><?= mp_h($error) ?></div>
            <?php } ?>
            <form method="post">
                <?= Form::getTokenInput() ?>
                <input type="hidden" name="id" value="<?= (int)$form->kID ?>">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="mpSlug">URL (z.B. PKW_Skoda)</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><?= mp_h($shopUrl) ?>/</span></div>
                            <input type="text" id="mpSlug" name="slug" class="form-control" maxlength="100" required
                                   value="<?= mp_h((string)$form->cSlug) ?>">
                        </div>
                        <small class="text-muted">Erlaubt: Buchstaben, Zahlen, _ und -</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="mpTitle">Seitentitel (H1)</label>
                        <input type="text" id="mpTitle" name="title" class="form-control" maxlength="255" required
                               value="<?= mp_h((string)$form->cTitle) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="mpMetaTitle">Meta-Titel (optional)</label>
                        <input type="text" id="mpMetaTitle" name="metaTitle" class="form-control" maxlength="255"
                               value="<?= mp_h((string)$form->cMetaTitle) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="mpMetaDesc">Meta-Beschreibung (optional)</label>
                        <input type="text" id="mpMetaDesc" name="metaDescription" class="form-control" maxlength="500"
                               value="<?= mp_h((string)$form->cMetaDescription) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="mpIntro">Einleitungstext (optional)</label>
                    <textarea id="mpIntro" name="intro" class="form-control" rows="3"><?= mp_h((string)$form->cIntro) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="mpArtNrs">Artikelnummern (eine pro Zeile oder mit Komma getrennt)</label>
                    <textarea id="mpArtNrs" name="artNrs" class="form-control" rows="10"
                              style="font-family:monospace"><?= mp_h((string)$form->cArtNrs) ?></textarea>
                    <small class="text-muted">Die Reihenfolge entspricht der Reihenfolge auf der Seite.</small>
                </div>
                <?php if ($artNrs !== []) { ?>
                    <div class="alert alert-<?= $miss === [] ? 'success' : 'warning' ?>">
                        Gefunden: <strong><?= \count($found) ?></strong> von <?= \count($artNrs) ?>
                        <?php if ($miss !== []) { ?>
                            <br>Nicht gefunden: <code><?= mp_h(\implode(', ', $miss)) ?></code>
                        <?php } ?>
                    </div>
                <?php } ?>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="mpActive" name="active" value="1"
                        <?= (int)$form->nActive === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mpActive">Seite aktiv</label>
                </div>
                <button type="submit" name="mpSave" value="1" class="btn btn-primary">Speichern</button>
                <?php if ((int)$form->kID > 0 && (string)$form->cSlug !== '') { ?>
                    <a href="<?= mp_h($shopUrl . '/' . (string)$form->cSlug) ?>" target="_blank" rel="noopener"
                       class="btn btn-outline-secondary">Seite öffnen</a>
                <?php } ?>
            </form>
        </div>
    </div>
    <?php
    return;
}

/* ── Liste ── */
$rows = $svc->all();
?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="mb-0">Seiten <span class="badge badge-secondary ml-2"><?= \count($rows) ?></span></h3>
        <a href="?new=1" class="btn btn-sm btn-primary">+ Neue Seite</a>
    </div>
    <div class="card-body p-0">
        <?php if ($message !== '') { ?>
            <div class="alert alert-success m-3"><?= mp_h($message) ?></div>
        <?php } ?>
        <?php if ($rows === []) { ?>
            <div class="p-4 text-muted">Noch keine Seiten angelegt.</div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th>URL</th>
                        <th>Titel</th>
                        <th>Artikel</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r) {
                        $cnt = \count(MarkePageService::parseArtNrs((string)$r->cArtNrs)); ?>
                        <tr>
                            <td><a href="<?= mp_h($shopUrl . '/' . (string)$r->cSlug) ?>" target="_blank" rel="noopener">/<?= mp_h((string)$r->cSlug) ?></a></td>
                            <td><?= mp_h((string)$r->cTitle) ?></td>
                            <td><?= $cnt ?></td>
                            <td><?= (int)$r->nActive === 1 ? '<span class="badge badge-success">aktiv</span>' : '<span class="badge badge-secondary">inaktiv</span>' ?></td>
                            <td class="text-right" style="white-space:nowrap">
                                <a href="?edit=<?= (int)$r->kID ?>" class="btn btn-sm btn-outline-primary">Bearbeiten</a>
                                <form method="post" class="d-inline"
                                      onsubmit="return confirm('Seite wirklich löschen?');">
                                    <?= Form::getTokenInput() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r->kID ?>">
                                    <button type="submit" name="mpDelete" value="1" class="btn btn-sm btn-outline-danger">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
