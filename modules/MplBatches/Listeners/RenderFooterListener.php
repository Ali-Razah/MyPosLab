<?php

namespace Modules\MplBatches\Listeners;

use App\Events\RenderFooterEvent;
use Illuminate\Support\Str;

/**
 * Registers the "Expiring soon" widget component on the dashboard home only.
 */
class RenderFooterListener
{
    public function handle( RenderFooterEvent $event ): void
    {
        if ( Str::endsWith( (string) $event->routeName, 'ns.dashboard.home' ) ) {
            $event->output->addView( 'MplBatches::widgets.assets' );
        }
    }
}
