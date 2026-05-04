<?php

namespace Plugin\SideLeftPanel\Tests;

use PHPUnit\Framework\TestCase;
use Plugin\SideLeftPanel\Bootstrap;
use Mockery;
use JTL\Events\Dispatcher;
use JTL\Plugin\PluginInterface;

class BootstrapTest extends TestCase
{
    private Bootstrap $bootstrap;
    private $pluginMock;
    private $dispatcherMock;
    private $configMock;

    protected function setUp(): void
    {
        $this->configMock = new \JTL\Plugin\Config();
        $this->pluginMock = Mockery::mock(PluginInterface::class);
        $this->pluginMock->shouldReceive('getConfig')->andReturn($this->configMock);
        
        $this->dispatcherMock = Mockery::mock(Dispatcher::class);
        $this->dispatcherMock->shouldReceive('hookInto')->byDefault();
        
        $this->bootstrap = new Bootstrap($this->pluginMock, $this->dispatcherMock);
        \JTL\Shop::$pageType = \PAGE_STARTSEITE; // Default for other tests
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /** @test */
    public function boot_registers_css_js_hook(): void
    {
        $this->dispatcherMock
            ->shouldReceive('hookInto')
            ->once()
            ->with(\HOOK_LETZTERINCLUDE_CSS_JS, Mockery::type('callable'));

        $this->bootstrap->boot($this->dispatcherMock);
        
        // This is a simple verification that the hook is called.
        // In a more complex test, we would invoke the callback and verify $args.
        $this->assertTrue(true);
    }

    /** @test */
    public function hook_callback_adds_css_and_js_paths(): void
    {
        $callback = null;
        $this->dispatcherMock
            ->shouldReceive('hookInto')
            ->once()
            ->with(\HOOK_LETZTERINCLUDE_CSS_JS, Mockery::on(function($arg) use (&$callback) {
                $callback = $arg;
                return is_callable($arg);
            }));

        $this->bootstrap->boot($this->dispatcherMock);

        $args = [
            'cPluginCss_arr' => [],
            'cPluginJsHead_arr' => []
        ];

        $callback($args);

        $this->assertContains('plugins/SideLeftPanel/templates/css/sideleftpanel.css', $args['cPluginCss_arr']);
        $this->assertContains('plugins/SideLeftPanel/templates/js/sideleftpanel.js', $args['cPluginJsHead_arr']);
    }

    /** @test */
    public function hook_callback_does_not_duplicate_paths(): void
    {
        $callback = null;
        $this->dispatcherMock
            ->shouldReceive('hookInto')
            ->once()
            ->with(\HOOK_LETZTERINCLUDE_CSS_JS, Mockery::on(function($arg) use (&$callback) {
                $callback = $arg;
                return is_callable($arg);
            }));

        $this->bootstrap->boot($this->dispatcherMock);

        $cssPath = 'plugins/SideLeftPanel/templates/css/sideleftpanel.css';
        $jsPath = 'plugins/SideLeftPanel/templates/js/sideleftpanel.js';

        $args = [
            'cPluginCss_arr' => [$cssPath],
            'cPluginJsHead_arr' => [$jsPath]
        ];

        $callback($args);

        $cssCount = array_count_values($args['cPluginCss_arr'])[$cssPath];
        $jsCount = array_count_values($args['cPluginJsHead_arr'])[$jsPath];

        $this->assertEquals(1, $cssCount);
        $this->assertEquals(1, $jsCount);
    }
    /** @test */
    public function hook_callback_does_not_add_paths_on_unsupported_page(): void
    {
        \JTL\Shop::$pageType = \PAGE_WARENKORB;

        $callback = null;
        $this->dispatcherMock
            ->shouldReceive('hookInto')
            ->once()
            ->with(\HOOK_LETZTERINCLUDE_CSS_JS, Mockery::on(function($arg) use (&$callback) {
                $callback = $arg;
                return is_callable($arg);
            }));

        $this->bootstrap->boot($this->dispatcherMock);

        $args = [
            'cPluginCss_arr' => [],
            'cPluginJsHead_arr' => []
        ];

        $callback($args);

        $this->assertEmpty($args['cPluginCss_arr']);
        $this->assertEmpty($args['cPluginJsHead_arr']);
    }

    /** @test */
    public function hook_callback_adds_paths_on_supported_page(): void
    {
        \JTL\Shop::$pageType = \PAGE_ARTIKELLISTE;

        $callback = null;
        $this->dispatcherMock
            ->shouldReceive('hookInto')
            ->once()
            ->with(\HOOK_LETZTERINCLUDE_CSS_JS, Mockery::on(function($arg) use (&$callback) {
                $callback = $arg;
                return is_callable($arg);
            }));

        $this->bootstrap->boot($this->dispatcherMock);

        $args = [
            'cPluginCss_arr' => [],
            'cPluginJsHead_arr' => []
        ];

        $callback($args);

        $this->assertContains('plugins/SideLeftPanel/templates/css/sideleftpanel.css', $args['cPluginCss_arr']);
        $this->assertContains('plugins/SideLeftPanel/templates/js/sideleftpanel.js', $args['cPluginJsHead_arr']);
    }

    /** @test */
    public function boot_registers_output_filter_hook(): void
    {
        $this->dispatcherMock
            ->shouldReceive('hookInto')
            ->once()
            ->with(\HOOK_SMARTY_OUTPUTFILTER, Mockery::type('callable'));

        $this->bootstrap->boot($this->dispatcherMock);
        
        $this->assertTrue(true);
    }
}
