<?php

namespace Modules\MplWhatsApp\Listeners;

use App\Events\OrderAfterPaymentStatusChangedEvent;
use App\Models\Order;
use Modules\MplWhatsApp\Jobs\SendWhatsAppReceiptJob;
use Modules\MplWhatsApp\Services\PhoneNormalizer;
use Modules\MplWhatsApp\Services\WhatsAppConfig;

/**
 * Queues the automatic Cloud API receipt when an order becomes fully paid.
 *
 * The core fires this event from the order "created"/"updated" model hooks,
 * after products and payments are saved but inside the order transaction,
 * so the job is dispatched after commit.
 */
class AutoSendReceiptOnPaymentListener
{
    public function __construct(
        private WhatsAppConfig $config,
        private PhoneNormalizer $phoneNormalizer,
    ) {}

    public function handle( OrderAfterPaymentStatusChangedEvent $event ): void
    {
        if ( $event->new !== Order::PAYMENT_PAID || $event->previous === Order::PAYMENT_PAID ) {
            return;
        }

        if ( ! $this->config->shouldAutoSend() ) {
            return;
        }

        $order = $event->order;
        $order->loadMissing( 'customer' );

        if ( $this->phoneNormalizer->tryNormalize( $order->customer?->phone, $this->config->countryCode() ) === null ) {
            return;
        }

        SendWhatsAppReceiptJob::dispatch( $order )->afterCommit();
    }
}
