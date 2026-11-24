<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use App\Models\Role;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\ReceiptLinkService;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;

final class SendReceiptTest extends MplWhatsAppTestCase
{
    private function sendUrl( int $orderId ): string
    {
        return '/api/mplwhatsapp/orders/' . $orderId . '/send';
    }

    public function test_link_mode_returns_a_wa_me_url_with_the_filled_and_encoded_template(): void
    {
        $this->configure( [
            WhatsAppConfig::MESSAGE_TEMPLATE => "Hi {customer} & welcome to {store}!\nOrder {order} = {total}\n{link}",
            'ns_store_name' => 'Souk Al Bahar',
        ] );

        $order = $this->createPaidOrder( $this->createCustomer( '+971 55 765 4321' ) );
        $admin = $this->actingAsAdmin();

        $response = $this->postJson( $this->sendUrl( $order->id ), [ 'phone' => '050 123 4567' ] );

        $response->assertOk()
            ->assertJsonPath( 'status', 'success' )
            ->assertJsonPath( 'mode', 'link' )
            ->assertJsonPath( 'phone', '971501234567' );

        $url = $response->json( 'url' );
        $this->assertStringStartsWith( 'https://wa.me/971501234567?text=', $url );

        $encoded = substr( $url, strlen( 'https://wa.me/971501234567?text=' ) );
        $this->assertSame( rawurlencode( rawurldecode( $encoded ) ), $encoded, 'The text must be RFC 3986 encoded.' );
        $this->assertStringNotContainsString( ' ', $encoded );
        $this->assertStringNotContainsString( '&', $encoded );

        $text = rawurldecode( $encoded );
        $lines = explode( "\n", $text );

        $this->assertSame( 'Hi Layla Hassan & welcome to Souk Al Bahar!', $lines[ 0 ] );
        $this->assertSame( 'Order ' . $order->code . ' = ' . ns()->currency->define( $order->total ), $lines[ 1 ] );
        $this->assertStringNotContainsString( '{', $text );

        /**
         * The {link} placeholder is a working signed receipt link.
         */
        $parts = parse_url( $lines[ 2 ] );
        $this->assertSame( '/mplwhatsapp/receipt/' . $order->id, $parts[ 'path' ] );
        $this->get( $parts[ 'path' ] . '?' . $parts[ 'query' ] )->assertOk()->assertSee( $order->code );

        $this->assertDatabaseHas( ( new WhatsAppMessage )->getTable(), [
            'order_id' => $order->id,
            'phone' => '971501234567',
            'mode' => 'link',
            'status' => WhatsAppMessage::STATUS_LINK_CREATED,
            'user_id' => $admin->id,
        ] );
    }

    public function test_link_mode_uses_the_customer_phone_by_default(): void
    {
        $order = $this->createPaidOrder( $this->createCustomer( '00971 52 111 2233' ) );
        $this->actingAsAdmin();

        $this->getJson( '/api/mplwhatsapp/orders/' . $order->id )
            ->assertOk()
            ->assertJsonPath( 'customer_phone', '00971 52 111 2233' )
            ->assertJsonPath( 'normalized_phone', '971521112233' )
            ->assertJsonPath( 'order.code', $order->code );

        $this->postJson( $this->sendUrl( $order->id ) )
            ->assertOk()
            ->assertJsonPath( 'phone', '971521112233' );
    }

    public function test_invalid_or_missing_phone_is_a_validation_error(): void
    {
        $this->actingAsAdmin();

        $order = $this->createPaidOrder();
        $this->postJson( $this->sendUrl( $order->id ), [ 'phone' => 'not a phone' ] )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( 'phone' );

        $withoutPhone = $this->createPaidOrder( $this->createCustomer( null ) );
        $this->postJson( $this->sendUrl( $withoutPhone->id ) )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( 'phone' );
    }

    public function test_sending_is_refused_when_the_module_is_disabled(): void
    {
        $order = $this->createPaidOrder();
        $this->configure( [ WhatsAppConfig::ENABLED => 'no' ] );
        $this->actingAsAdmin();

        $this->postJson( $this->sendUrl( $order->id ) )->assertForbidden();
    }

    public function test_cloud_api_success_logs_sent_with_the_provider_message_id(): void
    {
        $this->configureCloudApi();
        $order = $this->createPaidOrder();
        $admin = $this->actingAsAdmin();

        Http::fake( [
            'graph.facebook.com/*' => Http::response( [
                'messaging_product' => 'whatsapp',
                'contacts' => [ [ 'input' => '971501234567', 'wa_id' => '971501234567' ] ],
                'messages' => [ [ 'id' => 'wamid.HBgMOTcxNTAxMjM0NTY3FQIAERgSQ0' ] ],
            ], 200 ),
        ] );

        $this->postJson( $this->sendUrl( $order->id ) )
            ->assertOk()
            ->assertJsonPath( 'status', 'success' )
            ->assertJsonPath( 'mode', 'cloud_api' )
            ->assertJsonPath( 'message_id', 'wamid.HBgMOTcxNTAxMjM0NTY3FQIAERgSQ0' );

        Http::assertSent( function ( Request $request ) use ( $order ) {
            $parameters = $request[ 'template' ][ 'components' ][ 0 ][ 'parameters' ];
            $link = parse_url( $parameters[ 2 ][ 'text' ] );
            parse_str( $link[ 'query' ], $query );

            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/messages'
                && $request->method() === 'POST'
                && $request->hasHeader( 'Authorization', 'Bearer test-permanent-token' )
                && $request[ 'messaging_product' ] === 'whatsapp'
                && $request[ 'to' ] === '971501234567'
                && $request[ 'type' ] === 'template'
                && $request[ 'template' ][ 'name' ] === 'order_receipt'
                && $request[ 'template' ][ 'language' ][ 'code' ] === 'en'
                && $parameters[ 0 ][ 'text' ] === 'Layla Hassan'
                && $parameters[ 1 ][ 'text' ] === $order->code
                && $link[ 'path' ] === '/mplwhatsapp/receipt/' . $order->id
                && app( ReceiptLinkService::class )->isValid( $order, $query[ 'expires' ], $query[ 'signature' ] );
        } );

        $this->assertDatabaseHas( ( new WhatsAppMessage )->getTable(), [
            'order_id' => $order->id,
            'phone' => '971501234567',
            'mode' => 'cloud_api',
            'trigger' => 'manual',
            'status' => 'sent',
            'provider_message_id' => 'wamid.HBgMOTcxNTAxMjM0NTY3FQIAERgSQ0',
            'error' => null,
            'user_id' => $admin->id,
        ] );
    }

    public function test_cloud_api_failure_logs_failed_with_the_provider_error(): void
    {
        $this->configureCloudApi();
        $this->configure( [ WhatsAppConfig::CLOUD_API_VERSION => 'v22.0' ] );
        $order = $this->createPaidOrder();
        $this->actingAsAdmin();

        Http::fake( [
            'graph.facebook.com/*' => Http::response( [
                'error' => [
                    'message' => '(#132001) Template name does not exist in the translation',
                    'type' => 'OAuthException',
                    'code' => 132001,
                    'error_data' => [ 'details' => 'template name (order_receipt) does not exist in en' ],
                ],
            ], 404 ),
        ] );

        $response = $this->postJson( $this->sendUrl( $order->id ) );

        $response->assertStatus( 502 )
            ->assertJsonPath( 'status', 'error' );
        $this->assertStringContainsString( 'Template name does not exist', $response->json( 'message' ) );

        Http::assertSent( fn( Request $request ) => $request->url() === 'https://graph.facebook.com/v22.0/1234567890/messages' );

        $log = WhatsAppMessage::where( 'order_id', $order->id )->latest( 'id' )->firstOrFail();
        $this->assertSame( 'failed', $log->status );
        $this->assertNull( $log->provider_message_id );
        $this->assertStringContainsString( '132001', $log->error );
        $this->assertStringContainsString( 'does not exist in en', $log->error );
    }

    public function test_cloud_api_without_configuration_logs_failed_and_never_calls_meta(): void
    {
        $this->configure( [ WhatsAppConfig::MODE => 'cloud_api' ] );
        $order = $this->createPaidOrder();
        $this->actingAsAdmin();
        Http::fake();

        $response = $this->postJson( $this->sendUrl( $order->id ) );

        $response->assertStatus( 502 );
        $this->assertStringContainsString( 'Access Token', $response->json( 'message' ) );
        Http::assertNothingSent();

        $this->assertSame( 'failed', WhatsAppMessage::where( 'order_id', $order->id )->latest( 'id' )->value( 'status' ) );
    }

    public function test_cloud_api_connection_errors_are_logged(): void
    {
        $this->configureCloudApi();
        $order = $this->createPaidOrder();
        $this->actingAsAdmin();

        Http::fake( fn() => throw new ConnectionException( 'cURL error 6: Could not resolve host' ) );

        $this->postJson( $this->sendUrl( $order->id ) )->assertStatus( 502 );

        $log = WhatsAppMessage::where( 'order_id', $order->id )->latest( 'id' )->firstOrFail();
        $this->assertSame( 'failed', $log->status );
        $this->assertStringContainsString( 'Could not resolve host', $log->error );
    }

    public function test_cashier_role_can_send_receipts_for_its_orders(): void
    {
        $order = $this->createPaidOrder();
        $cashier = $this->userWithRole( Role::STORECASHIER );
        $order->author_id = $cashier->id;
        $order->save();

        Sanctum::actingAs( $cashier, [ '*' ] );

        $this->postJson( $this->sendUrl( $order->id ) )->assertOk()->assertJsonPath( 'mode', 'link' );
    }

    public function test_cashier_cannot_send_receipts_for_orders_of_other_cashiers(): void
    {
        $order = $this->createPaidOrder();
        $cashier = $this->userWithRole( Role::STORECASHIER );

        Sanctum::actingAs( $cashier, [ '*' ] );

        $this->postJson( $this->sendUrl( $order->id ) )->assertNotFound();
        $this->assertFalse( WhatsAppMessage::where( 'order_id', $order->id )->exists() );
    }

    public function test_users_without_the_send_permission_are_denied(): void
    {
        $order = $this->createPaidOrder();
        $user = $this->userWithRole( Role::USER );

        Sanctum::actingAs( $user, [ '*' ] );

        $this->postJson( $this->sendUrl( $order->id ) )->assertForbidden();
        $this->getJson( '/api/mplwhatsapp/orders/' . $order->id )->assertForbidden();
        $this->assertFalse( WhatsAppMessage::where( 'order_id', $order->id )->exists() );
    }

    /**
     * Regression: the send endpoint had no rate limit of its own, so any user
     * with "mplwhatsapp.send" could make the server message arbitrary numbers
     * (Cloud API: billed, and spam can get the business number banned).
     */
    public function test_sending_is_rate_limited_per_user(): void
    {
        $order = $this->createPaidOrder();
        $this->actingAsAdmin();

        for ( $attempt = 1; $attempt <= 10; $attempt++ ) {
            $this->postJson( $this->sendUrl( $order->id ), [ 'phone' => '0501234567' ] )->assertOk();
        }

        $this->postJson( $this->sendUrl( $order->id ), [ 'phone' => '0501234567' ] )->assertStatus( 429 );
        $this->assertSame( 10, WhatsAppMessage::where( 'order_id', $order->id )->count() );

        /**
         * The dedicated limiter does not throttle the other module endpoints.
         */
        $this->getJson( '/api/mplwhatsapp/orders/' . $order->id )->assertOk();
    }

    public function test_guests_are_rejected(): void
    {
        $order = $this->createPaidOrder();

        $this->postJson( $this->sendUrl( $order->id ) )->assertUnauthorized();
    }
}
