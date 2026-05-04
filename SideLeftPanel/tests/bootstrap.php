<?php

namespace JTL\Plugin {
    if (!interface_exists('PluginInterface')) {
        interface PluginInterface {
            public function getConfig();
        }
    }
    if (!class_exists('Bootstrapper')) {
        class Bootstrapper {
            protected $plugin;
            protected $dispatcher;
            public function __construct($plugin, $dispatcher) {
                $this->plugin = $plugin;
                $this->dispatcher = $dispatcher;
            }
            public function boot(\JTL\Events\Dispatcher $dispatcher): void {}
            public function getPlugin() {
                return $this->plugin;
            }
        }
    }
    class Config {
        public $options = [];
        public function getOption($key) {
            return $this->options[$key] ?? null;
        }
    }
}

namespace JTL\Events {
    if (!class_exists('Dispatcher')) {
        class Dispatcher {
            public function hookInto($hook, $callback): void {}
        }
    }
}

namespace JTL {
    class Shop {
        public static $pageType = 1;
        public static function getPageType(): int {
            return self::$pageType;
        }
    }
}

namespace {
    if (!defined('HOOK_LETZTERINCLUDE_CSS_JS')) {
        define('HOOK_LETZTERINCLUDE_CSS_JS', 1);
    }
    if (!defined('HOOK_SMARTY_OUTPUTFILTER')) {
        define('HOOK_SMARTY_OUTPUTFILTER', 2);
    }
    if (!defined('PAGE_STARTSEITE')) define('PAGE_STARTSEITE', 1);
    if (!defined('PAGE_ARTIKELLISTE')) define('PAGE_ARTIKELLISTE', 2);
    if (!defined('PAGE_ARTIKELLISTE_FILTER')) define('PAGE_ARTIKELLISTE_FILTER', 3);
    if (!defined('PAGE_LIVESUCHE')) define('PAGE_LIVESUCHE', 4);
    if (!defined('PAGE_HERSTELLER')) define('PAGE_HERSTELLER', 5);
    if (!defined('PAGE_EIGENE_SEITE')) define('PAGE_EIGENE_SEITE', 6);
    if (!defined('PAGE_WARENKORB')) define('PAGE_WARENKORB', 7);

    require_once __DIR__ . '/../vendor/autoload.php';
}
