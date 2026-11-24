<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\ClearRequestCacheMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplOwnerApp\Http\Controllers\OwnerPwaController;

Route::middleware( [
    SubstituteBindings::class,
] )->group( function (): void {
    /**
     * Public PWA assets: browsers fetch manifests and service worker scripts
     * outside of the authenticated session. They expose no store data.
     */
    Route::get( 'mplownerapp/manifest.webmanifest', [ OwnerPwaController::class, 'manifest' ] )
        ->name( 'mplownerapp.manifest' );

    Route::get( 'mplownerapp/owner-sw.js', [ OwnerPwaController::class, 'serviceWorker' ] )
        ->name( 'mplownerapp.sw' );

    Route::prefix( 'dashboard' )
        ->middleware( [
            Authenticate::class,
            ClearRequestCacheMiddleware::class,
        ] )
        ->group( function (): void {
            require __DIR__ . '/multistore.php';
        } );
} );
