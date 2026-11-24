<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\ClearRequestCacheMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::middleware( [
    SubstituteBindings::class,
    Authenticate::class,
    ClearRequestCacheMiddleware::class,
] )->prefix( 'dashboard' )->group( function (): void {
    require __DIR__ . '/multistore.php';
} );
