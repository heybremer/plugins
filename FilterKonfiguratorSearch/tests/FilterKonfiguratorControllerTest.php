<?php

namespace Plugin\FilterKonfiguratorSearch\Tests;

use PHPUnit\Framework\TestCase;
use Plugin\FilterKonfiguratorSearch\Repository\FilterKonfiguratorRepository;
use Plugin\FilterKonfiguratorSearch\Query\PkwSqlFragments;
use JTL\DB\DbInterface;
use Mockery;
use ReflectionClass;
use stdClass;

class FilterKonfiguratorControllerTest extends TestCase
{
    private FilterKonfiguratorRepository $repo;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $db = Mockery::mock(DbInterface::class);
        // Default: no calls expected unless specified per test
        $this->repo       = new FilterKonfiguratorRepository($db, 1);
        $this->reflection = new ReflectionClass(FilterKonfiguratorRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ----------------------------------------------------------------
    // Helper to build a fresh repo with a specific DB mock
    // ----------------------------------------------------------------
    private function repoWithDb(DbInterface $db): FilterKonfiguratorRepository
    {
        return new FilterKonfiguratorRepository($db, 1);
    }

    // ----------------------------------------------------------------
    // tryLoadOptionsFromSnapshot
    // ----------------------------------------------------------------

    /** @test */
    public function snapshot_returns_null_for_empty_field(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $m  = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');

        $this->assertNull($m->invoke($this->repoWithDb($db), '', '', '', '', ''));
    }

    /** @test */
    public function snapshot_returns_null_for_unknown_field(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $m  = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');

        // 'sitz' is not handled → null
        $this->assertNull($m->invoke($this->repoWithDb($db), 'sitz', '', '', '', ''));
    }

    /** @test */
    public function snapshot_returns_null_on_db_exception(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $db->shouldReceive('getObjects')->once()->andThrow(new \Exception('DB error'));
        $m = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');

        $this->assertNull($m->invoke($this->repoWithDb($db), 'hersteller', '', 'Kastenwagen', '', ''));
    }

    /** @test */
    public function snapshot_returns_null_when_db_returns_empty_array(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $db->shouldReceive('getObjects')->once()->andReturn([]);
        $m = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');

        $this->assertNull($m->invoke($this->repoWithDb($db), 'hersteller', '', 'Kastenwagen', '', ''));
    }

    /** @test */
    public function snapshot_returns_formatted_hersteller_options(): void
    {
        $db  = Mockery::mock(DbInterface::class);
        $row = static function (string $val): stdClass {
            $o      = new stdClass();
            $o->val = $val;
            return $o;
        };

        $db->shouldReceive('getObjects')->once()->andReturn([$row('Adria'), $row('Hymer'), $row('Knaus')]);

        $m      = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');
        $result = $m->invoke($this->repoWithDb($db), 'hersteller', '', 'Kastenwagen', '', '');

        $this->assertSame([
            ['value' => 'Adria'],
            ['value' => 'Hymer'],
            ['value' => 'Knaus'],
        ], $result);
    }

    /** @test */
    public function snapshot_filters_out_empty_val_rows(): void
    {
        $db  = Mockery::mock(DbInterface::class);
        $row = static function (string $val): stdClass {
            $o      = new stdClass();
            $o->val = $val;
            return $o;
        };

        $db->shouldReceive('getObjects')->once()
            ->andReturn([$row('Adria'), $row(''), $row('  '), $row('Hymer')]);

        $m      = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');
        $result = $m->invoke($this->repoWithDb($db), 'hersteller', '', '', '', '');

        $this->assertSame([
            ['value' => 'Adria'],
            ['value' => 'Hymer'],
        ], $result);
    }

    /** @test */
    public function snapshot_basis_requires_typ_and_hersteller(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $db->shouldNotReceive('getObjects');
        $m = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');

        // Missing hersteller (herstellerFilter='') → null without DB call
        $result = $m->invoke($this->repoWithDb($db), 'basis', '', 'Kastenwagen', '', '');
        $this->assertNull($result);
    }

    /** @test */
    public function snapshot_modell_requires_all_three_filters(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $db->shouldNotReceive('getObjects');
        $m = $this->reflection->getMethod('tryLoadOptionsFromSnapshot');

        // Missing basisFilter → null without DB call
        $result = $m->invoke($this->repoWithDb($db), 'modell', '', 'Kastenwagen', 'Adria', '');
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // PkwSqlFragments (static helpers — no DB needed)
    // ----------------------------------------------------------------

    /** @test */
    public function sql_pkw_fragment_contains_required_keywords(): void
    {
        $sql = PkwSqlFragments::articleNotAufbauart();

        $this->assertStringContainsString('NOT EXISTS', $sql);
        $this->assertStringContainsString('Wohnmobil-Aufbauart', $sql);
        $this->assertStringContainsString('tartikelmerkmal', $sql);
    }

    /** @test */
    public function sql_baujahr_fragment_covers_all_name_variants(): void
    {
        $sql = PkwSqlFragments::merkmalNameBaujahr('ms_bj');

        $this->assertStringContainsString("'Baujahr'", $sql);
        $this->assertStringContainsString("'Bau-Jahr'", $sql);
        $this->assertStringContainsString("'Bau Jahr'", $sql);
        $this->assertStringContainsString('REPLACE', $sql);
    }

    /** @test */
    public function sql_baujahr_fragment_uses_custom_alias(): void
    {
        $sql = PkwSqlFragments::merkmalNameBaujahr('ms_custom');

        $this->assertStringContainsString('ms_custom', $sql);
        $this->assertStringNotContainsString('ms_bj', $sql);
    }

    // ----------------------------------------------------------------
    // getKonfiguratorSegmentFlags
    // ----------------------------------------------------------------

    /** @test */
    public function segment_flags_defaults_to_both_active_on_empty_result(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $db->shouldReceive('getSingleObject')->once()->andReturn(null);

        $result = $this->repoWithDb($db)->getKonfiguratorSegmentFlags();

        $this->assertTrue($result['wm']);
        $this->assertTrue($result['pkw']);
    }

    /** @test */
    public function segment_flags_returns_both_true_on_db_exception(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $db->shouldReceive('getSingleObject')->once()->andThrow(new \Exception('DB down'));

        $result = $this->repoWithDb($db)->getKonfiguratorSegmentFlags();

        $this->assertTrue($result['wm']);
        $this->assertTrue($result['pkw']);
    }

    /** @test */
    public function segment_flags_forces_wm_active_when_both_disabled(): void
    {
        $db  = Mockery::mock(DbInterface::class);
        $row = new stdClass();
        $row->nSegmentWmActive  = 0;
        $row->nSegmentPkwActive = 0;
        $db->shouldReceive('getSingleObject')->once()->andReturn($row);

        $result = $this->repoWithDb($db)->getKonfiguratorSegmentFlags();

        // Safety rule: at least WM must stay active
        $this->assertTrue($result['wm']);
        $this->assertFalse($result['pkw']);
    }

    /** @test */
    public function segment_flags_reads_values_from_db_row(): void
    {
        $db  = Mockery::mock(DbInterface::class);
        $row = new stdClass();
        $row->nSegmentWmActive  = 1;
        $row->nSegmentPkwActive = 0;
        $db->shouldReceive('getSingleObject')->once()->andReturn($row);

        $result = $this->repoWithDb($db)->getKonfiguratorSegmentFlags();

        $this->assertTrue($result['wm']);
        $this->assertFalse($result['pkw']);
    }

    // ----------------------------------------------------------------
    // appendKonfiguratorQueryParams
    // ----------------------------------------------------------------

    /** @test */
    public function query_params_returns_url_unchanged_when_no_filters(): void
    {
        $db = Mockery::mock(DbInterface::class);
        $m  = $this->reflection->getMethod('appendKonfiguratorQueryParams');

        $result = $m->invoke($this->repoWithDb($db), 'https://example.com/dpl', []);
        $this->assertSame('https://example.com/dpl', $result);
    }

    /** @test */
    public function query_params_appends_non_empty_values(): void
    {
        $db  = Mockery::mock(DbInterface::class);
        $m   = $this->reflection->getMethod('appendKonfiguratorQueryParams');
        $url = 'https://example.com/dpl';

        $result = $m->invoke($this->repoWithDb($db), $url, [
            'typ'        => 'Kastenwagen',
            'hersteller' => 'Adria',
            'basis'      => '',        // empty → skipped
            'modell'     => 'Twin',
        ]);

        $this->assertStringContainsString('typ=Kastenwagen', $result);
        $this->assertStringContainsString('hersteller=Adria', $result);
        $this->assertStringContainsString('modell=Twin', $result);
        $this->assertStringNotContainsString('basis=', $result);
    }

    /** @test */
    public function query_params_uses_ampersand_separator_if_url_has_query(): void
    {
        $db  = Mockery::mock(DbInterface::class);
        $m   = $this->reflection->getMethod('appendKonfiguratorQueryParams');
        $url = 'https://example.com/dpl?existing=1';

        $result = $m->invoke($this->repoWithDb($db), $url, ['typ' => 'Kastenwagen']);

        $this->assertStringContainsString('existing=1&', $result);
    }
}
