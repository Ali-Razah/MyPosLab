<?php

namespace Modules\MplBatches\Tests\Feature;

use App\Models\Notification;
use App\Models\Procurement;
use App\Models\ProcurementProduct;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\Role;
use App\Models\User;
use Modules\MplBatches\Jobs\CheckBatchExpiryJob;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Models\BatchAllocation;
use Modules\MplBatches\Services\BatchService;
use Modules\MplBatches\Tests\MplBatchesTestCase;

class BatchLifecycleTest extends MplBatchesTestCase
{
    public function test_batch_is_created_when_a_procurement_is_stocked(): void
    {
        $product = $this->createProduct();
        $expiry = $this->daysFromToday( 90 );

        $data = $this->procure( $product, 25, $expiry, '4471', 3.5 );
        $procurementId = $data[ 'procurement' ][ 'id' ];

        $batch = Batch::where( 'product_id', $product->id )->first();
        $procurementProduct = ProcurementProduct::where( 'procurement_id', $procurementId )->firstOrFail();

        $this->assertInstanceOf( Batch::class, $batch, 'No batch was created on stock receipt.' );
        $this->assertSame( '4471', $batch->batch_number );
        $this->assertSame( $expiry, $batch->expiry_date );
        $this->assertEquals( 25, $batch->quantity_received );
        $this->assertEquals( 25, $batch->quantity_remaining );
        $this->assertEquals( 3.5, $batch->cost );
        $this->assertSame( Batch::STATUS_ACTIVE, $batch->status );
        $this->assertSame( $procurementId, (int) $batch->procurement_id );
        $this->assertSame( $procurementProduct->id, (int) $batch->procurement_product_id );
        $this->assertEquals( 25, $this->unitQuantity( $product )->quantity );
    }

    public function test_batch_number_is_generated_and_created_when_a_pending_procurement_is_delivered_later(): void
    {
        $product = $this->createProduct();

        $data = $this->procure( $product, 10, $this->daysFromToday( 60 ), null, 4, Procurement::PENDING );
        $this->assertSame( 0, Batch::where( 'product_id', $product->id )->count(), 'A batch was created for a pending procurement.' );

        $procurement = Procurement::with( 'products' )->findOrFail( $data[ 'procurement' ][ 'id' ] );
        $line = $procurement->products->first();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'PUT', 'api/procurements/' . $procurement->id, [
                'name' => $procurement->name,
                'general' => [
                    'provider_id' => $procurement->provider_id,
                    'payment_status' => Procurement::PAYMENT_PAID,
                    'delivery_status' => Procurement::DELIVERED,
                    'automatic_approval' => 1,
                ],
                'products' => [ array_merge( $line->only( [
                    'id', 'product_id', 'unit_id', 'quantity', 'gross_purchase_price', 'net_purchase_price',
                    'purchase_price', 'total_purchase_price', 'tax_group_id', 'tax_type', 'tax_value', 'expiration_date',
                ] ) ) ],
            ] );

        $response->assertOk();

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();
        $this->assertSame( 'B' . $procurement->id . '-' . $line->id, $batch->batch_number );
        $this->assertEquals( 10, $batch->quantity_remaining );
    }

    public function test_fefo_allocates_earliest_expiry_first_across_two_batches(): void
    {
        $product = $this->createProduct();

        $this->procure( $product, 10, $this->daysFromToday( 120 ), '200' );
        $this->procure( $product, 5, $this->daysFromToday( 20 ), '100' );
        $this->procure( $product, 4, null, '300' );

        $order = $this->assertOrderSucceeded( $this->sell( $product, 8 ) );

        $early = Batch::where( 'product_id', $product->id )->where( 'batch_number', '100' )->firstOrFail();
        $late = Batch::where( 'product_id', $product->id )->where( 'batch_number', '200' )->firstOrFail();
        $noExpiry = Batch::where( 'product_id', $product->id )->where( 'batch_number', '300' )->firstOrFail();

        $this->assertEquals( 0, $early->quantity_remaining, 'The earliest expiring batch was not consumed first.' );
        $this->assertSame( Batch::STATUS_DEPLETED, $early->status );
        $this->assertEquals( 7, $late->quantity_remaining );
        $this->assertSame( Batch::STATUS_ACTIVE, $late->status );
        $this->assertEquals( 4, $noExpiry->quantity_remaining, 'A batch without expiry must be sold last.' );

        $allocations = BatchAllocation::where( 'order_id', $order[ 'id' ] )->where( 'type', BatchAllocation::TYPE_SALE )->orderBy( 'id' )->get();
        $this->assertCount( 2, $allocations );
        $this->assertSame( $early->id, (int) $allocations[ 0 ]->batch_id );
        $this->assertEquals( 5, $allocations[ 0 ]->quantity );
        $this->assertSame( $late->id, (int) $allocations[ 1 ]->batch_id );
        $this->assertEquals( 3, $allocations[ 1 ]->quantity );
        $this->assertSame( (int) $order[ 'products' ][ 0 ][ 'id' ], (int) $allocations[ 0 ]->order_product_id );
    }

    public function test_partial_allocation_and_depletion_status(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 6, $this->daysFromToday( 45 ), '555' );

        $this->assertOrderSucceeded( $this->sell( $product, 2 ) );

        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();
        $this->assertEquals( 4, $batch->quantity_remaining );
        $this->assertSame( Batch::STATUS_ACTIVE, $batch->status );

        $this->assertOrderSucceeded( $this->sell( $product, 4 ) );

        $batch->refresh();
        $this->assertEquals( 0, $batch->quantity_remaining );
        $this->assertSame( Batch::STATUS_DEPLETED, $batch->status );
        $this->assertEquals( 6, BatchAllocation::where( 'batch_id', $batch->id )->where( 'type', BatchAllocation::TYPE_SALE )->sum( 'quantity' ) );
    }

    public function test_refund_restores_quantity_to_the_same_batches(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 3, $this->daysFromToday( 10 ), '901' );
        $this->procure( $product, 10, $this->daysFromToday( 200 ), '902' );

        $order = $this->assertOrderSucceeded( $this->sell( $product, 5 ) );

        $first = Batch::where( 'product_id', $product->id )->where( 'batch_number', '901' )->firstOrFail();
        $second = Batch::where( 'product_id', $product->id )->where( 'batch_number', '902' )->firstOrFail();
        $this->assertEquals( 0, $first->quantity_remaining );
        $this->assertEquals( 8, $second->quantity_remaining );

        $this->refund( $order, 5 )->assertOk()->assertJsonPath( 'status', 'success' );

        $first->refresh();
        $second->refresh();

        $this->assertEquals( 3, $first->quantity_remaining, 'The refunded quantity was not returned to the first batch.' );
        $this->assertSame( Batch::STATUS_ACTIVE, $first->status, 'A depleted batch receiving stock back must become active again.' );
        $this->assertEquals( 10, $second->quantity_remaining );

        $refunds = BatchAllocation::where( 'order_product_id', $order[ 'products' ][ 0 ][ 'id' ] )->where( 'type', BatchAllocation::TYPE_REFUND )->get();
        $this->assertEquals( 5, $refunds->sum( 'quantity' ) );
        $this->assertEqualsCanonicalizing( [ $first->id, $second->id ], $refunds->pluck( 'batch_id' )->map( fn( $id ) => (int) $id )->all() );
    }

    public function test_partial_refund_returns_to_the_latest_allocated_batch_first(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 3, $this->daysFromToday( 10 ), '911' );
        $this->procure( $product, 10, $this->daysFromToday( 200 ), '912' );

        $order = $this->assertOrderSucceeded( $this->sell( $product, 5 ) );
        $this->refund( $order, 1 )->assertOk();

        $this->assertEquals( 0, Batch::where( 'product_id', $product->id )->where( 'batch_number', '911' )->value( 'quantity_remaining' ) );
        $this->assertEquals( 9, Batch::where( 'product_id', $product->id )->where( 'batch_number', '912' )->value( 'quantity_remaining' ) );
    }

    public function test_sale_with_stock_not_covered_by_any_batch_still_works(): void
    {
        $product = $this->createProduct();

        /**
         * Stock added manually: no procurement, so no batch.
         */
        $unitQuantity = $this->unitQuantity( $product );
        $unitQuantity->quantity = 5;
        $unitQuantity->save();

        $order = $this->assertOrderSucceeded( $this->sell( $product, 3 ) );

        $this->assertSame( 0, Batch::where( 'product_id', $product->id )->count() );
        $this->assertEquals( 2, $this->unitQuantity( $product )->quantity );

        $unallocated = BatchAllocation::where( 'order_id', $order[ 'id' ] )->whereNull( 'batch_id' )->first();
        $this->assertInstanceOf( BatchAllocation::class, $unallocated, 'The uncovered quantity should be recorded as unallocated.' );
        $this->assertEquals( 3, $unallocated->quantity );
        $this->assertNotEmpty( $unallocated->note );
    }

    public function test_sale_larger_than_batches_is_partially_unallocated(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 2, $this->daysFromToday( 30 ), '777' );

        $unitQuantity = $this->unitQuantity( $product );
        $unitQuantity->quantity = 5; // 3 extra pieces not covered by a batch
        $unitQuantity->save();

        $order = $this->assertOrderSucceeded( $this->sell( $product, 4 ) );

        $this->assertEquals( 0, Batch::where( 'product_id', $product->id )->value( 'quantity_remaining' ) );
        $this->assertEquals( 2, BatchAllocation::where( 'order_id', $order[ 'id' ] )->whereNotNull( 'batch_id' )->sum( 'quantity' ) );
        $this->assertEquals( 2, BatchAllocation::where( 'order_id', $order[ 'id' ] )->whereNull( 'batch_id' )->sum( 'quantity' ) );
    }

    public function test_selling_expired_batch_is_blocked_for_prevent_sales_products(): void
    {
        $product = $this->createProduct( expires: true, onExpiration: Product::EXPIRES_PREVENT_SALES );
        $this->procure( $product, 5, $this->daysFromToday( -2 ), 'EXP1' );
        $this->procure( $product, 2, $this->daysFromToday( 30 ), 'OK1' );

        $response = $this->sell( $product, 4 );
        $response->assertStatus( 403 );
        $this->assertEquals( 7, $this->unitQuantity( $product )->quantity, 'Stock moved although the sale was refused.' );

        /**
         * Selling what the valid batch covers works and never touches the expired batch.
         */
        $this->assertOrderSucceeded( $this->sell( $product, 2 ) );
        $this->assertEquals( 5, Batch::where( 'product_id', $product->id )->where( 'batch_number', 'EXP1' )->value( 'quantity_remaining' ) );
        $this->assertEquals( 0, Batch::where( 'product_id', $product->id )->where( 'batch_number', 'OK1' )->value( 'quantity_remaining' ) );
    }

    public function test_expired_batch_can_be_sold_when_product_allows_sales(): void
    {
        $product = $this->createProduct( expires: true, onExpiration: Product::EXPIRES_ALLOW_SALES );
        $this->procure( $product, 5, $this->daysFromToday( -2 ), 'EXP2' );

        $this->assertOrderSucceeded( $this->sell( $product, 3 ) );
        $this->assertEquals( 2, Batch::where( 'product_id', $product->id )->value( 'quantity_remaining' ) );
    }

    public function test_expiry_job_marks_expired_batches_and_sends_one_notification_per_day(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 3 ), 'SOON' );
        $this->procure( $product, 4, $this->daysFromToday( 40 ), 'LATER' );

        /**
         * Simulate a batch whose expiry date has been reached since it was received.
         */
        $expired = Batch::where( 'product_id', $product->id )->where( 'batch_number', 'SOON' )->firstOrFail();
        $expired->expiry_date = $this->daysFromToday( -1 );
        $expired->status = Batch::STATUS_ACTIVE;
        $expired->save();

        $identifier = CheckBatchExpiryJob::NOTIFICATION_PREFIX . $this->today();
        Notification::where( 'identifier', $identifier )->delete();

        CheckBatchExpiryJob::dispatchSync();

        $this->assertSame( Batch::STATUS_EXPIRED, $expired->fresh()->status );
        $this->assertSame( Batch::STATUS_ACTIVE, Batch::where( 'product_id', $product->id )->where( 'batch_number', 'LATER' )->value( 'status' ) );

        $admin = User::whereHas( 'roles', fn( $query ) => $query->where( 'namespace', Role::ADMIN ) )->firstOrFail();
        $this->assertSame( 1, Notification::where( 'identifier', $identifier )->where( 'user_id', $admin->id )->count() );

        CheckBatchExpiryJob::dispatchSync();
        CheckBatchExpiryJob::dispatchSync();

        $this->assertSame( 1, Notification::where( 'identifier', $identifier )->where( 'user_id', $admin->id )->count(), 'Repeated runs on the same day must not create new notifications.' );
        $this->assertSame( $this->today(), ns()->option->get( BatchService::OPTION_LAST_ALERT ) );

        $notification = Notification::where( 'identifier', $identifier )->where( 'user_id', $admin->id )->first();
        $this->assertStringContainsString( 'expired', strtolower( $notification->description ) );
    }

    public function test_write_off_removes_stock_and_records_an_adjustment(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 15 ), 'W1' );
        $this->procure( $product, 3, $this->daysFromToday( -1 ), 'W2' );

        $target = Batch::where( 'product_id', $product->id )->where( 'batch_number', 'W2' )->firstOrFail();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/mplbatches/batches/' . $target->id . '/write-off' );

        $response->assertOk()->assertJsonPath( 'status', 'success' );

        $target->refresh();
        $this->assertEquals( 0, $target->quantity_remaining );
        $this->assertSame( Batch::STATUS_WRITTEN_OFF, $target->status );
        $this->assertEquals( 5, Batch::where( 'product_id', $product->id )->where( 'batch_number', 'W1' )->value( 'quantity_remaining' ), 'Write-off touched another batch.' );
        $this->assertEquals( 5, $this->unitQuantity( $product )->quantity );

        $adjustment = BatchAllocation::where( 'batch_id', $target->id )->where( 'type', BatchAllocation::TYPE_ADJUSTMENT )->first();
        $this->assertInstanceOf( BatchAllocation::class, $adjustment );
        $this->assertEquals( 3, $adjustment->quantity );

        $history = ProductHistory::find( $adjustment->product_history_id );
        $this->assertSame( ProductHistory::ACTION_REMOVED, $history->operation_type );

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/mplbatches/batches/' . $target->id . '/write-off' )
            ->assertStatus( 403 );
    }

    public function test_partial_write_off_with_quantity(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 15 ), 'PW' );
        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/mplbatches/batches/' . $batch->id . '/write-off', [ 'quantity' => 2, 'reason' => 'Broken blister' ] )
            ->assertOk();

        $batch->refresh();
        $this->assertEquals( 3, $batch->quantity_remaining );
        $this->assertSame( Batch::STATUS_ACTIVE, $batch->status );
        $this->assertEquals( 3, $this->unitQuantity( $product )->quantity );
    }

    public function test_batch_can_be_edited_through_crud(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 15 ), '1234' );
        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'PUT', 'api/crud/mplbatches.batches/' . $batch->id, [
                'batch_number' => 'LOT-AB12',
                'general' => [
                    'expiry_date' => $this->daysFromToday( -3 ),
                ],
            ] );

        $response->assertOk();

        $batch->refresh();
        $this->assertSame( 'LOT-AB12', $batch->batch_number );
        $this->assertSame( $this->daysFromToday( -3 ), $batch->expiry_date );
        $this->assertSame( Batch::STATUS_EXPIRED, $batch->status );

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/crud/mplbatches.batches' )
            ->assertOk()
            ->assertJsonFragment( [ 'batch_number' => 'LOT-AB12' ] );
    }

    public function test_dashboard_pages_render_for_admin(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 2 ), 'PAGE1' );
        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $user = $this->attemptGetAnyUserFromRole( Role::ADMIN );

        $this->actingAs( $user )->get( ns()->route( 'mplbatches.report' ) )->assertOk()->assertSee( 'PAGE1' );
        $this->actingAs( $user )->get( ns()->route( 'mplbatches.report.print' ) )->assertOk()->assertSee( 'PAGE1' );
        $this->actingAs( $user )->get( ns()->route( 'mplbatches.batches' ) )->assertOk();
        $this->actingAs( $user )->get( ns()->route( 'mplbatches.batches.allocations', [ 'batch' => $batch->id ] ) )->assertOk();
        $this->actingAs( $user )->get( ns()->route( 'mplbatches.batches.edit', [ 'batch' => $batch->id ] ) )->assertOk();
        $this->actingAs( $user )->get( ns()->route( 'mplbatches.settings' ) )->assertOk();
    }

    public function test_user_without_permission_is_denied(): void
    {
        $product = $this->createProduct();
        $this->procure( $product, 5, $this->daysFromToday( 15 ), 'DENY' );
        $batch = Batch::where( 'product_id', $product->id )->firstOrFail();

        $user = $this->actAsUserWithoutBatchPermissions();

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/mplbatches/batches/' . $batch->id . '/write-off' )
            ->assertForbidden();

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/crud/mplbatches.batches' )
            ->assertForbidden();

        $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'GET', 'api/mplbatches/summary' )
            ->assertForbidden();

        $this->actingAs( $user )->get( ns()->route( 'mplbatches.report' ) )->assertForbidden();

        $this->assertEquals( 5, $batch->fresh()->quantity_remaining );
    }
}
