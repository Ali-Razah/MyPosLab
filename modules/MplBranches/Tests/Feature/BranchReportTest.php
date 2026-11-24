<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Models\Order;
use App\Models\OrderProduct;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchReportTest extends MplBranchesTestCase
{
    public function test_branch_performance_totals_match_the_seeded_orders(): void
    {
        $this->actingAsUser( $this->admin() );

        $dubai = $this->createBranch( [ 'emirate' => 'Dubai' ] );
        $sharjah = $this->createBranch( [ 'emirate' => 'Sharjah' ] );
        $dubaiRegister = $this->createRegister();
        $sharjahRegister = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $dubai, [ $dubaiRegister->id ] );
        $this->branchService()->syncBranchRegisters( $sharjah, [ $sharjahRegister->id ] );

        $dubaiOrders = collect( [
            $this->createPaidOrder( [ 'register_id' => $dubaiRegister->id ], 1 ),
            $this->createPaidOrder( [ 'register_id' => $dubaiRegister->id ], 3 ),
        ] );
        $sharjahOrders = collect( [
            $this->createPaidOrder( [ 'register_id' => $sharjahRegister->id ], 2 ),
        ] );

        $response = $this->json( 'GET', 'api/mplbranches/reports/performance?period=today' );
        $response->assertOk();

        $rows = collect( $response->json( 'rows' ) )->keyBy( 'branch_id' );

        foreach ( [ [ $dubai, $dubaiOrders ], [ $sharjah, $sharjahOrders ] ] as [ $branch, $orders ] ) {
            $orders = $orders->map( fn ( Order $order ) => $order->fresh() );
            $row = $rows->get( $branch->id );
            $expectedSales = $orders->sum( 'total' );
            $expectedProfit = $orders->sum( function ( Order $order ) {
                $products = OrderProduct::where( 'order_id', $order->id );

                return $order->total - ( clone $products )->sum( 'total_purchase_price' ) - $order->tax_value - ( clone $products )->sum( 'tax_value' );
            } );

            $this->assertNotNull( $row, 'Missing report row for ' . $branch->name );
            $this->assertSame( $orders->count(), $row[ 'orders_count' ] );
            $this->assertEqualsWithDelta( $expectedSales, $row[ 'sales_total' ], 0.01 );
            $this->assertEqualsWithDelta( $expectedSales / $orders->count(), $row[ 'average_ticket' ], 0.01 );
            $this->assertEqualsWithDelta( $expectedProfit, $row[ 'gross_profit' ], 0.01 );
            $this->assertEqualsWithDelta( 0, $row[ 'refunds' ], 0.01 );
        }

        $total = $response->json( 'total' );
        $this->assertSame( collect( $response->json( 'rows' ) )->sum( 'orders_count' ), $total[ 'orders_count' ] );
        $this->assertEqualsWithDelta( collect( $response->json( 'rows' ) )->sum( 'sales_total' ), $total[ 'sales_total' ], 0.01 );

        // yesterday has none of these orders
        $yesterday = collect( $this->json( 'GET', 'api/mplbranches/reports/performance?period=yesterday' )->assertOk()->json( 'rows' ) )->keyBy( 'branch_id' );
        $this->assertSame( 0, $yesterday->get( $dubai->id )[ 'orders_count' ] );

        // custom range including today
        $today = ns()->date->toDateString();
        $custom = collect( $this->json( 'GET', 'api/mplbranches/reports/performance?period=custom&start=' . $today . '&end=' . $today )->assertOk()->json( 'rows' ) )->keyBy( 'branch_id' );
        $this->assertSame( 1, $custom->get( $sharjah->id )[ 'orders_count' ] );

        // invalid custom range
        $this->json( 'GET', 'api/mplbranches/reports/performance?period=custom' )->assertForbidden();
    }

    public function test_report_page_renders(): void
    {
        $admin = $this->admin();
        $branch = $this->createBranch();

        $this->actingAs( $admin );

        $this->get( 'dashboard/mplbranches/reports/performance?period=this_month' )
            ->assertOk()
            ->assertSee( $branch->name );
    }
}
