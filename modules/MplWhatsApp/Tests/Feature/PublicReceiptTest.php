<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Modules\MplWhatsApp\Services\ReceiptLinkService;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;

final class PublicReceiptTest extends MplWhatsAppTestCase
{
    private function relative( string $url ): string
    {
        $parts = parse_url( $url );

        return $parts[ 'path' ] . '?' . $parts[ 'query' ];
    }

    public function test_signed_link_renders_the_receipt_without_authentication(): void
    {
        $order = $this->createPaidOrder();
        $url = app( ReceiptLinkService::class )->make( $order );

        Auth::logout();
        $this->assertGuest();

        $response = $this->get( $this->relative( $url ) );

        $response->assertOk();
        $response->assertSee( $order->code );
        $response->assertSee( (string) ns()->currency->define( $order->total ) );
        $response->assertSee( 'Layla Hassan' );
        $response->assertHeader( 'Referrer-Policy', 'no-referrer' );
    }

    public function test_tampered_signature_is_forbidden(): void
    {
        $order = $this->createPaidOrder();
        $url = app( ReceiptLinkService::class )->make( $order );

        $this->get( $this->relative( $url ) . 'x' )->assertForbidden();
        $this->get( '/mplwhatsapp/receipt/' . $order->id )->assertForbidden();
    }

    public function test_a_signature_cannot_open_another_order(): void
    {
        $first = $this->createPaidOrder();
        $second = $this->createPaidOrder();
        $url = $this->relative( app( ReceiptLinkService::class )->make( $first ) );

        $tampered = str_replace( '/mplwhatsapp/receipt/' . $first->id . '?', '/mplwhatsapp/receipt/' . $second->id . '?', $url );

        $this->assertNotSame( $url, $tampered );
        $this->get( $tampered )->assertForbidden()->assertDontSee( $second->code );
    }

    public function test_tampered_expiry_is_forbidden(): void
    {
        $order = $this->createPaidOrder();
        $url = $this->relative( app( ReceiptLinkService::class )->make( $order ) );

        $tampered = preg_replace( '/expires=\d+/', 'expires=' . now()->addYears( 5 )->getTimestamp(), $url );

        $this->get( $tampered )->assertForbidden();
    }

    public function test_expired_link_is_forbidden(): void
    {
        $this->configure( [ WhatsAppConfig::LINK_VALIDITY_DAYS => 2 ] );

        $order = $this->createPaidOrder();
        $url = $this->relative( app( ReceiptLinkService::class )->make( $order ) );

        $this->travel( 1 )->days();
        $this->get( $url )->assertOk();

        $this->travel( 2 )->days();
        $this->get( $url )->assertForbidden()->assertDontSee( $order->code );
    }

    public function test_links_stop_working_when_the_module_is_disabled(): void
    {
        $order = $this->createPaidOrder();
        $url = $this->relative( app( ReceiptLinkService::class )->make( $order ) );

        $this->configure( [ WhatsAppConfig::ENABLED => 'no' ] );

        $this->get( $url )->assertForbidden();
    }

    public function test_unknown_order_is_forbidden(): void
    {
        $this->get( '/mplwhatsapp/receipt/999999999?expires=' . now()->addDay()->getTimestamp() . '&signature=abc' )->assertForbidden();
        $this->get( '/mplwhatsapp/receipt/not-a-number?expires=1&signature=abc' )->assertForbidden();
    }
}
