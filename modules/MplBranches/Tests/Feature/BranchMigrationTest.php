<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Services\BranchService;
use Modules\MplBranches\Tests\MplBranchesTestCase;

final class BranchMigrationTest extends MplBranchesTestCase
{
    public function test_default_branch_is_seeded_once_and_migrations_are_idempotent(): void
    {
        $this->assertSame( 1, Branch::where( 'code', BranchService::DEFAULT_CODE )->count() );
        $this->assertSame( 1, Branch::where( 'is_default', true )->count() );

        $migrations = glob( base_path( 'modules/MplBranches/Migrations/*.php' ) );
        $this->assertNotEmpty( $migrations );

        // re-running every migration must not duplicate data nor fail
        foreach ( [ 1, 2 ] as $run ) {
            foreach ( $migrations as $file ) {
                ( require $file )->up();
            }
        }

        $this->assertSame( 1, Branch::where( 'code', BranchService::DEFAULT_CODE )->count() );
        $this->assertSame( 1, Branch::where( 'is_default', true )->count() );

        foreach ( [ 'mplbranches.manage', 'mplbranches.reports', 'mplbranches.switch' ] as $namespace ) {
            $this->assertSame( 1, Permission::where( 'namespace', $namespace )->count() );
        }
    }

    public function test_permissions_are_granted_to_the_expected_roles(): void
    {
        $permissionsOf = fn ( string $role ) => Role::namespace( $role )->permissions()->pluck( 'namespace' )->toArray();

        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $role ) {
            $this->assertContains( 'mplbranches.manage', $permissionsOf( $role ) );
            $this->assertContains( 'mplbranches.reports', $permissionsOf( $role ) );
            $this->assertContains( 'mplbranches.switch', $permissionsOf( $role ) );
        }

        $this->assertContains( 'mplbranches.switch', $permissionsOf( Role::STORECASHIER ) );
        $this->assertNotContains( 'mplbranches.manage', $permissionsOf( Role::STORECASHIER ) );
        $this->assertNotContains( 'mplbranches.reports', $permissionsOf( Role::STORECASHIER ) );
        $this->assertNotContains( 'mplbranches.manage', $permissionsOf( Role::USER ) );
    }
}
