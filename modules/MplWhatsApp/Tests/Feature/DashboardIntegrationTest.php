<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use App\Models\Role;
use Laravel\Sanctum\Sanctum;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;

final class DashboardIntegrationTest extends MplWhatsAppTestCase
{
    public function test_orders_crud_rows_expose_the_send_via_whatsapp_action(): void
    {
        $order = $this->createPaidOrder();
        $this->actingAsAdmin();

        $rows = collect( $this->getJson( '/api/crud/ns.orders' )->assertOk()->json( 'data' ) );
        $row = $rows->firstWhere( 'id', $order->id );

        $this->assertNotNull( $row, 'The new order is listed.' );
        $this->assertArrayHasKey( 'mplwhatsapp.send', $row[ '$actions' ] );
        $this->assertSame( 'POPUP', $row[ '$actions' ][ 'mplwhatsapp.send' ][ 'type' ] );
    }

    public function test_the_row_action_is_hidden_when_disabled_or_without_permission(): void
    {
        $order = $this->createPaidOrder();
        $this->configure( [ WhatsAppConfig::ENABLED => 'no' ] );
        $this->actingAsAdmin();

        $row = collect( $this->getJson( '/api/crud/ns.orders' )->json( 'data' ) )->firstWhere( 'id', $order->id );
        $this->assertArrayNotHasKey( 'mplwhatsapp.send', $row[ '$actions' ] );

        $this->configure( [ WhatsAppConfig::ENABLED => 'yes' ] );
        $storeAdmin = $this->userWithRole( Role::STOREADMIN );
        Role::namespace( Role::STOREADMIN )->removePermissions( 'mplwhatsapp.send' );

        try {
            Sanctum::actingAs( $storeAdmin->fresh(), [ '*' ] );
            $response = $this->getJson( '/api/crud/ns.orders' );

            if ( $response->status() === 200 ) {
                $row = collect( $response->json( 'data' ) )->firstWhere( 'id', $order->id );
                $this->assertTrue( $row === null || ! isset( $row[ '$actions' ][ 'mplwhatsapp.send' ] ) );
            }
        } finally {
            Role::namespace( Role::STOREADMIN )->addPermissions( 'mplwhatsapp.send' );
        }
    }

    public function test_orders_list_page_loads_the_module_script_and_styles(): void
    {
        $this->actingAs( $this->admin() );

        $this->get( '/dashboard/orders' )
            ->assertOk()
            ->assertSee( 'window.MplWhatsAppConfig', false )
            ->assertSee( 'modules/mplwhatsapp/build/assets/orders-', false )
            ->assertSee( 'modules/mplwhatsapp/build/assets/style-', false );
    }

    public function test_pos_page_loads_the_module_script_and_styles_only_there(): void
    {
        $this->actingAs( $this->admin() );

        $this->get( '/dashboard/pos' )
            ->assertOk()
            ->assertSee( 'modules/mplwhatsapp/build/assets/pos-', false )
            ->assertSee( 'modules/mplwhatsapp/build/assets/style-', false )
            ->assertDontSee( 'modules/mplwhatsapp/build/assets/orders-', false );

        $this->get( '/dashboard/mplwhatsapp/settings' )
            ->assertOk()
            ->assertDontSee( 'modules/mplwhatsapp/build/assets/pos-', false );
    }

    public function test_messages_are_stored_in_the_module_prefixed_table(): void
    {
        $this->assertSame( 'mpl_wa_messages', ( new WhatsAppMessage )->getTable() );
    }
}
