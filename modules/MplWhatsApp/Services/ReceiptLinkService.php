<?php

namespace Modules\MplWhatsApp\Services;

use App\Classes\Hook;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Generates and verifies temporary signed links to the public receipt page.
 *
 * The signature is an HMAC (application key) over the order id, the order code,
 * the active store and the expiry timestamp. It does not depend on the URL itself,
 * so it keeps working whatever URL rewriting Multistore applies (path or subdomain
 * mode), while a link generated for one order/store can never open another one.
 */
class ReceiptLinkService
{
    public function __construct( private WhatsAppConfig $config ) {}

    public function make( Order $order, ?Carbon $expiresAt = null ): string
    {
        $expiresAt = $expiresAt ?: now()->addDays( $this->config->linkValidityDays() );
        $expires = $expiresAt->getTimestamp();

        $url = ns()->url( '/mplwhatsapp/receipt/' . $order->id ) . '?' . http_build_query( [
            'expires' => $expires,
            'signature' => $this->signature( $order, $expires ),
        ] );

        return Hook::filter( 'mplwhatsapp-receipt-url', $url, $order, $expires );
    }

    public function isValid( Order $order, mixed $expires, mixed $signature ): bool
    {
        if ( ! is_string( $signature ) || ! is_numeric( $expires ) ) {
            return false;
        }

        if ( (int) $expires < now()->getTimestamp() ) {
            return false;
        }

        return hash_equals( $this->signature( $order, (int) $expires ), $signature );
    }

    private function signature( Order $order, int $expires ): string
    {
        $payload = implode( '|', [
            'mplwhatsapp-receipt',
            ns()->storeID ?? 'root',
            $order->id,
            $order->code,
            $expires,
        ] );

        return hash_hmac( 'sha256', $payload, (string) config( 'app.key' ) );
    }
}
