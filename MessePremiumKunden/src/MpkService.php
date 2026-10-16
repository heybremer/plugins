<?php

declare(strict_types=1);

namespace Plugin\MessePremiumKunden;

use JTL\Shop;

class MpkService
{
    public const TABLE        = 'twomosi_mpk_anmeldungen';
    public const TABLE_FOTO   = 'twomosi_mpk_fotos';
    public const TABLE_SET    = 'twomosi_mpk_settings';
    public const MAX_FOTOS    = 5;
    public const HONEYPOT     = 'website';
    public const MIN_SECONDS  = 3;
    public const RATE_PER_HOUR = 5;

    private const DEFAULT_MARKEN = [
        'Adria', 'Bürstner', 'Carthago', 'Chausson', 'Dethleffs', 'Eura Mobil',
        'Fendt', 'Frankia', 'Hobby', 'Hymer', 'Knaus', 'Laika', 'LMC',
        'Malibu', 'Morelo', 'Niesmann+Bischoff', 'Pössl', 'Rapido',
        'Sunlight', 'Weinsberg', 'Westfalia',
    ];

    public function __construct(private readonly string $uploadDir)
    {
    }

    public function ensureTables(): void
    {
        $db = Shop::Container()->getDB();
        $db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                kID              INT AUTO_INCREMENT PRIMARY KEY,
                cName            VARCHAR(200)  NOT NULL DEFAULT "",
                cTelefon         VARCHAR(50)   NOT NULL DEFAULT "",
                cEmail           VARCHAR(200)  NOT NULL DEFAULT "",
                cAdresse         VARCHAR(500)  NOT NULL DEFAULT "",
                cKaravanMarke    VARCHAR(150)  NOT NULL DEFAULT "",
                cKaravanModell   VARCHAR(150)  NOT NULL DEFAULT "",
                cKaravanManuell  VARCHAR(200)  NOT NULL DEFAULT "",
                cNachricht       TEXT,
                nDatenschutz     TINYINT(1)    NOT NULL DEFAULT 0,
                dDatenschutz     DATETIME      NULL,
                cKuponCode       VARCHAR(32)   NOT NULL DEFAULT "",
                kKupon           INT           NOT NULL DEFAULT 0,
                nRabattProzent   DECIMAL(6,2)  NOT NULL DEFAULT 0,
                nEmailGesendet   TINYINT(1)    NOT NULL DEFAULT 0,
                cEmailFehler     VARCHAR(255)  NOT NULL DEFAULT "",
                cIp              VARCHAR(64)   NOT NULL DEFAULT "",
                dErstellt        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (cEmail),
                INDEX idx_erstellt (dErstellt),
                INDEX idx_kupon (cKuponCode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_FOTO . ' (
                kID            INT AUTO_INCREMENT PRIMARY KEY,
                kAnmeldung     INT           NOT NULL DEFAULT 0,
                cDateiname     VARCHAR(80)   NOT NULL DEFAULT "",
                cOriginalname  VARCHAR(255)  NOT NULL DEFAULT "",
                cMime          VARCHAR(80)   NOT NULL DEFAULT "",
                nGroesse       INT           NOT NULL DEFAULT 0,
                dErstellt      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_anmeldung (kAnmeldung)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_SET . ' (
                kID                 INT AUTO_INCREMENT PRIMARY KEY,
                nRabattProzent      DECIMAL(6,2)  NOT NULL DEFAULT 10.00,
                nKuponGueltigTage   INT           NOT NULL DEFAULT 90,
                fMindestbestellwert DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                nMaxFotoKb          INT           NOT NULL DEFAULT 5120,
                cMarkenListe        TEXT,
                nActive             TINYINT(1)    NOT NULL DEFAULT 1,
                dUpdated            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $exists = $db->getSingleObject('SELECT kID FROM ' . self::TABLE_SET . ' LIMIT 1');
        if ($exists === null) {
            $ins = new \stdClass();
            $ins->nRabattProzent      = 10;
            $ins->nKuponGueltigTage   = 90;
            $ins->fMindestbestellwert = 0;
            $ins->nMaxFotoKb          = 5120;
            $ins->cMarkenListe        = \implode("\n", self::DEFAULT_MARKEN);
            $ins->nActive             = 1;
            $ins->dUpdated            = \date('Y-m-d H:i:s');
            $db->insert(self::TABLE_SET, $ins);
        }
        $this->ensureUploadDir();
    }

    /** @return array<string,mixed> */
    public function fetchSettings(): array
    {
        $defaults = [
            'rabatt'      => 10.0,
            'gueltigTage' => 90,
            'mindest'     => 0.0,
            'maxFotoKb'   => 5120,
            'markenListe' => \implode("\n", self::DEFAULT_MARKEN),
            'active'      => 1,
        ];
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT * FROM ' . self::TABLE_SET . ' ORDER BY kID ASC LIMIT 1'
            );
            if ($row === null) {
                return $defaults;
            }
            return [
                'rabatt'      => (float)($row->nRabattProzent ?? 10),
                'gueltigTage' => (int)($row->nKuponGueltigTage ?? 90),
                'mindest'     => (float)($row->fMindestbestellwert ?? 0),
                'maxFotoKb'   => (int)($row->nMaxFotoKb ?? 5120),
                'markenListe' => (string)($row->cMarkenListe ?? $defaults['markenListe']),
                'active'      => (int)($row->nActive ?? 1),
            ];
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    /** @return list<string> */
    public function loadMarken(): array
    {
        $fromShop = $this->loadMarkenFromSnapshot();
        if ($fromShop !== []) {
            return $fromShop;
        }
        $settings = $this->fetchSettings();
        $lines    = \preg_split('/\r\n|\r|\n/', (string)$settings['markenListe']) ?: [];
        $out      = [];
        foreach ($lines as $line) {
            $v = \trim((string)$line);
            if ($v !== '') {
                $out[] = $v;
            }
        }
        return $out !== [] ? $out : self::DEFAULT_MARKEN;
    }

    /** @return list<string> */
    public function loadModelle(string $marke): array
    {
        $marke = \trim($marke);
        if ($marke === '' || $marke === 'Andere') {
            return [];
        }
        try {
            $db = Shop::Container()->getDB();
            $rows = $db->getObjects(
                'SELECT DISTINCT cModell
                   FROM twomosi_fk_opt_modell
                  WHERE cHersteller = :h AND cModell <> ""
                  ORDER BY cModell ASC',
                ['h' => $marke]
            );
            $out = [];
            foreach ($rows as $r) {
                $v = \trim((string)($r->cModell ?? ''));
                if ($v !== '') {
                    $out[] = $v;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $d
     * @return list<string>
     */
    public function validate(array $d, array $fotos): array
    {
        $errors = [];
        if (\trim((string)($d['name'] ?? '')) === '') {
            $errors[] = 'Bitte Vor- und Nachname angeben.';
        }
        if (\trim((string)($d['telefon'] ?? '')) === '') {
            $errors[] = 'Bitte eine Telefonnummer angeben.';
        }
        $email = \trim((string)($d['email'] ?? ''));
        if ($email === '' || !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        if (\trim((string)($d['adresse'] ?? '')) === '') {
            $errors[] = 'Bitte eine Adresse angeben.';
        }
        $marke   = \trim((string)($d['karavanMarke'] ?? ''));
        $modell  = \trim((string)($d['karavanModell'] ?? ''));
        $manuell = \trim((string)($d['karavanManuell'] ?? ''));
        if ($marke === '' && $manuell === '') {
            $errors[] = 'Bitte Marke/Modell wählen oder manuell eingeben.';
        }
        if ($marke === 'Andere' && $manuell === '' && $modell === '') {
            $errors[] = 'Bitte Marke und Modell manuell eingeben.';
        }
        if (empty($d['datenschutz'])) {
            $errors[] = 'Bitte die Datenschutzerklärung akzeptieren.';
        }
        if (\count($fotos) > self::MAX_FOTOS) {
            $errors[] = 'Es sind höchstens ' . self::MAX_FOTOS . ' Fotos erlaubt.';
        }
        $settings = $this->fetchSettings();
        $maxBytes = \max(100, (int)$settings['maxFotoKb']) * 1024;
        foreach ($fotos as $f) {
            $err = $this->validateFoto($f, $maxBytes);
            if ($err !== null) {
                $errors[] = $err;
            }
        }
        return $errors;
    }

    public function isHoneypotTriggered(array $d): bool
    {
        return \trim((string)($d[self::HONEYPOT] ?? '')) !== '';
    }

    public function isTooFast(array $d): bool
    {
        $ts = (int)($d['mpk_ts'] ?? 0);
        if ($ts <= 0) {
            return true;
        }
        return (\time() - $ts) < self::MIN_SECONDS;
    }

    public function isRateLimited(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT COUNT(*) AS cnt FROM ' . self::TABLE . '
                  WHERE cIp = :ip AND dErstellt >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                ['ip' => $ip]
            );
            return (int)($row->cnt ?? 0) >= self::RATE_PER_HOUR;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $d
     * @param list<array<string,mixed>> $fotos
     * @return array{id:int,code:string,rabatt:float,emailOk:bool}
     */
    public function saveRegistration(array $d, array $fotos, string $ip): array
    {
        $settings = $this->fetchSettings();
        $rabatt   = \max(0.01, \min(100.0, (float)$settings['rabatt']));
        $days     = \max(1, (int)$settings['gueltigTage']);
        $email    = \substr(\trim((string)($d['email'] ?? '')), 0, 200);
        $name     = \substr(\trim((string)($d['name'] ?? '')), 0, 200);

        $dup = $this->findRecentDuplicate($email, $ip);
        if ($dup !== null) {
            return $dup;
        }

        $code = $this->uniqueCouponCode();
        $now  = \date('Y-m-d H:i:s');

        $db  = Shop::Container()->getDB();
        $row = new \stdClass();
        $row->cName           = $name;
        $row->cTelefon        = \substr(\trim((string)($d['telefon'] ?? '')), 0, 50);
        $row->cEmail          = $email;
        $row->cAdresse        = \substr(\trim((string)($d['adresse'] ?? '')), 0, 500);
        $row->cKaravanMarke   = \substr(\trim((string)($d['karavanMarke'] ?? '')), 0, 150);
        $row->cKaravanModell  = \substr(\trim((string)($d['karavanModell'] ?? '')), 0, 150);
        $row->cKaravanManuell = \substr(\trim((string)($d['karavanManuell'] ?? '')), 0, 200);
        $row->cNachricht      = \substr(\trim((string)($d['nachricht'] ?? '')), 0, 4000);
        $row->nDatenschutz    = 1;
        $row->dDatenschutz    = $now;
        $row->cKuponCode      = $code;
        $row->kKupon          = 0;
        $row->nRabattProzent  = $rabatt;
        $row->nEmailGesendet  = 0;
        $row->cEmailFehler    = '';
        $row->cIp             = \substr($ip, 0, 64);
        $row->dErstellt       = $now;
        $id = (int)$db->insert(self::TABLE, $row);

        $this->storeFotos($id, $fotos);

        $mail = $this->sendCouponMail($email, $name, $code, $rabatt, $days);
        if ($id > 0) {
            $db->executeQueryPrepared(
                'UPDATE ' . self::TABLE . '
                    SET nEmailGesendet = :ok, cEmailFehler = :err
                  WHERE kID = :id',
                [
                    'ok'  => $mail['ok'] ? 1 : 0,
                    'err' => \substr($mail['error'], 0, 255),
                    'id'  => $id,
                ]
            );
        }

        return [
            'id'      => $id,
            'code'    => $code,
            'rabatt'  => $rabatt,
            'emailOk' => $mail['ok'],
        ];
    }

    public function resendMail(int $id): bool
    {
        $row = Shop::Container()->getDB()->getSingleObject(
            'SELECT * FROM ' . self::TABLE . ' WHERE kID = :id LIMIT 1',
            ['id' => $id]
        );
        if ($row === null) {
            return false;
        }
        $settings = $this->fetchSettings();
        $mail = $this->sendCouponMail(
            (string)($row->cEmail ?? ''),
            (string)($row->cName ?? ''),
            (string)($row->cKuponCode ?? ''),
            (float)($row->nRabattProzent ?? $settings['rabatt']),
            (int)$settings['gueltigTage']
        );
        Shop::Container()->getDB()->executeQueryPrepared(
            'UPDATE ' . self::TABLE . '
                SET nEmailGesendet = :ok, cEmailFehler = :err
              WHERE kID = :id',
            [
                'ok'  => $mail['ok'] ? 1 : 0,
                'err' => \substr($mail['error'], 0, 255),
                'id'  => $id,
            ]
        );
        return $mail['ok'];
    }

    /** @return list<array<string,mixed>> */
    public function collectFotosFromRequest(): array
    {
        if (empty($_FILES['fotos'])) {
            return [];
        }
        $bag = $_FILES['fotos'];
        if (!\is_array($bag['name'] ?? null)) {
            if ((int)($bag['error'] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [[
                'name'     => (string)($bag['name'] ?? ''),
                'type'     => (string)($bag['type'] ?? ''),
                'tmp_name' => (string)($bag['tmp_name'] ?? ''),
                'error'    => (int)($bag['error'] ?? 0),
                'size'     => (int)($bag['size'] ?? 0),
            ]];
        }
        $out = [];
        foreach ($bag['name'] as $i => $name) {
            if ((int)($bag['error'][$i] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => (string)$name,
                'type'     => (string)($bag['type'][$i] ?? ''),
                'tmp_name' => (string)($bag['tmp_name'][$i] ?? ''),
                'error'    => (int)($bag['error'][$i] ?? 0),
                'size'     => (int)($bag['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    public function readFoto(int $fotoId): ?array
    {
        if ($fotoId <= 0) {
            return null;
        }
        $row = Shop::Container()->getDB()->getSingleObject(
            'SELECT * FROM ' . self::TABLE_FOTO . ' WHERE kID = :id LIMIT 1',
            ['id' => $fotoId]
        );
        if ($row === null) {
            return null;
        }
        $name = \basename((string)($row->cDateiname ?? ''));
        if ($name === '' || \str_contains($name, '..')) {
            return null;
        }
        $path = \rtrim($this->uploadDir, '/\\') . \DIRECTORY_SEPARATOR . $name;
        if (!\is_file($path)) {
            return null;
        }
        return [
            'path' => $path,
            'mime' => (string)($row->cMime ?? 'application/octet-stream'),
            'name' => (string)($row->cOriginalname ?? $name),
        ];
    }

    public function readFotoByName(string $storedName): ?array
    {
        $name = \basename($storedName);
        if ($name === '' || \str_contains($name, '..') || !\preg_match('/^[a-f0-9]{16,64}\.(jpe?g|png|webp|gif)$/i', $name)) {
            return null;
        }
        $row = Shop::Container()->getDB()->getSingleObject(
            'SELECT * FROM ' . self::TABLE_FOTO . ' WHERE cDateiname = :n LIMIT 1',
            ['n' => $name]
        );
        if ($row === null) {
            return null;
        }
        $path = \rtrim($this->uploadDir, '/\\') . \DIRECTORY_SEPARATOR . $name;
        if (!\is_file($path)) {
            return null;
        }
        return [
            'path' => $path,
            'mime' => (string)($row->cMime ?? 'application/octet-stream'),
            'name' => (string)($row->cOriginalname ?? $name),
        ];
    }

    /** @return list<string> */
    private function loadMarkenFromSnapshot(): array
    {
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT DISTINCT cHersteller
                   FROM twomosi_fk_opt_hersteller
                  WHERE cHersteller <> ""
                  ORDER BY cHersteller ASC'
            );
            $out = [];
            foreach ($rows as $r) {
                $v = \trim((string)($r->cHersteller ?? ''));
                if ($v !== '') {
                    $out[] = $v;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @param array<string,mixed> $f */
    private function validateFoto(array $f, int $maxBytes): ?string
    {
        $orig = (string)($f['name'] ?? 'Datei');
        if ((int)($f['error'] ?? 0) !== \UPLOAD_ERR_OK) {
            return 'Foto „' . $orig . '“ konnte nicht hochgeladen werden.';
        }
        if ((int)($f['size'] ?? 0) <= 0 || (int)$f['size'] > $maxBytes) {
            return 'Foto „' . $orig . '“ ist zu groß oder leer.';
        }
        $tmp = (string)($f['tmp_name'] ?? '');
        if ($tmp === '' || !\is_uploaded_file($tmp)) {
            return 'Ungültiger Datei-Upload.';
        }
        $ext = \strtolower(\pathinfo($orig, \PATHINFO_EXTENSION));
        if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'Nur JPG, PNG, WEBP oder GIF sind erlaubt.';
        }
        $mime = $this->detectMime($tmp);
        if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return 'Datei „' . $orig . '“ ist kein gültiges Bild.';
        }
        $info = @\getimagesize($tmp);
        if ($info === false) {
            return 'Datei „' . $orig . '“ ist kein gültiges Bild.';
        }
        return null;
    }

    /** @param list<array<string,mixed>> $fotos */
    private function storeFotos(int $anmeldungId, array $fotos): void
    {
        if ($anmeldungId <= 0 || $fotos === []) {
            return;
        }
        $this->ensureUploadDir();
        $db = Shop::Container()->getDB();
        foreach ($fotos as $f) {
            $tmp  = (string)($f['tmp_name'] ?? '');
            $orig = \basename((string)($f['name'] ?? 'foto'));
            $ext  = \strtolower(\pathinfo($orig, \PATHINFO_EXTENSION));
            if (!\in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }
            $stored = \bin2hex(\random_bytes(16)) . '.' . $ext;
            $dest   = \rtrim($this->uploadDir, '/\\') . \DIRECTORY_SEPARATOR . $stored;
            if (!@\move_uploaded_file($tmp, $dest)) {
                continue;
            }
            @\chmod($dest, 0644);
            $ins = new \stdClass();
            $ins->kAnmeldung    = $anmeldungId;
            $ins->cDateiname    = $stored;
            $ins->cOriginalname = \substr($orig, 0, 255);
            $ins->cMime         = $this->detectMime($dest);
            $ins->nGroesse      = (int)($f['size'] ?? 0);
            $ins->dErstellt     = \date('Y-m-d H:i:s');
            $db->insert(self::TABLE_FOTO, $ins);
        }
    }

    /** @return array{id:int,code:string,rabatt:float,emailOk:bool}|null */
    private function findRecentDuplicate(string $email, string $ip): ?array
    {
        if ($email === '') {
            return null;
        }
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT kID, cKuponCode, nRabattProzent, nEmailGesendet
                   FROM ' . self::TABLE . '
                  WHERE cEmail = :e
                    AND dErstellt >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
                  ORDER BY kID DESC
                  LIMIT 1',
                ['e' => $email]
            );
            if ($row === null) {
                return null;
            }
            return [
                'id'      => (int)($row->kID ?? 0),
                'code'    => (string)($row->cKuponCode ?? ''),
                'rabatt'  => (float)($row->nRabattProzent ?? 0),
                'emailOk' => (int)($row->nEmailGesendet ?? 0) === 1,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array{ok:bool,error:string} */
    private function sendCouponMail(string $email, string $name, string $code, float $rabatt, int $days): array
    {
        if ($email === '' || $code === '') {
            return ['ok' => false, 'error' => 'Keine E-Mail oder kein Code.'];
        }
        $shopName = 'Womosi';
        try {
            if (\defined('CONF_GLOBAL')) {
                $shopName = (string)(Shop::getSettingValue(\CONF_GLOBAL, 'global_shopname') ?: 'Womosi');
            }
        } catch (\Throwable $e) {
            $shopName = 'Womosi';
        }
        $shopUrl  = \rtrim((string)Shop::getURL(), '/');
        $rabattTxt = \rtrim(\rtrim(\number_format($rabatt, 2, ',', ''), '0'), ',') . ' %';
        $subject   = 'Ihr Messe-Premium-Gutschein (' . $rabattTxt . ')';
        $safeName  = \htmlspecialchars($name !== '' ? $name : 'Kunde', \ENT_QUOTES, 'UTF-8');
        $safeCode  = \htmlspecialchars($code, \ENT_QUOTES, 'UTF-8');
        $html      = '<p>Hallo ' . $safeName . ',</p>'
            . '<p>vielen Dank für Ihre Registrierung als Messe-Premium-Kunde bei '
            . \htmlspecialchars($shopName, \ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p>Ihr persönlicher Gutscheincode über <strong>' . \htmlspecialchars($rabattTxt, \ENT_QUOTES, 'UTF-8')
            . '</strong> lautet:</p>'
            . '<p style="font-size:22px;font-weight:700;letter-spacing:1px;">' . $safeCode . '</p>'
            . '<p>Bitte bewahren Sie diesen Code auf. Er ist ' . (int)$days . ' Tage gültig.</p>'
            . '<p>Freundliche Grüße<br>' . \htmlspecialchars($shopName, \ENT_QUOTES, 'UTF-8') . '</p>';
        $text = "Hallo {$name},\n\nIhr Gutscheincode: {$code} ({$rabattTxt})\nGültig {$days} Tage.\n{$shopUrl}\n";

        try {
            if (\class_exists(\JTL\Mail\Mail\Mail::class)) {
                $mail = new \JTL\Mail\Mail\Mail();
                if (\method_exists($mail, 'setToMail')) {
                    $mail->setToMail($email);
                }
                if (\method_exists($mail, 'setToName')) {
                    $mail->setToName($name);
                }
                if (\method_exists($mail, 'setSubject')) {
                    $mail->setSubject($subject);
                }
                if (\method_exists($mail, 'setBodyHTML')) {
                    $mail->setBodyHTML($html);
                }
                if (\method_exists($mail, 'setBodyText')) {
                    $mail->setBodyText($text);
                }
                $mailer = null;
                if (\method_exists(Shop::Container(), 'getMailer')) {
                    $mailer = Shop::Container()->getMailer();
                } elseif (\class_exists(\JTL\Mail\Mailer::class)) {
                    $mailer = Shop::Container()->get(\JTL\Mail\Mailer::class);
                }
                if ($mailer !== null && \method_exists($mailer, 'send')) {
                    $mailer->send($mail);
                    return ['ok' => true, 'error' => ''];
                }
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $headers = 'MIME-Version: 1.0' . "\r\n"
            . 'Content-type: text/html; charset=UTF-8' . "\r\n"
            . 'From: ' . $shopName . ' <noreply@' . (\parse_url($shopUrl, \PHP_URL_HOST) ?: 'localhost') . '>';
        $ok = @\mail($email, '=?UTF-8?B?' . \base64_encode($subject) . '?=', $html, $headers);
        return ['ok' => (bool)$ok, 'error' => $ok ? '' : 'mail() fehlgeschlagen'];
    }

    private function uniqueCouponCode(): string
    {
        $db = Shop::Container()->getDB();
        for ($i = 0; $i < 12; $i++) {
            $code = 'MPK-' . \strtoupper(\bin2hex(\random_bytes(4)));
            try {
                $ex = $db->getSingleObject(
                    'SELECT kID FROM ' . self::TABLE . ' WHERE cKuponCode = :c LIMIT 1',
                    ['c' => $code]
                );
                if ($ex === null) {
                    return $code;
                }
            } catch (\Throwable $e) {
                return $code;
            }
        }
        return 'MPK-' . \strtoupper(\bin2hex(\random_bytes(6)));
    }

    private function detectMime(string $path): string
    {
        if (\function_exists('finfo_open')) {
            $fi = \finfo_open(\FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $mime = (string)\finfo_file($fi, $path);
                \finfo_close($fi);
                if ($mime !== '') {
                    return $mime;
                }
            }
        }
        $info = @\getimagesize($path);
        return \is_array($info) ? (string)($info['mime'] ?? '') : '';
    }

    private function ensureUploadDir(): void
    {
        if (!\is_dir($this->uploadDir)) {
            @\mkdir($this->uploadDir, 0755, true);
        }
        $ht = \rtrim($this->uploadDir, '/\\') . \DIRECTORY_SEPARATOR . '.htaccess';
        if (!\is_file($ht)) {
            @\file_put_contents(
                $ht,
                "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl)$\">\n  Require all denied\n</FilesMatch>\n"
            );
        }
        $idx = \rtrim($this->uploadDir, '/\\') . \DIRECTORY_SEPARATOR . 'index.html';
        if (!\is_file($idx)) {
            @\file_put_contents($idx, '');
        }
    }
}
