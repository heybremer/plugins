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
        unset($q['cs_edit'], $q['cs_editcat'], $q['cs_search'], $q['cs_type'], $q['q']);
        foreach ($set as $k => $v) {
            $q[$k] = $v;
        }

        return (string)($p['path'] ?? '') . ($q !== [] ? '?' . http_build_query($q) : '');
    }
}

// AJAX: Artikel- bzw. Kategoriesuche
if (isset($_GET['cs_search'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $found = (string)($_GET['cs_type'] ?? '') === 'cat'
        ? CrossSellRepository::searchCategories((string)($_GET['q'] ?? ''))
        : CrossSellRepository::search((string)($_GET['q'] ?? ''));
    $json = json_encode(['items' => $found], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '__CS_START__' . ($json !== false ? $json : '{"items":[]}') . '__CS_END__';
    exit;
}

$message  = '';
$editArt  = (int)($_GET['cs_edit'] ?? 0);
$editCat  = (int)($_GET['cs_editcat'] ?? 0);

if (Form::validateToken()) {
    if (!empty($_POST['cs_save'])) {
        $recs = CrossSellRepository::ids((array)($_POST['rec'] ?? []));
        $cat  = (int)($_POST['cs_cat'] ?? 0);
        $src  = (int)($_POST['cs_src'] ?? 0);
        if ($cat > 0) {
            CrossSellRepository::replaceForCategory($cat, $recs);
            $message = 'Kaydedildi.';
            $editCat = $cat;
        } elseif ($src > 0) {
            CrossSellRepository::replaceForSource($src, $recs, !empty($_POST['cs_both']));
            $message = 'Kaydedildi.';
            $editArt = $src;
        }
    } elseif (!empty($_POST['cs_delete'])) {
        CrossSellRepository::deleteSource((int)$_POST['cs_delete']);
        $message = 'Silindi.';
        $editArt = 0;
    } elseif (!empty($_POST['cs_delete_cat'])) {
        CrossSellRepository::deleteCategory((int)$_POST['cs_delete_cat']);
        $message = 'Silindi.';
        $editCat = 0;
    }
}

$token  = Form::getTokenInput();
$isEdit = $editArt > 0 || $editCat > 0;
?>
<style>
    .cs-admin .cs-row{display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;border:1px solid #ddd;border-radius:4px;margin-bottom:.35rem;background:#fff}
    .cs-admin .cs-row .cs-name{flex:1}
    .cs-admin .cs-res{max-height:260px;overflow:auto;border:1px solid #ddd;border-radius:4px;margin-top:.4rem}
    .cs-admin .cs-res div{padding:.4rem .6rem;cursor:pointer;border-bottom:1px solid #eee}
    .cs-admin .cs-res div:hover{background:#f2f6ff}
    .cs-admin small{color:#777}
    .cs-admin h4{margin-top:2rem}
</style>
<div class="cs-admin" style="padding:10px 0;">
<?php if ($message !== '') { ?>
    <div class="alert alert-success"><?php echo csH($message); ?></div>
<?php } ?>

<?php if ($isEdit) {
    $isCat = $editCat > 0;
    if ($isCat) {
        $recIds = CrossSellRepository::getForCategory($editCat);
        $paths  = CrossSellRepository::categoryPaths([$editCat]);
        $title  = 'Kategori: ' . ($paths[$editCat] ?? ('#' . $editCat));
        $info   = CrossSellRepository::articleInfo($recIds);
        $affected = CrossSellRepository::countCategoryArticles([$editCat])[$editCat] ?? 0;
        $hint   = 'Bu kategorideki (ve alt kategorilerdeki) TÜM ürünlerde aşağıdaki ürünler önerilir. Etkilenen ürün sayısı: ' . $affected . '. Tek tek ürün için tanımlanan öneriler önce gösterilir.';
    } else {
        $recIds = CrossSellRepository::getForSource($editArt);
        $info   = CrossSellRepository::articleInfo(array_merge([$editArt], $recIds));
        $srcInf = $info[$editArt] ?? ['name' => '(bulunamadı)', 'artNr' => ''];
        $title  = 'Kaynak ürün: ' . $srcInf['name'] . ' #' . $editArt . ' ' . $srcInf['artNr'];
        $hint   = 'Bu ürün sayfasında / sepette bu ürün varken aşağıdaki ürünler önerilir (sıra önemlidir).';
    }
    $actionUrl = $isCat ? csUrl(['cs_editcat' => $editCat]) : csUrl(['cs_edit' => $editArt]);
    ?>
    <p><a href="<?php echo csH(csUrl()); ?>">&larr; Tüm öneriler</a></p>
    <h3><?php echo csH($title); ?></h3>
    <p><?php echo csH($hint); ?></p>

    <form method="post" action="<?php echo csH($actionUrl); ?>" id="cs-form">
        <?php echo $token; ?>
        <?php if ($isCat) { ?>
            <input type="hidden" name="cs_cat" value="<?php echo $editCat; ?>">
        <?php } else { ?>
            <input type="hidden" name="cs_src" value="<?php echo $editArt; ?>">
        <?php } ?>
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

        <?php if (!$isCat) { ?>
            <div class="checkbox" style="margin:.75rem 0;">
                <label><input type="checkbox" name="cs_both" value="1"> Karşılıklı da ekle (önerilen ürün sayfasında bu ürün de önerilsin)</label>
            </div>
        <?php } ?>
        <button type="submit" name="cs_save" value="1" class="btn btn-primary">Kaydet</button>
        <?php if ($isCat) { ?>
            <button type="submit" name="cs_delete_cat" value="<?php echo $editCat; ?>" class="btn btn-danger"
                    onclick="return confirm('Bu kural silinirse <?php echo (int)$affected; ?> üründeki öneri kalkacak. Devam edilsin mi?');">Tümünü sil (<?php echo (int)$affected; ?> ürün etkilenir)</button>
        <?php } else { ?>
            <button type="submit" name="cs_delete" value="<?php echo $editArt; ?>" class="btn btn-danger"
                    onclick="return confirm('Bu ürünün tüm önerileri silinsin mi?');">Tümünü sil</button>
        <?php } ?>
    </form>
<?php } else { ?>
    <h3>Ürün önerileri</h3>

    <h4 style="margin-top:1rem;">Kategori bazlı</h4>
    <p>Bir kategori seçin: o kategorideki (ve alt kategorilerdeki) tüm ürünlerde seçtiğiniz tamamlayıcı ürünler önerilir.</p>
    <div class="form-group">
        <label>Kategori ara (ad veya ID)</label>
        <input type="text" class="form-control cs-search" data-type="cat" placeholder="Örn. E-Serie" autocomplete="off">
        <div class="cs-res" style="display:none;"></div>
    </div>
    <?php
    $catSources = CrossSellRepository::listCategorySources();
    $catIds     = [];
    $catRecMap  = [];
    $allArt     = [];
    foreach ($catSources as $s) {
        $cid                = (int)$s->kKategorie;
        $catIds[]           = $cid;
        $catRecMap[$cid]    = array_slice(CrossSellRepository::getForCategory($cid), 0, 5);
        $allArt             = array_merge($allArt, $catRecMap[$cid]);
    }
    $catPaths = CrossSellRepository::categoryPaths($catIds);
    $catCount = CrossSellRepository::countCategoryArticles($catIds);
    $catInfo  = CrossSellRepository::articleInfo($allArt);
    ?>
    <table class="table table-striped">
        <thead><tr><th>Kategori</th><th>Önerilenler</th><th>Etkilenen ürün</th><th style="width:1%"></th></tr></thead>
        <tbody>
        <?php if ($catSources === []) { ?>
            <tr><td colspan="4"><em>Henüz kategori kuralı yok.</em></td></tr>
        <?php } ?>
        <?php foreach ($catSources as $s) {
            $cid   = (int)$s->kKategorie;
            $names = [];
            foreach ($catRecMap[$cid] as $rid) {
                $names[] = csH($catInfo[$rid]['name'] ?? ('#' . $rid));
            }
            ?>
            <tr>
                <td><?php echo csH($catPaths[$cid] ?? ('#' . $cid)); ?> <small>#<?php echo $cid; ?></small></td>
                <td><?php echo implode(', ', $names); ?><?php echo (int)$s->nCount > 5 ? ' <small>+' . ((int)$s->nCount - 5) . '</small>' : ''; ?></td>
                <td><?php echo (int)($catCount[$cid] ?? 0); ?></td>
                <td style="white-space:nowrap"><a class="btn btn-sm btn-primary" href="<?php echo csH(csUrl(['cs_editcat' => $cid])); ?>">Düzenle</a></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <h4>Ürün bazlı</h4>
    <p>Tek bir ürün için öneri tanımlayın (kategori kuralından önce gösterilir).</p>
    <div class="form-group">
        <label>Kaynak ürün ara (ad, ürün no veya ID)</label>
        <input type="text" class="form-control cs-search" data-type="art" placeholder="Aramak için yazın..." autocomplete="off">
        <div class="cs-res" style="display:none;"></div>
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
            <tr><td colspan="3"><em>Henüz ürün kuralı yok.</em></td></tr>
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
    var editMode = <?php echo $isEdit ? 'true' : 'false'; ?>;
    var list = document.getElementById('cs-list');
    var searchBase = <?php echo json_encode(csUrl(['cs_search' => 1])); ?>;
    var editArtBase = <?php echo json_encode(csUrl(['cs_edit' => '__ID__'])); ?>;
    var editCatBase = <?php echo json_encode(csUrl(['cs_editcat' => '__ID__'])); ?>;

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

    function wire(input, type, res) {
        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var v = input.value.trim();
            if (v.length < 2) { res.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch(searchBase + '&cs_type=' + type + '&q=' + encodeURIComponent(v), { credentials: 'same-origin' })
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
                                if (editMode) { addRow(it); res.style.display = 'none'; input.value = ''; }
                                else { window.location.href = (type === 'cat' ? editCatBase : editArtBase).replace('__ID__', it.id); }
                            });
                            res.appendChild(d);
                        });
                        res.style.display = items.length ? 'block' : 'none';
                    });
            }, 250);
        });
    }

    var single = document.getElementById('cs-q');
    if (single) { wire(single, 'art', document.getElementById('cs-res')); }
    document.querySelectorAll('.cs-search').forEach(function (inp) {
        wire(inp, inp.getAttribute('data-type'), inp.parentElement.querySelector('.cs-res'));
    });
})();
</script>
