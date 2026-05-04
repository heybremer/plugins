<?php

namespace JTL\Plugin {
    if (!interface_exists('PluginInterface')) {
        interface PluginInterface {}
    }
    if (!class_exists('Bootstrapper')) {
        class Bootstrapper {
            protected $plugin;
            protected $dispatcher;
            public function __construct($plugin, $dispatcher) {
                $this->plugin = $plugin;
                $this->dispatcher = $dispatcher;
            }
        }
    }
}

namespace JTL\Events {
    if (!class_exists('Dispatcher')) {
        class Dispatcher {}
    }
}

namespace JTL\DB {
    if (!interface_exists('DbInterface')) {
        interface DbInterface {
            public function getObjects(string $query, array $params = []);
        }
    }
}

namespace {
    require_once __DIR__ . '/../vendor/autoload.php';
}
