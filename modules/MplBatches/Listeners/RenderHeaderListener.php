<?php

namespace Modules\MplBatches\Listeners;

use App\Events\RenderHeaderEvent;
use Illuminate\Support\Str;

/**
 * Loads the module stylesheet (prefixed "mplb:" utilities) in the document head
 * on the dashboard home (widget) and on the module pages.
 */
class RenderHeaderListener
{
    public function handle( RenderHeaderEvent $event ): void
    {
        $route = (string) $event->routeName;

        if ( Str::endsWith( $route, 'ns.dashboard.home' ) || Str::contains( $route, 'mplbatches.' ) ) {
            $event->output->addView( 'MplBatches::styles' );
        }
    }
}
