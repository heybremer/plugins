<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

use JTL\Shop;

/**
 * Request-Memo + JTL-Cache + Stampede-Lock für Characteristic::getOptions().
 *
 * JTL-Kern-SQL (EXPLAIN-Referenz, nicht ausführen — nur Analyse):
 *
 *   SELECT ssMerkmal.kMerkmalWert, COUNT(DISTINCT a.kArtikel) AS nAnzahl
 *     FROM tartikel a
 *     JOIN tartikelmerkmal ssMerkmal ON ssMerkmal.kArtikel = a.kArtikel
 *     JOIN tmerkmalwert mw ON mw.kMerkmalWert = ssMerkmal.kMerkmalWert
 *    WHERE mw.kMerkmal = :kMerkmal
 *      AND a.kArtikel IN ( -- ProductFilter-Base-Query -- )
 *    GROUP BY ssMerkmal.kMerkmalWert
 *    ORDER BY nAnzahl DESC
 *
 * „Creating sort index“ kommt vom GROUP BY / COUNT(DISTINCT) auf großer
 * tartikelmerkmal. Bestehende Indizes zuerst prüfen:
 *   tartikelmerkmal (kArtikel, kMerkmalWert)
 *   tartikelmerkmal (kMerkmalWert, kArtikel)
 *   tmerkmalwert (kMerkmal, kMerkmalWert)
 * Keine blinde Zusatz-Migration.
 */
final class CharacteristicOptionsGuard
{
    private const LOCK_TTL = 60;
    private const WAIT_MAX_SEC = 45.0;
    private const WAIT_STEP_US = 50000;

    /** @var array<string, array<int|string, mixed>> */
    private static array $memo = [];

    /**
     * @param callable():array $compute
     * @return array<int|string, mixed>
     */
    public static function remember(object $filter, mixed $mixed, callable $compute): array
    {
        if (self::shouldSkip($filter)) {
            return [];
        }

        return self::rememberByKey(self::cacheKey($filter, $mixed), $compute);
    }

    /**
     * @param callable():array $compute
     * @return array<int|string, mixed>
     */
    public static function rememberByKey(string $key, callable $compute): array
    {
        if (\array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $apcuHit = self::apcuGet($key);
        if (\is_array($apcuHit)) {
            return self::$memo[$key] = $apcuHit;
        }

        $cache = self::jtlCache();
        if ($cache !== null) {
            try {
                $hit = $cache->get($key);
                if (\is_array($hit)) {
                    self::apcuSet($key, $hit);

                    return self::$memo[$key] = $hit;
                }
            } catch (\Throwable $e) {
            }
        }

        $lockKey = 'fk_chl_' . $key;
        $locked  = self::acquireLock($lockKey);
        try {
            if (!$locked) {
                $waited = self::waitForCache($cache, $key);
                if (\is_array($waited)) {
                    return self::$memo[$key] = $waited;
                }
            } elseif ($cache !== null) {
                try {
                    $hit = $cache->get($key);
                    if (\is_array($hit)) {
                        return self::$memo[$key] = $hit;
                    }
                } catch (\Throwable $e) {
                }
            }

            $opts = $compute();
            if (!\is_array($opts)) {
                $opts = [];
            }
            self::$memo[$key] = $opts;
            self::apcuSet($key, $opts);
            if ($cache !== null) {
                try {
                    $tags = [\defined('CACHING_GROUP_FILTER') ? \CACHING_GROUP_FILTER : 'filter'];
                    $cache->set($key, $opts, $tags);
                } catch (\Throwable $e) {
                }
            }

            return $opts;
        } finally {
            if ($locked) {
                self::releaseLock($lockKey);
            }
        }
    }

    public static function resetMemo(): void
    {
        self::$memo = [];
    }

    public static function buildFallbackKey(
        int $languageId,
        int $customerGroupId,
        int $categoryId,
        string $filterId,
        string $activeFingerprint,
        string $mixedSuffix
    ): string {
        return 'fk_chopt_' . \md5(\implode('|', [
            $languageId,
            $customerGroupId,
            $categoryId,
            $filterId,
            $activeFingerprint,
            $mixedSuffix,
        ]));
    }

    public static function acquireLock(string $lockKey): bool
    {
        if (!\function_exists('apcu_add')) {
            return true;
        }
        try {
            return (bool)@\apcu_add($lockKey, 1, self::LOCK_TTL);
        } catch (\Throwable $e) {
            return true;
        }
    }

    public static function releaseLock(string $lockKey): void
    {
        if (!\function_exists('apcu_delete')) {
            return;
        }
        try {
            @\apcu_delete($lockKey);
        } catch (\Throwable $e) {
        }
    }

    public static function isLocked(string $lockKey): bool
    {
        if (!\function_exists('apcu_exists')) {
            return false;
        }
        try {
            return (bool)@\apcu_exists($lockKey);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function shouldSkip(object $filter): bool
    {
        if (!\method_exists($filter, 'getVisibility')) {
            return false;
        }
        try {
            $vis = $filter->getVisibility();
            if (\is_object($vis) && \method_exists($vis, 'value')) {
                $vis = $vis->value;
            }

            return (int)$vis === 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function cacheKey(object $filter, mixed $mixed): string
    {
        if (\method_exists($filter, 'getCacheID')) {
            try {
                $id = \trim((string)$filter->getCacheID());
                if ($id !== '') {
                    return 'fk_chopt_' . \md5($id . '|' . self::mixedSuffix($mixed));
                }
            } catch (\Throwable $e) {
            }
        }

        return self::buildFallbackKey(
            self::languageId(),
            self::customerGroupId(),
            self::categoryId(),
            self::filterId($filter),
            self::activeFingerprint($filter),
            self::mixedSuffix($mixed)
        );
    }

    private static function mixedSuffix(mixed $mixed): string
    {
        if ($mixed === null) {
            return '';
        }
        if (\is_scalar($mixed)) {
            return (string)$mixed;
        }

        try {
            return (string)\json_encode($mixed);
        } catch (\Throwable $e) {
            return 'x';
        }
    }

    private static function languageId(): int
    {
        try {
            return (int)Shop::getLanguageID();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function customerGroupId(): int
    {
        try {
            $cg = Shop::getCustomerGroup();
            if (\is_object($cg) && \method_exists($cg, 'getID')) {
                return (int)$cg->getID();
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    private static function categoryId(): int
    {
        try {
            $pf = Shop::getProductFilter();
            if ($pf !== null && \method_exists($pf, 'hasCategory') && $pf->hasCategory() && \method_exists($pf, 'getCategory')) {
                $v = $pf->getCategory()->getValue();
                if (\is_array($v)) {
                    return (int)(\reset($v) ?: 0);
                }
                if (\is_numeric($v)) {
                    return (int)$v;
                }
            }
        } catch (\Throwable $e) {
        }
        try {
            return (int)Shop::getState()->categoryID;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function filterId(object $filter): string
    {
        foreach (['getID', 'getCharacteristicID', 'getValue'] as $m) {
            if (!\method_exists($filter, $m)) {
                continue;
            }
            try {
                $v = $filter->{$m}();
                if (\is_scalar($v) && (string)$v !== '') {
                    return (string)$v;
                }
            } catch (\Throwable $e) {
            }
        }

        return \get_class($filter);
    }

    private static function activeFingerprint(object $current): string
    {
        $parts = [];
        try {
            $pf = Shop::getProductFilter();
            if ($pf === null || !\method_exists($pf, 'getActiveFilters')) {
                return '';
            }
            foreach ($pf->getActiveFilters() as $f) {
                if (!\is_object($f) || $f === $current) {
                    continue;
                }
                $type = \method_exists($f, 'getClassName') ? (string)$f->getClassName() : \get_class($f);
                $val  = \method_exists($f, 'getValue') ? $f->getValue() : '';
                if (\is_array($val)) {
                    $val = \implode(',', \array_map('strval', $val));
                } elseif (!\is_scalar($val) && $val !== null) {
                    $val = \json_encode($val);
                }
                $parts[] = $type . '=' . (string)$val;
            }
        } catch (\Throwable $e) {
            return '';
        }
        \sort($parts);

        return \implode(';', $parts);
    }

    private static function jtlCache(): ?object
    {
        try {
            $cache = Shop::Container()->getCache();

            return \is_object($cache) ? $cache : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function apcuGet(string $key): mixed
    {
        if (!\function_exists('apcu_fetch')) {
            return null;
        }
        try {
            $ok  = false;
            $val = @\apcu_fetch('fk_cg_' . \md5($key), $ok);

            return $ok ? $val : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function apcuSet(string $key, mixed $value): void
    {
        if (!\function_exists('apcu_store')) {
            return;
        }
        try {
            @\apcu_store('fk_cg_' . \md5($key), $value, 3600);
        } catch (\Throwable $e) {
        }
    }

    private static function waitForCache(?object $cache, string $key): ?array
    {
        $deadline = \microtime(true) + self::WAIT_MAX_SEC;
        $lockKey  = 'fk_chl_' . $key;
        while (\microtime(true) < $deadline) {
            $apcuHit = self::apcuGet($key);
            if (\is_array($apcuHit)) {
                return $apcuHit;
            }
            if ($cache !== null) {
                try {
                    $hit = $cache->get($key);
                    if (\is_array($hit)) {
                        self::apcuSet($key, $hit);

                        return $hit;
                    }
                } catch (\Throwable $e) {
                }
            }
            if (!self::isLocked($lockKey)) {
                break;
            }
            \usleep(self::WAIT_STEP_US);
        }

        return null;
    }
}
