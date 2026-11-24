<?php

namespace Modules\MplBatches\Tests\Feature;

use App\Models\OrderProductRefund;
use App\Models\ProductHistory;
use App\Services\ProductService;
use App\Services\WidgetService;
use Illuminate\Console\Scheduling\Schedule;
use Modules\MplBatches\Console\Kernel;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Models\BatchAllocation;
use Modules\MplBatches\Services\BatchService;
use Modules\MplBatches\Tests\MplBatchesTestCase;
use Modules\MplBatches\Widgets\ExpiringSoonWidget;

class BatchExtrasTest extends MplBatchesTestCase
{
    public function test_damaged_refund_does_not_put_stock_back_in_the_batch(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 50 ), 'DMG' );

        $order = $this->assertOrderSucceeded( $this->sell( $product, 2 ) );
        $this->refund( $order, 1, OrderProductRefund::CONDITION_DAMAGED )->assertOk();

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $this->assertEquals( 3, $batch->quantity_remaining, 'A damaged return must not be sellable again.' );
        $this->assertEquals( 3, $this->unitQuantity( $product )->quantity );
        $this->assertEquals( 1, BatchAllocation::where( 'batch_id', $batch->id )->where( 'type', BatchAllocation::TYPE_ADJUSTMENT )->sum( 'quantity' ) );
    }

    public function test_manual_stock_removal_takes_expired_stock_first(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 4, $this->daysFromToday( 60 ), 'FRESH' );
        $this->procure( $product, 2, $this->daysFromToday( -5 ), 'OLD' );

        app()->make( ProductService::class )->stockAdjustment( ProductHistory::ACTION_DEFECTIVE, [
            'product_id' => $product->id,
            'unit_id' => $this->baseUnit()->id,
            'unit_price' => 4,
            'quantity' => 3,
            'description' => 'Broken during shelving',
        ] );

        $this->assertEquals( 3, $this->unitQuantity( $product )->quantity );
        $this->assertEquals( 0, Batch::where( 'product_id', $product->id )->where( 'batch_number', 'OLD' )->value( 'quantity_remaining' ) );
        $this->assertEquals( 3, Batch::where( 'product_id', $product->id )->where( 'batch_number', 'FRESH' )->value( 'quantity_remaining' ) );
    }

    public function test_widget_is_registered_and_summary_lists_urgent_batches(): void
    {
        $this->assertTrue(
            app()->make( WidgetService::class )->getAllWidgets()->contains( fn( $widget ) => $widget->{'class-name'} === ExpiringSoonWidget::class ),
            'The Expiring Soon widget is not registered.'
        );

        $product = $this->createProduct();
        $this->procure( $product, 3, $this->daysFromToday( 2 ), 'URGENT' );

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/mplbatches/summary' );

        $response->assertOk();
        $this->assertGreaterThanOrEqual( 1, $response->json( 'critical' ) );
        $this->assertLessThanOrEqual( 5, count( $response->json( 'top' ) ) );
        $response->assertJsonStructure( [ 'expired', 'critical', 'soon', 'top' => [ [ 'id', 'product', 'batch_number', 'expiry_date', 'quantity_remaining', 'days_left' ] ] ] );

        /**
         * The test database keeps batches from earlier runs, so check the bucket itself.
         */
        $critical = app()->make( BatchService::class )->getExpiryBuckets()[ 'critical' ];
        $this->assertContains( 'URGENT', $critical->where( 'product_id', $product->id )->pluck( 'batch_number' )->all() );
    }

    public function test_procurement_form_exposes_the_batch_number_column(): void
    {
        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/forms/ns.procurement' );

        $response->assertOk();
        $this->assertArrayHasKey( 'batch_number', $response->json( 'columns' ) );
    }

    public function test_module_kernel_schedules_the_daily_expiry_check(): void
    {
        $schedule = app()->make( Schedule::class );
        $kernel = new Kernel( app(), app( 'events' ) );
        $kernel->schedule( $schedule );

        $event = collect( $schedule->events() )->first( fn( $event ) => $event->description === 'mplbatches:check-expiry' );

        $this->assertNotNull( $event, 'The expiry job is not scheduled.' );
        $this->assertSame( '0 6 * * *', $event->expression );
    }
}
