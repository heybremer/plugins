<?php

declare(strict_types=1);

use JTL\Shop;

$db = Shop::Container()->getDB();

// ── Ensure table ──────────────────────────────────────────────────────────────
try {
    $db->executeQuery(
        'CREATE TABLE IF NOT EXISTS twomosi_megoogletracking_settings (
            cKey   VARCHAR(100) NOT NULL,
            cValue TEXT         NOT NULL DEFAULT "",
            PRIMARY KEY (cKey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
} catch (\Throwable $e) {}

if (!function_exists('megt_h')) {
    function megt_h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

// ── Keys ─────────────────────────────────────────────────────────────────────
$keys = [
    'megt_ga4_active', 'megt_ga4_id',
    'megt_ads_active', 'megt_ads_id', 'megt_ads_label', 'megt_ads_value',
    'megt_gtm_active', 'megt_gtm_id',
    'megt_consent_mode', 'megt_anonymize_ip', 'megt_ecommerce',
];

// ── Save ─────────────────────────────────────────────────────────────────────
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['megt_save'])) {
    foreach ($keys as $k) {
        $val = trim((string)($_POST[$k] ?? ''));
        // Checkboxes: unchecked = '0'
        if (in_array($k, ['megt_ga4_active','megt_ads_active','megt_gtm_active','megt_consent_mode','megt_anonymize_ip','megt_ecommerce'], true)) {
            $val = isset($_POST[$k]) ? '1' : '0';
        }
        try {
            $db->executeQueryPrepared(
                'INSERT INTO twomosi_megoogletracking_settings (cKey, cValue) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE cValue = VALUES(cValue)',
                ['k' => $k, 'v' => $val]
            );
        } catch (\Throwable $e) {}
    }
    $saved = true;
}

// ── Load ─────────────────────────────────────────────────────────────────────
$cfg = [];
try {
    $rows = $db->getObjects('SELECT cKey, cValue FROM twomosi_megoogletracking_settings');
    foreach ($rows as $r) {
        $cfg[(string)$r->cKey] = (string)$r->cValue;
    }
} catch (\Throwable $e) {}

$defaults = [
    'megt_ga4_active' => '0', 'megt_ga4_id' => 'G-',
    'megt_ads_active' => '0', 'megt_ads_id' => 'AW-', 'megt_ads_label' => '', 'megt_ads_value' => 'gesamt_netto',
    'megt_gtm_active' => '0', 'megt_gtm_id' => 'GTM-',
    'megt_consent_mode' => '0', 'megt_anonymize_ip' => '0', 'megt_ecommerce' => '1',
];
foreach ($defaults as $k => $v) {
    if (!array_key_exists($k, $cfg)) {
        $cfg[$k] = $v;
    }
}

if (!function_exists('megt_checked')) {
    function megt_checked(array $cfg, string $key): string {
        return ((int)($cfg[$key] ?? 0) === 1) ? 'checked' : '';
    }
}
if (!function_exists('megt_val')) {
    function megt_val(array $cfg, string $key): string {
        return megt_h((string)($cfg[$key] ?? ''));
    }
}
if (!function_exists('megt_sel')) {
    function megt_sel(array $cfg, string $key, string $val): string {
        return ($cfg[$key] ?? '') === $val ? 'selected' : '';
    }
}
?>
<style>
.megt-wrap { max-width: 820px; }
.megt-section { background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; }
.megt-section-title { font-size: 1rem; font-weight: 700; color: #212529; margin-bottom: 1.1rem; padding-bottom: .5rem; border-bottom: 2px solid #f0f0f0; display: flex; align-items: center; gap: .5rem; }
.megt-badge { background: #0d6efd; color: #fff; font-size: .7rem; padding: 1px 7px; border-radius: 20px; font-weight: 600; }
.megt-badge-green { background: #198754; }
.megt-badge-orange { background: #fd7e14; }
.megt-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.megt-field { display: flex; flex-direction: column; gap: .3rem; margin-bottom: .8rem; }
.megt-field label { font-size: .83rem; font-weight: 600; color: #495057; }
.megt-field input[type=text], .megt-field select { border: 1px solid #ced4da; border-radius: 6px; padding: .4rem .7rem; font-size: .88rem; }
.megt-field input[type=text]:focus, .megt-field select:focus { border-color: #0d6efd; outline: none; box-shadow: 0 0 0 2px rgba(13,110,253,.15); }
.megt-toggle { display: flex; align-items: center; gap: .6rem; margin-bottom: .8rem; }
.megt-toggle label { font-size: .88rem; font-weight: 600; color: #212529; cursor: pointer; margin: 0; }
.megt-hint { font-size: .78rem; color: #6c757d; margin-top: .15rem; }
.megt-alert-success { background: #d1e7dd; color: #0a3622; border-radius: 6px; padding: .6rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
@media (max-width: 640px) { .megt-row { grid-template-columns: 1fr; } }
</style>

<div class="megt-wrap">
    <h2 class="mb-3" style="font-size:1.25rem;font-weight:800;">Me Google Tracking
        <small style="font-size:.8rem;color:#6c757d;font-weight:400;"> v1.2.1</small>
    </h2>

    <?php if ($saved): ?>
    <div class="megt-alert-success">✓ Einstellungen wurden gespeichert.</div>
    <?php endif; ?>

    <form method="post" action="">
    <input type="hidden" name="megt_save" value="1">

    <!-- Google Analytics 4 -->
    <div class="megt-section">
        <div class="megt-section-title">
            <span>Google Analytics 4</span>
            <span class="megt-badge megt-badge-blue">GA4</span>
        </div>
        <div class="megt-toggle">
            <input type="checkbox" id="megt_ga4_active" name="megt_ga4_active" value="1" <?= megt_checked($cfg, 'megt_ga4_active') ?>>
            <label for="megt_ga4_active">GA4 aktivieren</label>
        </div>
        <div class="megt-field">
            <label for="megt_ga4_id">Measurement-ID</label>
            <input type="text" id="megt_ga4_id" name="megt_ga4_id" value="<?= megt_val($cfg, 'megt_ga4_id') ?>" placeholder="G-XXXXXXXXXX">
            <span class="megt-hint">Ihre GA4 Measurement-ID, z. B. G-ABC123456</span>
        </div>
        <div class="megt-toggle">
            <input type="checkbox" id="megt_anonymize_ip" name="megt_anonymize_ip" value="1" <?= megt_checked($cfg, 'megt_anonymize_ip') ?>>
            <label for="megt_anonymize_ip">IP-Adresse anonymisieren</label>
        </div>
    </div>

    <!-- Google Ads -->
    <div class="megt-section">
        <div class="megt-section-title">
            <span>Google Ads</span>
            <span class="megt-badge megt-badge-orange">Ads</span>
        </div>
        <div class="megt-toggle">
            <input type="checkbox" id="megt_ads_active" name="megt_ads_active" value="1" <?= megt_checked($cfg, 'megt_ads_active') ?>>
            <label for="megt_ads_active">Conversion Tracking aktivieren</label>
        </div>
        <div class="megt-row">
            <div class="megt-field">
                <label for="megt_ads_id">Conversion ID</label>
                <input type="text" id="megt_ads_id" name="megt_ads_id" value="<?= megt_val($cfg, 'megt_ads_id') ?>" placeholder="AW-XXXXXXXXXX">
                <span class="megt-hint">z. B. AW-1234567890</span>
            </div>
            <div class="megt-field">
                <label for="megt_ads_label">Conversion Label</label>
                <input type="text" id="megt_ads_label" name="megt_ads_label" value="<?= megt_val($cfg, 'megt_ads_label') ?>" placeholder="AbC-Xyz1234">
            </div>
        </div>
        <div class="megt-field">
            <label for="megt_ads_value">Conversion Wert</label>
            <select id="megt_ads_value" name="megt_ads_value">
                <option value="gesamt_netto"   <?= megt_sel($cfg, 'megt_ads_value', 'gesamt_netto') ?>>Gesamtsumme Netto</option>
                <option value="gesamt_brutto"  <?= megt_sel($cfg, 'megt_ads_value', 'gesamt_brutto') ?>>Gesamtsumme Brutto</option>
                <option value="waren_netto"    <?= megt_sel($cfg, 'megt_ads_value', 'waren_netto') ?>>Warensumme Netto</option>
                <option value="waren_brutto"   <?= megt_sel($cfg, 'megt_ads_value', 'waren_brutto') ?>>Warensumme Brutto</option>
            </select>
        </div>
    </div>

    <!-- Google Tag Manager -->
    <div class="megt-section">
        <div class="megt-section-title">
            <span>Google Tag Manager</span>
            <span class="megt-badge" style="background:#6f42c1">GTM</span>
        </div>
        <div class="megt-hint mb-2" style="color:#856404;background:#fff3cd;border-radius:6px;padding:.4rem .8rem;margin-bottom:.8rem;">
            ⚠ Verwende GTM nur, wenn Analytics/Ads <strong>nicht</strong> direkt über dieses Plugin eingebunden sind, um Doppelzählung zu vermeiden.
        </div>
        <div class="megt-toggle">
            <input type="checkbox" id="megt_gtm_active" name="megt_gtm_active" value="1" <?= megt_checked($cfg, 'megt_gtm_active') ?>>
            <label for="megt_gtm_active">Google Tag Manager einbinden</label>
        </div>
        <div class="megt-field">
            <label for="megt_gtm_id">GTM Container-ID</label>
            <input type="text" id="megt_gtm_id" name="megt_gtm_id" value="<?= megt_val($cfg, 'megt_gtm_id') ?>" placeholder="GTM-XXXXXXX">
        </div>
    </div>

    <!-- Consent Mode & E-Commerce -->
    <div class="megt-section">
        <div class="megt-section-title">
            <span>Consent Mode &amp; E-Commerce</span>
            <span class="megt-badge megt-badge-green">DSGVO</span>
        </div>
        <div class="megt-toggle">
            <input type="checkbox" id="megt_consent_mode" name="megt_consent_mode" value="1" <?= megt_checked($cfg, 'megt_consent_mode') ?>>
            <label for="megt_consent_mode">Google Consent Mode v2 aktivieren</label>
        </div>
        <p class="megt-hint">Setzt alle Consent-Kategorien initial auf <code>denied</code>. Ihr CMP muss die Freigaben via <code>gtag('consent','update',…)</code> setzen.</p>
        <div class="megt-toggle mt-3">
            <input type="checkbox" id="megt_ecommerce" name="megt_ecommerce" value="1" <?= megt_checked($cfg, 'megt_ecommerce') ?>>
            <label for="megt_ecommerce">Enhanced E-Commerce Events (dataLayer) aktivieren</label>
        </div>
        <p class="megt-hint">Sendet <code>view_item</code>, <code>add_to_cart</code>, <code>remove_from_cart</code>, <code>begin_checkout</code> und <code>purchase</code> Events an den dataLayer.</p>
    </div>

    <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
</div>
