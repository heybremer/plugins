<?php
declare(strict_types=1);

require_once \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'SeriesMetaRuntime.php';
require_once \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Query' . \DIRECTORY_SEPARATOR . 'PkwSqlFragments.php';
require_once \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Service' . \DIRECTORY_SEPARATOR . 'SnapshotService.php';

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\FilterKonfiguratorSearch\SeriesMetaRuntime;

$db = Shop::Container()->getDB();

$message = '';
$bannerEnsureOk = true;
$bannerEnsureError = '';
$fkPluginId = 0;
$aiPhotoEnabled = 0;
$aiApiKey = '';
$aiModel = 'gpt-5.6';

try {
    $pluginRow = $db->getSingleObject(
        "SELECT kPlugin FROM tplugin
          WHERE cPluginID = 'FilterKonfiguratorSearch'
             OR TRIM(cVerzeichnis) = 'FilterKonfiguratorSearch'
       ORDER BY kPlugin DESC LIMIT 1"
    );
    $fkPluginId = (int)($pluginRow->kPlugin ?? 0);
    if ($fkPluginId > 0) {
        $settingRows = $db->getObjects(
            "SELECT cName, cWert FROM tplugineinstellungen
              WHERE kPlugin = :pid
                AND cName IN ('fk_seat_photo_enabled_v2', 'fk_openai_api_key', 'fk_openai_model')",
            ['pid' => $fkPluginId]
        );
        foreach ((array)$settingRows as $settingRow) {
            $name = (string)($settingRow->cName ?? '');
            $value = (string)($settingRow->cWert ?? '');
            if ($name === 'fk_seat_photo_enabled_v2') {
                $aiPhotoEnabled = \in_array(\strtolower(\trim($value)), ['0', 'n', 'no', 'false', 'off'], true) ? 0 : 1;
            } elseif ($name === 'fk_openai_api_key') {
                $aiApiKey = \trim($value);
            } elseif ($name === 'fk_openai_model' && \trim($value) !== '') {
                $aiModel = \trim($value);
            }
        }
    }
} catch (\Throwable $e) {
}
if ($aiModel === 'gpt-4.1-mini') {
    $aiModel = 'gpt-5.6';
}

// Admin sayfası bazen Bootstrap boot tetiklenmeden çalışabilir.
// Bu yüzden banner tablosunu burada da garanti altına al.
try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_banner (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            cHeadline VARCHAR(255) NOT NULL DEFAULT '',
            cSub1 VARCHAR(255) NOT NULL DEFAULT '',
            cSub2 VARCHAR(255) NOT NULL DEFAULT '',
            cSub3 VARCHAR(255) NOT NULL DEFAULT '',
            nSub1Active TINYINT(1) NOT NULL DEFAULT 1,
            nSub2Active TINYINT(1) NOT NULL DEFAULT 1,
            nSub3Active TINYINT(1) NOT NULL DEFAULT 1,
            cSub2ButtonLabel VARCHAR(255) NOT NULL DEFAULT 'Aktif et',
            cSub2ButtonUrl VARCHAR(500) NOT NULL DEFAULT '#',
            nSegmentWmActive TINYINT(1) NOT NULL DEFAULT 1,
            nSegmentPkwActive TINYINT(1) NOT NULL DEFAULT 1,
            nSegmentOrder TINYINT(1) NOT NULL DEFAULT 0,
            cActiveCategories VARCHAR(1000) NOT NULL DEFAULT '',
            cSegmentPkwFirstCategories VARCHAR(1000) NOT NULL DEFAULT '',
            dUpdated DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $fkHaveCols = static function ($db, string $table): array {
        $have = [];
        $cols = $db->getObjects("SHOW COLUMNS FROM {$table}");
        if (\is_array($cols)) {
            foreach ($cols as $c) {
                $have[(string)($c->Field ?? '')] = true;
            }
        }
        return $have;
    };
    $bannerHave = $fkHaveCols($db, 'twomosi_fk_banner');
    $bannerAlters = [
        'nSub1Active' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER cSub3",
        'nSub2Active' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER nSub1Active",
        'nSub3Active' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER nSub2Active",
        'cSub2ButtonLabel' => "VARCHAR(255) NOT NULL DEFAULT 'Aktif et' AFTER nSub3Active",
        'cSub2ButtonUrl' => "VARCHAR(500) NOT NULL DEFAULT '#' AFTER cSub2ButtonLabel",
        'nSegmentWmActive' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER cSub2ButtonUrl",
        'nSegmentPkwActive' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER nSegmentWmActive",
        'nSegmentOrder' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER nSegmentPkwActive",
        'cActiveCategories' => "VARCHAR(1000) NOT NULL DEFAULT '' AFTER nSegmentOrder",
        'cSegmentPkwFirstCategories' => "VARCHAR(1000) NOT NULL DEFAULT '' AFTER cActiveCategories",
    ];
    foreach ($bannerAlters as $col => $def) {
        if (!isset($bannerHave[$col])) {
            $db->executeQuery("ALTER TABLE twomosi_fk_banner ADD COLUMN {$col} {$def}");
        }
    }
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_micro_scope (
            kID TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            cCategoryIds VARCHAR(4000) NOT NULL DEFAULT '',
            cCategoryIdsPkwFirst VARCHAR(4000) NOT NULL DEFAULT '',
            dUpdated DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $scopeHave = $fkHaveCols($db, 'twomosi_fk_micro_scope');
    if (!isset($scopeHave['cCategoryIdsPkwFirst'])) {
        $db->executeQuery("ALTER TABLE twomosi_fk_micro_scope ADD COLUMN cCategoryIdsPkwFirst VARCHAR(4000) NOT NULL DEFAULT '' AFTER cCategoryIds");
    }
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_serie_meta (
            cSerieKey VARCHAR(64) NOT NULL,
            cLabel VARCHAR(255) NOT NULL DEFAULT '',
            cColor VARCHAR(32) NOT NULL DEFAULT '',
            cImg VARCHAR(2000) NOT NULL DEFAULT '',
            nHide TINYINT(1) NOT NULL DEFAULT 0,
            dUpdated DATETIME NOT NULL,
            PRIMARY KEY (cSerieKey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (\Throwable $e) {
    $bannerEnsureOk = false;
    $bannerEnsureError = $e->getMessage();
}
$banner = (object)[
    'cHeadline' => 'Besuchen Sie uns auf der HanseMesse Rostock',
    'cSub1'     => 'Boot & Angeln Camping',
    'cSub2'     => '',
    'cSub3'     => '20. bis 22. März 2026 | HanseMesse Rostock',
    'nSub1Active' => 1,
    'nSub2Active' => 1,
    'nSub3Active' => 1,
    'cSub2ButtonLabel' => 'Aktif et',
    'cSub2ButtonUrl' => '#',
    'nSegmentWmActive' => 1,
    'nSegmentPkwActive' => 1,
];

// Ensure snapshot tables exist (same as Bootstrap, but admin can be visited standalone)
try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_hersteller (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            kSprache INT NOT NULL,
            cTyp VARCHAR(255) NOT NULL DEFAULT '',
            cHersteller VARCHAR(255) NOT NULL DEFAULT '',
            UNIQUE KEY uq_opt_her (kSprache, cTyp, cHersteller),
            KEY idx_opt_her (kSprache, cTyp),
            KEY idx_opt_her2 (kSprache, cHersteller)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_basis (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            kSprache INT NOT NULL,
            cTyp VARCHAR(255) NOT NULL DEFAULT '',
            cHersteller VARCHAR(255) NOT NULL DEFAULT '',
            cBasis VARCHAR(255) NOT NULL DEFAULT '',
            UNIQUE KEY uq_opt_basis (kSprache, cTyp, cHersteller, cBasis),
            KEY idx_opt_basis (kSprache, cTyp, cHersteller),
            KEY idx_opt_basis2 (kSprache, cBasis)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_modell (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            kSprache INT NOT NULL,
            cTyp VARCHAR(255) NOT NULL DEFAULT '',
            cHersteller VARCHAR(255) NOT NULL DEFAULT '',
            cBasis VARCHAR(255) NOT NULL DEFAULT '',
            cModell VARCHAR(255) NOT NULL DEFAULT '',
            UNIQUE KEY uq_opt_modell (kSprache, cTyp, cHersteller, cBasis, cModell),
            KEY idx_opt_modell (kSprache, cTyp, cHersteller, cBasis),
            KEY idx_opt_modell2 (kSprache, cModell)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_fk_opt_meta (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            dRefreshed DATETIME NOT NULL,
            kSprache INT NOT NULL DEFAULT 0,
            cNote VARCHAR(255) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (\Throwable $e) {
    // ignore
}

// Eğer POST geldiyse ama token geçersizse, kullanıcıya net göster.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !Form::validateToken()) {
    $message = 'Token-Fehler: Formular-Sitzung ungültig. Bitte laden Sie die Seite neu und versuchen Sie es erneut.';
}

// Cache/log clear actions
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// For AJAX snapshot steps: validate token and return JSON immediately (never return HTML)
if ($isAjax && (
    !empty($_POST['fkSnapshotStepWm']) ||
    !empty($_POST['fkSnapshotStepPkwHerMod']) ||
    !empty($_POST['fkSnapshotStepPkwBj']) ||
    !empty($_POST['fkSnapshotStepPkwSerie'])
)) {
    header('Content-Type: application/json; charset=utf-8');
    if (!Form::validateToken()) {
        echo json_encode(['ok' => false, 'msg' => 'Token ungültig — bitte Seite neu laden (F5).']);
        exit;
    }
    try {
        $lid = (int)Shop::getLanguageID();
        $svc = new \Plugin\FilterKonfiguratorSearch\Service\SnapshotService($db);
        $pkwCountMsg = static function (\Plugin\FilterKonfiguratorSearch\Service\SnapshotService $svc): string {
            $c = $svc->getPkwSnapshotCounts();

            return ' (PKW: Hersteller=' . $c['hersteller']
                . ', Modell=' . $c['modell']
                . ', Baujahr=' . $c['baujahr']
                . ', Serien=' . $c['serie'] . ')';
        };
        if (!empty($_POST['fkSnapshotStepWm'])) {
            $svc->refreshWohnmobilSnapshots($lid);
            if ($lid !== 1) $svc->refreshWohnmobilSnapshots(1);
            echo json_encode(['ok' => true, 'msg' => 'Schritt 1/4: Wohnmobil-Snapshots aktualisiert.']);
        } elseif (!empty($_POST['fkSnapshotStepPkwHerMod'])) {
            $svc->refreshPkwHerstellerModell($lid);
            if ($lid !== 1) $svc->refreshPkwHerstellerModell(1);
            echo json_encode(['ok' => true, 'msg' => 'Schritt 2/4: PKW Hersteller & Modell aktualisiert.' . $pkwCountMsg($svc)]);
        } elseif (!empty($_POST['fkSnapshotStepPkwBj'])) {
            $svc->refreshPkwBaujahr($lid);
            if ($lid !== 1) $svc->refreshPkwBaujahr(1);
            echo json_encode(['ok' => true, 'msg' => 'Schritt 3/4: PKW Baujahr-Snapshots aktualisiert.' . $pkwCountMsg($svc)]);
        } elseif (!empty($_POST['fkSnapshotStepPkwSerie'])) {
            $svc->refreshPkwSerie($lid);
            if ($lid !== 1) $svc->refreshPkwSerie(1);
            echo json_encode(['ok' => true, 'msg' => 'Schritt 4/4: PKW Serien-Snapshots aktualisiert. Alle fertig!' . $pkwCountMsg($svc)]);
        }
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => 'Fehler: ' . $e->getMessage()]);
    }
    exit;
}

if (Form::validateToken()) {
    try {
        $snapshotService = null;
        $getSnapshotService = function() use ($db, &$snapshotService) {
            if ($snapshotService === null) {
                $snapshotService = new \Plugin\FilterKonfiguratorSearch\Service\SnapshotService($db);
            }
            return $snapshotService;
        };

        // Step 1: Wohnmobil snapshots (hersteller, basis, modell)
        if (!empty($_POST['fkSnapshotStepWm'])) {
            $lid = (int)Shop::getLanguageID();
            $getSnapshotService()->refreshWohnmobilSnapshots($lid);
            if ($lid !== 1) $getSnapshotService()->refreshWohnmobilSnapshots(1);
            $message = 'Schritt 1/4: Wohnmobil-Snapshots aktualisiert.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'msg' => $message]); exit; }
        }

        // Step 2: PKW Hersteller + Modell snapshots
        if (!empty($_POST['fkSnapshotStepPkwHerMod'])) {
            $lid = (int)Shop::getLanguageID();
            $getSnapshotService()->refreshPkwHerstellerModell($lid);
            if ($lid !== 1) $getSnapshotService()->refreshPkwHerstellerModell(1);
            $message = 'Schritt 2/4: PKW Hersteller & Modell-Snapshots aktualisiert.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'msg' => $message]); exit; }
        }

        // Step 3: PKW Baujahr snapshot
        if (!empty($_POST['fkSnapshotStepPkwBj'])) {
            $lid = (int)Shop::getLanguageID();
            $getSnapshotService()->refreshPkwBaujahr($lid);
            if ($lid !== 1) $getSnapshotService()->refreshPkwBaujahr(1);
            $message = 'Schritt 3/4: PKW Baujahr-Snapshots aktualisiert.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'msg' => $message]); exit; }
        }

        // Step 4: PKW Serie snapshot (heaviest)
        if (!empty($_POST['fkSnapshotStepPkwSerie'])) {
            $lid = (int)Shop::getLanguageID();
            $getSnapshotService()->refreshPkwSerie($lid);
            if ($lid !== 1) $getSnapshotService()->refreshPkwSerie(1);
            $db->executeQueryPrepared(
                'INSERT INTO twomosi_fk_opt_meta (dRefreshed, kSprache, cNote) VALUES (NOW(), :lid, :note)
                 ON DUPLICATE KEY UPDATE dRefreshed = NOW(), cNote = VALUES(cNote)',
                ['lid' => (int)Shop::getLanguageID(), 'note' => 'Manual Stepwise Refresh']
            );
            $message = 'Schritt 4/4: PKW Serien-Snapshots aktualisiert. Alle Snapshots fertig!';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => true, 'msg' => $message]); exit; }
        }

        // Legacy: full refresh in one request (may timeout on large datasets)
        if (!empty($_POST['fkRefreshOptionsSnapshot'])) {
            $lid = (int)Shop::getLanguageID();
            $svc = $getSnapshotService();
            $svc->refreshAll($lid);
            if ($lid !== 1) $svc->refreshAll(1);
            $message = 'Snapshots erfolgreich aktualisiert (Segmente: PKW & Wohnmobil — inkl. PKW Baujahr & Serien).';
        }
        if (!empty($_POST['fkClearStep4Cache'])) {
            $db->executeQuery('DELETE FROM twomosi_step4_cache');
            $message = 'Step-4-Cache wurde geleert.';
        }
        if (!empty($_POST['fkClearQueryLog'])) {
            $db->executeQuery('DELETE FROM twomosi_fk_search_log');
            $message = 'Suchprotokolle wurden geleert.';
        }
        if (!empty($_POST['fkSaveSettingsTab'])) {
            if (!$bannerEnsureOk) {
                throw new \RuntimeException('Banner tablosu oluşturulamadı: ' . $bannerEnsureError);
            }
            $activeCats = '';
            if (isset($_POST['fkActiveCategories'])) {
                if (is_array($_POST['fkActiveCategories'])) {
                    $activeCats = implode(',', array_map('intval', $_POST['fkActiveCategories']));
                } else {
                    $activeCats = trim((string)$_POST['fkActiveCategories']);
                }
            }
            $pkwFirstCats = '';
            if (isset($_POST['fkPkwFirstCategories'])) {
                if (is_array($_POST['fkPkwFirstCategories'])) {
                    $pkwFirstCats = implode(',', array_map('intval', $_POST['fkPkwFirstCategories']));
                } else {
                    $pkwFirstCats = trim((string)$_POST['fkPkwFirstCategories']);
                }
            }
            $aiPhotoEnabled = !empty($_POST['fkSeatPhotoEnabled']) ? 1 : 0;
            $postedAiModel = \trim((string)($_POST['fkOpenAiModel'] ?? 'gpt-5.6'));
            $postedAiModel = \preg_replace('/[^0-9A-Za-z._-]/', '', $postedAiModel) ?: 'gpt-5.6';
            $aiModel = $postedAiModel;
            if (!empty($_POST['fkOpenAiApiKeyClear'])) {
                $aiApiKey = '';
            } else {
                $postedAiKey = \trim((string)($_POST['fkOpenAiApiKey'] ?? ''));
                if ($postedAiKey !== '') {
                    $aiApiKey = $postedAiKey;
                }
            }
            
            // Tüm banner satırlarını güncelle: aksi halde yeni eklenen boş satır ORDER BY dUpdated ile
            // "son satır" olup cActiveCategories'i gizleyebiliyor (vitrinde allowed hep boş görünür).
            $cntBanner = $db->getSingleObject('SELECT COUNT(*) AS c FROM twomosi_fk_banner');
            if ($cntBanner !== null && (int)($cntBanner->c ?? 0) > 0) {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_fk_banner SET cActiveCategories = :cats, cSegmentPkwFirstCategories = :pkw, dUpdated = NOW()',
                    ['cats' => $activeCats, 'pkw' => $pkwFirstCats]
                );
            } else {
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_fk_banner (cActiveCategories, cSegmentPkwFirstCategories, dUpdated) VALUES (:cats, :pkw, NOW())",
                    ['cats' => $activeCats, 'pkw' => $pkwFirstCats]
                );
            }
            // Kanonische Whitelist (Vitrin liest zuerst hieraus)
            try {
                $db->executeQueryPrepared(
                    'REPLACE INTO twomosi_fk_micro_scope (kID, cCategoryIds, cCategoryIdsPkwFirst, dUpdated) VALUES (1, :cats, :pkw, NOW())',
                    ['cats' => $activeCats, 'pkw' => $pkwFirstCats]
                );
            } catch (\Throwable $e) {
                if ($message === '') {
                    $message = 'twomosi_fk_micro_scope konnte nicht geschrieben werden: ' . $e->getMessage();
                }
            }
            // JTL plugin ayarı (getConfig()->getValue): vitrin her zaman buradan da okuyabilsin.
            try {
                $plg = $db->getSingleObject(
                    "SELECT kPlugin FROM tplugin WHERE cPluginID = 'FilterKonfiguratorSearch' OR TRIM(cVerzeichnis) = 'FilterKonfiguratorSearch' ORDER BY kPlugin DESC LIMIT 1"
                );
                if ($plg !== null && (int)($plg->kPlugin ?? 0) > 0) {
                    $kPlugin = (int)$plg->kPlugin;
                    $confNm  = 'fk_micro_active_categories';
                    $ex = $db->getSingleObject(
                        'SELECT kPlugin FROM tplugineinstellungen WHERE kPlugin = :pid AND cName = :n LIMIT 1',
                        ['pid' => $kPlugin, 'n' => $confNm]
                    );
                    if ($ex !== null) {
                        $db->executeQueryPrepared(
                            'UPDATE tplugineinstellungen SET cWert = :v WHERE kPlugin = :pid AND cName = :n',
                            ['v' => $activeCats, 'pid' => $kPlugin, 'n' => $confNm]
                        );
                    } else {
                        $db->executeQueryPrepared(
                            'INSERT INTO tplugineinstellungen (kPlugin, cName, cWert) VALUES (:pid, :n, :v)',
                            ['pid' => $kPlugin, 'n' => $confNm, 'v' => $activeCats]
                        );
                    }

                    // PKW first sync
                    $confNm2 = 'fk_micro_pkw_first_categories';
                    $ex2 = $db->getSingleObject(
                        'SELECT kPlugin FROM tplugineinstellungen WHERE kPlugin = :pid AND cName = :n LIMIT 1',
                        ['pid' => $kPlugin, 'n' => $confNm2]
                    );
                    if ($ex2 !== null) {
                        $db->executeQueryPrepared(
                            'UPDATE tplugineinstellungen SET cWert = :v WHERE kPlugin = :pid AND cName = :n',
                            ['v' => $pkwFirstCats, 'pid' => $kPlugin, 'n' => $confNm2]
                        );
                    } else {
                        $db->executeQueryPrepared(
                            'INSERT INTO tplugineinstellungen (kPlugin, cName, cWert) VALUES (:pid, :n, :v)',
                            ['pid' => $kPlugin, 'n' => $confNm2, 'v' => $pkwFirstCats]
                        );
                    }

                    $aiSettings = [
                        'fk_seat_photo_enabled_v2' => (string)$aiPhotoEnabled,
                        'fk_openai_api_key'     => $aiApiKey,
                        'fk_openai_model'       => $aiModel,
                    ];
                    foreach ($aiSettings as $settingName => $settingValue) {
                        $settingExists = $db->getSingleObject(
                            'SELECT kPlugin FROM tplugineinstellungen WHERE kPlugin = :pid AND cName = :n LIMIT 1',
                            ['pid' => $kPlugin, 'n' => $settingName]
                        );
                        if ($settingExists !== null) {
                            $db->executeQueryPrepared(
                                'UPDATE tplugineinstellungen SET cWert = :v WHERE kPlugin = :pid AND cName = :n',
                                ['v' => $settingValue, 'pid' => $kPlugin, 'n' => $settingName]
                            );
                        } else {
                            $db->executeQueryPrepared(
                                'INSERT INTO tplugineinstellungen (kPlugin, cName, cWert) VALUES (:pid, :n, :v)',
                                ['pid' => $kPlugin, 'n' => $settingName, 'v' => $settingValue]
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                $message = 'Allgemeine Einstellungen gespeichert (Banner). Plugin-Einstellungen konnten nicht geschrieben werden: ' . $e->getMessage();
            }
            if ($message === '') {
                $nSel = 0;
                if ($activeCats !== '') {
                    foreach (\explode(',', $activeCats) as $p) {
                        if ((int)\trim((string)$p) > 0) {
                            ++$nSel;
                        }
                    }
                }
                $message = $nSel > 0
                    ? 'Allgemeine Einstellungen gespeichert (' . $nSel . ' Kategorien).'
                    : 'Allgemeine Einstellungen gespeichert: Keine Kategorien ausgewählt — der Mikrofilter ist in allen Kategorien sichtbar.';
            }
        }
        if (!empty($_POST['fkSaveSeriesMeta'])) {
            $defaults = SeriesMetaRuntime::getBuiltInDefaults();
            $db->executeQuery('DELETE FROM twomosi_fk_serie_meta');
            $labels = isset($_POST['fk_serie_label']) && \is_array($_POST['fk_serie_label']) ? $_POST['fk_serie_label'] : [];
            $colors = isset($_POST['fk_serie_color']) && \is_array($_POST['fk_serie_color']) ? $_POST['fk_serie_color'] : [];
            $imgs   = isset($_POST['fk_serie_img']) && \is_array($_POST['fk_serie_img']) ? $_POST['fk_serie_img'] : [];
            $hides  = isset($_POST['fk_serie_hide']) && \is_array($_POST['fk_serie_hide']) ? $_POST['fk_serie_hide'] : [];
            foreach ($defaults as $key => $_def) {
                $k = (string)$key;
                $label = \trim((string)($labels[$k] ?? ''));
                $color = \trim((string)($colors[$k] ?? ''));
                $img   = \trim((string)($imgs[$k] ?? ''));
                $hide  = !empty($hides[$k]) ? 1 : 0;
                $db->executeQueryPrepared(
                    'INSERT INTO twomosi_fk_serie_meta (cSerieKey, cLabel, cColor, cImg, nHide, dUpdated) VALUES (:k, :l, :c, :i, :h, NOW())',
                    ['k' => $k, 'l' => $label, 'c' => $color, 'i' => $img, 'h' => $hide]
                );
            }
            $extraKey = \preg_replace('/[^0-9A-Za-z_-]/', '', \trim((string)($_POST['fk_serie_extra_key'] ?? '')));
            if ($extraKey !== '' && $extraKey !== '0') {
                if (!isset($defaults[$extraKey])) {
                    $el = \trim((string)($_POST['fk_serie_extra_label'] ?? ''));
                    $ec = \trim((string)($_POST['fk_serie_extra_color'] ?? ''));
                    $ei = \trim((string)($_POST['fk_serie_extra_img'] ?? ''));
                    $eh = !empty($_POST['fk_serie_extra_hide']) ? 1 : 0;
                    $db->executeQueryPrepared(
                        'INSERT INTO twomosi_fk_serie_meta (cSerieKey, cLabel, cColor, cImg, nHide, dUpdated) VALUES (:k, :l, :c, :i, :h, NOW())',
                        ['k' => $extraKey, 'l' => $el, 'c' => $ec, 'i' => $ei, 'h' => $eh]
                    );
                }
            }
            $message = 'Serien-Metadaten gespeichert.';
        }
        if (!empty($_POST['fkSaveBanner'])) {
            if (!$bannerEnsureOk) {
                throw new \RuntimeException('Banner tablosu oluşturulamadı: ' . $bannerEnsureError);
            }
            $headline = trim((string)($_POST['fkBannerHeadline'] ?? ''));
            $sub1     = trim((string)($_POST['fkBannerSub1'] ?? ''));
            $sub3     = trim((string)($_POST['fkBannerSub3'] ?? ''));
            $nSub1    = !empty($_POST['fkSub1Active']) ? 1 : 0;
            $nSub2    = !empty($_POST['fkSub2Active']) ? 1 : 0;
            $nSub3    = !empty($_POST['fkSub3Active']) ? 1 : 0;
            $nSegWm   = !empty($_POST['fkSegmentWmActive']) ? 1 : 0;
            $nSegPkw  = !empty($_POST['fkSegmentPkwActive']) ? 1 : 0;
            if ($nSegWm !== 1 && $nSegPkw !== 1) {
                $nSegWm = 1;
            }
            $nSegOrder = (int)($_POST['fkSegmentOrder'] ?? 0);
            $btnLabel = trim((string)($_POST['fkSub2ButtonLabel'] ?? 'Aktif et'));
            $btnUrl   = trim((string)($_POST['fkSub2ButtonUrl'] ?? '#'));
            if ($btnLabel === '') {
                $btnLabel = 'Aktif et';
            }
            if ($btnUrl === '') {
                $btnUrl = '#';
            }

            // Her zaman en güncel satırı güncelle (tabloda birden fazla satır olabilir)
            $row = $db->getSingleObject('SELECT kID FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
            if ($row !== null && !empty($row->kID)) {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_fk_banner
                        SET cHeadline = :h, cSub1 = :s1, cSub3 = :s3,
                            nSub1Active = :n1, nSub2Active = :n2, nSub3Active = :n3,
                            cSub2ButtonLabel = :bl, cSub2ButtonUrl = :bu,
                            nSegmentWmActive = :swm, nSegmentPkwActive = :spkw,
                            nSegmentOrder = :sord,
                            dUpdated = NOW()
                      WHERE kID = :id",
                    [
                        'h'  => $headline,
                        's1' => $sub1,
                        's3' => $sub3,
                        'n1' => $nSub1,
                        'n2' => $nSub2,
                        'n3' => $nSub3,
                        'bl' => $btnLabel,
                        'bu' => $btnUrl,
                        'swm' => $nSegWm,
                        'spkw' => $nSegPkw,
                        'sord' => $nSegOrder,
                        'id' => (int)$row->kID,
                    ]
                );
            } else {
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_fk_banner (cHeadline, cSub1, cSub2, cSub3, nSub1Active, nSub2Active, nSub3Active, cSub2ButtonLabel, cSub2ButtonUrl, nSegmentWmActive, nSegmentPkwActive, nSegmentOrder, dUpdated)
                     VALUES (:h, :s1, '', :s3, :n1, :n2, :n3, :bl, :bu, :swm, :spkw, :sord, NOW())",
                    [
                        'h'  => $headline,
                        's1' => $sub1,
                        's3' => $sub3,
                        'n1' => $nSub1,
                        'n2' => $nSub2,
                        'n3' => $nSub3,
                        'bl' => $btnLabel,
                        'bu' => $btnUrl,
                        'swm' => $nSegWm,
                        'spkw' => $nSegPkw,
                        'sord' => $nSegOrder,
                    ]
                );
            }
            // Kaydetme sonrası tekrar DB'den çekip ekranda hemen göster (en güncel satır).
            $bannerRow = $db->getSingleObject('SELECT * FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
            if ($bannerRow !== null) {
                $banner = $bannerRow;
                $message = 'Banner-Inhalt gespeichert. (kID=' . (int)($bannerRow->kID ?? 0) . ')';
            } else {
                $message = 'Banner-Inhalt gespeichert, konnte jedoch nicht erneut gelesen werden.';
            }
        }
    } catch (\Throwable $e) {
        $message = 'Vorgang fehlgeschlagen: ' . $e->getMessage();
    }
}

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'logs';
if (!\in_array($tab, ['logs', 'summary', 'settings', 'banner', 'series'], true)) {
    $tab = 'logs';
}

$cacheCount = 0;
$logCount   = 0;
$selCount   = 0;
$optHerCnt  = 0;
$optBasCnt  = 0;
$optModCnt  = 0;
$optLast    = '';
try {
    $cacheCountRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_step4_cache');
    $cacheCount = isset($cacheCountRow->cnt) ? (int)$cacheCountRow->cnt : 0;

    $logCountRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_search_log');
    $logCount = isset($logCountRow->cnt) ? (int)$logCountRow->cnt : 0;

    $selCountRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_selection');
    $selCount = isset($selCountRow->cnt) ? (int)$selCountRow->cnt : 0;

    $optHerCntRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_hersteller');
    $optHerCnt = isset($optHerCntRow->cnt) ? (int)$optHerCntRow->cnt : 0;
    $optBasCntRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_basis');
    $optBasCnt = isset($optBasCntRow->cnt) ? (int)$optBasCntRow->cnt : 0;
    $optModCntRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_modell');
    $optModCnt = isset($optModCntRow->cnt) ? (int)$optModCntRow->cnt : 0;
    
    // PKW Counts
    $pkwHerCntRow  = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_pkw_hersteller');
    $pkwHerCnt     = isset($pkwHerCntRow->cnt)  ? (int)$pkwHerCntRow->cnt  : 0;
    $pkwModCntRow  = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_pkw_modell');
    $pkwModCnt     = isset($pkwModCntRow->cnt)  ? (int)$pkwModCntRow->cnt  : 0;
    try {
        $pkwBjCntRow   = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_pkw_baujahr');
        $pkwBjCnt      = isset($pkwBjCntRow->cnt)   ? (int)$pkwBjCntRow->cnt   : 0;
    } catch (\Throwable $e) { $pkwBjCnt = 0; }
    try {
        $pkwSerieCntRow = $db->getSingleObject('SELECT COUNT(*) AS cnt FROM twomosi_fk_opt_pkw_serie');
        $pkwSerieCnt    = isset($pkwSerieCntRow->cnt) ? (int)$pkwSerieCntRow->cnt : 0;
    } catch (\Throwable $e) { $pkwSerieCnt = 0; }

    $optLastRow = $db->getSingleObject('SELECT dRefreshed FROM twomosi_fk_opt_meta ORDER BY dRefreshed DESC, kID DESC LIMIT 1');
    $optLast    = isset($optLastRow->dRefreshed) ? (string)$optLastRow->dRefreshed : '';

    $pkwLastRow = $db->getSingleObject('SELECT dRefreshed FROM twomosi_fk_opt_pkw_meta ORDER BY dRefreshed DESC, kSprache DESC LIMIT 1');
    $pkwLast    = isset($pkwLastRow->dRefreshed) ? (string)$pkwLastRow->dRefreshed : '';
} catch (\Throwable $e) {
    $cacheCount = 0;
    $logCount   = 0;
    $selCount   = 0;
    $optHerCnt  = 0;
    $optBasCnt  = 0;
    $optModCnt  = 0;
    $optLast    = '';
}

try {
    $bannerRow = $db->getSingleObject('SELECT * FROM twomosi_fk_banner ORDER BY dUpdated DESC, kID DESC LIMIT 1');
    if ($bannerRow !== null) {
        $banner = $bannerRow;
    }
} catch (\Throwable $e) {
    // keep default values if table does not exist yet
}
try {
    $scopeRow = $db->getSingleObject('SELECT cCategoryIds FROM twomosi_fk_micro_scope WHERE kID = 1 LIMIT 1');
    if ($scopeRow !== null) {
        $sc = trim((string)($scopeRow->cCategoryIds ?? ''));
        if ($sc !== '') {
            $banner->cActiveCategories = $sc;
        }
    }
} catch (\Throwable $e) {
}

$seriesDefaults = SeriesMetaRuntime::getBuiltInDefaults();
$seriesDbMap    = [];
try {
    $seriesDbRows = $db->getObjects('SELECT cSerieKey, cLabel, cColor, cImg, nHide FROM twomosi_fk_serie_meta');
    if (\is_array($seriesDbRows)) {
        foreach ($seriesDbRows as $sr) {
            $sk = \trim((string)($sr->cSerieKey ?? ''));
            if ($sk !== '') {
                $seriesDbMap[$sk] = $sr;
            }
        }
    }
} catch (\Throwable $e) {
    $seriesDbMap = [];
}
$seriesExtraKey = '';
foreach (\array_keys($seriesDbMap) as $exk) {
    if (!isset($seriesDefaults[$exk])) {
        $seriesExtraKey = $exk;
        break;
    }
}

$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page  = $page > 0 ? $page : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$rows    = [];
$selRows = [];

if ($tab === 'summary') {
    try {
        $selRows = $db->getObjects(
                "SELECT dCreated, cIP, cHersteller, cBasis, cModell, cBaujahr, cSitz, cSerie
               FROM twomosi_fk_selection
              ORDER BY dCreated DESC
              LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    } catch (\Throwable $e) {
        $selRows = [];
    }
} else {
    try {
        $rows = $db->getObjects(
            "SELECT cIP, dCreated, cMode, cField, cParams, nDurationMs, cUserAgent
               FROM twomosi_fk_search_log
              ORDER BY dCreated DESC
              LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
    } catch (\Throwable $e) {
        $rows = [];
    }
}
?>

<div style="padding: 10px 0;">
    <h2 style="margin: 0 0 12px;"><?= htmlspecialchars((string)($message ? $message : 'Filter Konfigurator Dashboard')) ?></h2>

    <div style="margin-bottom: 14px; display: flex; gap: 10px; align-items: center;">
        <a href="?tab=logs" style="text-decoration: none; padding: 8px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 8px; <?= $tab === 'logs' ? 'background: rgba(204,255,0,.15);' : '' ?>">Suchprotokoll</a>
        <a href="?tab=summary" style="text-decoration: none; padding: 8px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 8px; <?= $tab === 'summary' ? 'background: rgba(204,255,0,.15);' : '' ?>">Step-4-Auswahlübersicht</a>
        <a href="?tab=banner" style="text-decoration: none; padding: 8px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 8px; <?= $tab === 'banner' ? 'background: rgba(204,255,0,.15);' : '' ?>">Banner</a>
        <a href="?tab=settings" style="text-decoration: none; padding: 8px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 8px; <?= $tab === 'settings' ? 'background: rgba(204,255,0,.15);' : '' ?>">Einstellungen</a>
        <a href="?tab=series" style="text-decoration: none; padding: 8px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 8px; <?= $tab === 'series' ? 'background: rgba(204,255,0,.15);' : '' ?>">Serie (Bilder)</a>
    </div>

    <div style="margin-bottom: 16px; font-size: 13px; line-height: 1.5;">
        <div><b>Step-4-Cache:</b> <?= (int)$cacheCount ?> Einträge</div>
        <div><b>Suchprotokoll:</b> <?= (int)$logCount ?> Einträge</div>
        <div><b>Step-4-Auswahlen:</b> <?= (int)$selCount ?> Einträge</div>
        <div style="margin-top: 4px;">
            <b>Snapshot (Wohnmobil):</b> Hersteller=<?= (int)$optHerCnt ?>, Basis=<?= (int)$optBasCnt ?>, Modell=<?= (int)$optModCnt ?> <?= $optLast ? '<span style="color:rgba(0,0,0,.5);">(Aktualisiert: ' . htmlspecialchars($optLast) . ')</span>' : '' ?>
        </div>
        <div>
            <b>Snapshot (PKW):</b>
            Hersteller=<?= (int)$pkwHerCnt ?>,
            Modell=<?= (int)$pkwModCnt ?>,
            Baujahr=<?= (int)$pkwBjCnt ?>,
            Serien=<?= (int)$pkwSerieCnt ?>
            <?= $pkwLast ? '<span style="color:rgba(0,0,0,.5);">(Aktualisiert: ' . htmlspecialchars($pkwLast) . ')</span>' : '' ?>
        </div>
    </div>

    <?php if ($tab === 'series'): ?>
        <div style="padding: 12px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px; max-width: 1100px;">
            <h3 style="margin-top: 0;">Serie — Labels, Farben, Bild-URLs, Ausblenden</h3>
            <p style="font-size: 13px; color: rgba(0,0,0,.6); max-width: 900px;">
                Gilt für den großen Konfigurator (Startseite) und den Micro-Konfigurator (Kategorie-Listen). Leere Label-/Farb-/Bild-Felder nutzen die fest eingebauten Standardwerte.
                <strong>„In Liste ausblenden“</strong> entfernt die Serie aus der Kachel- und Dropdown-Auswahl (API liefert sie weiter, Frontend filtert).
            </p>
            <form method="post" style="display: block;">
                <?= Form::getTokenInput(); ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 12px; min-width: 720px;">
                        <thead>
                        <tr>
                            <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">Code</th>
                            <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">Label</th>
                            <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px; white-space: nowrap;">Farbe</th>
                            <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 8px;">Bild-URL</th>
                            <th style="text-align: center; border-bottom: 1px solid #ddd; padding: 8px; white-space: nowrap;">Ausblenden</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($seriesDefaults as $code => $def): ?>
                            <?php
                            $dbR = $seriesDbMap[$code] ?? null;
                            $dLabel = $dbR ? \trim((string)($dbR->cLabel ?? '')) : '';
                            $dColor = $dbR ? \trim((string)($dbR->cColor ?? '')) : '';
                            $dImg   = $dbR ? \trim((string)($dbR->cImg ?? '')) : '';
                            $dHide  = $dbR && (int)($dbR->nHide ?? 0) === 1;
                            $dispL  = $dLabel !== '' ? $dLabel : (string)($def['label'] ?? '');
                            ?>
                            <tr>
                                <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; font-weight: 600;"><?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?></td>
                                <td style="border-bottom: 1px solid #f0f0f0; padding: 4px;">
                                    <input type="text" name="fk_serie_label[<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars($dLabel, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars($dispL, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; padding: 6px; box-sizing: border-box;">
                                </td>
                                <td style="border-bottom: 1px solid #f0f0f0; padding: 4px;">
                                    <input type="text" name="fk_serie_color[<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars($dColor, ENT_QUOTES, 'UTF-8') ?>" placeholder="#f5f5f5" style="width: 100%; min-width: 90px; padding: 6px; box-sizing: border-box;">
                                </td>
                                <td style="border-bottom: 1px solid #f0f0f0; padding: 4px;">
                                    <input type="text" name="fk_serie_img[<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars($dImg, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars((string)($def['img'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; min-width: 200px; padding: 6px; box-sizing: border-box;">
                                </td>
                                <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; text-align: center;">
                                    <input type="checkbox" name="fk_serie_hide[<?= htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') ?>]" value="1" <?= $dHide ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 20px; padding: 12px; border: 1px dashed rgba(0,0,0,.12); border-radius: 6px; background: #fafafa;">
                    <strong>Zusätzlicher Serien-Code (optional)</strong>
                    <p style="font-size: 12px; color: rgba(0,0,0,.55); margin: 4px 0 8px;">Nur ausfüllen, wenn die API eine weitere <code>Serie</code> liefert, die nicht in der Standardliste steht.</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                        <input type="text" name="fk_serie_extra_key" value="<?= htmlspecialchars($seriesExtraKey, ENT_QUOTES, 'UTF-8') ?>" placeholder="z.B. XY" style="width: 100px; padding: 6px;">
                        <?php
                        $exR = $seriesExtraKey !== '' && isset($seriesDbMap[$seriesExtraKey]) ? $seriesDbMap[$seriesExtraKey] : null;
                        $exL = $exR ? \trim((string)($exR->cLabel ?? '')) : '';
                        $exC = $exR ? \trim((string)($exR->cColor ?? '')) : '';
                        $exI = $exR ? \trim((string)($exR->cImg ?? '')) : '';
                        $exH = $exR && (int)($exR->nHide ?? 0) === 1;
                        ?>
                        <input type="text" name="fk_serie_extra_label" value="<?= htmlspecialchars($exL, ENT_QUOTES, 'UTF-8') ?>" placeholder="Label" style="flex: 1; min-width: 140px; padding: 6px;">
                        <input type="text" name="fk_serie_extra_color" value="<?= htmlspecialchars($exC, ENT_QUOTES, 'UTF-8') ?>" placeholder="#f5f5f5" style="width: 100px; padding: 6px;">
                        <input type="text" name="fk_serie_extra_img" value="<?= htmlspecialchars($exI, ENT_QUOTES, 'UTF-8') ?>" placeholder="Bild-URL" style="flex: 2; min-width: 200px; padding: 6px;">
                        <label style="font-size: 12px; white-space: nowrap;"><input type="checkbox" name="fk_serie_extra_hide" value="1" <?= $exH ? 'checked' : '' ?>> ausblenden</label>
                    </div>
                </div>
                <div style="margin-top: 16px;">
                    <button type="submit" name="fkSaveSeriesMeta" value="1" style="padding: 8px 14px;">Speichern</button>
                </div>
            </form>
        </div>
    <?php elseif ($tab === 'settings'): ?>
        <?php
        $catTree = [];
        $catError = '';
        try {
            $langID = (int)Shop::getLanguageID();
            if ($langID <= 0) {
                $langID = 1;
            }
            // Robust: if backend language has no translation rows, fallback to language 1 via COALESCE.
            // Use LEFT JOIN to never lose categories due to missing tkategoriesprache rows.
            $catTree = $db->getObjects(
                "SELECT
                    k.kKategorie,
                    k.kOberKategorie,
                    COALESCE(NULLIF(ks1.cName, ''), NULLIF(ksDef.cName, ''), NULLIF(k.cName, ''), '') AS cName,
                    k.nLevel
                 FROM tkategorie k
                 LEFT JOIN tkategoriesprache ks1
                        ON ks1.kKategorie = k.kKategorie
                       AND ks1.kSprache   = :lang
                 LEFT JOIN tkategoriesprache ksDef
                        ON ksDef.kKategorie = k.kKategorie
                       AND ksDef.kSprache   = 1
                 ORDER BY k.lft ASC",
                ['lang' => $langID]
            );
        } catch (\Throwable $e) {
            $catError = $e->getMessage();
        }
        
        $selectedIds = [];
        if (!empty($banner->cActiveCategories)) {
            foreach (\explode(',', (string)$banner->cActiveCategories) as $sid) {
                $sid = \trim($sid);
                if ($sid !== '' && (int)$sid > 0) {
                    $selectedIds[(int)$sid] = true;
                }
            }
        }
        $selectedPkwIds = [];
        if (!empty($banner->cSegmentPkwFirstCategories)) {
            foreach (\explode(',', (string)$banner->cSegmentPkwFirstCategories) as $sid) {
                $sid = \trim($sid);
                if ($sid !== '' && (int)$sid > 0) {
                    $selectedPkwIds[(int)$sid] = true;
                }
            }
        }
        ?>
        <div style="margin-bottom: 18px; padding: 12px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px; max-width: 760px;">
            <h3 style="margin-top: 0;">System-Werkzeuge</h3>

            <form method="post" id="fkToolsForm" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px;">
                <?= Form::getTokenInput(); ?>
                <button type="button" id="fkSnapshotBtn" onclick="fkRunSnapshotSteps(this)" style="padding: 8px 16px; background: #197149; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 700;">
                    ⚡ Options-Snapshot aktualisieren (4 Schritte)
                </button>
                <button type="submit" name="fkClearStep4Cache" value="1" style="padding: 8px 12px;">Step-4-Cache leeren</button>
                <button type="submit" name="fkClearQueryLog" value="1" style="padding: 8px 12px;">Suchprotokoll leeren</button>
            </form>

            <!-- Snapshot progress UI -->
            <div id="fkSnapshotProgress" style="display:none; margin-bottom:20px; padding:14px; background:#f0faf5; border:1px solid #c3e6d8; border-radius:8px;">
                <div style="font-weight:700; margin-bottom:10px; color:#197149;">🔄 Snapshot wird aktualisiert...</div>
                <div style="background:#e0e0e0; border-radius:999px; height:10px; overflow:hidden; margin-bottom:10px;">
                    <div id="fkSnapshotBar" style="height:100%; background:#197149; border-radius:999px; transition:width .4s; width:0%;"></div>
                </div>
                <div id="fkSnapshotLog" style="font-size:12px; color:#444; line-height:1.8;"></div>
            </div>

            <script>
            function fkRunSnapshotSteps(btn) {
                btn.disabled = true;
                var progress = document.getElementById('fkSnapshotProgress');
                var bar      = document.getElementById('fkSnapshotBar');
                var log      = document.getElementById('fkSnapshotLog');
                progress.style.display = 'block';
                log.innerHTML = '';

                // Collect ALL hidden inputs from the form (handles any JTL token field name)
                var form = document.getElementById('fkToolsForm');
                var baseFormData = new FormData();
                var hiddenInputs = form.querySelectorAll('input[type="hidden"]');
                hiddenInputs.forEach(function(inp) {
                    baseFormData.append(inp.name, inp.value);
                });

                var steps = [
                    { field: 'fkSnapshotStepWm',        label: 'Schritt 1/4: Wohnmobil (Hersteller, Basis, Modell)' },
                    { field: 'fkSnapshotStepPkwHerMod', label: 'Schritt 2/4: PKW Hersteller & Modell' },
                    { field: 'fkSnapshotStepPkwBj',     label: 'Schritt 3/4: PKW Baujahr' },
                    { field: 'fkSnapshotStepPkwSerie',  label: 'Schritt 4/4: PKW Serien (dauert am längsten)' },
                ];

                function runStep(i) {
                    if (i >= steps.length) {
                        bar.style.width = '100%';
                        log.innerHTML += '<br><b style="color:#197149">✅ Alle Snapshots erfolgreich aktualisiert!</b>';
                        btn.disabled = false;
                        btn.innerHTML = '✅ Fertig — nochmals aktualisieren';
                        return;
                    }
                    var step = steps[i];
                    bar.style.width = Math.round((i / steps.length) * 100) + '%';
                    log.innerHTML += '⏳ ' + step.label + '...<br>';

                    var body = new FormData();
                    hiddenInputs.forEach(function(inp) { body.append(inp.name, inp.value); });
                    body.append(step.field, '1');

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: body
                    })
                    .then(function(r) {
                        var ct = r.headers.get('content-type') || '';
                        if (!ct.includes('json')) {
                            return r.text().then(function(txt) {
                                throw new Error('Kein JSON erhalten. Serverantwort: ' + txt.substring(0, 200));
                            });
                        }
                        return r.json();
                    })
                    .then(function(data) {
                        if (data.ok) {
                            log.innerHTML += '✅ ' + (data.msg || step.label + ' OK') + '<br>';
                        } else {
                            log.innerHTML += '⚠️ ' + (data.msg || 'Fehler bei ' + step.label) + '<br>';
                        }
                        bar.style.width = Math.round(((i + 1) / steps.length) * 100) + '%';
                        runStep(i + 1);
                    })
                    .catch(function(err) {
                        log.innerHTML += '❌ Netzwerkfehler: ' + err + '<br>';
                        btn.disabled = false;
                    });
                }

                runStep(0);
            }
            </script>

            <h3 style="margin-top: 0; border-top: 1px solid rgba(0,0,0,.08); padding-top: 18px;">Allgemeine Einstellungen</h3>
            <form method="post" style="display: grid; gap: 10px;">
                <?= Form::getTokenInput(); ?>
                
                <div style="padding:16px; margin-bottom:10px; border:1px solid rgba(0,0,0,.1); border-radius:8px; background:#fafafa;">
                    <h4 style="margin:0 0 6px;">KI-Sitz-Fotoerkennung</h4>
                    <p style="margin:0 0 14px; color:rgba(0,0,0,.6); font-size:13px;">
                        Der API-Key bleibt serverseitig und wird nicht an den Browser ausgegeben.
                        Status:
                        <?php if ($aiApiKey !== ''): ?>
                            <strong style="color:#198754;">API-Key gespeichert</strong>
                        <?php else: ?>
                            <strong style="color:#b42318;">Kein API-Key gespeichert</strong>
                        <?php endif; ?>
                    </p>

                    <label style="display:flex; gap:8px; align-items:center; margin-bottom:14px;">
                        <input type="checkbox" name="fkSeatPhotoEnabled" value="1" <?= $aiPhotoEnabled === 1 ? 'checked' : '' ?>>
                        <strong>Fotoerkennung aktivieren</strong>
                    </label>

                    <div style="display:grid; grid-template-columns:minmax(260px,2fr) minmax(180px,1fr); gap:12px;">
                        <label>
                            <strong>OpenAI API Key</strong><br>
                            <input type="password" name="fkOpenAiApiKey" value="" autocomplete="new-password"
                                   placeholder="<?= $aiApiKey !== '' ? 'Gespeichert – nur zum Ändern neu eingeben' : 'sk-proj-…' ?>"
                                   style="width:100%; box-sizing:border-box; padding:8px; margin-top:5px;">
                        </label>
                        <label>
                            <strong>OpenAI Vision Model</strong><br>
                            <input type="text" name="fkOpenAiModel"
                                   value="<?= htmlspecialchars($aiModel, ENT_QUOTES, 'UTF-8') ?>"
                                   style="width:100%; box-sizing:border-box; padding:8px; margin-top:5px;">
                        </label>
                    </div>
                    <?php if ($aiApiKey !== ''): ?>
                        <label style="display:flex; gap:8px; align-items:center; margin-top:10px; color:#b42318; font-size:12px;">
                            <input type="checkbox" name="fkOpenAiApiKeyClear" value="1">
                            Gespeicherten API-Key löschen
                        </label>
                    <?php endif; ?>
                </div>

                <div>
                    <strong>Aktive Kategorie(n)</strong><br>
                    <p style="margin:4px 0 8px; font-size:12px; color:rgba(0,0,0,.55);">Checkboxen zuverlässiger als Mehrfach-Select: jede markierte Kategorie wird gespeichert. Unterkategorien der Auswahl gelten automatisch als erlaubt. Für <strong>alle</strong> Kategorien: alle Häkchen entfernen und speichern.</p>
                    
                    <?php if (!empty($catError)): ?>
                        <div style="padding:10px; background:#ffebeb; border:1px solid #ffcdcd; color:#d00; margin-bottom:10px; border-radius:4px;">
                            <strong>Kategorie-Fehler:</strong> <?= htmlspecialchars($catError) ?>
                        </div>
                    <?php endif; ?>

                    <div id="fkActiveCategories" style="width:100%; max-height:320px; overflow:auto; padding:8px; border:1px solid #ccc; border-radius:4px; background:#fafafa;">
                        <?php foreach ($catTree as $cat): ?>
                            <?php
                            $kid = (int)($cat->kKategorie ?? 0);
                            if ($kid <= 0) {
                                continue;
                            }
                            $pad = (int)($cat->nLevel > 0 ? $cat->nLevel - 1 : 0) * 16;
                            $isSelected = isset($selectedIds[$kid]);
                            ?>
                            <label style="display:block; padding:3px 4px 3px <?= (int)$pad ?>px; cursor:pointer;">
                                <input type="checkbox" name="fkActiveCategories[]" value="<?= $kid ?>" <?= $isSelected ? 'checked' : '' ?>>
                                <?= htmlspecialchars((string)($cat->cName ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <span style="color:rgba(0,0,0,.35); font-size:11px;">(<?= $kid ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <strong>PKW an 1. Stelle (Micro-Konfigurator)</strong><br>
                    <p style="margin:4px 0 8px; font-size:12px; color:rgba(0,0,0,.55);">In den hier ausgewählten Kategorien wird der PKW-Tab an erster Stelle angezeigt und ist standardmäßig aktiv. In nicht gewählten Kategorien gilt die allgemeine Einstellung (Reihenfolge im Banner-Tab).</p>
                    
                    <div id="fkPkwFirstCategories" style="width:100%; max-height:320px; overflow:auto; padding:8px; border:1px solid #ccc; border-radius:4px; background:#fafafa;">
                        <?php foreach ($catTree as $cat): ?>
                            <?php
                            $kid = (int)($cat->kKategorie ?? 0);
                            if ($kid <= 0) {
                                continue;
                            }
                            $pad = (int)($cat->nLevel > 0 ? $cat->nLevel - 1 : 0) * 16;
                            $isSelectedPkw = isset($selectedPkwIds[$kid]);
                            ?>
                            <label style="display:block; padding:3px 4px 3px <?= (int)$pad ?>px; cursor:pointer;">
                                <input type="checkbox" name="fkPkwFirstCategories[]" value="<?= $kid ?>" <?= $isSelectedPkw ? 'checked' : '' ?>>
                                <?= htmlspecialchars((string)($cat->cName ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <span style="color:rgba(0,0,0,.35); font-size:11px;">(<?= $kid ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <button type="submit" name="fkSaveSettingsTab" value="1" style="padding: 8px 14px;">
                        Einstellungen speichern
                    </button>
                </div>
            </form>
        </div>
    <?php elseif ($tab === 'banner'): ?>
        <div style="padding: 12px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px; max-width: 760px;">
            <h3 style="margin-top: 0;">Banner bearbeiten</h3>

            <form method="post" style="display: grid; gap: 10px;">
                <?= Form::getTokenInput(); ?>

                <div>
                    <label for="fkBannerHeadline"><strong>Überschrift</strong></label><br>
                    <input type="text" id="fkBannerHeadline" name="fkBannerHeadline"
                           value="<?= htmlspecialchars((string)($banner->cHeadline ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           style="width:100%; padding:8px;">
                </div>

                <div style="border:1px solid rgba(0,0,0,.08); border-radius:8px; padding:10px; margin-bottom:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:700;">
                        <input type="checkbox" name="fkSub1Active" value="1" <?= (int)($banner->nSub1Active ?? 0) === 1 ? 'checked' : '' ?>>
                        Sub 1 aktiv
                    </label>
                    <label for="fkBannerSub1" style="display:block; margin-top:8px;">Text</label>
                    <input type="text" id="fkBannerSub1" name="fkBannerSub1"
                           value="<?= htmlspecialchars((string)($banner->cSub1 ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           style="width:100%; padding:8px;">
                </div>

                <div style="border:1px solid rgba(0,0,0,.08); border-radius:8px; padding:10px; margin-bottom:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:700;">
                        <input type="checkbox" name="fkSub2Active" value="1" <?= (int)($banner->nSub2Active ?? 0) === 1 ? 'checked' : '' ?>>
                        Sub 2 — Button anzeigen (aktiv)
                    </label>
                    <p style="margin:8px 0 0; font-size:12px; color:rgba(0,0,0,.55);">Eski düz metin yerine tek bir buton (başlık + yönlendirme).</p>
                    <div style="margin-top:10px; display:grid; gap:8px;">
                        <div>
                            <label for="fkSub2ButtonLabel">Button-Text</label><br>
                            <input type="text" id="fkSub2ButtonLabel" name="fkSub2ButtonLabel"
                                   value="<?= htmlspecialchars((string)($banner->cSub2ButtonLabel ?? 'Aktif et'), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="Aktif et"
                                   style="width:100%; padding:8px;">
                        </div>
                        <div>
                            <label for="fkSub2ButtonUrl">Weiterleitungs-URL</label><br>
                            <input type="text" id="fkSub2ButtonUrl" name="fkSub2ButtonUrl"
                                   value="<?= htmlspecialchars((string)($banner->cSub2ButtonUrl ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="https://..."
                                   style="width:100%; padding:8px;">
                        </div>
                    </div>
                </div>

                <div style="border:1px solid rgba(0,0,0,.08); border-radius:8px; padding:10px; margin-bottom:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:700;">
                        <input type="checkbox" name="fkSub3Active" value="1" <?= (int)($banner->nSub3Active ?? 0) === 1 ? 'checked' : '' ?>>
                        Sub 3 aktiv
                    </label>
                    <label for="fkBannerSub3" style="display:block; margin-top:8px;">Text</label>
                    <input type="text" id="fkBannerSub3" name="fkBannerSub3"
                           value="<?= htmlspecialchars((string)($banner->cSub3 ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           style="width:100%; padding:8px;">
                </div>

                <div style="border:1px solid rgba(0,0,0,.08); border-radius:8px; padding:10px; margin-bottom:8px;">
                    <strong>Konfigurator-Tabs</strong>
                    <p style="margin:6px 0 10px; font-size:12px; color:rgba(0,0,0,.55);">PKW kutusunu kaldırırsanız vitrinde yalnızca Wohnmobil akışı görünür. İkisini de kapatamazsınız (en az biri açık kalır).</p>
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="fkSegmentWmActive" value="1" <?= (int)($banner->nSegmentWmActive ?? 1) === 1 ? 'checked' : '' ?>>
                        Wohnmobil-Tab / Workflow
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; margin-top:8px;">
                        <input type="checkbox" name="fkSegmentPkwActive" value="1" <?= (int)($banner->nSegmentPkwActive ?? 1) === 1 ? 'checked' : '' ?>>
                        PKW-Tab / Workflow
                    </label>
                    <div style="margin-top:12px; border-top:1px solid rgba(0,0,0,.05); padding-top:10px;">
                        <label for="fkSegmentOrder">Welcher Tab soll an 1. Stelle stehen?</label><br>
                        <select id="fkSegmentOrder" name="fkSegmentOrder" style="padding:6px; margin-top:4px;">
                            <option value="0" <?= (int)($banner->nSegmentOrder ?? 0) === 0 ? 'selected' : '' ?>>Wohnmobil zuerst</option>
                            <option value="1" <?= (int)($banner->nSegmentOrder ?? 0) === 1 ? 'selected' : '' ?>>PKW zuerst</option>
                        </select>
                    </div>
                </div>

                <div>
                    <button type="submit" name="fkSaveBanner" value="1" style="padding: 8px 14px;">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    <?php elseif ($tab === 'summary'): ?>
        <div style="padding: 12px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px;">
            <h3 style="margin-top: 0;">Step-4-Auswahlübersicht (Serie gewählt)</h3>

            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px; white-space: nowrap;">Datum</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px; white-space: nowrap;">IP</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Fahrzeug-Marke</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Wohnmobil-Fahrgestell</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Fahrzeug-Modell</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px; white-space: nowrap;">Baujahr</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px; white-space: nowrap;">Sitz</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Serie</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($selRows as $r): ?>
                    <tr>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px; white-space: nowrap;"><?= htmlspecialchars((string)($r->dCreated ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px; white-space: nowrap;"><?= htmlspecialchars((string)($r->cIP ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px;"><?= htmlspecialchars((string)($r->cHersteller ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px;"><?= htmlspecialchars((string)($r->cBasis ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px;"><?= htmlspecialchars((string)($r->cModell ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px; white-space: nowrap;"><?= htmlspecialchars((string)($r->cBaujahr ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px; white-space: nowrap;"><?= htmlspecialchars((string)($r->cSitz ?? '')) ?></td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 10px;"><?= htmlspecialchars((string)($r->cSerie ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!\count($selRows)): ?>
                    <tr><td colspan="8" style="padding: 12px; color: rgba(0,0,0,.6);">Noch keine Auswahl-Einträge vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 12px; display: flex; gap: 10px; align-items: center;">
                <div>Seite: <?= (int)$page ?></div>
                <a href="?tab=summary&page=<?= (int)max(1, $page - 1) ?>" style="text-decoration: none; padding: 6px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 6px;">
                    &laquo; Zurück
                </a>
                <a href="?tab=summary&page=<?= (int)($page + 1) ?>" style="text-decoration: none; padding: 6px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 6px;">
                    Weiter &raquo;
                </a>
            </div>
        </div>
    <?php else: ?>
        <div style="padding: 12px; border: 1px solid rgba(0,0,0,.08); border-radius: 8px;">
            <h3 style="margin-top: 0;">Letzte Suchanfragen</h3>

            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Datum</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">IP</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Modus</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Feld</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Dauer (ms)</th>
                        <th style="text-align: left; border-bottom: 1px solid #ddd; padding: 6px;">Parameter</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $paramsArr = json_decode((string)($r->cParams ?? ''), true);
                        if (!is_array($paramsArr)) {
                            $paramsArr = [];
                        }
                    ?>
                    <tr>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; white-space: nowrap;">
                            <?= htmlspecialchars((string)($r->dCreated ?? '')) ?>
                        </td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; white-space: nowrap;">
                            <?= htmlspecialchars((string)($r->cIP ?? '')) ?>
                        </td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; white-space: nowrap;">
                            <?= htmlspecialchars((string)($r->cMode ?? '')) ?>
                        </td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; white-space: nowrap;">
                            <?= htmlspecialchars((string)($r->cField ?? '')) ?>
                        </td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 6px; white-space: nowrap;">
                            <?= htmlspecialchars((string)($r->nDurationMs ?? 0)) ?>
                        </td>
                        <td style="border-bottom: 1px solid #f0f0f0; padding: 6px;">
                            <pre style="margin: 0; white-space: pre-wrap; word-break: break-word;"><?= htmlspecialchars(\json_encode($paramsArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!\count($rows)): ?>
                    <tr><td colspan="6" style="padding: 12px; color: rgba(0,0,0,.6);">Noch keine Protokolle vorhanden.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 12px; display: flex; gap: 10px; align-items: center;">
                <div>Seite: <?= (int)$page ?></div>
                <a href="?tab=logs&page=<?= (int)max(1, $page - 1) ?>" style="text-decoration: none; padding: 6px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 6px;">
                    &laquo; Zurück
                </a>
                <a href="?tab=logs&page=<?= (int)($page + 1) ?>" style="text-decoration: none; padding: 6px 10px; border: 1px solid rgba(0,0,0,.15); border-radius: 6px;">
                    Weiter &raquo;
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

