<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

/**
 * Hängt StampedeProtectingCache an ProductFilter / FilterConfig.
 * Characteristic-Objekte müssen dafür noch nicht existieren.
 */
final class ProductFilterCacheBinder
{
    private const MAX_DEPTH = 4;

    public static function bind(object $productFilter): void
    {
        try {
            self::bindOn($productFilter, 0, []);
            self::trySetter($productFilter);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param array<int, true> $seen
     */
    private static function bindOn(object $obj, int $depth, array $seen): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        $oid = \spl_object_id($obj);
        if (isset($seen[$oid])) {
            return;
        }
        $seen[$oid] = true;

        $ref = new \ReflectionObject($obj);
        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            try {
                $val = $prop->getValue($obj);
            } catch (\Throwable $e) {
                continue;
            }
            if ($val instanceof StampedeProtectingCache) {
                continue;
            }
            if (\is_object($val) && self::looksLikeCache($val)) {
                try {
                    $prop->setValue($obj, new StampedeProtectingCache($val));
                } catch (\Throwable $e) {
                }
                continue;
            }
            if (\is_object($val) && self::isFilterOrConfig($val)) {
                self::bindOn($val, $depth + 1, $seen);
                self::trySetter($val);
            }
        }
    }

    private static function trySetter(object $obj): void
    {
        if (!\method_exists($obj, 'getCache') || !\method_exists($obj, 'setCache')) {
            return;
        }
        try {
            $cache = $obj->getCache();
            if (!\is_object($cache) || $cache instanceof StampedeProtectingCache) {
                return;
            }
            if (self::looksLikeCache($cache)) {
                $obj->setCache(new StampedeProtectingCache($cache));
            }
        } catch (\Throwable $e) {
        }
    }

    private static function looksLikeCache(object $obj): bool
    {
        if (!\method_exists($obj, 'get') || !\method_exists($obj, 'set')) {
            return false;
        }
        $class = \get_class($obj);

        return \str_contains($class, 'Cache')
            || \method_exists($obj, 'flush')
            || \method_exists($obj, 'getJtlCacheInstance');
    }

    private static function isFilterOrConfig(object $obj): bool
    {
        $ns = (new \ReflectionClass($obj))->getNamespaceName();

        return \str_starts_with($ns, 'JTL\\Filter')
            || \str_contains($ns, 'Cache');
    }
}
