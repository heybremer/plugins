<?php

namespace Plugin\FilterKonfiguratorSearch\Tests;

use PHPUnit\Framework\TestCase;
use Plugin\FilterKonfiguratorSearch\Bootstrap;
use Mockery;
use ReflectionClass;

class BootstrapTest extends TestCase
{
    private Bootstrap $bootstrap;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $pluginMock     = Mockery::mock(\JTL\Plugin\PluginInterface::class);
        $dispatcherMock = Mockery::mock(\JTL\Events\Dispatcher::class);

        $this->bootstrap  = new Bootstrap($pluginMock, $dispatcherMock);
        $this->reflection = new ReflectionClass(Bootstrap::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ----------------------------------------------------------------
    // fkHumanizeSeoSegment
    // ----------------------------------------------------------------

    /** @test */
    public function humanize_returns_empty_string_for_empty_input(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        $this->assertSame('', $m->invoke($this->bootstrap, '', 0));
    }

    /** @test */
    public function humanize_returns_empty_for_whitespace_only(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        $this->assertSame('', $m->invoke($this->bootstrap, '   ', 0));
    }

    /** @test */
    public function humanize_converts_dash_to_space_for_non_seat_index(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        // "Fiat-Ducato" at index 4 (Wohnmobil-Fahrgestell) => "Fiat Ducato"
        $this->assertSame('Fiat Ducato', $m->invoke($this->bootstrap, 'Fiat-Ducato', 4));
    }

    /** @test */
    public function humanize_collapses_multiple_spaces(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        $this->assertSame('Adria Twin', $m->invoke($this->bootstrap, 'Adria--Twin', 2));
    }

    /** @test */
    public function humanize_index_5_converts_underscore_to_slash_notation(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        $this->assertSame('5 / 6', $m->invoke($this->bootstrap, '5_6', 5));
    }

    /** @test */
    public function humanize_index_5_converts_dash_to_space(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        // dashes become spaces in index 5 BEFORE underscore replacement
        $this->assertSame('Test Seat', $m->invoke($this->bootstrap, 'Test-Seat', 5));
    }

    /** @test */
    public function humanize_plain_text_unchanged(): void
    {
        $m = $this->getPrivateMethod('fkHumanizeSeoSegment');
        $this->assertSame('Kastenwagen', $m->invoke($this->bootstrap, 'Kastenwagen', 1));
    }

    // ----------------------------------------------------------------
    // fkBuildSelectionItemsFromSeoPath
    // ----------------------------------------------------------------

    /** @test */
    public function build_returns_empty_for_uri_without_double_underscore(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        $_SERVER['REQUEST_URI'] = '/some/url/without/pattern';
        $this->assertSame([], $m->invoke($this->bootstrap));
    }

    /** @test */
    public function build_returns_empty_for_empty_uri(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        $_SERVER['REQUEST_URI'] = '';
        $this->assertSame([], $m->invoke($this->bootstrap));
    }

    /** @test */
    public function build_returns_empty_when_only_one_segment(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        $_SERVER['REQUEST_URI'] = '/DPL';
        $this->assertSame([], $m->invoke($this->bootstrap));
    }

    /** @test */
    public function build_parses_two_segment_path(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        $_SERVER['REQUEST_URI'] = '/DPL__Kastenwagen';
        $expected = [
            ['label' => 'Serie',      'value' => 'DPL'],
            ['label' => 'Fahrzeugtyp','value' => 'Kastenwagen'],
        ];
        $this->assertSame($expected, $m->invoke($this->bootstrap));
    }

    /** @test */
    public function build_parses_full_seven_segment_path_with_seat_conversion(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        $_SERVER['REQUEST_URI'] = '/DPL__Kastenwagen__Adria__Twin__Fiat__5_6__2022';
        $expected = [
            ['label' => 'Serie',                  'value' => 'DPL'],
            ['label' => 'Fahrzeugtyp',             'value' => 'Kastenwagen'],
            ['label' => 'Fahrzeug-Marke',          'value' => 'Adria'],
            ['label' => 'Fahrzeug-Modell',         'value' => 'Twin'],
            ['label' => 'Wohnmobil-Fahrgestell',   'value' => 'Fiat'],
            ['label' => 'kompatible Sitze',        'value' => '5 / 6'],
            ['label' => 'Baujahr',                 'value' => '2022'],
        ];
        $this->assertSame($expected, $m->invoke($this->bootstrap));
    }

    /** @test */
    public function build_ignores_extra_segments_beyond_label_count(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        // 8 segments, but only 7 labels defined — extra should be silently ignored
        $_SERVER['REQUEST_URI'] = '/DPL__Kastenwagen__Adria__Twin__Fiat__5_6__2022__ExtraJunk';
        $result = $m->invoke($this->bootstrap);
        $this->assertCount(7, $result);
    }

    /** @test */
    public function build_skips_empty_segment_values(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        // Leading slash and trailing slash plus empty parts after explode should be filtered
        $_SERVER['REQUEST_URI'] = '/__Kastenwagen';
        $result = $m->invoke($this->bootstrap);
        // First part is empty => filtered => only 'Kastenwagen' remains => count < 2 => []
        $this->assertSame([], $result);
    }

    /** @test */
    public function build_handles_uri_with_query_string(): void
    {
        $m = $this->getPrivateMethod('fkBuildSelectionItemsFromSeoPath');
        $_SERVER['REQUEST_URI'] = '/DPL__Kastenwagen?foo=bar';
        $result = $m->invoke($this->bootstrap);
        $this->assertCount(2, $result);
        $this->assertSame('DPL', $result[0]['value']);
        $this->assertSame('Kastenwagen', $result[1]['value']);
    }

    // ----------------------------------------------------------------
    // Helper
    // ----------------------------------------------------------------

    private function getPrivateMethod(string $name): \ReflectionMethod
    {
        return $this->reflection->getMethod($name);
    }
}
