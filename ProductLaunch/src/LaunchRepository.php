<?php
declare(strict_types=1);

namespace Plugin\ProductLaunch;

use JTL\Shop;

final class LaunchRepository
{
    public const TABLE = 'twomosi_product_launch';

    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        try {
            Shop::Container()->getDB()->executeQueryPrepared(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    kID        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    kArtikel   INT UNSIGNED NOT NULL,
                    kKategorie INT UNSIGNED NOT NULL DEFAULT 0,
                    cBadge     VARCHAR(60)  NOT NULL DEFAULT \'\',
                    cTitle     VARCHAR(255) NOT NULL DEFAULT \'\',
                    cText      TEXT         NULL,
                    cButton    VARCHAR(80)  NOT NULL DEFAULT \'\',
                    dStart     DATE         NULL,
                    dEnd       DATE         NULL,
                    nActive    TINYINT(1)   NOT NULL DEFAULT 1,
                    nSort      INT          NOT NULL DEFAULT 0,
                    dCreated   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    cImage     VARCHAR(500) NOT NULL DEFAULT \'\',
                    cUrl       VARCHAR(500) NOT NULL DEFAULT \'\',
                    kKategorie2 INT UNSIGNED NOT NULL DEFAULT 0,
                    cCatLabel1 VARCHAR(80)  NOT NULL DEFAULT \'\',
                    cCatLabel2 VARCHAR(80)  NOT NULL DEFAULT \'\',
                    cGallery   TEXT         NULL,
                    PRIMARY KEY (kID),
                    KEY idx_active (nActive, nSort)
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                []
            );
            $db = Shop::Container()->getDB();
            if ($db->getSingleObject('SHOW COLUMNS FROM ' . self::TABLE . " LIKE 'cImage'") === null) {
                $db->executeQueryPrepared('ALTER TABLE ' . self::TABLE . " ADD COLUMN cImage VARCHAR(500) NOT NULL DEFAULT '', ADD COLUMN cUrl VARCHAR(500) NOT NULL DEFAULT ''", []);
            }
            if ($db->getSingleObject('SHOW COLUMNS FROM ' . self::TABLE . " LIKE 'cGallery'") === null) {
                $db->executeQueryPrepared('ALTER TABLE ' . self::TABLE . " ADD COLUMN kKategorie2 INT UNSIGNED NOT NULL DEFAULT 0, ADD COLUMN cCatLabel1 VARCHAR(80) NOT NULL DEFAULT '', ADD COLUMN cCatLabel2 VARCHAR(80) NOT NULL DEFAULT '', ADD COLUMN cGallery TEXT NULL", []);
            }
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
            return Shop::Container()->getDB()->getObjects(
                'SELECT * FROM ' . self::TABLE . ' ORDER BY nSort ASC, kID DESC'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Aktive Lansmanlar (Zeitraum beachtet).
     *
     * @return array<int,object>
     */
    public static function active(int $limit): array
    {
        // kein ensure() im Frontend (Tabelle wird im Dashboard angelegt)
        try {
            return Shop::Container()->getDB()->getObjects(
                'SELECT * FROM ' . self::TABLE . '
                  WHERE nActive = 1
                    AND (dStart IS NULL OR dStart <= CURDATE())
                    AND (dEnd IS NULL OR dEnd >= CURDATE())
               ORDER BY nSort ASC, kID DESC
                  LIMIT ' . \max(1, \min(10, $limit))
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function get(int $id): ?object
    {
        self::ensure();
        try {
            return Shop::Container()->getDB()->getSingleObject(
                'SELECT * FROM ' . self::TABLE . ' WHERE kID = :id',
                ['id' => $id]
            );
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
            'a'  => (int)$d['kArtikel'],
            'k'  => (int)$d['kKategorie'],
            'b'  => (string)$d['cBadge'],
            't'  => (string)$d['cTitle'],
            'x'  => (string)$d['cText'],
            'bt' => (string)$d['cButton'],
            's'  => $d['dStart'],
            'e'  => $d['dEnd'],
            'ac' => (int)$d['nActive'],
            'so' => (int)$d['nSort'],
            'im' => (string)($d['cImage'] ?? ''),
            'ur' => (string)($d['cUrl'] ?? ''),
            'k2' => (int)($d['kKategorie2'] ?? 0),
            'l1' => (string)($d['cCatLabel1'] ?? ''),
            'l2' => (string)($d['cCatLabel2'] ?? ''),
            'g'  => (string)($d['cGallery'] ?? ''),
        ];
        if ($id > 0) {
            $params['id'] = $id;
            $db->executeQueryPrepared(
                'UPDATE ' . self::TABLE . ' SET kArtikel = :a, kKategorie = :k, cBadge = :b, cTitle = :t, cText = :x,
                        cButton = :bt, dStart = :s, dEnd = :e, nActive = :ac, nSort = :so, cImage = :im, cUrl = :ur,
                        kKategorie2 = :k2, cCatLabel1 = :l1, cCatLabel2 = :l2, cGallery = :g
                  WHERE kID = :id',
                $params
            );

            return $id;
        }
        $db->executeQueryPrepared(
            'INSERT INTO ' . self::TABLE . ' (kArtikel, kKategorie, cBadge, cTitle, cText, cButton, dStart, dEnd, nActive, nSort, cImage, cUrl, kKategorie2, cCatLabel1, cCatLabel2, cGallery)
             VALUES (:a, :k, :b, :t, :x, :bt, :s, :e, :ac, :so, :im, :ur, :k2, :l1, :l2, :g)',
            $params
        );
        $row = $db->getSingleObject('SELECT MAX(kID) AS kID FROM ' . self::TABLE);

        return (int)($row->kID ?? 0);
    }

    public static function delete(int $id): void
    {
        self::ensure();
        Shop::Container()->getDB()->executeQueryPrepared(
            'DELETE FROM ' . self::TABLE . ' WHERE kID = :id',
            ['id' => $id]
        );
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
     * Speichert ein hochgeladenes Bild unter mediafiles/productlaunch/. Liefert die URL oder wirft eine Exception.
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
        $dir = \rtrim(\PFAD_ROOT, '/\\') . '/mediafiles/productlaunch/';
        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Klasör oluşturulamadı: mediafiles/productlaunch');
        }
        $base = \preg_replace('/[^a-z0-9]+/', '-', \strtolower((string)\pathinfo((string)$f['name'], \PATHINFO_FILENAME))) ?: 'img';
        $name = \trim($base, '-') . '-' . \substr(\md5((string)\microtime(true) . $f['name']), 0, 6) . '.' . $ext;
        if (!@\move_uploaded_file((string)$f['tmp_name'], $dir . $name)) {
            throw new \RuntimeException('Dosya kaydedilemedi.');
        }

        return \rtrim((string)Shop::getURL(), '/') . '/mediafiles/productlaunch/' . $name;
    }

    /**
     * @param array<int,mixed> $raw
     * @return array<int,int>
     */
    public static function ids(array $raw): array
    {
        $out = [];
        foreach ($raw as $v) {
            $id = (int)$v;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }

        return \array_values($out);
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array{id:int,name:string,artNr:string}>
     */
    public static function articleInfo(array $ids): array
    {
        $ids = self::ids($ids);
        if ($ids === []) {
            return [];
        }
        $out = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT a.kArtikel, COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName, COALESCE(a.cArtNr, '') AS cArtNr
                   FROM tartikel a
              LEFT JOIN (SELECT kArtikel, MIN(cName) AS cName FROM tartikelsprache GROUP BY kArtikel) ts ON ts.kArtikel = a.kArtikel
                  WHERE a.kArtikel IN (" . \implode(',', $ids) . ')'
            );
            foreach ($rows as $r) {
                $out[(int)$r->kArtikel] = ['id' => (int)$r->kArtikel, 'name' => (string)$r->cName, 'artNr' => (string)$r->cArtNr];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }

    /**
     * @return array<int,array{id:int,name:string,artNr:string}>
     */
    public static function searchArticles(string $query, int $limit = 20): array
    {
        $q = \trim($query);
        if ($q === '') {
            return [];
        }
        $limit = \max(1, \min(50, $limit));
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT a.kArtikel, COALESCE(NULLIF(a.cName, ''), ts.cName, '') AS cName, COALESCE(a.cArtNr, '') AS cArtNr
                   FROM tartikel a
              LEFT JOIN (SELECT kArtikel, MIN(cName) AS cName FROM tartikelsprache GROUP BY kArtikel) ts ON ts.kArtikel = a.kArtikel
                  WHERE a.kArtikel = :id
                     OR a.cArtNr LIKE :l1
                     OR a.cName LIKE :l2
                     OR ts.cName LIKE :l3
               ORDER BY (a.cArtNr = :e1) DESC, (a.kArtikel = :id2) DESC, a.kArtikel DESC
                  LIMIT {$limit}",
                [
                    'id'  => \ctype_digit($q) ? (int)$q : -1,
                    'id2' => \ctype_digit($q) ? (int)$q : -1,
                    'l1'  => '%' . $q . '%',
                    'l2'  => '%' . $q . '%',
                    'l3'  => '%' . $q . '%',
                    'e1'  => $q,
                ]
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['id' => (int)$r->kArtikel, 'name' => (string)$r->cName, 'artNr' => (string)$r->cArtNr];
        }

        return $out;
    }

    /**
     * @return array<int,array{id:int,name:string,artNr:string}>
     */
    public static function searchCategories(string $query, int $limit = 25): array
    {
        $q = \trim($query);
        if ($q === '') {
            return [];
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT kKategorie FROM tkategorie WHERE cName LIKE :l OR kKategorie = :id ORDER BY cName ASC LIMIT ' . (int)$limit,
                ['l' => '%' . $q . '%', 'id' => \ctype_digit($q) ? (int)$q : -1]
            );
        } catch (\Throwable $e) {
            return [];
        }
        $ids   = \array_map(static fn($r): int => (int)$r->kKategorie, $rows);
        $paths = self::categoryPaths($ids);
        $out   = [];
        foreach ($ids as $id) {
            $out[] = ['id' => $id, 'name' => $paths[$id] ?? ('#' . $id), 'artNr' => ''];
        }

        return $out;
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,string>
     */
    public static function categoryPaths(array $ids): array
    {
        $ids = self::ids($ids);
        if ($ids === []) {
            return [];
        }
        $out = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT n.kKategorie, p.cName
                   FROM tkategorie n
                   JOIN tkategorie p ON n.lft BETWEEN p.lft AND p.rght
                  WHERE n.kKategorie IN (' . \implode(',', $ids) . ')
               ORDER BY n.kKategorie, p.lft'
            );
            $parts = [];
            foreach ($rows as $r) {
                $parts[(int)$r->kKategorie][] = (string)$r->cName;
            }
            foreach ($parts as $id => $names) {
                $out[$id] = \implode(' › ', $names);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }

    /**
     * Name und SEO-URL einer Kategorie in der aktuellen Sprache.
     *
     * @return array{name:string,url:string}
     */
    public static function categoryLink(int $id, int $langId, string $shopUrl): array
    {
        $out = ['name' => '', 'url' => ''];
        if ($id <= 0) {
            return $out;
        }
        $db = Shop::Container()->getDB();
        try {
            $row = $db->getSingleObject(
                "SELECT COALESCE(NULLIF(ks.cName, ''), k.cName) AS cName
                   FROM tkategorie k
              LEFT JOIN tkategoriesprache ks ON ks.kKategorie = k.kKategorie AND ks.kSprache = :l
                  WHERE k.kKategorie = :id",
                ['id' => $id, 'l' => $langId]
            );
            if ($row !== null) {
                $out['name'] = (string)$row->cName;
            }
            $seo = $db->getSingleObject(
                "SELECT cSeo FROM tseo WHERE cKey = 'kKategorie' AND kKey = :id ORDER BY (kSprache = :l) DESC LIMIT 1",
                ['id' => $id, 'l' => $langId]
            );
            if ($seo !== null && (string)$seo->cSeo !== '') {
                $out['url'] = \rtrim($shopUrl, '/') . '/' . (string)$seo->cSeo;
            } else {
                $out['url'] = \rtrim($shopUrl, '/') . '/navi.php?k=' . $id;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $out;
    }
}
