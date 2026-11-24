<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Crud\OrderCrud;
use App\Models\Order;
use App\Models\Role;
use Modules\MplBranches\Models\BranchOrder;
use Modules\MplBranches\Models\BranchUser;
use Modules\MplBranches\Services\BranchService;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class OrderTaggingTest extends MplBranchesTestCase
{
    public function test_order_is_tagged_with_the_register_branch(): void
    {
        $this->actingAsUser( $this->admin() );

        $branch = $this->createBranch( [ 'emirate' => 'Sharjah' ] );
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );

        $order = $this->createPaidOrder( [ 'register_id' => $register->id ] );

        $tag = BranchOrder::where( 'order_id', $order->id )->firstOrFail();

        $this->assertSame( (int) $branch->id, (int) $tag->branch_id );
        $this->assertSame( (int) $register->id, (int) $tag->register_id );
        $this->assertSame( BranchOrder::SOURCE_REGISTER, $tag->source );
        $this->assertSame( $branch->name, $this->branchService()->getOrderBranch( $order->id )?->name );
    }

    /**
     * Moving a register to another branch must not move the sales already
     * recorded on it when one of those orders is later updated (refund, note...).
     */
    public function test_moving_a_register_keeps_the_branch_of_past_orders(): void
    {
        $this->actingAsUser( $this->admin() );

        $first = $this->createBranch();
        $second = $this->createBranch();
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $first, [ $register->id ] );

        $order = $this->createPaidOrder( [ 'register_id' => $register->id ] );
        $this->assertSame( (int) $first->id, (int) BranchOrder::where( 'order_id', $order->id )->value( 'branch_id' ) );

        $this->branchService()->syncBranchRegisters( $second, [ $register->id ] );

        $order->refresh();
        $order->note = 'Customer called back';
        $order->save();

        $this->assertSame( (int) $first->id, (int) BranchOrder::where( 'order_id', $order->id )->value( 'branch_id' ) );
    }

    public function test_register_attached_by_the_payment_still_sets_the_branch(): void
    {
        $this->actingAsUser( $this->admin() );

        $branch = $this->createBranch();
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );

        $order = $this->createPaidOrder( [
            'payments' => [ [ 'identifier' => 'cash-payment', 'value' => 0.01 ] ],
        ] );

        $this->assertSame( BranchOrder::SOURCE_DEFAULT, BranchOrder::where( 'order_id', $order->id )->value( 'source' ) );

        $this->withSession( $this->app[ 'session' ]->all() )->json( 'POST', 'api/orders/' . $order->id . '/payments', [
            'identifier' => 'cash-payment',
            'value' => 999999,
            'register_id' => $register->id,
        ] )->assertOk();

        $tag = BranchOrder::where( 'order_id', $order->id )->firstOrFail();

        $this->assertSame( (int) $branch->id, (int) $tag->branch_id );
        $this->assertSame( BranchOrder::SOURCE_REGISTER, $tag->source );
    }

    public function test_order_is_tagged_with_the_current_branch_of_the_author_when_no_register(): void
    {
        $cashier = $this->createStaff( Role::STORECASHIER );
        $primary = $this->createBranch();
        $other = $this->createBranch();
        $this->branchService()->syncBranchUsers( $primary, [ $cashier->id ] );
        $this->branchService()->syncBranchUsers( $other, [ $cashier->id ] );

        $this->assertTrue( BranchUser::where( [ 'user_id' => $cashier->id, 'branch_id' => $primary->id ] )->first()->is_primary );

        $this->actingAsUser( $cashier );

        // without switching: primary branch
        $order = $this->createPaidOrder();
        $this->assertSame( (int) $primary->id, (int) BranchOrder::where( 'order_id', $order->id )->value( 'branch_id' ) );

        // after switching: the session branch
        $this->json( 'POST', 'api/mplbranches/switch', [ 'branch_id' => $other->id ] )->assertOk();
        $this->assertSame( (int) $other->id, (int) session( BranchService::SESSION_KEY ) );

        $order = $this->createPaidOrder();
        $tag = BranchOrder::where( 'order_id', $order->id )->firstOrFail();

        $this->assertSame( (int) $other->id, (int) $tag->branch_id );
        $this->assertSame( BranchOrder::SOURCE_SESSION, $tag->source );
        $this->assertSame( (int) $cashier->id, (int) $tag->author_id );
    }

    public function test_order_falls_back_to_the_default_branch(): void
    {
        $cashier = $this->createStaff( Role::STORECASHIER );
        $this->actingAsUser( $cashier );

        $order = $this->createPaidOrder();
        $tag = BranchOrder::where( 'order_id', $order->id )->firstOrFail();

        $this->assertSame( (int) $this->branchService()->getDefaultBranch()->id, (int) $tag->branch_id );
        $this->assertSame( BranchOrder::SOURCE_DEFAULT, $tag->source );
    }

    public function test_tagging_failure_never_breaks_order_creation(): void
    {
        $this->actingAsUser( $this->admin() );

        $order = new Order;
        $order->id = 999999999;
        $order->register_id = null;
        $order->author_id = 0;

        // an unsaved order: tagging works (default branch) or fails silently, but never throws
        $tag = $this->branchService()->tagOrder( $order );
        $this->assertTrue( $tag === null || (int) $tag->branch_id === (int) $this->branchService()->getDefaultBranch()->id );
        BranchOrder::where( 'order_id', 999999999 )->delete();
    }

    public function test_orders_list_exposes_and_filters_by_branch(): void
    {
        $this->actingAsUser( $this->admin() );

        $branch = $this->createBranch();
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );
        $order = $this->createPaidOrder( [ 'register_id' => $register->id ] );

        $config = ( new OrderCrud )->getCrudConfig();
        $this->assertArrayHasKey( 'mplbranch_name', $config[ 'columns' ] );
        $this->assertContains( 'mplbrorder.branch_id', collect( $config[ 'queryFilters' ] )->pluck( 'name' )->toArray() );

        $response = $this->json( 'GET', 'api/crud/ns.orders?queryFilters=' . urlencode( json_encode( [ 'mplbrorder.branch_id' => $branch->id ] ) ) );
        $response->assertOk();

        $rows = collect( $response->json( 'data' ) );
        $this->assertCount( 1, $rows );
        $this->assertSame( (int) $order->id, (int) $rows->first()[ 'id' ] );
        $this->assertSame( $branch->name, $rows->first()[ 'mplbranch_name' ] );

        $this->json( 'GET', 'api/mplbranches/orders/' . $order->id . '/branch' )
            ->assertOk()
            ->assertJsonPath( 'branch.id', $branch->id );
    }
}
