<?php

/**
 * Public, unauthenticated routes. Loaded from web.php and, when Multistore
 * is installed, from its public (store-aware, unauthenticated) route group.
 */

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplWhatsApp\Http\Controllers\PublicReceiptController;

Route::middleware( [
    SubstituteBindings::class,
    'throttle:60,1',
] )->group( function (): void {
    Route::get( 'mplwhatsapp/receipt/{order}', [ PublicReceiptController::class, 'show' ] )
        ->name( ns()->routeName( 'mplwhatsapp.receipt' ) );
} );
