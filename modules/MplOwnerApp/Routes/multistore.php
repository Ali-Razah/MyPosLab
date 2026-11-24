<?php

/**
 * Authenticated dashboard routes. Loaded under the "dashboard" prefix by
 * Routes/web.php and, when Multistore is enabled, inside the store dashboard group.
 */

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplOwnerApp\Http\Controllers\OwnerAppController;

Route::middleware( [
    SubstituteBindings::class,
    NsRestrictMiddleware::arguments( 'mplownerapp.view' ),
] )->group( function (): void {
    Route::get( 'owner', [ OwnerAppController::class, 'page' ] )
        ->name( ns()->routeName( 'mplownerapp.owner' ) );

    Route::get( 'owner/connect', [ OwnerAppController::class, 'connect' ] )
        ->name( ns()->routeName( 'mplownerapp.connect' ) );
} );
