<?php

/**
 * Registers the WhatsApp receipt permissions and grants them to roles.
 *
 * Permissions and roles are shared (unprefixed) tables, so this migration
 * is repeat-safe across Multistore stores.
 */

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function permissions(): array
    {
        return [
            'mplwhatsapp.send' => [
                __m( 'WhatsApp: Send Receipts', 'MplWhatsApp' ),
                __m( 'Send order receipts to customers through WhatsApp.', 'MplWhatsApp' ),
            ],
            'mplwhatsapp.settings' => [
                __m( 'WhatsApp: Manage Settings', 'MplWhatsApp' ),
                __m( 'Configure the WhatsApp receipt module (sending mode, Cloud API credentials, message template).', 'MplWhatsApp' ),
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ( $this->permissions() as $namespace => [ $name, $description ] ) {
            if ( Permission::where( 'name', $name )->where( 'namespace', '!=', $namespace )->exists() ) {
                throw new RuntimeException( sprintf( 'The permission name "%s" is already assigned to another namespace.', $name ) );
            }

            $permission = Permission::firstOrNew( [ 'namespace' => $namespace ] );
            $permission->name = $name;
            $permission->namespace = $namespace;
            $permission->description = $description;
            $permission->save();
        }

        $admin = Role::namespace( Role::ADMIN );

        if ( $admin instanceof Role ) {
            $admin->addPermissions( [ 'mplwhatsapp.send', 'mplwhatsapp.settings' ], true );
        }

        /**
         * Roles that already sell (create orders) may send receipts.
         */
        foreach ( [ Role::STOREADMIN, Role::STORECASHIER ] as $roleNamespace ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                $role->addPermissions( [ 'mplwhatsapp.send' ], true );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ( array_keys( $this->permissions() ) as $namespace ) {
            $permission = Permission::namespace( $namespace );

            if ( $permission instanceof Permission ) {
                $permission->removeFromRoles();
                $permission->delete();
            }
        }
    }
};
