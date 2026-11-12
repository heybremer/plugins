<?php

declare(strict_types=1);

namespace Plugin\MegaMenu;

use JTL\DB\DbInterface;
use JTL\Shop;

/**
 * Manuell gewählte Kategoriebilder (Web/Mobil) für das Mega-Menü.
 */
final class ImageStore
{
    public const TABLE      = 'twomosi_mm_image';
    public const AUTO_CACHE = 'mm_auto_images';
    public const UPLOAD_DIR = 'mediafiles/megamenu/';
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MIME_EXT  = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif'  => 'gif',
    ];

    private static bool $schemaEnsured = false;

    public function __construct(private readonly DbInterface $db)
    {
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                kKategorie INT UNSIGNED NOT NULL PRIMARY KEY,
                cDesktop   VARCHAR(512) NOT NULL DEFAULT \'\',
                cMobile    VARCHAR(512) NOT NULL DEFAULT \'\',
                dUpdated   DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::$schemaEnsured = true;
    }

    /**
     * @return array<int, array{d:string, m:string}>
     */
    public function all(): array
    {
        $out = [];
        try {
            $this->ensureSchema();
            foreach ($this->db->getObjects('SELECT kKategorie, cDesktop, cMobile FROM ' . self::TABLE) as $r) {
                $d = (string)$r->cDesktop;
                $m = (string)$r->cMobile;
                if ($d !== '' || $m !== '') {
                    $out[(int)$r->kKategorie] = ['d' => $d, 'm' => $m];
                }
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }

    public function save(int $categoryID, string $desktop, string $mobile): void
    {
        if ($desktop === '' && $mobile === '') {
            $this->db->delete(self::TABLE, 'kKategorie', $categoryID);

            return;
        }
        $this->db->executeQueryPrepared(
            'INSERT INTO ' . self::TABLE . ' (kKategorie, cDesktop, cMobile, dUpdated)
             VALUES (:k, :d, :m, NOW())
             ON DUPLICATE KEY UPDATE cDesktop = VALUES(cDesktop), cMobile = VALUES(cMobile), dUpdated = NOW()',
            ['k' => $categoryID, 'd' => $desktop, 'm' => $mobile]
        );
    }

    /**
     * Erlaubt absolute http(s)-URLs und shop-relative Pfade.
     */
    public static function cleanUrl(string $url): string
    {
        $url = \trim($url);
        if ($url === '') {
            return '';
        }
        if (\preg_match('~^https?://[^\s"<>]+$~i', $url) || \preg_match('~^/?[A-Za-z0-9_\-./]+\.(jpe?g|png|webp|avif|gif)$~i', $url)) {
            return \mb_substr($url, 0, 512);
        }

        return '';
    }

    /**
     * Speichert einen Upload und liefert die absolute URL (oder '' bei Fehler).
     *
     * @param array{tmp_name?:string, error?:int, size?:int} $file
     */
    public function storeUpload(array $file, int $categoryID, string $variant, ?string &$error = null): string
    {
        if ((int)($file['error'] ?? \UPLOAD_ERR_NO_FILE) === \UPLOAD_ERR_NO_FILE) {
            return '';
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ((int)($file['error'] ?? 1) !== \UPLOAD_ERR_OK || $tmp === '' || !\is_uploaded_file($tmp)) {
            $error = 'Upload fehlgeschlagen.';

            return '';
        }
        if ((int)($file['size'] ?? 0) > self::MAX_BYTES) {
            $error = 'Datei größer als 5 MB.';

            return '';
        }
        $mime = (string)(new \finfo(\FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::MIME_EXT[$mime])) {
            $error = 'Nur JPG, PNG, WEBP, AVIF oder GIF erlaubt.';

            return '';
        }
        $dir = \PFAD_ROOT . self::UPLOAD_DIR;
        if (!\is_dir($dir) && !\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            $error = 'Upload-Ordner konnte nicht angelegt werden.';

            return '';
        }
        $name = 'cat' . $categoryID . '-' . ($variant === 'm' ? 'mobil' : 'web') . '-' . \date('YmdHis') . '.' . self::MIME_EXT[$mime];
        if (!\move_uploaded_file($tmp, $dir . $name)) {
            $error = 'Datei konnte nicht gespeichert werden.';

            return '';
        }

        return \rtrim((string)Shop::getURL(), '/') . '/' . self::UPLOAD_DIR . $name;
    }
}
