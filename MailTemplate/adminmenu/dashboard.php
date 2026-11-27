<?php

declare(strict_types=1);

use JTL\Helpers\Form;
use Plugin\MailTemplate\Config;
use Plugin\MailTemplate\Demo;

require_once dirname(__DIR__) . '/src/Config.php';
require_once dirname(__DIR__) . '/src/Labels.php';
require_once dirname(__DIR__) . '/src/Designer.php';
require_once dirname(__DIR__) . '/src/Demo.php';

if (!function_exists('mtH')) {
    function mtH(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    function mtUrl(array $set = []): string
    {
        $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''));
        parse_str((string)($p['query'] ?? ''), $q);
        unset($q['mt_lang']);
        foreach ($set as $k => $v) {
            $q[$k] = $v;
        }

        return (string)($p['path'] ?? '') . ($q !== [] ? '?' . http_build_query($q) : '');
    }
}

$message = '';
$error   = '';
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
            Gilt für die Mail, die Kunden direkt nach dem Kauf erhalten (Bestellbestätigung).
            Betreff, Textversion, AGB/Widerruf und Anhänge bleiben unverändert aus den Shop-Mailvorlagen.
            Vorschau mit Beispieldaten ·
            <a href="<?= mtH(mtUrl(['mt_lang' => 'de'])) ?>"<?= $lang === 'de' ? ' class="fw-bold"' : '' ?>>DE</a> /
            <a href="<?= mtH(mtUrl(['mt_lang' => 'en'])) ?>"<?= $lang === 'en' ? ' class="fw-bold"' : '' ?>>EN</a>
        </p>

        <form method="post">
            <?= Form::getTokenInput() ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="mt_enabled" name="enabled" value="1"<?= $cfg['enabled'] === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="mt_enabled"><strong>Neues Mail-Design aktiv</strong> (aus = Standard-Vorlage des Shops)</label>
            </div>

            <div class="row g-3 mb-4">
                <?php foreach (Config::DESIGNS as $key => $meta):
                    $previewHtml = '';
                    $previewErr  = '';
                    try {
                        $previewHtml = Demo::render($key, $lang);
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
