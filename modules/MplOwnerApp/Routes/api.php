<?php

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplOwnerApp\Http\Controllers\OwnerMetricsController;

Route::middleware( [
    'auth:sanctum',
    SubstituteBindings::class,
    NsRestrictMiddleware::arguments( 'mplownerapp.view' ),
] )->group( function (): void {
    Route::get( 'mplownerapp/metrics', [ OwnerMetricsController::class, 'index' ] )
        ->name( ns()->routeName( 'mplownerapp.api.metrics' ) );
} );
