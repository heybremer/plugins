<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Filter;

/**
 * JTL-Cache-Decorator mit APCu-L1.
 * JTL-Object-Cache darf aus sein — Ergebnis liegt trotzdem in APCu.
 * Wartezeit 45s, weil ssMerkmal-Queries 10–40s dauern.
 */
final class StampedeProtectingCache
{
    /** @var array<string, string> */
    private array $heldLocks = [];

    public function __construct(private object $inner)
    {
    }

    public function getInner(): object
    {
        return $this->inner;
    }

    public function get($cacheID, ...$args)
    {
        $id      = (string)$cacheID;
        $apcuHit = CharacteristicOptionsGuard::apcuGet($id);
        if ($apcuHit !== null && $apcuHit !== false) {
            return $apcuHit;
        }

        $hit = $this->callInner('get', \array_merge([$cacheID], $args));
        if ($hit !== false && $hit !== null) {
            CharacteristicOptionsGuard::apcuSet($id, $hit);

            return $hit;
        }

        $lockKey = 'fk_sl_' . \md5($id);
        if (!CharacteristicOptionsGuard::acquireLock($lockKey)) {
            $waited = $this->waitForValue($cacheID, $args);
            if ($waited !== false && $waited !== null) {
                return $waited;
            }
        }

        $this->heldLocks[$id] = $lockKey;

        return $hit;
    }

    public function getCache($cacheID, ...$args)
    {
        return $this->get($cacheID, ...$args);
    }

    public function set($cacheID, $content, $tags = null, $expiration = null)
    {
        $id = (string)$cacheID;
        CharacteristicOptionsGuard::apcuSet($id, $content);
        try {
            return $this->callInner('set', \func_get_args());
        } finally {
            $this->releaseFor($id);
        }
    }

    public function setCache($cacheID, $content, $tags = null, $expiration = null)
    {
        return $this->set($cacheID, $content, $tags, $expiration);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->callInner($name, $arguments);
    }

    private function waitForValue($cacheID, array $args): mixed
    {
        $id       = (string)$cacheID;
        $lockKey  = 'fk_sl_' . \md5($id);
        $deadline = \microtime(true) + 45.0;
        while (\microtime(true) < $deadline) {
            $apcuHit = CharacteristicOptionsGuard::apcuGet($id);
            if ($apcuHit !== null && $apcuHit !== false) {
                return $apcuHit;
            }
            $hit = $this->callInner('get', \array_merge([$cacheID], $args));
            if ($hit !== false && $hit !== null) {
                CharacteristicOptionsGuard::apcuSet($id, $hit);

                return $hit;
            }
            if (!CharacteristicOptionsGuard::isLocked($lockKey)) {
                break;
            }
            \usleep(100000);
        }

        return false;
    }

    private function releaseFor(string $id): void
    {
        $lockKey = $this->heldLocks[$id] ?? ('fk_sl_' . \md5($id));
        unset($this->heldLocks[$id]);
        CharacteristicOptionsGuard::releaseLock($lockKey);
    }

    private function callInner(string $name, array $arguments): mixed
    {
        return $this->inner->{$name}(...$arguments);
    }
}
