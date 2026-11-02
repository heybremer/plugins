<?php

declare(strict_types=1);

namespace Plugin\MarkePage;

use JTL\Catalog\Product\Artikel;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\Link\Admin\LinkAdmin;
use JTL\Shop;

final class MarkePageService
{
    public const TABLE    = 'twomosi_markepage';
    public const IDENT    = 'womosi_markepage';
    public const TEMPLATE = 'marke-page.tpl';
    public const DATEI    = 'marke_page.php';
    public const MAX_PRODUCTS = 200;

    private static bool $tablesEnsured = false;

    private DbInterface $db;

    private int $pluginID;

    public function __construct(DbInterface $db, int $pluginID = 0)
    {
        $this->db       = $db;
        $this->pluginID = $pluginID;
    }

    public static function resolvePluginID(DbInterface $db): int
    {
        try {
            $row = $db->getSingleObject(
                'SELECT kPlugin FROM tplugin WHERE cPluginID = :id LIMIT 1',
                ['id' => 'MarkePage']
            );

            return (int)($row->kPlugin ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function ensureTables(): void
    {
        if (self::$tablesEnsured) {
            return;
        }
        $this->db->executeQuery(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                kID               INT AUTO_INCREMENT PRIMARY KEY,
                cSlug             VARCHAR(120)  NOT NULL,
                cTitle            VARCHAR(255)  NOT NULL DEFAULT "",
                cMetaTitle        VARCHAR(255)  NOT NULL DEFAULT "",
                cMetaDescription  VARCHAR(500)  NOT NULL DEFAULT "",
                cIntro            TEXT,
                cArtNrs           MEDIUMTEXT,
                nActive           TINYINT(1)    NOT NULL DEFAULT 1,
                kLink             INT           NOT NULL DEFAULT 0,
                dCreated          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                dUpdated          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slug (cSlug),
                INDEX idx_link (kLink)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::$tablesEnsured = true;
    }

    public static function normalizeSlug(string $raw): string
    {
        $s = \trim($raw);
        $s = \trim($s, '/ ');
        $s = (string)\preg_replace('/\s+/u', '_', $s);
        $s = (string)\preg_replace('/[^A-Za-z0-9_\-]/', '', $s);

        return \trim($s, '-_');
    }

    /**
     * @return list<string>
     */
    public static function parseArtNrs(string $raw): array
    {
        $parts = \preg_split('/[\r\n,;\t]+/', $raw) ?: [];
        $out   = [];
        $seen  = [];
        foreach ($parts as $p) {
            $p = \trim((string)$p);
            if ($p === '' || \mb_strlen($p) > 100) {
                continue;
            }
            $key = \mb_strtolower($p);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[]      = $p;
            if (\count($out) >= self::MAX_PRODUCTS) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    public function all(): array
    {
        try {
            return $this->db->getObjects(
                'SELECT * FROM ' . self::TABLE . ' ORDER BY cSlug ASC'
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return $this->db->getSingleObject(
            'SELECT * FROM ' . self::TABLE . ' WHERE kID = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function findByLink(int $kLink): ?object
    {
        if ($kLink <= 0) {
            return null;
        }

        return $this->db->getSingleObject(
            'SELECT * FROM ' . self::TABLE . ' WHERE kLink = :k AND nActive = 1 LIMIT 1',
            ['k' => $kLink]
        );
    }

    /**
     * @param array<string, mixed> $in
     * @return array{ok: bool, error: string, warning: string, id: int}
     */
    public function save(array $in, int $id = 0): array
    {
        $fail = static fn(string $m): array => ['ok' => false, 'error' => $m, 'warning' => '', 'id' => $id];

        $slug  = self::normalizeSlug((string)($in['slug'] ?? ''));
        $title = \trim((string)($in['title'] ?? ''));
        if ($slug === '' || \strlen($slug) > 100) {
            return $fail('Bitte eine gültige URL angeben (Buchstaben, Zahlen, _ und -; max. 100 Zeichen).');
        }
        if ($title === '') {
            return $fail('Bitte einen Seitentitel angeben.');
        }
        if ($this->pluginID <= 0) {
            return $fail('Plugin-ID nicht gefunden.');
        }

        $existing = $id > 0 ? $this->find($id) : null;
        if ($id > 0 && $existing === null) {
            return $fail('Seite nicht gefunden.');
        }
        $ownLink = (int)($existing->kLink ?? 0);

        $dup = $this->db->getSingleObject(
            'SELECT kID FROM ' . self::TABLE . ' WHERE cSlug = :s AND kID <> :id LIMIT 1',
            ['s' => $slug, 'id' => $id]
        );
        if ($dup !== null) {
            return $fail('Diese URL wird bereits von einer anderen Seite genutzt.');
        }
        $seo = $this->db->getSingleObject(
            'SELECT cKey, kKey FROM tseo WHERE cSeo = :s LIMIT 1',
            ['s' => $slug]
        );
        if ($seo !== null && !((string)$seo->cKey === 'kLink' && $ownLink > 0 && (int)$seo->kKey === $ownLink)) {
            return $fail('Diese URL ist im Shop bereits vergeben (Kategorie, Artikel oder Seite).');
        }

        $artNrs = \implode("\n", self::parseArtNrs((string)($in['artNrs'] ?? '')));
        $row    = (object)[
            'cSlug'            => $slug,
            'cTitle'           => \mb_substr($title, 0, 255),
            'cMetaTitle'       => \mb_substr(\trim((string)($in['metaTitle'] ?? '')), 0, 255),
            'cMetaDescription' => \mb_substr(\trim((string)($in['metaDescription'] ?? '')), 0, 500),
            'cIntro'           => \trim((string)($in['intro'] ?? '')),
            'cArtNrs'          => $artNrs,
            'nActive'          => !empty($in['active']) ? 1 : 0,
        ];

        $kLink = 0;
        try {
            if ($id > 0) {
                $this->db->executeQueryPrepared(
                    'UPDATE ' . self::TABLE . '
                        SET cSlug = :cSlug, cTitle = :cTitle, cMetaTitle = :cMetaTitle,
                            cMetaDescription = :cMetaDescription, cIntro = :cIntro,
                            cArtNrs = :cArtNrs, nActive = :nActive, dUpdated = NOW()
                      WHERE kID = :id',
                    [
                        'cSlug'            => $row->cSlug,
                        'cTitle'           => $row->cTitle,
                        'cMetaTitle'       => $row->cMetaTitle,
                        'cMetaDescription' => $row->cMetaDescription,
                        'cIntro'           => $row->cIntro,
                        'cArtNrs'          => $row->cArtNrs,
                        'nActive'          => $row->nActive,
                        'id'               => $id,
                    ]
                );
            } else {
                $row->kLink    = 0;
                $row->dCreated = \date('Y-m-d H:i:s');
                $row->dUpdated = $row->dCreated;
                $id            = (int)$this->db->insert(self::TABLE, $row);
            }
            $saved = $this->find($id);
            if ($saved === null) {
                return $fail('Speichern fehlgeschlagen.');
            }
            $kLink = $this->syncLink($saved);
            $this->db->executeQueryPrepared(
                'UPDATE ' . self::TABLE . ' SET kLink = :k WHERE kID = :id',
                ['k' => $kLink, 'id' => $id]
            );
            $this->clearCache();
        } catch (\Throwable $e) {
            return $fail('Fehler beim Speichern: ' . $e->getMessage());
        }

        $warning = '';
        if ($kLink > 0) {
            $stored = $this->db->getSingleObject(
                "SELECT cSeo FROM tseo WHERE cKey = 'kLink' AND kKey = :k ORDER BY kSprache ASC LIMIT 1",
                ['k' => $kLink]
            );
            $storedSeo = (string)($stored->cSeo ?? '');
            if ($storedSeo !== '' && $storedSeo !== $slug) {
                $warning = 'Der Shop hat die URL zu "' . $storedSeo . '" geändert.';
            }
        }

        return ['ok' => true, 'error' => '', 'warning' => $warning, 'id' => $id];
    }

    public function delete(int $id): void
    {
        $row = $this->find($id);
        if ($row === null) {
            return;
        }
        $this->removeLink((int)$row->kLink);
        $this->db->executeQueryPrepared('DELETE FROM ' . self::TABLE . ' WHERE kID = :id', ['id' => $id]);
        $this->clearCache();
    }

    /**
     * Fehlende Shop-Links (z.B. nach Plugin-Update) neu erzeugen.
     */
    public function ensureLinks(): void
    {
        $rows = $this->db->getObjects(
            'SELECT * FROM ' . self::TABLE . ' WHERE nActive = 1'
        );
        if ($rows === []) {
            return;
        }
        $changed = false;
        foreach ($rows as $row) {
            $kLink = (int)$row->kLink;
            if ($kLink > 0) {
                $hit = $this->db->getSingleObject(
                    'SELECT l.kLink FROM tlink l
                       INNER JOIN tpluginlinkdatei d ON d.kLink = l.kLink
                      WHERE l.kLink = :k LIMIT 1',
                    ['k' => $kLink]
                );
                if ($hit !== null) {
                    continue;
                }
            }
            try {
                $new = $this->syncLink($row);
                if ($new > 0 && $new !== $kLink) {
                    $this->db->executeQueryPrepared(
                        'UPDATE ' . self::TABLE . ' SET kLink = :k WHERE kID = :id',
                        ['k' => $new, 'id' => (int)$row->kID]
                    );
                }
                $changed = true;
            } catch (\Throwable $e) {
                // nächste Seite versuchen
            }
        }
        if ($changed) {
            $this->clearCache();
        }
    }

    /**
     * Legt den JTL-Link (Plugin-Seite) an bzw. aktualisiert ihn und setzt die gewünschte SEO-URL.
     */
    public function syncLink(object $row): int
    {
        if ($this->pluginID <= 0) {
            throw new \RuntimeException('Plugin-ID fehlt');
        }
        $kLink = (int)($row->kLink ?? 0);
        if ($kLink > 0) {
            $exists = $this->db->getSingleObject('SELECT kLink FROM tlink WHERE kLink = :k LIMIT 1', ['k' => $kLink]);
            if ($exists === null) {
                $kLink = 0;
            }
        }

        $hiddenLg = $this->db->select('tlinkgruppe', 'cTemplatename', 'hidden');
        $lgID     = (int)($hiddenLg->kLinkgruppe ?? 1);
        if ($lgID <= 0) {
            $lgID = 1;
        }

        $title = (string)$row->cTitle;
        $data  = [
            'kLink'              => $kLink,
            'kPlugin'            => $this->pluginID,
            'cName'              => $title,
            'nLinkart'           => \LINKTYP_PLUGIN,
            'nSort'              => 0,
            'bSSL'               => 0,
            'bIsActive'          => (int)$row->nActive === 1 ? 1 : 0,
            'bIsFluid'           => 0,
            'cSichtbarNachLogin' => 'N',
            'cNoFollow'          => 'N',
            'cIdentifier'        => self::IDENT . '_' . (int)$row->kID,
            'linkTarget'         => '_self',
            'cKundengruppen'     => '_DBNULL_',
            'kLinkgruppe'        => $lgID,
        ];
        $metaTitle = (string)$row->cMetaTitle !== '' ? (string)$row->cMetaTitle : $title;
        foreach (LanguageHelper::getAllLanguages(0, true) as $language) {
            $iso = $language->getIso();
            $data['cName_' . $iso]            = $title;
            $data['cTitle_' . $iso]           = $title;
            $data['cSeo_' . $iso]             = (string)$row->cSlug;
            $data['cContent_' . $iso]         = '';
            $data['cMetaTitle_' . $iso]       = $metaTitle;
            $data['cMetaDescription_' . $iso] = (string)$row->cMetaDescription;
        }

        $linkAdmin = new LinkAdmin($this->db, Shop::Container()->getCache());
        $linkObj   = $linkAdmin->createOrUpdateLink($data);
        $kLink     = (int)$linkObj->getID();
        if ($kLink <= 0) {
            throw new \RuntimeException('Link konnte nicht angelegt werden');
        }

        $pld = $this->db->getSingleObject(
            'SELECT kLink FROM tpluginlinkdatei WHERE kLink = :k LIMIT 1',
            ['k' => $kLink]
        );
        if ($pld === null) {
            $ins                      = new \stdClass();
            $ins->kPlugin             = $this->pluginID;
            $ins->kLink               = $kLink;
            $ins->cDatei              = self::DATEI;
            $ins->cTemplate           = self::TEMPLATE;
            $ins->cFullscreenTemplate = '_DBNULL_';
            $this->db->insert('tpluginlinkdatei', $ins);
        }

        return $kLink;
    }

    private function removeLink(int $kLink): void
    {
        if ($kLink <= 0) {
            return;
        }
        try {
            $this->db->executeQueryPrepared('UPDATE tlink SET bIsActive = 0 WHERE kLink = :k', ['k' => $kLink]);
            $this->db->delete('tpluginlinkdatei', 'kLink', $kLink);
            $this->db->executeQueryPrepared(
                "DELETE FROM tseo WHERE cKey = 'kLink' AND kKey = :k",
                ['k' => $kLink]
            );
            $this->db->delete('tlinksprache', 'kLink', $kLink);
            try {
                $this->db->delete('tlinkgroupassociations', 'linkID', $kLink);
            } catch (\Throwable $e) {
                // ältere Shops
            }
            $this->db->delete('tlink', 'kLink', $kLink);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function clearCache(): void
    {
        try {
            (new LinkAdmin($this->db, Shop::Container()->getCache()))->clearCache();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * @param list<string> $artNrs
     * @return array<string, int> lower(cArtNr) => kArtikel
     */
    public function resolveIds(array $artNrs): array
    {
        if ($artNrs === []) {
            return [];
        }
        $params = [];
        $marks  = [];
        foreach (\array_values($artNrs) as $i => $nr) {
            $params['n' . $i] = $nr;
            $marks[]          = ':n' . $i;
        }
        $map = [];
        try {
            $rows = $this->db->getObjects(
                'SELECT kArtikel, cArtNr FROM tartikel WHERE cArtNr IN (' . \implode(',', $marks) . ')',
                $params
            );
            foreach ($rows as $r) {
                $map[\mb_strtolower((string)$r->cArtNr)] = (int)$r->kArtikel;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $map;
    }

    /**
     * @param list<string> $artNrs
     * @return array{products: list<Artikel>, missing: list<string>}
     */
    public function loadProducts(array $artNrs): array
    {
        $ids      = $this->resolveIds($artNrs);
        $products = [];
        $missing  = [];

        $opt                        = Artikel::getDefaultOptions();
        $opt->nKategorie            = 1;
        $opt->nVariationen          = 1;
        $opt->nWarenlager           = 1;
        $opt->nRatings              = (\defined('PRODUCT_LIST_SHOW_RATINGS') && \PRODUCT_LIST_SHOW_RATINGS === true) ? 1 : 0;
        $opt->nVariationDetailPreis = 1;

        foreach ($artNrs as $nr) {
            $id = $ids[\mb_strtolower($nr)] ?? 0;
            if ($id <= 0) {
                $missing[] = $nr;
                continue;
            }
            try {
                $a = (new Artikel())->fuelleArtikel($id, $opt);
            } catch (\Throwable $e) {
                $a = null;
            }
            if ($a === null || (int)$a->kArtikel <= 0) {
                $missing[] = $nr;
                continue;
            }
            $products[] = $a;
        }

        return ['products' => $products, 'missing' => $missing];
    }
}
