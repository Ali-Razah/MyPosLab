<?php

/**
 * Creates the Customer Display permissions and assigns them to roles.
 *
 * Permissions and roles are shared (unprefixed) tables, so this migration is
 * safe to run in every Multistore store context.
 */

namespace Modules\MplCustomerDisplay\Migrations;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use RuntimeException;

return new class extends Migration
{
    /**
     * Permission namespaces mapped to [name, description].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function permissions(): array
    {
        return [
            'mplcustomerdisplay.view' => [
                __m( 'Open Customer Display', 'MplCustomerDisplay' ),
                __m( 'Allows opening the customer-facing second screen from the POS.', 'MplCustomerDisplay' ),
            ],
            'mplcustomerdisplay.settings' => [
                __m( 'Manage Customer Display Settings', 'MplCustomerDisplay' ),
                __m( 'Allows changing the customer display welcome message, slideshow and theme.', 'MplCustomerDisplay' ),
            ],
        ];
    }

    /**
     * Role namespaces mapped to the permissions they receive.
     *
     * @return array<string, string[]>
     */
    protected function assignments(): array
    {
        return [
            Role::ADMIN => [ 'mplcustomerdisplay.view', 'mplcustomerdisplay.settings' ],
            Role::STOREADMIN => [ 'mplcustomerdisplay.view' ],
            Role::STORECASHIER => [ 'mplcustomerdisplay.view' ],
        ];
    }

    public function up(): void
    {
        foreach ( $this->permissions() as $namespace => [ $name, $description ] ) {
            $permission = Permission::where( 'namespace', $namespace )->first();

            if ( ! $permission instanceof Permission ) {
                $collision = Permission::where( 'name', $name )->first();

                if ( $collision instanceof Permission ) {
                    throw new RuntimeException( sprintf(
                        'Unable to create the permission "%s": the name "%s" is already used by "%s".',
                        $namespace,
                        $name,
                        $collision->namespace
                    ) );
                }

                $permission = new Permission;
                $permission->namespace = $namespace;
            }

            $permission->name = $name;
            $permission->description = $description;
            $permission->save();
        }

        foreach ( $this->assignments() as $roleNamespace => $permissions ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                $role->addPermissions( $permissions, true );
            }
        }
    }

    public function down(): void
    {
        foreach ( $this->assignments() as $roleNamespace => $permissions ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                foreach ( $permissions as $permission ) {
                    if ( Permission::where( 'namespace', $permission )->exists() ) {
                        $role->removePermissions( $permission );
                    }
                }
            }
        }

        Permission::whereIn( 'namespace', array_keys( $this->permissions() ) )->delete();
    }
};
