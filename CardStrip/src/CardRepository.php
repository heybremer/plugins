<?php
declare(strict_types=1);

namespace Plugin\CardStrip;

use JTL\Shop;

final class CardRepository
{
    public const TABLE = 'twomosi_card_strip';

    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        try {
            Shop::Container()->getDB()->executeQueryPrepared(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    kID       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    cVariant  CHAR(1)      NOT NULL DEFAULT \'A\',
                    cTitle    VARCHAR(255) NOT NULL DEFAULT \'\',
                    cSubtitle VARCHAR(255) NOT NULL DEFAULT \'\',
                    cBgColor  VARCHAR(20)  NOT NULL DEFAULT \'#197149\',
                    cTextTone CHAR(5)      NOT NULL DEFAULT \'light\',
                    cImage    VARCHAR(500) NOT NULL DEFAULT \'\',
                    cBadge    VARCHAR(60)  NOT NULL DEFAULT \'\',
                    cUrl      VARCHAR(500) NOT NULL DEFAULT \'\',
                    cItems    TEXT         NULL,
                    nActive   TINYINT(1)   NOT NULL DEFAULT 1,
                    nSort     INT          NOT NULL DEFAULT 0,
                    dStart    DATE         NULL,
                    dEnd      DATE         NULL,
                    dCreated  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (kID),
                    KEY idx_active (nActive, nSort)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                []
            );
            self::$ensured = true;
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @return array<int,object>
     */
    public static function all(): array
    {
        self::ensure();
        try {
            return Shop::Container()->getDB()->getObjects('SELECT * FROM ' . self::TABLE . ' ORDER BY nSort ASC, kID ASC');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int,object>
     */
    public static function active(): array
    {
        self::ensure();
        try {
            return Shop::Container()->getDB()->getObjects(
                'SELECT * FROM ' . self::TABLE . '
                  WHERE nActive = 1
                    AND (dStart IS NULL OR dStart <= CURDATE())
                    AND (dEnd IS NULL OR dEnd >= CURDATE())
               ORDER BY nSort ASC, kID ASC
                  LIMIT 40'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function get(int $id): ?object
    {
        self::ensure();
        try {
            return Shop::Container()->getDB()->getSingleObject('SELECT * FROM ' . self::TABLE . ' WHERE kID = :id', ['id' => $id]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $d
     */
    public static function save(int $id, array $d): int
    {
        self::ensure();
        $db     = Shop::Container()->getDB();
        $params = [
            'v'  => (string)$d['cVariant'],
            't'  => (string)$d['cTitle'],
            's'  => (string)$d['cSubtitle'],
            'bg' => (string)$d['cBgColor'],
            'tn' => (string)$d['cTextTone'],
            'im' => (string)$d['cImage'],
            'b'  => (string)$d['cBadge'],
            'u'  => (string)$d['cUrl'],
            'it' => (string)$d['cItems'],
            'ac' => (int)$d['nActive'],
            'so' => (int)$d['nSort'],
            'ds' => $d['dStart'],
            'de' => $d['dEnd'],
        ];
        if ($id > 0) {
            $params['id'] = $id;
            $db->executeQueryPrepared(
                'UPDATE ' . self::TABLE . ' SET cVariant = :v, cTitle = :t, cSubtitle = :s, cBgColor = :bg, cTextTone = :tn,
                        cImage = :im, cBadge = :b, cUrl = :u, cItems = :it, nActive = :ac, nSort = :so, dStart = :ds, dEnd = :de
                  WHERE kID = :id',
                $params
            );

            return $id;
        }
        $db->executeQueryPrepared(
            'INSERT INTO ' . self::TABLE . ' (cVariant, cTitle, cSubtitle, cBgColor, cTextTone, cImage, cBadge, cUrl, cItems, nActive, nSort, dStart, dEnd)
             VALUES (:v, :t, :s, :bg, :tn, :im, :b, :u, :it, :ac, :so, :ds, :de)',
            $params
        );
        $row = $db->getSingleObject('SELECT MAX(kID) AS kID FROM ' . self::TABLE);

        return (int)($row->kID ?? 0);
    }

    public static function delete(int $id): void
    {
        self::ensure();
        Shop::Container()->getDB()->executeQueryPrepared('DELETE FROM ' . self::TABLE . ' WHERE kID = :id', ['id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        self::ensure();
        Shop::Container()->getDB()->executeQueryPrepared(
            'UPDATE ' . self::TABLE . ' SET nActive = :a WHERE kID = :id',
            ['a' => $active ? 1 : 0, 'id' => $id]
        );
    }

    /**
     * Speichert ein hochgeladenes Bild unter mediafiles/cardstrip/ und liefert die URL (oder wirft eine Exception).
     *
     * @param array{name:string,tmp_name:string,error:int,size:int} $f
     */
    public static function saveUpload(array $f): string
    {
        if ((int)$f['error'] !== \UPLOAD_ERR_OK || !\is_uploaded_file((string)$f['tmp_name'])) {
            throw new \RuntimeException('Yükleme başarısız (' . (int)$f['error'] . ').');
        }
        if ((int)$f['size'] > 8 * 1024 * 1024) {
            throw new \RuntimeException('Dosya 8 MB sınırını aşıyor.');
        }
        $info = @\getimagesize((string)$f['tmp_name']);
        $ext  = [\IMAGETYPE_JPEG => 'jpg', \IMAGETYPE_PNG => 'png', \IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
        if ($ext === null) {
            throw new \RuntimeException('Sadece JPG, PNG veya WEBP yüklenebilir.');
        }
        $dir = \rtrim(\PFAD_ROOT, '/\\') . '/mediafiles/cardstrip/';
        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Klasör oluşturulamadı: mediafiles/cardstrip');
        }
        $base = \preg_replace('/[^a-z0-9]+/', '-', \strtolower((string)\pathinfo((string)$f['name'], \PATHINFO_FILENAME))) ?: 'img';
        $name = \trim($base, '-') . '-' . \substr(\md5((string)\microtime(true) . $f['name']), 0, 6) . '.' . $ext;
        if (!@\move_uploaded_file((string)$f['tmp_name'], $dir . $name)) {
            throw new \RuntimeException('Dosya kaydedilemedi.');
        }

        return \rtrim((string)Shop::getURL(), '/') . '/mediafiles/cardstrip/' . $name;
    }
}
