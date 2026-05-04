<?php
declare(strict_types=1);

namespace Plugin\SideLeftFiltre\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Plugin\SideLeftFiltre\src\SideLeftFiltreDatabase;

/**
 * Unit tests for SideLeftFiltreDatabase::parseOrder().
 *
 * parseOrder() is a pure function with no DB dependency — easy to test fully.
 */
class ParseOrderTest extends TestCase
{
    // ── Empty / whitespace ───────────────────────────────────────────────────

    #[Test]
    public function empty_string_returns_empty_array(): void
    {
        $this->assertSame([], SideLeftFiltreDatabase::parseOrder(''));
    }

    #[Test]
    public function whitespace_only_string_returns_empty_array(): void
    {
        $this->assertSame([], SideLeftFiltreDatabase::parseOrder("   \n  \t  "));
    }

    // ── Newline separated ────────────────────────────────────────────────────

    #[Test]
    public function single_id_on_one_line(): void
    {
        $this->assertSame(['fahrzeug-model'], SideLeftFiltreDatabase::parseOrder('fahrzeug-model'));
    }

    #[Test]
    public function multiple_ids_separated_by_lf(): void
    {
        $csv = "fahrzeug-model\nprice\nmanufacturer";
        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function multiple_ids_separated_by_crlf(): void
    {
        $csv = "fahrzeug-model\r\nprice\r\nmanufacturer";
        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function multiple_ids_separated_by_cr_only(): void
    {
        $csv = "fahrzeug-model\rprice\rmanufacturer";
        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function blank_lines_are_skipped(): void
    {
        $csv = "fahrzeug-model\n\n\nprice\n\nmanufacturer";
        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    // ── Comma separated ──────────────────────────────────────────────────────

    #[Test]
    public function comma_separated_on_single_line(): void
    {
        $csv = 'fahrzeug-model, price, manufacturer';
        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function comma_without_spaces(): void
    {
        $csv = 'fahrzeug-model,price,manufacturer';
        $this->assertSame(['fahrzeug-model', 'price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function mixed_newline_and_comma_on_same_line(): void
    {
        $csv = "fahrzeug-model, price\nmanufacturer, availability";
        $this->assertSame(
            ['fahrzeug-model', 'price', 'manufacturer', 'availability'],
            SideLeftFiltreDatabase::parseOrder($csv)
        );
    }

    // ── Deduplication ────────────────────────────────────────────────────────

    #[Test]
    public function duplicate_ids_are_removed_keeping_first_occurrence(): void
    {
        $csv = "price\nfahrzeug-model\nprice";
        $this->assertSame(['price', 'fahrzeug-model'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function case_insensitive_deduplication_keeps_first_casing(): void
    {
        $csv = "Price\nprice\nPRICE";
        $this->assertSame(['Price'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function duplicate_comma_separated_ids_are_removed(): void
    {
        $csv = 'price, price, manufacturer';
        $this->assertSame(['price', 'manufacturer'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    // ── Trimming ─────────────────────────────────────────────────────────────

    #[Test]
    public function leading_and_trailing_whitespace_is_trimmed_per_id(): void
    {
        $csv = "  fahrzeug-model  \n  price  ";
        $this->assertSame(['fahrzeug-model', 'price'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    #[Test]
    public function ids_with_internal_whitespace_are_preserved(): void
    {
        $csv = "fahrzeug-marke\nfahrzeug-model-2024";
        $this->assertSame(['fahrzeug-marke', 'fahrzeug-model-2024'], SideLeftFiltreDatabase::parseOrder($csv));
    }

    // ── Order preservation ───────────────────────────────────────────────────

    #[Test]
    public function original_order_is_preserved(): void
    {
        $csv = "manufacturer\nprice\nfahrzeug-model\navailability";
        $result = SideLeftFiltreDatabase::parseOrder($csv);
        $this->assertSame('manufacturer',   $result[0]);
        $this->assertSame('price',          $result[1]);
        $this->assertSame('fahrzeug-model', $result[2]);
        $this->assertSame('availability',   $result[3]);
    }

    // ── Return type ──────────────────────────────────────────────────────────

    #[Test]
    public function return_value_is_a_plain_indexed_array(): void
    {
        $result = SideLeftFiltreDatabase::parseOrder("a\nb\nc");
        $this->assertIsArray($result);
        $this->assertSame([0, 1, 2], array_keys($result));
    }
}
