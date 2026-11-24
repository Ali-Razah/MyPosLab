<?php

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplBatches\Http\Controllers\BatchesApiController;

Route::middleware( [
    'auth:sanctum',
    SubstituteBindings::class,
] )->prefix( 'mplbatches' )->group( function (): void {
    Route::get( 'summary', [ BatchesApiController::class, 'summary' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.read' ) )
        ->name( ns()->routeName( 'mplbatches.api.summary' ) );

    Route::get( 'batches/{batch}/allocations', [ BatchesApiController::class, 'allocations' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.read' ) )
        ->name( ns()->routeName( 'mplbatches.api.allocations' ) );

    /**
     * GET is what the CRUD row action (with confirmation popup) can call;
     * POST accepts a partial "quantity" and a "reason".
     */
    Route::match( [ 'get', 'post' ], 'batches/{batch}/write-off', [ BatchesApiController::class, 'writeOff' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.manage' ) )
        ->name( ns()->routeName( 'mplbatches.api.write-off' ) );
} );
