<?php

namespace Modules\MplOwnerApp\Listeners;

use App\Events\RenderFooterEvent;
use Illuminate\Support\Str;

/**
 * Signing out lands on the login page: the owner page and metrics cached by
 * the owner service worker are private data and are purged there, so they
 * can't be shown offline to the next person using a shared device.
 */
class RenderFooterListener
{
    public function handle( RenderFooterEvent $event ): void
    {
        if ( Str::endsWith( (string) $event->routeName, 'ns.login' ) ) {
            $event->output->addView( 'MplOwnerApp::purge-caches' );
        }
    }
}
