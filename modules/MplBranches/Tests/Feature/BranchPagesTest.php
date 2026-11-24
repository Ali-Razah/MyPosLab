<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Services\MenuService;
use Modules\MplBranches\Crud\BranchCrud;
use Modules\MplBranches\Models\BranchOrder;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchPagesTest extends MplBranchesTestCase
{
    public function test_branch_management_pages_render(): void
    {
        $admin = $this->admin();
        $branch = $this->createBranch();

        $this->actingAs( $admin );

        $this->get( 'dashboard/mplbranches/branches' )->assertOk();
        $this->get( 'dashboard/mplbranches/branches/create' )->assertOk();
        $this->get( 'dashboard/mplbranches/branches/edit/' . $branch->id )->assertOk();

        $form = $this->json( 'GET', 'api/crud/' . BranchCrud::IDENTIFIER . '/form-config/' . $branch->id )->assertOk();
        $fields = collect( $form->json( 'form.tabs' ) )->flatMap( fn ( $tab ) => $tab[ 'fields' ] );

        $this->assertContains( 'user_ids', $fields->pluck( 'name' ) );
        $this->assertContains( 'register_ids', $fields->pluck( 'name' ) );
        $fields->each( fn ( $field ) => $this->assertNotEmpty( $field[ 'description' ] ?? null, 'Missing description for ' . $field[ 'name' ] ) );
    }

    public function test_menus_expose_branches_and_current_branch(): void
    {
        $this->actingAs( $this->admin() );

        $menuService = app()->make( MenuService::class );

        $this->assertArrayHasKey( 'mplbranches', $menuService->getMenus() );
        $this->assertArrayHasKey( 'mplbranches-current', $menuService->getAccountMenus() );
    }

    public function test_register_assigned_after_creation_moves_the_order_to_the_register_branch(): void
    {
        $this->actingAsUser( $this->admin() );

        $branch = $this->createBranch();
        $register = $this->createRegister();
        $this->branchService()->syncBranchRegisters( $branch, [ $register->id ] );

        $order = $this->createPaidOrder();
        $this->assertNotSame( (int) $branch->id, (int) BranchOrder::where( 'order_id', $order->id )->value( 'branch_id' ) );

        // the core assigns the register when a later payment is recorded (OrdersService::persistOrderSinglePayment)
        $order->register_id = $register->id;
        $order->save();

        $tag = BranchOrder::where( 'order_id', $order->id )->firstOrFail();

        $this->assertSame( (int) $branch->id, (int) $tag->branch_id );
        $this->assertSame( BranchOrder::SOURCE_REGISTER, $tag->source );
    }
}
