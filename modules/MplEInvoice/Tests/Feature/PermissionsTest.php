<?php

namespace Modules\MplEInvoice\Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

class PermissionsTest extends EInvoiceTestCase
{
    private function userWithoutModulePermissions(): User
    {
        $user = new User;
        $user->username = 'mpleinv-' . Str::random( 8 );
        $user->email = $user->username . '@example.test';
        $user->password = Hash::make( Str::random( 16 ) );
        $user->active = true;
        $user->save();
        $user->assignRole( Role::STORECASHIER );

        $this->assertFalse( $user->allowedTo( [ 'mpleinvoice.read' ] ) );

        return $user;
    }

    public function test_admin_roles_receive_the_module_permissions(): void
    {
        foreach ( [ Role::ADMIN, Role::STOREADMIN ] as $roleNamespace ) {
            $permissions = Role::namespace( $roleNamespace )->permissions()->pluck( 'namespace' )->all();

            $this->assertContains( 'mpleinvoice.read', $permissions );
            $this->assertContains( 'mpleinvoice.submit', $permissions );
            $this->assertContains( 'mpleinvoice.manage-settings', $permissions );
        }
    }

    public function test_admin_can_list_the_documents_crud(): void
    {
        $this->attemptAuthenticate();

        $this->json( 'GET', 'api/crud/mpleinvoice.documents' )->assertOk();
    }

    public function test_user_without_permission_is_denied(): void
    {
        $this->attemptAuthenticate( $this->userWithoutModulePermissions() );

        $this->json( 'GET', 'api/crud/mpleinvoice.documents' )->assertForbidden();
        $this->json( 'GET', 'api/mpl-einvoice/documents/1/submit' )->assertForbidden();
        $this->json( 'POST', 'api/mpl-einvoice/documents/1/submit' )->assertForbidden();
        $this->json( 'GET', 'api/mpl-einvoice/documents/1/xml' )->assertForbidden();
        $this->json( 'GET', 'api/mpl-einvoice/settings' )->assertForbidden();
        $this->json( 'POST', 'api/mpl-einvoice/settings', [] )->assertForbidden();
        $this->json( 'POST', 'api/mpl-einvoice/orders/1/generate' )->assertForbidden();
    }
}
