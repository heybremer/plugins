<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap for SideLeftFiltre plugin.
 *
 * Provides lightweight stubs for JTL-Shop framework classes so that the
 * plugin's own code can be loaded and tested without a full shop installation.
 */

// ── JTL\Shop stub ────────────────────────────────────────────────────────────
namespace JTL {
    if (!class_exists('Shop')) {
        class Shop
        {
            private static object $container;

            public static function setContainer(object $c): void
            {
                self::$container = $c;
            }

            public static function Container(): object
            {
                return self::$container;
            }
        }
    }
}

// ── JTL\Plugin stubs ─────────────────────────────────────────────────────────
namespace JTL\Plugin {
    if (!interface_exists('PluginInterface')) {
        interface PluginInterface
        {
            public function getID(): int;
        }
    }
    if (!class_exists('Bootstrapper')) {
        abstract class Bootstrapper
        {
            protected $plugin;
            protected $dispatcher;

            public function __construct($plugin, $dispatcher)
            {
                $this->plugin     = $plugin;
                $this->dispatcher = $dispatcher;
            }

            public function getPlugin(): mixed
            {
                return $this->plugin;
            }

            public function boot($dispatcher): void {}
        }
    }
}

// ── JTL\Events stub ──────────────────────────────────────────────────────────
namespace JTL\Events {
    if (!class_exists('Dispatcher')) {
        class Dispatcher
        {
            public function hookInto(int|string $hook, callable $cb): void {}
        }
    }
}

// ── JTL\DB stub ──────────────────────────────────────────────────────────────
namespace JTL\DB {
    if (!interface_exists('DbInterface')) {
        interface DbInterface
        {
            public function getSingleObject(string $query, array $params = []): ?object;
            public function executeQuery(string $query): mixed;
            public function executeQueryPrepared(string $query, array $params): mixed;
            public function insert(string $table, object $obj): int;
        }
    }
}

// ── JTL\Helpers\Form stub ────────────────────────────────────────────────────
namespace JTL\Helpers {
    if (!class_exists('Form')) {
        class Form
        {
            public static function validateToken(): bool { return true; }
            public static function getTokenInput(): string { return ''; }
        }
    }
}

// ── Global constants used by Bootstrap ───────────────────────────────────────
namespace {
    if (!defined('HOOK_LETZTERINCLUDE_CSS_JS')) {
        define('HOOK_LETZTERINCLUDE_CSS_JS', 1);
    }
    if (!defined('HOOK_SMARTY_OUTPUTFILTER')) {
        define('HOOK_SMARTY_OUTPUTFILTER', 2);
    }

    require_once __DIR__ . '/../vendor/autoload.php';
}
