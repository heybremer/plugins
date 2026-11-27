<?php

declare(strict_types=1);

namespace Plugin\MailTemplate;

use JTL\Language\LanguageHelper;
use JTL\Mail\Mail\Mail;
use JTL\Shop;

/**
 * Sendet die Vorschau (Beispielbestellung) als Testmail an eine Adresse.
 */
final class TestMail
{
    /** @return string Fehlertext oder '' bei Erfolg */
    public static function send(string $to, string $design, string $kind, string $lang): string
    {
        $mail = self::build($to, $design, $kind, $lang);
        if (\is_string($mail)) {
            return $mail;
        }
        // Direkt senden (ohne Queue/Smarty): Body ist bereits fertig gerendert.
        $mailer = Shop::Container()->getMailer();
        try {
            $sent = $mailer->sendPreparedMail($mail);
        } catch (\Throwable $e) {
            return 'Versand fehlgeschlagen: ' . $e->getMessage();
        }
        if (!$sent) {
            return 'Versand fehlgeschlagen' . ($mail->getError() !== '' ? ': ' . $mail->getError() : ' (siehe Shop-Log).');
        }

        return '';
    }

    /** @return Mail|string Mail-Objekt oder Fehlertext */
    public static function build(string $to, string $design, string $kind, string $lang): Mail|string
    {
        if (!\filter_var($to, \FILTER_VALIDATE_EMAIL)) {
            return 'Ungültige E-Mail-Adresse.';
        }
        if (!isset(Config::DESIGNS[$design])) {
            return 'Unbekanntes Design.';
        }
        if (!\in_array($kind, ['confirmation', 'paid', 'shipped', 'partshipped', 'cancelled'], true)) {
            $kind = 'confirmation';
        }
        $mailer = Shop::Container()->getMailer();
        $conf   = $mailer->getConfig('emails');
        $from   = (string)($conf['email_master_absender'] ?? '');
        if ($from === '') {
            return 'Keine Absender-Adresse in den Shop-E-Mail-Einstellungen hinterlegt.';
        }
        $html  = Demo::render($design, $lang, $kind);
        $label = Labels::get($lang, $kind);
        $text  = \trim(\html_entity_decode(\strip_tags(
            (string)\preg_replace('~<(style|script)\b.*?</\1>~is', '', $html)
        ), \ENT_QUOTES, 'UTF-8'));
        $text  = (string)\preg_replace('~[ \t]+~', ' ', $text);
        $text  = (string)\preg_replace('~\s*\n\s*~', "\n", $text);

        $mail = new Mail();
        $mail->setLanguage(LanguageHelper::getDefaultLanguage());
        $mail->setCustomerGroupID(1);
        $mail->setToMail($to);
        $mail->setToName($to);
        $mail->setFromMail($from);
        $mail->setFromName((string)($conf['email_master_absender_name'] ?? ''));
        $mail->setReplyToMail($from);
        $mail->setSubject('[Testmail] ' . $label['title']);
        $mail->setBodyHTML($html);
        $mail->setBodyText($text);
        $mail->setPriority(0);

        return $mail;
    }
}
