<?php

/**
 * Mounted under /api by NexoPOS (and under the store API group by MultiStore).
 */

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplBranches\Http\Controllers\Api\BranchesApiController;

Route::middleware( [
    SubstituteBindings::class,
    'auth:sanctum',
] )->prefix( 'mplbranches' )->group( function () {
    Route::middleware( NsRestrictMiddleware::arguments( 'mplbranches.switch' ) )->group( function () {
        Route::get( 'branches/mine', [ BranchesApiController::class, 'myBranches' ] )
            ->name( ns()->routeName( 'mplbranches.api.branches.mine' ) );
        Route::post( 'switch', [ BranchesApiController::class, 'switchBranch' ] )
            ->name( ns()->routeName( 'mplbranches.api.switch' ) );
    } );

    Route::get( 'reports/performance', [ BranchesApiController::class, 'performance' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbranches.reports' ) )
        ->name( ns()->routeName( 'mplbranches.api.reports.performance' ) );

    Route::get( 'orders/{order}/branch', [ BranchesApiController::class, 'orderBranch' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'nexopos.read.orders' ) )
        ->name( ns()->routeName( 'mplbranches.api.orders.branch' ) );
} );
