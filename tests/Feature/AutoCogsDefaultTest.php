<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxGroup;
use App\Models\Unit;
use App\Models\UnitGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthentication;
use Tests\Traits\WithProductTest;

/**
 * Products saved without an explicit "auto_cogs" flag (API, imports, demo data)
 * must not silently lose automatic COGS, otherwise profit reports treat the
 * whole sale price as profit.
 */
class AutoCogsDefaultTest extends TestCase
{
    /**
     * Roll back everything this test creates so it never changes what later
     * test files find in the shared test database.
     */
    use DatabaseTransactions, WithAuthentication, WithProductTest;

    /**
     * The test must not depend on other test files having created a category
     * or a unit group first, so it creates what it needs when missing.
     *
     * @return array{0: ProductCategory, 1: UnitGroup}
     */
    private function ensureCatalog(): array
    {
        $category = ProductCategory::first() ?? ProductCategory::factory()->create( [ 'displays_on_pos' => true ] );
        $unitGroup = UnitGroup::whereHas( 'units' )->first();

        if ( ! $unitGroup instanceof UnitGroup ) {
            $unitGroup = UnitGroup::factory()->create();
            Unit::factory()->create( [
                'group_id' => $unitGroup->id,
                'identifier' => 'auto-cogs-' . Str::lower( Str::random( 8 ) ),
                'base_unit' => 1,
                'value' => 1,
            ] );
        }

        return [ $category, $unitGroup ];
    }

    private function form( array $units = [], ?string $name = null ): array
    {
        [ $category, $unitGroup ] = $this->ensureCatalog();

        return [
            'name' => $name ?? 'Auto COGS ' . Str::random( 6 ),
            'variations' => [
                [
                    '$primary' => true,
                    'expiracy' => [ 'expires' => 0, 'on_expiration' => 'prevent_sales' ],
                    'identification' => [
                        'barcode' => Str::random( 10 ),
                        'barcode_type' => 'code128',
                        'searchable' => true,
                        'category_id' => $category->id,
                        'description' => 'Created via tests',
                        'product_type' => 'product',
                        'type' => Product::TYPE_MATERIALIZED,
                        'sku' => Str::random( 15 ) . '-sku',
                        'status' => 'available',
                        'stock_management' => 'enabled',
                    ],
                    'images' => [],
                    'groups' => [ 'product_subitems' => [] ],
                    'taxes' => [ 'tax_group_id' => TaxGroup::first()?->id ?: 0, 'tax_type' => 'inclusive' ],
                    'units' => array_merge( [
                        'selling_group' => $unitGroup->units()->limit( 1 )->get()->map( fn( $unit ) => array_merge( [
                            'sale_price_edit' => 100,
                            'wholesale_price_edit' => 90,
                            'unit_id' => $unit->id,
                        ], $units[ 'unit' ] ?? [] ) )->toArray(),
                        'unit_group' => $unitGroup->id,
                    ], $units[ 'group' ] ?? [] ),
                ],
            ],
        ];
    }

    public function test_new_product_without_flag_uses_auto_cogs(): void
    {
        $this->attemptAuthenticate();

        $response = $this->attemptSetProduct( form: $this->form(), skip_tests: true );

        $this->assertTrue( (bool) Product::find( $response[ 'data' ][ 'product' ][ 'id' ] )->auto_cogs );
    }

    public function test_new_product_with_manual_cogs_keeps_manual_costing(): void
    {
        $this->attemptAuthenticate();

        $response = $this->attemptSetProduct( form: $this->form( [ 'unit' => [ 'cogs' => 10 ] ] ), skip_tests: true );

        $this->assertFalse( (bool) Product::find( $response[ 'data' ][ 'product' ][ 'id' ] )->auto_cogs );
    }

    public function test_editing_without_flag_keeps_existing_choice(): void
    {
        $this->attemptAuthenticate();

        $created = $this->attemptSetProduct(
            form: $this->form( [ 'group' => [ 'auto_cogs' => false ] ] ),
            skip_tests: true
        );

        $id = $created[ 'data' ][ 'product' ][ 'id' ];
        $this->assertFalse( (bool) Product::find( $id )->auto_cogs );

        $this->attemptSetProduct( product_id: $id, form: $this->form(), skip_tests: true );

        $this->assertFalse( (bool) Product::find( $id )->auto_cogs );
    }
}
