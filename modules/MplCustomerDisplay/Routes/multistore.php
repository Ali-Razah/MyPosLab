<?php

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplCustomerDisplay\Http\Controllers\CustomerDisplayController;

/**
 * Authenticated dashboard routes. No "dashboard" prefix or auth middleware here:
 * Routes/web.php and the Multistore store group provide them.
 */
Route::middleware( [
    SubstituteBindings::class,
] )->group( function (): void {
    Route::get( 'customer-display', [ CustomerDisplayController::class, 'display' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplcustomerdisplay.view' ) )
        ->name( ns()->routeName( 'mplcd.display' ) );

    Route::get( 'customer-display/settings', [ CustomerDisplayController::class, 'settings' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplcustomerdisplay.settings' ) )
        ->name( ns()->routeName( 'mplcd.settings' ) );
} );
