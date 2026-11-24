<?php

namespace Modules\MplWhatsApp\Listeners;

use App\Crud\OrderCrud;
use App\Events\CrudActionEvent;
use App\Models\Order;
use Modules\MplWhatsApp\Services\WhatsAppConfig;

/**
 * Adds the "Send via WhatsApp" row action to the core Orders list.
 */
class CrudActionEventListener
{
    public function __construct( private WhatsAppConfig $config ) {}

    public function handle( CrudActionEvent $event ): void
    {
        if ( ! $event->instance instanceof OrderCrud || ! $this->config->isEnabled() ) {
            return;
        }

        if ( in_array( $event->entry->__raw->payment_status ?? null, [ Order::PAYMENT_HOLD, Order::PAYMENT_VOID ], true ) ) {
            return;
        }

        $event->entry->action(
            identifier: 'mplwhatsapp.send',
            label: '<i class="mr-2 lab la-whatsapp"></i> ' . __m( 'Send via WhatsApp', 'MplWhatsApp' ),
            type: 'POPUP',
            url: 'javascript:void(0)',
            permissions: [ 'mplwhatsapp.send' ],
        );
    }
}
