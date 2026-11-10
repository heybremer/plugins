<?php

declare(strict_types=1);

namespace Plugin\SalesProduct\Cron;

use JTL\Shop;

/**
 * Woechentlicher E-Mail-Report: Bestellungen/Umsatz je Kanal (7 Tage vs. Vorwoche), Besuche, Stornos.
 */
class WeeklyReportJob
{
    public static function buildHtml(\JTL\DB\DbInterface $db): string
    {
        $valid = '(b.cStatus IS NULL OR b.cStatus <> -1)';
        $per   = static function (string $from, string $to) use ($db, $valid): array {
            $out = [];
            foreach ($db->getObjects(
                "SELECT a.cChannel, COUNT(*) AS n, COALESCE(SUM(b.fGesamtsumme),0) AS f
                   FROM twomosi_sales_attribution a JOIN tbestellung b ON b.kBestellung = a.kBestellung
                  WHERE b.dErstellt >= $from AND b.dErstellt < $to AND $valid GROUP BY a.cChannel",
            ) as $r) {
                $out[(string)$r->cChannel] = ['n' => (int)$r->n, 'f' => (float)$r->f];
            }

            return $out;
        };
        $cur  = $per('CURDATE() - INTERVAL 7 DAY', 'CURDATE()');
        $prev = $per('CURDATE() - INTERVAL 14 DAY', 'CURDATE() - INTERVAL 7 DAY');
        $vis  = [];
        try {
            foreach ($db->getObjects('SELECT cChannel, SUM(nVisits) AS n FROM twomosi_sales_visits WHERE dDay >= CURDATE() - INTERVAL 7 DAY AND dDay < CURDATE() GROUP BY cChannel') as $r) {
                $vis[(string)$r->cChannel] = (int)$r->n;
            }
        } catch (\Throwable $e) {
        }
        $storno = $db->getSingleObject(
            'SELECT COUNT(*) AS n, COALESCE(SUM(fGesamtsumme),0) AS f FROM tbestellung
              WHERE cStatus = -1 AND dErstellt >= CURDATE() - INTERVAL 7 DAY AND dErstellt < CURDATE()'
        );
        $m = static fn (float $v): string => \number_format($v, 0, ',', '.') . ' €';

        $rows = '';
        $tn = $tf = $pn = $pf = 0;
        foreach (\array_unique(\array_merge(\array_keys($cur), \array_keys($prev))) as $ch) {
            $c = $cur[$ch] ?? ['n' => 0, 'f' => 0.0];
            $p = $prev[$ch] ?? ['n' => 0, 'f' => 0.0];
            $v = $vis[$ch] ?? null;
            $tn += $c['n'];
            $tf += $c['f'];
            $pn += $p['n'];
            $pf += $p['f'];
            $rows .= '<tr><td>' . \htmlspecialchars($ch) . '</td><td align="right">' . $c['n'] . '</td><td align="right">' . $m($c['f'])
                . '</td><td align="right">' . $p['n'] . '</td><td align="right">' . $m($p['f'])
                . '</td><td align="right">' . ($v === null ? '–' : $v) . '</td><td align="right">'
                . ($v !== null && $v > 0 ? \number_format($c['n'] * 100 / $v, 2, ',', '.') . ' %' : '–') . '</td></tr>';
        }

        return '<h2>Wochenreport Kanal-Attribution</h2><p>Letzte 7 Tage (ohne heute) vs. Vorwoche, ohne stornierte Bestellungen.</p>'
            . '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;font-family:sans-serif;font-size:13px">'
            . '<tr style="background:#eee"><th>Kanal</th><th>Best.</th><th>Umsatz</th><th>Vorwoche Best.</th><th>Vorwoche Umsatz</th><th>Besuche</th><th>Konv.</th></tr>'
            . $rows
            . '<tr style="font-weight:bold"><td>Gesamt</td><td align="right">' . $tn . '</td><td align="right">' . $m($tf) . '</td><td align="right">' . $pn . '</td><td align="right">' . $m($pf) . '</td><td></td><td></td></tr></table>'
            . '<p>Stornos: ' . (int)($storno->n ?? 0) . ' (' . $m((float)($storno->f ?? 0)) . ')</p>';
    }

    public static function sendMail(string $to, string $html): bool
    {
        $host    = (string)(\parse_url((string)Shop::getURL(), \PHP_URL_HOST) ?: 'localhost');
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: SalesProduct <noreply@$host>";
        return (bool)@\mail($to, '=?UTF-8?B?' . \base64_encode('Wochenreport Kanal-Attribution – ' . $host) . '?=', $html, $headers);
    }
}