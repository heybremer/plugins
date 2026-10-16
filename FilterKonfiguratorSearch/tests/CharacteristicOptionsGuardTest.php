<?php

declare(strict_types=1);

namespace Plugin\FilterKonfiguratorSearch\Tests;

use PHPUnit\Framework\TestCase;
use Plugin\FilterKonfiguratorSearch\Filter\CharacteristicOptionsGuard;

class CharacteristicOptionsGuardTest extends TestCase
{
    protected function tearDown(): void
    {
        CharacteristicOptionsGuard::resetMemo();
        parent::tearDown();
    }

    public function test_fallback_key_is_stable_for_same_inputs(): void
    {
        $a = CharacteristicOptionsGuard::buildFallbackKey(1, 2, 19, 'mw-4', 'cat=19', '');
        $b = CharacteristicOptionsGuard::buildFallbackKey(1, 2, 19, 'mw-4', 'cat=19', '');
        $this->assertSame($a, $b);
        $this->assertStringStartsWith('fk_chopt_', $a);
    }

    public function test_fallback_key_changes_with_customer_group(): void
    {
        $a = CharacteristicOptionsGuard::buildFallbackKey(1, 1, 19, 'mw-4', '', '');
        $b = CharacteristicOptionsGuard::buildFallbackKey(1, 2, 19, 'mw-4', '', '');
        $this->assertNotSame($a, $b);
    }

    public function test_fallback_key_changes_with_active_filters(): void
    {
        $a = CharacteristicOptionsGuard::buildFallbackKey(1, 1, 19, 'mw-4', 'hersteller=1', '');
        $b = CharacteristicOptionsGuard::buildFallbackKey(1, 1, 19, 'mw-4', 'hersteller=2', '');
        $this->assertNotSame($a, $b);
    }

    public function test_lock_is_released_after_manual_release(): void
    {
        $key = 'fk_chl_test_' . \bin2hex(\random_bytes(4));
        CharacteristicOptionsGuard::acquireLock($key);
        CharacteristicOptionsGuard::releaseLock($key);
        $this->assertFalse(CharacteristicOptionsGuard::isLocked($key));
    }

    public function test_remember_by_key_reuses_request_memo(): void
    {
        $calls = 0;
        $first = CharacteristicOptionsGuard::rememberByKey('fk_test_memo', static function () use (&$calls): array {
            $calls++;

            return ['a' => 1];
        });
        $second = CharacteristicOptionsGuard::rememberByKey('fk_test_memo', static function () use (&$calls): array {
            $calls++;

            return ['a' => 2];
        });
        $this->assertSame(['a' => 1], $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }
}
