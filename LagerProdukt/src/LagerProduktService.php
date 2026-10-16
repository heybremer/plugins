<?php

declare(strict_types=1);

namespace Plugin\LagerProdukt;

use JTL\Shop;

final class LagerProduktService
{
    public const PAGE_SLUG   = 'lager-produkte';
    public const TABLE_PAGE  = 'twomosi_lagerprodukt_page';
    public const TABLE_GROUP = 'twomosi_lagerprodukt_group';
    public const TABLE_ITEM  = 'twomosi_lagerprodukt_item';

    public static string $lastError = '';

    private const CATALOG_TTL = 900;
    private const CACHE_GEN   = 'lp_cat_gen';

    /** @var array<string, array{order:int,name:string,img:string}> */
    private const SEATS = [
        '1'  => ['order' => 7,  'name' => 'Aguti Milan 12', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/1.jpg'],
        '2'  => ['order' => 6,  'name' => 'Aguti Liner 12', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/2.jpg'],
        '3'  => ['order' => 1,  'name' => 'ISRI Captain Chair', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/3.jpg'],
        '4'  => ['order' => 3,  'name' => 'ISRI CC mit Gurt', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/4.jpg'],
        '5'  => ['order' => 2,  'name' => 'ISRI Ducato / Jumper / Boxer, Bj. 2006–2014', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/5.jpg'],
        '6'  => ['order' => 5,  'name' => 'Aguti Milan', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/6.jpg'],
        '7'  => ['order' => 4,  'name' => 'Aguti GIS-Liner', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/7.jpg'],
        '8'  => ['order' => 11, 'name' => 'Aguti GIS-Liner 3', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/8.jpg'],
        '9'  => ['order' => 8,  'name' => 'Ford Transit ab Bj. 2014', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/9.jpg'],
        '10' => ['order' => 9,  'name' => 'Ford Transit ab Bj. 2014 mit integrierter Kopfstütze', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/10.jpg'],
        '11' => ['order' => 12, 'name' => 'Aguti Milan 3', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/11.jpg'],
        '12' => ['order' => 13, 'name' => 'SKA 1800 M23 Komfort', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/12.jpg'],
        '13' => ['order' => 14, 'name' => 'SKA 1800/1000 M23 Classic', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/13.jpg'],
        '14' => ['order' => 10, 'name' => 'Mercedes-Benz Sprinter / Hymer', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/sitz/14.jpg'],
    ];

    /** @var array<string, array{products: list<array<string, mixed>>, groups: list<array<string, mixed>>}> */
    private static array $catalogMemo = [];

    private static bool $tablesReady = false;

    public static function ensureTables(): void
    {
        if (self::$tablesReady) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE_PAGE . ' (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    cName VARCHAR(160) NOT NULL DEFAULT "",
                    cSlug VARCHAR(160) NOT NULL DEFAULT "",
                    nSort INT NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_slug (cSlug)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE_GROUP . ' (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kPage INT NOT NULL DEFAULT 0,
                    cName VARCHAR(160) NOT NULL DEFAULT "",
                    nSort INT NOT NULL DEFAULT 0,
                    KEY ix_page (kPage)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            $db->executeQuery(
                'CREATE TABLE IF NOT EXISTS ' . self::TABLE_ITEM . ' (
                    kID INT AUTO_INCREMENT PRIMARY KEY,
                    kPage INT NOT NULL DEFAULT 0,
                    kGroup INT NOT NULL DEFAULT 0,
                    nMain TINYINT(1) NOT NULL DEFAULT 0,
                    cArtNr VARCHAR(64) NOT NULL DEFAULT "",
                    nSort INT NOT NULL DEFAULT 0,
                    UNIQUE KEY uq_page_art (kPage, cArtNr),
                    KEY ix_page (kPage),
                    KEY ix_group (kGroup)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            self::ensureItemGroupColumn($db);
            self::ensureItemMainColumn($db);
            self::ensureItemArtNrIndex($db);
            $ex = $db->getSingleObject('SELECT kID FROM ' . self::TABLE_PAGE . ' ORDER BY kID ASC LIMIT 1');
            if ($ex === null) {
                $row        = new \stdClass();
                $row->cName = 'Familie-Serie';
                $row->cSlug = self::PAGE_SLUG;
                $row->nSort = 1;
                $db->insert(self::TABLE_PAGE, $row);
            }
            self::$tablesReady = true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    /**
     * @return list<array{kID:int,cName:string,cSlug:string,nSort:int,nItems:int}>
     */
    public static function listPages(): array
    {
        self::ensureTables();
        $out = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT p.kID, p.cName, p.cSlug, p.nSort,
                        (SELECT COUNT(*) FROM ' . self::TABLE_ITEM . ' i WHERE i.kPage = p.kID) AS nItems
                   FROM ' . self::TABLE_PAGE . ' p
               ORDER BY p.nSort ASC, p.kID ASC'
            );
            if (!\is_array($rows)) {
                return [];
            }
            foreach ($rows as $r) {
                $out[] = [
                    'kID'    => (int)($r->kID ?? 0),
                    'cName'  => (string)($r->cName ?? ''),
                    'cSlug'  => (string)($r->cSlug ?? ''),
                    'nSort'  => (int)($r->nSort ?? 0),
                    'nItems' => (int)($r->nItems ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }

        return $out;
    }

    /**
     * @return array{kID:int,cName:string,cSlug:string}|null
     */
    public static function getPage(int $id): ?array
    {
        foreach (self::listPages() as $p) {
            if ($p['kID'] === $id) {
                return $p;
            }
        }

        return null;
    }

    /**
     * @return array{kID:int,cName:string,cSlug:string}|null
     */
    public static function getPageBySlug(string $slug): ?array
    {
        $slug = self::normalizeSlug($slug);
        foreach (self::listPages() as $p) {
            if ($p['cSlug'] === $slug) {
                return $p;
            }
        }

        return null;
    }

    /**
     * @return array{kID:int,cName:string,cSlug:string}|null
     */
    public static function currentPage(): ?array
    {
        $uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = \strtolower((string)(\parse_url($uri, \PHP_URL_PATH) ?? ''));
        $seoQ = \strtolower(\trim((string)($_GET['seo'] ?? '')));
        $best = null;
        $bestLen = 0;
        foreach (self::listPages() as $p) {
            $slug = $p['cSlug'];
            if ($slug === '') {
                continue;
            }
            if ($seoQ === $slug || \str_ends_with($path, '/' . $slug) || \basename($path) === $slug) {
                if (\strlen($slug) >= $bestLen) {
                    $best    = $p;
                    $bestLen = \strlen($slug);
                }
            }
        }
        if ($best !== null) {
            return $best;
        }
        $pages = self::listPages();

        return $pages[0] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function allSlugs(): array
    {
        $out = [];
        foreach (self::listPages() as $p) {
            if ($p['cSlug'] !== '') {
                $out[] = $p['cSlug'];
            }
        }

        return $out;
    }

    public static function createPage(string $name, string $slug = ''): int
    {
        self::ensureTables();
        $name = \trim($name);
        if ($name === '') {
            self::$lastError = 'Seitenname fehlt.';
            return 0;
        }
        $slug = self::normalizeSlug($slug !== '' ? $slug : $name);
        if ($slug === '') {
            self::$lastError = 'Slug ungültig.';
            return 0;
        }
        if (self::getPageBySlug($slug) !== null) {
            self::$lastError = 'Slug bereits vorhanden.';
            return 0;
        }
        try {
            $row        = new \stdClass();
            $row->cName = \substr($name, 0, 160);
            $row->cSlug = $slug;
            $row->nSort = \count(self::listPages()) + 1;
            $id         = (int)Shop::Container()->getDB()->insert(self::TABLE_PAGE, $row);
            if ($id > 0) {
                self::ensureShopLink($name, $slug);
                self::forgetCatalogCache();
            }

            return $id > 0 ? $id : 0;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return 0;
        }
    }

    public static function deletePage(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $pages = self::listPages();
        if (\count($pages) <= 1) {
            self::$lastError = 'Mindestens eine Seite muss bleiben.';
            return false;
        }
        $slug = '';
        foreach ($pages as $p) {
            if ((int)$p['kID'] === $id) {
                $slug = (string)$p['cSlug'];
                break;
            }
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQueryPrepared('DELETE FROM ' . self::TABLE_ITEM . ' WHERE kPage = :k', ['k' => $id]);
            $db->executeQueryPrepared('DELETE FROM ' . self::TABLE_GROUP . ' WHERE kPage = :k', ['k' => $id]);
            $db->executeQueryPrepared('DELETE FROM ' . self::TABLE_PAGE . ' WHERE kID = :k', ['k' => $id]);
            if ($slug !== '') {
                self::deleteShopLink($slug);
            }
            self::flushShopCache();
            self::forgetCatalogCache();

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return array{added:list<string>,exists:list<string>,missing:list<string>}
     */
    /**
     * @return list<array{kID:int,cName:string,nSort:int,nItems:int}>
     */
    public static function listGroups(int $pageId): array
    {
        self::ensureTables();
        $out = [];
        if ($pageId <= 0) {
            return [];
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT g.kID, g.cName, g.nSort,
                        (SELECT COUNT(*) FROM ' . self::TABLE_ITEM . ' i WHERE i.kGroup = g.kID) AS nItems
                   FROM ' . self::TABLE_GROUP . ' g
                  WHERE g.kPage = :p
               ORDER BY g.nSort ASC, g.kID ASC',
                ['p' => $pageId]
            );
            if (!\is_array($rows)) {
                return [];
            }
            foreach ($rows as $r) {
                $out[] = [
                    'kID'    => (int)($r->kID ?? 0),
                    'cName'  => (string)($r->cName ?? ''),
                    'nSort'  => (int)($r->nSort ?? 0),
                    'nItems' => (int)($r->nItems ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }

        return $out;
    }

    public static function createGroup(int $pageId, string $name): int
    {
        self::ensureTables();
        $name = \trim($name);
        if ($pageId <= 0 || self::getPage($pageId) === null) {
            self::$lastError = 'Seite nicht gefunden.';
            return 0;
        }
        if ($name === '') {
            self::$lastError = 'Gruppenname fehlt.';
            return 0;
        }
        try {
            $row        = new \stdClass();
            $row->kPage = $pageId;
            $row->cName = \substr($name, 0, 160);
            $row->nSort = \count(self::listGroups($pageId)) + 1;
            $id = (int)Shop::Container()->getDB()->insert(self::TABLE_GROUP, $row);
            if ($id > 0) {
                self::forgetCatalogCache();
            }

            return $id;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return 0;
        }
    }

    public static function deleteGroup(int $groupId, int $pageId): bool
    {
        if ($groupId <= 0 || $pageId <= 0) {
            return false;
        }
        try {
            $db = Shop::Container()->getDB();
            $db->executeQueryPrepared(
                'UPDATE ' . self::TABLE_ITEM . ' SET kGroup = 0, nMain = 0 WHERE kGroup = :g AND kPage = :p',
                ['g' => $groupId, 'p' => $pageId]
            );
            $db->executeQueryPrepared(
                'DELETE FROM ' . self::TABLE_GROUP . ' WHERE kID = :g AND kPage = :p2',
                ['g' => $groupId, 'p2' => $pageId]
            );
            self::forgetCatalogCache();

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    public static function addSkus(int $pageId, string $raw, int $groupId = 0): array
    {
        $result = ['added' => [], 'exists' => [], 'missing' => []];
        if ($pageId <= 0 || self::getPage($pageId) === null) {
            self::$lastError = 'Seite nicht gefunden.';
            return $result;
        }
        if ($groupId > 0) {
            $ok = false;
            foreach (self::listGroups($pageId) as $g) {
                if ($g['kID'] === $groupId) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                self::$lastError = 'Gruppe nicht gefunden.';
                return $result;
            }
        }
        $nrs = \preg_split('/[\s,;|]+/u', \trim($raw)) ?: [];
        $seen = [];
        $sort = self::nextItemSort($pageId);
        $db   = Shop::Container()->getDB();
        foreach ($nrs as $nr) {
            $nr = self::normalizeArtNr((string)$nr);
            $nr = \trim($nr, " \t\n\r\0\x0B\"'");
            if ($nr === '' || isset($seen[$nr])) {
                continue;
            }
            $seen[$nr] = true;
            $prod = self::findArticle($nr);
            if ($prod === null) {
                $result['missing'][] = $nr;
                continue;
            }
            try {
                $ex = $db->getSingleObject(
                    'SELECT kID, IFNULL(kGroup, 0) AS kGroup
                       FROM ' . self::TABLE_ITEM . '
                      WHERE kPage = :p AND cArtNr = :a LIMIT 1',
                    ['p' => $pageId, 'a' => $nr]
                );
                if ($ex !== null) {
                    if ($groupId > 0) {
                        $oldGroupId = (int)($ex->kGroup ?? 0);
                        $db->executeQueryPrepared(
                            'UPDATE ' . self::TABLE_ITEM . ' SET kGroup = :g, nMain = 0 WHERE kID = :id',
                            ['g' => $groupId, 'id' => (int)($ex->kID ?? 0)]
                        );
                        if ($oldGroupId > 0 && $oldGroupId !== $groupId) {
                            self::normalizeGroupMain($pageId, $oldGroupId);
                        }
                        self::normalizeGroupMain($pageId, $groupId);
                    }
                    $result['exists'][] = $nr;
                    continue;
                }
                $row         = new \stdClass();
                $row->kPage  = $pageId;
                $row->kGroup = $groupId;
                $row->nMain  = 0;
                $row->cArtNr = $nr;
                $row->nSort  = $sort++;
                $itemId = (int)$db->insert(self::TABLE_ITEM, $row);
                if ($groupId > 0) {
                    self::normalizeGroupMain($pageId, $groupId);
                }
                $result['added'][] = $nr;
            } catch (\Throwable $e) {
                self::$lastError = $e->getMessage();
            }
        }
        if ($result['missing'] !== []) {
            self::$lastError = 'Nicht gefunden: ' . \implode(', ', $result['missing']);
        }
        if ($result['added'] !== [] || $result['exists'] !== []) {
            self::forgetCatalogCache();
        }

        return $result;
    }

    public static function removeSku(int $itemId): bool
    {
        if ($itemId <= 0) {
            return false;
        }
        try {
            $db = Shop::Container()->getDB();
            $item = $db->getSingleObject(
                'SELECT kPage, IFNULL(kGroup, 0) AS kGroup FROM ' . self::TABLE_ITEM . ' WHERE kID = :k',
                ['k' => $itemId]
            );
            $db->executeQueryPrepared(
                'DELETE FROM ' . self::TABLE_ITEM . ' WHERE kID = :k',
                ['k' => $itemId]
            );
            if ($item !== null && (int)($item->kGroup ?? 0) > 0) {
                self::normalizeGroupMain((int)($item->kPage ?? 0), (int)$item->kGroup);
            }
            self::forgetCatalogCache();

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @param list<int> $itemIds
     */
    public static function removeSkus(array $itemIds, int $pageId): int
    {
        if ($pageId <= 0) {
            return 0;
        }
        $ids = [];
        foreach ($itemIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            self::$lastError = 'Keine Artikel ausgewählt.';
            return 0;
        }
        try {
            $db   = Shop::Container()->getDB();
            $deleted = 0;
            $groups = [];
            foreach ($ids as $id) {
                $item = $db->getSingleObject(
                    'SELECT IFNULL(kGroup, 0) AS kGroup FROM ' . self::TABLE_ITEM . '
                      WHERE kID = :k AND kPage = :p',
                    ['k' => $id, 'p' => $pageId]
                );
                if ($item !== null && (int)($item->kGroup ?? 0) > 0) {
                    $groups[(int)$item->kGroup] = true;
                }
                $db->executeQueryPrepared(
                    'DELETE FROM ' . self::TABLE_ITEM . ' WHERE kID = :k AND kPage = :p',
                    ['k' => $id, 'p' => $pageId]
                );
                ++$deleted;
            }
            foreach (\array_keys($groups) as $groupId) {
                self::normalizeGroupMain($pageId, (int)$groupId);
            }
            if ($deleted > 0) {
                self::forgetCatalogCache();
            }

            return $deleted;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return 0;
        }
    }

    public static function clearGroupItems(int $pageId, int $groupId): int
    {
        if ($pageId <= 0 || $groupId < 0) {
            return 0;
        }
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject(
                'SELECT COUNT(*) AS n FROM ' . self::TABLE_ITEM . '
                  WHERE kPage = :p AND IFNULL(kGroup, 0) = :g',
                ['p' => $pageId, 'g' => $groupId]
            );
            $n = (int)($row->n ?? 0);
            if ($n <= 0) {
                return 0;
            }
            $db->executeQueryPrepared(
                'DELETE FROM ' . self::TABLE_ITEM . ' WHERE kPage = :p AND IFNULL(kGroup, 0) = :g',
                ['p' => $pageId, 'g' => $groupId]
            );
            self::forgetCatalogCache();

            return $n;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return 0;
        }
    }

    public static function setMainItem(int $pageId, int $groupId, int $itemId): bool
    {
        if ($pageId <= 0 || $groupId <= 0 || $itemId <= 0) {
            self::$lastError = 'Ungültige Auswahl.';
            return false;
        }
        try {
            $db = Shop::Container()->getDB();
            $item = $db->getSingleObject(
                'SELECT kID FROM ' . self::TABLE_ITEM . '
                  WHERE kID = :i AND kPage = :p AND kGroup = :g',
                ['i' => $itemId, 'p' => $pageId, 'g' => $groupId]
            );
            if ($item === null) {
                self::$lastError = 'Artikel nicht in dieser Gruppe gefunden.';
                return false;
            }
            $db->executeQueryPrepared(
                'UPDATE ' . self::TABLE_ITEM . '
                    SET nMain = CASE WHEN kID = :i THEN 1 ELSE 0 END
                  WHERE kPage = :p AND kGroup = :g',
                ['i' => $itemId, 'p' => $pageId, 'g' => $groupId]
            );
            self::forgetCatalogCache();

            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listItems(int $pageId): array
    {
        $out = [];
        if ($pageId <= 0) {
            return [];
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                'SELECT kID, cArtNr, nSort, IFNULL(kGroup, 0) AS kGroup,
                        IFNULL(nMain, 0) AS nMain
                   FROM ' . self::TABLE_ITEM . '
                  WHERE kPage = :p
               ORDER BY nSort ASC, kID ASC',
                ['p' => $pageId]
            );
            if (!\is_array($rows)) {
                return [];
            }
            $groupNames = [];
            foreach (self::listGroups($pageId) as $g) {
                $groupNames[$g['kID']] = $g['cName'];
            }
            $groupsWithMain = [];
            foreach ($rows as $r) {
                $gid = (int)($r->kGroup ?? 0);
                if ($gid > 0 && (int)($r->nMain ?? 0) === 1) {
                    $groupsWithMain[$gid] = true;
                }
            }
            $fallbackMain = [];
            foreach ($rows as $r) {
                $nr   = \trim((string)($r->cArtNr ?? ''));
                $gid  = (int)($r->kGroup ?? 0);
                $prod = self::findArticle($nr);
                $isMain = (int)($r->nMain ?? 0);
                if ($gid > 0 && !isset($groupsWithMain[$gid]) && !isset($fallbackMain[$gid])) {
                    $isMain = 1;
                    $fallbackMain[$gid] = true;
                }
                $out[] = [
                    'kID'        => (int)($r->kID ?? 0),
                    'cArtNr'     => $nr,
                    'kGroup'     => $gid,
                    'nMain'      => $isMain,
                    'cGroupName' => $groupNames[$gid] ?? '',
                    'cName'      => $prod['cName'] ?? '',
                    'found'      => $prod !== null,
                ];
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }

        return $out;
    }

    /**
     * @return list<array{cArtNr:string,kGroup:int,nMain:int,cGroupName:string}>
     */
    private static function listItemAssignments(int $pageId, int $groupId = 0): array
    {
        $out = [];
        if ($pageId <= 0) {
            return [];
        }
        try {
            $sql = 'SELECT cArtNr, IFNULL(kGroup, 0) AS kGroup, IFNULL(nMain, 0) AS nMain
                      FROM ' . self::TABLE_ITEM . '
                     WHERE kPage = :p';
            $params = ['p' => $pageId];
            if ($groupId > 0) {
                $sql .= ' AND kGroup = :g';
                $params['g'] = $groupId;
            }
            $rows = Shop::Container()->getDB()->getObjects(
                $sql . ' ORDER BY nSort ASC, kID ASC',
                $params
            );
            if (!\is_array($rows)) {
                return [];
            }
            $groupNames = [];
            foreach (self::listGroups($pageId) as $g) {
                $groupNames[$g['kID']] = $g['cName'];
            }
            $groupsWithMain = [];
            foreach ($rows as $r) {
                $gid = (int)($r->kGroup ?? 0);
                if ($gid > 0 && (int)($r->nMain ?? 0) === 1) {
                    $groupsWithMain[$gid] = true;
                }
            }
            $fallbackMain = [];
            foreach ($rows as $r) {
                $nr  = \trim((string)($r->cArtNr ?? ''));
                $gid = (int)($r->kGroup ?? 0);
                $isMain = (int)($r->nMain ?? 0);
                if ($gid > 0 && !isset($groupsWithMain[$gid]) && !isset($fallbackMain[$gid])) {
                    $isMain = 1;
                    $fallbackMain[$gid] = true;
                }
                $out[] = [
                    'cArtNr'     => $nr,
                    'kGroup'     => $gid,
                    'nMain'      => $isMain,
                    'cGroupName' => $groupNames[$gid] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadProducts(?int $pageId = null): array
    {
        return self::loadCatalog($pageId)['products'];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function loadGroupedProducts(?int $pageId = null): array
    {
        return self::loadCatalog($pageId)['groups'];
    }

    /**
     * @return list<array{code:string,name:string,img:string,order:int,groups:list<int>}>
     */
    public static function loadCompatibleSeats(?int $pageId = null): array
    {
        $byCode = [];
        foreach (self::SEATS as $code => $meta) {
            $byCode[$code] = [
                'code'      => (string)$code,
                'name'      => $meta['name'],
                'img'       => $meta['img'],
                'order'     => $meta['order'],
                'groups'    => [],
                'groupsCsv' => '',
                'hasSeries' => false,
            ];
        }
        foreach (self::loadGroupedProducts($pageId) as $group) {
            $gid = (int)($group['id'] ?? 0);
            foreach ((array)($group['seats'] ?? []) as $code) {
                $code = (string)$code;
                if (!isset($byCode[$code])) {
                    continue;
                }
                if (!\in_array($gid, $byCode[$code]['groups'], true)) {
                    $byCode[$code]['groups'][] = $gid;
                }
                $byCode[$code]['groupsCsv'] = \implode(',', $byCode[$code]['groups']);
                $byCode[$code]['hasSeries'] = $byCode[$code]['groups'] !== [];
            }
        }
        $seats = \array_values($byCode);
        \usort($seats, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return $seats;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getProductDetailVariantContext(string $artNr): ?array
    {
        $artNr = self::normalizeArtNr($artNr);
        if ($artNr === '') {
            return null;
        }
        $lid        = \max(1, (int)Shop::getLanguageID());
        $generation = (int)self::cacheRead(self::CACHE_GEN);
        $cacheKey   = 'lp_detail_ctx2_' . \md5($artNr . '|' . $lid) . '_' . $generation;
        $cached     = self::cacheRead($cacheKey);
        if (\is_array($cached)) {
            if ($cached === [] || empty($cached['variants'])) {
                return null;
            }
            $cached['selectedSeat'] = self::seatCodeFromValue((string)($_GET['sitz'] ?? ''));

            return $cached;
        }
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT i.kPage, i.kGroup, p.cName AS cPageName, p.cSlug,
                        COALESCE(g.cName, "") AS cGroupName
                   FROM ' . self::TABLE_ITEM . ' i
                   JOIN ' . self::TABLE_PAGE . ' p ON p.kID = i.kPage
              LEFT JOIN ' . self::TABLE_GROUP . ' g
                     ON g.kID = i.kGroup AND g.kPage = i.kPage
                  WHERE i.cArtNr = :nr AND i.kGroup > 0
               ORDER BY p.nSort ASC, p.kID ASC, g.nSort ASC, g.kID ASC
                  LIMIT 1',
                ['nr' => $artNr]
            );
            if ($row === null) {
                self::cacheWrite($cacheKey, []);

                return null;
            }
            $pageId    = (int)($row->kPage ?? 0);
            $groupId   = (int)($row->kGroup ?? 0);
            $groupName = (string)($row->cGroupName ?? '');
            if ($pageId <= 0 || $groupId <= 0) {
                self::cacheWrite($cacheKey, []);

                return null;
            }
            $variants = self::attachSeats(self::fetchProductsFromDb($pageId, $groupId));
            if ($variants === []) {
                self::cacheWrite($cacheKey, []);

                return null;
            }
            $productName = '';
            foreach ($variants as &$variant) {
                $variant['isCurrent'] = (string)($variant['artNr'] ?? '') === $artNr;
                if (!empty($variant['isCurrent'])) {
                    $productName = (string)($variant['name'] ?? '');
                }
            }
            unset($variant);
            if ($productName === '') {
                $productName = (string)($variants[0]['name'] ?? '');
            }
            $seats = self::uniqueSeatCodes($variants);
            if ($seats === []) {
                $seats = self::loadSeatsForSeriesName($groupName);
            }
            $context = [
                'pageName'        => (string)($row->cPageName ?? ''),
                'pageUrl'         => self::pageUrl((string)($row->cSlug ?? '')),
                'groupName'       => $groupName,
                'productName'     => $productName,
                'current'         => $artNr,
                'compatibleSeats' => self::seatOptionsFromCodes($seats),
                'compatibleTypes' => [],
                'showVariants'    => \count($variants) >= 2,
                'variants'        => $variants,
            ];
            self::cacheWrite($cacheKey, $context);
            $context['selectedSeat'] = self::seatCodeFromValue((string)($_GET['sitz'] ?? ''));

            return $context;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();

            return null;
        }
    }

    /**
     * @param list<string> $codes
     * @return list<array{code:string,name:string}>
     */
    private static function seatOptionsFromCodes(array $codes): array
    {
        $out = [];
        foreach ($codes as $code) {
            $code = (string)$code;
            if (!isset(self::SEATS[$code])) {
                continue;
            }
            $out[] = [
                'code'  => $code,
                'name'  => self::SEATS[$code]['name'],
                'order' => self::SEATS[$code]['order'],
            ];
        }
        if ($out === []) {
            foreach (self::SEATS as $code => $meta) {
                $out[] = [
                    'code'  => (string)$code,
                    'name'  => $meta['name'],
                    'order' => $meta['order'],
                ];
            }
        }
        \usort($out, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return \array_map(static fn (array $seat): array => [
            'code' => $seat['code'],
            'name' => $seat['name'],
        ], $out);
    }

    /**
     * @param array{kArtikel?:int,oPosition_arr?:mixed} $args
     */
    public static function applyDynamicCartTitle(array &$args): void
    {
        $raw = \trim((string)($_POST['lp_dyn_title'] ?? $_POST['fk_womosi_dyn_title'] ?? ''));
        if ($raw === '') {
            return;
        }
        $dyn = \trim((string)(\preg_replace('/\s+/u', ' ', \strip_tags($raw)) ?? ''));
        if ($dyn === '') {
            return;
        }
        if (\mb_strlen($dyn) > 255) {
            $dyn = \mb_substr($dyn, 0, 252) . '…';
        }
        if (!isset($args['oPosition_arr']) || !\is_array($args['oPosition_arr'])) {
            return;
        }
        $positions = &$args['oPosition_arr'];
        $kArtikel  = (int)($args['kArtikel'] ?? 0);
        if ($kArtikel <= 0) {
            return;
        }
        for ($i = \count($positions) - 1; $i >= 0; $i--) {
            $position = $positions[$i];
            if (!\is_object($position) || (int)($position->kArtikel ?? 0) !== $kArtikel) {
                continue;
            }
            if (\is_array($position->cName ?? null)) {
                foreach (\array_keys($position->cName) as $code) {
                    $position->cName[$code] = $dyn;
                }
            } else {
                $position->cName = $dyn;
            }
            return;
        }
    }

    /**
     * @return list<string>
     */
    private static function loadCompatibleVehicleTypes(string $artNr, string $groupName): array
    {
        $langId = \max(1, (int)Shop::getLanguageID());
        $key    = 'lp_vehicle_types_' . \md5($artNr . '|' . $langId);
        $cached = self::cacheRead($key);
        if (\is_array($cached) && $cached !== []) {
            return \array_values(\array_filter(\array_map('strval', $cached)));
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT DISTINCT TRIM(mws.cWert) AS cWert
                   FROM tartikel a
                   JOIN tartikelmerkmal am ON am.kArtikel = a.kArtikel
                   JOIN tmerkmalsprache ms
                     ON ms.kMerkmal = am.kMerkmal
                    AND ms.kSprache = :lang
                    AND ms.cName = 'Wohnmobil-Aufbauart'
                   JOIN tmerkmalwertsprache mws
                     ON mws.kMerkmalWert = am.kMerkmalWert
                    AND mws.kSprache = :lang
                  WHERE a.cArtNr = :nr
                    AND TRIM(mws.cWert) <> ''
               ORDER BY mws.cWert ASC",
                ['lang' => $langId, 'nr' => $artNr]
            );
            $types = [];
            foreach ((array)$rows as $row) {
                $value = \trim((string)($row->cWert ?? ''));
                if ($value !== '') {
                    $types[$value] = $value;
                }
            }
            $types = \array_values($types);
            self::cacheWrite($key, $types);
            if ($types !== []) {
                return $types;
            }
        } catch (\Throwable $e) {
        }

        $seriesTypes = self::loadCompatibleVehicleTypesForSeries($groupName, $langId);
        if ($seriesTypes !== []) {
            return $seriesTypes;
        }

        return self::loadCompatibleVehicleTypesForSeats($artNr, $langId);
    }

    /**
     * @return list<string>
     */
    private static function loadCompatibleVehicleTypesForSeries(string $groupName, int $langId): array
    {
        $series = \trim((string)\preg_replace('/\bserie\b/iu', '', $groupName));
        if ($series === '') {
            return [];
        }
        $key    = 'lp_vehicle_series_types_v2_' . \md5($series . '|' . $langId);
        $cached = self::cacheRead($key);
        if (\is_array($cached)) {
            return \array_values(\array_filter(\array_map('strval', $cached)));
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT DISTINCT TRIM(type_ws.cWert) AS cWert
                   FROM tartikel a
                   JOIN tartikelmerkmal type_am ON type_am.kArtikel = a.kArtikel
                   JOIN tmerkmalsprache type_ms
                     ON type_ms.kMerkmal = type_am.kMerkmal
                    AND type_ms.kSprache = :lang
                    AND type_ms.cName = 'Wohnmobil-Aufbauart'
                   JOIN tmerkmalwertsprache type_ws
                     ON type_ws.kMerkmalWert = type_am.kMerkmalWert
                    AND type_ws.kSprache = :lang
                   JOIN tartikelmerkmal series_am ON series_am.kArtikel = a.kArtikel
                   JOIN tmerkmalsprache series_ms
                     ON series_ms.kMerkmal = series_am.kMerkmal
                    AND series_ms.kSprache = :lang
                    AND series_ms.cName IN ('Serie', 'kompatible Serien', 'Familie-Serie')
                   JOIN tmerkmalwertsprache series_ws
                     ON series_ws.kMerkmalWert = series_am.kMerkmalWert
                    AND series_ws.kSprache = :lang
                  WHERE TRIM(REPLACE(REPLACE(UPPER(series_ws.cWert), 'SERIE', ''), '-', ''))
                        = UPPER(:series)
                    AND TRIM(type_ws.cWert) <> ''
               ORDER BY type_ws.cWert ASC",
                ['lang' => $langId, 'series' => $series]
            );
            $types = [];
            foreach ((array)$rows as $row) {
                $value = \trim((string)($row->cWert ?? ''));
                if ($value !== '') {
                    $types[$value] = $value;
                }
            }
            $types = \array_values($types);
            self::cacheWrite($key, $types);
            return $types;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    private static function loadCompatibleVehicleTypesForSeats(string $artNr, int $langId): array
    {
        $key    = 'lp_vehicle_seat_types_' . \md5($artNr . '|' . $langId);
        $cached = self::cacheRead($key);
        if (\is_array($cached)) {
            return \array_values(\array_filter(\array_map('strval', $cached)));
        }
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT DISTINCT TRIM(type_ws.cWert) AS cWert
                   FROM tartikel current_article
                   JOIN tartikelmerkmal current_seat
                     ON current_seat.kArtikel = current_article.kArtikel
                   JOIN tmerkmalsprache seat_ms
                     ON seat_ms.kMerkmal = current_seat.kMerkmal
                    AND seat_ms.kSprache = :lang
                    AND seat_ms.cName = 'kompatible Sitze'
                   JOIN tartikelmerkmal matching_seat
                     ON matching_seat.kMerkmalWert = current_seat.kMerkmalWert
                   JOIN tartikelmerkmal type_am
                     ON type_am.kArtikel = matching_seat.kArtikel
                   JOIN tmerkmalsprache type_ms
                     ON type_ms.kMerkmal = type_am.kMerkmal
                    AND type_ms.kSprache = :lang
                    AND type_ms.cName = 'Wohnmobil-Aufbauart'
                   JOIN tmerkmalwertsprache type_ws
                     ON type_ws.kMerkmalWert = type_am.kMerkmalWert
                    AND type_ws.kSprache = :lang
                  WHERE current_article.cArtNr = :nr
                    AND TRIM(type_ws.cWert) <> ''
               ORDER BY type_ws.cWert ASC",
                ['lang' => $langId, 'nr' => $artNr]
            );
            $types = [];
            foreach ((array)$rows as $row) {
                $value = \trim((string)($row->cWert ?? ''));
                if ($value !== '') {
                    $types[$value] = $value;
                }
            }
            $types = \array_values($types);
            self::cacheWrite($key, $types);
            return $types;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{products: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    private static function loadCatalog(?int $pageId = null): array
    {
        self::ensureTables();
        if ($pageId === null || $pageId <= 0) {
            $page = self::currentPage();
            $pageId = (int)($page['kID'] ?? 0);
        }
        $key = self::catalogCacheKey($pageId);
        if (isset(self::$catalogMemo[$key])) {
            return self::$catalogMemo[$key];
        }
        $cached = self::cacheRead($key);
        if (\is_array($cached) && isset($cached['products'], $cached['groups'])) {
            self::$catalogMemo[$key] = $cached;

            return $cached;
        }
        $products = self::attachSeats(self::fetchProductsFromDb($pageId));
        $payload  = [
            'products' => $products,
            'groups'   => self::buildGroupedProducts($pageId, $products),
        ];
        self::$catalogMemo[$key] = $payload;
        self::cacheWrite($key, $payload);

        return $payload;
    }

    public static function forgetCatalogCache(): void
    {
        self::$catalogMemo = [];
        $gen = (int)self::cacheRead(self::CACHE_GEN);
        self::cacheWrite(self::CACHE_GEN, $gen + 1);
    }

    private static function catalogCacheKey(int $pageId): string
    {
        $lid = (int)Shop::getLanguageID();
        if ($lid <= 0) {
            $lid = 1;
        }

        return 'lp_cat_seat1_' . $pageId . '_' . $lid . '_' . (int)self::cacheRead(self::CACHE_GEN);
    }

    private static function cacheRead(string $key): mixed
    {
        try {
            $cache = Shop::Container()->getCache();
            if (!\method_exists($cache, 'get')) {
                return null;
            }
            $val = $cache->get($key);
            if ($val === false) {
                return null;
            }

            return $val;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function cacheWrite(string $key, mixed $value): void
    {
        try {
            $cache = Shop::Container()->getCache();
            if (!\method_exists($cache, 'set')) {
                return;
            }
            $cache->set($key, $value, ['lagerprodukt'], self::CATALOG_TTL);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fetchProductsFromDb(int $pageId, int $groupId = 0): array
    {
        $items = self::listItemAssignments($pageId, $groupId);
        $nrs   = [];
        foreach ($items as $it) {
            if ($it['cArtNr'] !== '') {
                $nrs[] = (string)$it['cArtNr'];
            }
        }
        if ($nrs === []) {
            return [];
        }

        try {
            $db  = Shop::Container()->getDB();
            $lid = (int)Shop::getLanguageID();
            if ($lid <= 0) {
                $lid = 1;
            }
            $shopUrl = \rtrim((string)Shop::getURL(), '/');
            $in      = \implode(',', \array_map(static fn (string $n): string => "'" . \addslashes($n) . "'", $nrs));
            $rows    = $db->getObjects(
                "SELECT a.kArtikel,
                        COALESCE(a.cArtNr, '') AS cArtNr,
                        COALESCE(NULLIF(ts.cName, ''), NULLIF(a.cName, ''), '') AS cName,
                        COALESCE(NULLIF(seo.cSeo, ''), NULLIF(a.cSeo, ''), '') AS cSeo,
                        COALESCE(a.fStandardpreisNetto, 0) AS fNetFallback,
                        COALESCE(a.fMwSt, 0) AS fMwSt,
                        COALESCE(a.fLagerbestand, 0) AS nStock,
                        COALESCE(pic.nNr, 1) AS nNr
                   FROM tartikel a
                   LEFT JOIN tartikelsprache ts
                          ON ts.kArtikel = a.kArtikel AND ts.kSprache = :lid1
                   LEFT JOIN tseo seo
                          ON seo.cKey = 'kArtikel'
                         AND seo.kKey = a.kArtikel
                         AND seo.kSprache = :lid2
                   LEFT JOIN (
                        SELECT p1.kArtikel, MIN(p1.nNr) AS nNr
                          FROM tartikelpict p1
                      GROUP BY p1.kArtikel
                   ) pic ON pic.kArtikel = a.kArtikel
                  WHERE TRIM(a.cArtNr) IN ({$in})
               ORDER BY a.cArtNr ASC",
                ['lid1' => $lid, 'lid2' => $lid]
            );
            if (!\is_array($rows)) {
                return [];
            }

            $byNr = [];
            foreach ($rows as $r) {
                $artNr = \trim((string)($r->cArtNr ?? ''));
                if ($artNr === '') {
                    continue;
                }
                $kArtikel = (int)($r->kArtikel ?? 0);
                $name     = \trim((string)($r->cName ?? ''));
                $seo      = \trim((string)($r->cSeo ?? ''));
                $mwst     = (float)($r->fMwSt ?? 0);
                if ($mwst <= 0) {
                    $mwst = 19.0;
                }
                $net    = self::loadNettoPrice($kArtikel, (float)($r->fNetFallback ?? 0));
                $brutto = \round($net * (1 + $mwst / 100), 2);
                $imgs   = self::productImages($shopUrl, $kArtikel, $name, $seo, $artNr, (int)($r->nNr ?? 1));
                $byNr[$artNr] = [
                    'id'     => $kArtikel,
                    'artNr'  => $artNr,
                    'name'   => $name !== '' ? $name : ('Artikel ' . $kArtikel),
                    'url'    => self::productUrl($shopUrl, $kArtikel, $seo),
                    'img'    => $imgs['md'],
                    'imgLg'  => $imgs['lg'],
                    'price'  => $brutto,
                    'priceF' => \number_format($brutto, 2, ',', '.') . ' €',
                    'stock'  => (float)($r->nStock ?? 0),
                ];
            }

            $out = [];
            foreach ($items as $it) {
                $nr = (string)($it['cArtNr'] ?? '');
                if (!isset($byNr[$nr])) {
                    continue;
                }
                $row = $byNr[$nr];
                $row['groupId']   = (int)($it['kGroup'] ?? 0);
                $row['groupName'] = (string)($it['cGroupName'] ?? '');
                $row['isMain']    = (int)($it['nMain'] ?? 0) === 1;
                $out[] = $row;
            }

            return $out;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return list<array<string,mixed>>
     */
    private static function buildGroupedProducts(int $pageId, array $products): array
    {
        $groups = self::listGroups($pageId);
        $out = [];
        foreach ($groups as $g) {
            $out[$g['kID']] = [
                'id'          => $g['kID'],
                'name'        => $g['cName'],
                'products'    => [],
                'mainProduct' => null,
                'variants'    => [],
                'seats'       => [],
                'seatsCsv'    => '',
            ];
        }
        $other = [
            'id'          => 0,
            'name'        => 'Weitere Produkte',
            'products'    => [],
            'mainProduct' => null,
            'variants'    => [],
            'seats'       => [],
            'seatsCsv'    => '',
        ];
        foreach ($products as $p) {
            $gid = (int)($p['groupId'] ?? 0);
            if ($gid > 0 && isset($out[$gid])) {
                $out[$gid]['products'][] = $p;
            } else {
                $other['products'][] = $p;
            }
        }
        foreach ($out as &$group) {
            foreach ($group['products'] as $product) {
                if ($group['mainProduct'] === null || !empty($product['isMain'])) {
                    $group['mainProduct'] = $product;
                    if (!empty($product['isMain'])) {
                        break;
                    }
                }
            }
            $group['variants'] = $group['products'];
            $group['seats'] = self::uniqueSeatCodes($group['products']);
            if ($group['seats'] === []) {
                $group['seats'] = self::loadSeatsForSeriesName((string)$group['name']);
            }
            $group['seatsCsv'] = \implode(',', $group['seats']);
        }
        unset($group);
        $other['variants'] = $other['products'];
        $other['seats'] = self::uniqueSeatCodes($other['products']);
        $other['seatsCsv'] = \implode(',', $other['seats']);
        $list = \array_values(\array_filter($out, static fn (array $g): bool => $g['products'] !== []));
        if ($other['products'] !== []) {
            $list[] = $other;
        }

        return $list;
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    private static function attachSeats(array $products): array
    {
        $nrs = [];
        foreach ($products as $product) {
            $nr = \trim((string)($product['artNr'] ?? ''));
            if ($nr !== '') {
                $nrs[$nr] = $nr;
            }
        }
        $map = self::loadSeatsByArtNr(\array_values($nrs));
        foreach ($products as &$product) {
            $product['seats'] = $map[\trim((string)($product['artNr'] ?? ''))] ?? [];
            $product['seatsCsv'] = \implode(',', $product['seats']);
        }
        unset($product);

        return $products;
    }

    /**
     * @param list<string> $artNrs
     * @return array<string, list<string>>
     */
    private static function loadSeatsByArtNr(array $artNrs): array
    {
        if ($artNrs === []) {
            return [];
        }
        $langId = \max(1, (int)Shop::getLanguageID());
        $key    = 'lp_seats_by_art_' . \md5(\implode('|', $artNrs) . '|' . $langId);
        $cached = self::cacheRead($key);
        if (\is_array($cached)) {
            return $cached;
        }
        $map = [];
        try {
            $in = \implode(',', \array_map(
                static fn (string $nr): string => "'" . \addslashes($nr) . "'",
                $artNrs
            ));
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT COALESCE(a.cArtNr, '') AS cArtNr, TRIM(mws.cWert) AS cWert
                   FROM tartikel a
                   JOIN tartikelmerkmal am ON am.kArtikel = a.kArtikel
                   JOIN tmerkmalsprache ms
                     ON ms.kMerkmal = am.kMerkmal
                    AND ms.kSprache = :lang
                    AND ms.cName = 'kompatible Sitze'
                   JOIN tmerkmalwertsprache mws
                     ON mws.kMerkmalWert = am.kMerkmalWert
                    AND mws.kSprache = :lang
                  WHERE a.cArtNr IN ({$in})
                    AND TRIM(mws.cWert) <> ''",
                ['lang' => $langId]
            );
            foreach ((array)$rows as $row) {
                $nr   = \trim((string)($row->cArtNr ?? ''));
                $code = self::seatCodeFromValue((string)($row->cWert ?? ''));
                if ($nr === '' || $code === '') {
                    continue;
                }
                if (!isset($map[$nr])) {
                    $map[$nr] = [];
                }
                if (!\in_array($code, $map[$nr], true)) {
                    $map[$nr][] = $code;
                }
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }
        self::cacheWrite($key, $map);

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return list<string>
     */
    private static function uniqueSeatCodes(array $products): array
    {
        $codes = [];
        foreach ($products as $product) {
            foreach ((array)($product['seats'] ?? []) as $code) {
                $code = (string)$code;
                if (isset(self::SEATS[$code])) {
                    $codes[$code] = $code;
                }
            }
        }

        return \array_values($codes);
    }

    /**
     * @return list<string>
     */
    private static function loadSeatsForSeriesName(string $groupName): array
    {
        $series = \trim((string)\preg_replace('/\bserie\b/iu', '', $groupName));
        if ($series === '') {
            return [];
        }
        $langId = \max(1, (int)Shop::getLanguageID());
        $key    = 'lp_series_seats_' . \md5($series . '|' . $langId);
        $cached = self::cacheRead($key);
        if (\is_array($cached)) {
            return \array_values(\array_filter(\array_map('strval', $cached)));
        }
        $codes = [];
        try {
            $rows = Shop::Container()->getDB()->getObjects(
                "SELECT DISTINCT TRIM(seat_ws.cWert) AS cWert
                   FROM tartikel a
                   JOIN tartikelmerkmal series_am ON series_am.kArtikel = a.kArtikel
                   JOIN tmerkmalsprache series_ms
                     ON series_ms.kMerkmal = series_am.kMerkmal
                    AND series_ms.kSprache = :lang
                    AND series_ms.cName IN ('Serie', 'kompatible Serien', 'Familie-Serie')
                   JOIN tmerkmalwertsprache series_ws
                     ON series_ws.kMerkmalWert = series_am.kMerkmalWert
                    AND series_ws.kSprache = :lang
                   JOIN tartikelmerkmal seat_am ON seat_am.kArtikel = a.kArtikel
                   JOIN tmerkmalsprache seat_ms
                     ON seat_ms.kMerkmal = seat_am.kMerkmal
                    AND seat_ms.kSprache = :lang
                    AND seat_ms.cName = 'kompatible Sitze'
                   JOIN tmerkmalwertsprache seat_ws
                     ON seat_ws.kMerkmalWert = seat_am.kMerkmalWert
                    AND seat_ws.kSprache = :lang
                  WHERE TRIM(REPLACE(REPLACE(UPPER(series_ws.cWert), 'SERIE', ''), '-', ''))
                        = UPPER(:series)
                    AND TRIM(seat_ws.cWert) <> ''",
                ['lang' => $langId, 'series' => $series]
            );
            foreach ((array)$rows as $row) {
                $code = self::seatCodeFromValue((string)($row->cWert ?? ''));
                if ($code !== '') {
                    $codes[$code] = $code;
                }
            }
        } catch (\Throwable $e) {
        }
        $codes = \array_values($codes);
        self::cacheWrite($key, $codes);

        return $codes;
    }

    public static function seatCodeFromValue(string $value): string
    {
        $value = \trim($value);
        if ($value === '') {
            return '';
        }
        if (\preg_match('/^\d{1,2}$/', $value) === 1) {
            $code = (string)(int)$value;
            return isset(self::SEATS[$code]) ? $code : '';
        }
        $norm = self::normalizeSeatName($value);
        foreach (self::SEATS as $code => $meta) {
            if (self::normalizeSeatName($meta['name']) === $norm) {
                return (string)$code;
            }
        }
        if (\preg_match('/(?:^|[^\d])sitz\s*(\d{1,2})\b/i', $value, $match) === 1) {
            $code = (string)(int)$match[1];
            return isset(self::SEATS[$code]) ? $code : '';
        }

        return '';
    }

    private static function normalizeSeatName(string $value): string
    {
        $value = \mb_strtolower($value);
        $value = \strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return (string)\preg_replace('/[^a-z0-9]+/', '', $value);
    }

    public static function pageUrl(string $slug = self::PAGE_SLUG): string
    {
        return \rtrim((string)Shop::getURL(), '/') . '/' . self::normalizeSlug($slug);
    }

    public static function ensureAllShopLinks(): void
    {
        foreach (self::listPages() as $p) {
            self::ensureShopLink($p['cName'], $p['cSlug']);
        }
        self::cleanupOrphanShopLinks();
        self::flushShopCache();
    }

    public static function deleteShopLink(string $slug): void
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return;
        }
        try {
            $db  = Shop::Container()->getDB();
            $seo = $db->getSingleObject(
                "SELECT kKey FROM tseo WHERE cKey = 'kLink' AND cSeo = :seo LIMIT 1",
                ['seo' => $slug]
            );
            $kLink = $seo !== null ? (int)($seo->kKey ?? 0) : 0;
            if ($kLink <= 0) {
                $row = $db->getSingleObject(
                    "SELECT kLink FROM tlink
                      WHERE cIdentifier = :a OR cIdentifier = :b
                      LIMIT 1",
                    ['a' => 'womosi_lager_produkt', 'b' => 'womosi_lager_produkt_' . $slug]
                );
                $kLink = $row !== null ? (int)($row->kLink ?? 0) : 0;
            }
            if ($kLink > 0) {
                self::deleteLinkRow($db, $kLink);
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }

    public static function cleanupOrphanShopLinks(): void
    {
        $keep = [];
        foreach (self::listPages() as $p) {
            if ($p['cSlug'] !== '') {
                $keep[$p['cSlug']] = true;
            }
        }
        try {
            $db   = Shop::Container()->getDB();
            $pid  = self::pluginId();
            $sql = "SELECT t.kLink, t.cIdentifier, IFNULL(s.cSeo, '') AS cSeo
                      FROM tlink t
                      LEFT JOIN tpluginlinkdatei pld ON pld.kLink = t.kLink
                      LEFT JOIN tseo s ON s.cKey = 'kLink' AND s.kKey = t.kLink
                     WHERE pld.cDatei = 'lager_produkt.php'
                        OR t.cIdentifier LIKE 'womosi_lager_produkt%'";
            $params = [];
            if ($pid > 0) {
                $sql .= ' OR t.kPlugin = :pid';
                $params['pid'] = $pid;
            }
            $rows = $db->getObjects($sql, $params);
            if (!\is_array($rows)) {
                return;
            }
            $seen = [];
            foreach ($rows as $r) {
                $kLink = (int)($r->kLink ?? 0);
                if ($kLink <= 0 || isset($seen[$kLink])) {
                    continue;
                }
                $seen[$kLink] = true;
                $seo = self::normalizeSlug((string)($r->cSeo ?? ''));
                if ($seo !== '' && isset($keep[$seo])) {
                    continue;
                }
                self::deleteLinkRow($db, $kLink);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function deleteLinkRow(object $db, int $kLink): void
    {
        if ($kLink <= 0) {
            return;
        }
        try {
            $db->executeQueryPrepared('UPDATE tlink SET bIsActive = 0 WHERE kLink = :k', ['k' => $kLink]);
            $db->delete('tpluginlinkdatei', 'kLink', $kLink);
            $db->executeQueryPrepared("DELETE FROM tseo WHERE cKey = 'kLink' AND kKey = :k", ['k' => $kLink]);
            $db->delete('tlinksprache', 'kLink', $kLink);
            try {
                $db->delete('tlinkgroupassociations', 'kLink', $kLink);
            } catch (\Throwable $e) {
            }
            $db->delete('tlink', 'kLink', $kLink);
        } catch (\Throwable $e) {
        }
    }

    public static function ensureShopLink(string $title, string $slug): int
    {
        $title = \trim($title);
        $slug  = self::normalizeSlug($slug);
        if ($title === '' || $slug === '') {
            return 0;
        }
        $pluginID = self::pluginId();
        if ($pluginID <= 0) {
            self::$lastError = 'Plugin-ID nicht gefunden.';
            return 0;
        }
        try {
            $db    = Shop::Container()->getDB();
            $seo   = $db->getSingleObject(
                "SELECT kKey FROM tseo WHERE cKey = 'kLink' AND cSeo = :seo ORDER BY kSprache ASC LIMIT 1",
                ['seo' => $slug]
            );
            $kLink = $seo !== null ? (int)($seo->kKey ?? 0) : 0;

            $hiddenLg = $db->select('tlinkgruppe', 'cTemplatename', 'hidden');
            $lgID     = (int)($hiddenLg->kLinkgruppe ?? 1);
            if ($lgID <= 0) {
                $lgID = 1;
            }

            if ($kLink <= 0) {
                $linkAdmin = new \JTL\Link\Admin\LinkAdmin($db, Shop::Container()->getCache());
                $data      = [
                    'kLink'              => 0,
                    'kPlugin'            => $pluginID,
                    'cName'              => $title,
                    'nLinkart'           => \LINKTYP_PLUGIN,
                    'nSort'              => 0,
                    'bSSL'               => 0,
                    'bIsActive'          => 1,
                    'bIsFluid'           => 1,
                    'cSichtbarNachLogin' => 'N',
                    'cNoFollow'          => 'N',
                    'cIdentifier'        => 'womosi_lager_produkt_' . $slug,
                    'linkTarget'         => '_self',
                    'cKundengruppen'     => '_DBNULL_',
                    'kLinkgruppe'        => $lgID,
                ];
                foreach (\JTL\Language\LanguageHelper::getAllLanguages(0, true) as $language) {
                    $iso = $language->getIso();
                    $data['cName_' . $iso]    = $title;
                    $data['cTitle_' . $iso]   = $title;
                    $data['cSeo_' . $iso]     = $slug;
                    $data['cContent_' . $iso] = '';
                }
                $linkObj = $linkAdmin->createOrUpdateLink($data);
                $kLink   = (int)$linkObj->getID();
            }

            if ($kLink <= 0) {
                self::$lastError = 'Shop-Link für ' . $slug . ' konnte nicht erstellt werden.';
                return 0;
            }

            $pld = $db->getSingleObject(
                'SELECT kLink FROM tpluginlinkdatei WHERE kLink = :k LIMIT 1',
                ['k' => $kLink]
            );
            if ($pld === null) {
                $row                      = new \stdClass();
                $row->kPlugin             = $pluginID;
                $row->kLink               = $kLink;
                $row->cDatei              = 'lager_produkt.php';
                $row->cTemplate           = 'lager-produkt.tpl';
                $row->cFullscreenTemplate = '_DBNULL_';
                $db->insert('tpluginlinkdatei', $row);
            }

            $seo2 = $db->getSingleObject(
                "SELECT cSeo FROM tseo WHERE cKey = 'kLink' AND kKey = :k AND cSeo = :s LIMIT 1",
                ['k' => $kLink, 's' => $slug]
            );
            if ($seo2 === null) {
                $langs = $db->getObjects('SELECT kSprache FROM tsprache');
                if (!\is_array($langs) || $langs === []) {
                    $langs = [(object)['kSprache' => 1]];
                }
                foreach ($langs as $lr) {
                    $ins          = new \stdClass();
                    $ins->cSeo    = $slug;
                    $ins->cKey    = 'kLink';
                    $ins->kKey    = $kLink;
                    $ins->kSprache = (int)($lr->kSprache ?? 1);
                    try {
                        $db->insert('tseo', $ins);
                    } catch (\Throwable $e) {
                    }
                }
            }

            return $kLink;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            return 0;
        }
    }

    public static function flushShopCache(): void
    {
        self::$catalogMemo = [];
        try {
            $cache = Shop::Container()->getCache();
            if (\method_exists($cache, 'flushAll')) {
                $cache->flushAll();
            }
        } catch (\Throwable $e) {
        }
    }

    private static function pluginId(): int
    {
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                "SELECT kPlugin FROM tplugin
                  WHERE cPluginID = 'LagerProdukt' OR cName = 'LagerProdukt'
                  LIMIT 1"
            );
            return $row !== null ? (int)($row->kPlugin ?? 0) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array{kArtikel:int,cArtNr:string,cName:string}|null
     */
    public static function findArticle(string $artNr): ?array
    {
        $artNr = self::normalizeArtNr($artNr);
        if ($artNr === '') {
            return null;
        }
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                "SELECT a.kArtikel,
                        TRIM(a.cArtNr) AS cArtNr,
                        COALESCE(NULLIF(TRIM(a.cName), ''), TRIM(a.cArtNr)) AS cName
                   FROM tartikel a
                  WHERE TRIM(a.cArtNr) = :nr
                  LIMIT 1",
                ['nr' => $artNr]
            );
            if ($row === null) {
                return null;
            }

            return [
                'kArtikel' => (int)($row->kArtikel ?? 0),
                'cArtNr'   => (string)($row->cArtNr ?? $artNr),
                'cName'    => (string)($row->cName ?? $artNr),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function normalizeSlug(string $s): string
    {
        $s = \trim(\mb_strtolower($s, 'UTF-8'));
        $s = \strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        ]);
        $s = (string)\preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = (string)\preg_replace('/-+/', '-', $s);

        return \trim($s, '-');
    }

    private static function normalizeArtNr(string $s): string
    {
        return \trim($s);
    }

    private static function nextItemSort(int $pageId): int
    {
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT MAX(nSort) AS nMax FROM ' . self::TABLE_ITEM . ' WHERE kPage = :p',
                ['p' => $pageId]
            );
            return ((int)($row->nMax ?? 0)) + 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private static function productUrl(string $shopUrl, int $kArtikel, string $seo): string
    {
        if ($seo !== '') {
            return $shopUrl . '/' . \ltrim($seo, '/');
        }

        return $shopUrl . '/?a=' . $kArtikel;
    }

    private static function loadNettoPrice(int $kArtikel, float $fallback): float
    {
        try {
            $row = Shop::Container()->getDB()->getSingleObject(
                'SELECT pd.fVKNetto AS fVKNetto
                   FROM tpreis p
                   INNER JOIN tpreisdetail pd ON pd.kPreis = p.kPreis
                  WHERE p.kArtikel = :k
                    AND p.kKundengruppe = 1
                    AND (p.kKunde IS NULL OR p.kKunde = 0)
                    AND pd.nAnzahlAb = 0
                  LIMIT 1',
                ['k' => $kArtikel]
            );
            $net = $row !== null ? (float)($row->fVKNetto ?? 0.0) : 0.0;
            if ($net > 0) {
                return $net;
            }
        } catch (\Throwable $e) {
        }

        return $fallback;
    }

    /**
     * @return array{md:string,lg:string}
     */
    private static function productImages(
        string $shopUrl,
        int $kArtikel,
        string $name,
        string $seo,
        string $artNr,
        int $nNr
    ): array {
        $nNr = \max(1, $nNr);
        $obj                = new \stdClass();
        $obj->kArtikel      = $kArtikel;
        $obj->cName         = $name;
        $obj->cSeo          = $seo;
        $obj->originalSeo   = $seo;
        $obj->cArtNr        = $artNr;
        $obj->cBarcode      = '';
        $obj->customImgName = '';

        $md = '';
        $lg = '';
        try {
            if (\class_exists(\JTL\Media\Image\Product::class) && \class_exists(\JTL\Media\Image::class)) {
                $sizeMd = \defined(\JTL\Media\Image::class . '::SIZE_MD')
                    ? \JTL\Media\Image::SIZE_MD
                    : \JTL\Media\Image::SIZE_LG;
                $relMd = \JTL\Media\Image\Product::getThumb(
                    \JTL\Media\Image::TYPE_PRODUCT,
                    $kArtikel,
                    $obj,
                    $sizeMd,
                    $nNr
                );
                $relLg = \JTL\Media\Image\Product::getThumb(
                    \JTL\Media\Image::TYPE_PRODUCT,
                    $kArtikel,
                    $obj,
                    \JTL\Media\Image::SIZE_LG,
                    $nNr
                );
                if (\is_string($relMd) && \trim($relMd) !== '') {
                    $md = $shopUrl . '/' . \ltrim(\str_replace('\\', '/', $relMd), '/');
                }
                if (\is_string($relLg) && \trim($relLg) !== '') {
                    $lg = $shopUrl . '/' . \ltrim(\str_replace('\\', '/', $relLg), '/');
                }
            }
        } catch (\Throwable $e) {
        }

        if ($md === '') {
            $clean = \trim($name);
            try {
                if (\class_exists(\JTL\Media\Image\Product::class)) {
                    $fromJtl = \JTL\Media\Image\Product::getCustomName($obj);
                    if (\is_string($fromJtl) && \trim($fromJtl) !== '') {
                        $clean = \trim($fromJtl);
                    }
                }
            } catch (\Throwable $e) {
            }
            if ($clean === '') {
                $clean = (string)$kArtikel;
            }
            $clean  = \str_replace(['/', '\\', ' '], ['-', '-', '-'], $clean);
            $suffix = $nNr > 1 ? ('~' . $nNr) : '';
            $md     = $shopUrl . '/media/image/product/' . $kArtikel . '/md/' . $clean . $suffix . '.jpg';
            $lg     = $shopUrl . '/media/image/product/' . $kArtikel . '/lg/' . $clean . $suffix . '.jpg';
        }

        return [
            'md' => $md,
            'lg' => $lg !== '' ? $lg : $md,
        ];
    }

    private static function ensureItemGroupColumn(object $db): void
    {
        try {
            $cols = $db->getObjects('SHOW COLUMNS FROM ' . self::TABLE_ITEM . " LIKE 'kGroup'");
            if (\is_array($cols) && $cols !== []) {
                return;
            }
            $db->executeQuery(
                'ALTER TABLE ' . self::TABLE_ITEM . '
                    ADD COLUMN kGroup INT NOT NULL DEFAULT 0,
                    ADD KEY ix_group (kGroup)'
            );
        } catch (\Throwable $e) {
        }
    }

    private static function ensureItemMainColumn(object $db): void
    {
        try {
            $cols = $db->getObjects('SHOW COLUMNS FROM ' . self::TABLE_ITEM . " LIKE 'nMain'");
            if (\is_array($cols) && $cols !== []) {
                return;
            }
            $db->executeQuery(
                'ALTER TABLE ' . self::TABLE_ITEM . '
                    ADD COLUMN nMain TINYINT(1) NOT NULL DEFAULT 0 AFTER kGroup'
            );
        } catch (\Throwable $e) {
        }
    }

    private static function ensureItemArtNrIndex(object $db): void
    {
        try {
            $rows = $db->getObjects('SHOW INDEX FROM ' . self::TABLE_ITEM . " WHERE Key_name = 'ix_artnr'");
            if (\is_array($rows) && $rows !== []) {
                return;
            }
            $db->executeQuery(
                'ALTER TABLE ' . self::TABLE_ITEM . ' ADD KEY ix_artnr (cArtNr)'
            );
        } catch (\Throwable $e) {
        }
    }

    private static function normalizeGroupMain(int $pageId, int $groupId): void
    {
        if ($pageId <= 0 || $groupId <= 0) {
            return;
        }
        try {
            $db = Shop::Container()->getDB();
            $main = $db->getSingleObject(
                'SELECT kID FROM ' . self::TABLE_ITEM . '
                  WHERE kPage = :p AND kGroup = :g AND nMain = 1
               ORDER BY nSort ASC, kID ASC LIMIT 1',
                ['p' => $pageId, 'g' => $groupId]
            );
            if ($main !== null) {
                return;
            }
            $first = $db->getSingleObject(
                'SELECT kID FROM ' . self::TABLE_ITEM . '
                  WHERE kPage = :p AND kGroup = :g
               ORDER BY nSort ASC, kID ASC LIMIT 1',
                ['p' => $pageId, 'g' => $groupId]
            );
            if ($first !== null) {
                $db->executeQueryPrepared(
                    'UPDATE ' . self::TABLE_ITEM . ' SET nMain = 1 WHERE kID = :i',
                    ['i' => (int)$first->kID]
                );
            }
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
        }
    }
}
