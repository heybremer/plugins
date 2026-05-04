<?php
declare(strict_types=1);

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\GoogleReview\GoogleReviewDatabase;

require_once dirname(__DIR__) . '/src/GoogleReviewDatabase.php';

$db = Shop::Container()->getDB();
GoogleReviewDatabase::ensureAll();
$message = '';

function grFloat(string $v, float $fallback = 0.0): float
{
    $v = str_replace(',', '.', trim($v));
    if ($v === '') {
        return $fallback;
    }
    return (float)$v;
}

if (Form::validateToken()) {
    try {
        if (!empty($_POST['grSaveSettings'])) {
            $heading    = trim((string)($_POST['cHeading'] ?? 'Ihr Unternehmen'));
            $subheading = trim((string)($_POST['cSubheading'] ?? 'Basierend auf 500 Bewertungen'));
            $business   = trim((string)($_POST['cBusinessName'] ?? $heading));
            $rating     = grFloat((string)($_POST['fRating'] ?? '4.5'), 4.5);
            $total      = (int)($_POST['nTotalReviews'] ?? 500);
            $writeUrl   = trim((string)($_POST['cWriteUrl'] ?? ''));
            $allUrl     = trim((string)($_POST['cAllUrl'] ?? ''));
            $injectMode = trim((string)($_POST['cInjectMode'] ?? 'after_cardproduct'));
            $injectSelector = trim((string)($_POST['cInjectSelector'] ?? ''));
            if (!in_array($injectMode, ['after_cardproduct', 'after_configurator', 'after_selector'], true)) {
                $injectMode = 'after_cardproduct';
            }

            $existing = $db->getSingleObject("SELECT kID FROM twomosi_google_review_settings LIMIT 1");
            if ($existing === null) {
                $row = new stdClass();
                $row->cBusinessName = $business;
                $row->fRating = $rating;
                $row->nTotalReviews = $total;
                $row->cWriteUrl = $writeUrl;
                $row->cAllUrl = $allUrl;
                $row->cHeading = $heading;
                $row->cSubheading = $subheading;
                $row->cInjectMode = $injectMode;
                $row->cInjectSelector = $injectSelector;
                $row->dUpdated = date('Y-m-d H:i:s');
                $db->insert('twomosi_google_review_settings', $row);
            } else {
                $db->executeQueryPrepared(
                    "UPDATE twomosi_google_review_settings
                        SET cBusinessName = :cBusinessName,
                            fRating = :fRating,
                            nTotalReviews = :nTotalReviews,
                            cWriteUrl = :cWriteUrl,
                            cAllUrl = :cAllUrl,
                            cHeading = :cHeading,
                            cSubheading = :cSubheading,
                            cInjectMode = :cInjectMode,
                            cInjectSelector = :cInjectSelector,
                            dUpdated = NOW()
                      WHERE kID = :kID",
                    [
                        'kID' => (int)$existing->kID,
                        'cBusinessName' => $business,
                        'fRating' => $rating,
                        'nTotalReviews' => $total,
                        'cWriteUrl' => $writeUrl,
                        'cAllUrl' => $allUrl,
                        'cHeading' => $heading,
                        'cSubheading' => $subheading,
                        'cInjectMode' => $injectMode,
                        'cInjectSelector' => $injectSelector,
                    ]
                );
            }
            $message = 'Ayarlar kaydedildi.';
        }

        if (!empty($_POST['grAddReview'])) {
            $author = trim((string)($_POST['cAuthor'] ?? ''));
            $time   = trim((string)($_POST['cTimeText'] ?? ''));
            $stars  = (int)($_POST['nStars'] ?? 5);
            $text   = trim((string)($_POST['cText'] ?? ''));
            $avatar = trim((string)($_POST['cAvatarUrl'] ?? ''));
            $sort   = (int)($_POST['nSort'] ?? 100);
            $active = !empty($_POST['nActive']) ? 1 : 0;

            $db->executeQueryPrepared(
                "INSERT INTO twomosi_google_review_items
                    (cAuthor, cTimeText, nStars, cText, cAvatarUrl, nSort, nActive, dCreated, dUpdated)
                 VALUES
                    (:cAuthor, :cTimeText, :nStars, :cText, :cAvatarUrl, :nSort, :nActive, NOW(), NOW())",
                [
                    'cAuthor' => $author,
                    'cTimeText' => $time,
                    'nStars' => max(1, min(5, $stars)),
                    'cText' => $text,
                    'cAvatarUrl' => $avatar,
                    'nSort' => $sort,
                    'nActive' => $active,
                ]
            );
            $message = 'Yorum eklendi.';
        }

        if (!empty($_POST['grSaveReview']) && !empty($_POST['kID'])) {
            $kID    = (int)$_POST['kID'];
            $author = trim((string)($_POST['cAuthor'] ?? ''));
            $time   = trim((string)($_POST['cTimeText'] ?? ''));
            $stars  = (int)($_POST['nStars'] ?? 5);
            $text   = trim((string)($_POST['cText'] ?? ''));
            $avatar = trim((string)($_POST['cAvatarUrl'] ?? ''));
            $sort   = (int)($_POST['nSort'] ?? 100);
            $active = !empty($_POST['nActive']) ? 1 : 0;

            $db->executeQueryPrepared(
                "UPDATE twomosi_google_review_items
                    SET cAuthor = :cAuthor,
                        cTimeText = :cTimeText,
                        nStars = :nStars,
                        cText = :cText,
                        cAvatarUrl = :cAvatarUrl,
                        nSort = :nSort,
                        nActive = :nActive,
                        dUpdated = NOW()
                  WHERE kID = :kID",
                [
                    'kID' => $kID,
                    'cAuthor' => $author,
                    'cTimeText' => $time,
                    'nStars' => max(1, min(5, $stars)),
                    'cText' => $text,
                    'cAvatarUrl' => $avatar,
                    'nSort' => $sort,
                    'nActive' => $active,
                ]
            );
            $message = 'Yorum güncellendi.';
        }

        if (!empty($_POST['grDeleteReview']) && !empty($_POST['kID'])) {
            $kID = (int)$_POST['kID'];
            $db->executeQueryPrepared(
                "DELETE FROM twomosi_google_review_items WHERE kID = :kID",
                ['kID' => $kID]
            );
            $message = 'Yorum silindi.';
        }
    } catch (Throwable $e) {
        $message = 'Hata: ' . $e->getMessage();
    }
}

$settings = $db->getSingleObject("SELECT * FROM twomosi_google_review_settings ORDER BY kID ASC LIMIT 1");
$items = $db->getObjects("SELECT * FROM twomosi_google_review_items ORDER BY nSort ASC, kID ASC");
if (!is_array($items)) {
    $items = [];
}
$lastUpdated = '';
try {
    $maxRow = $db->getSingleObject("SELECT MAX(dUpdated) AS dMax FROM twomosi_google_review_items");
    $lastUpdated = isset($maxRow->dMax) ? (string)$maxRow->dMax : '';
} catch (Throwable $e) {
    $lastUpdated = '';
}

function grStars(int $nStars): string
{
    $nStars = max(1, min(5, $nStars));
    $out = '';
    for ($i = 0; $i < 5; $i++) {
        $out .= $i < $nStars ? '★' : '☆';
    }
    return $out;
}
?>

<div style="padding: 10px 0;">
    <style>
        .grw-wrap{--grw-border:rgba(0,0,0,.08);--grw-text:rgba(0,0,0,.78);--grw-muted:rgba(0,0,0,.55);--grw-accent:#1a73e8;--grw-ok:#34a853;--grw-danger:#b00020;}
        .grw-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
        .grw-title{margin:0;font-size:18px;font-weight:900;letter-spacing:-.01em;}
        .grw-msg{margin:0;color:var(--grw-text);font-weight:700;}
        .grw-grid{display:grid;grid-template-columns:1fr;gap:14px;}
        .grw-card{background:#fff;border:1px solid var(--grw-border);border-radius:14px;padding:14px;box-shadow:0 10px 28px rgba(0,0,0,.04);}
        .grw-card h3{margin:0 0 10px;font-size:14px;font-weight:900;}
        .grw-form{display:grid;gap:10px;}
        .grw-form label{font-weight:800;color:var(--grw-text);font-size:12px;}
        .grw-input,.grw-textarea{width:100%;padding:10px 12px;border:1px solid rgba(0,0,0,.12);border-radius:12px;outline:none;background:#fff;}
        .grw-textarea{min-height:92px;resize:vertical;}
        .grw-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
        .grw-row-3{display:grid;grid-template-columns:1fr 200px 120px;gap:10px;}
        .grw-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
        .grw-btn{padding:10px 14px;border-radius:12px;border:1px solid transparent;cursor:pointer;font-weight:900;}
        .grw-btn.primary{background:var(--grw-accent);color:#fff;}
        .grw-btn.ghost{background:#fff;border-color:rgba(26,115,232,.55);color:var(--grw-accent);}
        .grw-btn.danger{background:var(--grw-danger);color:#fff;}
        .grw-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;border:1px solid var(--grw-border);color:var(--grw-muted);font-size:12px;font-weight:800;}
        .grw-pill.on{color:var(--grw-ok);border-color:rgba(52,168,83,.35);background:rgba(52,168,83,.08);}
        .grw-reviews{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
        .grw-review{border:1px solid var(--grw-border);border-radius:14px;background:#fff;overflow:hidden;}
        .grw-review-top{padding:12px 12px 10px;display:flex;justify-content:space-between;gap:10px;align-items:flex-start;}
        .grw-stars{font-size:14px;letter-spacing:1px;color:#fbbc04;}
        .grw-meta{font-size:12px;color:var(--grw-muted);font-weight:800;}
        .grw-text{padding:0 12px 12px;color:var(--grw-text);line-height:1.5;font-size:13px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
        .grw-review-actions{padding:12px;border-top:1px solid var(--grw-border);display:flex;justify-content:space-between;gap:10px;align-items:center;}
        .grw-toggle{background:#fff;border:1px solid var(--grw-border);border-radius:10px;padding:8px 10px;cursor:pointer;font-weight:900;color:rgba(0,0,0,.7);}
        .grw-editor{padding:12px;border-top:1px dashed rgba(0,0,0,.12);display:none;}
        .grw-review.is-open .grw-editor{display:block;}
        @media (max-width: 1100px){.grw-reviews{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media (max-width: 720px){.grw-reviews{grid-template-columns:1fr;}.grw-row,.grw-row-3{grid-template-columns:1fr;}}
    </style>

    <div class="grw-wrap">
        <div class="grw-head">
            <h2 class="grw-title">GoogleReview Dashboard</h2>
            <?php if ($message !== ''): ?>
                <p class="grw-msg"><?= htmlspecialchars((string)$message) ?></p>
            <?php endif; ?>
        </div>

        <div class="grw-grid">
            <div class="grw-card">
                <h3>Ayarlar</h3>
                <form method="post" class="grw-form">
                    <?= Form::getTokenInput(); ?>

                    <div class="grw-row">
                        <div>
                            <label>Başlık (sol kart)</label>
                            <input class="grw-input" name="cHeading" value="<?= htmlspecialchars((string)($settings->cHeading ?? 'Ihr Unternehmen'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div>
                            <label>Alt başlık</label>
                            <input class="grw-input" name="cSubheading" value="<?= htmlspecialchars((string)($settings->cSubheading ?? 'Basierend auf 500 Bewertungen'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="grw-row">
                        <div>
                            <label>Rating (örn 4.5)</label>
                            <input class="grw-input" name="fRating" value="<?= htmlspecialchars((string)($settings->fRating ?? '4.5'), ENT_QUOTES, 'UTF-8') ?>" placeholder="4.5">
                        </div>
                        <div>
                            <label>Toplam yorum</label>
                            <input class="grw-input" type="number" name="nTotalReviews" value="<?= (int)($settings->nTotalReviews ?? 500) ?>">
                        </div>
                    </div>

                    <div class="grw-row">
                        <div>
                            <label>"Bewertung schreiben" URL</label>
                            <input class="grw-input" name="cWriteUrl" value="<?= htmlspecialchars((string)($settings->cWriteUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
                        </div>
                        <div>
                            <label>"Alle ansehen" URL</label>
                            <input class="grw-input" name="cAllUrl" value="<?= htmlspecialchars((string)($settings->cAllUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
                        </div>
                    </div>

                    <div class="grw-row">
                        <div>
                            <label>Enjeksiyon konumu</label>
                            <select class="grw-input" name="cInjectMode">
                                <?php $mode = (string)($settings->cInjectMode ?? 'after_cardproduct'); ?>
                                <option value="after_cardproduct" <?= $mode === 'after_cardproduct' ? 'selected' : '' ?>>CardProduct sonrası (önerilen)</option>
                                <option value="after_configurator" <?= $mode === 'after_configurator' ? 'selected' : '' ?>>Konfigurator sonrası</option>
                                <option value="after_selector" <?= $mode === 'after_selector' ? 'selected' : '' ?>>Custom CSS selector sonrası</option>
                            </select>
                        </div>
                        <div>
                            <label>Custom selector (mode=custom)</label>
                            <input class="grw-input" name="cInjectSelector" value="<?= htmlspecialchars((string)($settings->cInjectSelector ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="örn: div#content veya .some-class">
                        </div>
                    </div>

                    <div class="grw-actions">
                        <button type="submit" name="grSaveSettings" value="1" class="grw-btn primary">Kaydet</button>
                        <span class="grw-pill">Önizleme: <?= htmlspecialchars((string)($settings->fRating ?? '4.5')) ?> / <?= (int)($settings->nTotalReviews ?? 500) ?></span>
                        <span class="grw-pill">Letzte Bewertung: <?= htmlspecialchars($lastUpdated !== '' ? $lastUpdated : (string)($settings->dUpdated ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </form>
            </div>

            <div class="grw-card">
                <h3>Yeni Yorum Ekle</h3>
                <form method="post" class="grw-form">
                    <?= Form::getTokenInput(); ?>

                    <div class="grw-row-3">
                        <div>
                            <label>İsim</label>
                            <input class="grw-input" name="cAuthor" required>
                        </div>
                        <div>
                            <label>Zaman (örn vor 2 Tagen)</label>
                            <input class="grw-input" name="cTimeText">
                        </div>
                        <div>
                            <label>Yıldız</label>
                            <input class="grw-input" type="number" name="nStars" min="1" max="5" value="5">
                        </div>
                    </div>

                    <div>
                        <label>Yorum</label>
                        <textarea class="grw-textarea" name="cText" required></textarea>
                    </div>

                    <div class="grw-row">
                        <div>
                            <label>Avatar URL (opsiyonel)</label>
                            <input class="grw-input" name="cAvatarUrl" placeholder="https://...">
                        </div>
                        <div>
                            <label>Sıra</label>
                            <input class="grw-input" type="number" name="nSort" value="100">
                        </div>
                    </div>

                    <div class="grw-actions">
                        <label class="grw-pill on"><input type="checkbox" name="nActive" value="1" checked style="margin-right:8px;"> Aktif</label>
                        <button type="submit" name="grAddReview" value="1" class="grw-btn primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grw-card" style="margin-top:14px;">
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <h3 style="margin:0;">Yorumlar</h3>
                <span class="grw-pill"><?= count($items) ?> kayıt</span>
            </div>

            <?php if (!count($items)): ?>
                <p style="margin:10px 0 0; color: rgba(0,0,0,.6);">Henüz yorum yok.</p>
            <?php endif; ?>

            <div class="grw-reviews" style="margin-top:12px;">
                <?php foreach ($items as $it): ?>
                    <?php
                        $stars = (int)($it->nStars ?? 5);
                        $isActive = !empty($it->nActive);
                    ?>
                    <div class="grw-review <?= $isActive ? '' : 'is-inactive' ?>">
                        <div class="grw-review-top">
                            <div>
                                <div style="font-weight:900; font-size:13px;"><?= htmlspecialchars((string)($it->cAuthor ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="grw-meta"><?= htmlspecialchars((string)($it->cTimeText ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div class="grw-stars" aria-label="<?= $stars ?> Sterne"><?= htmlspecialchars(grStars($stars), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="grw-meta">#<?= (int)$it->kID ?> · sort <?= (int)($it->nSort ?? 100) ?></div>
                            </div>
                        </div>
                        <div class="grw-text"><?= htmlspecialchars((string)($it->cText ?? ''), ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="grw-review-actions">
                            <span class="grw-pill <?= $isActive ? 'on' : '' ?>">
                                <?= $isActive ? 'Aktif' : 'Pasif' ?>
                            </span>
                            <button type="button" class="grw-toggle" onclick="this.closest('.grw-review').classList.toggle('is-open')">Düzenle</button>
                        </div>

                        <div class="grw-editor">
                            <form method="post" class="grw-form">
                                <?= Form::getTokenInput(); ?>
                                <input type="hidden" name="kID" value="<?= (int)$it->kID ?>">

                                <div class="grw-row-3">
                                    <div>
                                        <label>İsim</label>
                                        <input class="grw-input" name="cAuthor" value="<?= htmlspecialchars((string)($it->cAuthor ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div>
                                        <label>Zaman</label>
                                        <input class="grw-input" name="cTimeText" value="<?= htmlspecialchars((string)($it->cTimeText ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div>
                                        <label>Yıldız</label>
                                        <input class="grw-input" type="number" name="nStars" min="1" max="5" value="<?= $stars ?>">
                                    </div>
                                </div>

                                <div>
                                    <label>Yorum</label>
                                    <textarea class="grw-textarea" name="cText"><?= htmlspecialchars((string)($it->cText ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>

                                <div class="grw-row">
                                    <div>
                                        <label>Avatar URL</label>
                                        <input class="grw-input" name="cAvatarUrl" value="<?= htmlspecialchars((string)($it->cAvatarUrl ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://...">
                                    </div>
                                    <div>
                                        <label>Sıra</label>
                                        <input class="grw-input" type="number" name="nSort" value="<?= (int)($it->nSort ?? 100) ?>">
                                    </div>
                                </div>

                                <div class="grw-actions" style="justify-content:space-between;">
                                    <label class="grw-pill <?= $isActive ? 'on' : '' ?>">
                                        <input type="checkbox" name="nActive" value="1" <?= $isActive ? 'checked' : '' ?> style="margin-right:8px;">
                                        Aktif
                                    </label>
                                    <div class="grw-actions">
                                        <button type="submit" name="grSaveReview" value="1" class="grw-btn primary">Kaydet</button>
                                        <button type="submit" name="grDeleteReview" value="1" class="grw-btn danger" onclick="return confirm('Bu yorumu silmek istediğinize emin misiniz?')">Sil</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
        </div>
    </div>
</div>

