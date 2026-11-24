<?php

/**
 * Registers the e-invoicing permissions and grants them to the
 * administrator roles. Permissions and roles are shared (non store-prefixed)
 * tables, so this migration only runs on the root installation.
 */

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public bool $multistore = false;

    /**
     * @var array<string, array{name: string, description: string}>
     */
    private array $permissions = [];

    public function __construct()
    {
        $this->permissions = [
            'mpleinvoice.read' => [
                'name' => __m( 'MplEInvoice: Read E-Invoices', 'MplEInvoice' ),
                'description' => __m( 'Can list, download and print UAE e-invoices.', 'MplEInvoice' ),
            ],
            'mpleinvoice.submit' => [
                'name' => __m( 'MplEInvoice: Submit E-Invoices', 'MplEInvoice' ),
                'description' => __m( 'Can submit or retry the submission of e-invoices to the Accredited Service Provider.', 'MplEInvoice' ),
            ],
            'mpleinvoice.manage-settings' => [
                'name' => __m( 'MplEInvoice: Manage Settings', 'MplEInvoice' ),
                'description' => __m( 'Can configure the seller tax identity and the ASP integration.', 'MplEInvoice' ),
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ( $this->permissions as $namespace => $details ) {
            $permission = Permission::where( 'namespace', $namespace )->first();

            if ( ! $permission instanceof Permission ) {
                $name = $details[ 'name' ];

                if ( Permission::where( 'name', $name )->exists() ) {
                    $name = $name . ' (' . $namespace . ')';
                }

                if ( Permission::where( 'name', $name )->exists() ) {
                    throw new RuntimeException( sprintf( 'Unable to create the permission "%s": its name is already used.', $namespace ) );
                }

                $permission = new Permission;
                $permission->namespace = $namespace;
                $permission->name = $name;
                $permission->description = $details[ 'description' ];
                $permission->save();
            }
        }

        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
            $role = Role::namespace( $roleNamespace );

            if ( $role instanceof Role ) {
                $role->addPermissions( array_keys( $this->permissions ) );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ( array_keys( $this->permissions ) as $namespace ) {
            $permission = Permission::where( 'namespace', $namespace )->first();

            if ( $permission instanceof Permission ) {
                $permission->removeFromRoles();
                $permission->delete();
            }
        }
    }
};
