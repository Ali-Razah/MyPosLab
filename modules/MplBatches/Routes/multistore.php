<?php

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplBatches\Http\Controllers\BatchesController;

/**
 * Dashboard routes. Loaded under "dashboard" by Routes/web.php and, inside a
 * store, by Multistore's dashboard group (which already adds auth + prefix).
 */
Route::middleware( [
    SubstituteBindings::class,
] )->prefix( 'mplbatches' )->group( function (): void {
    Route::get( 'batches', [ BatchesController::class, 'listBatches' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.read' ) )
        ->name( ns()->routeName( 'mplbatches.batches' ) );

    Route::get( 'batches/edit/{batch}', [ BatchesController::class, 'editBatch' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.manage' ) )
        ->name( ns()->routeName( 'mplbatches.batches.edit' ) );

    Route::get( 'batches/{batch}/allocations', [ BatchesController::class, 'batchAllocations' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.read' ) )
        ->name( ns()->routeName( 'mplbatches.batches.allocations' ) );

    Route::get( 'report', [ BatchesController::class, 'expiryReport' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.read' ) )
        ->name( ns()->routeName( 'mplbatches.report' ) );

    Route::get( 'report/print', [ BatchesController::class, 'printExpiryReport' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.read' ) )
        ->name( ns()->routeName( 'mplbatches.report.print' ) );

    Route::get( 'settings', [ BatchesController::class, 'settings' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbatches.settings' ) )
        ->name( ns()->routeName( 'mplbatches.settings' ) );
} );
