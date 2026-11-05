<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use Plugin\CrossSell\CrossSellRepository;

require_once dirname(__DIR__) . '/src/CrossSellRepository.php';

CrossSellRepository::ensure();

if (!function_exists('csH')) {
    function csH(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    function csUrl(array $set = []): string
    {
        $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''));
        parse_str((string)($p['query'] ?? ''), $q);
        unset($q['cs_edit'], $q['cs_search'], $q['q']);
        foreach ($set as $k => $v) {
            $q[$k] = $v;
        }

        return (string)($p['path'] ?? '') . ($q !== [] ? '?' . http_build_query($q) : '');
    }
}

// AJAX: Artikelsuche
if (isset($_GET['cs_search'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $json = json_encode(['items' => CrossSellRepository::search((string)($_GET['q'] ?? ''))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '__CS_START__' . ($json !== false ? $json : '{"items":[]}') . '__CS_END__';
    exit;
}

$message = '';
$edit    = (int)($_GET['cs_edit'] ?? 0);

if (Form::validateToken()) {
    if (!empty($_POST['cs_save'])) {
        $src = (int)($_POST['cs_src'] ?? 0);
        if ($src > 0) {
            CrossSellRepository::replaceForSource(
                $src,
                CrossSellRepository::ids((array)($_POST['rec'] ?? [])),
                !empty($_POST['cs_both'])
            );
            $message = 'Kaydedildi.';
            $edit    = $src;
        }
    } elseif (!empty($_POST['cs_delete'])) {
        CrossSellRepository::deleteSource((int)$_POST['cs_delete']);
        $message = 'Silindi.';
        $edit    = 0;
    }
}

$token = Form::getTokenInput();
?>
<style>
    .cs-admin .cs-row{display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;border:1px solid #ddd;border-radius:4px;margin-bottom:.35rem;background:#fff}
    .cs-admin .cs-row .cs-name{flex:1}
    .cs-admin .cs-res{max-height:260px;overflow:auto;border:1px solid #ddd;border-radius:4px;margin-top:.4rem}
    .cs-admin .cs-res div{padding:.4rem .6rem;cursor:pointer;border-bottom:1px solid #eee}
    .cs-admin .cs-res div:hover{background:#f2f6ff}
    .cs-admin small{color:#777}
</style>
<div class="cs-admin" style="padding:10px 0;">
<?php if ($message !== '') { ?>
    <div class="alert alert-success"><?php echo csH($message); ?></div>
<?php } ?>

<?php if ($edit > 0) {
    $recIds = CrossSellRepository::getForSource($edit);
    $info   = CrossSellRepository::articleInfo(array_merge([$edit], $recIds));
    $srcInf = $info[$edit] ?? ['id' => $edit, 'name' => '(bulunamadı)', 'artNr' => ''];
    ?>
    <p><a href="<?php echo csH(csUrl()); ?>">&larr; Tüm öneriler</a></p>
    <h3>Kaynak ürün: <?php echo csH($srcInf['name']); ?> <small>#<?php echo $edit; ?> <?php echo csH($srcInf['artNr']); ?></small></h3>
    <p>Bu ürün sayfasında / sepette bu ürün varken aşağıdaki ürünler önerilir (sıra önemlidir).</p>

    <form method="post" action="<?php echo csH(csUrl(['cs_edit' => $edit])); ?>" id="cs-form">
        <?php echo $token; ?>
        <input type="hidden" name="cs_src" value="<?php echo $edit; ?>">
        <div id="cs-list">
            <?php foreach ($recIds as $rid) {
                $ri = $info[$rid] ?? ['name' => '(bulunamadı)', 'artNr' => '']; ?>
                <div class="cs-row" data-id="<?php echo $rid; ?>">
                    <input type="hidden" name="rec[]" value="<?php echo $rid; ?>">
                    <span class="cs-name"><?php echo csH($ri['name']); ?> <small>#<?php echo $rid; ?> <?php echo csH($ri['artNr']); ?></small></span>
                    <button type="button" class="btn btn-sm btn-light cs-up">&uarr;</button>
                    <button type="button" class="btn btn-sm btn-light cs-down">&darr;</button>
                    <button type="button" class="btn btn-sm btn-danger cs-del">&times;</button>
                </div>
            <?php } ?>
        </div>

        <div class="form-group" style="margin-top:1rem;">
            <label>Önerilecek ürün ekle (ad, ürün no veya ID)</label>
            <input type="text" class="form-control" id="cs-q" placeholder="Aramak için yazın..." autocomplete="off">
            <div class="cs-res" id="cs-res" style="display:none;"></div>
        </div>

        <div class="checkbox" style="margin:.75rem 0;">
            <label><input type="checkbox" name="cs_both" value="1"> Karşılıklı da ekle (önerilen ürün sayfasında bu ürün de önerilsin)</label>
        </div>
        <button type="submit" name="cs_save" value="1" class="btn btn-primary">Kaydet</button>
        <button type="submit" name="cs_delete" value="<?php echo $edit; ?>" class="btn btn-danger"
                onclick="return confirm('Bu ürünün tüm önerileri silinsin mi?');">Tümünü sil</button>
    </form>
<?php } else { ?>
    <h3>Ürün önerileri</h3>
    <p>Önce önerinin gösterileceği ürünü seçin, sonra o ürünle birlikte hangi ürünlerin önerileceğini belirleyin.</p>
    <div class="form-group">
        <label>Kaynak ürün ara (ad, ürün no veya ID)</label>
        <input type="text" class="form-control" id="cs-q" placeholder="Aramak için yazın..." autocomplete="off">
        <div class="cs-res" id="cs-res" style="display:none;"></div>
    </div>

    <?php
    $sources = CrossSellRepository::listSources();
    $allIds  = [];
    $recMap  = [];
    foreach ($sources as $s) {
        $allIds[] = (int)$s->kArtikel;
        $recMap[(int)$s->kArtikel] = array_slice(CrossSellRepository::getForSource((int)$s->kArtikel), 0, 5);
        $allIds = array_merge($allIds, $recMap[(int)$s->kArtikel]);
    }
    $info = CrossSellRepository::articleInfo($allIds);
    ?>
    <table class="table table-striped">
        <thead><tr><th>Kaynak ürün</th><th>Önerilenler</th><th style="width:1%"></th></tr></thead>
        <tbody>
        <?php if ($sources === []) { ?>
            <tr><td colspan="3"><em>Henüz öneri tanımlanmadı.</em></td></tr>
        <?php } ?>
        <?php foreach ($sources as $s) {
            $sid = (int)$s->kArtikel;
            $names = [];
            foreach ($recMap[$sid] as $rid) {
                $names[] = csH($info[$rid]['name'] ?? ('#' . $rid));
            }
            ?>
            <tr>
                <td><?php echo csH($info[$sid]['name'] ?? '(bulunamadı)'); ?> <small>#<?php echo $sid; ?></small></td>
                <td><?php echo implode(', ', $names); ?><?php echo (int)$s->nCount > 5 ? ' <small>+' . ((int)$s->nCount - 5) . '</small>' : ''; ?></td>
                <td style="white-space:nowrap"><a class="btn btn-sm btn-primary" href="<?php echo csH(csUrl(['cs_edit' => $sid])); ?>">Düzenle</a></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
</div>

<script>
(function () {
    var q = document.getElementById('cs-q'), res = document.getElementById('cs-res'), list = document.getElementById('cs-list');
    var editMode = <?php echo $edit > 0 ? 'true' : 'false'; ?>;
    var searchBase = <?php echo json_encode(csUrl(['cs_search' => 1])); ?>;
    var editBase = <?php echo json_encode(csUrl(['cs_edit' => '__ID__'])); ?>;
    var timer = null;

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function addRow(it) {
        if (list.querySelector('[data-id="' + it.id + '"]')) { return; }
        var row = document.createElement('div');
        row.className = 'cs-row';
        row.setAttribute('data-id', it.id);
        row.innerHTML = '<input type="hidden" name="rec[]" value="' + it.id + '">' +
            '<span class="cs-name">' + esc(it.name) + ' <small>#' + it.id + ' ' + esc(it.artNr || '') + '</small></span>' +
            '<button type="button" class="btn btn-sm btn-light cs-up">&uarr;</button>' +
            '<button type="button" class="btn btn-sm btn-light cs-down">&darr;</button>' +
            '<button type="button" class="btn btn-sm btn-danger cs-del">&times;</button>';
        list.appendChild(row);
    }

    if (list) {
        list.addEventListener('click', function (e) {
            var row = e.target.closest('.cs-row');
            if (!row) { return; }
            if (e.target.classList.contains('cs-del')) { row.remove(); }
            else if (e.target.classList.contains('cs-up') && row.previousElementSibling) { list.insertBefore(row, row.previousElementSibling); }
            else if (e.target.classList.contains('cs-down') && row.nextElementSibling) { list.insertBefore(row.nextElementSibling, row); }
        });
    }

    if (!q) { return; }
    q.addEventListener('input', function () {
        clearTimeout(timer);
        var v = q.value.trim();
        if (v.length < 2) { res.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(searchBase + '&q=' + encodeURIComponent(v), { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (t) {
                    var a = t.indexOf('__CS_START__'), b = t.indexOf('__CS_END__');
                    if (a < 0 || b < 0) { return; }
                    var items = JSON.parse(t.substring(a + 12, b)).items || [];
                    res.innerHTML = '';
                    items.forEach(function (it) {
                        var d = document.createElement('div');
                        d.innerHTML = esc(it.name) + ' <small>#' + it.id + ' ' + esc(it.artNr || '') + '</small>';
                        d.addEventListener('click', function () {
                            if (editMode) { addRow(it); res.style.display = 'none'; q.value = ''; }
                            else { window.location.href = editBase.replace('__ID__', it.id); }
                        });
                        res.appendChild(d);
                    });
                    res.style.display = items.length ? 'block' : 'none';
                });
        }, 250);
    });
})();
</script>
