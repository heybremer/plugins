<?php

declare(strict_types=1);

if (!defined('PFAD_ROOT')) {
    die('Kein direkter Zugriff erlaubt.');
}

use JTL\Helpers\Form;

/* ═══════════════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════════════ */
if (!function_exists('bd_h')) {
function bd_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function bd_normalize_custom_selector(string $raw): string
{
    $s = trim($raw);
    while (str_starts_with(strtolower($s), 'custom:')) {
        $s = trim(substr($s, 7));
    }

    return $s;
}

function bd_db(): \JTL\DB\DbInterface
{
    return \JTL\Shop::Container()->getDB();
}

/**
 * JTL Admin'de mevcut plugin sayfasının base URL'sini döner.
 * Tüm form action ve link'ler bu üzerine inşa edilir.
 */
function bd_admin_url(): string
{
    // JTL 5.x admin URL yapısı: /admin/plugin.php?kPlugin=X
    // ya da yeni router: /admin/plugins/BremerDesc/dashboard
    // Her iki durumda da $_SERVER['REQUEST_URI'] base'ini kullanabiliriz
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    // Sadece path + query string'deki kPlugin, kAdminMenu vb. parametreleri koru
    $parsed = parse_url($uri);
    $path   = $parsed['path'] ?? '/admin/plugin.php';
    $query  = [];
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $query);
    }
    // Bizim eklediğimiz parametreleri temizle, JTL'nin parametrelerini koru
    $keep = [];
    foreach (['kPlugin', 'kAdminMenu', 'Plugin', 'plugin'] as $k) {
        if (isset($query[$k])) { $keep[$k] = $query[$k]; }
    }
    $base = $path;
    if (!empty($keep)) { $base .= '?' . http_build_query($keep); }
    return $base;
}

/**
 * bd_admin_url()'e ek parametreler ekler.
 */
function bd_url(array $extra = []): string
{
    $base = bd_admin_url();
    if (empty($extra)) { return $base; }
    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . http_build_query($extra);
}

function bd_ensure_table(): void
{
    static $done = false;
    if ($done) return;
    try {
        $db = bd_db();
        $db->executeQuery(
            'CREATE TABLE IF NOT EXISTS twomosi_bremer_desc (
                kID INT AUTO_INCREMENT PRIMARY KEY,
                cType VARCHAR(20) NOT NULL DEFAULT "artikel",
                kArtikel INT NOT NULL DEFAULT 0,
                nActive TINYINT(1) NOT NULL DEFAULT 1,
                cVideoUrl VARCHAR(512) NOT NULL DEFAULT "",
                cBilderTabLabel VARCHAR(120) NOT NULL DEFAULT "Produktbilder",
                cImagesJson LONGTEXT NULL,
                cTabsJson LONGTEXT NULL,
                cCategoriesJson LONGTEXT NULL,
                dUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_type_artikel (cType, kArtikel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        // Eksik kolonları güvenli şekilde ekle
        $existingCols = [];
        try {
            $colRows = $db->getObjects('SHOW COLUMNS FROM twomosi_bremer_desc') ?: [];
            foreach ($colRows as $c) {
                $existingCols[] = strtolower((string)($c->Field ?? $c->field ?? ''));
            }
        } catch (\Throwable $e) {}
        $neededCols = [
            'cTabsJson'       => 'LONGTEXT NULL',
            'cCategoriesJson' => 'LONGTEXT NULL',
            'cInjectPosition' => "VARCHAR(200) NOT NULL DEFAULT 'content_top'",
        ];
        foreach ($neededCols as $colName => $colDef) {
            if (!in_array(strtolower($colName), $existingCols, true)) {
                try { $db->executeQuery("ALTER TABLE twomosi_bremer_desc ADD COLUMN `{$colName}` {$colDef}"); }
                catch (\Throwable $e) {}
            }
        }
        $done = true;
    } catch (\Throwable $e) {}
}

function bd_load(string $type, int $kArtikel): ?\stdClass
{
    try {
        return bd_db()->getSingleObject(
            'SELECT * FROM twomosi_bremer_desc WHERE cType = :t AND kArtikel = :k LIMIT 1',
            ['t' => $type, 'k' => $kArtikel]
        );
    } catch (\Throwable $e) { return null; }
}

function bd_save_row(\stdClass $obj): void
{
    $db  = bd_db();
    $row = bd_load($obj->cType, $obj->kArtikel);
    $obj->dUpdated = date('Y-m-d H:i:s');
    try {
        if ($row === null) { $db->insert('twomosi_bremer_desc', $obj); }
        else { $db->update('twomosi_bremer_desc', ['cType', 'kArtikel'], [$obj->cType, $obj->kArtikel], $obj); }
    } catch (\Throwable $e) {}
}

function bd_delete(string $type, int $kArtikel): void
{
    bd_db()->executeQueryPrepared(
        'DELETE FROM twomosi_bremer_desc WHERE cType = :t AND kArtikel = :k',
        ['t' => $type, 'k' => $kArtikel]
    );
}

/** Media klasör yolu ve URL */
function bd_media_dir(): string
{
    return \PFAD_ROOT . 'plugins/BremerDesc/media/';
}

function bd_media_url(): string
{
    return \JTL\Shop::getURL() . '/plugins/BremerDesc/media/';
}

/**
 * AJAX yanıtları için cache + JSON header (Cloudflare edge cache önleme).
 */
function bd_ajax_headers(): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

/**
 * AJAX isteklerinde CSRF token doğrula — Cloudflare arkasında session cookie gerekli.
 */
function bd_require_ajax_token(): void
{
    bd_ajax_headers();
    if (!class_exists('JTL\Helpers\Form') || !Form::validateToken()) {
        echo json_encode([
            'ok'    => false,
            'error' => 'Token ungültig — bitte Seite neu laden (F5) und erneut versuchen.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * AJAX upload handler — $_FILES['file'] yükler, URL döner
 * Dönen değer: JSON {ok:true, url:"..."} veya {ok:false, error:"..."}
 */
function bd_handle_upload(): void
{
    bd_require_ajax_token();

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['file']['error'] ?? -1;
        echo json_encode(['ok' => false, 'error' => 'Upload-Fehler ' . $errCode]);
        exit;
    }

    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $mime    = mime_content_type($_FILES['file']['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Nicht erlaubter Dateityp: ' . $mime]);
        exit;
    }

    $ext     = match ($mime) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png'               => 'png',
        'image/webp'              => 'webp',
        'image/gif'               => 'gif',
        default                   => 'jpg',
    };

    $dir = bd_media_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = uniqid('bd_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => 'Konnte Datei nicht speichern.']);
        exit;
    }

    echo json_encode(['ok' => true, 'url' => bd_media_url() . $filename, 'filename' => $filename]);
    exit;
}

/**
 * AJAX delete handler — uploaded dosyayı sil
 */
function bd_handle_delete_file(): void
{
    bd_require_ajax_token();

    $filename = basename((string)($_POST['filename'] ?? ''));
    if ($filename === '') {
        echo json_encode(['ok' => false, 'error' => 'Kein Dateiname.']);
        exit;
    }
    $path = bd_media_dir() . $filename;
    if (is_file($path)) {
        unlink($path);
    }
    echo json_encode(['ok' => true]);
    exit;
}

/** Yüklü görsellerin listesi */
function bd_get_uploaded_files(): array
{
    $dir = bd_media_dir();
    if (!is_dir($dir)) return [];
    $files = [];
    foreach (glob($dir . '*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) as $f) {
        $files[] = [
            'filename' => basename($f),
            'url'      => bd_media_url() . basename($f),
            'size'     => filesize($f),
            'mtime'    => filemtime($f),
        ];
    }
    usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
    return $files;
}

/** Kategori ağacını getir — tüm JTL 5.x varyantları için robust */
function bd_get_categories(): array
{
    $db = bd_db();

    // Adım 1: tkategorie sütunlarını öğren
    $colsRaw = [];
    try {
        $cols = $db->getObjects('SHOW COLUMNS FROM tkategorie');
        foreach ($cols as $c) {
            $colsRaw[] = strtolower((string)($c->Field ?? $c->field ?? ''));
        }
    } catch (\Throwable $e) {}

    $hasNameCol = in_array('cname', $colsRaw, true); // JTL 4.x compat

    // Adım 2: tkategoriename tablosu var mı?
    $hasNameTable = false;
    try {
        $db->getSingleObject('SELECT kKategorie FROM tkategoriename LIMIT 1');
        $hasNameTable = true;
    } catch (\Throwable $e) {}

    // Adım 3: Standart dil ID'si
    $langId = 1;
    try {
        $lang = $db->getSingleObject(
            'SELECT kSprache FROM tsprache WHERE cShopStandard = 1 LIMIT 1'
        );
        if ($lang) { $langId = (int)$lang->kSprache; }
    } catch (\Throwable $e) {}

    // Adım 4: Sorgular — en spesifikten en genel fallback'e
    $queries = [];

    if ($hasNameTable) {
        $queries[] = [
            'sql'    => 'SELECT k.kKategorie, k.kOberKategorie,
                                COALESCE(kn.cName, CONCAT("Kategorie #", k.kKategorie)) AS cName
                         FROM tkategorie k
                         LEFT JOIN tkategoriename kn
                             ON kn.kKategorie = k.kKategorie AND kn.kSprache = :lang
                         WHERE k.kKategorie > 0
                         ORDER BY k.kOberKategorie ASC, cName ASC',
            'params' => ['lang' => $langId],
        ];
    }

    if ($hasNameCol) {
        $queries[] = [
            'sql'    => 'SELECT kKategorie, kOberKategorie,
                                COALESCE(cName, CONCAT("Kategorie #", kKategorie)) AS cName
                         FROM tkategorie
                         WHERE kKategorie > 0
                         ORDER BY kOberKategorie ASC, cName ASC',
            'params' => [],
        ];
    }

    // Son çare: sadece ID'leri döndür
    $queries[] = [
        'sql'    => 'SELECT kKategorie, kOberKategorie,
                            CONCAT("Kategorie #", kKategorie) AS cName
                     FROM tkategorie
                     WHERE kKategorie > 0
                     ORDER BY kOberKategorie ASC, kKategorie ASC',
        'params' => [],
    ];

    foreach ($queries as $q) {
        try {
            $rows = $q['params']
                ? $db->getObjects($q['sql'], $q['params'])
                : $db->getObjects($q['sql']);
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // bir sonraki fallback'i dene
        }
    }

    return [];
}
} // end if (!function_exists('bd_h'))

// JTL aynı request içinde dashboard.php'yi birden fazla include edebilir.
if (defined('BD_DASHBOARD_EXEC')) {
    return;
}
define('BD_DASHBOARD_EXEC', true);

/* ═══════════════════════════════════════════════════════════════
   AJAX ROUTING (early exit)
   JTL Admin'de header() zaten gönderilmiş olabilir, bu yüzden
   ob_start/ob_end_clean ile buffer'ı temizliyoruz.
═══════════════════════════════════════════════════════════════ */
bd_ensure_table();

$ajaxAction = (string)($_REQUEST['bdAjax'] ?? '');
if ($ajaxAction === 'upload') {
    while (ob_get_level()) { ob_end_clean(); }
    bd_handle_upload();
}
if ($ajaxAction === 'deletefile') {
    while (ob_get_level()) { ob_end_clean(); }
    bd_handle_delete_file();
}
if ($ajaxAction === 'debugartikel') {
    // Debug: belirli bir ürün için kategori eşleşmesini test et
    // Kullanım: ?bdAjax=debugartikel&k=1234
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    $db  = bd_db();
    $kArt = (int)($_REQUEST['k'] ?? 0);
    $out = [
        'kArtikel'        => $kArt,
        'artikelRow'      => null,
        'artikelCatIds'   => [],
        'kategorieRows'   => [],
        'matchedRow'      => null,
        'allBdRows'       => [],
        'error'           => null,
    ];
    try {
        // 1. Direkt artikel kaydı
        $out['artikelRow'] = $db->getSingleObject(
            'SELECT kID, cType, kArtikel, nActive, cTabsJson FROM twomosi_bremer_desc
             WHERE cType = "artikel" AND kArtikel = :k LIMIT 1',
            ['k' => $kArt]
        );
        // 2. Ürünün kategorileri
        $catRows = $db->getObjects(
            'SELECT kKategorie FROM tkategorieartikel WHERE kArtikel = :k',
            ['k' => $kArt]
        ) ?: [];
        $out['artikelCatIds'] = array_map(fn($r) => (int)$r->kKategorie, $catRows);
        // 3. Yeni model: kArtikel IN (catIds)
        if (!empty($out['artikelCatIds'])) {
            $ph = implode(',', $out['artikelCatIds']);
            $out['kategorieRows'] = $db->getObjects(
                "SELECT kID, cType, kArtikel, nActive, cTabsJson FROM twomosi_bremer_desc
                 WHERE cType = 'kategorie' AND kArtikel IN ({$ph})"
            ) ?: [];
        }
        // 4. Tüm BD kayıtları
        $out['allBdRows'] = $db->getObjects(
            'SELECT kID, cType, kArtikel, nActive,
                    LEFT(cTabsJson,80) AS tabsPreview,
                    LEFT(cCategoriesJson,80) AS catsPreview
             FROM twomosi_bremer_desc ORDER BY kID DESC LIMIT 20'
        ) ?: [];
        // 5. Eşleşme özeti
        if ($out['artikelRow']) {
            $out['matchedRow'] = 'direkt_artikel';
        } elseif (!empty($out['kategorieRows'])) {
            $out['matchedRow'] = 'kategorie_match';
        } else {
            $out['matchedRow'] = 'NO_MATCH';
        }
    } catch (\Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($ajaxAction === 'debugcats')  {
    // Debug: kategori sorgusunu test et
    header('Content-Type: application/json; charset=UTF-8');
    $db = bd_db();
    $out = ['cols' => [], 'hasNameTable' => false, 'langId' => null, 'catCount' => 0, 'sample' => [], 'error' => null];
    try {
        $cols = $db->getObjects('SHOW COLUMNS FROM tkategorie');
        foreach ($cols as $c) { $out['cols'][] = $c->Field ?? $c->field ?? '?'; }
    } catch (\Throwable $e) { $out['error'] = 'SHOW COLUMNS: ' . $e->getMessage(); }
    try {
        $db->getSingleObject('SELECT kKategorie FROM tkategoriename LIMIT 1');
        $out['hasNameTable'] = true;
    } catch (\Throwable $e) {}
    try {
        $lang = $db->getSingleObject('SELECT kSprache FROM tsprache WHERE cShopStandard = 1 LIMIT 1');
        $out['langId'] = $lang ? $lang->kSprache : null;
    } catch (\Throwable $e) {}
    try {
        $cats = bd_get_categories();
        $out['catCount'] = count($cats);
        $out['sample']   = array_slice($cats, 0, 5);
    } catch (\Throwable $e) { $out['error'] = 'bd_get_categories: ' . $e->getMessage(); }
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ═══════════════════════════════════════════════════════════════
   NORMAL PAGE LOGIC
═══════════════════════════════════════════════════════════════ */
$pageTab = isset($_REQUEST['tab']) ? (string)$_REQUEST['tab'] : 'artikel';
if (!in_array($pageTab, ['artikel', 'kategorie', 'homepage'], true)) {
    $pageTab = 'artikel';
}
$message = '';
$msgType = 'success';

/* ── POST kontrolü ────────────────────────────────────────── */
$isPost = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (class_exists('JTL\Helpers\Form')) {
        if (Form::validateToken()) {
            $isPost = true;
        } else {
            $message = '⚠ Sicherheitstoken ungültig – bitte Seite neu laden (F5) und erneut versuchen.';
            $msgType = 'warning';
        }
    } else {
        $isPost = true;
    }
}

/* ── DELETE row ────────────────────────────────────────────── */
if ($isPost && isset($_POST['bdDelete'])) {
    $delType = in_array($_POST['bdType'] ?? '', ['artikel', 'kategorie', 'homepage']) ? (string)$_POST['bdType'] : 'artikel';
    $delK    = max(0, (int)($_POST['bdKArtikel'] ?? 0));
    if ($delK > 0) {
        try {
            bd_delete($delType, $delK);
            $message = 'Gelöscht (type=' . $delType . ', kArtikel=' . $delK . ')';
            $msgType = 'warning';
        } catch (\Throwable $e) {
            $message = 'DB-Fehler: ' . $e->getMessage();
            $msgType = 'warning';
        }
    } else {
        $message = 'Fehler: kArtikel = 0 – nichts gelöscht.';
        $msgType = 'warning';
    }
    $pageTab = $delType === 'homepage' ? 'homepage' : ($delType === 'kategorie' ? 'kategorie' : 'artikel');
}

/* ── SAVE ──────────────────────────────────────────────────── */
if ($isPost && isset($_POST['bdSave'])) {
    $saveType = in_array($_POST['bdType'] ?? '', ['artikel', 'kategorie', 'homepage'])
        ? (string)$_POST['bdType'] : 'artikel';
    $kArtikel = max(0, (int)($_POST['bdKArtikel'] ?? 0));

    $tabs = json_decode((string)($_POST['cTabsJson'] ?? ''), true);
    if (!is_array($tabs)) { $tabs = []; }

    $cats = json_decode((string)($_POST['cCategoriesJson'] ?? ''), true);
    if (!is_array($cats)) { $cats = []; }

    $allowedPositions = ['content_top','content_bottom','after_header','before_footer','after_hero','after_cms'];
    $injectPos = (string)($_POST['cInjectPosition'] ?? 'content_top');
    if (!in_array($injectPos, $allowedPositions, true)) {
        if (str_starts_with($injectPos, 'custom:')) {
            $customSel = bd_normalize_custom_selector(substr($injectPos, 7));
            if ($customSel === '') {
                $injectPos = 'content_top';
                $message = 'Hinweis: CSS-Selektor leer — Fallback „Anfang des Inhaltsbereichs“ gespeichert.';
                $msgType = 'warning';
            } else {
                $injectPos = 'custom:' . $customSel;
            }
        } else {
            $injectPos = 'content_top';
        }
    }

    $obj = new \stdClass();
    $obj->cType             = $saveType;
    $obj->kArtikel          = $kArtikel;
    $obj->nActive           = isset($_POST['nActive']) ? 1 : 0;
    $obj->cVideoUrl         = '';
    $obj->cBilderTabLabel   = 'Produktbilder';
    $obj->cImagesJson       = null;
    $obj->cTabsJson         = json_encode($tabs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $obj->cCategoriesJson   = $saveType === 'kategorie'
        ? json_encode(array_map('intval', $cats), JSON_UNESCAPED_UNICODE)
        : null;
    $obj->cInjectPosition   = $saveType === 'homepage' ? $injectPos : 'content_top';

    bd_save_row($obj);
    if (!isset($message) || $message === '') {
        $message = 'Gespeichert.';
        if ($saveType === 'homepage' && str_starts_with($injectPos, 'custom:')) {
            $message .= ' Custom-Selektor „' . bd_normalize_custom_selector(substr($injectPos, 7))
                . '“ — bitte auf der Startseite prüfen (Seitenquelltext bei Fehler).';
        }
    }
    $pageTab = $saveType;
}

/* ── Data load ─────────────────────────────────────────────── */
$editK   = (int)($_REQUEST['k'] ?? 0);
$editRow = null;

if ($pageTab === 'homepage') {
    $editRow = bd_load('homepage', 0);
} elseif ($pageTab === 'kategorie' && $editK > 0) {
    $editRow = bd_load('kategorie', $editK);
} elseif ($pageTab === 'artikel' && $editK > 0) {
    $editRow = bd_load('artikel', $editK);
}

$artikelRows   = [];
$kategorieRows = [];
try {
    $artikelRows   = bd_db()->getObjects('SELECT * FROM twomosi_bremer_desc WHERE cType = "artikel"   ORDER BY dUpdated DESC') ?: [];
    $kategorieRows = bd_db()->getObjects('SELECT * FROM twomosi_bremer_desc WHERE cType = "kategorie" ORDER BY dUpdated DESC') ?: [];
} catch (\Throwable $e) {}

$homepageRow   = bd_load('homepage', 0);
$allCats       = bd_get_categories();
$uploadedFiles = bd_get_uploaded_files();

/* ── Helpers ───────────────────────────────────────────────── */
if (!function_exists('bd_get_tabs')) {
function bd_get_tabs(?\stdClass $row): array
{
    if ($row === null) return [];
    $j = trim((string)($row->cTabsJson ?? ''));
    if ($j === '') return [];
    $a = json_decode($j, true);
    return is_array($a) ? $a : [];
}
}

if (!function_exists('bd_get_cats')) {
function bd_get_cats(?\stdClass $row): array
{
    if ($row === null) return [];
    $j = trim((string)($row->cCategoriesJson ?? ''));
    if ($j === '') return [];
    $a = json_decode($j, true);
    return is_array($a) ? array_map('intval', $a) : [];
}
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
.bd-wrap *, .bd-modal * { box-sizing: border-box; }
.bd-wrap { max-width: 980px; font-family: system-ui, -apple-system, sans-serif; color: #1e1b4b; }

/* Page tabs */
.bd-ptabs { display:flex; gap:.3rem; margin-bottom:0; flex-wrap:wrap; }
.bd-ptab  { padding:.5rem 1.1rem; border:1px solid #ccc; border-radius:6px 6px 0 0;
            background:#f5f5f5; cursor:pointer; font-size:.88rem; text-decoration:none; color:#444; border-bottom:none; }
.bd-ptab.active { background:#fff; font-weight:700; color:#7c3aed; }
.bd-tab-body { border:1px solid #ccc; border-radius:0 6px 6px 6px; background:#fff; padding:1.5rem; margin-bottom:1rem; }

/* Cards */
.bd-card { background:#fafafa; border:1px solid #e5e7eb; border-radius:8px; padding:1.25rem; margin-bottom:1rem; }
.bd-card-title { font-weight:700; font-size:.95rem; margin:0 0 1rem; color:#374151; }

/* Form */
.bd-fg { margin-bottom:.9rem; }
.bd-fg label { display:block; font-weight:600; font-size:.82rem; margin-bottom:.3rem; color:#374151; }
.bd-fg input[type=text], .bd-fg input[type=number], .bd-fg input[type=url],
.bd-fg select, .bd-fg textarea
  { width:100%; padding:.45rem .7rem; border:1px solid #d1d5db; border-radius:6px; font-size:.88rem; background:#fff; }
.bd-fg textarea { resize:vertical; font-family:monospace; font-size:.8rem; }
.bd-fg small { color:#6b7280; font-size:.77rem; }

/* Buttons */
.bd-btn { padding:.45rem 1rem; border:none; border-radius:6px; cursor:pointer; font-size:.85rem; font-weight:600; display:inline-flex; align-items:center; gap:.35rem; transition:background .15s; }
.bd-btn-primary { background:#7c3aed; color:#fff; }
.bd-btn-primary:hover { background:#6d28d9; }
.bd-btn-danger  { background:#fee2e2; color:#b91c1c; }
.bd-btn-danger:hover  { background:#fecaca; }
.bd-btn-ghost   { background:#f3f4f6; color:#374151; }
.bd-btn-ghost:hover { background:#e5e7eb; }
.bd-btn-sm { padding:.28rem .65rem; font-size:.78rem; }
.bd-btn-icon { padding:.3rem .45rem; font-size:1rem; line-height:1; }

/* Alert */
.bd-alert { padding:.7rem 1rem; border-radius:6px; margin-bottom:1rem; font-size:.88rem; }
.bd-alert-success { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7; }
.bd-alert-warning { background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }

/* Table */
.bd-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.bd-table th, .bd-table td { padding:.5rem .7rem; border-bottom:1px solid #f3f4f6; text-align:left; vertical-align:middle; }
.bd-table th { background:#f9fafb; font-weight:700; color:#374151; }
.bd-badge { display:inline-block; padding:.12rem .45rem; border-radius:999px; font-size:.72rem; font-weight:700; }
.bd-badge-on  { background:#d1fae5; color:#065f46; }
.bd-badge-off { background:#fee2e2; color:#991b1b; }

/* Tab builder */
.bd-tabs-builder { display:flex; flex-direction:column; gap:.6rem; }
.bd-tab-row { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:.9rem 1rem; }
.bd-tab-row-header { display:flex; align-items:center; gap:.5rem; margin-bottom:.7rem; }
.bd-tab-row-header .bd-drag-handle { color:#9ca3af; font-size:1.1rem; cursor:grab; user-select:none; }
.bd-tab-row-header input.bd-tab-label { flex:1; font-weight:600; border:1px solid #d1d5db; border-radius:5px; padding:.3rem .6rem; font-size:.88rem; }
.bd-tab-type-sel { display:flex; gap:.4rem; }
.bd-tab-type-btn { padding:.25rem .75rem; border:1px solid #d1d5db; border-radius:5px; font-size:.8rem; cursor:pointer; background:#f9fafb; }
.bd-tab-type-btn.active { background:#7c3aed; color:#fff; border-color:#7c3aed; }

/* Image list */
.bd-img-list { display:flex; flex-direction:column; gap:.4rem; }
.bd-img-row { display:grid; grid-template-columns:auto 56px 1fr auto; align-items:center; gap:.4rem;
              background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:.35rem .5rem; }
.bd-img-row .bd-drag-handle { color:#9ca3af; cursor:grab; font-size:1rem; }
.bd-img-thumb { width:56px; height:44px; object-fit:cover; border-radius:4px; border:1px solid #e5e7eb; background:#e5e7eb; display:block; }
.bd-img-input-wrap { display:flex; flex-direction:column; gap:.2rem; }
.bd-img-input-wrap input { border:1px solid #d1d5db; border-radius:5px; padding:.3rem .5rem; font-size:.8rem; background:#fff; width:100%; }
.bd-img-actions { display:flex; flex-direction:column; gap:.25rem; align-items:flex-end; }

/* Upload zone */
.bd-upload-zone { border:2px dashed #c4b5fd; border-radius:8px; padding:1.5rem; text-align:center;
                  cursor:pointer; transition:border-color .15s, background .15s; background:#faf5ff; }
.bd-upload-zone:hover, .bd-upload-zone.dragover { border-color:#7c3aed; background:#ede9fe; }
.bd-upload-zone p { margin:.3rem 0; font-size:.85rem; color:#6b7280; }
.bd-upload-zone .bd-upload-icon { font-size:2rem; margin-bottom:.3rem; }
.bd-upload-zone input[type=file] { display:none; }

/* Media library */
.bd-media-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px,1fr)); gap:.6rem; }
.bd-media-item { position:relative; border:2px solid #e5e7eb; border-radius:6px; overflow:hidden; cursor:pointer; transition:border-color .15s; background:#f9fafb; }
.bd-media-item:hover { border-color:#7c3aed; }
.bd-media-item img { width:100%; height:90px; object-fit:cover; display:block; }
.bd-media-item .bd-media-name { font-size:.68rem; color:#6b7280; padding:.2rem .3rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.bd-media-item .bd-media-del { position:absolute; top:.2rem; right:.2rem; width:1.3rem; height:1.3rem;
  background:rgba(220,38,38,.85); color:#fff; border:none; border-radius:50%; font-size:.7rem;
  cursor:pointer; display:none; align-items:center; justify-content:center; line-height:1; }
.bd-media-item:hover .bd-media-del { display:flex; }

/* Upload progress */
.bd-progress { height:4px; background:#e5e7eb; border-radius:2px; overflow:hidden; margin-top:.4rem; display:none; }
.bd-progress-bar { height:100%; background:#7c3aed; transition:width .2s; width:0; }

/* Modal */
.bd-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; display:flex; align-items:center; justify-content:center; }
.bd-modal { background:#fff; border-radius:10px; padding:1.5rem; max-width:780px; width:calc(100% - 2rem); max-height:85vh; display:flex; flex-direction:column; gap:1rem; }
.bd-modal-header { display:flex; justify-content:space-between; align-items:center; }
.bd-modal-header h4 { margin:0; font-size:1rem; }
.bd-modal-body { overflow-y:auto; flex:1; }

/* Category tree */
.bd-cat-tree { max-height:300px; overflow-y:auto; border:1px solid #e5e7eb; border-radius:6px; background:#fff; padding:.5rem; }
.bd-cat-group { margin-bottom:.4rem; }
.bd-cat-parent { font-weight:700; font-size:.83rem; color:#374151; padding:.2rem .3rem; display:flex; align-items:center; gap:.4rem; }
.bd-cat-children { padding-left:1.2rem; }
.bd-cat-item { display:flex; align-items:center; gap:.4rem; padding:.18rem .3rem; font-size:.82rem; }
.bd-cat-item input[type=checkbox] { accent-color:#7c3aed; }
.bd-cat-search { width:100%; padding:.35rem .6rem; border:1px solid #d1d5db; border-radius:6px; font-size:.82rem; margin-bottom:.5rem; }

/* Sortable */
.sortable-ghost  { opacity:.4; background:#e0e7ff !important; }
.sortable-chosen { box-shadow:0 4px 16px rgba(124,58,237,.2); }

/* Category tree (left panel) */
.bd-cat-tree-item:hover { background:#f5f3ff; }
.bd-cat-tree-item.active { background:#ede9fe; }
.bd-cat-tree-group + .bd-cat-tree-group { margin-top:.1rem; }
</style>
</head>
<body>
<div class="bd-wrap">
  <h3 style="margin:0 0 1.2rem;font-size:1.2rem">BremerDesc – Produkt Showcase</h3>

  <?php if ($message !== '') { ?>
  <div class="bd-alert bd-alert-<?= $msgType ?>"><?= bd_h($message) ?></div>
  <?php } ?>

  <!-- Page tabs -->
  <div class="bd-ptabs">
    <a href="<?= bd_h(bd_url(['tab' => 'artikel'])) ?>"   class="bd-ptab <?= $pageTab === 'artikel'   ? 'active' : '' ?>">Artikel</a>
    <a href="<?= bd_h(bd_url(['tab' => 'kategorie'])) ?>" class="bd-ptab <?= $pageTab === 'kategorie' ? 'active' : '' ?>">Kategorien</a>
    <a href="<?= bd_h(bd_url(['tab' => 'homepage'])) ?>"  class="bd-ptab <?= $pageTab === 'homepage'  ? 'active' : '' ?>">Startseite</a>
  </div>
  <div class="bd-tab-body">

<?php

/* ═══════════════════════════════════════════════════════════════
   MACRO: Image row
═══════════════════════════════════════════════════════════════ */
if (!function_exists('bd_render_img_row')) {
function bd_render_img_row(string $url, string $uid): void
{
    $filename = $url !== '' ? basename($url) : '';
    $isBdFile = $filename !== '' && str_starts_with($filename, 'bd_');
    ?>
  <div class="bd-img-row">
    <span class="bd-drag-handle" title="Ziehen">⠿</span>
    <img class="bd-img-thumb" src="<?= bd_h($url ?: 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=') ?>"
         alt="" loading="lazy" onerror="this.src='data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='">
    <div class="bd-img-input-wrap">
      <input type="text" placeholder="URL…" value="<?= bd_h($url) ?>"
             data-img-filename="<?= bd_h($filename) ?>"
             oninput="bdUpdateThumb(this)">
    </div>
    <div class="bd-img-actions">
      <button type="button" class="bd-btn bd-btn-ghost bd-btn-sm"
              onclick="bdOpenMediaPicker(this,'<?= bd_h($uid) ?>')" title="Aus Mediathek wählen">&#128247;</button>
      <?php if ($isBdFile) { ?>
      <button type="button" class="bd-btn bd-btn-danger bd-btn-sm"
              onclick="bdDeleteUploadedAndRow(this,'<?= bd_h($filename) ?>')" title="Datei löschen">🗑</button>
      <?php } else { ?>
      <button type="button" class="bd-btn bd-btn-danger bd-btn-sm"
              onclick="this.closest('.bd-img-row').remove()" title="Zeile entfernen">✕</button>
      <?php } ?>
    </div>
  </div>
    <?php
}
}

/* ═══════════════════════════════════════════════════════════════
   MACRO: Tab row
═══════════════════════════════════════════════════════════════ */
if (!function_exists('bd_render_tab_row')) {
function bd_render_tab_row(array $tab, int $idx, string $uid): void
{
    $type     = $tab['type'] ?? 'images';
    $label    = $tab['label'] ?? '';
    $videoUrl = $tab['videoUrl'] ?? $tab['videoId'] ?? '';
    $images   = $tab['images'] ?? [];
    ?>
  <div class="bd-tab-row" data-tab-idx="<?= $idx ?>">
    <div class="bd-tab-row-header">
      <span class="bd-drag-handle" title="Ziehen">⠿</span>
      <input class="bd-tab-label" type="text" placeholder="Tab-Bezeichnung" value="<?= bd_h($label) ?>">
      <div class="bd-tab-type-sel">
        <button type="button" class="bd-tab-type-btn <?= $type === 'video'  ? 'active' : '' ?>"
                data-type="video"  onclick="bdSetTabType(this,'<?= bd_h($uid) ?>')">▶ Video</button>
        <button type="button" class="bd-tab-type-btn <?= $type === 'images' ? 'active' : '' ?>"
                data-type="images" onclick="bdSetTabType(this,'<?= bd_h($uid) ?>')">🖼 Bilder</button>
      </div>
      <button type="button" class="bd-btn bd-btn-danger bd-btn-icon bd-btn-sm"
              onclick="bdRemoveTab(this,'<?= bd_h($uid) ?>')" title="Tab entfernen">✕</button>
    </div>
    <div class="bd-tab-row-body">
      <!-- VIDEO area -->
      <div class="bd-tab-video-area" style="<?= $type !== 'video' ? 'display:none' : '' ?>">
        <div class="bd-fg">
          <label>YouTube-URL oder Video-ID</label>
          <input type="text" class="bd-tab-video-url" placeholder="https://youtu.be/…" value="<?= bd_h($videoUrl) ?>">
        </div>
      </div>
      <!-- IMAGES area -->
      <div class="bd-tab-images-area" style="<?= $type !== 'images' ? 'display:none' : '' ?>">
        <!-- Upload zone -->
        <div class="bd-upload-zone" id="uploadZone_<?= bd_h($uid) ?>_<?= $idx ?>"
             onclick="document.getElementById('fileInput_<?= bd_h($uid) ?>_<?= $idx ?>').click()"
             ondragover="event.preventDefault();this.classList.add('dragover')"
             ondragleave="this.classList.remove('dragover')"
             ondrop="bdHandleDrop(event,'<?= bd_h($uid) ?>','<?= $idx ?>')">
          <div class="bd-upload-icon">☁️</div>
          <p><strong>Bilder hochladen</strong></p>
          <p>Klicken oder Dateien hierher ziehen</p>
          <p style="font-size:.75rem">JPG, PNG, WebP, GIF — max. 8 MB</p>
          <input type="file" id="fileInput_<?= bd_h($uid) ?>_<?= $idx ?>"
                 accept="image/jpeg,image/png,image/webp,image/gif"
                 multiple
                 onchange="bdHandleFileInput(this,'<?= bd_h($uid) ?>','<?= $idx ?>')">
        </div>
        <div class="bd-progress" id="progress_<?= bd_h($uid) ?>_<?= $idx ?>">
          <div class="bd-progress-bar" id="progressBar_<?= bd_h($uid) ?>_<?= $idx ?>"></div>
        </div>
        <!-- Image list -->
        <div class="bd-img-list" data-img-sortable style="margin-top:.6rem">
          <?php foreach ($images as $imgUrl) {
              bd_render_img_row((string)$imgUrl, $uid);
          } ?>
        </div>
        <button type="button" class="bd-btn bd-btn-ghost bd-btn-sm" style="margin-top:.4rem"
                onclick="bdAddImageRow(this,'<?= bd_h($uid) ?>')">+ URL manuell hinzufügen</button>
      </div>
    </div>
  </div>
    <?php
}
}

/* ═══════════════════════════════════════════════════════════════
   MACRO: Full form
═══════════════════════════════════════════════════════════════ */
if (!function_exists('bd_render_form')) {
function bd_render_form(
    string     $formType,
    int        $kArtikel,
    ?\stdClass $row,
    array      $allCats,
    bool       $showCats,
    bool       $showKArtikel
): void {
    $tabs         = bd_get_tabs($row);
    $selectedCats = bd_get_cats($row);
    $isActive     = $row === null ? 1 : (int)($row->nActive ?? 1);
    $tabsJson     = json_encode($tabs,         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $catsJson     = json_encode($selectedCats, JSON_UNESCAPED_UNICODE);
    $uid          = $formType . '_' . $kArtikel;
    ?>
  <?php
  $formActionParams = ['tab' => $formType];
  if ($formType === 'kategorie' && $kArtikel > 0) {
      $formActionParams['edit_cat'] = $kArtikel;
  }
  ?>
  <form method="post" action="<?= bd_h(bd_url($formActionParams)) ?>"
        id="bdForm_<?= bd_h($uid) ?>" enctype="multipart/form-data">
    <?php if (class_exists('JTL\Helpers\Form')) { echo Form::getTokenInput(); } ?>
    <input type="hidden" name="bdType"          value="<?= bd_h($formType) ?>">
    <input type="hidden" name="bdKArtikel"      value="<?= $kArtikel ?>">
    <input type="hidden" name="tab"             value="<?= bd_h($formType) ?>">
    <input type="hidden" name="cTabsJson"        id="hidTabsJson_<?= bd_h($uid) ?>" value="<?= bd_h($tabsJson) ?>">
    <input type="hidden" name="cCategoriesJson" id="hidCatsJson_<?= bd_h($uid) ?>"  value="<?= bd_h($catsJson) ?>">
    <input type="hidden" name="cInjectPosition" id="hidInjectPos"
           value="<?= bd_h((string)($row->cInjectPosition ?? 'content_top')) ?>">
    <?php if ($formType === 'kategorie' && $kArtikel > 0) { ?>
    <input type="hidden" name="edit_cat" value="<?= (int)$kArtikel ?>">
    <?php } ?>

    <?php if ($showKArtikel) { ?>
    <div class="bd-fg">
      <label>Artikel-ID (kArtikel)</label>
      <input type="number" name="bdKArtikel" min="1" required
             value="<?= $kArtikel > 0 ? $kArtikel : '' ?>"
             onchange="document.getElementById('hidTabsJson_<?= bd_h($uid) ?>').id='hidTabsJson_<?= bd_h($formType) ?>_'+this.value;
                       document.getElementById('hidCatsJson_<?= bd_h($uid) ?>').id='hidCatsJson_<?= bd_h($formType) ?>_'+this.value;">
    </div>
    <?php } ?>

    <div class="bd-fg">
      <label style="display:inline-flex;align-items:center;gap:.4rem;cursor:pointer">
        <input type="checkbox" name="nActive" value="1" <?= $isActive ? 'checked' : '' ?>> Aktiv
      </label>
    </div>

    <?php if ($showCats && !empty($allCats)) { ?>
    <div class="bd-fg">
      <label>Kategorien &amp; Unterkategorien</label>
      <input type="text" class="bd-cat-search" placeholder="Kategorie suchen…"
             oninput="bdFilterCats(this.value,'<?= bd_h($uid) ?>')">
      <div class="bd-cat-tree" id="catTree_<?= bd_h($uid) ?>">
        <?php
        $parents  = array_filter($allCats, fn($c) => (int)$c->kOberKategorie === 0);
        $byParent = [];
        foreach ($allCats as $c) { $byParent[(int)$c->kOberKategorie][] = $c; }
        foreach ($parents as $parent) {
            $pid     = (int)$parent->kKategorie;
            $checked = in_array($pid, $selectedCats) ? 'checked' : '';
            ?>
        <div class="bd-cat-group" data-cat-name="<?= bd_h(strtolower($parent->cName)) ?>">
          <div class="bd-cat-parent">
            <input type="checkbox" class="bd-cat-cb" data-uid="<?= bd_h($uid) ?>"
                   value="<?= $pid ?>" <?= $checked ?>>
            <span><?= bd_h($parent->cName) ?></span>
            <?php if (!empty($byParent[$pid])) { ?>
            <button type="button" class="bd-btn bd-btn-ghost bd-btn-sm"
                    style="margin-left:auto;font-size:.72rem;padding:.15rem .5rem"
                    onclick="bdToggleChildren(this)">▼ Unterkategorien</button>
            <?php } ?>
          </div>
          <?php if (!empty($byParent[$pid])) { ?>
          <div class="bd-cat-children" style="display:none">
            <?php foreach ($byParent[$pid] as $child) {
                $cid      = (int)$child->kKategorie;
                $cchecked = in_array($cid, $selectedCats) ? 'checked' : '';
                ?>
            <div class="bd-cat-item" data-cat-name="<?= bd_h(strtolower($child->cName)) ?>">
              <input type="checkbox" class="bd-cat-cb" data-uid="<?= bd_h($uid) ?>"
                     value="<?= $cid ?>" <?= $cchecked ?>>
              <span><?= bd_h($child->cName) ?></span>
            </div>
            <?php } ?>
          </div>
          <?php } ?>
        </div>
        <?php } ?>
      </div>
    </div>
    <?php } ?>

    <!-- Tab builder -->
    <div class="bd-fg">
      <label>Tabs <span style="font-weight:400;color:#9ca3af;font-size:.78rem">(ziehen = sortieren)</span></label>
      <div class="bd-tabs-builder" id="tabBuilder_<?= bd_h($uid) ?>" data-uid="<?= bd_h($uid) ?>">
        <?php foreach ($tabs as $ti => $tab) {
            bd_render_tab_row($tab, $ti, $uid);
        } ?>
      </div>
      <div style="margin-top:.5rem;display:flex;gap:.4rem">
        <button type="button" class="bd-btn bd-btn-ghost bd-btn-sm"
                onclick="bdAddTab('video','<?= bd_h($uid) ?>')">+ Video-Tab</button>
        <button type="button" class="bd-btn bd-btn-ghost bd-btn-sm"
                onclick="bdAddTab('images','<?= bd_h($uid) ?>')">+ Bilder-Tab</button>
      </div>
    </div>

    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:1rem">
      <button type="submit" name="bdSave" value="1" class="bd-btn bd-btn-primary"
              onclick="bdCollectAll('<?= bd_h($uid) ?>')">Speichern</button>
      <?php if ($kArtikel > 0 && $formType !== 'homepage') { ?>
      <a href="<?= bd_h(bd_url(['tab' => $formType])) ?>" style="color:#6b7280;font-size:.82rem">Abbrechen</a>
      <?php } ?>
    </div>
  </form>
  <?php if ($row !== null) { ?>
  <form method="post" action="<?= bd_h(bd_url(['tab' => $formType])) ?>"
        style="display:inline;margin-top:.6rem" onsubmit="return confirm('Eintrag wirklich löschen?')">
    <?php if (class_exists('JTL\Helpers\Form')) { echo Form::getTokenInput(); } ?>
    <input type="hidden" name="bdDelete"   value="1">
    <input type="hidden" name="bdType"     value="<?= bd_h($formType) ?>">
    <input type="hidden" name="bdKArtikel" value="<?= $kArtikel ?>">
    <input type="hidden" name="tab"        value="<?= bd_h($formType) ?>">
    <button type="submit" class="bd-btn bd-btn-danger bd-btn-sm">Löschen</button>
  </form>
  <?php } ?>
    <?php
}
}

/* ═══════════════════════════════════════════════════════════════
   PAGE: ARTIKEL
═══════════════════════════════════════════════════════════════ */
if ($pageTab === 'artikel') { ?>
  <div class="bd-card">
    <p class="bd-card-title"><?= $editRow !== null ? 'Artikel bearbeiten' : 'Neuen Artikel hinzufügen' ?></p>
    <?php bd_render_form('artikel', $editK > 0 ? $editK : 0, $editRow, $allCats, false, true); ?>
  </div>
  <?php if (count($artikelRows) > 0) { ?>
  <div class="bd-card">
    <p class="bd-card-title">Konfigurierte Artikel</p>
    <table class="bd-table">
      <thead><tr><th>Artikel-ID</th><th>Tabs</th><th>Status</th><th>Geändert</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($artikelRows as $r) {
          $tc  = count(bd_get_tabs($r));
          $rKA = (int)$r->kArtikel; ?>
      <tr>
        <td><strong><?= $rKA ?></strong></td>
        <td><?= $tc ?> Tab<?= $tc !== 1 ? 's' : '' ?></td>
        <td><span class="bd-badge <?= (int)$r->nActive === 1 ? 'bd-badge-on' : 'bd-badge-off' ?>">
          <?= (int)$r->nActive === 1 ? 'Aktiv' : 'Inaktiv' ?></span></td>
        <td style="white-space:nowrap;font-size:.78rem"><?= bd_h((string)$r->dUpdated) ?></td>
        <td style="display:flex;gap:.4rem">
          <a href="<?= bd_h(bd_url(['tab' => 'artikel', 'k' => $rKA])) ?>"
             class="bd-btn bd-btn-ghost bd-btn-sm">Bearbeiten</a>
          <form method="post" action="<?= bd_h(bd_url(['tab' => 'artikel'])) ?>"
                style="margin:0" onsubmit="return confirm('Artikel <?= $rKA ?> wirklich löschen?')">
            <?php if (class_exists('JTL\Helpers\Form')) { echo Form::getTokenInput(); } ?>
            <input type="hidden" name="bdDelete"   value="1">
            <input type="hidden" name="bdType"     value="artikel">
            <input type="hidden" name="bdKArtikel" value="<?= $rKA ?>">
            <input type="hidden" name="tab"        value="artikel">
            <button type="submit" class="bd-btn bd-btn-danger bd-btn-sm">✕</button>
          </form>
        </td>
      </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
  <?php }

/* ═══════════════════════════════════════════════════════════════
   PAGE: KATEGORIE  — Kategorie-Baum + inline Konfiguration
═══════════════════════════════════════════════════════════════ */
} elseif ($pageTab === 'kategorie') {

    // Kategori kayıtları: kArtikel = kKategorie (1:1 ilişki, cType='kategorie')
    // kategorieRows zaten bu şekilde kaydediliyor.
    $katRowByCatId = []; // kKategorie → row
    foreach ($kategorieRows as $kr) {
        // Birincil lookup: kArtikel = kKategorie ID'si
        $kArt = (int)$kr->kArtikel;
        if ($kArt > 0) {
            $katRowByCatId[$kArt] = $kr;
        }
        // Eski format fallback: cCategoriesJson içindeki ID'ler
        $kCats = bd_get_cats($kr);
        foreach ($kCats as $cId) {
            if (!isset($katRowByCatId[$cId])) {
                $katRowByCatId[$cId] = $kr;
            }
        }
    }

    // Kategori ağacı yapısı: parent → children
    $catParents  = array_filter($allCats, fn($c) => (int)$c->kOberKategorie === 0);
    $catByParent = [];
    foreach ($allCats as $c) { $catByParent[(int)$c->kOberKategorie][] = $c; }

    // POST sonrası edit_cat'i $_POST'tan da oku (form'da hidden olarak gönderiyoruz)
    $editCatId = (int)($_REQUEST['edit_cat'] ?? $_POST['edit_cat'] ?? 0);

    // POST ile kayıt yapıldıysa, kaydedilen kArtikel'i edit_cat olarak kullan
    if ($isPost && isset($_POST['bdSave']) && (string)($_POST['bdType'] ?? '') === 'kategorie') {
        $editCatId = max($editCatId, (int)($_POST['bdKArtikel'] ?? 0));
    }
    if ($isPost && isset($_POST['bdDelete']) && (string)($_POST['bdType'] ?? '') === 'kategorie') {
        $editCatId = 0; // sildikten sonra formu kapat
    }

    // Seçili kategorinin DB row'unu yükle (her zaman DB'den taze oku)
    $editCatRow      = $editCatId > 0 ? bd_load('kategorie', $editCatId) : null;
    $editCatKArtikel = $editCatId > 0 ? $editCatId : 0;
?>
  <div style="display:grid;grid-template-columns:300px 1fr;gap:1rem;align-items:start">

    <!-- ── Sol: Kategori Ağacı ─────────────────────────────── -->
    <div class="bd-card" style="padding:1rem">
      <p class="bd-card-title" style="margin-bottom:.7rem">Kategorien</p>
      <input type="text" class="bd-cat-search" id="catTreeSearch"
             placeholder="Kategorie suchen…"
             oninput="bdFilterCatTree(this.value)"
             style="margin-bottom:.6rem">
      <div id="bdCatTree" style="max-height:70vh;overflow-y:auto">
        <?php if (empty($allCats)) { ?>
        <div style="padding:.75rem;background:#fef9c3;border:1px solid #fde047;border-radius:6px;font-size:.82rem;color:#854d0e">
          <strong>Keine Kategorien gefunden.</strong><br>
          <a href="<?= bd_h(bd_url(['tab' => 'kategorie', 'bdAjax' => 'debugcats'])) ?>" target="_blank"
             style="color:#7c3aed;font-size:.78rem">→ Debug-Info anzeigen</a>
        </div>
        <?php } ?>
        <?php foreach ($catParents as $parent) {
            $pid       = (int)$parent->kKategorie;
            $hasRow    = isset($katRowByCatId[$pid]);
            $children  = $catByParent[$pid] ?? [];
            $hasChildren = !empty($children);
            $isActive  = $editCatId === $pid;
        ?>
        <div class="bd-cat-tree-group" data-cat-name="<?= bd_h(strtolower($parent->cName)) ?>">
          <div class="bd-cat-tree-item <?= $isActive ? 'active' : '' ?>"
               data-cat-id="<?= $pid ?>" style="display:flex;align-items:center;gap:.35rem;padding:.4rem .5rem;border-radius:6px;cursor:pointer;<?= $isActive ? 'background:#ede9fe;' : '' ?>"
               onclick="window.location.href='<?= bd_h(bd_url(['tab' => 'kategorie', 'edit_cat' => $pid])) ?>'">
            <?php if ($hasChildren) { ?>
            <span class="bd-tree-toggle" onclick="event.stopPropagation();bdToggleCatGroup(this)"
                  style="cursor:pointer;font-size:.8rem;color:#9ca3af;width:1rem;text-align:center">▶</span>
            <?php } else { ?>
            <span style="width:1rem;display:inline-block"></span>
            <?php } ?>
            <span style="flex:1;font-size:.85rem;font-weight:<?= $hasRow ? '700' : '400' ?>;
                         color:<?= $hasRow ? '#7c3aed' : '#374151' ?>">
              <?= bd_h($parent->cName) ?>
            </span>
            <?php if ($hasRow) { ?>
            <span style="width:7px;height:7px;border-radius:50%;background:#7c3aed;flex-shrink:0" title="Konfiguriert"></span>
            <?php } ?>
          </div>
          <?php if ($hasChildren) { ?>
          <div class="bd-cat-children-tree" style="display:none;padding-left:1rem">
            <?php foreach ($children as $child) {
                $cid      = (int)$child->kKategorie;
                $cHasRow  = isset($katRowByCatId[$cid]);
                $cActive  = $editCatId === $cid;
            ?>
            <div class="bd-cat-tree-item <?= $cActive ? 'active' : '' ?>"
                 data-cat-id="<?= $cid ?>" data-cat-name="<?= bd_h(strtolower($child->cName)) ?>"
                 style="display:flex;align-items:center;gap:.35rem;padding:.35rem .5rem;border-radius:6px;cursor:pointer;<?= $cActive ? 'background:#ede9fe;' : '' ?>"
                 onclick="window.location.href='<?= bd_h(bd_url(['tab' => 'kategorie', 'edit_cat' => $cid])) ?>'">
              <span style="width:1rem;display:inline-block"></span>
              <span style="flex:1;font-size:.83rem;font-weight:<?= $cHasRow ? '700' : '400' ?>;
                           color:<?= $cHasRow ? '#7c3aed' : '#374151' ?>">
                <?= bd_h($child->cName) ?>
              </span>
              <?php if ($cHasRow) { ?>
              <span style="width:7px;height:7px;border-radius:50%;background:#7c3aed;flex-shrink:0" title="Konfiguriert"></span>
              <?php } ?>
            </div>
            <?php } ?>
          </div>
          <?php } ?>
        </div>
        <?php } ?>
      </div>
    </div>

    <!-- ── Sağ: Konfigürasyon formu ─────────────────────────── -->
    <div>
      <?php if ($editCatId > 0) {
          // Seçili kategorinin adını bul
          $editCatName = '';
          foreach ($allCats as $ac) {
              if ((int)$ac->kKategorie === $editCatId) { $editCatName = $ac->cName; break; }
          }
      ?>
      <div class="bd-card">
        <p class="bd-card-title">
          Showcase für: <strong><?= bd_h($editCatName) ?></strong>
          <span style="font-size:.78rem;font-weight:400;color:#6b7280">
            — wird in allen Artikeln dieser Kategorie angezeigt
          </span>
        </p>
        <?php
        // kArtikel = kKategorie ID (eindeutig als Schlüssel für cType=kategorie)
        bd_render_form('kategorie', $editCatId, $editCatRow, $allCats, false, false);
        ?>
      </div>
      <?php } else { ?>
      <div class="bd-card" style="color:#6b7280;font-size:.88rem;padding:2rem;text-align:center">
        <div style="font-size:2rem;margin-bottom:.5rem">👈</div>
        <p>Wähle links eine Kategorie aus, um ihr Showcase zu konfigurieren.</p>
        <p style="font-size:.78rem;margin-top:.5rem">
          Kategorien mit lila Punkt haben bereits eine Konfiguration.
        </p>
      </div>
      <?php } ?>
    </div>
  </div>

<?php

/* ═══════════════════════════════════════════════════════════════
   PAGE: HOMEPAGE
═══════════════════════════════════════════════════════════════ */
} elseif ($pageTab === 'homepage') {
    $injectOptions = [
        'content_top'    => 'Anfang des Inhaltsbereichs (Standard)',
        'content_bottom' => 'Ende des Inhaltsbereichs',
        'after_header'   => 'Nach dem Header',
        'before_footer'  => 'Vor dem Footer',
        'after_hero'     => 'Nach dem Hero/Slider-Banner',
        'after_cms'      => 'Nach dem CMS-Inhaltsbereich',
    ];
    $currentPos = trim((string)($homepageRow->cInjectPosition ?? 'content_top'));
    $isCustom   = $currentPos !== '' && !array_key_exists($currentPos, $injectOptions);
    if ($isCustom && str_starts_with($currentPos, 'custom:')) {
        $customSelector = bd_normalize_custom_selector(substr($currentPos, 7));
    } else {
        $customSelector = '';
        if ($isCustom) { $currentPos = 'content_top'; $isCustom = false; }
    }
?>
  <div class="bd-card">
    <p class="bd-card-title">Startseite Showcase</p>

    <!-- Inject Pozisyon Seçimi -->
    <div class="bd-card" style="background:#f0fdf4;border-color:#bbf7d0;margin-bottom:1rem;padding:1rem">
      <p style="margin:0 0 .7rem;font-weight:700;font-size:.88rem;color:#166534">
        📍 Inject-Position — Wo soll das Showcase auf der Startseite erscheinen?
      </p>
      <div style="display:flex;flex-direction:column;gap:.4rem" id="injectPosList">
        <?php foreach ($injectOptions as $val => $label) { ?>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;cursor:pointer;padding:.3rem .5rem;border-radius:6px;border:1px solid #d1fae5">
          <input type="radio" name="bd_inject_pos_ui" value="<?= bd_h($val) ?>"
                 <?= $currentPos === $val && !$isCustom ? 'checked' : '' ?>
                 onchange="document.getElementById('hidInjectPos').value=this.value;
                           document.getElementById('customSelectorRow').style.display='none'">
          <span><?= bd_h($label) ?></span>
        </label>
        <?php } ?>
        <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;cursor:pointer;padding:.3rem .5rem;border-radius:6px;border:1px solid #d1fae5">
          <input type="radio" name="bd_inject_pos_ui" value="custom"
                 <?= $isCustom ? 'checked' : '' ?>
                 onchange="document.getElementById('customSelectorRow').style.display='';bdSyncCustomInjectPos()">
          <span>Benutzerdefinierter CSS-Selektor</span>
        </label>
      </div>
      <div id="customSelectorRow" style="margin-top:.6rem;<?= $isCustom ? '' : 'display:none' ?>">
        <label style="font-size:.82rem;font-weight:600;margin-bottom:.25rem;display:block">CSS-Selektor (Inhalt wird danach eingefügt)</label>
        <input type="text" id="customSelectorInput"
               placeholder=".mein-element, #meine-id"
               value="<?= bd_h($customSelector) ?>"
               oninput="bdSyncCustomInjectPos()"
               style="width:100%;padding:.4rem .7rem;border:1px solid #d1d5db;border-radius:6px;font-size:.85rem">
        <small style="color:#6b7280">Beispiel: <code>.startseite-banner</code> oder <code>#hero-section</code>. Kein <code>custom:</code>-Präfix nötig. Wird der Selektor nicht gefunden, erscheint ein HTML-Kommentar im Seitenquelltext.</small>
      </div>
    </div>

    <?php bd_render_form('homepage', 0, $homepageRow, $allCats, false, false); ?>
  </div>
<?php } ?>

  </div><!-- .bd-tab-body -->
</div><!-- .bd-wrap -->

<!-- ── Media Picker Modal ──────────────────────────────────── -->
<div id="bdMediaModal" class="bd-modal-overlay" style="display:none" onclick="if(event.target===this)bdCloseMediaModal()">
  <div class="bd-modal">
    <div class="bd-modal-header">
      <h4>Mediathek</h4>
      <button type="button" class="bd-btn bd-btn-ghost bd-btn-sm" onclick="bdCloseMediaModal()">✕ Schließen</button>
    </div>
    <div class="bd-modal-body">
      <div class="bd-media-grid" id="bdMediaGrid">
        <?php foreach ($uploadedFiles as $f) { ?>
        <div class="bd-media-item" data-url="<?= bd_h($f['url']) ?>" data-filename="<?= bd_h($f['filename']) ?>"
             onclick="bdPickMedia(this)">
          <img src="<?= bd_h($f['url']) ?>" alt="<?= bd_h($f['filename']) ?>" loading="lazy">
          <div class="bd-media-name"><?= bd_h($f['filename']) ?></div>
          <button type="button" class="bd-media-del" onclick="event.stopPropagation();bdDeleteMedia(this)"
                  title="Datei löschen">✕</button>
        </div>
        <?php } ?>
        <?php if (empty($uploadedFiles)) { ?>
        <p style="color:#9ca3af;font-size:.85rem;padding:.5rem" id="bdMediaEmpty">Noch keine Bilder hochgeladen.</p>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
'use strict';

/* ── AJAX base URL (same page = session cookie + kPlugin params) ── */
var ADMIN_AJAX_URL = window.location.pathname + window.location.search;

function getToken() {
  var el = document.querySelector('input[name="jtl_token"]');
  return el ? el.value : '';
}

/* ── Init sortables ────────────────────────────────────────── */
document.querySelectorAll('[id^="tabBuilder_"]').forEach(function (el) {
  initTabBuilder(el);
});
document.querySelectorAll('[data-img-sortable]').forEach(initImgSortable);

function initTabBuilder(el) {
  Sortable.create(el, { handle: '.bd-drag-handle', animation: 150,
    ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen' });
  el.querySelectorAll('[data-img-sortable]').forEach(initImgSortable);
}

function initImgSortable(el) {
  Sortable.create(el, { handle: '.bd-drag-handle', animation: 120,
    ghostClass: 'sortable-ghost', chosenClass: 'sortable-chosen' });
}

/* ── Tab operations ────────────────────────────────────────── */
window.bdAddTab = function (type, uid) {
  var builder = document.getElementById('tabBuilder_' + uid);
  if (!builder) return;
  var idx   = builder.querySelectorAll('.bd-tab-row').length;
  var label = type === 'video' ? 'Video' : 'Bilder ' + (idx + 1);
  var html  = bdTabRowHtml({ type: type, label: label, videoUrl: '', images: [] }, idx, uid);
  var tmp   = document.createElement('div');
  tmp.innerHTML = html.trim();
  var node = tmp.firstChild;
  builder.appendChild(node);
  node.querySelectorAll('[data-img-sortable]').forEach(initImgSortable);
};

window.bdRemoveTab = function (btn) {
  btn.closest('.bd-tab-row').remove();
};

window.bdSetTabType = function (btn, uid) {
  var row  = btn.closest('.bd-tab-row');
  var type = btn.dataset.type;
  row.querySelectorAll('.bd-tab-type-btn').forEach(function (b) {
    b.classList.toggle('active', b.dataset.type === type);
  });
  row.querySelector('.bd-tab-video-area').style.display  = type === 'video'  ? '' : 'none';
  row.querySelector('.bd-tab-images-area').style.display = type === 'images' ? '' : 'none';
};

/* ── Image row operations ──────────────────────────────────── */
window.bdAddImageRow = function (btn, uid) {
  var list = btn.previousElementSibling;
  var html = bdImgRowHtml('', uid);
  var tmp  = document.createElement('div');
  tmp.innerHTML = html.trim();
  list.appendChild(tmp.firstChild);
};

window.bdUpdateThumb = function (input) {
  var thumb = input.closest('.bd-img-row').querySelector('.bd-img-thumb');
  if (thumb) thumb.src = input.value.trim() || 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
};

/* ── Upload helpers ────────────────────────────────────────── */
window.bdHandleFileInput = function (fileInput, uid, tabIdx) {
  uploadFiles(Array.from(fileInput.files), uid, tabIdx);
  fileInput.value = '';
};

window.bdHandleDrop = function (e, uid, tabIdx) {
  e.preventDefault();
  var zone = document.getElementById('uploadZone_' + uid + '_' + tabIdx);
  if (zone) zone.classList.remove('dragover');
  var files = Array.from(e.dataTransfer.files).filter(function (f) { return f.type.startsWith('image/'); });
  uploadFiles(files, uid, tabIdx);
};

function uploadFiles(files, uid, tabIdx) {
  if (!files.length) return;
  var progress    = document.getElementById('progress_' + uid + '_' + tabIdx);
  var progressBar = document.getElementById('progressBar_' + uid + '_' + tabIdx);
  if (progress) { progress.style.display = 'block'; }

  var done = 0;
  files.forEach(function (file) {
    var fd = new FormData();
    fd.append('bdAjax', 'upload');
    fd.append('file', file);
    fd.append('jtl_token', getToken());

    var xhr = new XMLHttpRequest();
    xhr.open('POST', ADMIN_AJAX_URL, true);
    xhr.withCredentials = true;

    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable && progressBar) {
        progressBar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
      }
    };

    xhr.onload = function () {
      done++;
      try {
        var res = JSON.parse(xhr.responseText);
        if (res.ok) {
          bdAppendImageRow(res.url, res.filename, uid, tabIdx);
          bdAddToMediaGrid(res.url, res.filename);
        } else {
          alert('Upload-Fehler: ' + (res.error || 'Unbekannt'));
        }
      } catch (ex) {
        alert('Server-Antwort ungültig.');
      }
      if (done === files.length) {
        if (progressBar) progressBar.style.width = '0';
        if (progress)    progress.style.display  = 'none';
      }
    };

    xhr.onerror = function () {
      done++;
      alert('Netzwerkfehler beim Upload.');
      if (done === files.length && progress) progress.style.display = 'none';
    };

    xhr.send(fd);
  });
}

function bdAppendImageRow(url, filename, uid, tabIdx) {
  // Doğru tab-row'u bul (tabIdx'e göre)
  var builder = document.getElementById('tabBuilder_' + uid);
  if (!builder) return;
  var rows = builder.querySelectorAll('.bd-tab-row');
  var row  = rows[tabIdx] || null;
  if (!row) {
    // fallback: son images tab
    rows.forEach(function (r) {
      var activeType = r.querySelector('.bd-tab-type-btn.active');
      if (activeType && activeType.dataset.type === 'images') row = r;
    });
  }
  if (!row) return;
  var list = row.querySelector('[data-img-sortable]');
  if (!list) return;
  var html = bdImgRowHtml(url, uid, filename, true);
  var tmp  = document.createElement('div');
  tmp.innerHTML = html.trim();
  list.appendChild(tmp.firstChild);
}

/* ── Media picker ──────────────────────────────────────────── */
var _pickerTargetRow = null;

window.bdOpenMediaPicker = function (btn, uid) {
  _pickerTargetRow = btn.closest('.bd-img-row');
  document.getElementById('bdMediaModal').style.display = 'flex';
};

window.bdCloseMediaModal = function () {
  document.getElementById('bdMediaModal').style.display = 'none';
  _pickerTargetRow = null;
};

window.bdPickMedia = function (item) {
  var url = item.dataset.url;
  var filename = item.dataset.filename;
  if (!_pickerTargetRow) return;
  var input = _pickerTargetRow.querySelector('input[type=text]');
  if (input) {
    input.value = url;
    input.dataset.imgFilename = filename;
    bdUpdateThumb(input);
  }
  bdCloseMediaModal();
};

window.bdDeleteMedia = function (btn) {
  if (!confirm('Datei dauerhaft löschen?')) return;
  var item     = btn.closest('.bd-media-item');
  var filename = item.dataset.filename;
  var fd       = new FormData();
  fd.append('bdAjax', 'deletefile');
  fd.append('filename', filename);
  fd.append('jtl_token', getToken());
  fetch(ADMIN_AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.ok) {
        item.remove();
        // Sayfadaki aynı URL'ye sahip satırları temizle
        document.querySelectorAll('input[data-img-filename="' + filename + '"]').forEach(function (inp) {
          inp.closest('.bd-img-row').remove();
        });
      }
    });
};

window.bdDeleteUploadedAndRow = function (btn, filename) {
  if (!confirm('Bild und Zeile entfernen?')) return;
  var row = btn.closest('.bd-img-row');
  var fd  = new FormData();
  fd.append('bdAjax', 'deletefile');
  fd.append('filename', filename);
  fd.append('jtl_token', getToken());
  fetch(ADMIN_AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin' }).finally(function () { row.remove(); });
  // Modal grid'den de kaldır
  var gridItem = document.querySelector('#bdMediaGrid [data-filename="' + filename + '"]');
  if (gridItem) gridItem.remove();
};

function bdAddToMediaGrid(url, filename) {
  var empty = document.getElementById('bdMediaEmpty');
  if (empty) empty.remove();
  var grid = document.getElementById('bdMediaGrid');
  if (!grid) return;
  var div = document.createElement('div');
  div.className = 'bd-media-item';
  div.dataset.url = url;
  div.dataset.filename = filename;
  div.onclick = function () { bdPickMedia(div); };
  div.innerHTML = '<img src="' + _esc(url) + '" alt="" loading="lazy">'
    + '<div class="bd-media-name">' + _esc(filename) + '</div>'
    + '<button type="button" class="bd-media-del" onclick="event.stopPropagation();bdDeleteMedia(this)" title="Löschen">✕</button>';
  grid.prepend(div);
}

/* ── Collect before submit ─────────────────────────────────── */
window.bdCollectAll = function (uid) {
  var builder = document.getElementById('tabBuilder_' + uid);
  var hidTabs = document.getElementById('hidTabsJson_' + uid);
  var hidCats = document.getElementById('hidCatsJson_' + uid);
  if (!builder || !hidTabs) return;

  var tabs = [];
  builder.querySelectorAll('.bd-tab-row').forEach(function (row) {
    var activeTypeBtn = row.querySelector('.bd-tab-type-btn.active');
    var type     = activeTypeBtn ? activeTypeBtn.dataset.type : 'images';
    var label    = (row.querySelector('.bd-tab-label') || {}).value || '';
    var videoUrl = '';
    var images   = [];
    if (type === 'video') {
      var vu = row.querySelector('.bd-tab-video-url');
      videoUrl = vu ? vu.value.trim() : '';
    } else {
      row.querySelectorAll('[data-img-sortable] .bd-img-row input[type=text]').forEach(function (inp) {
        var u = inp.value.trim();
        if (u) images.push(u);
      });
    }
    tabs.push({ type: type, label: label, videoUrl: videoUrl, images: images });
  });
  hidTabs.value = JSON.stringify(tabs);

  if (hidCats) {
    var catIds = [];
    var catTree = document.getElementById('catTree_' + uid);
    if (catTree) {
      catTree.querySelectorAll('.bd-cat-cb:checked').forEach(function (cb) {
        catIds.push(parseInt(cb.value, 10));
      });
    }
    hidCats.value = JSON.stringify(catIds);
  }
};

/* ── Category helpers ──────────────────────────────────────── */
window.bdToggleChildren = function (btn) {
  var ch = btn.closest('.bd-cat-group').querySelector('.bd-cat-children');
  if (!ch) return;
  var open = ch.style.display !== 'none';
  ch.style.display = open ? 'none' : '';
  btn.textContent  = open ? '▼ Unterkategorien' : '▲ Unterkategorien';
};

window.bdFilterCats = function (val, uid) {
  var tree = document.getElementById('catTree_' + uid);
  if (!tree) return;
  var q = val.toLowerCase().trim();
  tree.querySelectorAll('.bd-cat-group').forEach(function (grp) {
    var parentName   = (grp.dataset.catName || '').toLowerCase();
    var anyChildMatch = false;
    grp.querySelectorAll('.bd-cat-item').forEach(function (ci) {
      var show = q === '' || (ci.dataset.catName || '').toLowerCase().includes(q);
      ci.style.display = show ? '' : 'none';
      if (show) anyChildMatch = true;
    });
    grp.style.display = (q === '' || parentName.includes(q) || anyChildMatch) ? '' : 'none';
    if (anyChildMatch && q !== '') {
      var childContainer = grp.querySelector('.bd-cat-children');
      if (childContainer) childContainer.style.display = '';
    }
  });
};

/* ── Kategori ağacı (sol panel) toggle + arama ─────────────── */
window.bdToggleCatGroup = function (toggleBtn) {
  var children = toggleBtn.closest('.bd-cat-tree-group').querySelector('.bd-cat-children-tree');
  if (!children) return;
  var open = children.style.display !== 'none';
  children.style.display = open ? 'none' : '';
  toggleBtn.textContent  = open ? '▶' : '▼';
};

window.bdFilterCatTree = function (val) {
  var tree = document.getElementById('bdCatTree');
  if (!tree) return;
  var q = val.toLowerCase().trim();
  tree.querySelectorAll('.bd-cat-tree-group').forEach(function (grp) {
    var parentName   = (grp.dataset.catName || '').toLowerCase();
    var anyChildMatch = false;
    grp.querySelectorAll('[data-cat-name]').forEach(function (item) {
      if (item === grp) return; // skip group itself
      var cn   = (item.dataset.catName || '').toLowerCase();
      var show = q === '' || cn.includes(q);
      item.style.display = show ? '' : 'none';
      if (show) anyChildMatch = true;
    });
    var groupShow = q === '' || parentName.includes(q) || anyChildMatch;
    grp.style.display = groupShow ? '' : 'none';
    if (anyChildMatch && q !== '') {
      var ch = grp.querySelector('.bd-cat-children-tree');
      if (ch) ch.style.display = '';
    }
  });
};

/* ── HTML generators ───────────────────────────────────────── */
function bdTabRowHtml(tab, idx, uid) {
  var type     = tab.type || 'images';
  var label    = tab.label || '';
  var videoUrl = tab.videoUrl || '';
  var images   = tab.images || [];
  var imgRows  = images.map(function (u) { return bdImgRowHtml(u, uid); }).join('');
  var zoneId   = 'uploadZone_' + uid + '_' + idx;
  var fileId   = 'fileInput_'  + uid + '_' + idx;
  var progId   = 'progress_'   + uid + '_' + idx;
  var barId    = 'progressBar_' + uid + '_' + idx;

  return '<div class="bd-tab-row" data-tab-idx="' + idx + '">'
    + '<div class="bd-tab-row-header">'
    +   '<span class="bd-drag-handle">⠿</span>'
    +   '<input class="bd-tab-label" type="text" placeholder="Tab-Bezeichnung" value="' + _esc(label) + '">'
    +   '<div class="bd-tab-type-sel">'
    +     '<button type="button" class="bd-tab-type-btn' + (type === 'video'  ? ' active' : '') + '" data-type="video"  onclick="bdSetTabType(this,\'' + uid + '\')">▶ Video</button>'
    +     '<button type="button" class="bd-tab-type-btn' + (type === 'images' ? ' active' : '') + '" data-type="images" onclick="bdSetTabType(this,\'' + uid + '\')">🖼 Bilder</button>'
    +   '</div>'
    +   '<button type="button" class="bd-btn bd-btn-danger bd-btn-icon bd-btn-sm" onclick="bdRemoveTab(this)" title="Tab entfernen">✕</button>'
    + '</div>'
    + '<div class="bd-tab-row-body">'
    +   '<div class="bd-tab-video-area" style="' + (type !== 'video'  ? 'display:none' : '') + '">'
    +     '<div class="bd-fg"><label>YouTube-URL</label>'
    +     '<input type="text" class="bd-tab-video-url" placeholder="https://youtu.be/…" value="' + _esc(videoUrl) + '"></div>'
    +   '</div>'
    +   '<div class="bd-tab-images-area" style="' + (type !== 'images' ? 'display:none' : '') + '">'
    +     '<div class="bd-upload-zone" id="' + zoneId + '"'
    +       ' onclick="document.getElementById(\'' + fileId + '\').click()"'
    +       ' ondragover="event.preventDefault();this.classList.add(\'dragover\')"'
    +       ' ondragleave="this.classList.remove(\'dragover\')"'
    +       ' ondrop="bdHandleDrop(event,\'' + uid + '\',\'' + idx + '\')">'
    +       '<div class="bd-upload-icon">☁️</div>'
    +       '<p><strong>Bilder hochladen</strong></p>'
    +       '<p style="font-size:.8rem;color:#6b7280">Klicken oder ziehen – JPG, PNG, WebP, GIF</p>'
    +       '<input type="file" id="' + fileId + '" accept="image/jpeg,image/png,image/webp,image/gif" multiple'
    +         ' onchange="bdHandleFileInput(this,\'' + uid + '\',\'' + idx + '\')">'
    +     '</div>'
    +     '<div class="bd-progress" id="' + progId + '"><div class="bd-progress-bar" id="' + barId + '"></div></div>'
    +     '<div class="bd-img-list" data-img-sortable style="margin-top:.6rem">' + imgRows + '</div>'
    +     '<button type="button" class="bd-btn bd-btn-ghost bd-btn-sm" style="margin-top:.4rem"'
    +       ' onclick="bdAddImageRow(this,\'' + uid + '\')">+ URL manuell</button>'
    +   '</div>'
    + '</div></div>';
}

function bdImgRowHtml(url, uid, filename, isBdFile) {
  url      = url      || '';
  filename = filename || (url ? url.split('/').pop() : '');
  isBdFile = isBdFile || (filename.indexOf('bd_') === 0);
  var thumb = url
    ? '<img class="bd-img-thumb" src="' + _esc(url) + '" alt="" loading="lazy" onerror="this.src=\'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=\'">'
    : '<img class="bd-img-thumb" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" alt="">';
  var delBtn = isBdFile
    ? '<button type="button" class="bd-btn bd-btn-danger bd-btn-sm" onclick="bdDeleteUploadedAndRow(this,\'' + _esc(filename) + '\')" title="Datei löschen">🗑</button>'
    : '<button type="button" class="bd-btn bd-btn-danger bd-btn-sm" onclick="this.closest(\'.bd-img-row\').remove()" title="Entfernen">✕</button>';
  return '<div class="bd-img-row">'
    + '<span class="bd-drag-handle">⠿</span>'
    + thumb
    + '<div class="bd-img-input-wrap">'
    +   '<input type="text" placeholder="URL…" value="' + _esc(url) + '" data-img-filename="' + _esc(filename) + '" oninput="bdUpdateThumb(this)">'
    + '</div>'
    + '<div class="bd-img-actions">'
    +   '<button type="button" class="bd-btn bd-btn-ghost bd-btn-sm" onclick="bdOpenMediaPicker(this,\'' + uid + '\')" title="Aus Mediathek">&#128247;</button>'
    +   delBtn
    + '</div>'
    + '</div>';
}

function _esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

window.bdNormalizeCustomSelector = function (raw) {
  var s = String(raw || '').trim();
  while (s.toLowerCase().indexOf('custom:') === 0) {
    s = s.slice(7).trim();
  }
  return s;
};

window.bdSyncCustomInjectPos = function () {
  var inp = document.getElementById('customSelectorInput');
  var hid = document.getElementById('hidInjectPos');
  var customRadio = document.querySelector('input[name="bd_inject_pos_ui"][value="custom"]');
  if (!inp || !hid) return;
  if (customRadio && customRadio.checked) {
    var sel = window.bdNormalizeCustomSelector(inp.value);
    inp.value = sel;
    hid.value = sel ? ('custom:' + sel) : 'custom:';
  }
};

document.addEventListener('DOMContentLoaded', function () {
  window.bdSyncCustomInjectPos();
});

})();
</script>
</body>
</html>
