<?php

/**
 * Creates the Owner App permission and grants it to the
 * administrator and store administrator roles.
 */

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleRelation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * The migration only touches shared permission tables.
     */
    public bool $multistore = false;

    public function up(): void
    {
        $namespace = 'mplownerapp.view';
        $name = __m( 'View Owner Mobile Dashboard', 'MplOwnerApp' );

        $permission = Permission::where( 'namespace', $namespace )->first();

        if ( ! $permission instanceof Permission ) {
            $collision = Permission::where( 'name', $name )->first();

            if ( $collision instanceof Permission ) {
                $name = $name . ' (MplOwnerApp)';
            }

            $permission = new Permission;
            $permission->namespace = $namespace;
            $permission->name = $name;
        }

        $permission->description = __m( 'Let the user open the owner mobile dashboard with sales, profit, stock and registers.', 'MplOwnerApp' );
        $permission->save();

        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                $role->addPermissions( $namespace );

                UserRoleRelation::where( 'role_id', $role->id )->pluck( 'user_id' )->each( fn( $userId ) => Cache::forget( 'ns-all-permissions-' . $userId ) );
            }
        }
    }

    public function down(): void
    {
        $permission = Permission::where( 'namespace', 'mplownerapp.view' )->first();

        if ( $permission instanceof Permission ) {
            foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
                $role = Role::namespace( $roleNamespace );

                if ( $role instanceof Role ) {
                    $role->removePermissions( 'mplownerapp.view' );
                }
            }

            $permission->delete();
        }
    }
};
