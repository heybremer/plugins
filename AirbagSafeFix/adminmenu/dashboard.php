<?php

declare(strict_types=1);

use JTL\Shop;

$db    = Shop::Container()->getDB();
$table = 'twomosi_airbag_safe_fix';

/* ── Tabelle anlegen falls noch nicht vorhanden ── */
try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS ' . $table . ' (
            kID              INT AUTO_INCREMENT PRIMARY KEY,
            cVorname         VARCHAR(100)  NOT NULL DEFAULT "",
            cNachname        VARCHAR(100)  NOT NULL DEFAULT "",
            cEmail           VARCHAR(200)  NOT NULL DEFAULT "",
            cTelefon         VARCHAR(50)   NOT NULL DEFAULT "",
            cStrasse         VARCHAR(200)  NOT NULL DEFAULT "",
            cPlz             VARCHAR(20)   NOT NULL DEFAULT "",
            cStadt           VARCHAR(100)  NOT NULL DEFAULT "",
            cLand            VARCHAR(100)  NOT NULL DEFAULT "",
            cProduktName     VARCHAR(200)  NOT NULL DEFAULT "",
            cVariante        VARCHAR(50)   NOT NULL DEFAULT "",
            cFahrzeugMarke   VARCHAR(100)  NOT NULL DEFAULT "",
            cFahrzeugModell  VARCHAR(100)  NOT NULL DEFAULT "",
            cPartiNr         VARCHAR(100)  NOT NULL DEFAULT "",
            cAirbagDikis1    VARCHAR(100)  NOT NULL DEFAULT "",
            cAirbagDikis2    VARCHAR(100)  NOT NULL DEFAULT "",
            cAirbagDikis3    VARCHAR(100)  NOT NULL DEFAULT "",
            cAirbagDikis4    VARCHAR(100)  NOT NULL DEFAULT "",
            cProductionTime  VARCHAR(50)   NOT NULL DEFAULT "",
            cPlatform        VARCHAR(100)  NOT NULL DEFAULT "",
            cFaturaLink      VARCHAR(500)  NOT NULL DEFAULT "",
            nDatenschutz     TINYINT(1)    NOT NULL DEFAULT 0,
            dErstellt        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (cEmail),
            INDEX idx_erstellt (dErstellt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {
    // ignore
}

if (!function_exists('asf_h')) {
    function asf_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

/* ── Detailansicht ── */
$detailId = isset($_GET['detail']) ? (int)$_GET['detail'] : 0;
if ($detailId > 0) {
    $row = null;
    try {
        $row = $db->getSingleObject(
            'SELECT * FROM ' . $table . ' WHERE kID = :id LIMIT 1',
            ['id' => $detailId]
        );
    } catch (\Throwable $e) {}

    echo '<div class="card">';
    echo '<div class="card-header d-flex align-items-center justify-content-between">';
    echo '<h3 class="mb-0">Registrierung #' . $detailId . '</h3>';
    echo '<a href="?" class="btn btn-sm btn-secondary">← Zurück zur Liste</a>';
    echo '</div>';
    echo '<div class="card-body">';

    if ($row === null) {
        echo '<div class="alert alert-warning">Eintrag nicht gefunden.</div>';
    } else {
        $fields = [
            'Vorname'           => (string)($row->cVorname ?? ''),
            'Nachname'          => (string)($row->cNachname ?? ''),
            'E-Mail'            => (string)($row->cEmail ?? ''),
            'Telefon'           => (string)($row->cTelefon ?? ''),
            'Straße'            => (string)($row->cStrasse ?? ''),
            'PLZ'               => (string)($row->cPlz ?? ''),
            'Stadt'             => (string)($row->cStadt ?? ''),
            'Land'              => (string)($row->cLand ?? ''),
            'Produkt'           => (string)($row->cProduktName ?? ''),
            'Variante'          => (string)($row->cVariante ?? ''),
            'Fahrzeugmarke'     => (string)($row->cFahrzeugMarke ?? ''),
            'Fahrzeugmodell'    => (string)($row->cFahrzeugModell ?? ''),
            'Parti-Nr.'         => (string)($row->cPartiNr ?? ''),
            'Airbag Näht-Nr. 1' => (string)($row->cAirbagDikis1 ?? ''),
            'Airbag Näht-Nr. 2' => (string)($row->cAirbagDikis2 ?? ''),
            'Airbag Näht-Nr. 3' => (string)($row->cAirbagDikis3 ?? ''),
            'Airbag Näht-Nr. 4' => (string)($row->cAirbagDikis4 ?? ''),
            'Production Time'   => (string)($row->cProductionTime ?? ''),
            'Plattform'         => (string)($row->cPlatform ?? ''),
            'Rechnung/Link'     => (string)($row->cFaturaLink ?? ''),
            'Datenschutz'       => ((int)($row->nDatenschutz ?? 0) === 1) ? '✓ Akzeptiert' : '✗ Nicht akzeptiert',
            'Registriert am'    => (string)($row->dErstellt ?? ''),
        ];

        echo '<table class="table table-sm table-bordered">';
        echo '<tbody>';
        foreach ($fields as $label => $value) {
            if ($value === '') continue;
            echo '<tr><th style="width:200px">' . asf_h($label) . '</th><td>' . asf_h($value) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div></div>';
    return;
}

/* ── Suche & Paginierung ── */
$search  = trim((string)($_GET['q'] ?? ''));
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$total = 0;
$rows  = [];

try {
    $whereClause = '';
    $params      = [];

    if ($search !== '') {
        $whereClause = " WHERE (cVorname LIKE :q OR cNachname LIKE :q OR cEmail LIKE :q
                              OR cProduktName LIKE :q OR cFahrzeugMarke LIKE :q OR cFahrzeugModell LIKE :q)";
        $params['q'] = '%' . $search . '%';
    }

    $countRow = $db->getSingleObject(
        'SELECT COUNT(*) AS cnt FROM ' . $table . $whereClause,
        $params
    );
    $total = (int)($countRow->cnt ?? 0);

    $rows = $db->getObjects(
        'SELECT kID, cVorname, cNachname, cEmail, cProduktName,
                cFahrzeugMarke, cFahrzeugModell, dErstellt
           FROM ' . $table . $whereClause . '
          ORDER BY dErstellt DESC
          LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );
} catch (\Throwable $e) {
    $rows = [];
}

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

?>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h3 class="mb-0">Airbag Safe Fix — Registrierungen
            <span class="badge badge-secondary ml-2"><?= $total ?></span>
        </h3>
        <form method="get" action="" class="d-flex" style="gap:.5rem;">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Suche (Name, E-Mail, Produkt…)"
                   value="<?= asf_h($search) ?>" style="min-width:240px;">
            <button type="submit" class="btn btn-sm btn-primary">Suchen</button>
            <?php if ($search !== '') { ?>
                <a href="?" class="btn btn-sm btn-outline-secondary">✕</a>
            <?php } ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($rows)) { ?>
            <div class="p-4 text-muted">Keine Einträge<?= $search !== '' ? ' für "' . asf_h($search) . '"' : '' ?> gefunden.</div>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Produkt</th>
                        <th>Fahrzeug</th>
                        <th>Datum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r) { ?>
                    <tr>
                        <td class="text-muted"><?= (int)($r->kID ?? 0) ?></td>
                        <td><?= asf_h(trim((string)($r->cVorname ?? '') . ' ' . (string)($r->cNachname ?? ''))) ?></td>
                        <td><?= asf_h((string)($r->cEmail ?? '')) ?></td>
                        <td><?= asf_h((string)($r->cProduktName ?? '')) ?></td>
                        <td><?= asf_h(trim((string)($r->cFahrzeugMarke ?? '') . ' ' . (string)($r->cFahrzeugModell ?? ''))) ?></td>
                        <td style="white-space:nowrap"><?= asf_h((string)($r->dErstellt ?? '')) ?></td>
                        <td>
                            <a href="?detail=<?= (int)($r->kID ?? 0) ?>" class="btn btn-xs btn-outline-primary">Detail</a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
    <?php if ($totalPages > 1) { ?>
    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <small class="text-muted">
            Seite <?= $page ?> von <?= $totalPages ?> (<?= $total ?> Einträge)
        </small>
        <div class="d-flex" style="gap:.35rem;">
            <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++) { ?>
                <a href="?p=<?= $i ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                   class="btn btn-xs <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $i ?>
                </a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>
