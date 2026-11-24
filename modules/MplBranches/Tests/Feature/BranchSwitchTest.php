<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Models\Role;
use Modules\MplBranches\Services\BranchService;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchSwitchTest extends MplBranchesTestCase
{
    public function test_staff_can_switch_only_to_assigned_branches(): void
    {
        $cashier = $this->createStaff( Role::STORECASHIER );
        $assigned = $this->createBranch();
        $notAssigned = $this->createBranch();
        $this->branchService()->syncBranchUsers( $assigned, [ $cashier->id ] );

        $this->actingAsUser( $cashier );

        $this->json( 'GET', 'api/mplbranches/branches/mine' )
            ->assertOk()
            ->assertJsonPath( 'current.id', $assigned->id )
            ->assertJsonCount( 1, 'branches' );

        $response = $this->json( 'POST', 'api/mplbranches/switch', [ 'branch_id' => $notAssigned->id ] );
        $response->assertForbidden();
        $this->assertNotSame( (int) $notAssigned->id, (int) session( BranchService::SESSION_KEY ) );
        $this->assertSame( (int) $assigned->id, (int) $this->branchService()->getCurrentBranch( $cashier )->id );

        $this->json( 'POST', 'api/mplbranches/switch', [ 'branch_id' => $assigned->id ] )->assertOk();
        $this->assertSame( (int) $assigned->id, (int) session( BranchService::SESSION_KEY ) );
    }

    public function test_inactive_branch_cannot_be_selected(): void
    {
        $cashier = $this->createStaff( Role::STORECASHIER );
        $inactive = $this->createBranch( [ 'active' => false ] );
        $this->branchService()->syncBranchUsers( $inactive, [ $cashier->id ] );

        $this->actingAsUser( $cashier );

        $response = $this->json( 'POST', 'api/mplbranches/switch', [ 'branch_id' => $inactive->id ] );
        $response->assertForbidden();

        // no active assignment: the default branch is the current branch
        $this->assertSame( (int) $this->branchService()->getDefaultBranch()->id, (int) $this->branchService()->getCurrentBranch( $cashier )->id );
    }

    public function test_admin_can_switch_to_any_active_branch(): void
    {
        $admin = $this->admin();
        $branch = $this->createBranch();

        $this->actingAsUser( $admin );

        $this->json( 'POST', 'api/mplbranches/switch', [ 'branch_id' => $branch->id ] )->assertOk();
        $this->assertSame( (int) $branch->id, (int) $this->branchService()->getCurrentBranch( $admin )->id );
    }

    public function test_switch_page_renders_and_posts(): void
    {
        $cashier = $this->createStaff( Role::STORECASHIER );
        $branch = $this->createBranch();
        $this->branchService()->syncBranchUsers( $branch, [ $cashier->id ] );

        $this->actingAs( $cashier );

        $page = $this->get( 'dashboard/mplbranches/switch' );
        $this->assertSame( 200, $page->status(), substr( (string) $page->getContent(), 0, 3000 ) );
        $page->assertSee( $branch->name );

        $this->withSession( [ '_token' => 'mplbr-token' ] )
            ->post( 'dashboard/mplbranches/switch', [ 'branch_id' => $branch->id, '_token' => 'mplbr-token' ] )
            ->assertRedirect();
        $this->assertSame( (int) $branch->id, (int) session( BranchService::SESSION_KEY ) );
    }
}
