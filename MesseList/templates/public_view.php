<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $products */
/** @var list<array{name:string,groups:list<array{name:string,series:list<string>}>}> $tabs */
/** @var list<array{name:string,series:list<string>}> $groups */
/** @var string $assetBase */

$h = static function (string $s): string {
    return \htmlspecialchars($s, \ENT_QUOTES, 'UTF-8');
};
$json = \json_encode($products, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]';
$tabsJson = \json_encode($tabs ?? [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]';
$groupsJson = \json_encode($groups ?? [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Messe Liste · DS-Serie</title>
    <link rel="stylesheet" href="<?= $h($assetBase) ?>/css/messe-list.css?v=143">
</head>
<body>
    <header class="ml-head">
        <div>
            <p class="ml-kicker">Messe · Familie-Serie</p>
            <h1>Messe Lagerliste</h1>
        </div>
        <div class="ml-total-box" id="mlGrand">
            <span>Gesamt</span>
            <strong>0,00 €</strong>
        </div>
    </header>

    <main class="ml-wrap">
        <p class="ml-hint">Plus (+) tippen, um den Artikel in die Gesamtliste zu legen. Stückzahl und Rabatt dort anpassen.</p>
        <div class="ml-layout">
            <div id="mlList"></div>
            <aside class="ml-cart" id="mlCart">
                <h2>Gesamtliste</h2>
                <div id="mlCartBody" class="ml-cart-empty">Noch keine Artikel.</div>
                <button type="button" class="ml-offer-open" id="mlOfferOpen" hidden>Angebot erstellen</button>
            </aside>
        </div>
    </main>

    <div class="ml-toast" id="mlToast" hidden role="status" aria-live="polite"></div>
    <button type="button" class="ml-cart-fab" id="mlCartFab" aria-label="Zur Gesamtliste">
        <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true">
            <path fill="currentColor" d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 2-2-.9-2-2-2zM7.2 14.8h9.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 21.1 6H6.21L5.27 4H2v2h2l3.6 7.59-1.35 2.45C5.52 16.37 6.48 18 8 18h12v-2H8.1l1.1-2z"/>
        </svg>
        <span class="ml-cart-fab-count" id="mlCartFabCount" hidden>0</span>
    </button>

    <div class="ml-lightbox" id="mlLightbox" hidden>
        <button type="button" class="ml-lb-close" aria-label="Schließen">×</button>
        <img alt="">
    </div>

    <div class="ml-offer-modal" id="mlOfferModal" hidden>
        <div class="ml-offer-dialog" role="dialog" aria-labelledby="mlOfferTitle">
            <button type="button" class="ml-lb-close" id="mlOfferClose" aria-label="Schließen">×</button>
            <h3 id="mlOfferTitle">Angebot erstellen</h3>
            <p>Bitte Kontaktdaten eintragen. Das A4-Angebot wird an die angegebene E-Mail gesendet.</p>
            <form id="mlOfferForm">
                <div class="ml-offer-grid">
                    <label>Vorname <input type="text" name="vorname" required maxlength="80" autocomplete="given-name"></label>
                    <label>Nachname <input type="text" name="nachname" required maxlength="80" autocomplete="family-name"></label>
                    <label>Telefon <input type="tel" name="telefon" required maxlength="40" autocomplete="tel"></label>
                    <label>E-Mail <input type="email" name="email" required maxlength="160" autocomplete="email"></label>
                </div>
                <p class="ml-offer-msg" id="mlOfferMsg" hidden></p>
                <button type="submit" class="ml-offer-send" id="mlOfferSend">Angebot senden</button>
                <a class="ml-offer-download" id="mlOfferDownload" href="#" hidden>PDF-Angebot herunterladen</a>
            </form>
        </div>
    </div>

    <script>window.ML_PRODUCTS = <?= $json ?>; window.ML_TABS = <?= $tabsJson ?>; window.ML_GROUPS = <?= $groupsJson ?>; window.ML_QUOTE_URL = <?= \json_encode($quoteUrl ?? '', \JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= $h($assetBase) ?>/js/messe-list.js?v=143"></script>
</body>
</html>
