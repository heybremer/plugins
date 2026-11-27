<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

/**
 * Rendert eine der Design-Vorlagen (templates/*.tpl) mit dem Mail-Smarty.
 * Die Smarty-Variablen (Kunde, Bestellung, Firma, ...) sind bereits vom Shop gesetzt.
 */
final class Designer
{
    /**
     * @param object               $smarty JTL\Smarty\MailSmarty
     * @param array<string,string> $cfg    Config::all()
     */
    public static function render(
        string $design,
        object $smarty,
        string $lang,
        array $cfg,
        string $kind = 'confirmation'
    ): string {
        if (!isset(Config::DESIGNS[$design])) {
            $design = 'modern';
        }
        $file = \dirname(__DIR__) . '/templates/' . $design . '.tpl';
        $src  = \is_file($file) ? (string)\file_get_contents($file) : '';
        if ($src === '') {
            return '';
        }
        $accent = $cfg['accent'] !== '' ? $cfg['accent'] : Config::DESIGNS[$design]['accent'];
        $h      = static fn(string $v): string => \htmlspecialchars($v, \ENT_QUOTES, 'UTF-8');
        if ($kind === 'partshipped') {
            self::usePartialPositions($smarty);
        }
        $smarty->assign('mt', Labels::get($lang, $kind));
        $smarty->assign('mtc', [
            'accent'       => $accent,
            'accent_dark'  => self::mix($accent, '#000000', 0.25),
            'accent_tint'  => self::mix($accent, '#ffffff', 0.92),
            'tagline'      => $h($cfg['tagline']),
            'note'         => \nl2br($h($cfg['note']), false),
            'support_mail' => $h($cfg['support_mail']),
            'support_phone' => $h($cfg['support_phone']),
            'logo'         => $h($cfg['logo_url']),
            'images'       => ($cfg['show_images'] ?? '1') === '1',
            'promo_title'  => $h($cfg['promo_title'] ?? ''),
            'promo_text'   => $h($cfg['promo_text'] ?? ''),
            'promo_btn'    => $h($cfg['promo_btn'] ?? ''),
            'promo_url'    => $h($cfg['promo_url'] ?? ''),
            'year'         => \date('Y'),
        ]);

        return $smarty->fetch('string:' . $src);
    }

    /**
     * Teilversand: Positionsliste = die in dieser Mail versandten Lieferschein-Positionen.
     */
    private static function usePartialPositions(object $smarty): void
    {
        $order = $smarty->getTemplateVars('Bestellung');
        if (!\is_object($order) || empty($order->oLieferschein_arr)) {
            return;
        }
        $byArt = [];
        foreach ((array)($order->Positionen ?? []) as $p) {
            if (\is_object($p)) {
                $byArt[(string)($p->cArtNr ?? '')] = $p;
            }
        }
        $rows = [];
        foreach ($order->oLieferschein_arr as $ls) {
            if (!\is_object($ls) || (\method_exists($ls, 'getEmailVerschickt') && $ls->getEmailVerschickt())) {
                continue;
            }
            foreach ((array)($ls->oPosition_arr ?? []) as $p) {
                $row                = clone $p;
                $row->nAnzahl       = (int)($p->nAusgeliefert ?? $p->nAnzahl ?? 1);
                $orig               = $byArt[(string)($p->cArtNr ?? '')] ?? null;
                $row->Artikel       = $p->Artikel ?? ($orig->Artikel ?? null);
                $row->cEinzelpreisLocalized = null;
                $row->cGesamtpreisLocalized = [0 => '', 1 => ''];
                unset($row->cEinzelpreisLocalized);
                $rows[] = $row;
            }
        }
        if ($rows === []) {
            return;
        }
        $clone            = clone $order;
        $clone->Positionen = $rows;
        $smarty->assign('Bestellung', $clone);
    }

    /** Mischt Farbe $a mit $b (Anteil $t von $b). */
    private static function mix(string $a, string $b, float $t): string
    {
        $pa  = \sscanf(\ltrim($a, '#'), '%2x%2x%2x');
        $pb  = \sscanf(\ltrim($b, '#'), '%2x%2x%2x');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= \sprintf('%02x', (int)\round(($pa[$i] ?? 0) * (1 - $t) + ($pb[$i] ?? 0) * $t));
        }

        return $out;
    }
}
