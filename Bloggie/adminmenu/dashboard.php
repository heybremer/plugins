<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\Bloggie\BloggieDatabase;

require_once dirname(__DIR__) . '/src/BloggieDatabase.php';

BloggieDatabase::ensureAll();

$db = Shop::Container()->getDB();
$message = '';
$testResult = null;

$s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
if ($s === null) {
    $ins = new stdClass();
    $ins->nActive = 1;
    $ins->nAutoDaily = 1;
    $ins->cApiKey = '';
    $ins->cModel = 'gpt-4o-mini';
    $ins->cLanguage = 'de';
    $ins->cSectionTitle = 'Blog';
    $ins->cPromptPrefix = 'Write a high quality SEO blog post for this product page.';
    $ins->cInjectSelector = 'div.index-filter-konfigurator';
    $ins->cInjectMode = 'after';
    $ins->cCronToken = bin2hex(random_bytes(16));
    $ins->dUpdated = date('Y-m-d H:i:s');
    $db->insert('twomosi_bloggie_settings', $ins);
    $s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
}

if (Form::validateToken() && !empty($_POST['bgSave'])) {
    try {
        $id = (int)($s->kID ?? 0);
        $active = !empty($_POST['nActive']) ? 1 : 0;
        $autoDaily = !empty($_POST['nAutoDaily']) ? 1 : 0;
        $apiKey = trim((string)($_POST['cApiKey'] ?? ''));
        if ($apiKey === '') {
            $apiKey = (string)($s->cApiKey ?? '');
        }
        $model = trim((string)($_POST['cModel'] ?? 'gpt-4o-mini'));
        $language = trim((string)($_POST['cLanguage'] ?? 'de'));
        $sectionTitle = trim((string)($_POST['cSectionTitle'] ?? 'Blog'));
        $promptPrefix = trim((string)($_POST['cPromptPrefix'] ?? ''));
        $brandPool = trim((string)($_POST['cBrandPool'] ?? ''));
        $topicPool = trim((string)($_POST['cTopicPool'] ?? ''));
        $injectSelector = trim((string)($_POST['cInjectSelector'] ?? 'div.index-filter-konfigurator'));
        if ($injectSelector === '') {
            $injectSelector = 'div.index-filter-konfigurator';
        }
        $injectMode = trim((string)($_POST['cInjectMode'] ?? 'after'));
        if (!in_array($injectMode, ['after', 'before', 'prepend', 'append'], true)) {
            $injectMode = 'after';
        }

        $db->executeQueryPrepared(
            "UPDATE twomosi_bloggie_settings
                SET nActive = :nActive,
                    nAutoDaily = :nAutoDaily,
                    cApiKey = :cApiKey,
                    cModel = :cModel,
                    cLanguage = :cLanguage,
                    cSectionTitle = :cSectionTitle,
                    cPromptPrefix = :cPromptPrefix,
                    cInjectSelector = :cInjectSelector,
                    cInjectMode = :cInjectMode,
                    cBrandPool = :cBrandPool,
                    cTopicPool = :cTopicPool,
                    dUpdated = NOW()
              WHERE kID = :kID",
            [
                'kID' => $id,
                'nActive' => $active,
                'nAutoDaily' => $autoDaily,
                'cApiKey' => $apiKey,
                'cModel' => $model,
                'cLanguage' => $language,
                'cSectionTitle' => $sectionTitle,
                'cPromptPrefix' => $promptPrefix,
                'cInjectSelector' => $injectSelector,
                'cInjectMode' => $injectMode,
                'cBrandPool' => $brandPool,
                'cTopicPool' => $topicPool,
            ]
        );
        $message = 'Kaydedildi.';
        $s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['bgRegenerateToken'])) {
    try {
        $newToken = bin2hex(random_bytes(16));
        $db->executeQueryPrepared(
            "UPDATE twomosi_bloggie_settings SET cCronToken = :t, dUpdated = NOW() WHERE kID = :kID",
            ['t' => $newToken, 'kID' => (int)($s->kID ?? 0)]
        );
        $message = 'Cron token yenilendi.';
        $s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['bgRunSelfTest'])) {
    try {
        $result = [
            'db' => ['ok' => true, 'message' => 'DB connection ok'],
            'product' => ['ok' => false, 'message' => 'Not tested'],
            'openai' => ['ok' => false, 'message' => 'Not tested'],
        ];

        $pl = dirname(__DIR__) . '/includes/product_lib.php';
        if (is_file($pl)) {
            require_once $pl;
        }
        $tCount = function_exists('bloggieCountArticles') ? bloggieCountArticles() : 0;
        $product = function_exists('bloggieFetchOneArticleForTest') ? bloggieFetchOneArticleForTest() : null;
        if ($product === null) {
            $result['product'] = ['ok' => false, 'message' => 'No product found (tartikel count=' . $tCount . ')'];
        } else {
            $pName = trim((string)($product->cName ?? ''));
            if ($pName === '') {
                $pName = trim((string)($product->cArtName ?? ''));
            }
            $result['product'] = [
                'ok' => true,
                'message' => 'Product ok: #' . (int)($product->kArtikel ?? 0) . ' ' . $pName,
            ];
        }

        $apiKey = trim((string)($s->cApiKey ?? ''));
        if ($apiKey === '') {
            $result['openai'] = ['ok' => false, 'message' => 'API key missing'];
        } else {
            $payload = [
                'model' => (string)($s->cModel ?? 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Return strict JSON with key status only.'],
                    ['role' => 'user', 'content' => 'Return {"status":"ok"}'],
                ],
                'temperature' => 0.0,
                'response_format' => ['type' => 'json_object'],
            ];
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
            ]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if (!is_string($resp) || $resp === '' || $code < 200 || $code >= 300) {
                $result['openai'] = ['ok' => false, 'message' => 'OpenAI fail: ' . $code . ' ' . $err];
            } else {
                $decoded = json_decode($resp, true);
                $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
                $json = json_decode($content, true);
                if (is_array($json) && (($json['status'] ?? '') === 'ok')) {
                    $result['openai'] = ['ok' => true, 'message' => 'OpenAI connection ok'];
                } else {
                    $result['openai'] = ['ok' => false, 'message' => 'OpenAI parse failed'];
                }
            }
        }

        $allOk = !empty($result['db']['ok']) && !empty($result['product']['ok']) && !empty($result['openai']['ok']);
        $message = $allOk ? 'Self-test basarili.' : 'Self-test tamamlandi, hata var.';
        $testResult = $result;
    } catch (\Throwable $e) {
        $message = 'Self-test hata: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['bgGenerateNow'])) {
    try {
        $lib = dirname(__DIR__) . '/includes/generate_lib.php';
        if (is_file($lib)) {
            require_once $lib;
        }
        if (function_exists('bloggieGenerateOnePost')) {
            $gen = bloggieGenerateOnePost(null);
            if (!empty($gen['ok'])) {
                $message = 'Hemen uretim basarili: ' . (string)($gen['title'] ?? '');
            } else {
                $message = 'Hemen uretim basarisiz: ' . (string)($gen['message'] ?? 'unknown');
            }
        } else {
            $message = 'Hemen uretim: generate_lib.php bulunamadi.';
        }
        $s = $db->getSingleObject("SELECT * FROM twomosi_bloggie_settings ORDER BY kID ASC LIMIT 1");
    } catch (\Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['bgDeleteLatest'])) {
    try {
        $limit = (int)($_POST['bgDeleteLimit'] ?? 1);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 20) {
            $limit = 20;
        }
        $rows = $db->getObjects(
            "SELECT kID, kNews
               FROM twomosi_bloggie_posts
              ORDER BY dPublish DESC, kID DESC
              LIMIT " . $limit
        );
        $deleted = 0;
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $kID = (int)($r->kID ?? 0);
                $kNews = (int)($r->kNews ?? 0);
                if ($kNews > 0) {
                    // delete JTL news + language + seo + category relation
                    $db->delete('tnews', 'kNews', $kNews);
                    $db->delete('tnewssprache', 'kNews', $kNews);
                    $db->delete('tnewskategorienews', 'kNews', $kNews);
                    $db->delete('tseo', ['cKey', 'kKey'], ['kNews', $kNews]);
                }
                if ($kID > 0) {
                    $db->delete('twomosi_bloggie_posts', 'kID', $kID);
                }
                $deleted++;
            }
        }
        $message = 'Silindi: ' . $deleted . ' (son üretilen)';
    } catch (\Throwable $e) {
        $message = 'Silme hatası: ' . $e->getMessage();
    }
}

if (Form::validateToken() && !empty($_POST['bgDeleteSelected'])) {
    try {
        $ids = $_POST['bgDeleteIDs'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static fn($v) => $v > 0);
        if ($ids === []) {
            $message = 'Silinecek blog seçilmedi.';
        } else {
            $deleted = 0;
            foreach ($ids as $kID) {
                $row = $db->getSingleObject(
                    "SELECT kID, kNews FROM twomosi_bloggie_posts WHERE kID = :id LIMIT 1",
                    ['id' => $kID]
                );
                if ($row === null) {
                    continue;
                }
                $kNews = (int)($row->kNews ?? 0);
                if ($kNews > 0) {
                    $db->delete('tnews', 'kNews', $kNews);
                    $db->delete('tnewssprache', 'kNews', $kNews);
                    $db->delete('tnewskategorienews', 'kNews', $kNews);
                    $db->delete('tseo', ['cKey', 'kKey'], ['kNews', $kNews]);
                }
                $db->delete('twomosi_bloggie_posts', 'kID', (int)$kID);
                $deleted++;
            }
            $message = 'Silindi (seçili): ' . $deleted;
        }
    } catch (\Throwable $e) {
        $message = 'Silme hatası: ' . $e->getMessage();
    }
}

$posts = $db->getObjects(
    "SELECT kID, kNews, cBlogTitle, cProductName, cExcerpt, dPublish
       FROM twomosi_bloggie_posts
      ORDER BY dPublish DESC, kID DESC
      LIMIT 20"
);
if (!is_array($posts)) {
    $posts = [];
}

$mode = (string)($s->cInjectMode ?? 'after');
if (!in_array($mode, ['after', 'before', 'prepend', 'append'], true)) {
    $mode = 'after';
}
$shopUrl = rtrim((string)Shop::getURL(), '/');
$cronUrl = $shopUrl . '/plugins/Bloggie/api/generate.php?token=' . urlencode((string)($s->cCronToken ?? ''));
$testUrl = $shopUrl . '/plugins/Bloggie/api/test.php?token=' . urlencode((string)($s->cCronToken ?? ''));
$maskedApi = trim((string)($s->cApiKey ?? '')) !== '' ? str_repeat('*', 10) : '';
?>

<div style="padding:10px 0; max-width: 1100px;">
    <h2 style="margin:0 0 12px;">Bloggie Dashboard</h2>
    <?php if ($message !== ''): ?>
        <div class="alert alert-info" style="margin:10px 0;">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:12px;">
        <div class="card-body">
            <form method="post">
                <?= Form::getTokenInput(); ?>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label><input type="checkbox" name="nActive" value="1" <?= (int)($s->nActive ?? 1) === 1 ? 'checked' : '' ?>> Aktif</label>
                    </div>
                    <div class="col-md-3 form-group">
                        <label><input type="checkbox" name="nAutoDaily" value="1" <?= (int)($s->nAutoDaily ?? 1) === 1 ? 'checked' : '' ?>> Her gun otomatik</label>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Bolum Basligi</label>
                        <input class="form-control" type="text" name="cSectionTitle" value="<?= htmlspecialchars((string)($s->cSectionTitle ?? 'Blog'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>OpenAI API Key (bos birakirsan mevcut key korunur)</label>
                        <input class="form-control" type="password" name="cApiKey" placeholder="<?= $maskedApi !== '' ? $maskedApi : 'sk-...'; ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Model</label>
                        <input class="form-control" type="text" name="cModel" value="<?= htmlspecialchars((string)($s->cModel ?? 'gpt-4o-mini'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Dil</label>
                        <select class="form-control" name="cLanguage">
                            <?php $lang = (string)($s->cLanguage ?? 'de'); ?>
                            <option value="de" <?= $lang === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                            <option value="tr" <?= $lang === 'tr' ? 'selected' : ''; ?>>Turkce</option>
                            <option value="en" <?= $lang === 'en' ? 'selected' : ''; ?>>English</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Prompt Prefix</label>
                    <textarea class="form-control" name="cPromptPrefix" rows="3"><?= htmlspecialchars((string)($s->cPromptPrefix ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Marka Havuzu (her satıra 1 marka)</label>
                        <textarea class="form-control" name="cBrandPool" rows="4" placeholder="Iveco&#10;Fiat&#10;Ford"><?= htmlspecialchars((string)($s->cBrandPool ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Kategori / Konu Havuzu (her satıra 1 konu)</label>
                        <textarea class="form-control" name="cTopicPool" rows="4" placeholder="Schonbezüge&#10;Fußmatten&#10;Zubehör Tipps"><?= htmlspecialchars((string)($s->cTopicPool ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 form-group">
                        <label>CSS Selector (virgul ile birden fazla)</label>
                        <input class="form-control" type="text" name="cInjectSelector" value="<?= htmlspecialchars((string)($s->cInjectSelector ?? 'div.index-filter-konfigurator'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Enjeksiyon</label>
                        <select class="form-control" name="cInjectMode">
                            <option value="after" <?= $mode === 'after' ? 'selected' : ''; ?>>Sonra (after)</option>
                            <option value="before" <?= $mode === 'before' ? 'selected' : ''; ?>>Once (before)</option>
                            <option value="prepend" <?= $mode === 'prepend' ? 'selected' : ''; ?>>Icine basa (prepend)</option>
                            <option value="append" <?= $mode === 'append' ? 'selected' : ''; ?>>Icine sona (append)</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button class="btn btn-primary" type="submit" name="bgSave" value="1">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom:12px;">
        <div class="card-header"><strong>Gunluk Uretim (Cron)</strong></div>
        <div class="card-body">
            <p style="margin:0 0 8px;">Sunucu cron'una bu URL'i gunde 1 kez ekle:</p>
            <input class="form-control" type="text" readonly value="<?= htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <p style="margin:8px 0 0;color:rgba(0,0,0,.6);">Son calisma tarihi: <?= htmlspecialchars((string)($s->dLastRunDate ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
            <form method="post" style="margin-top:8px;">
                <?= Form::getTokenInput(); ?>
                <button class="btn btn-outline-secondary" type="submit" name="bgRegenerateToken" value="1">Cron Token Yenile</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom:12px;">
        <div class="card-header"><strong>Test Merkezi</strong></div>
        <div class="card-body">
            <p style="margin:0 0 8px;">API/DB test endpoint:</p>
            <input class="form-control" type="text" readonly value="<?= htmlspecialchars($testUrl, ENT_QUOTES, 'UTF-8'); ?>">
            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                <form method="post" style="margin:0;">
                    <?= Form::getTokenInput(); ?>
                    <button class="btn btn-outline-primary" type="submit" name="bgRunSelfTest" value="1">Self-Test Calistir</button>
                </form>
                <form method="post" style="margin:0;">
                    <?= Form::getTokenInput(); ?>
                    <button class="btn btn-success" type="submit" name="bgGenerateNow" value="1">Hemen Blog Uret</button>
                </form>
            </div>
            <?php if (is_array($testResult)): ?>
                <div style="margin-top:12px; font-family:monospace; white-space:pre-wrap; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
<?= htmlspecialchars(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:12px;">
        <div class="card-header"><strong>AI Prompt (Son Çalışma)</strong></div>
        <div class="card-body">
            <p style="margin:0 0 8px; color:rgba(0,0,0,.65);">
                Son prompt zamanı: <?= htmlspecialchars((string)($s->dLastPrompt ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
            </p>
            <div class="row">
                <div class="col-md-6 form-group">
                    <label>System Prompt</label>
                    <textarea class="form-control" rows="10" readonly><?php
                        echo htmlspecialchars((string)($s->cLastSystemPrompt ?? ''), ENT_QUOTES, 'UTF-8');
                    ?></textarea>
                </div>
                <div class="col-md-6 form-group">
                    <label>User Prompt</label>
                    <textarea class="form-control" rows="10" readonly><?php
                        echo htmlspecialchars((string)($s->cLastUserPrompt ?? ''), ENT_QUOTES, 'UTF-8');
                    ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Son Uretilen Bloglar</strong></div>
        <div class="card-body">
            <form method="post" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin:0 0 10px;">
                <?= Form::getTokenInput(); ?>
                <div>
                    <label style="display:block; margin:0 0 4px;">Kaç tane silinsin? (1-20)</label>
                    <input class="form-control" style="width:120px;" type="number" min="1" max="20" name="bgDeleteLimit" value="1">
                </div>
                <button class="btn btn-danger" type="submit" name="bgDeleteLatest" value="1" onclick="return confirm('Son üretilen blogları silmek istiyor musun?');">
                    Son Üretilenleri Sil
                </button>
            </form>
            <?php if ($posts === []): ?>
                <p style="margin:0;">Henuz blog yok.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <form method="post" style="margin:0;">
                        <?= Form::getTokenInput(); ?>
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin:0 0 10px;">
                            <button class="btn btn-danger" type="submit" name="bgDeleteSelected" value="1"
                                onclick="return confirm('Seçili blogları silmek istiyor musun?');">
                                Seçilileri Sil
                            </button>
                        </div>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th style="width:28px;"></th>
                                    <th>ID</th><th>kNews</th><th>Baslik</th><th>Urun</th><th>Ozet</th><th>Tarih</th>
                                </tr>
                            </thead>
                        <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="bgDeleteIDs[]" value="<?= (int)($p->kID ?? 0); ?>">
                                </td>
                                <td><?= (int)($p->kID ?? 0); ?></td>
                                <td><?= (int)($p->kNews ?? 0); ?></td>
                                <td><?= htmlspecialchars((string)($p->cBlogTitle ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p->cProductName ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p->cExcerpt ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?= htmlspecialchars((string)($p->dPublish ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        </table>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

