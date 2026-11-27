<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use Plugin\MailTemplate\Config;
use Plugin\MailTemplate\Demo;
use Plugin\MailTemplate\TestMail;

require_once dirname(__DIR__) . '/src/Config.php';
require_once dirname(__DIR__) . '/src/Labels.php';
require_once dirname(__DIR__) . '/src/Designer.php';
require_once dirname(__DIR__) . '/src/Demo.php';
require_once dirname(__DIR__) . '/src/TestMail.php';

if (!function_exists('mtH')) {
    function mtH(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    function mtUrl(array $set = []): string
    {
        $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''));
        parse_str((string)($p['query'] ?? ''), $q);
        foreach ($set as $k => $v) {
            $q[$k] = $v;
        }

        return (string)($p['path'] ?? '') . ($q !== [] ? '?' . http_build_query($q) : '');
    }
}

$message = '';
$error   = '';
if (strcasecmp((string)($_SERVER['REQUEST_METHOD'] ?? ''), 'POST') === 0 && !empty($_POST['mt_test'])) {
    if (Form::validateToken()) {
        try {
            $err = TestMail::send(
                trim((string)($_POST['test_to'] ?? '')),
                (string)($_POST['test_design'] ?? ''),
                (string)($_POST['test_kind'] ?? 'confirmation'),
                (($_POST['test_lang'] ?? 'de') === 'en') ? 'en' : 'de'
            );
            if ($err === '') {
                $message = 'Testmail wurde an ' . trim((string)$_POST['test_to']) . ' gesendet.';
            } else {
                $error = $err;
            }
        } catch (\Throwable $e) {
            $error = 'Testmail fehlgeschlagen: ' . $e->getMessage();
        }
    } else {
        $error = 'Sicherheits-Token ungültig, bitte Seite neu laden.';
    }
}
if (strcasecmp((string)($_SERVER['REQUEST_METHOD'] ?? ''), 'POST') === 0 && !empty($_POST['mt_save'])) {
    if (Form::validateToken()) {
        try {
            Config::save($_POST);
            $message = 'Einstellungen gespeichert.';
        } catch (\Throwable $e) {
            $error = 'Speichern fehlgeschlagen: ' . $e->getMessage();
        }
    } else {
        $error = 'Sicherheits-Token ungültig, bitte Seite neu laden.';
    }
}

$cfg  = Config::all();
$lang = (($_GET['mt_lang'] ?? 'de') === 'en') ? 'en' : 'de';
$kinds = [
    'confirmation' => 'Bestellbestätigung',
    'paid'         => 'Zahlungseingang',
    'shipped'      => 'Versandbestätigung',
];
$kind = isset($kinds[(string)($_GET['mt_kind'] ?? '')]) ? (string)$_GET['mt_kind'] : 'confirmation';
?>
<style>
    .mt-frame { width: 352px; max-width: 100%; height: 700px; overflow: hidden; border: 1px solid #d5d9e0; border-radius: 6px; margin: 0 auto 12px; background: #fff; }
    .mt-frame iframe { width: 640px; height: 1273px; border: 0; transform: scale(.55); transform-origin: 0 0; }
    .mt-design.is-active { border-color: #198754 !important; box-shadow: 0 0 0 2px rgba(25,135,84,.25); }
    .mt-design label.mt-pick { cursor: pointer; display: block; }
</style>
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">MailTemplate — Bestellbestätigung</h3>
    </div>
    <div class="card-body">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= mtH($message) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= mtH($error) ?></div><?php endif; ?>

        <p class="text-muted small mb-3">
            Gilt für die Mails nach dem Kauf: Bestellbestätigung, Zahlungseingang und Versandbestätigung.
            Betreff, Textversion, Rechtstexte und Anhänge bleiben unverändert aus den Shop-Mailvorlagen.
            Vorschau mit Beispieldaten ·
            <?php foreach ($kinds as $kk => $kl): ?>
                <a href="<?= mtH(mtUrl(['mt_kind' => $kk])) ?>"<?= $kind === $kk ? ' class="fw-bold"' : '' ?>><?= mtH($kl) ?></a> |
            <?php endforeach; ?>
            <a href="<?= mtH(mtUrl(['mt_lang' => 'de'])) ?>"<?= $lang === 'de' ? ' class="fw-bold"' : '' ?>>DE</a> /
            <a href="<?= mtH(mtUrl(['mt_lang' => 'en'])) ?>"<?= $lang === 'en' ? ' class="fw-bold"' : '' ?>>EN</a>
        </p>

        <form method="post">
            <?= Form::getTokenInput() ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="mt_enabled" name="enabled" value="1"<?= $cfg['enabled'] === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="mt_enabled"><strong>Neues Mail-Design aktiv</strong> (aus = Standard-Vorlagen des Shops)</label>
            </div>
            <div class="mb-3 small">
                <label class="me-3"><input type="checkbox" name="apply_paid" value="1"<?= $cfg['apply_paid'] === '1' ? ' checked' : '' ?>> auch für Zahlungseingang</label>
                <label class="me-3"><input type="checkbox" name="apply_shipped" value="1"<?= $cfg['apply_shipped'] === '1' ? ' checked' : '' ?>> auch für Versandbestätigung</label>
                <label><input type="checkbox" name="show_images" value="1"<?= $cfg['show_images'] === '1' ? ' checked' : '' ?>> Artikelbilder anzeigen</label>
            </div>

            <div class="row g-3 mb-4">
                <?php foreach (Config::DESIGNS as $key => $meta):
                    $previewHtml = '';
                    $previewErr  = '';
                    try {
                        $previewHtml = Demo::render($key, $lang, $kind);
                    } catch (\Throwable $e) {
                        $previewErr = $e->getMessage();
                    }
                    ?>
                    <div class="col-12 col-lg-4">
                        <div class="card h-100 mt-design<?= $cfg['design'] === $key ? ' is-active' : '' ?>">
                            <div class="card-body text-center">
                                <label class="mt-pick mb-2">
                                    <input type="radio" name="design" value="<?= mtH($key) ?>"<?= $cfg['design'] === $key ? ' checked' : '' ?>>
                                    <strong class="h5 ms-1"><?= mtH($meta['label']) ?></strong>
                                </label>
                                <p class="small text-muted" style="min-height:40px"><?= mtH($meta['desc']) ?></p>
                                <?php if ($previewErr !== ''): ?>
                                    <div class="alert alert-warning small">Vorschau nicht verfügbar: <?= mtH($previewErr) ?></div>
                                <?php else: ?>
                                    <div class="mt-frame"><iframe title="<?= mtH($meta['label']) ?>" srcdoc="<?= mtH($previewHtml) ?>"></iframe></div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-open">Vollansicht</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h5>Anpassungen</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="mt_accent">Akzentfarbe</label>
                    <input class="form-control" type="text" id="mt_accent" name="accent" maxlength="7" placeholder="#4f46e5" value="<?= mtH($cfg['accent']) ?>">
                    <div class="form-text">Leer = Standardfarbe des Designs (Format #rrggbb).</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="mt_tagline">Slogan (unter der Überschrift)</label>
                    <input class="form-control" type="text" id="mt_tagline" name="tagline" maxlength="120" value="<?= mtH($cfg['tagline']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="mt_logo">Logo-URL (https)</label>
                    <input class="form-control" type="text" id="mt_logo" name="logo_url" maxlength="500" value="<?= mtH($cfg['logo_url']) ?>">
                    <div class="form-text">Leer = Shop-Logo.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="mt_note">Persönliche Nachricht (optional)</label>
                    <textarea class="form-control" id="mt_note" name="note" rows="2" maxlength="600"><?= mtH($cfg['note']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mt_smail">Support-E-Mail (Fußbereich)</label>
                    <input class="form-control" type="email" id="mt_smail" name="support_mail" value="<?= mtH($cfg['support_mail']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mt_sphone">Support-Telefon (Fußbereich)</label>
                    <input class="form-control" type="text" id="mt_sphone" name="support_phone" maxlength="40" value="<?= mtH($cfg['support_phone']) ?>">
                </div>
            </div>

            <button type="submit" name="mt_save" value="1" class="btn btn-primary mt-4">Speichern</button>
            <span class="text-muted small ms-2">Die Vorschau aktualisiert sich nach dem Speichern.</span>
        </form>

        <hr class="my-4">
        <h5>Testmail senden</h5>
        <form method="post" class="row g-2 align-items-end">
            <?= Form::getTokenInput() ?>
            <div class="col-md-4">
                <label class="form-label" for="mt_to">Empfänger</label>
                <input class="form-control" type="email" id="mt_to" name="test_to" required placeholder="name@example.com">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="mt_td">Design</label>
                <select class="form-select" id="mt_td" name="test_design">
                    <?php foreach (Config::DESIGNS as $key => $meta): ?>
                        <option value="<?= mtH($key) ?>"<?= $cfg['design'] === $key ? ' selected' : '' ?>><?= mtH($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="mt_tk">Mail-Art</label>
                <select class="form-select" id="mt_tk" name="test_kind">
                    <?php foreach ($kinds as $kk => $kl): ?>
                        <option value="<?= mtH($kk) ?>"<?= $kind === $kk ? ' selected' : '' ?>><?= mtH($kl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label" for="mt_tl">Sprache</label>
                <select class="form-select" id="mt_tl" name="test_lang">
                    <option value="de"<?= $lang === 'de' ? ' selected' : '' ?>>DE</option>
                    <option value="en"<?= $lang === 'en' ? ' selected' : '' ?>>EN</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" name="mt_test" value="1" class="btn btn-outline-primary w-100">Testmail senden</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">Sendet die Vorschau (Beispieldaten) mit den zuletzt gespeicherten Anpassungen.</p>
    </div>
</div>
<script>
    document.querySelectorAll('.mt-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var frame = btn.parentNode.querySelector('iframe');
            if (!frame) { return; }
            var blob = new Blob([frame.getAttribute('srcdoc') || ''], {type: 'text/html'});
            window.open(URL.createObjectURL(blob), '_blank');
        });
    });
</script>
