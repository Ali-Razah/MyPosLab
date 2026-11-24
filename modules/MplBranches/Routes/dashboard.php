<?php

/**
 * Authenticated dashboard routes, shared by the regular dashboard (Routes/web.php)
 * and by the MultiStore store dashboard (Routes/multistore.php).
 */

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplBranches\Http\Controllers\BranchesController;

Route::middleware( [
    SubstituteBindings::class,
] )->prefix( 'mplbranches' )->group( function () {
    Route::middleware( NsRestrictMiddleware::arguments( 'mplbranches.manage' ) )->group( function () {
        Route::get( 'branches', [ BranchesController::class, 'listBranches' ] )
            ->name( ns()->routeName( 'mplbranches.dashboard.branches' ) );
        Route::get( 'branches/create', [ BranchesController::class, 'createBranch' ] )
            ->name( ns()->routeName( 'mplbranches.dashboard.branches.create' ) );
        Route::get( 'branches/edit/{branch}', [ BranchesController::class, 'editBranch' ] )
            ->name( ns()->routeName( 'mplbranches.dashboard.branches.edit' ) );
    } );

    Route::middleware( NsRestrictMiddleware::arguments( 'mplbranches.switch' ) )->group( function () {
        Route::get( 'switch', [ BranchesController::class, 'switchPage' ] )
            ->name( ns()->routeName( 'mplbranches.dashboard.switch' ) );
        Route::post( 'switch', [ BranchesController::class, 'switchBranch' ] )
            ->name( ns()->routeName( 'mplbranches.dashboard.switch.post' ) );
    } );

    Route::get( 'reports/performance', [ BranchesController::class, 'performanceReport' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplbranches.reports' ) )
        ->name( ns()->routeName( 'mplbranches.dashboard.reports.performance' ) );
} );
