<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Models\Role;
use Modules\MplBranches\Crud\BranchCrud;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchPermissionTest extends MplBranchesTestCase
{
    public function test_cashier_cannot_manage_branches_nor_see_reports(): void
    {
        $cashier = $this->createStaff( Role::STORECASHIER );
        $this->actingAsUser( $cashier );

        $this->json( 'GET', 'api/crud/' . BranchCrud::IDENTIFIER )->assertForbidden();

        $response = $this->json( 'POST', 'api/crud/' . BranchCrud::IDENTIFIER, [
            'name' => 'Forbidden branch',
            'general' => [ 'code' => 'FORBIDDEN-' . $cashier->id, 'active' => 1, 'is_default' => 0 ],
        ] );
        $response->assertForbidden();
        $this->assertFalse( Branch::where( 'code', 'FORBIDDEN-' . $cashier->id )->exists() );

        $branch = $this->createBranch();
        $this->json( 'DELETE', 'api/crud/' . BranchCrud::IDENTIFIER . '/' . $branch->id )->assertForbidden();
        $this->assertNotNull( Branch::find( $branch->id ) );

        $this->json( 'GET', 'api/mplbranches/reports/performance?period=today' )->assertForbidden();
    }

    public function test_user_without_pos_role_cannot_switch_branch(): void
    {
        $user = $this->createStaff( Role::USER );
        $branch = $this->createBranch();
        $this->branchService()->syncBranchUsers( $branch, [ $user->id ] );

        $this->actingAsUser( $user );

        $this->json( 'POST', 'api/mplbranches/switch', [ 'branch_id' => $branch->id ] )->assertForbidden();
        $this->json( 'GET', 'api/mplbranches/branches/mine' )->assertForbidden();
    }

    public function test_store_admin_can_manage_branches_and_see_reports(): void
    {
        $storeAdmin = $this->createStaff( Role::STOREADMIN );
        $this->actingAsUser( $storeAdmin );

        $this->json( 'GET', 'api/crud/' . BranchCrud::IDENTIFIER )->assertOk();
        $this->json( 'GET', 'api/mplbranches/reports/performance?period=this_week' )->assertOk();
    }

    public function test_guest_is_rejected(): void
    {
        $this->json( 'GET', 'api/mplbranches/reports/performance' )->assertUnauthorized();
        $this->get( 'dashboard/mplbranches/branches' )->assertRedirect();
    }
}
