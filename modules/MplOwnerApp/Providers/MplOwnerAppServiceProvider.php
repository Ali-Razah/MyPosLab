<?php

namespace Modules\MplOwnerApp\Providers;

use App\Classes\Hook;
use App\Services\ModulesService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\MplOwnerApp\Services\OwnerMenuService;
use Modules\MplOwnerApp\Services\OwnerMetricsService;

class MplOwnerAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton( OwnerMetricsService::class, fn() => new OwnerMetricsService );

        Hook::addFilter( 'ns-dashboard-menus', [ OwnerMenuService::class, 'injectMenu' ] );

        /**
         * Multistore bridges: expose the same API and dashboard routes inside a store context.
         */
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreApiRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplOwnerApp', 'Routes/api' ) );
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreWebRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplOwnerApp', 'Routes/multistore' ) );
    }
}
