<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

use JTL\Filter\Items\Characteristic;

/**
 * JTL-Characteristic mit stampede-sicherem getOptions().
 * Instanz wird aus dem Original geklont — Kernklasse bleibt unverändert.
 */
class CachedCharacteristic extends Characteristic
{
    public static function from(Characteristic $src): self
    {
        if ($src instanceof self) {
            return $src;
        }
        $dst  = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $seen = [];
        $cls  = new \ReflectionClass($src);
        while ($cls instanceof \ReflectionClass) {
            foreach ($cls->getProperties() as $prop) {
                $key = $prop->getDeclaringClass()->getName() . '::' . $prop->getName();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $prop->setAccessible(true);
                $prop->setValue($dst, $prop->getValue($src));
            }
            $parent = $cls->getParentClass();
            $cls    = $parent !== false ? $parent : null;
        }

        return $dst;
    }

    public function getOptions($mixed = null): array
    {
        return CharacteristicOptionsGuard::remember($this, $mixed, function () use ($mixed): array {
            return parent::getOptions($mixed);
        });
    }
}
