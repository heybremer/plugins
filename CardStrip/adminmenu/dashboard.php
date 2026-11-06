<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use Plugin\CardStrip\CardRepository;

require_once dirname(__DIR__) . '/src/CardRepository.php';

CardRepository::ensure();

if (!function_exists('csH')) {
    function csH(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    function csUrl(array $set = []): string
    {
        $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''));
        parse_str((string)($p['query'] ?? ''), $q);
        unset($q['cs_edit'], $q['cs_new']);
        foreach ($set as $k => $v) {
            $q[$k] = $v;
        }

        return (string)($p['path'] ?? '') . ($q !== [] ? '?' . http_build_query($q) : '');
    }

    function csDate(string $v): ?string
    {
        $v = trim($v);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    /** @param array<string,mixed> $files einzelne Datei aus $_FILES */
    function csHasFile(array $f): bool
    {
        return !empty($f['name']) && (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }
}

$message = '';
$error   = '';
$editId  = (int)($_GET['cs_edit'] ?? 0);
$isNew   = !empty($_GET['cs_new']);

if (Form::validateToken()) {
    if (!empty($_POST['cs_save'])) {
        $variant = in_array((string)($_POST['cs_variant'] ?? 'A'), ['A', 'B', 'C'], true) ? (string)$_POST['cs_variant'] : 'A';
        $bg      = trim((string)($_POST['cs_bg'] ?? ''));
        $data    = [
            'cVariant'  => $variant,
            'cTitle'    => mb_substr(trim((string)($_POST['cs_title'] ?? '')), 0, 255),
            'cSubtitle' => mb_substr(trim((string)($_POST['cs_subtitle'] ?? '')), 0, 255),
            'cBgColor'  => preg_match('/^#[0-9a-fA-F]{3,8}$/', $bg) ? $bg : '#197149',
            'cTextTone' => (string)($_POST['cs_tone'] ?? 'light') === 'dark' ? 'dark' : 'light',
            'cImage'    => mb_substr(trim((string)($_POST['cs_image'] ?? '')), 0, 500),
            'cBadge'    => mb_substr(trim((string)($_POST['cs_badge'] ?? '')), 0, 60),
            'cUrl'      => mb_substr(trim((string)($_POST['cs_url'] ?? '')), 0, 500),
            'nActive'   => !empty($_POST['cs_active']) ? 1 : 0,
            'nSort'     => (int)($_POST['cs_sort'] ?? 0),
            'dStart'    => csDate((string)($_POST['cs_start'] ?? '')),
            'dEnd'      => csDate((string)($_POST['cs_end'] ?? '')),
        ];
        $uploadError = '';
        $items       = [];
        try {
            if (!empty($_FILES['cs_image_file']) && csHasFile($_FILES['cs_image_file'])) {
                $data['cImage'] = CardRepository::saveUpload($_FILES['cs_image_file']);
            }
            for ($i = 0; $i < 4; $i++) {
                $img = mb_substr(trim((string)($_POST['cs_item_img'][$i] ?? '')), 0, 500);
                if (!empty($_FILES['cs_item_file']['name'][$i]) && (int)$_FILES['cs_item_file']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $img = CardRepository::saveUpload([
                        'name'     => (string)$_FILES['cs_item_file']['name'][$i],
                        'tmp_name' => (string)$_FILES['cs_item_file']['tmp_name'][$i],
                        'error'    => (int)$_FILES['cs_item_file']['error'][$i],
                        'size'     => (int)$_FILES['cs_item_file']['size'][$i],
                    ]);
                }
                $items[] = [
                    'image' => $img,
                    'badge' => mb_substr(trim((string)($_POST['cs_item_badge'][$i] ?? '')), 0, 40),
                    'alt'   => mb_substr(trim((string)($_POST['cs_item_alt'][$i] ?? '')), 0, 120),
                ];
            }
        } catch (\Throwable $e) {
            $uploadError = $e->getMessage();
        }
        $data['cItems'] = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        $id             = (int)($_POST['cs_id'] ?? 0);
        if ($uploadError !== '') {
            $error  = 'Görsel hatası: ' . $uploadError . ' (kayıt yapılmadı)';
            $editId = $id;
            $isNew  = $id === 0;
        } elseif ($data['cTitle'] === '' && $data['cImage'] === '' && $variant !== 'C') {
            $error  = 'En az başlık veya görsel girin.';
            $editId = $id;
            $isNew  = $id === 0;
        } else {
            $editId  = CardRepository::save($id, $data);
            $isNew   = false;
            $message = 'Kaydedildi.';
        }
    } elseif (!empty($_POST['cs_delete'])) {
        CardRepository::delete((int)$_POST['cs_delete']);
        $message = 'Silindi.';
        $editId  = 0;
    } elseif (isset($_POST['cs_toggle'])) {
        CardRepository::setActive((int)$_POST['cs_toggle'], !empty($_POST['cs_to']));
        $message = 'Güncellendi.';
    }
}

$token   = Form::getTokenInput();
$today   = date('Y-m-d');
$variant = [
    'A' => 'Tip A – düz renk + büyük görsel',
    'B' => 'Tip B – tam kapak görseli',
    'C' => 'Tip C – 2x2 ürün grid',
];
?>
<style>
    .cs-admin small{color:#777}
    .cs-admin .cs-grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 1.25rem}
    .cs-admin .cs-status{display:inline-block;padding:.15rem .55rem;border-radius:999px;font-size:.8rem;font-weight:700}
    .cs-admin .cs-on{background:#dff5e6;color:#14663a}
    .cs-admin .cs-off{background:#eee;color:#666}
    .cs-admin .cs-wait{background:#fff4d6;color:#8a6100}
    .cs-admin .cs-item{border:1px solid #ddd;border-radius:6px;padding:.6rem;background:#fafafa;margin-bottom:.6rem}
    .cs-admin .cs-sw{display:inline-block;width:22px;height:22px;border-radius:5px;border:1px solid #ccc;vertical-align:middle}
    .cs-admin .cs-thumb{max-height:60px;border-radius:6px;margin-top:.3rem;background:#eee}
</style>
<div class="cs-admin" style="padding:10px 0;">
<?php if ($message !== '') { ?>
    <div class="alert alert-success"><?php echo csH($message); ?></div>
<?php } ?>
<?php if ($error !== '') { ?>
    <div class="alert alert-danger"><?php echo csH($error); ?></div>
<?php } ?>

<?php if ($editId > 0 || $isNew) {
    $row = $editId > 0 ? CardRepository::get($editId) : null;
    if ($editId > 0 && $row === null) {
        echo '<div class="alert alert-warning">Kayıt bulunamadı.</div>';
    }
    $cur    = (string)($row->cVariant ?? 'A');
    $bg     = (string)($row->cBgColor ?? '#197149');
    $tone   = (string)($row->cTextTone ?? 'light');
    $active = $row === null ? true : (int)$row->nActive === 1;
    $items  = json_decode((string)($row->cItems ?? ''), true);
    $items  = is_array($items) ? $items : [];
    ?>
    <p><a href="<?php echo csH(csUrl()); ?>">&larr; Tüm kartlar</a></p>
    <h3><?php echo $row !== null ? 'Kartı düzenle' : 'Yeni kart'; ?></h3>
    <p>Kartlar 285x456 px'dir. Tip A: renkli arka plan + görsel. Tip B: görsel tüm kartı kaplar. Tip C: 4 küçük ürün kutusu (rozetli).</p>

    <form method="post" enctype="multipart/form-data" action="<?php echo csH(csUrl($editId > 0 ? ['cs_edit' => $editId] : ['cs_new' => 1])); ?>">
        <?php echo $token; ?>
        <input type="hidden" name="cs_id" value="<?php echo (int)$editId; ?>">

        <div class="cs-grid2">
            <div class="form-group">
                <label><strong>Kart tipi</strong></label>
                <select class="form-control" name="cs_variant" id="cs-variant">
                    <?php foreach ($variant as $k => $lbl) { ?>
                        <option value="<?php echo $k; ?>" <?php echo $cur === $k ? 'selected' : ''; ?>><?php echo csH($lbl); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Başlık <small>(28px, kalın)</small></label>
                <input type="text" class="form-control" name="cs_title" maxlength="255" value="<?php echo csH((string)($row->cTitle ?? '')); ?>">
            </div>
            <div class="form-group">
                <label>Alt başlık <small>(opsiyonel, 15px)</small></label>
                <input type="text" class="form-control" name="cs_subtitle" maxlength="255" value="<?php echo csH((string)($row->cSubtitle ?? '')); ?>">
            </div>
        </div>

        <div class="cs-grid2">
            <div class="form-group">
                <label>Arka plan rengi <small>(Tip A ve C)</small></label>
                <input type="color" class="form-control" name="cs_bg" value="<?php echo csH(preg_match('/^#[0-9a-fA-F]{6}$/', $bg) ? $bg : '#197149'); ?>" style="height:38px;padding:2px">
            </div>
            <div class="form-group">
                <label>Yazı rengi</label>
                <select class="form-control" name="cs_tone">
                    <option value="light" <?php echo $tone === 'light' ? 'selected' : ''; ?>>Beyaz (koyu zemin için)</option>
                    <option value="dark" <?php echo $tone === 'dark' ? 'selected' : ''; ?>>Koyu (açık zemin için)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Etiket <small>(sol altta, örn. "Gesponsert")</small></label>
                <input type="text" class="form-control" name="cs_badge" maxlength="60" value="<?php echo csH((string)($row->cBadge ?? '')); ?>">
            </div>
            <div class="form-group">
                <label>Link</label>
                <input type="text" class="form-control" name="cs_url" maxlength="500" value="<?php echo csH((string)($row->cUrl ?? '')); ?>" placeholder="https://...">
            </div>
        </div>

        <div class="form-group" id="cs-main-image">
            <label><strong>Görsel</strong> <small>(Tip A ve B – yükle veya URL gir)</small></label>
            <input type="file" class="form-control" name="cs_image_file" accept="image/jpeg,image/png,image/webp">
            <input type="text" class="form-control" name="cs_image" maxlength="500" style="margin-top:.4rem" placeholder="https://... (yükleme yoksa)" value="<?php echo csH((string)($row->cImage ?? '')); ?>">
            <?php if (!empty($row->cImage)) { ?><img class="cs-thumb" src="<?php echo csH((string)$row->cImage); ?>" alt=""><?php } ?>
        </div>

        <div id="cs-items">
            <label><strong>Tip C – 4 ürün kutusu</strong> <small>(görsel + kırmızı rozet, örn. "%25 Rabatt")</small></label>
            <?php for ($i = 0; $i < 4; $i++) {
                $it = $items[$i] ?? [];
                ?>
                <div class="cs-item">
                    <div class="cs-grid2">
                        <div class="form-group">
                            <small>Kutu <?php echo $i + 1; ?> görseli</small>
                            <input type="file" class="form-control" name="cs_item_file[<?php echo $i; ?>]" accept="image/jpeg,image/png,image/webp">
                            <input type="text" class="form-control" name="cs_item_img[<?php echo $i; ?>]" style="margin-top:.3rem" placeholder="https://..." value="<?php echo csH((string)($it['image'] ?? '')); ?>">
                            <?php if (!empty($it['image'])) { ?><img class="cs-thumb" src="<?php echo csH((string)$it['image']); ?>" alt=""><?php } ?>
                        </div>
                        <div class="form-group">
                            <small>Rozet</small>
                            <input type="text" class="form-control" name="cs_item_badge[<?php echo $i; ?>]" maxlength="40" placeholder="%25 Rabatt" value="<?php echo csH((string)($it['badge'] ?? '')); ?>">
                            <small>Alt metin (erişilebilirlik)</small>
                            <input type="text" class="form-control" name="cs_item_alt[<?php echo $i; ?>]" maxlength="120" value="<?php echo csH((string)($it['alt'] ?? '')); ?>">
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div class="cs-grid2">
            <div class="form-group">
                <label>Sıra <small>(küçük olan önce)</small></label>
                <input type="number" class="form-control" name="cs_sort" value="<?php echo (int)($row->nSort ?? 0); ?>">
            </div>
            <div class="form-group">
                <label>Başlangıç tarihi <small>(opsiyonel)</small></label>
                <input type="date" class="form-control" name="cs_start" value="<?php echo csH((string)($row->dStart ?? '')); ?>">
            </div>
            <div class="form-group">
                <label>Bitiş tarihi <small>(opsiyonel)</small></label>
                <input type="date" class="form-control" name="cs_end" value="<?php echo csH((string)($row->dEnd ?? '')); ?>">
            </div>
        </div>

        <div class="checkbox" style="margin:.5rem 0 1rem;">
            <label><input type="checkbox" name="cs_active" value="1" <?php echo $active ? 'checked' : ''; ?>> Aktif (anasayfada göster)</label>
        </div>

        <button type="submit" name="cs_save" value="1" class="btn btn-primary">Kaydet</button>
        <?php if ($row !== null) { ?>
            <button type="submit" name="cs_delete" value="<?php echo (int)$editId; ?>" class="btn btn-danger"
                    onclick="return confirm('Bu kart silinsin mi?');">Sil</button>
        <?php } ?>
    </form>
    <script>
    (function () {
        var sel = document.getElementById('cs-variant');
        var main = document.getElementById('cs-main-image');
        var items = document.getElementById('cs-items');
        function sync() {
            var v = sel.value;
            main.style.display = v === 'C' ? 'none' : '';
            items.style.display = v === 'C' ? '' : 'none';
        }
        sel.addEventListener('change', sync);
        sync();
    })();
    </script>
<?php } else {
    $rows = CardRepository::all();
    ?>
    <h3>Kart şeridi</h3>
    <p>Aktif kartlar anasayfada yatay kaydırılabilir şerit olarak gösterilir (sıraya göre).</p>
    <p><a class="btn btn-primary" href="<?php echo csH(csUrl(['cs_new' => 1])); ?>">+ Yeni kart</a></p>

    <table class="table table-striped">
        <thead><tr><th>Sıra</th><th>Tip</th><th>Başlık</th><th>Renk</th><th>Tarih</th><th>Durum</th><th style="width:1%"></th></tr></thead>
        <tbody>
        <?php if ($rows === []) { ?>
            <tr><td colspan="7"><em>Henüz kart yok.</em></td></tr>
        <?php } ?>
        <?php foreach ($rows as $r) {
            $id   = (int)$r->kID;
            $on   = (int)$r->nActive === 1;
            $s    = (string)($r->dStart ?? '');
            $e    = (string)($r->dEnd ?? '');
            $live = $on && ($s === '' || $s <= $today) && ($e === '' || $e >= $today);
            if ($live) {
                $st = '<span class="cs-status cs-on">Yayında</span>';
            } elseif ($on && $s !== '' && $s > $today) {
                $st = '<span class="cs-status cs-wait">Planlandı</span>';
            } elseif ($on) {
                $st = '<span class="cs-status cs-off">Süresi doldu</span>';
            } else {
                $st = '<span class="cs-status cs-off">Pasif</span>';
            }
            ?>
            <tr>
                <td><?php echo (int)$r->nSort; ?></td>
                <td><?php echo csH((string)$r->cVariant); ?></td>
                <td><?php echo csH((string)$r->cTitle) !== '' ? csH((string)$r->cTitle) : '<small>(başlıksız)</small>'; ?></td>
                <td><span class="cs-sw" style="background:<?php echo csH((string)$r->cBgColor); ?>"></span></td>
                <td><small><?php echo csH($s !== '' ? $s : '…'); ?> – <?php echo csH($e !== '' ? $e : '…'); ?></small></td>
                <td><?php echo $st; ?></td>
                <td style="white-space:nowrap">
                    <form method="post" action="<?php echo csH(csUrl()); ?>" style="display:inline">
                        <?php echo $token; ?>
                        <input type="hidden" name="cs_toggle" value="<?php echo $id; ?>">
                        <input type="hidden" name="cs_to" value="<?php echo $on ? '' : '1'; ?>">
                        <button type="submit" class="btn btn-sm btn-light"><?php echo $on ? 'Pasif yap' : 'Aktif yap'; ?></button>
                    </form>
                    <a class="btn btn-sm btn-primary" href="<?php echo csH(csUrl(['cs_edit' => $id])); ?>">Düzenle</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
<?php } ?>
</div>
