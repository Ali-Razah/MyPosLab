<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckApplicationHealthMiddleware;
use App\Http\Middleware\ClearRequestCacheMiddleware;
use App\Services\ModulesService;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::middleware( [
    SubstituteBindings::class,
    Authenticate::class,
    CheckApplicationHealthMiddleware::class,
    ClearRequestCacheMiddleware::class,
] )->prefix( 'dashboard' )->group( function () {
    ModulesService::loadModuleFile( 'MplBranches', 'Routes/dashboard' );
} );
