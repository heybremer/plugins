<?php

declare(strict_types=1);

/**
 * Zusatz-Auswertungen: Konversion nach Herkunft, First- vs. Last-Touch,
 * Kampagnen/Landing-Pages, Stornos. Nur Admin-Dashboard, 90 Tage.
 */

if (!function_exists('sp_channel_group')) {
    /** Kanal (+ Label) -> Herkunftsgruppe (gleiche Gruppen wie bei den Besuchern). */
    function sp_channel_group(string $channel, string $label = ''): string
    {
        if (\in_array($channel, ['google_ads', 'google_shopping_organic'], true)) {
            return 'Google';
        }
        if ($channel === 'seo') {
            return \stripos($label, 'google') !== false ? 'Google' : 'Andere Suchmaschinen';
        }
        if ($channel === 'bing_ads') {
            return 'Andere Suchmaschinen';
        }
        if (\strpos($channel, 'chatgpt_') === 0) {
            return 'ChatGPT';
        }
        if ($channel === 'meta_ads') {
            return 'Meta';
        }
        if ($channel === 'direct') {
            return 'Direkt';
        }

        return 'Sonstige';
    }
}

if (!function_exists('sp_referer_group')) {
    function sp_referer_group(string $ref): string
    {
        $r = \strtolower(\trim($ref));
        if ($r === '') {
            return 'Direkt';
        }
        if (\preg_match('/(^|\\.)google\\./', $r) === 1) {
            return 'Google';
        }
        if (\preg_match('/(^|\\.)(bing|yahoo|duckduckgo|ecosia|startpage)\\./', $r) === 1) {
            return 'Andere Suchmaschinen';
        }
        if (\preg_match('/(chatgpt|openai)/', $r) === 1) {
            return 'ChatGPT';
        }
        if (\preg_match('/(^|\\.)(facebook|instagram|fb|l\\.facebook|m\\.facebook)\\./', $r) === 1) {
            return 'Meta';
        }

        return 'Sonstige';
    }
}

if (!function_exists('sp_load_insights')) {
    /**
     * @return array<string, mixed>
     */
    function sp_load_insights(\JTL\DB\DbInterface $db): array
    {
        $out = ['conv' => [], 'touch' => [], 'campaigns' => [], 'landing' => [], 'storno' => [], 'errors' => []];
        $valid = '(b.cStatus IS NULL OR b.cStatus <> -1)';

        // 1) Konversion nach Herkunft (30 Tage, ohne Bots)
        try {
            $visits = [];
            $rows = $db->getObjects(
                "SELECT cReferer, COUNT(*) AS n FROM tbesucherarchiv
                  WHERE dZeit >= NOW() - INTERVAL 30 DAY AND kBesucherBot = 0
                  GROUP BY cReferer"
            );
            foreach ($rows as $r) {
                $g = sp_referer_group((string)($r->cReferer ?? ''));
                $visits[$g] = ($visits[$g] ?? 0) + (int)$r->n;
            }
            $orders = [];
            $rows = $db->getObjects(
                "SELECT a.cChannel, a.cChannelLabel, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme),0) AS f
                   FROM twomosi_sales_attribution a
                   JOIN tbestellung b ON b.kBestellung = a.kBestellung
                  WHERE b.dErstellt >= NOW() - INTERVAL 30 DAY AND $valid
                  GROUP BY a.cChannel, a.cChannelLabel"
            );
            foreach ($rows as $r) {
                $g = sp_channel_group((string)$r->cChannel, (string)$r->cChannelLabel);
                $orders[$g]['n'] = ($orders[$g]['n'] ?? 0) + (int)$r->n;
                $orders[$g]['f'] = ($orders[$g]['f'] ?? 0.0) + (float)$r->f;
            }
            foreach (\array_unique(\array_merge(\array_keys($visits), \array_keys($orders))) as $g) {
                $v = (int)($visits[$g] ?? 0);
                $n = (int)($orders[$g]['n'] ?? 0);
                $out['conv'][] = [
                    'group' => $g, 'visits' => $v, 'orders' => $n,
                    'revenue' => (float)($orders[$g]['f'] ?? 0.0),
                    'rate' => $v > 0 ? $n * 100 / $v : null,
                ];
            }
            \usort($out['conv'], static fn ($a, $b) => $b['visits'] <=> $a['visits']);
        } catch (\Throwable $e) {
            $out['errors'][] = 'Konversion: ' . $e->getMessage();
        }

        // 2) First- vs. Last-Touch (90 Tage)
        try {
            $rows = $db->getObjects(
                "SELECT COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.cAttributionJson,'$.first.channel')),'null'),'(unbekannt)') AS fch,
                        a.cChannel AS lch, COUNT(*) AS n
                   FROM twomosi_sales_attribution a
                   JOIN tbestellung b ON b.kBestellung = a.kBestellung
                  WHERE b.dErstellt >= NOW() - INTERVAL 90 DAY AND $valid
                  GROUP BY fch, lch"
            );
            $first = [];
            $last = [];
            foreach ($rows as $r) {
                $first[(string)$r->fch] = ($first[(string)$r->fch] ?? 0) + (int)$r->n;
                $last[(string)$r->lch] = ($last[(string)$r->lch] ?? 0) + (int)$r->n;
            }
            foreach (\array_unique(\array_merge(\array_keys($first), \array_keys($last))) as $ch) {
                $out['touch'][] = ['channel' => $ch, 'first' => (int)($first[$ch] ?? 0), 'last' => (int)($last[$ch] ?? 0)];
            }
            \usort($out['touch'], static fn ($a, $b) => \max($b['first'], $b['last']) <=> \max($a['first'], $a['last']));
        } catch (\Throwable $e) {
            $out['errors'][] = 'Touch: ' . $e->getMessage();
        }

        // 3) Kampagnen + Landing-Pages (90 Tage)
        try {
            $out['campaigns'] = $db->getObjects(
                "SELECT a.cChannel, LEFT(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.cAttributionJson,'$.picked.source_detail')),''),70) AS d,
                        COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme),0) AS f
                   FROM twomosi_sales_attribution a
                   JOIN tbestellung b ON b.kBestellung = a.kBestellung
                  WHERE b.dErstellt >= NOW() - INTERVAL 90 DAY AND $valid AND a.cChannel NOT IN ('direct')
                  GROUP BY a.cChannel, d ORDER BY n DESC LIMIT 20"
            );
            $out['landing'] = $db->getObjects(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(a.cAttributionJson,'$.picked.landing')) AS lp, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme),0) AS f
                   FROM twomosi_sales_attribution a
                   JOIN tbestellung b ON b.kBestellung = a.kBestellung
                  WHERE b.dErstellt >= NOW() - INTERVAL 90 DAY AND $valid
                    AND JSON_EXTRACT(a.cAttributionJson,'$.picked.landing') IS NOT NULL
                  GROUP BY lp ORDER BY n DESC LIMIT 15"
            );
        } catch (\Throwable $e) {
            $out['errors'][] = 'Kampagnen: ' . $e->getMessage();
        }

        // 4) Stornos (90 Tage)
        try {
            $out['storno'] = $db->getObjects(
                "SELECT COALESCE(a.cChannel,'(keine)') AS ch, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme),0) AS f
                   FROM tbestellung b
                   LEFT JOIN twomosi_sales_attribution a ON a.kBestellung = b.kBestellung
                  WHERE b.dErstellt >= NOW() - INTERVAL 90 DAY AND b.cStatus = -1
                  GROUP BY ch ORDER BY n DESC"
            );
        } catch (\Throwable $e) {
            $out['errors'][] = 'Storno: ' . $e->getMessage();
        }

        return $out;
    }
}

if (!function_exists('sp_render_visits_and_costs')) {
    /** Besuche pro Kanal (eigener Zähler) + Kosten/ROAS. */
    function sp_render_visits_and_costs(\JTL\DB\DbInterface $db): void
    {
        $msg = '';
        $valid = '(b.cStatus IS NULL OR b.cStatus <> -1)';
        $channels = ['google_ads', 'bing_ads', 'meta_ads', 'chatgpt_ads', 'email', 'utm_campaign', 'other'];
        try {
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS twomosi_sales_cost (
                    cMonth CHAR(7) NOT NULL,
                    cChannel VARCHAR(64) NOT NULL,
                    fCost DECIMAL(12,2) NOT NULL DEFAULT 0,
                    PRIMARY KEY (cMonth, cChannel)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        } catch (\Throwable $e) {
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sp_cost_save']) && \JTL\Helpers\Form::validateToken()) {
            $m = (string)($_POST['sp_cost_month'] ?? '');
            $c = (string)($_POST['sp_cost_channel'] ?? '');
            $v = (float)\str_replace(',', '.', (string)($_POST['sp_cost_value'] ?? '0'));
            if (\preg_match('/^\d{4}-\d{2}$/', $m) === 1 && \in_array($c, $channels, true) && $v >= 0) {
                try {
                    $db->executeQueryPrepared(
                        'INSERT INTO twomosi_sales_cost (cMonth, cChannel, fCost) VALUES (:m, :c, :v)
                         ON DUPLICATE KEY UPDATE fCost = VALUES(fCost)',
                        ['m' => $m, 'c' => $c, 'v' => $v]
                    );
                    $msg = 'Kosten gespeichert.';
                } catch (\Throwable $e) {
                    $msg = 'Fehler: ' . $e->getMessage();
                }
            }
        }

        // Wochenreport-Empfänger (E-Mail) + Testversand
        $mailRow = null;
        try {
            $db->executeQuery("INSERT IGNORE INTO twomosi_sales_config (cKey, cValue) VALUES ('report_email', '')");
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sp_mail_save']) && \JTL\Helpers\Form::validateToken()) {
                $e = \trim((string)($_POST['sp_report_email'] ?? ''));
                if ($e === '' || \filter_var($e, \FILTER_VALIDATE_EMAIL) !== false) {
                    $db->executeQueryPrepared("UPDATE twomosi_sales_config SET cValue = :v WHERE cKey = 'report_email'", ['v' => $e]);
                    $msg = $e === '' ? 'Wochenreport deaktiviert.' : 'Wochenreport-Empfänger gespeichert.';
                    if ($e !== '' && isset($_POST['sp_mail_test'])) {
                        $ok  = \Plugin\SalesProduct\Cron\WeeklyReportJob::sendMail($e, \Plugin\SalesProduct\Cron\WeeklyReportJob::buildHtml($db));
                        $msg .= $ok ? ' Testmail gesendet.' : ' Testmail fehlgeschlagen (mail()).';
                    }
                } else {
                    $msg = 'Ungültige E-Mail-Adresse.';
                }
            }
            $mailRow = $db->getSingleObject("SELECT cValue FROM twomosi_sales_config WHERE cKey = 'report_email'");
        } catch (\Throwable $e) {
            $msg = $msg !== '' ? $msg : 'Wochenreport: ' . $e->getMessage();
        }
        $reportEmail = (string)($mailRow->cValue ?? '');

        // Besuche pro Kanal (eigener Zähler, ab Erfassungsbeginn, max. 30 Tage)
        $visitRows = [];
        $since = null;
        try {
            $s = $db->getSingleObject('SELECT MIN(dDay) AS d FROM twomosi_sales_visits WHERE dDay >= CURDATE() - INTERVAL 30 DAY');
            $since = $s !== null && $s->d !== null ? (string)$s->d : null;
            if ($since !== null) {
                $v = [];
                foreach ($db->getObjects('SELECT cChannel, SUM(nVisits) AS n FROM twomosi_sales_visits WHERE dDay >= :d GROUP BY cChannel', ['d' => $since]) as $r) {
                    $v[(string)$r->cChannel] = (int)$r->n;
                }
                $o = [];
                foreach ($db->getObjects(
                    "SELECT a.cChannel, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme),0) AS f
                       FROM twomosi_sales_attribution a JOIN tbestellung b ON b.kBestellung = a.kBestellung
                      WHERE b.dErstellt >= :d AND $valid GROUP BY a.cChannel",
                    ['d' => $since]
                ) as $r) {
                    $o[(string)$r->cChannel] = ['n' => (int)$r->n, 'f' => (float)$r->f];
                }
                foreach (\array_unique(\array_merge(\array_keys($v), \array_keys($o))) as $ch) {
                    $visitRows[] = ['ch' => $ch, 'v' => $v[$ch] ?? 0, 'n' => $o[$ch]['n'] ?? 0, 'f' => $o[$ch]['f'] ?? 0.0];
                }
                \usort($visitRows, static fn ($a, $b) => $b['v'] <=> $a['v']);
            }
        } catch (\Throwable $e) {
            // Tabelle noch nicht vorhanden → wird beim ersten Besuch angelegt.
        }

        // Kosten / ROAS (aktueller + letzter Monat)
        $months = [\date('Y-m'), \date('Y-m', \strtotime('first day of last month'))];
        $costRows = [];
        try {
            foreach ($months as $mo) {
                $rev = [];
                foreach ($db->getObjects(
                    "SELECT a.cChannel, COALESCE(SUM(b.fGesamtsumme),0) AS f, COUNT(*) AS n
                       FROM twomosi_sales_attribution a JOIN tbestellung b ON b.kBestellung = a.kBestellung
                      WHERE DATE_FORMAT(b.dErstellt,'%Y-%m') = :m AND $valid GROUP BY a.cChannel",
                    ['m' => $mo]
                ) as $r) {
                    $rev[(string)$r->cChannel] = ['f' => (float)$r->f, 'n' => (int)$r->n];
                }
                foreach ($db->getObjects('SELECT cChannel, fCost FROM twomosi_sales_cost WHERE cMonth = :m', ['m' => $mo]) as $r) {
                    $c = (float)$r->fCost;
                    $f = (float)($rev[(string)$r->cChannel]['f'] ?? 0.0);
                    $n = (int)($rev[(string)$r->cChannel]['n'] ?? 0);
                    $costRows[] = ['mo' => $mo, 'ch' => (string)$r->cChannel, 'cost' => $c, 'rev' => $f, 'n' => $n];
                }
            }
        } catch (\Throwable $e) {
            $msg = $msg !== '' ? $msg : 'Kosten-Auswertung: ' . $e->getMessage();
        }
        $money = static fn (float $v): string => \number_format($v, 2, ',', '.') . ' €';
        ?>
        <div class="row">
            <div class="col-md-6 mb-4">
                <h4>Besuche &amp; Konversion pro Kanal <small class="text-muted">(eigener Zähler<?= $since !== null ? ', seit ' . sp_h($since) : '' ?>)</small></h4>
                <?php if ($visitRows === []): ?>
                    <p class="text-muted">Zähler läuft ab jetzt (Neue Sitzung = 1 Besuch, ohne Bots). Daten erscheinen nach den ersten Besuchen.</p>
                <?php else: ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Kanal</th><th class="text-right">Besuche</th><th class="text-right">Best.</th><th class="text-right">Umsatz</th><th class="text-right">Konv.</th></tr></thead>
                        <tbody>
                        <?php foreach ($visitRows as $r): ?>
                            <tr>
                                <td><?= sp_h($r['ch']) ?></td>
                                <td class="text-right"><?= (int)$r['v'] ?></td>
                                <td class="text-right"><?= (int)$r['n'] ?></td>
                                <td class="text-right"><?= sp_h(\number_format($r['f'], 0, ',', '.')) ?> €</td>
                                <td class="text-right"><?= $r['v'] > 0 ? sp_h(\number_format($r['n'] * 100 / $r['v'], 2, ',', '.')) . ' %' : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="col-md-6 mb-4">
                <h4>Kosten &amp; ROAS</h4>
                <?php if ($msg !== ''): ?><div class="alert alert-info py-1"><?= sp_h($msg) ?></div><?php endif; ?>
                <form method="post" class="form-inline mb-2">
                    <?= \JTL\Helpers\Form::getTokenInput() ?>
                    <select name="sp_cost_month" class="form-control form-control-sm mr-2">
                        <?php foreach ($months as $mo): ?><option value="<?= sp_h($mo) ?>"><?= sp_h($mo) ?></option><?php endforeach; ?>
                    </select>
                    <select name="sp_cost_channel" class="form-control form-control-sm mr-2">
                        <?php foreach ($channels as $ch): ?><option value="<?= sp_h($ch) ?>"><?= sp_h($ch) ?></option><?php endforeach; ?>
                    </select>
                    <input type="text" name="sp_cost_value" class="form-control form-control-sm mr-2" placeholder="Kosten in €" style="width:110px" required>
                    <button type="submit" name="sp_cost_save" value="1" class="btn btn-sm btn-primary">Speichern</button>
                </form>
                <h5 class="h6 mt-3">Wochenreport per E-Mail <small class="text-muted">(montags, leer = aus)</small></h5>
                <form method="post" class="form-inline mb-3">
                    <?= \JTL\Helpers\Form::getTokenInput() ?>
                    <input type="email" name="sp_report_email" value="<?= sp_h($reportEmail) ?>" class="form-control form-control-sm mr-2" placeholder="name@firma.de" style="width:220px">
                    <label class="mr-2 small"><input type="checkbox" name="sp_mail_test" value="1"> Testmail</label>
                    <button type="submit" name="sp_mail_save" value="1" class="btn btn-sm btn-secondary">Speichern</button>
                </form>
                <?php if ($costRows !== []): ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Monat</th><th>Kanal</th><th class="text-right">Kosten</th><th class="text-right">Umsatz</th><th class="text-right">ROAS</th><th class="text-right">€/Bestellung</th></tr></thead>
                        <tbody>
                        <?php foreach ($costRows as $r): ?>
                            <tr>
                                <td><?= sp_h($r['mo']) ?></td>
                                <td><?= sp_h($r['ch']) ?></td>
                                <td class="text-right"><?= sp_h($money($r['cost'])) ?></td>
                                <td class="text-right"><?= sp_h($money($r['rev'])) ?></td>
                                <td class="text-right"><?= $r['cost'] > 0 ? sp_h(\number_format($r['rev'] / $r['cost'], 2, ',', '.')) . 'x' : '–' ?></td>
                                <td class="text-right"><?= $r['n'] > 0 ? sp_h($money($r['cost'] / $r['n'])) : '–' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted small">Monatliche Werbekosten je Kanal eintragen (z. B. google_ads), dann erscheint ROAS.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('sp_render_insights')) {
    function sp_render_insights(\JTL\DB\DbInterface $db): void
    {
        sp_render_visits_and_costs($db);
        $d = sp_load_insights($db);
        $money = static fn (float $v): string => \number_format($v, 0, ',', '.') . ' €';
        ?>
        <div class="mb-4">
            <h4>Konversion nach Herkunft <small class="text-muted">(30 Tage, Besucher ohne Bots, Herkunft = Referrer)</small></h4>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Herkunft</th><th class="text-right">Besucher</th><th class="text-right">Bestellungen</th><th class="text-right">Umsatz</th><th class="text-right">Konversion</th></tr></thead>
                <tbody>
                <?php foreach ($d['conv'] as $r): ?>
                    <tr>
                        <td><?= sp_h($r['group']) ?></td>
                        <td class="text-right"><?= (int)$r['visits'] ?></td>
                        <td class="text-right"><?= (int)$r['orders'] ?></td>
                        <td class="text-right"><?= sp_h($money($r['revenue'])) ?></td>
                        <td class="text-right"><?= $r['rate'] === null ? '–' : sp_h(\number_format($r['rate'], 2, ',', '.')) . ' %' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted small mb-0">Hinweis: Bezahlte und organische Google-Besucher lassen sich im Besucherarchiv nicht trennen (beide Referrer „google“).</p>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <h4>First- vs. Last-Touch <small class="text-muted">(90 Tage)</small></h4>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Kanal</th><th class="text-right">First-Touch</th><th class="text-right">Last-Touch</th></tr></thead>
                    <tbody>
                    <?php foreach ($d['touch'] as $r): ?>
                        <tr><td><?= sp_h((string)$r['channel']) ?></td><td class="text-right"><?= (int)$r['first'] ?></td><td class="text-right"><?= (int)$r['last'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6 mb-4">
                <h4>Stornierte Bestellungen <small class="text-muted">(90 Tage, nicht im Umsatz)</small></h4>
                <?php if ($d['storno'] === []): ?>
                    <p class="text-muted">Keine Stornos.</p>
                <?php else: ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Kanal</th><th class="text-right">Anzahl</th><th class="text-right">Wert</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['storno'] as $r): ?>
                            <tr><td><?= sp_h((string)$r->ch) ?></td><td class="text-right"><?= (int)$r->n ?></td><td class="text-right"><?= sp_h($money((float)$r->f)) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7 mb-4">
                <h4>Top-Kampagnen / Quellen <small class="text-muted">(90 Tage)</small></h4>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Kanal</th><th>Quelle / Kampagne</th><th class="text-right">Best.</th><th class="text-right">Umsatz</th></tr></thead>
                    <tbody>
                    <?php foreach ($d['campaigns'] as $r): ?>
                        <tr>
                            <td><?= sp_h((string)$r->cChannel) ?></td>
                            <td class="small"><?= sp_h((string)$r->d) ?></td>
                            <td class="text-right"><?= (int)$r->n ?></td>
                            <td class="text-right"><?= sp_h($money((float)$r->f)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-5 mb-4">
                <h4>Top-Landing-Pages <small class="text-muted">(90 Tage)</small></h4>
                <?php if ($d['landing'] === []): ?>
                    <p class="text-muted">Noch keine Daten – wird ab jetzt für neue Bestellungen erfasst.</p>
                <?php else: ?>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Seite</th><th class="text-right">Best.</th><th class="text-right">Umsatz</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['landing'] as $r): ?>
                            <tr>
                                <td class="small"><?= sp_h((string)$r->lp) ?></td>
                                <td class="text-right"><?= (int)$r->n ?></td>
                                <td class="text-right"><?= sp_h($money((float)$r->f)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($d['errors'] !== []): ?>
            <p class="text-muted small">Auswertungs-Hinweis: <?= sp_h(\implode(' | ', $d['errors'])) ?></p>
        <?php endif; ?>
        <?php
    }
}
