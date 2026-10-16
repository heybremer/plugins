<?php

declare(strict_types=1);

require_once \dirname(__DIR__) . '/src/MpkService.php';

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\MessePremiumKunden\MpkService;

$db  = Shop::Container()->getDB();
$svc = new MpkService(\dirname(__DIR__) . '/uploads');
$svc->ensureTables();

if (!\function_exists('mpk_h')) {
    function mpk_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

/* ── Foto ausliefern (nur Backend) ── */
$fotoId = isset($_GET['foto']) ? (int)$_GET['foto'] : 0;
if ($fotoId > 0) {
    $foto = $svc->readFoto($fotoId);
    if ($foto === null) {
        \header('HTTP/1.0 404 Not Found');
        echo 'Foto nicht gefunden.';
        return;
    }
    \header('Content-Type: ' . $foto['mime']);
    \header('Content-Disposition: inline; filename="' . \basename($foto['name']) . '"');
    \header('X-Content-Type-Options: nosniff');
    \readfile($foto['path']);
    return;
}

$message = '';
$set     = $svc->fetchSettings();

if (Form::validateToken() && !empty($_POST['mpkSaveSettings'])) {
    $rabatt  = (float)\str_replace(',', '.', (string)($_POST['nRabattProzent'] ?? '10'));
    $tage    = (int)($_POST['nKuponGueltigTage'] ?? 90);
    $mindest = (float)\str_replace(',', '.', (string)($_POST['fMindestbestellwert'] ?? '0'));
    $maxKb   = (int)($_POST['nMaxFotoKb'] ?? 5120);
    $marken  = \trim((string)($_POST['cMarkenListe'] ?? ''));
    $active  = !empty($_POST['nActive']) ? 1 : 0;

    if ($rabatt <= 0 || $rabatt > 100) {
        $message = 'Rabatt muss zwischen 0,01 und 100 liegen.';
    } elseif ($tage < 1 || $tage > 3650) {
        $message = 'Gültigkeit muss zwischen 1 und 3650 Tagen liegen.';
    } elseif ($maxKb < 100 || $maxKb > 20480) {
        $message = 'Max. Fotogröße muss zwischen 100 und 20480 KB liegen.';
    } else {
        try {
            $row = $db->getSingleObject('SELECT kID FROM ' . MpkService::TABLE_SET . ' ORDER BY kID ASC LIMIT 1');
            if ($row === null) {
                $ins = new \stdClass();
                $ins->nRabattProzent      = $rabatt;
                $ins->nKuponGueltigTage   = $tage;
                $ins->fMindestbestellwert = $mindest;
                $ins->nMaxFotoKb          = $maxKb;
                $ins->cMarkenListe        = $marken;
                $ins->nActive             = $active;
                $ins->dUpdated            = \date('Y-m-d H:i:s');
                $db->insert(MpkService::TABLE_SET, $ins);
            } else {
                $db->executeQueryPrepared(
                    'UPDATE ' . MpkService::TABLE_SET . '
                        SET nRabattProzent = :r,
                            nKuponGueltigTage = :t,
                            fMindestbestellwert = :m,
                            nMaxFotoKb = :k,
                            cMarkenListe = :l,
                            nActive = :a,
                            dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'r'  => $rabatt,
                        't'  => $tage,
                        'm'  => $mindest,
                        'k'  => $maxKb,
                        'l'  => $marken,
                        'a'  => $active,
                        'id' => (int)$row->kID,
                    ]
                );
            }
            $message = 'Einstellungen gespeichert.';
            $set     = $svc->fetchSettings();
        } catch (\Throwable $e) {
            $message = 'Fehler beim Speichern der Einstellungen.';
        }
    }
}

if (Form::validateToken() && !empty($_POST['mpkResend']) && isset($_POST['kID'])) {
    $ok      = $svc->resendMail((int)$_POST['kID']);
    $message = $ok ? 'E-Mail erneut gesendet.' : 'E-Mail konnte nicht gesendet werden.';
}

/* ── Detail ── */
$detailId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
if ($detailId > 0) {
    $row   = $db->getSingleObject('SELECT * FROM ' . MpkService::TABLE . ' WHERE kID = :id LIMIT 1', ['id' => $detailId]);
    $fotos = [];
    try {
        $fotos = $db->getObjects(
            'SELECT * FROM ' . MpkService::TABLE_FOTO . ' WHERE kAnmeldung = :id ORDER BY kID ASC',
            ['id' => $detailId]
        );
    } catch (\Throwable $e) {
        $fotos = [];
    }

    echo '<div class="card">';
    echo '<div class="card-header d-flex align-items-center justify-content-between">';
    echo '<h3 class="mb-0">Anmeldung #' . $detailId . '</h3>';
    echo '<a href="?" class="btn btn-sm btn-secondary">← Zurück zur Liste</a>';
    echo '</div><div class="card-body">';

    if ($message !== '') {
        echo '<div class="alert alert-info">' . mpk_h($message) . '</div>';
    }

    if ($row === null) {
        echo '<div class="alert alert-warning">Eintrag nicht gefunden.</div>';
    } else {
        $fahrzeug = \trim((string)($row->cKaravanMarke ?? '') . ' ' . (string)($row->cKaravanModell ?? ''));
        if ((string)($row->cKaravanManuell ?? '') !== '') {
            $fahrzeug .= ($fahrzeug !== '' ? ' / ' : '') . (string)$row->cKaravanManuell;
        }
        $fields = [
            'Name'              => (string)($row->cName ?? ''),
            'Telefon'           => (string)($row->cTelefon ?? ''),
            'E-Mail'            => (string)($row->cEmail ?? ''),
            'Adresse'           => (string)($row->cAdresse ?? ''),
            'Fahrzeug'          => $fahrzeug,
            'Nachricht'         => (string)($row->cNachricht ?? ''),
            'Datenschutz'       => ((int)($row->nDatenschutz ?? 0) === 1)
                ? '✓ Akzeptiert am ' . (string)($row->dDatenschutz ?? '')
                : '✗ Nicht akzeptiert',
            'Kupon-Code'        => (string)($row->cKuponCode ?? ''),
            'Rabatt'            => \rtrim(\rtrim(\number_format((float)($row->nRabattProzent ?? 0), 2, ',', ''), '0'), ',') . ' %',
            'E-Mail versendet'  => ((int)($row->nEmailGesendet ?? 0) === 1) ? 'Ja' : 'Nein',
            'E-Mail-Fehler'     => (string)($row->cEmailFehler ?? ''),
            'IP'                => (string)($row->cIp ?? ''),
            'Registriert am'    => (string)($row->dErstellt ?? ''),
        ];
        echo '<table class="table table-sm table-bordered"><tbody>';
        foreach ($fields as $label => $value) {
            if ($value === '') {
                continue;
            }
            echo '<tr><th style="width:200px">' . mpk_h($label) . '</th><td>' . \nl2br(mpk_h($value)) . '</td></tr>';
        }
        echo '</tbody></table>';

        if ($fotos !== []) {
            $fotoBase = \rtrim((string)Shop::getURL(), '/') . '/messe-premium-kunden-foto';
            echo '<h4 class="mt-4">Fotos</h4><div class="d-flex flex-wrap" style="gap:12px;">';
            foreach ($fotos as $f) {
                $stored = \basename((string)($f->cDateiname ?? ''));
                $orig   = (string)($f->cOriginalname ?? $stored);
                $src    = $fotoBase . '?f=' . \rawurlencode($stored);
                $path   = \dirname(__DIR__) . '/uploads/' . $stored;
                if ($stored === '' || !\is_file($path)) {
                    echo '<div class="text-muted">Datei fehlt: ' . mpk_h($orig) . '</div>';
                    continue;
                }
                echo '<a href="' . mpk_h($src) . '" target="_blank" rel="noopener">';
                echo '<img src="' . mpk_h($src) . '" alt="' . mpk_h($orig) . '" ';
                echo 'style="max-width:180px;max-height:140px;object-fit:cover;border:1px solid #ddd;border-radius:8px;">';
                echo '</a>';
            }
            echo '</div>';
        }

        if ((string)($row->cKuponCode ?? '') !== '') {
            echo '<form method="post" class="mt-3">';
            echo Form::getTokenInput();
            echo '<input type="hidden" name="kID" value="' . $detailId . '">';
            echo '<button type="submit" name="mpkResend" value="1" class="btn btn-sm btn-outline-primary">Kupon-E-Mail erneut senden</button>';
            echo '</form>';
        }
    }
    echo '</div></div>';
    return;
}

/* ── Liste ── */
$search  = \trim((string)($_GET['q'] ?? ''));
$page    = \max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$total   = 0;
$rows    = [];

try {
    $where  = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE (cName LIKE :q OR cEmail LIKE :q OR cTelefon LIKE :q
                       OR cKaravanMarke LIKE :q OR cKaravanModell LIKE :q
                       OR cKaravanManuell LIKE :q OR cKuponCode LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }
    $countRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM ' . MpkService::TABLE . $where, $params);
    $total    = (int)($countRow->cnt ?? 0);
    $rows     = $db->getObjects(
        'SELECT kID, cName, cEmail, cTelefon, cKaravanMarke, cKaravanModell, cKaravanManuell,
                cKuponCode, nRabattProzent, nEmailGesendet, dErstellt
           FROM ' . MpkService::TABLE . $where . '
          ORDER BY dErstellt DESC
          LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );
} catch (\Throwable $e) {
    $rows = [];
}

$totalPages = $total > 0 ? (int)\ceil($total / $perPage) : 1;
?>
<div class="card mb-3">
    <div class="card-header"><h3 class="mb-0">Einstellungen</h3></div>
    <div class="card-body">
        <?php if ($message !== '') { ?>
            <div class="alert alert-info"><?= mpk_h($message) ?></div>
        <?php } ?>
        <form method="post">
            <?= Form::getTokenInput() ?>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Rabatt (%)</label>
                    <input type="number" step="0.01" min="0.01" max="100" name="nRabattProzent" class="form-control"
                           value="<?= mpk_h((string)$set['rabatt']) ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Kupon gültig (Tage)</label>
                    <input type="number" min="1" max="3650" name="nKuponGueltigTage" class="form-control"
                           value="<?= (int)$set['gueltigTage'] ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Mindestbestellwert</label>
                    <input type="number" step="0.01" min="0" name="fMindestbestellwert" class="form-control"
                           value="<?= mpk_h((string)$set['mindest']) ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>Max. Fotogröße (KB)</label>
                    <input type="number" min="100" max="20480" name="nMaxFotoKb" class="form-control"
                           value="<?= (int)$set['maxFotoKb'] ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Markenliste (Fallback, eine Marke pro Zeile)</label>
                <textarea name="cMarkenListe" class="form-control" rows="6"><?= mpk_h((string)$set['markenListe']) ?></textarea>
                <small class="text-muted">Wird nur genutzt, wenn keine FilterKonfigurator-Snapshots vorhanden sind.</small>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="mpkActive" name="nActive" value="1"
                    <?= (int)$set['active'] === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="mpkActive">Formular aktiv</label>
            </div>
            <button type="submit" name="mpkSaveSettings" value="1" class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="mb-0">Anmeldungen <span class="badge badge-secondary ml-2"><?= $total ?></span></h3>
        <form method="get" class="d-flex" style="gap:.5rem;">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Suche (Name, E-Mail, Kupon…)"
                   value="<?= mpk_h($search) ?>" style="min-width:240px;">
            <button type="submit" class="btn btn-sm btn-primary">Suchen</button>
            <?php if ($search !== '') { ?>
                <a href="?" class="btn btn-sm btn-outline-secondary">✕</a>
            <?php } ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)) { ?>
            <div class="p-4 text-muted">Keine Einträge<?= $search !== '' ? ' für "' . mpk_h($search) . '"' : '' ?> gefunden.</div>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Fahrzeug</th>
                        <th>Kupon</th>
                        <th>Mail</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r) {
                    $fz = \trim((string)($r->cKaravanMarke ?? '') . ' ' . (string)($r->cKaravanModell ?? ''));
                    if ((string)($r->cKaravanManuell ?? '') !== '') {
                        $fz .= ($fz !== '' ? ' / ' : '') . (string)$r->cKaravanManuell;
                    }
                    ?>
                    <tr>
                        <td class="text-muted"><?= (int)($r->kID ?? 0) ?></td>
                        <td><?= mpk_h((string)($r->cName ?? '')) ?></td>
                        <td><?= mpk_h((string)($r->cEmail ?? '')) ?></td>
                        <td><?= mpk_h($fz) ?></td>
                        <td><code><?= mpk_h((string)($r->cKuponCode ?? '')) ?></code>
                            <small class="text-muted"><?= mpk_h((string)($r->nRabattProzent ?? '')) ?>%</small></td>
                        <td><?= ((int)($r->nEmailGesendet ?? 0) === 1) ? '✓' : '–' ?></td>
                        <td style="white-space:nowrap"><?= mpk_h((string)($r->dErstellt ?? '')) ?></td>
                        <td><a href="?detail=<?= (int)($r->kID ?? 0) ?>" class="btn btn-xs btn-outline-primary">Detail</a></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
    <?php if ($totalPages > 1) { ?>
    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">Seite <?= $page ?> von <?= $totalPages ?> (<?= $total ?> Einträge)</small>
        <div class="d-flex" style="gap:.35rem;">
            <?php for ($i = \max(1, $page - 3); $i <= \min($totalPages, $page + 3); $i++) { ?>
                <a href="?p=<?= $i ?><?= $search !== '' ? '&q=' . \urlencode($search) : '' ?>"
                   class="btn btn-xs <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $i ?></a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>
