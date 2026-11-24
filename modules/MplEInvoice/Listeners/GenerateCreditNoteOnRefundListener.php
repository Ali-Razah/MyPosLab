<?php

namespace Modules\MplEInvoice\Listeners;

use App\Events\OrderAfterRefundedEvent;
use App\Models\Order;
use App\Models\OrderRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceService;
use Throwable;

/**
 * Generates a credit note for every refund of an invoiced (or fully paid) order.
 */
class GenerateCreditNoteOnRefundListener
{
    public function __construct( private EInvoiceService $service ) {}

    public function handle( OrderAfterRefundedEvent $event ): void
    {
        $refundId = $event->orderRefund->id;
        $orderId = $event->order->id;

        /**
         * Payment status before the refund (the core refreshes the order afterwards).
         * Orders that were never fully paid received no tax invoice, so there is no
         * invoiced VAT to credit. Paid orders without invoice (e.g. paid before the
         * module was enabled) still get a credit note referencing the order.
         */
        $wasPaid = in_array( $event->order->payment_status, [
            Order::PAYMENT_PAID,
            Order::PAYMENT_PARTIALLY_REFUNDED,
            Order::PAYMENT_REFUNDED,
        ], true );

        DB::afterCommit( function () use ( $refundId, $orderId, $wasPaid ) {
            try {
                if ( ! $wasPaid && ! EInvoiceDocument::where( 'source_key', EInvoiceService::orderSourceKey( $orderId ) )->exists() ) {
                    return;
                }

                $refund = OrderRefund::find( $refundId );

                if ( $refund instanceof OrderRefund ) {
                    $this->service->generateCreditNote( $refund );
                }
            } catch ( Throwable $exception ) {
                Log::error( 'MplEInvoice: unable to generate the credit note of refund #' . $refundId, [
                    'exception' => $exception,
                ] );
            }
        } );
    }
}
