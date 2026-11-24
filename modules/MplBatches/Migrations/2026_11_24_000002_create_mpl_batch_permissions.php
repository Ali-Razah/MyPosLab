<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Permissions and roles are shared (unprefixed) tables.
     */
    public bool $multistore = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'mplbatches.read' => [
                'name' => __m( 'Read Batches & Expiry', 'MplBatches' ),
                'description' => __m( 'Can see batches, their allocations and the expiry report.', 'MplBatches' ),
            ],
            'mplbatches.manage' => [
                'name' => __m( 'Manage Batches & Expiry', 'MplBatches' ),
                'description' => __m( 'Can edit batch numbers and expiry dates and write off batches.', 'MplBatches' ),
            ],
            'mplbatches.settings' => [
                'name' => __m( 'Batches & Expiry Settings', 'MplBatches' ),
                'description' => __m( 'Can open the Batches & Expiry settings page.', 'MplBatches' ),
            ],
        ];

        foreach ( $permissions as $namespace => $details ) {
            $permission = Permission::namespace( $namespace );

            if ( ! $permission instanceof Permission ) {
                $name = $details[ 'name' ];

                if ( Permission::where( 'name', $name )->exists() ) {
                    $name = $name . ' [' . $namespace . ']';
                }

                $permission = new Permission;
                $permission->name = $name;
                $permission->namespace = $namespace;
                $permission->description = $details[ 'description' ];
                $permission->save();
            }
        }

        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                $role->addPermissions( array_keys( $permissions ) );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ( [ 'mplbatches.read', 'mplbatches.manage', 'mplbatches.settings' ] as $namespace ) {
            $permission = Permission::namespace( $namespace );

            if ( $permission instanceof Permission ) {
                $permission->removeFromRoles();
                $permission->delete();
            }
        }
    }
};
