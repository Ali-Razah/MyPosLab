<?php

namespace Modules\MplBatches\Listeners;

use App\Events\OrderAfterCheckPerformedEvent;
use App\Models\Order;
use Modules\MplBatches\Services\BatchService;

/**
 * Complements core's barcode-level expiry check: refuses an order that could
 * only be served from expired batches when selling them is prevented.
 */
class OrderAfterCheckPerformedListener
{
    public function __construct( private BatchService $batchService )
    {
        // ...
    }

    public function handle( OrderAfterCheckPerformedEvent $event ): void
    {
        $fields = (array) $event->fields;

        /**
         * Hold orders do not move stock yet. Like core (__checkOrderPayments),
         * an order only stays on hold when no payment is provided.
         */
        $hasPayments = collect( $fields[ 'payments' ] ?? [] )->sum( fn( $payment ) => (float) ( $payment[ 'value' ] ?? 0 ) ) > 0;

        if ( ! $this->batchService->isEnabled() || ( ( $fields[ 'payment_status' ] ?? null ) === Order::PAYMENT_HOLD && ! $hasPayments ) ) {
            return;
        }

        $this->batchService->assertOrderDoesNotSellExpiredStock( $fields );
    }
}
