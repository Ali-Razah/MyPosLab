<?php

/**
 * Authenticated dashboard routes. Loaded under "/dashboard" by web.php and,
 * when Multistore is installed, inside its store dashboard group
 * (which already provides the prefix and authentication).
 */

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplWhatsApp\Http\Controllers\WhatsAppSettingsController;

Route::middleware( [
    SubstituteBindings::class,
] )->group( function (): void {
    Route::get( 'mplwhatsapp/settings', [ WhatsAppSettingsController::class, 'page' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplwhatsapp.settings' ) )
        ->name( ns()->routeName( 'mplwhatsapp.settings' ) );
} );
