<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

use JTL\Shop;

/**
 * Ersetzt den Shop-Cache-Singleton durch StampedeProtectingCache.
 * Characteristic::getOptions() nutzt oft Shop::Container()->getCache(), nicht ein PF-Feld.
 */
final class ShopCacheBinder
{
    private static bool $done = false;

    public static function install(): void
    {
        if (self::$done) {
            return;
        }
        try {
            $container = Shop::Container();
            if (!\is_object($container) || !\method_exists($container, 'getCache')) {
                return;
            }
            $cache = $container->getCache();
            if (!\is_object($cache) || $cache instanceof StampedeProtectingCache) {
                self::$done = true;

                return;
            }
            $wrapped = new StampedeProtectingCache($cache);
            self::replaceIn($container, $cache, $wrapped);
            self::$done = true;
        } catch (\Throwable $e) {
        }
    }

    private static function replaceIn(object $container, object $original, StampedeProtectingCache $wrapped): void
    {
        foreach (['set', 'offsetSet'] as $method) {
            if (!\method_exists($container, $method)) {
                continue;
            }
            foreach (['cache', 'jtlCache', 'JTLCache'] as $id) {
                try {
                    $container->{$method}($id, $wrapped);
                } catch (\Throwable $e) {
                }
            }
            if (\interface_exists('JTL\\Cache\\JTLCacheInterface')) {
                try {
                    $container->{$method}('JTL\\Cache\\JTLCacheInterface', $wrapped);
                } catch (\Throwable $e) {
                }
            }
        }

        $ref = new \ReflectionObject($container);
        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            try {
                $val = $prop->getValue($container);
            } catch (\Throwable $e) {
                continue;
            }
            if ($val === $original) {
                try {
                    $prop->setValue($container, $wrapped);
                } catch (\Throwable $e) {
                }
                continue;
            }
            if (!\is_array($val)) {
                continue;
            }
            $changed = false;
            foreach ($val as $k => $item) {
                if ($item === $original) {
                    $val[$k]   = $wrapped;
                    $changed = true;
                }
            }
            if ($changed) {
                try {
                    $prop->setValue($container, $val);
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
