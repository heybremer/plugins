<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;

use Plugin\SideLeftFiltre\src\SideLeftFiltreDatabase;

$db = Shop::Container()->getDB();
$pluginName = 'SideLeftFiltre';

$message = '';

SideLeftFiltreDatabase::ensureAll();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !Form::validateToken()) {
    $message = 'Token hatası: Form oturumu geçersiz. Sayfayı yenileyip tekrar kaydedin.';
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['slfSave'])) {
            $active   = ((int)($_POST['slfActive']   ?? 0) === 1) ? 1 : 0;
            $csv      = (string)($_POST['slfOrderCsv'] ?? '');
            $openCsv  = (string)($_POST['slfOpenCsv']  ?? '');
            $closeCsv = (string)($_POST['slfCloseCsv'] ?? '');

            $existing = $db->getSingleObject(
                'SELECT kID FROM twomosi_sideleftfiltre_settings ORDER BY dUpdated DESC, kID DESC LIMIT 1'
            );

            if ($existing !== null) {
                $db->executeQueryPrepared(
                    'UPDATE twomosi_sideleftfiltre_settings
                        SET nActive = :a, cOrderCsv = :csv, cOpenCsv = :o, cCloseCsv = :c, dUpdated = NOW()
                      WHERE kID = :id',
                    ['a' => $active, 'csv' => $csv, 'o' => $openCsv, 'c' => $closeCsv, 'id' => (int)$existing->kID]
                );
            } else {
                $db->executeQueryPrepared(
                    'INSERT INTO twomosi_sideleftfiltre_settings (nActive, cOrderCsv, cOpenCsv, cCloseCsv, dUpdated)
                     VALUES (:a, :csv, :o, :c, NOW())',
                    ['a' => $active, 'csv' => $csv, 'o' => $openCsv, 'c' => $closeCsv]
                );
            }
            $message = 'Kaydedildi.';
        }
    } catch (\Throwable $e) {
        $message = 'İşlem başarısız: ' . $e->getMessage();
    }
}

$cfg = SideLeftFiltreDatabase::getConfig();
$active = $cfg['active'] ? 1 : 0;
$orderCsv = \implode("\n", $cfg['order']);
$openCsv = \implode("\n", $cfg['open']);
$closeCsv = \implode("\n", $cfg['close']);

function slf_h(string $s): string
{
    return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <div>
                    <h1 class="mb-1">SideLeftFiltre</h1>
                    <div class="text-muted">Sidepanel left filtre bloklarını ID’ye göre sırala.</div>
                </div>
            </div>
            <?php if ($message !== ''): ?>
                <div class="alert alert-info"><?php echo slf_h($message); ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12 col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <strong>Sıralama Ayarları</strong>
                            <span class="badge badge-<?php echo $active === 1 ? 'success' : 'secondary'; ?>">
                                <?php echo $active === 1 ? 'Aktif' : 'Pasif'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <?php echo Form::getTokenInput(); ?>
                                <input type="hidden" name="slfSave" value="1">

                                <div class="form-group mb-3">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="slfActive" value="0">
                                        <input type="checkbox" class="custom-control-input" id="slfActive" name="slfActive" value="1" <?php echo $active === 1 ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="slfActive"><strong>Aktif</strong></label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Pasif yaparsan hiçbir DOM değişikliği yapılmaz.
                                    </small>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="slfOrderCsv"><strong>Filtre ID sırası</strong></label>
                                    <textarea class="form-control font-monospace" id="slfOrderCsv" name="slfOrderCsv" rows="12" placeholder="fahrzeug-model&#10;price&#10;manufacturer"><?php echo slf_h($orderCsv); ?></textarea>
                                    <small class="form-text text-muted">
                                        Her satıra bir filtre <code>id</code> yaz. Listede olmayan filtreler otomatik olarak en sona bırakılır.
                                        İstersen aynı satırda virgülle de ayırabilirsin: <code>fahrzeug-model, price, manufacturer</code>
                                    </small>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="slfOpenCsv"><strong>Default açık (ID listesi)</strong></label>
                                            <textarea class="form-control font-monospace" id="slfOpenCsv" name="slfOpenCsv" rows="6" placeholder="fahrzeug-marke"><?php echo slf_h($openCsv); ?></textarea>
                                            <small class="form-text text-muted">
                                                Buraya yazdıkların açık zorlanır (<code>aria-expanded=true</code>).
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="slfCloseCsv"><strong>Default kapalı (ID listesi)</strong></label>
                                            <textarea class="form-control font-monospace" id="slfCloseCsv" name="slfCloseCsv" rows="6" placeholder="price"><?php echo slf_h($closeCsv); ?></textarea>
                                            <small class="form-text text-muted">
                                                Buraya yazdıkların kapalı zorlanır (<code>aria-expanded=false</code>).
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">Kaydet</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Nasıl bulurum?</strong></div>
                        <div class="card-body">
                            <ol class="mb-2">
                                <li>Kategori/Arama sayfasını aç.</li>
                                <li>Tarayıcıda DevTools → Inspect.</li>
                                <li>Sol filtrede ilgili bloğu seç ve <code>id</code> değerini kopyala.</li>
                            </ol>
                            <div class="alert alert-light mb-0">
                                <strong>İpucu:</strong> Eğer hiçbir şey değişmiyorsa, filtre blokları <code>#sidepanel_left</code> altında direkt child değil olabilir.
                                O durumda bir sonraki adımda selector’ı da ayarlanabilir yaparız.
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><strong>Örnek sıra</strong></div>
                        <div class="card-body">
                            <pre class="mb-0" style="white-space: pre-wrap;">fahrzeug-model
price
manufacturer
availability</pre>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

