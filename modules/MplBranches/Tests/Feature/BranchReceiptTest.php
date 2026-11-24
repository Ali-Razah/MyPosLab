<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Models\Order;
use App\Services\OrdersService;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchReceiptTest extends MplBranchesTestCase
{
    public function test_receipt_shows_the_branch_details(): void
    {
        $admin = $this->admin();
        $this->actingAsUser( $admin );

        $branch = $this->createBranch( [
            'name' => 'Al Qasimia Grocery',
            'address' => 'King Faisal St',
            'phone' => '+971 6 123 4567',
            'trn' => '100200300400500',
            'receipt_footer' => 'Exchange within 7 days',
        ] );
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );
        $order = $this->createPaidOrder( [ 'register_id' => $register->id ] );

        $this->actingAs( $admin );

        $this->get( 'dashboard/orders/receipt/' . $order->id . '?dash-visibility=disabled' )
            ->assertOk()
            ->assertSee( 'Al Qasimia Grocery' )
            ->assertSee( '100200300400500' )
            ->assertSee( 'Exchange within 7 days' );

        ns()->option->set( 'mplbr_test_template', 'Branch: {branch_name} TRN {branch_trn}' );
        $mapped = app()->make( OrdersService::class )->orderTemplateMapping( 'mplbr_test_template', Order::with( [ 'user', 'customer', 'shipping_address', 'billing_address' ] )->find( $order->id ) );
        ns()->option->delete( 'mplbr_test_template' );

        $this->assertSame( 'Branch: Al Qasimia Grocery TRN 100200300400500', $mapped );
    }

    /**
     * The receipt column templates are rendered unescaped by the core: branch
     * values injected through {branch_*} tags must be HTML escaped.
     */
    public function test_branch_template_tags_are_html_escaped(): void
    {
        $this->actingAsUser( $this->admin() );

        $branch = $this->createBranch( [ 'name' => 'Deira <script>alert(1)</script>', 'receipt_header' => 'Tom & Jerry' ] );
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );
        $order = $this->createPaidOrder( [ 'register_id' => $register->id ] );

        ns()->option->set( 'mplbr_test_template', '{branch_name} | {branch_receipt_header}' );
        $mapped = app()->make( OrdersService::class )->orderTemplateMapping( 'mplbr_test_template', Order::with( [ 'user', 'customer', 'shipping_address', 'billing_address' ] )->find( $order->id ) );
        ns()->option->delete( 'mplbr_test_template' );

        $this->assertSame( 'Deira &lt;script&gt;alert(1)&lt;/script&gt; | Tom &amp; Jerry', $mapped );
    }
}
