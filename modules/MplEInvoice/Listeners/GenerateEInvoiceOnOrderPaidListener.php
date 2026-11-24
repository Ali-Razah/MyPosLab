<?php

namespace Modules\MplEInvoice\Listeners;

use App\Events\OrderAfterPaymentStatusChangedEvent;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MplEInvoice\Services\EInvoiceService;
use Throwable;

/**
 * Generates the tax invoice once an order becomes fully paid.
 * Generation runs after the order transaction commits so that it reads the
 * final order state and never rolls back (or blocks) the sale itself.
 */
class GenerateEInvoiceOnOrderPaidListener
{
    public function __construct( private EInvoiceService $service ) {}

    public function handle( OrderAfterPaymentStatusChangedEvent $event ): void
    {
        if ( $event->new !== Order::PAYMENT_PAID || $event->previous === Order::PAYMENT_PAID ) {
            return;
        }

        $orderId = $event->order->id;

        DB::afterCommit( function () use ( $orderId ) {
            try {
                $order = Order::find( $orderId );

                if ( $order instanceof Order ) {
                    $this->service->generateForOrder( $order );
                }
            } catch ( Throwable $exception ) {
                Log::error( 'MplEInvoice: unable to generate the e-invoice of order #' . $orderId, [
                    'exception' => $exception,
                ] );
            }
        } );
    }
}
