<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch;

use JTL\DB\DbInterface;
use JTL\Shop;

/**
 * Built-in series labels/images + DB overrides (Dashboard) for frontend (FK_SERIES_CONFIG).
 */
final class SeriesMetaRuntime
{
    /**
     * @return array<string, array{label: string, color: string, img: string}>
     */
    public static function getBuiltInDefaults(): array
    {
        return [
            'ZM'    => ['label' => 'Serie ZM', 'color' => '#f5f5f5', 'img' => ''],
            '01'    => ['label' => 'Serie 01', 'color' => '#f5f5f5', 'img' => ''],
            'Serie 01' => ['label' => 'Serie 01', 'color' => '#f5f5f5', 'img' => ''],
            'FT'    => ['label' => 'Serie FT', 'color' => '#f5f5f5', 'img' => ''],
            'FNL'   => ['label' => 'Serie FNL', 'color' => '#f5f5f5', 'img' => ''],
            'V'     => ['label' => 'Serie V', 'color' => '#f5f5f5', 'img' => ''],
            'TK600' => ['label' => 'Serie TK600', 'color' => '#f5f5f5', 'img' => ''],
            'T'     => ['label' => 'Serie T', 'color' => '#f5f5f5', 'img' => ''],
            'TF'    => ['label' => 'Serie TF', 'color' => '#f5f5f5', 'img' => ''],
            'SL'    => ['label' => 'Serie SL', 'color' => '#f5f5f5', 'img' => ''],
            'PS'    => ['label' => 'Serie PS', 'color' => '#f5f5f5', 'img' => ''],
            'PL'    => ['label' => 'Serie PL', 'color' => '#f5f5f5', 'img' => ''],
            'NL'    => ['label' => 'Serie NL', 'color' => '#f5f5f5', 'img' => ''],
            'N3'    => ['label' => 'Serie N3', 'color' => '#f5f5f5', 'img' => ''],
            'MD'    => ['label' => 'Serie MD', 'color' => '#f5f5f5', 'img' => ''],
            'M'     => ['label' => 'Serie M', 'color' => '#f5f5f5', 'img' => ''],
            'KT300' => ['label' => 'Serie KT3', 'color' => '#f5f5f5', 'img' => ''],
            'KT3'   => ['label' => 'Serie KT3', 'color' => '#f5f5f5', 'img' => ''],
            'KT'    => ['label' => 'Serie KT', 'color' => '#f5f5f5', 'img' => ''],
            'K'     => ['label' => 'Serie K', 'color' => '#f5f5f5', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/seat/k-serie.webp'],
            'VK'    => ['label' => 'Serie VK', 'color' => '#f5f5f5', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/seat/vk-serie.webp'],
            'AH'    => ['label' => 'Serie AH', 'color' => '#f5f5f5', 'img' => ''],
            'GT'    => ['label' => 'Serie GT', 'color' => '#f5f5f5', 'img' => ''],
            'C'     => ['label' => 'Serie C', 'color' => '#f5f5f5', 'img' => ''],
            'D'     => ['label' => 'Serie D', 'color' => '#f5f5f5', 'img' => ''],
            'G'     => ['label' => 'Serie G', 'color' => '#f5f5f5', 'img' => ''],
            'DPL'   => ['label' => 'Serie DPL', 'color' => '#f5f5f5', 'img' => ''],
            'H'     => ['label' => 'Serie H', 'color' => '#f5f5f5', 'img' => ''],
            'PLKT'  => ['label' => 'Serie PLKT', 'color' => '#f5f5f5', 'img' => ''],
            'WPL'   => ['label' => 'Serie WPL', 'color' => '#f5f5f5', 'img' => ''],
            'WMS'   => ['label' => 'Serie WMS', 'color' => '#f5f5f5', 'img' => ''],
            'HW'    => ['label' => 'Serie HW', 'color' => '#f5f5f5', 'img' => ''],
            'BC'    => ['label' => 'Serie BC', 'color' => '#f5f5f5', 'img' => ''],
            'DS'    => ['label' => 'Serie DS', 'color' => '#f5f5f5', 'img' => ''],
            'LT'    => ['label' => 'Serie LT', 'color' => '#f5f5f5', 'img' => ''],
            '800'   => ['label' => 'Serie 800er', 'color' => '#f5f5f5', 'img' => ''],
            'WMA'   => ['label' => 'Aguti Sitze', 'color' => '#f5f5f5', 'img' => ''],
            'WDS'   => ['label' => 'Serie WDS', 'color' => '#f5f5f5', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/seat/wds-serie.webp'],
            'SMD'   => ['label' => 'Serie SMD', 'color' => '#f5f5f5', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/seat/smd-serie.webp'],
            'NP'    => ['label' => 'Serie NP', 'color' => '#f5f5f5', 'img' => 'https://bremer-sitzbezuege.de/mediafiles/seat/np-serie.webp'],
            'WMC'   => ['label' => 'Serie WMC', 'color' => '#f5f5f5', 'img' => ''],
            'KT100' => ['label' => 'Serie KT100', 'color' => '#f5f5f5', 'img' => ''],
            'P10X'  => ['label' => 'Serie P10X', 'color' => '#f5f5f5', 'img' => ''],
            'ISRI'  => ['label' => 'Isri Sitze', 'color' => '#f5f5f5', 'img' => ''],
        ];
    }

    /**
     * @return array{meta: array<string, array{label: string, color: string, img: string}>, hidden: list<string>}
     */
    public static function buildPayload(DbInterface $db): array
    {
        $meta = self::getBuiltInDefaults();
        $hidden = [];

        try {
            $rows = $db->getObjects('SELECT cSerieKey, cLabel, cColor, cImg, nHide FROM twomosi_fk_serie_meta');
        } catch (\Throwable $e) {
            return ['meta' => $meta, 'hidden' => []];
        }
        if (!\is_array($rows)) {
            return ['meta' => $meta, 'hidden' => []];
        }

        foreach ($rows as $r) {
            $k = \trim((string)($r->cSerieKey ?? ''));
            if ($k === '') {
                continue;
            }
            // USER TALEBİ: '1' -> '01' olarak normalize edilmeli.
            if ($k === '1') {
                $k = '01';
            }
            if (!isset($meta[$k])) {
                $meta[$k] = [
                    'label' => 'Serie ' . $k,
                    'color' => '#f5f5f5',
                    'img'   => '',
                ];
            }
            $lbl = \trim((string)($r->cLabel ?? ''));
            $col = \trim((string)($r->cColor ?? ''));
            $img = \trim((string)($r->cImg ?? ''));
            if ($lbl !== '') {
                $meta[$k]['label'] = $lbl;
            }
            if ($col !== '') {
                $meta[$k]['color'] = $col;
            }
            if ($img !== '') {
                $meta[$k]['img'] = $img;
            }
            if ((int)($r->nHide ?? 0) === 1) {
                $hidden[] = $k;
            }
        }

        // If no explicit image is configured, use the matching category's image automatically.
        try {
            $langID = (int)Shop::getLanguageID();
        } catch (\Throwable $e) {
            $langID = 1;
        }
        foreach (\array_keys($meta) as $serieKey) {
            $serieKey = (string)$serieKey;
            $img = \trim((string)($meta[$serieKey]['img'] ?? ''));
            if ($img !== '') {
                continue;
            }
            $auto = self::tryResolveCategoryImageUrl($db, $langID, $serieKey);
            if ($auto !== '') {
                $meta[$serieKey]['img'] = $auto;
            }
        }

        $hidden = \array_values(\array_unique($hidden));

        return ['meta' => $meta, 'hidden' => $hidden];
    }

    private static function tryResolveCategoryImageUrl(DbInterface $db, int $langID, string $serieKey): string
    {
        $serieKey = \trim($serieKey);
        // Bazı durumlarda '01_1' gibi son ekli gelebilir, bunları temizliyoruz.
        $serieKey = (string)\preg_replace('/_\d+$/', '', $serieKey);
        // 'Serie ' ön ekini temizleyerek aramayı kolaylaştırıyoruz.
        $serieKey = (string)\preg_replace('/^Serie\s+/i', '', $serieKey);

        if ($serieKey === '') {
            return '';
        }

        // --- MANUEL ÖZEL DURUMLAR (USER TALEBİ) ---
        // '01' serisi için kategori 362 görseli kullanılacak.
        if ($serieKey === '01' || $serieKey === 'Serie 01') {
            return self::buildMediaUrlFromId($db, 362);
        }

        // Typical category naming in this shop: "AH-Serie"
        $name1 = $serieKey . '-Serie';
        $name2 = 'Serie ' . $serieKey;
        $name3 = $serieKey . ' Serie';
        $name4 = $serieKey;
        $name5 = str_replace('-', ' ', $serieKey) . ' Serie';
        $name6 = str_replace(' ', '-', $serieKey) . '-Serie';
        $candidates = array_values(array_unique([$name1, $name2, $name3, $name4, $name5, $name6]));

        $kKategorie = 0;
        foreach ($candidates as $nm) {
            try {
                // Use Nested Set model (lft/rght) for accurate hierarchical matching
                // Root IDs 19 (PKW) and 24 (WM) are used as anchors.
                $row = $db->getSingleObject(
                    "SELECT c.kKategorie
                       FROM tkategorie c
                 INNER JOIN tkategorie p
                         ON p.kKategorie IN (19, 24)
                        AND c.lft BETWEEN p.lft AND p.rght
                 INNER JOIN tkategoriepict cp
                         ON cp.kKategorie = c.kKategorie
                  LEFT JOIN tkategoriesprache cs
                         ON cs.kKategorie = c.kKategorie
                        AND cs.kSprache   = :lid
                      WHERE COALESCE(NULLIF(cs.cName, ''), NULLIF(c.cName, '')) = :nm
                      ORDER BY c.lft ASC
                      LIMIT 1",
                    ['lid' => $langID, 'nm' => $nm]
                );
                if ($row !== null && (int)($row->kKategorie ?? 0) > 0) {
                    $kKategorie = (int)$row->kKategorie;
                    break;
                }
            } catch (\Throwable $e) {
                // ignore and try next candidate
            }
        }
        if ($kKategorie <= 0) {
            return '';
        }

        $path = '';
        try {
            $pic = $db->getSingleObject(
                'SELECT cPfad FROM tkategoriepict WHERE kKategorie = :cid ORDER BY kKategoriePict ASC LIMIT 1',
                ['cid' => $kKategorie]
            );
            $path = \trim((string)($pic->cPfad ?? ''));
        } catch (\Throwable $e) {
            $path = '';
        }
        if ($path === '') {
            return '';
        }

        $ext = \strtolower((string)\pathinfo($path, \PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }

        $base = '';
        try {
            $base = \rtrim((string)Shop::getURL(), '/');
        } catch (\Throwable $e) {
            $base = '';
        }

        return self::buildMediaUrlFromId($db, $kKategorie);
    }

    /**
     * Kategori ID'sinden md formatında görsel URL'i oluşturur.
     */
    private static function buildMediaUrlFromId(DbInterface $db, int $kKategorie): string
    {
        if ($kKategorie <= 0) {
            return '';
        }

        $path = '';
        try {
            $pic = $db->getSingleObject(
                'SELECT cPfad FROM tkategoriepict WHERE kKategorie = :cid ORDER BY kKategoriePict ASC LIMIT 1',
                ['cid' => $kKategorie]
            );
            $path = \trim((string)($pic->cPfad ?? ''));
        } catch (\Throwable $e) {
            $path = '';
        }

        if ($path === '') {
            return '';
        }

        $ext = \strtolower((string)\pathinfo($path, \PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }

        $base = '';
        try {
            $base = \rtrim((string)Shop::getURL(), '/');
        } catch (\Throwable $e) {
            $base = '';
        }

        // JTL 5 Media Controller formatı
        return $base . '/media/image/category/' . $kKategorie . '/md/image.' . $ext;
    }
}