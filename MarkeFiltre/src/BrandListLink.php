<?php

declare(strict_types=1);

namespace Plugin\MarkeFiltre;

use JTL\Catalog\Product\MerkmalWert;
use JTL\DB\DbInterface;
use JTL\Language\LanguageHelper;
use JTL\Router\Router;
use JTL\Shop;

/**
 * Link wie im JTL-Core: {@see MerkmalWert} (Merkmal „Fahrzeug-Marke“ + Wert-SEO), nicht handisch zusammengebaut.
 */
final class BrandListLink
{
    /**
     * Router erwartet dieselbe Locale wie {@see Router::$defaultLocale} — i. d. R. ISO-639-3 (getIso639).
     */
    private static function routerLocaleForLang(int $langID): string
    {
        try {
            $iso639 = LanguageHelper::getInstance()->getLanguageByID($langID)->getIso639();
            if (\is_string($iso639) && $iso639 !== '') {
                return $iso639;
            }
        } catch (\Throwable $e) {
        }

        return Shop::getLanguageCode();
    }

    /**
     * SEO-Segment für Listen-/Konfigurator-Pfade (tseo → tmerkmalwertsprache.cSeo).
     */
    public static function merkmalWertSeoSlug(DbInterface $db, int $langID, int $kMerkmalWert): string
    {
        if ($kMerkmalWert <= 0) {
            return '';
        }

        return self::resolveMerkmalWertSlug($db, $langID, $kMerkmalWert);
    }

    /** SEO-Slug: zuerst tseo, dann tmerkmalwertsprache.cSeo (Fallback wenn Core-URL leer). */
    private static function resolveMerkmalWertSlug(DbInterface $db, int $langID, int $kMerkmalWert): string
    {
        foreach (\array_unique([$langID, 1]) as $lid) {
            if ($lid <= 0) {
                continue;
            }
            $row = $db->getSingleObject(
                "SELECT cSeo
                   FROM tseo
                  WHERE cKey     = 'kMerkmalWert'
                    AND kKey     = :kid
                    AND kSprache = :lang
                  LIMIT 1",
                ['kid' => $kMerkmalWert, 'lang' => $lid]
            );
            if ($row !== null) {
                $s = \trim((string) ($row->cSeo ?? ''));
                if ($s !== '') {
                    return $s;
                }
            }

            $rowMw = $db->getSingleObject(
                'SELECT cSeo
                   FROM tmerkmalwertsprache
                  WHERE kMerkmalWert = :kid
                    AND kSprache     = :lang
                  LIMIT 1',
                ['kid' => $kMerkmalWert, 'lang' => $lid]
            );
            if ($rowMw !== null) {
                $s = \trim((string) ($rowMw->cSeo ?? ''));
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return '';
    }

    private static function characteristicsPathPrefix(): string
    {
        if (\defined('ROUTE_PREFIX_CHARACTERISTICS')) {
            return \trim((string) \ROUTE_PREFIX_CHARACTERISTICS, '/');
        }

        return 'characteristics';
    }

    private static function fallbackIdListUrl(string $shopURL, int $kMerkmalWert): string
    {
        $pfx = self::characteristicsPathPrefix();

        return \rtrim($shopURL, '/') . '/' . $pfx . '/id/' . $kMerkmalWert;
    }

    private static function fallbackRouterUrl(DbInterface $db, int $langID, int $kMerkmalWert, string $shopURL): string
    {
        $locale = self::routerLocaleForLang($langID);
        $slug   = self::resolveMerkmalWertSlug($db, $langID, $kMerkmalWert);

        try {
            $router = Shop::Container()->getRouter();
            if ($slug !== '') {
                $url = $router->getURLByType(
                    Router::TYPE_CHARACTERISTIC_VALUE,
                    [
                        'id'   => $kMerkmalWert,
                        'name' => $slug,
                        'lang' => $locale,
                    ],
                    true
                );
                if (\is_string($url) && $url !== '') {
                    return $url;
                }
            }

            $url = $router->getURLByType(
                Router::TYPE_CHARACTERISTIC_VALUE,
                [
                    'id'   => $kMerkmalWert,
                    'lang' => $locale,
                ],
                false
            );
            if (\is_string($url) && $url !== '') {
                return $url;
            }
        } catch (\Throwable $e) {
        }

        if ($slug !== '') {
            return $shopURL . '/' . $slug;
        }

        return self::fallbackIdListUrl($shopURL, $kMerkmalWert);
    }

    /**
     * Kanonische Listen-URL wie im Shop (Merkmal + Merkmalwert laut JTL).
     */
    public static function forMerkmalWert(DbInterface $db, int $langID, int $kMerkmalWert): string
    {
        $shopURL = \rtrim((string) Shop::getURL(), '/');
        if ($kMerkmalWert <= 0) {
            return $shopURL . '/';
        }

        try {
            $mw = new MerkmalWert($kMerkmalWert, $langID);
            if ($mw->getID() > 0) {
                $url = $mw->getURL($langID);
                if (\is_string($url) && $url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
        }

        return self::fallbackRouterUrl($db, $langID, $kMerkmalWert, $shopURL);
    }

    /**
     * Für DB-Cache: nie werfen, Länge begrenzen (VARCHAR(2048) nach Plugin-ALTER).
     */
    public static function forMerkmalWertStorable(DbInterface $db, int $langID, int $kMerkmalWert): string
    {
        try {
            $url = self::forMerkmalWert($db, $langID, $kMerkmalWert);
        } catch (\Throwable $e) {
            $base = \rtrim((string) Shop::getURL(), '/');
            $url  = self::fallbackIdListUrl($base, $kMerkmalWert);
        }
        if (\function_exists('mb_substr')) {
            return \mb_substr($url, 0, 2040, 'UTF-8');
        }

        return \substr($url, 0, 2040);
    }
}
