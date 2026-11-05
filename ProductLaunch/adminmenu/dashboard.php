<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use Plugin\ProductLaunch\LaunchRepository;

require_once dirname(__DIR__) . '/src/LaunchRepository.php';

LaunchRepository::ensure();

if (!function_exists('plH')) {
    function plH(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    function plUrl(array $set = []): string
    {
        $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''));
        parse_str((string)($p['query'] ?? ''), $q);
        unset($q['pl_edit'], $q['pl_new'], $q['pl_search'], $q['pl_type'], $q['q']);
        foreach ($set as $k => $v) {
            $q[$k] = $v;
        }

        return (string)($p['path'] ?? '') . ($q !== [] ? '?' . http_build_query($q) : '');
    }

    function plDate(string $v): ?string
    {
        $v = trim($v);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }
}

// AJAX: Artikel- bzw. Kategoriesuche
if (isset($_GET['pl_search'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $found = (string)($_GET['pl_type'] ?? '') === 'cat'
        ? LaunchRepository::searchCategories((string)($_GET['q'] ?? ''))
        : LaunchRepository::searchArticles((string)($_GET['q'] ?? ''));
    $json = json_encode(['items' => $found], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '__PL_START__' . ($json !== false ? $json : '{"items":[]}') . '__PL_END__';
    exit;
}

$message = '';
$error   = '';
$editId  = (int)($_GET['pl_edit'] ?? 0);
$isNew   = !empty($_GET['pl_new']);

if (Form::validateToken()) {
    if (!empty($_POST['pl_save'])) {
        $data = [
            'kArtikel'   => (int)($_POST['pl_art'] ?? 0),
            'kKategorie' => (int)($_POST['pl_cat'] ?? 0),
            'cBadge'     => mb_substr(trim((string)($_POST['pl_badge'] ?? '')), 0, 60),
            'cTitle'     => mb_substr(trim((string)($_POST['pl_title'] ?? '')), 0, 255),
            'cText'      => trim((string)($_POST['pl_text'] ?? '')),
            'cButton'    => mb_substr(trim((string)($_POST['pl_button'] ?? '')), 0, 80),
            'dStart'     => plDate((string)($_POST['pl_start'] ?? '')),
            'dEnd'       => plDate((string)($_POST['pl_end'] ?? '')),
            'nActive'    => !empty($_POST['pl_active']) ? 1 : 0,
            'nSort'      => (int)($_POST['pl_sort'] ?? 0),
        ];
        $id = (int)($_POST['pl_id'] ?? 0);
        if ($data['kArtikel'] <= 0) {
            $error  = 'Lütfen bir ürün seçin.';
            $editId = $id;
            $isNew  = $id === 0;
        } else {
            $editId  = LaunchRepository::save($id, $data);
            $isNew   = false;
            $message = 'Kaydedildi.';
        }
    } elseif (!empty($_POST['pl_delete'])) {
        LaunchRepository::delete((int)$_POST['pl_delete']);
        $message = 'Silindi.';
        $editId  = 0;
    } elseif (isset($_POST['pl_toggle'])) {
        LaunchRepository::setActive((int)$_POST['pl_toggle'], !empty($_POST['pl_to']));
        $message = 'Güncellendi.';
    }
}

$token = Form::getTokenInput();
$today = date('Y-m-d');
?>
<style>
    .pl-admin small{color:#777}
    .pl-admin .pl-res{max-height:260px;overflow:auto;border:1px solid #ddd;border-radius:4px;margin-top:.4rem;background:#fff;position:relative;z-index:5}
    .pl-admin .pl-res div{padding:.4rem .6rem;cursor:pointer;border-bottom:1px solid #eee}
    .pl-admin .pl-res div:hover{background:#f2f6ff}
    .pl-admin .pl-pick{display:flex;align-items:center;gap:.5rem;padding:.45rem .7rem;border:1px solid #cfe3d6;border-radius:4px;background:#f3faf5;margin-bottom:.4rem}
    .pl-admin .pl-pick span{flex:1}
    .pl-admin .pl-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 1.25rem}
    .pl-admin .pl-status{display:inline-block;padding:.15rem .55rem;border-radius:999px;font-size:.8rem;font-weight:700}
    .pl-admin .pl-on{background:#dff5e6;color:#14663a}
    .pl-admin .pl-off{background:#eee;color:#666}
    .pl-admin .pl-wait{background:#fff4d6;color:#8a6100}
</style>
<div class="pl-admin" style="padding:10px 0;">
<?php if ($message !== '') { ?>
    <div class="alert alert-success"><?php echo plH($message); ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
    <div class="alert alert-danger"><?php echo plH($error); ?></div>
<?php } ?>

<?php if ($editId > 0 || $isNew) {
    $row = $editId > 0 ? LaunchRepository::get($editId) : null;
    if ($editId > 0 && $row === null) {
        echo '<div class="alert alert-warning">Kayıt bulunamadı.</div>';
        $row = null;
    }
    $artId   = (int)($row->kArtikel ?? ($_POST['pl_art'] ?? 0));
    $catId   = (int)($row->kKategorie ?? ($_POST['pl_cat'] ?? 0));
    $artInfo = LaunchRepository::articleInfo([$artId])[$artId] ?? null;
    $catPath = $catId > 0 ? (LaunchRepository::categoryPaths([$catId])[$catId] ?? ('#' . $catId)) : '';
    $active  = $row === null ? true : (int)$row->nActive === 1;
    ?>
    <p><a href="<?php echo plH(plUrl()); ?>">&larr; Tüm lansmanlar</a></p>
    <h3><?php echo $row !== null ? 'Lansmanı düzenle' : 'Yeni lansman'; ?></h3>
    <p>Seçilen ürün anasayfada büyük bir vitrin kartı olarak gösterilir. Başlık, metin ve buton boş bırakılırsa ürün adı ve kısa açıklama kullanılır.</p>

    <form method="post" action="<?php echo plH(plUrl($editId > 0 ? ['pl_edit' => $editId] : ['pl_new' => 1])); ?>">
        <?php echo $token; ?>
        <input type="hidden" name="pl_id" value="<?php echo (int)$editId; ?>">

        <div class="pl-grid">
            <div class="form-group">
                <label><strong>Ürün *</strong> <small>(ad, ürün no veya ID)</small></label>
                <input type="hidden" name="pl_art" id="pl-art" value="<?php echo $artId > 0 ? $artId : ''; ?>">
                <div class="pl-pick" id="pl-art-pick" style="<?php echo $artInfo ? '' : 'display:none'; ?>">
                    <span><?php echo $artInfo ? plH($artInfo['name']) . ' <small>#' . $artId . ' ' . plH($artInfo['artNr']) . '</small>' : ''; ?></span>
                    <button type="button" class="btn btn-sm btn-light" data-pl-clear="art">&times;</button>
                </div>
                <input type="text" class="form-control pl-search" data-type="art" placeholder="Ürün ara..." autocomplete="off">
                <div class="pl-res" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label><strong>Kategori</strong> <small>(opsiyonel – "Alle in …" butonu ve etiket)</small></label>
                <input type="hidden" name="pl_cat" id="pl-cat" value="<?php echo $catId > 0 ? $catId : ''; ?>">
                <div class="pl-pick" id="pl-cat-pick" style="<?php echo $catPath !== '' ? '' : 'display:none'; ?>">
                    <span><?php echo $catPath !== '' ? plH($catPath) . ' <small>#' . $catId . '</small>' : ''; ?></span>
                    <button type="button" class="btn btn-sm btn-light" data-pl-clear="cat">&times;</button>
                </div>
                <input type="text" class="form-control pl-search" data-type="cat" placeholder="Kategori ara..." autocomplete="off">
                <div class="pl-res" style="display:none;"></div>
            </div>
        </div>

        <div class="pl-grid">
            <div class="form-group">
                <label>Başlık <small>(boşsa ürün adı)</small></label>
                <input type="text" class="form-control" name="pl_title" maxlength="255" value="<?php echo plH((string)($row->cTitle ?? '')); ?>">
            </div>
            <div class="form-group">
                <label>Rozet <small>(boşsa "NEU")</small></label>
                <input type="text" class="form-control" name="pl_badge" maxlength="60" value="<?php echo plH((string)($row->cBadge ?? '')); ?>" placeholder="NEU">
            </div>
        </div>

        <div class="form-group">
            <label>Tanıtım metni <small>(boşsa ürünün kısa açıklaması)</small></label>
            <textarea class="form-control" name="pl_text" rows="3"><?php echo plH((string)($row->cText ?? '')); ?></textarea>
        </div>

        <div class="pl-grid">
            <div class="form-group">
                <label>Buton metni <small>(boşsa "Jetzt entdecken")</small></label>
                <input type="text" class="form-control" name="pl_button" maxlength="80" value="<?php echo plH((string)($row->cButton ?? '')); ?>" placeholder="Jetzt entdecken">
            </div>
            <div class="form-group">
                <label>Sıra <small>(küçük olan önce)</small></label>
                <input type="number" class="form-control" name="pl_sort" value="<?php echo (int)($row->nSort ?? 0); ?>">
            </div>
            <div class="form-group">
                <label>Başlangıç tarihi <small>(opsiyonel)</small></label>
                <input type="date" class="form-control" name="pl_start" value="<?php echo plH((string)($row->dStart ?? '')); ?>">
            </div>
            <div class="form-group">
                <label>Bitiş tarihi <small>(opsiyonel)</small></label>
                <input type="date" class="form-control" name="pl_end" value="<?php echo plH((string)($row->dEnd ?? '')); ?>">
            </div>
        </div>

        <div class="checkbox" style="margin:.5rem 0 1rem;">
            <label><input type="checkbox" name="pl_active" value="1" <?php echo $active ? 'checked' : ''; ?>> Aktif (anasayfada göster)</label>
        </div>

        <button type="submit" name="pl_save" value="1" class="btn btn-primary">Kaydet</button>
        <?php if ($row !== null) { ?>
            <button type="submit" name="pl_delete" value="<?php echo (int)$editId; ?>" class="btn btn-danger"
                    onclick="return confirm('Bu lansman silinsin mi?');">Sil</button>
        <?php } ?>
    </form>
<?php } else {
    $rows    = LaunchRepository::all();
    $artIds  = array_map(static fn($r): int => (int)$r->kArtikel, $rows);
    $catIds  = array_map(static fn($r): int => (int)$r->kKategorie, $rows);
    $artInfo = LaunchRepository::articleInfo($artIds);
    $catPath = LaunchRepository::categoryPaths($catIds);
    ?>
    <h3>Ürün lansmanları</h3>
    <p>Aktif ve tarih aralığında olan lansmanlar anasayfada (konfigüratörün altında) gösterilir. Birden fazla aktif lansman varsa slider olur.</p>
    <p><a class="btn btn-primary" href="<?php echo plH(plUrl(['pl_new' => 1])); ?>">+ Yeni lansman</a></p>

    <table class="table table-striped">
        <thead><tr><th>Ürün</th><th>Kategori</th><th>Başlık</th><th>Tarih</th><th>Sıra</th><th>Durum</th><th style="width:1%"></th></tr></thead>
        <tbody>
        <?php if ($rows === []) { ?>
            <tr><td colspan="7"><em>Henüz lansman yok.</em></td></tr>
        <?php } ?>
        <?php foreach ($rows as $r) {
            $id   = (int)$r->kID;
            $aid  = (int)$r->kArtikel;
            $cid  = (int)$r->kKategorie;
            $on   = (int)$r->nActive === 1;
            $s    = (string)($r->dStart ?? '');
            $e    = (string)($r->dEnd ?? '');
            $live = $on && ($s === '' || $s <= $today) && ($e === '' || $e >= $today);
            if ($live) {
                $st = '<span class="pl-status pl-on">Yayında</span>';
            } elseif ($on && $s !== '' && $s > $today) {
                $st = '<span class="pl-status pl-wait">Planlandı</span>';
            } elseif ($on) {
                $st = '<span class="pl-status pl-off">Süresi doldu</span>';
            } else {
                $st = '<span class="pl-status pl-off">Pasif</span>';
            }
            ?>
            <tr>
                <td><?php echo plH($artInfo[$aid]['name'] ?? '(bulunamadı)'); ?> <small>#<?php echo $aid; ?> <?php echo plH($artInfo[$aid]['artNr'] ?? ''); ?></small></td>
                <td><?php echo $cid > 0 ? plH($catPath[$cid] ?? ('#' . $cid)) : '<small>–</small>'; ?></td>
                <td><?php echo plH((string)$r->cTitle) !== '' ? plH((string)$r->cTitle) : '<small>(ürün adı)</small>'; ?></td>
                <td><small><?php echo plH($s !== '' ? $s : '…'); ?> – <?php echo plH($e !== '' ? $e : '…'); ?></small></td>
                <td><?php echo (int)$r->nSort; ?></td>
                <td><?php echo $st; ?></td>
                <td style="white-space:nowrap">
                    <form method="post" action="<?php echo plH(plUrl()); ?>" style="display:inline">
                        <?php echo $token; ?>
                        <input type="hidden" name="pl_toggle" value="<?php echo $id; ?>">
                        <input type="hidden" name="pl_to" value="<?php echo $on ? '' : '1'; ?>">
                        <button type="submit" class="btn btn-sm btn-light"><?php echo $on ? 'Pasif yap' : 'Aktif yap'; ?></button>
                    </form>
                    <a class="btn btn-sm btn-primary" href="<?php echo plH(plUrl(['pl_edit' => $id])); ?>">Düzenle</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
</div>

<script>
(function () {
    var searchBase = <?php echo json_encode(plUrl(['pl_search' => 1])); ?>;

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function pick(type, it) {
        var hidden = document.getElementById('pl-' + type);
        var box = document.getElementById('pl-' + type + '-pick');
        if (!hidden || !box) { return; }
        hidden.value = it ? it.id : '';
        box.querySelector('span').innerHTML = it ? esc(it.name) + ' <small>#' + it.id + ' ' + esc(it.artNr || '') + '</small>' : '';
        box.style.display = it ? '' : 'none';
    }

    document.querySelectorAll('[data-pl-clear]').forEach(function (b) {
        b.addEventListener('click', function () { pick(b.getAttribute('data-pl-clear'), null); });
    });

    document.querySelectorAll('.pl-search').forEach(function (input) {
        var type = input.getAttribute('data-type');
        var res = input.parentElement.querySelector('.pl-res');
        var timer = null;
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var v = input.value.trim();
            if (v.length < 2) { res.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch(searchBase + '&pl_type=' + type + '&q=' + encodeURIComponent(v), { credentials: 'same-origin' })
                    .then(function (r) { return r.text(); })
                    .then(function (t) {
                        var a = t.indexOf('__PL_START__'), b = t.indexOf('__PL_END__');
                        if (a < 0 || b < 0) { return; }
                        var items = JSON.parse(t.substring(a + 12, b)).items || [];
                        res.innerHTML = '';
                        items.forEach(function (it) {
                            var d = document.createElement('div');
                            d.innerHTML = esc(it.name) + ' <small>#' + it.id + ' ' + esc(it.artNr || '') + '</small>';
                            d.addEventListener('click', function () { pick(type, it); res.style.display = 'none'; input.value = ''; });
                            res.appendChild(d);
                        });
                        res.style.display = items.length ? 'block' : 'none';
                    });
            }, 250);
        });
    });
})();
</script>
