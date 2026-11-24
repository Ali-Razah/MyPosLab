<?php

namespace Modules\MplBranches\Providers;

use App\Classes\Hook;
use App\Crud\OrderCrud;
use App\Services\ModulesService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\MplBranches\Filters\MenusFilter;
use Modules\MplBranches\Filters\OrderCrudFilter;
use Modules\MplBranches\Filters\ReceiptFilter;
use Modules\MplBranches\Services\BranchReportService;
use Modules\MplBranches\Services\BranchService;

class MplBranchesServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton( BranchService::class, fn () => new BranchService );
        $this->app->singleton( BranchReportService::class, fn () => new BranchReportService );
    }

    public function boot()
    {
        Hook::addFilter( 'ns-dashboard-menus', [ MenusFilter::class, 'injectDashboardMenus' ], 30 );

        /**
         * A store reset (ns:reset / "wipe") truncates orders and cash registers.
         * Their branch links are wiped too; branches and staff assignments stay.
         */
        Hook::addFilter( 'ns-wipeable-tables', fn( array $tables ) => array_merge( $tables, [
            'mpl_br_branch_orders',
            'mpl_br_branch_register',
        ] ) );
        Hook::addFilter( 'ns-account-menus', [ MenusFilter::class, 'injectAccountMenus' ] );

        OrderCrud::filterMethod( 'getRelations', [ OrderCrudFilter::class, 'relations' ] );
        OrderCrud::filterMethod( 'getPicked', [ OrderCrudFilter::class, 'picked' ] );
        OrderCrud::filterMethod( 'getColumns', [ OrderCrudFilter::class, 'columns' ] );
        OrderCrud::filterMethod( 'getQueryFilters', [ OrderCrudFilter::class, 'queryFilters' ] );

        Hook::addFilter( 'ns-web-receipt-template', [ ReceiptFilter::class, 'wrapReceiptTemplate' ], 30 );
        Hook::addFilter( 'ns-orders-template-mapping', [ ReceiptFilter::class, 'templateMapping' ], 10, 2 );

        /**
         * MultiStore bridges (no-op when MultiStore is not installed).
         */
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreApiRoutesLoadedEvent', fn () => ModulesService::loadModuleFile( 'MplBranches', 'Routes/api' ) );
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreWebRoutesLoadedEvent', fn () => ModulesService::loadModuleFile( 'MplBranches', 'Routes/multistore' ) );
    }
}
