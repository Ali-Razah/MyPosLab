<?php

namespace Modules\MplCustomerDisplay\Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;
use Modules\MplCustomerDisplay\Tests\TestCase;

class CustomerDisplayPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get( ns()->route( 'mplcd.display' ) )
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createUserWithRole( Role::USER );

        $this->actingAs( $user )
            ->get( ns()->route( 'mplcd.display' ) )
            ->assertForbidden();
    }

    public function test_admin_gets_the_standalone_display_page(): void
    {
        $response = $this->actingAs( $this->adminUser() )
            ->get( ns()->route( 'mplcd.display' ) );

        $response->assertOk()
            ->assertSee( 'id="mplcd-display-root"', false )
            ->assertSee( 'window.MplCustomerDisplay', false )
            ->assertSee( CustomerDisplayConfig::CHANNEL, false )
            ->assertDontSee( 'id="dashboard-aside"', false )
            ->assertDontSee( '<ns-pos', false );

        $this->assertMatchesRegularExpression( '#modules/mplcustomerdisplay/build/assets/display-[^"\']+\.js#', $response->getContent() );
        $this->assertMatchesRegularExpression( '#modules/mplcustomerdisplay/build/assets/style-[^"\']+\.css#', $response->getContent() );
    }

    /**
     * Regression: the channel name was a global constant, so with Multistore
     * the POS of one store and the display of another store opened in the
     * same browser (same origin) shared their carts.
     */
    public function test_the_channel_is_scoped_to_the_active_store(): void
    {
        $config = app()->make( CustomerDisplayConfig::class );
        $previous = ns()->storeID;

        try {
            ns()->storeID = null;
            $root = $config->forPos()[ 'channel' ];
            $this->assertSame( $root, $config->forDisplay()[ 'channel' ] );

            ns()->storeID = 7;
            $storeSeven = $config->forPos()[ 'channel' ];
            $this->assertSame( $storeSeven, $config->forDisplay()[ 'channel' ] );

            ns()->storeID = 8;
            $storeEight = $config->forDisplay()[ 'channel' ];
        } finally {
            ns()->storeID = $previous;
        }

        $this->assertNotSame( $root, $storeSeven );
        $this->assertNotSame( $storeSeven, $storeEight );
    }

    public function test_store_cashier_can_open_the_display(): void
    {
        $this->actingAs( $this->createUserWithRole( Role::STORECASHIER ) )
            ->get( ns()->route( 'mplcd.display' ) )
            ->assertOk()
            ->assertSee( 'id="mplcd-display-root"', false );
    }

    public function test_display_page_uses_the_configured_theme_and_messages(): void
    {
        ns()->option->set( CustomerDisplayConfig::OPTION_THEME, 'light' );
        ns()->option->set( CustomerDisplayConfig::OPTION_WELCOME_EN, 'Hello from the test shop' );
        ns()->option->set( CustomerDisplayConfig::OPTION_PROMO_IMAGES, "https://cdn.example.test/a.png\njavascript:alert(1)\n/images/b.png" );

        $response = $this->actingAs( $this->adminUser() )
            ->get( ns()->route( 'mplcd.display' ) )
            ->assertOk()
            ->assertSee( 'class="light"', false )
            ->assertSee( 'Hello from the test shop', false );

        $content = $response->getContent();

        $this->assertStringContainsString( 'https:\/\/cdn.example.test\/a.png', $content );
        $this->assertStringContainsString( '\/images\/b.png', $content );
        $this->assertStringNotContainsString( 'javascript:alert', $content );
    }

    public function test_permissions_are_granted_to_the_expected_roles(): void
    {
        $admin = Role::namespace( Role::ADMIN );
        $cashier = Role::namespace( Role::STORECASHIER );
        $user = Role::namespace( Role::USER );

        $adminPermissions = $admin->permissions()->pluck( 'namespace' )->all();
        $cashierPermissions = $cashier->permissions()->pluck( 'namespace' )->all();
        $userPermissions = $user->permissions()->pluck( 'namespace' )->all();

        $this->assertContains( 'mplcustomerdisplay.view', $adminPermissions );
        $this->assertContains( 'mplcustomerdisplay.settings', $adminPermissions );
        $this->assertContains( 'mplcustomerdisplay.view', $cashierPermissions );
        $this->assertNotContains( 'mplcustomerdisplay.settings', $cashierPermissions );
        $this->assertNotContains( 'mplcustomerdisplay.view', $userPermissions );
    }
}
