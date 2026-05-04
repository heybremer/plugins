<?php
declare(strict_types=1);

use JTL\Shop;

if (!function_exists('bloggieGetLanguageId')) {
    function bloggieGetLanguageId(): int
    {
        try {
            if (class_exists(Shop::class) && method_exists(Shop::class, 'getLanguageID')) {
                $id = Shop::getLanguageID();
                if (is_int($id) && $id > 0) {
                    return $id;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        try {
            $db = Shop::Container()->getDB();
            $lang = $db->getSingleObject('SELECT kSprache FROM tsprache ORDER BY kSprache ASC LIMIT 1');
            if ($lang !== null) {
                return (int)($lang->kSprache ?? 1);
            }
        } catch (Throwable $e) {
            // ignore
        }
        return 1;
    }
}

if (!function_exists('bloggieArticleStockWhereClause')) {
    /**
     * JTL: wenn cLagerBeachten = Y, gilt Artikel als lieferbar wenn fLagerbestand > 0 oder cLagerKleinerNull = Y.
     */
    function bloggieArticleStockWhereClause(): string
    {
        return "(IFNULL(a.cLagerBeachten,'') <> 'Y' OR a.fLagerbestand > 0 OR IFNULL(a.cLagerKleinerNull,'') = 'Y')";
    }
}

if (!function_exists('bloggieBrandPoolLineForDay')) {
    /**
     * Rotiert die Marken-Zeile nach Kalendertag (yılın günü), damit nicht immer dieselbe Marke/Index.
     */
    function bloggieBrandPoolLineForDay(string $brandPool): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $brandPool) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn($v) => $v !== ''));
        if ($lines === []) {
            return '';
        }
        $dayKey = (int)date('z');

        return (string)$lines[$dayKey % count($lines)];
    }
}

if (!function_exists('bloggieSelectArticleRowForBlog')) {
    /**
     * Wählt eine Zeile: optional nach heutiger Marken-Zeile aus dem Pool (Name/Hersteller/Meta),
     * sonst stabil nach Datum aus dem gesamten (lagernden) Pool.
     *
     * @param array<int, object> $rows
     */
    function bloggieSelectArticleRowForBlog(array $rows, string $brandPool): ?object
    {
        if ($rows === []) {
            return null;
        }
        $needle = bloggieBrandPoolLineForDay($brandPool);
        $matches = static function (object $row, string $n): bool {
            if ($n === '') {
                return true;
            }
            $hay = mb_strtolower(trim(
                (string)($row->cName ?? '') . ' ' . (string)($row->cArtName ?? '') . ' '
                . (string)($row->cHerstellerName ?? '') . ' ' . (string)($row->cMetaTitle ?? '')
            ));

            return mb_stripos($hay, mb_strtolower($n)) !== false;
        };
        $filtered = $needle !== '' ? array_values(array_filter($rows, static fn($r) => $matches($r, $needle))) : [];
        if ($filtered === []) {
            $filtered = $rows;
        }
        $seed = crc32((string)date('Ymd') . '|' . $needle . '|' . (string)count($filtered));
        $idx = ($seed >= 0 ? $seed : -$seed) % count($filtered);

        return $filtered[$idx];
    }
}

if (!function_exists('bloggieFetchArticleRows')) {
    /**
     * Returns rows for article SEO/blog input. Tries several strategies for different DB/language setups.
     * Only articles that are considered in stock / sellable (Lager) are included.
     *
     * @return array<int, object>
     */
    function bloggieFetchArticleRows(): array
    {
        $db = Shop::Container()->getDB();
        $langId = bloggieGetLanguageId();
        $stock = bloggieArticleStockWhereClause();
        $joinH = 'LEFT JOIN thersteller h ON h.kHersteller = a.kHersteller';

        $baseSelect = 'SELECT a.kArtikel, a.cName AS cArtName, h.cName AS cHerstellerName, ts.cName, ts.cMetaTitle, ts.cMetaDescription, ts.cBeschreibung, ts.cKurzBeschreibung';

        // 1) Language-specific sprache (standard)
        try {
            $rows = $db->getObjects(
                $baseSelect . '
                   FROM tartikel a
                   ' . $joinH . '
                   LEFT JOIN tartikelsprache ts
                     ON ts.kArtikel = a.kArtikel
                    AND ts.kSprache = :kSprache
                  WHERE ' . $stock . '
                  ORDER BY a.kArtikel ASC
                  LIMIT 1000',
                ['kSprache' => $langId]
            );
            if (is_array($rows) && count($rows) > 0) {
                return $rows;
            }
        } catch (Throwable $e) {
            // continue
        }

        // 2) Any sprache row per article (Top10List-style), language-agnostic fallback
        try {
            $rows = $db->getObjects(
                'SELECT
                    a.kArtikel,
                    a.cName AS cArtName,
                    h.cName AS cHerstellerName,
                    ts.cName,
                    ts.cMetaTitle,
                    ts.cMetaDescription,
                    ts.cBeschreibung,
                    ts.cKurzBeschreibung
                   FROM tartikel a
                   ' . $joinH . '
                   LEFT JOIN (
                        SELECT s1.kArtikel,
                               MIN(s1.cName) AS cName,
                               MAX(s1.cMetaTitle) AS cMetaTitle,
                               MAX(s1.cMetaDescription) AS cMetaDescription,
                               MAX(s1.cBeschreibung) AS cBeschreibung,
                               MAX(s1.cKurzBeschreibung) AS cKurzBeschreibung
                          FROM tartikelsprache s1
                         GROUP BY s1.kArtikel
                   ) ts ON ts.kArtikel = a.kArtikel
                  WHERE ' . $stock . '
                  ORDER BY a.kArtikel ASC
                  LIMIT 1000'
            );
            if (is_array($rows) && count($rows) > 0) {
                return $rows;
            }
        } catch (Throwable $e) {
            // continue
        }

        // 3) tartikel only (name from main article row)
        try {
            $rows = $db->getObjects(
                'SELECT a.kArtikel, a.cName AS cArtName,
                        h.cName AS cHerstellerName,
                        NULL AS cName, NULL AS cMetaTitle, NULL AS cMetaDescription,
                        NULL AS cBeschreibung, NULL AS cKurzBeschreibung
                   FROM tartikel a
                   ' . $joinH . '
                  WHERE ' . $stock . '
                  ORDER BY a.kArtikel ASC
                  LIMIT 1000'
            );
            if (is_array($rows) && count($rows) > 0) {
                return $rows;
            }
        } catch (Throwable $e) {
            // continue
        }

        return [];
    }
}

if (!function_exists('bloggieCountArticles')) {
    function bloggieCountArticles(): int
    {
        try {
            $db = Shop::Container()->getDB();
            $row = $db->getSingleObject('SELECT COUNT(*) AS n FROM tartikel');
            return (int)($row->n ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('bloggieFetchOneArticleForTest')) {
    /**
     * Single row for self-test / diagnostics (one in-stock article, same selection logic as generation).
     */
    function bloggieFetchOneArticleForTest(): ?object
    {
        $rows = bloggieFetchArticleRows();
        if ($rows === []) {
            return null;
        }

        return bloggieSelectArticleRowForBlog($rows, '');
    }
}
