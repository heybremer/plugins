<?php
declare(strict_types=1);

namespace Plugin\SideLeftFiltre\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plugin\SideLeftFiltre\src\SideLeftFiltreDatabase;
use Mockery;
use Mockery\MockInterface;
use JTL\DB\DbInterface;

/**
 * Integration-style tests for SideLeftFiltreDatabase::getConfig() and ensureAll().
 *
 * Uses Mockery to stub the JTL DB layer — no real database needed.
 * The internal static $ensured flag is reset via Reflection before every test.
 *
 * Important: getSingleObject() in the real source is called WITHOUT a second
 * argument, so Mockery expectations must not include the [] parameter.
 */
class SideLeftFiltreDatabaseTest extends TestCase
{
    private MockInterface $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = Mockery::mock(DbInterface::class);

        $db = $this->dbMock;
        $container = new class($db) {
            public function __construct(private readonly object $db) {}
            public function getDB(): object { return $this->db; }
        };
        \JTL\Shop::setContainer($container);

        $this->resetEnsuredFlag();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->resetEnsuredFlag();
    }

    // ── getConfig() ──────────────────────────────────────────────────────────

    #[Test]
    public function getConfig_returns_safe_defaults_when_db_throws(): void
    {
        $this->dbMock->shouldReceive('executeQuery')->andThrow(new \RuntimeException('DB down'));
        $this->dbMock->shouldReceive('getSingleObject')->andThrow(new \RuntimeException('DB down'));

        $result = SideLeftFiltreDatabase::getConfig();

        $this->assertIsBool($result['active']);
        $this->assertIsArray($result['order']);
        $this->assertIsArray($result['open']);
        $this->assertIsArray($result['close']);
    }

    #[Test]
    public function getConfig_returns_active_true_with_empty_arrays_when_no_row_exists(): void
    {
        $this->expectEnsureAllQueries(rowExists: false, expectInsert: true);

        $this->dbMock
            ->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT nActive/'))
            ->once()
            ->andReturn(null);

        $result = SideLeftFiltreDatabase::getConfig();

        $this->assertTrue($result['active']);
        $this->assertSame([], $result['order']);
        $this->assertSame([], $result['open']);
        $this->assertSame([], $result['close']);
    }

    #[Test]
    public function getConfig_maps_nActive_0_to_false(): void
    {
        $this->expectEnsureAllQueries(rowExists: true);

        $row = (object)['nActive' => '0', 'cOrderCsv' => '', 'cOpenCsv' => '', 'cCloseCsv' => ''];
        $this->dbMock
            ->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT nActive/'))
            ->once()
            ->andReturn($row);

        $result = SideLeftFiltreDatabase::getConfig();
        $this->assertFalse($result['active']);
    }

    #[Test]
    public function getConfig_maps_nActive_1_to_true(): void
    {
        $this->expectEnsureAllQueries(rowExists: true);

        $row = (object)['nActive' => '1', 'cOrderCsv' => '', 'cOpenCsv' => '', 'cCloseCsv' => ''];
        $this->dbMock
            ->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT nActive/'))
            ->once()
            ->andReturn($row);

        $result = SideLeftFiltreDatabase::getConfig();
        $this->assertTrue($result['active']);
    }

    #[Test]
    public function getConfig_parses_order_csv_correctly(): void
    {
        $this->expectEnsureAllQueries(rowExists: true);

        $row = (object)[
            'nActive'   => '1',
            'cOrderCsv' => "fahrzeug-model\nprice\nmanufacturer",
            'cOpenCsv'  => 'fahrzeug-marke',
            'cCloseCsv' => 'price',
        ];
        $this->dbMock
            ->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT nActive/'))
            ->once()
            ->andReturn($row);

        $result = SideLeftFiltreDatabase::getConfig();

        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], $result['order']);
        $this->assertSame(['fahrzeug-marke'], $result['open']);
        $this->assertSame(['price'], $result['close']);
    }

    #[Test]
    public function getConfig_open_and_close_parse_independently(): void
    {
        $this->expectEnsureAllQueries(rowExists: true);

        $row = (object)[
            'nActive'   => '1',
            'cOrderCsv' => '',
            'cOpenCsv'  => "fahrzeug-marke\nprice",
            'cCloseCsv' => "price\nmanufacturer",
        ];
        $this->dbMock
            ->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT nActive/'))
            ->once()
            ->andReturn($row);

        $result = SideLeftFiltreDatabase::getConfig();

        // Both lists parse independently — JS resolves conflict (open wins)
        $this->assertContains('price', $result['open']);
        $this->assertContains('price', $result['close']);
    }

    // ── ensureAll() ──────────────────────────────────────────────────────────

    #[Test]
    public function ensureAll_is_idempotent_within_same_request(): void
    {
        $this->expectEnsureAllQueries(rowExists: true);

        SideLeftFiltreDatabase::ensureAll(); // DB queries run
        SideLeftFiltreDatabase::ensureAll(); // $ensured = true → no DB calls
        SideLeftFiltreDatabase::ensureAll(); // same

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function ensureAll_inserts_default_row_when_table_is_empty(): void
    {
        $this->expectEnsureAllQueries(rowExists: false, expectInsert: true);

        SideLeftFiltreDatabase::ensureAll();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function ensureAll_does_not_insert_when_row_already_exists(): void
    {
        $this->expectEnsureAllQueries(rowExists: true, expectInsert: false);

        $this->dbMock->shouldNotReceive('executeQueryPrepared');

        SideLeftFiltreDatabase::ensureAll();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function ensureAll_does_not_hard_fail_when_alter_throws(): void
    {
        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/CREATE TABLE IF NOT EXISTS/'))
            ->once();

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/ALTER TABLE.*cOpenCsv/'))
            ->once()
            ->andThrow(new \RuntimeException('Duplicate column'));

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/ALTER TABLE.*cCloseCsv/'))
            ->once()
            ->andThrow(new \RuntimeException('Duplicate column'));

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/UPDATE.*cOpenCsv IS NULL/'))
            ->once();

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/UPDATE.*cCloseCsv IS NULL/'))
            ->once();

        $this->dbMock->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT kID FROM twomosi_sideleftfiltre_settings/'))
            ->once()
            ->andReturn((object)['kID' => 1]);

        SideLeftFiltreDatabase::ensureAll();

        $this->addToAssertionCount(1);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function expectEnsureAllQueries(bool $rowExists, bool $expectInsert = false): void
    {
        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/CREATE TABLE IF NOT EXISTS/'))
            ->once();

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/ALTER TABLE.*cOpenCsv/'))
            ->once();

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/ALTER TABLE.*cCloseCsv/'))
            ->once();

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/UPDATE.*cOpenCsv IS NULL/'))
            ->once();

        $this->dbMock->shouldReceive('executeQuery')
            ->with(Mockery::pattern('/UPDATE.*cCloseCsv IS NULL/'))
            ->once();

        $this->dbMock->shouldReceive('getSingleObject')
            ->with(Mockery::pattern('/SELECT kID FROM twomosi_sideleftfiltre_settings/'))
            ->once()
            ->andReturn($rowExists ? (object)['kID' => 1] : null);

        if ($expectInsert) {
            $this->dbMock->shouldReceive('executeQueryPrepared')
                ->with(
                    Mockery::pattern('/INSERT INTO twomosi_sideleftfiltre_settings/'),
                    Mockery::on(fn($p) => isset($p['csv'], $p['open'], $p['close']))
                )
                ->once();
        }
    }

    private function resetEnsuredFlag(): void
    {
        $ref  = new \ReflectionClass(SideLeftFiltreDatabase::class);
        $prop = $ref->getProperty('ensured');
        $prop->setValue(null, false);
    }
}
