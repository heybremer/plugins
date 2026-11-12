<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\MegaMenu\ImageStore;

require_once \dirname(__DIR__) . '/src/ImageStore.php';

if (!function_exists('mm_h')) {
    function mm_h(string $s): string
    {
        return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
    }
}

$db    = Shop::Container()->getDB();
$store = new ImageStore($db);
$msg   = [];
$err   = [];

try {
    $store->ensureSchema();
} catch (\Throwable $e) {
    $err[] = 'Tabelle konnte nicht angelegt werden: ' . $e->getMessage();
}

if (($_POST['mmAction'] ?? '') === 'save' && Form::validateToken()) {
    $ids     = \array_unique(\array_filter(\array_map('intval', (array)($_POST['ids'] ?? []))));
    $current = $store->all();
    $files   = $_FILES;
    $changed = 0;
    foreach ($ids as $id) {
        $values = [];
        foreach (['d', 'm'] as $v) {
            $val = $current[$id][$v] ?? '';
            if (!empty($_POST['clear_' . $v][$id])) {
                $val = '';
            } else {
                $upload = [
                    'tmp_name' => $files['file_' . $v]['tmp_name'][$id] ?? '',
                    'error'    => $files['file_' . $v]['error'][$id] ?? \UPLOAD_ERR_NO_FILE,
                    'size'     => $files['file_' . $v]['size'][$id] ?? 0,
                ];
                $e   = null;
                $url = $store->storeUpload($upload, $id, $v, $e);
                if ($e !== null) {
                    $err[] = 'Kategorie ' . $id . ' (' . ($v === 'm' ? 'Mobil' : 'Web') . '): ' . $e;
                }
                if ($url !== '') {
                    $val = $url;
                } elseif (isset($_POST['url_' . $v][$id])) {
                    $raw   = \trim((string)$_POST['url_' . $v][$id]);
                    $clean = ImageStore::cleanUrl($raw);
                    if ($raw !== '' && $clean === '') {
                        $err[] = 'Kategorie ' . $id . ': ungültige Bild-URL.';
                    } else {
                        $val = $clean;
                    }
                }
            }
            $values[$v] = $val;
        }
        if ($values['d'] !== ($current[$id]['d'] ?? '') || $values['m'] !== ($current[$id]['m'] ?? '')) {
            $store->save($id, $values['d'], $values['m']);
            $changed++;
        }
    }
    $msg[] = $changed . ' Kategorie(n) aktualisiert. Das Menü übernimmt die Änderung sofort.';
}

$langID = 1;
try {
    $lang   = $db->getSingleObject("SELECT kSprache FROM tsprache WHERE cShopStandard = 'Y' LIMIT 1");
    $langID = $lang !== null ? (int)$lang->kSprache : 1;
} catch (\Throwable $e) {
}
$rows = $db->getObjects(
    'SELECT k.kKategorie, k.kOberKategorie, k.lft, COALESCE(NULLIF(ks.cName, \'\'), k.cName) AS cName
       FROM tkategorie k
  LEFT JOIN tkategoriesprache ks ON ks.kKategorie = k.kKategorie AND ks.kSprache = :l
   ORDER BY k.lft',
    ['l' => $langID]
);
$byID = [];
foreach ($rows as $r) {
    $byID[(int)$r->kKategorie] = $r;
}
$depthOf = static function (int $id) use ($byID): int {
    $d = 1;
    while (isset($byID[$id]) && (int)$byID[$id]->kOberKategorie > 0 && $d < 10) {
        $id = (int)$byID[$id]->kOberKategorie;
        $d++;
    }

    return $d;
};
$groups = [];
$rootOf = [];
foreach ($rows as $r) {
    $id    = (int)$r->kKategorie;
    $depth = $depthOf($id);
    if ($depth > 3) {
        continue;
    }
    $root          = $depth === 1 ? $id : ($rootOf[(int)$r->kOberKategorie] ?? 0);
    $rootOf[$id]   = $root;
    if ($root === 0) {
        continue;
    }
    $groups[$root][] = ['id' => $id, 'name' => (string)$r->cName, 'depth' => $depth];
}

$current = $store->all();
$auto    = [];
try {
    $auto = (array)(Shop::Container()->getCache()->get(ImageStore::AUTO_CACHE) ?: []);
} catch (\Throwable $e) {
}
$thumb = static function (string $url, string $label = ''): string {
    if ($url === '') {
        return '<span class="mmd-thumb mmd-thumb--empty">' . mm_h($label !== '' ? $label : '—') . '</span>';
    }

    return '<a class="mmd-thumb" href="' . mm_h($url) . '" target="_blank" rel="noopener"><img src="' . mm_h($url) . '" alt="" loading="lazy"></a>';
};
$customCount = \count($current);
?>
<style>
.mmd { max-width: 1400px; }
.mmd-intro { color: #555; margin-bottom: 14px; }
.mmd-search { max-width: 360px; margin-bottom: 14px; }
.mmd-group { border: 1px solid #e3e6ea; border-radius: 8px; margin-bottom: 12px; background: #fff; }
.mmd-group > summary { cursor: pointer; padding: 12px 16px; font-weight: 700; font-size: 15px; list-style: none; display: flex; align-items: center; gap: 10px; }
.mmd-group > summary::-webkit-details-marker { display: none; }
.mmd-group > summary .mmd-count { font-weight: 500; color: #777; font-size: 12px; }
.mmd-group[open] > summary { border-bottom: 1px solid #eef0f3; }
.mmd-table { width: 100%; border-collapse: collapse; }
.mmd-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #777; padding: 8px 10px; text-align: left; background: #fafbfc; }
.mmd-table td { padding: 8px 10px; border-top: 1px solid #f1f2f4; vertical-align: middle; }
.mmd-name { font-weight: 600; }
.mmd-name small { display: block; color: #999; font-weight: 400; }
.mmd-d2 .mmd-name { padding-left: 18px; }
.mmd-d3 .mmd-name { padding-left: 36px; font-weight: 500; }
.mmd-thumb { display: inline-flex; width: 72px; height: 54px; border: 1px solid #e3e6ea; border-radius: 6px; background: #f6f7f9; align-items: center; justify-content: center; overflow: hidden; vertical-align: middle; }
.mmd-thumb img { width: 100%; height: 100%; object-fit: contain; }
.mmd-thumb--empty { color: #aaa; font-size: 11px; text-align: center; padding: 2px; }
.mmd-cell { display: flex; gap: 10px; align-items: center; }
.mmd-inputs { display: flex; flex-direction: column; gap: 4px; min-width: 220px; flex: 1; }
.mmd-inputs input[type=text] { font-size: 12px; }
.mmd-inputs input[type=file] { font-size: 11px; }
.mmd-inputs label { font-size: 11px; color: #a33; margin: 0; }
.mmd-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; background: #e8f5e9; color: #2e7d32; margin-left: 4px; }
.mmd-actions { padding: 10px 16px; text-align: right; background: #fafbfc; border-top: 1px solid #eef0f3; }
</style>
<div class="mmd">
    <?php foreach ($msg as $m): ?><div class="alert alert-success"><?= mm_h($m) ?></div><?php endforeach; ?>
    <?php foreach ($err as $m): ?><div class="alert alert-danger"><?= mm_h($m) ?></div><?php endforeach; ?>

    <p class="mmd-intro">
        Wählen Sie pro Kategorie ein eigenes Bild für das <strong>Web-Menü</strong> (Desktop) und das <strong>Mobil-Menü</strong>.
        Leer = automatisches Bild (Kategoriebild bzw. Serienbild). Ist nur ein Web-Bild gesetzt, wird es auch mobil verwendet.
        Bild per Upload (JPG, PNG, WEBP, AVIF, GIF, max. 5 MB) oder URL. Aktuell <strong><?= $customCount ?></strong> Kategorie(n) mit eigenem Bild.
    </p>
    <?php if ($auto === []): ?>
        <div class="alert alert-info">Die Vorschau der automatischen Bilder erscheint, nachdem der Shop einmal im Frontend aufgerufen wurde.</div>
    <?php endif; ?>
    <input type="search" class="form-control mmd-search" id="mmdSearch" placeholder="Kategorie suchen …">

    <?php $first = true; foreach ($groups as $rootID => $items): $rootName = $items[0]['name']; ?>
    <details class="mmd-group"<?= $first ? ' open' : '' ?>>
        <summary><span>▸ <?= mm_h($rootName) ?></span><span class="mmd-count"><?= \count($items) ?> Kategorien</span></summary>
        <form method="post" enctype="multipart/form-data">
            <?= Form::getTokenInput() ?>
            <input type="hidden" name="mmAction" value="save">
            <table class="mmd-table">
                <thead><tr><th>Kategorie</th><th>Automatisch</th><th>Web (Desktop)</th><th>Mobil</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): $id = $it['id']; $c = $current[$id] ?? ['d' => '', 'm' => '']; ?>
                    <tr class="mmd-d<?= $it['depth'] ?>" data-name="<?= mm_h(\mb_strtolower($it['name'])) ?>">
                        <td class="mmd-name"><?= mm_h($it['name']) ?><small>ID <?= $id ?></small>
                            <input type="hidden" name="ids[]" value="<?= $id ?>"></td>
                        <td><?= $thumb((string)($auto[$id] ?? ''), 'kein Bild') ?></td>
                        <?php foreach (['d' => 'Web', 'm' => 'Mobil'] as $v => $label): ?>
                        <td>
                            <div class="mmd-cell">
                                <?= $thumb($c[$v], $v === 'm' && $c['d'] !== '' ? 'wie Web' : 'auto') ?>
                                <div class="mmd-inputs">
                                    <input type="text" class="form-control form-control-sm" name="url_<?= $v ?>[<?= $id ?>]" value="<?= mm_h($c[$v]) ?>" placeholder="Bild-URL (optional)">
                                    <input type="file" name="file_<?= $v ?>[<?= $id ?>]" accept="image/jpeg,image/png,image/webp,image/avif,image/gif">
                                    <?php if ($c[$v] !== ''): ?>
                                        <label><input type="checkbox" name="clear_<?= $v ?>[<?= $id ?>]" value="1"> <?= $label ?>-Bild entfernen</label>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mmd-actions"><button type="submit" class="btn btn-primary">„<?= mm_h($rootName) ?>“ speichern</button></div>
        </form>
    </details>
    <?php $first = false; endforeach; ?>
</div>
<script>
(function () {
  var input = document.getElementById('mmdSearch');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    document.querySelectorAll('.mmd-group').forEach(function (g) {
      var hit = 0;
      g.querySelectorAll('tbody tr').forEach(function (tr) {
        var ok = !q || tr.getAttribute('data-name').indexOf(q) !== -1;
        tr.style.display = ok ? '' : 'none';
        if (ok) hit++;
      });
      g.style.display = hit ? '' : 'none';
      if (q && hit) g.open = true;
    });
  });
  document.querySelectorAll('.mmd-table tbody tr').forEach(function (tr) {
    ['input', 'change'].forEach(function (ev) {
      tr.addEventListener(ev, function () { tr.classList.add('mmd-dirty'); });
    });
  });
  document.querySelectorAll('.mmd form').forEach(function (form) {
    form.addEventListener('submit', function () {
      form.querySelectorAll('tbody tr:not(.mmd-dirty) input').forEach(function (i) { i.disabled = true; });
    });
  });
  document.querySelectorAll('.mmd-inputs input[type=file]').forEach(function (f) {
    f.addEventListener('change', function () {
      var file = f.files && f.files[0];
      var img = f.closest('.mmd-cell').querySelector('.mmd-thumb');
      if (!file || !img) return;
      var url = URL.createObjectURL(file);
      img.outerHTML = '<span class="mmd-thumb"><img src="' + url + '" alt=""></span>';
    });
  });
})();
</script>
