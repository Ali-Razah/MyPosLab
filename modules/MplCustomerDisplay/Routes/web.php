<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckApplicationHealthMiddleware;
use App\Http\Middleware\ClearRequestCacheMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/**
 * Dashboard routes (non-Multistore). Multistore loads Routes/multistore.php
 * directly inside its own authenticated store dashboard group.
 */
Route::prefix( 'dashboard' )
    ->middleware( [
        SubstituteBindings::class,
        Authenticate::class,
        CheckApplicationHealthMiddleware::class,
        ClearRequestCacheMiddleware::class,
    ] )
    ->group( function (): void {
        require __DIR__ . DIRECTORY_SEPARATOR . 'multistore.php';
    } );
