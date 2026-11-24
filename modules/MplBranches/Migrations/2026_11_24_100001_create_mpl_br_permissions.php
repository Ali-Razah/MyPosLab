<?php

/**
 * Registers the MplBranches permissions and grants them to the operational roles.
 */

namespace Modules\MplBranches\Migrations;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use RuntimeException;

return new class extends Migration
{
    /**
     * Permission namespace => [ name, description, roles ].
     */
    private function definitions(): array
    {
        return [
            'mplbranches.manage' => [
                'name' => __m( 'Manage Branches', 'MplBranches' ),
                'description' => __m( 'Create, edit and delete branches and assign users and cash registers to them.', 'MplBranches' ),
                'roles' => [ Role::ADMIN, Role::STOREADMIN ],
            ],
            'mplbranches.reports' => [
                'name' => __m( 'See Branch Reports', 'MplBranches' ),
                'description' => __m( 'Access the branch performance report.', 'MplBranches' ),
                'roles' => [ Role::ADMIN, Role::STOREADMIN ],
            ],
            'mplbranches.switch' => [
                'name' => __m( 'Switch Current Branch', 'MplBranches' ),
                'description' => __m( 'Switch the current working branch among the branches assigned to the user.', 'MplBranches' ),
                'roles' => [ Role::ADMIN, Role::STOREADMIN, Role::STORECASHIER ],
            ],
        ];
    }

    public function up(): void
    {
        foreach ( $this->definitions() as $namespace => $definition ) {
            $permission = Permission::where( 'namespace', $namespace )->first();

            if ( ! $permission instanceof Permission ) {
                $nameTaken = Permission::where( 'name', $definition[ 'name' ] )->exists();

                if ( $nameTaken ) {
                    throw new RuntimeException( sprintf( 'MplBranches: the permission name "%s" is already used by another permission.', $definition[ 'name' ] ) );
                }

                $permission = new Permission;
                $permission->namespace = $namespace;
                $permission->name = $definition[ 'name' ];
            }

            $permission->description = $definition[ 'description' ];
            $permission->save();

            foreach ( $definition[ 'roles' ] as $roleNamespace ) {
                $role = Role::namespace( $roleNamespace );

                if ( $role instanceof Role ) {
                    $role->addPermissions( $namespace );
                }
            }
        }

        /**
         * Any other role able to sell on the POS should be able to switch branch.
         */
        Role::whereHas( 'permissions', fn ( $query ) => $query->where( 'namespace', 'nexopos.create.orders' ) )
            ->get()
            ->each( fn ( Role $role ) => $role->addPermissions( 'mplbranches.switch' ) );
    }

    public function down(): void
    {
        foreach ( array_keys( $this->definitions() ) as $namespace ) {
            $permission = Permission::where( 'namespace', $namespace )->first();

            if ( $permission instanceof Permission ) {
                $permission->removeFromRoles();
                $permission->delete();
            }
        }
    }
};
