<?php

namespace Modules\MplCustomerDisplay\Listeners;

use App\Events\RenderFooterEvent;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;

/**
 * Loads the POS publisher (cart -> second screen) and the header button
 * on the POS page only.
 */
class InjectPosFooterAssetsListener
{
    public function __construct( protected CustomerDisplayConfig $config )
    {
        //
    }

    public function handle( RenderFooterEvent $event ): void
    {
        if ( $event->routeName !== ns()->routeName( 'ns.dashboard.pos' ) || ! $this->config->isEnabled() ) {
            return;
        }

        $event->output->addView( 'MplCustomerDisplay::pos.footer', [
            'posConfig' => $this->config->forPos(),
        ] );
    }
}
