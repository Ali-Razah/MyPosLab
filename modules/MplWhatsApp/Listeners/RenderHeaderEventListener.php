<?php

namespace Modules\MplWhatsApp\Listeners;

use App\Events\RenderHeaderEvent;
use Modules\MplWhatsApp\Services\WhatsAppConfig;

/**
 * Loads the module stylesheet in the document header, only on the POS
 * and on the Orders list (the two places with a "Send via WhatsApp" action).
 */
class RenderHeaderEventListener
{
    public function __construct( private WhatsAppConfig $config ) {}

    public function handle( RenderHeaderEvent $event ): void
    {
        if ( ! RenderFooterEventListener::isTargetRoute( $event->routeName ) || ! $this->config->isEnabled() || ! ns()->allowedTo( 'mplwhatsapp.send' ) ) {
            return;
        }

        $event->output->addView( 'MplWhatsApp::assets.header' );
    }
}
