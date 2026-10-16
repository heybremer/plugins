<?php
declare(strict_types=1);

use JTL\Shop;
use JTL\Helpers\Form;
require_once \dirname(__DIR__) . '/src/BrandListLink.php';

use Plugin\MarkeFiltre\BrandListLink;

$db = Shop::Container()->getDB();
$pluginName = 'MarkeFiltre';
$pluginID = 0;
try {
    // Plugin-ID direkt bul (bazı admin context'lerde $oPlugin global olmayabilir)
    $row = $db->getSingleObject("SELECT kPlugin FROM tplugin WHERE cPluginID = :pid LIMIT 1", ['pid' => $pluginName]);
    $pluginID = isset($row->kPlugin) ? (int)$row->kPlugin : 0;
} catch (\Throwable $e) {
    $pluginID = 0;
}

$message = '';

// Ensure settings table exists
try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_markefiltre_settings (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            dUpdated DATETIME NOT NULL,
            cSelector VARCHAR(255) NOT NULL DEFAULT '',
            cMode VARCHAR(16) NOT NULL DEFAULT '',
            nRefreshDow INT NOT NULL DEFAULT 0,
            nRefreshHour INT NOT NULL DEFAULT 3,
            bShowPKW TINYINT(1) NOT NULL DEFAULT 1,
            bShowWohnmobil TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $mfSettingsAlters = [
        'nRefreshDow'     => 'INT NOT NULL DEFAULT 0',
        'nRefreshHour'    => 'INT NOT NULL DEFAULT 3',
        'bShowPKW'        => 'TINYINT(1) NOT NULL DEFAULT 1',
        'bShowWohnmobil'  => 'TINYINT(1) NOT NULL DEFAULT 1',
    ];
    foreach ($mfSettingsAlters as $col => $def) {
        $exists = $db->getSingleObject("SHOW COLUMNS FROM twomosi_markefiltre_settings LIKE '{$col}'");
        if ($exists === null) {
            $db->executeQuery("ALTER TABLE twomosi_markefiltre_settings ADD COLUMN {$col} {$def}");
        }
    }
    $row = $db->getSingleObject('SELECT kID FROM twomosi_markefiltre_settings ORDER BY dUpdated DESC, kID DESC LIMIT 1');
    if ($row === null) {
        $db->executeQueryPrepared(
            'INSERT INTO twomosi_markefiltre_settings (dUpdated, cSelector, cMode, nRefreshDow, nRefreshHour, bShowPKW, bShowWohnmobil)
             VALUES (NOW(), :sel, :mode, :dow, :hr, 1, 1)',
            ['sel' => 'main', 'mode' => 'after', 'dow' => 0, 'hr' => 3]
        );
    }
} catch (\Throwable $e) {
    // ignore
}

// Ensure brands cache tables exist
try {
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_markefiltre_brands (
            kID INT AUTO_INCREMENT PRIMARY KEY,
            kSprache INT NOT NULL,
            cType VARCHAR(16) NOT NULL,
            cName VARCHAR(191) NOT NULL,
            cUrl VARCHAR(512) NOT NULL DEFAULT '',
            dUpdated DATETIME NOT NULL,
            UNIQUE KEY uq_mf_brand (kSprache, cType, cName),
            KEY ix_mf_brand_lang_type (kSprache, cType),
            KEY ix_mf_brand_updated (dUpdated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->executeQuery(
        "CREATE TABLE IF NOT EXISTS twomosi_markefiltre_meta (
            cKey VARCHAR(64) NOT NULL PRIMARY KEY,
            cValue VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (\Throwable $e) {}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !Form::validateToken()) {
    $message = 'Token hatası: Form oturumu geçersiz. Sayfayı yenileyip tekrar kaydedin.';
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['mfSavePlacement'])) {
            // Vollständige Zeile: zweites Formular postet kein mfSelector/mfMode — sonst würde „Aktif konum“ gelöscht.
            $cur = $db->getSingleObject(
                'SELECT cSelector, cMode, nRefreshDow, nRefreshHour, bShowPKW, bShowWohnmobil
                   FROM twomosi_markefiltre_settings
               ORDER BY dUpdated DESC, kID DESC
                  LIMIT 1'
            );
            $curSel   = $cur !== null ? trim((string)($cur->cSelector ?? '')) : '';
            $curMode  = $cur !== null ? trim((string)($cur->cMode ?? '')) : '';
            $curDow   = $cur !== null ? (int)($cur->nRefreshDow ?? 0) : 0;
            $curHour  = $cur !== null ? (int)($cur->nRefreshHour ?? 3) : 3;
            $curShowPKW  = $cur !== null ? (int)($cur->bShowPKW ?? 1) : 1;
            $curShowWohn = $cur !== null ? (int)($cur->bShowWohnmobil ?? 1) : 1;

            $selector = \array_key_exists('mfSelector', $_POST) ? trim((string) $_POST['mfSelector']) : $curSel;
            $mode     = \array_key_exists('mfMode', $_POST) ? trim((string) $_POST['mfMode']) : $curMode;
            $dow      = \array_key_exists('mfRefreshDow', $_POST) ? (int) $_POST['mfRefreshDow'] : $curDow;
            $hour     = \array_key_exists('mfRefreshHour', $_POST) ? (int) $_POST['mfRefreshHour'] : $curHour;
            // Checkbox-Formular: hidden+checkbox nur im zweiten Formular; sonst DB-Wert behalten
            $showPKW  = \array_key_exists('mfShowPKW', $_POST) ? (((int) $_POST['mfShowPKW'] === 1) ? 1 : 0) : $curShowPKW;
            $showWohn = \array_key_exists('mfShowWohnmobil', $_POST) ? (((int) $_POST['mfShowWohnmobil'] === 1) ? 1 : 0) : $curShowWohn;
            if ($dow < 0 || $dow > 6) { $dow = 0; }
            if ($hour < 0 || $hour > 23) { $hour = 3; }

            // allow disabling via empty selector
            if (!\in_array($mode, ['prepend', 'append', 'before', 'after'], true)) {
                $mode = '';
            }

            $db->executeQueryPrepared(
                "INSERT INTO twomosi_markefiltre_settings (dUpdated, cSelector, cMode, nRefreshDow, nRefreshHour, bShowPKW, bShowWohnmobil)
                 VALUES (NOW(), :sel, :mode, :dow, :hr, :pkw, :wohn)",
                ['sel' => $selector, 'mode' => $mode, 'dow' => $dow, 'hr' => $hour, 'pkw' => $showPKW, 'wohn' => $showWohn]
            );
            $message = 'Kaydedildi.';
        }

        if (!empty($_POST['mfRefreshBrands'])) {
            // Rebuild cache now for current + default language
            $langID = (int)Shop::getLanguageID();
            $tryLangs = [$langID];
            if ($langID !== 1) { $tryLangs[] = 1; }
            foreach ($tryLangs as $lid) {
                // Wohnmobil
                $wohnRows = $db->getObjects(
                    "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                       FROM tartikel a
                 INNER JOIN tartikelmerkmal am_typ ON am_typ.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                 INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
                 INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                                  AND ms_typ.kSprache = :lid
                                                  AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR (ms_typ.cName LIKE '%Aufbauart%'))
                 INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                 INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
                 INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                                  AND ms_her.kSprache = :lid
                                                  AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
                 INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                        AND tmws_her.kSprache     = :lid
                      WHERE a.nIstVater = 0
                        AND TRIM(tmws_her.cWert) <> ''
                        AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
                   ORDER BY tmws_her.cWert ASC
                      LIMIT 2000",
                    ['lid' => $lid]
                ) ?: [];

                // PKW
                $pkwRows = $db->getObjects(
                    "SELECT DISTINCT tmws_her.kMerkmalWert AS kMerkmalWert, tmws_her.cWert AS name
                       FROM tartikel a
                 INNER JOIN tartikelmerkmal am_her ON am_her.kArtikel = a.kArtikel
                 INNER JOIN tmerkmalwert mw_her    ON mw_her.kMerkmalWert = am_her.kMerkmalWert
                 INNER JOIN tmerkmal m_her         ON m_her.kMerkmal = mw_her.kMerkmal
                 INNER JOIN tmerkmalsprache ms_her ON ms_her.kMerkmal = m_her.kMerkmal
                                                  AND ms_her.kSprache = :lid
                                                  AND (ms_her.cName = 'Fahrzeug-Marke' OR ms_her.cName = 'Fahrzeug Marke')
                 INNER JOIN tmerkmalwertsprache tmws_her ON tmws_her.kMerkmalWert = mw_her.kMerkmalWert
                                                        AND tmws_her.kSprache     = :lid
                      WHERE a.nIstVater = 0
                        AND TRIM(tmws_her.cWert) <> ''
                        AND tmws_her.cWert NOT REGEXP '^[0-9]+$'
                        AND NOT EXISTS (
                            SELECT 1
                              FROM tartikelmerkmal am_typ
                        INNER JOIN tmerkmalwert mw_typ    ON mw_typ.kMerkmalWert = am_typ.kMerkmalWert
                        INNER JOIN tmerkmal m_typ         ON m_typ.kMerkmal = mw_typ.kMerkmal
                        INNER JOIN tmerkmalsprache ms_typ ON ms_typ.kMerkmal = m_typ.kMerkmal
                                                         AND ms_typ.kSprache = :lid
                                                         AND (ms_typ.cName = 'Wohnmobil-Aufbauart' OR ms_typ.cName = 'Wohnmobil Aufbauart' OR (ms_typ.cName LIKE '%Aufbauart%'))
                             WHERE am_typ.kArtikel = a.kArtikel
                        )
                   ORDER BY tmws_her.cWert ASC
                      LIMIT 2000",
                    ['lid' => $lid]
                ) ?: [];

                $db->executeQueryPrepared("DELETE FROM twomosi_markefiltre_brands WHERE kSprache = :lid", ['lid' => $lid]);

                $insert = function (string $type, array $rows) use ($db, $lid): int {
                    $n = 0;
                    foreach ($rows as $r) {
                        $name = trim((string)($r->name ?? ''));
                        $kid  = (int)($r->kMerkmalWert ?? 0);
                        if ($name === '' || $kid <= 0) {
                            continue;
                        }
                        $db->executeQueryPrepared(
                            "INSERT INTO twomosi_markefiltre_brands (kSprache, cType, cName, cUrl, dUpdated)
                             VALUES (:lid, :t, :n, :u, NOW())
                             ON DUPLICATE KEY UPDATE cUrl = VALUES(cUrl), dUpdated = NOW()",
                            ['lid' => $lid, 't' => $type, 'n' => $name, 'u' => BrandListLink::forMerkmalWertStorable($db, $lid, $kid)]
                        );
                        $n++;
                    }
                    return $n;
                };
                $nWohn = $insert('Wohnmobil', is_array($wohnRows) ? $wohnRows : []);
                $nPkw  = $insert('PKW', is_array($pkwRows) ? $pkwRows : []);

                $key = 'brands_last_refresh_lang_' . $lid;
                $db->executeQueryPrepared(
                    "INSERT INTO twomosi_markefiltre_meta (cKey, cValue)
                     VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)",
                    ['k' => $key, 'v' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]
                );
            }
            $message = 'Markalar cache yenilendi.';
        }
    } catch (\Throwable $e) {
        $message = 'İşlem başarısız: ' . $e->getMessage();
    }
}

$cfgSelector = '';
$cfgMode = '';
$cfgUpdated = '';
$cfgDow = 0;
$cfgHour = 3;
$cfgShowPKW = 1;
$cfgShowWohnmobil = 1;
try {
    $row = $db->getSingleObject('SELECT cSelector, cMode, dUpdated, nRefreshDow, nRefreshHour, bShowPKW, bShowWohnmobil FROM twomosi_markefiltre_settings ORDER BY dUpdated DESC, kID DESC LIMIT 1');
    if ($row !== null) {
        $cfgSelector = (string)($row->cSelector ?? '');
        $cfgMode     = (string)($row->cMode ?? '');
        $cfgUpdated  = (string)($row->dUpdated ?? '');
        $cfgDow      = (int)($row->nRefreshDow ?? 0);
        $cfgHour     = (int)($row->nRefreshHour ?? 3);
        $cfgShowPKW  = (int)($row->bShowPKW ?? 1);
        $cfgShowWohnmobil = (int)($row->bShowWohnmobil ?? 1);
    }
} catch (\Throwable $e) {}

$lastRefresh = '';
$pkwCnt = 0;
$wohnCnt = 0;
try {
    $langID = (int)Shop::getLanguageID();
    $key = 'brands_last_refresh_lang_' . $langID;
    $row = $db->getSingleObject("SELECT cValue FROM twomosi_markefiltre_meta WHERE cKey = :k LIMIT 1", ['k' => $key]);
    if ($row !== null) { $lastRefresh = (string)($row->cValue ?? ''); }
    $row = $db->getSingleObject("SELECT COUNT(*) AS cnt FROM twomosi_markefiltre_brands WHERE kSprache = :lid AND cType = 'PKW'", ['lid' => $langID]);
    $pkwCnt = $row !== null ? (int)($row->cnt ?? 0) : 0;
    $row = $db->getSingleObject("SELECT COUNT(*) AS cnt FROM twomosi_markefiltre_brands WHERE kSprache = :lid AND cType = 'Wohnmobil'", ['lid' => $langID]);
    $wohnCnt = $row !== null ? (int)($row->cnt ?? 0) : 0;
} catch (\Throwable $e) {}

?>
<div style="padding:10px 0;">
    <h2 style="margin:0 0 12px;">MarkeFiltre Dashboard</h2>
    <?php if ($message !== ''): ?>
        <div style="margin:0 0 12px;padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:10px;background:rgba(204,255,0,.12);max-width:900px;">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
    <div style="padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;max-width:900px;">
        <div style="margin-bottom:8px;"><b>PluginID:</b> <?= (int)$pluginID ?></div>
        <div style="margin-bottom:8px;"><b>Görünen sayfa:</b> Yalnızca <code>Startseite</code> (anasayfa).</div>
        <div style="margin-bottom:8px;"><b>Widget nerede dursun?</b> Tamamen siz seçersiniz: aşağıda <b>hedef CSS seçici</b> + <b>konum modu</b>. Footer, header, OPC satırı veya child template’e koyduğunuz herhangi bir <code>id</code>/<code>class</code> kullanılabilir.</div>
        <div style="margin-bottom:8px;"><b>İçerik şablonu:</b> <code>plugins/MarkeFiltre/templates/markefiltre.tpl</code></div>
        <div style="margin-bottom:8px;"><b>DOM kontrol:</b> Sayfada zaten <code>#br-caravan-root</code> varsa (ör. Smarty <code>include</code>) otomatik enjekte tekrarlanmaz.</div>
        <div style="margin-bottom:8px;"><b>Kayıtlı hedef / mod:</b>
            <code><?= htmlspecialchars($cfgSelector ?: '(kapalı)', ENT_QUOTES, 'UTF-8') ?></code>
            <span style="opacity:.85;"> / </span>
            <code><?= htmlspecialchars($cfgMode ?: '-', ENT_QUOTES, 'UTF-8') ?></code>
            <?php if ($cfgUpdated): ?><span style="opacity:.75;"> (<?= htmlspecialchars($cfgUpdated, ENT_QUOTES, 'UTF-8') ?>)</span><?php endif; ?>
        </div>
    </div>

    <div style="margin-top:14px;padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;max-width:900px;">
        <h3 style="margin:0 0 10px;">Widget yerleşimi — hedefi siz seçin</h3>
        <form method="post" style="display:grid;gap:10px;">
            <?= Form::getTokenInput(); ?>

            <div>
                <label for="mfSelector"><b>Hedef öğe (CSS seçici)</b></label><br>
                <input id="mfSelector" name="mfSelector" type="text"
                       value="<?= htmlspecialchars($cfgSelector, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="#markefiltre-here, .opc-Container, main#main-wrapper, [data-my-slot]"
                       style="width:100%;padding:8px;">
                <div style="opacity:.78;margin-top:8px;font-size:13px;line-height:1.5;">
                    Anasayfada <b>zaten var olan</b> bir öğeyi hedefleyin: <code>#id</code>, <code>.class</code>, etiket adı (<code>main</code>),
                    veya tarayıcı geliştirici araçlarından kopyaladığınız geçerli bir seçici.
                    <b>Tam kontrol</b> için child template’te (ör. <code>page/index.tpl</code>) boş bir tutucu ekleyebilirsiniz:
                    <code style="white-space:normal;word-break:break-all;">&lt;div id="markefiltre-here"&gt;&lt;/div&gt;</code>
                    — ardından hedef: <code>#markefiltre-here</code>, mod: içine sona / öncesine vb.
                    <br><br>
                    Boş bırakırsanız bileşen enjekte edilmez (kapalı).
                    <br>
                    <b>HomepageInjector</b> açık ve sırasında <code>MarkeFiltre</code> varsa, o plugin kendi hedef kutusuna taşır; yalnızca bu ayarı kullanmak için sıradan <code>MarkeFiltre</code>’yi kaldırın veya HomepageInjector’ı kapatın.
                </div>
            </div>

            <div>
                <label for="mfMode"><b>Hedefe göre konum</b></label><br>
                <select id="mfMode" name="mfMode" style="padding:8px;min-width:280px;">
                    <option value="" <?= $cfgMode === '' ? 'selected' : '' ?>>Seçiniz</option>
                    <option value="prepend" <?= $cfgMode === 'prepend' ? 'selected' : '' ?>>Hedefin içine — en üste (prepend)</option>
                    <option value="append" <?= $cfgMode === 'append' ? 'selected' : '' ?>>Hedefin içine — en alta (append)</option>
                    <option value="before" <?= $cfgMode === 'before' ? 'selected' : '' ?>>Hedefin hemen önüne (before)</option>
                    <option value="after" <?= $cfgMode === 'after' ? 'selected' : '' ?>>Hedefin hemen arkasına (after)</option>
                </select>
                <div style="opacity:.78;margin-top:8px;font-size:13px;line-height:1.5;">
                    Mod, <b>sizin yazdığınız hedefe</b> göredir: örneğin hedef bir <code>&lt;footer&gt;</code> ise
                    <i>before</i> üstüne, <i>after</i> altına koyar. İstediğiniz alan başka bir div ise aynı mantık geçerlidir.
                    Çok karmaşık seçiciler bazen sunucu tarafı (phpQuery) ile uyumsuz olabilir; o zaman sade <code>#id</code> kullanın veya tutucu div ekleyin.
                </div>
            </div>

            <div>
                <button type="submit" name="mfSavePlacement" value="1" style="padding:10px 14px;">Kaydet</button>
            </div>
        </form>
    </div>

    <div style="margin-top:14px;padding:12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;max-width:900px;">
        <h3 style="margin:0 0 10px;">Marka cache (performans)</h3>
        <div style="margin-bottom:10px;opacity:.9;">
            Cache tablosundan okunur (frontend her seferinde JOIN çalıştırmaz).
            <br>
            <b>Durum:</b> PKW=<?= (int)$pkwCnt ?>, Wohnmobil=<?= (int)$wohnCnt ?>
            <?php if ($lastRefresh): ?> <span style="opacity:.75;">(son yenileme: <?= htmlspecialchars($lastRefresh, ENT_QUOTES, 'UTF-8') ?>)</span><?php endif; ?>
        </div>

        <form method="post" style="display:grid;gap:10px;">
            <?= Form::getTokenInput(); ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                    <label for="mfRefreshDow"><b>Haftalık kontrol günü</b></label><br>
                    <select id="mfRefreshDow" name="mfRefreshDow" style="padding:8px;width:100%;">
                        <option value="0" <?= $cfgDow === 0 ? 'selected' : '' ?>>Pazar</option>
                        <option value="1" <?= $cfgDow === 1 ? 'selected' : '' ?>>Pazartesi</option>
                        <option value="2" <?= $cfgDow === 2 ? 'selected' : '' ?>>Salı</option>
                        <option value="3" <?= $cfgDow === 3 ? 'selected' : '' ?>>Çarşamba</option>
                        <option value="4" <?= $cfgDow === 4 ? 'selected' : '' ?>>Perşembe</option>
                        <option value="5" <?= $cfgDow === 5 ? 'selected' : '' ?>>Cuma</option>
                        <option value="6" <?= $cfgDow === 6 ? 'selected' : '' ?>>Cumartesi</option>
                    </select>
                </div>
                <div>
                    <label for="mfRefreshHour"><b>Saat</b> (0-23)</label><br>
                    <input id="mfRefreshHour" name="mfRefreshHour" type="number" min="0" max="23"
                           value="<?= (int)$cfgHour ?>" style="padding:8px;width:100%;">
                </div>
            </div>

            <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
                <label style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="mfShowPKW" value="0">
                    <input type="checkbox" name="mfShowPKW" value="1" <?= $cfgShowPKW === 1 ? 'checked' : '' ?>>
                    <b>PKW sekmesi açık</b>
                </label>
                <label style="display:flex;gap:8px;align-items:center;">
                    <input type="hidden" name="mfShowWohnmobil" value="0">
                    <input type="checkbox" name="mfShowWohnmobil" value="1" <?= $cfgShowWohnmobil === 1 ? 'checked' : '' ?>>
                    <b>Wohnmobil sekmesi açık</b>
                </label>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit" name="mfSavePlacement" value="1" style="padding:10px 14px;">Ayarları Kaydet</button>
                <button type="submit" name="mfRefreshBrands" value="1" style="padding:10px 14px;">Markaları şimdi yenile</button>
            </div>

            <div style="opacity:.75;">
                Not: Otomatik yenileme, seçtiğin gün/saat geldiğinde ve son yenileme 6+ gün önceyse çalışır.
            </div>
        </form>
    </div>
</div>

