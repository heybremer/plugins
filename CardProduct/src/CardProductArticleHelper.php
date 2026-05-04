<?php

declare(strict_types=1);

namespace Plugin\CardProduct;

/**
 * Artikel görselleri (tartikelpict) ve „Produkttyp“ Merkmal değerleri.
 */
final class CardProductArticleHelper
{
    public static function buildJtlProductImageUrl(
        string $shopUrl,
        int $kArtikel,
        string $cleanName,
        int $nNr,
        string $size = 'lg',
        string $ext = 'jpg'
    ): string {
        $size = \in_array($size, ['xs', 'sm', 'md', 'lg', 'xl'], true) ? $size : 'lg';
        $ext  = \in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true) ? $ext : 'jpg';
        $suffix = $nNr > 1 ? ('~' . $nNr) : '';
        $cleanName = \trim($cleanName);
        if ($cleanName === '') {
            $cleanName = (string) $kArtikel;
        }

        return $shopUrl . '/media/image/product/' . $kArtikel . '/' . $size . '/' . $cleanName . $suffix . '.' . $ext;
    }

    /**
     * @param mixed $db \JTL\DB\DbInterface
     *
     * @return array<int, string>
     */
    public static function collectImageUrls($db, string $shopUrl, int $kArtikel, int $max = 24): array
    {
        if ($kArtikel <= 0) {
            return [];
        }
        $max = \max(1, \min(48, $max));

        $p = null;
        try {
            $p = $db->getSingleObject(
                "SELECT a.kArtikel,
                        COALESCE(NULLIF(a.cName, ''), tsAny.cName, '') AS cName,
                        COALESCE(a.cSeo, '') AS cSeo,
                        COALESCE(a.cArtNr, '') AS cArtNr,
                        COALESCE(a.cBarcode, '') AS cBarcode
                   FROM tartikel a
                   LEFT JOIN (
                        SELECT s1.kArtikel, MIN(s1.cName) AS cName
                          FROM tartikelsprache s1
                      GROUP BY s1.kArtikel
                   ) tsAny ON tsAny.kArtikel = a.kArtikel
                  WHERE a.kArtikel = :kArtikel
                  LIMIT 1",
                ['kArtikel' => $kArtikel]
            );
        } catch (\Throwable $e) {
            $p = null;
        }
        if ($p === null) {
            return [];
        }

        $cleanName = '';
        try {
            $tmp = new \stdClass();
            $tmp->kArtikel = (int) $p->kArtikel;
            $tmp->cName = (string) ($p->cName ?? '');
            $tmp->cSeo = (string) ($p->cSeo ?? '');
            $tmp->originalSeo = (string) ($p->cSeo ?? '');
            $tmp->cArtNr = (string) ($p->cArtNr ?? '');
            $tmp->cBarcode = (string) ($p->cBarcode ?? '');
            $tmp->customImgName = '';
            $cleanName = \JTL\Media\Image\Product::getCustomName($tmp);
        } catch (\Throwable $e) {
            $cleanName = (string) $kArtikel;
        }

        $imgs = [];
        try {
            $imgRows = $db->getObjects(
                'SELECT nNr
                   FROM tartikelpict
                  WHERE kArtikel = :kArtikel
               GROUP BY cPfad, nNr
               ORDER BY nNr ASC
                  LIMIT ' . $max,
                ['kArtikel' => $kArtikel]
            );
            if (\is_array($imgRows)) {
                foreach ($imgRows as $ir) {
                    $nr = (int) ($ir->nNr ?? 1);
                    $imgs[] = self::buildJtlProductImageUrl($shopUrl, $kArtikel, $cleanName, $nr, 'lg', 'jpg');
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return \array_values(\array_unique(\array_filter($imgs, static fn ($u) => $u !== '')));
    }

    /**
     * Artikel için „Produkttyp“ Merkmal değeri (cWert).
     *
     * @param mixed $db
     */
    public static function fetchProdukttypLabelForArticle($db, int $langId, int $kArtikel): string
    {
        if ($kArtikel <= 0) {
            return '';
        }
        $tryLangs = [$langId];
        if ($langId !== 1) {
            $tryLangs[] = 1;
        }
        foreach ($tryLangs as $lid) {
            $row = null;
            try {
                $row = $db->getSingleObject(
                    "SELECT TRIM(tmws.cWert) AS v
                       FROM tartikelmerkmal tam
                       INNER JOIN tmerkmalwert mw ON mw.kMerkmalWert = tam.kMerkmalWert
                       INNER JOIN tmerkmal m ON m.kMerkmal = mw.kMerkmal
                       INNER JOIN tmerkmalsprache ms
                               ON ms.kMerkmal = m.kMerkmal AND ms.kSprache = :lid
                       INNER JOIN tmerkmalwertsprache tmws
                               ON tmws.kMerkmalWert = tam.kMerkmalWert AND tmws.kSprache = :lid
                      WHERE tam.kArtikel = :ka
                        AND LOWER(ms.cName) LIKE '%produkttyp%'
                      LIMIT 1",
                    ['lid' => $lid, 'ka' => $kArtikel]
                );
            } catch (\Throwable $e) {
                $row = null;
            }
            if ($row !== null && \trim((string) ($row->v ?? '')) !== '') {
                return \trim((string) $row->v);
            }
        }

        return '';
    }

    /**
     * Admin’de tanımlı kategori ↔ ürün eşlemesine göre panel satırları (Kategorie + Produkttyp).
     *
     * @param mixed $db
     *
     * @return array<int, array{kKategorie: int, categoryName: string, kArtikel: int, produkttyp: string}>
     */
    public static function fetchPanelRowsFromCatArtikelMapping($db, int $langId): array
    {
        $mapRows = null;
        try {
            $mapRows = $db->getObjects(
                'SELECT kID, kKategorie, kArtikel, nSort
                   FROM twomosi_cardproduct_cat_artikel
               ORDER BY nSort ASC, kID ASC'
            );
        } catch (\Throwable $e) {
            $mapRows = null;
        }
        if (!\is_array($mapRows) || $mapRows === []) {
            return [];
        }

        $tryLangs = [$langId];
        if ($langId !== 1) {
            $tryLangs[] = 1;
        }

        $catNames = [];
        foreach ($tryLangs as $lid) {
            try {
                $cRows = $db->getObjects(
                    'SELECT kKategorie, cName
                       FROM tkategoriesprache
                      WHERE kSprache = :lid',
                    ['lid' => $lid]
                );
            } catch (\Throwable $e) {
                $cRows = null;
            }
            if (!\is_array($cRows)) {
                continue;
            }
            foreach ($cRows as $cr) {
                $kid = (int) ($cr->kKategorie ?? 0);
                $nm = \trim((string) ($cr->cName ?? ''));
                if ($kid > 0 && $nm !== '' && !isset($catNames[$kid])) {
                    $catNames[$kid] = $nm;
                }
            }
        }

        $out = [];
        foreach ($mapRows as $mr) {
            $kk = (int) ($mr->kKategorie ?? 0);
            $ka = (int) ($mr->kArtikel ?? 0);
            if ($kk <= 0 || $ka <= 0) {
                continue;
            }
            $catLabel = $catNames[$kk] ?? ('Kategorie #' . $kk);
            $pt = self::fetchProdukttypLabelForArticle($db, $langId, $ka);
            $out[] = [
                'kKategorie' => $kk,
                'categoryName' => $catLabel,
                'kArtikel' => $ka,
                'produkttyp' => $pt,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array{kKategorie: int, categoryName: string, kArtikel: int, produkttyp: string}> $rows
     */
    public static function buildProdukttypSummaryLine(array $rows, int $maxLen = 140): string
    {
        $parts = [];
        foreach ($rows as $r) {
            $c = \trim((string) ($r['categoryName'] ?? ''));
            $p = \trim((string) ($r['produkttyp'] ?? ''));
            if ($c === '') {
                continue;
            }
            $parts[] = $p !== '' ? ($c . ' · ' . $p) : $c;
        }
        $s = \implode(' · ', $parts);
        if ($s === '') {
            return '';
        }
        if (\strlen($s) > $maxLen) {
            return \substr($s, 0, $maxLen - 1) . '…';
        }

        return $s;
    }

    /**
     * Önce cImages / cImage; boşsa ve kArtikel varsa tartikelpict üzerinden URL üretir.
     *
     * @param mixed $db
     * @param object|\stdClass $row twomosi_cardproduct satırı
     *
     * @return array<int, string>
     */
    public static function resolveImagesForCacheRow($db, string $shopUrl, $row): array
    {
        $imgRaw = (string) ($row->cImage ?? '');
        $imgJson = \trim((string) ($row->cImages ?? ''));
        $kArtikel = (int) ($row->kArtikel ?? 0);
        $imgList = [];
        if ($imgJson !== '') {
            $decoded = \json_decode($imgJson, true);
            if (\is_array($decoded)) {
                foreach ($decoded as $entry) {
                    $u = \trim((string) $entry);
                    if ($u !== '') {
                        $imgList[] = $u;
                    }
                }
            }
        }
        if (\count($imgList) === 0) {
            $imgRaw = \str_replace(["\r\n", "\r"], "\n", $imgRaw);
            $parts = \preg_split('/[\n,;]+/', $imgRaw) ?: [];
            foreach ($parts as $part) {
                $u = \trim((string) $part);
                if ($u !== '') {
                    $imgList[] = $u;
                }
            }
        }
        if (\count($imgList) === 0 && $kArtikel > 0) {
            $imgList = self::collectImageUrls($db, $shopUrl, $kArtikel, 24);
        }

        return \array_values(\array_unique($imgList));
    }
}
