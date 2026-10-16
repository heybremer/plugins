<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

use JTL\Filter\Items\Characteristic;

/**
 * Ersetzt Characteristic-Instanzen im ProductFilter durch CachedCharacteristic.
 * Nur JTL\Filter\*-Objekte werden traversiert (kein Shop/DB-Graph).
 */
final class ProductFilterCharacteristicWrapper
{
    private const MAX_DEPTH = 6;

    public static function wrap(object $productFilter): void
    {
        if (!\class_exists(Characteristic::class)) {
            return;
        }
        try {
            self::walk($productFilter, 0, []);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param array<int, true> $seen
     */
    private static function walk(object $owner, int $depth, array $seen): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        $oid = \spl_object_id($owner);
        if (isset($seen[$oid])) {
            return;
        }
        $seen[$oid] = true;

        $ref = new \ReflectionObject($owner);
        foreach ($ref->getProperties() as $prop) {
            $prop->setAccessible(true);
            try {
                $val = $prop->getValue($owner);
            } catch (\Throwable $e) {
                continue;
            }
            $new = self::transform($val, $depth, $seen);
            if ($new !== $val) {
                try {
                    $prop->setValue($owner, $new);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    /**
     * @param array<int, true> $seen
     */
    private static function transform(mixed $val, int $depth, array $seen): mixed
    {
        if ($val instanceof CachedCharacteristic) {
            return $val;
        }
        if ($val instanceof Characteristic) {
            try {
                return CachedCharacteristic::from($val);
            } catch (\Throwable $e) {
                return $val;
            }
        }
        if (\is_array($val)) {
            foreach ($val as $k => $item) {
                $n = self::transform($item, $depth, $seen);
                if ($n !== $item) {
                    $val[$k] = $n;
                }
            }

            return $val;
        }
        if (\is_object($val) && self::isFilterGraph($val)) {
            self::walk($val, $depth + 1, $seen);
        }

        return $val;
    }

    private static function isFilterGraph(object $obj): bool
    {
        $ns = (new \ReflectionClass($obj))->getNamespaceName();

        return \str_starts_with($ns, 'JTL\\Filter');
    }
}
