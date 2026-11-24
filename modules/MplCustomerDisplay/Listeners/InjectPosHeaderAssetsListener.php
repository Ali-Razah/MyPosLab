<?php

namespace Modules\MplCustomerDisplay\Listeners;

use App\Events\RenderHeaderEvent;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;

/**
 * Loads the prefixed module stylesheet on the POS page only.
 */
class InjectPosHeaderAssetsListener
{
    public function __construct( protected CustomerDisplayConfig $config )
    {
        //
    }

    public function handle( RenderHeaderEvent $event ): void
    {
        if ( $event->routeName !== ns()->routeName( 'ns.dashboard.pos' ) || ! $this->config->isEnabled() ) {
            return;
        }

        $event->output->addView( 'MplCustomerDisplay::pos.header' );
    }
}
