<?php

namespace Modules\MplBatches\Tests\Feature;

use App\Events\ProductAfterStockAdjustmentEvent;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\ProductSubItem;
use Illuminate\Support\Facades\Auth;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Models\BatchAllocation;
use Modules\MplBatches\Services\BatchService;
use Modules\MplBatches\Tests\MplBatchesTestCase;
use RuntimeException;

/**
 * Regression tests written during the module review.
 */
class BatchReviewRegressionTest extends MplBatchesTestCase
{
    public function test_a_batch_failure_never_leaves_a_half_applied_allocation(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 10, $this->daysFromToday( 30 ), 'TX1' );

        $fail = true;
        BatchAllocation::creating( function () use ( &$fail ) {
            if ( $fail ) {
                throw new RuntimeException( 'Simulated batch bookkeeping failure.' );
            }
        } );

        try {
            $this->assertOrderSucceeded( $this->sell( $product, 3 ) );
        } finally {
            $fail = false;
        }

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $this->assertEquals( 7, $this->unitQuantity( $product )->quantity, 'The sale itself must go through.' );
        $this->assertEquals( 10, $batch->quantity_remaining, 'The batch was decremented although its allocation failed (half-applied state).' );
        $this->assertSame( 0, BatchAllocation::where( 'batch_id', $batch->id )->count() );
    }

    public function test_the_same_stock_event_handled_twice_is_not_double_counted(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 10, $this->daysFromToday( 30 ), 'IDEM' );

        $order = $this->assertOrderSucceeded( $this->sell( $product, 3 ) );

        $history = ProductHistory::where( 'order_product_id', $order[ 'products' ][ 0 ][ 'id' ] )
            ->where( 'operation_type', ProductHistory::ACTION_SOLD )
            ->firstOrFail();

        event( new ProductAfterStockAdjustmentEvent( $history ) );

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $this->assertEquals( 7, $batch->quantity_remaining, 'Replaying the same product history allocated the sale twice.' );
        $this->assertEquals( 3, BatchAllocation::where( 'product_history_id', $history->id )->sum( 'quantity' ) );
    }

    public function test_stock_returned_to_a_written_off_batch_is_tracked_again(): void
    {
        $product = $this->createProduct( expires: true, onExpiration: Product::EXPIRES_PREVENT_SALES );
        $this->procure( $product, 10, $this->daysFromToday( 30 ), 'WOR' );

        $order = $this->assertOrderSucceeded( $this->sell( $product, 2 ) );

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();
        app()->make( BatchService::class )->writeOff( $batch );
        $this->assertSame( Batch::STATUS_WRITTEN_OFF, $batch->fresh()->status );

        $this->refund( $order, 2 )->assertOk();

        $batch->refresh();
        $this->assertEquals( 2, $batch->quantity_remaining );
        $this->assertNotSame( Batch::STATUS_WRITTEN_OFF, $batch->status, 'Returned stock sits in a written-off batch: it is invisible to reports, FEFO and expiry blocking.' );
        $this->assertSame( Batch::STATUS_ACTIVE, $batch->status );
    }

    public function test_grouped_product_with_an_expired_component_is_blocked(): void
    {
        $component = $this->createProduct( expires: true, onExpiration: Product::EXPIRES_PREVENT_SALES );
        $this->procure( $component, 5, $this->daysFromToday( -2 ), 'GEXP' );

        $bundle = $this->createGroupedProduct( $component, 2 );

        $response = $this->sellProduct( $bundle, 1 );

        $response->assertStatus( 403 );
        $this->assertEquals( 5, $this->unitQuantity( $component )->quantity, 'An expired component was sold through a grouped product.' );
    }

    public function test_grouped_product_with_valid_components_is_sold_from_batches(): void
    {
        $component = $this->createProduct( expires: true, onExpiration: Product::EXPIRES_PREVENT_SALES );
        $this->procure( $component, 5, $this->daysFromToday( 20 ), 'GOK' );

        $bundle = $this->createGroupedProduct( $component, 2 );

        $this->assertOrderSucceeded( $this->sellProduct( $bundle, 1 ) );

        $this->assertEquals( 3, $this->unitQuantity( $component )->quantity );
        $this->assertEquals( 3, Batch::where( 'product_id', $component->id )->value( 'quantity_remaining' ) );
    }

    public function test_hold_status_with_a_payment_cannot_bypass_the_expired_block(): void
    {
        $product = $this->createProduct( expires: true, onExpiration: Product::EXPIRES_PREVENT_SALES );
        $this->procure( $product, 5, $this->daysFromToday( -2 ), 'HOLDX' );

        $response = $this->sellProduct( $product, 2, [ 'payment_status' => Order::PAYMENT_HOLD ] );

        $response->assertStatus( 403 );
        $this->assertEquals( 5, $this->unitQuantity( $product )->quantity, 'Core treated the order as paid and sold expired stock.' );
    }

    public function test_write_off_is_refused_when_core_would_not_move_the_stock(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 4, $this->daysFromToday( -1 ), 'NOSM' );

        $product->stock_management = Product::STOCK_MANAGEMENT_DISABLED;
        $product->save();

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/mplbatches/batches/' . $batch->id . '/write-off' )
            ->assertStatus( 403 );

        $this->assertEquals( 4, $batch->fresh()->quantity_remaining );
    }

    public function test_void_and_delete_return_stock_to_the_batches(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 10, $this->daysFromToday( 30 ), 'VD' );

        $voided = $this->assertOrderSucceeded( $this->sell( $product, 3 ) );
        $deleted = $this->assertOrderSucceeded( $this->sell( $product, 2 ) );

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();
        $this->assertEquals( 5, $batch->quantity_remaining );

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/orders/' . $voided[ 'id' ] . '/void', [ 'reason' => 'Review test' ] )
            ->assertOk();

        $this->assertEquals( 8, $batch->fresh()->quantity_remaining );

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'DELETE', 'api/crud/ns.orders/' . $deleted[ 'id' ] )
            ->assertOk();

        $this->assertEquals( 10, $batch->fresh()->quantity_remaining );
        $this->assertEquals( 10, $this->unitQuantity( $product )->quantity );
    }

    private function createGroupedProduct( Product $component, float $quantity ): Product
    {
        $bundle = $this->createProduct( expires: false, onExpiration: Product::EXPIRES_ALLOW_SALES );
        $bundle->type = Product::TYPE_GROUPED;
        $bundle->save();

        $subItem = new ProductSubItem;
        $subItem->parent_id = $bundle->id;
        $subItem->product_id = $component->id;
        $subItem->unit_id = $this->baseUnit()->id;
        $subItem->unit_quantity_id = $this->unitQuantity( $component )->id;
        $subItem->quantity = $quantity;
        $subItem->sale_price = 10;
        $subItem->total_price = 10 * $quantity;
        $subItem->author_id = Auth::id();
        $subItem->save();

        return $bundle->fresh();
    }

    private function sellProduct( Product $product, float $quantity, array $overrides = [] )
    {
        return $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/orders', array_merge( [
                'customer_id' => $this->anyCustomerId(),
                'type' => [ 'identifier' => 'takeaway' ],
                'shipping' => 0,
                'discount' => 0,
                'discount_percentage' => 0,
                'products' => [
                    [
                        'product_id' => $product->id,
                        'unit_quantity_id' => $this->unitQuantity( $product )->id,
                        'unit_id' => $this->baseUnit()->id,
                        'quantity' => $quantity,
                        'unit_price' => 10,
                    ],
                ],
                'payments' => [
                    [
                        'identifier' => OrderPayment::PAYMENT_CASH,
                        'value' => 10 * $quantity,
                    ],
                ],
            ], $overrides ) );
    }
}
