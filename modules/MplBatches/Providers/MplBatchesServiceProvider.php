<?php

namespace Modules\MplBatches\Providers;

use App\Classes\AsideMenu;
use App\Classes\Hook;
use App\Services\ModulesService;
use App\Services\WidgetService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\MplBatches\Services\BatchService;
use Modules\MplBatches\Widgets\ExpiringSoonWidget;

class MplBatchesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /**
         * Singleton: the write-off context must be shared between the
         * service call and the stock adjustment listener.
         */
        $this->app->singleton( BatchService::class, fn() => new BatchService );
    }

    public function boot(): void
    {
        Hook::addFilter( 'ns-dashboard-menus', [ $this, 'registerMenus' ], 30 );

        /**
         * A store reset (ns:reset / "wipe") truncates products, procurements and
         * orders; batches reference their ids, so they must be wiped with them.
         */
        Hook::addFilter( 'ns-wipeable-tables', fn( array $tables ) => array_merge( $tables, [
            'mpl_batch_batches',
            'mpl_batch_allocations',
            'mpl_batch_procurement_numbers',
        ] ) );
        Hook::addFilter( 'ns-procurement-columns', [ $this, 'addProcurementColumn' ] );
        Hook::addFilter( 'ns-procurement-form', [ $this, 'fillProcurementBatchNumbers' ] );

        app()->make( WidgetService::class )->registerWidgets( ExpiringSoonWidget::class );

        /**
         * Multistore bridges (no-op when Multistore isn't installed).
         */
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreApiRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplBatches', 'Routes/api' ) );
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreWebRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplBatches', 'Routes/multistore' ) );
    }

    public function registerMenus( array $menus ): array
    {
        /**
         * Only on a regular dashboard / inside a store (Multistore root has no inventory).
         */
        if ( ! isset( $menus[ 'inventory' ] ) ) {
            return $menus;
        }

        return array_insert_after( $menus, 'inventory', AsideMenu::menu(
            label: __m( 'Batches & Expiry', 'MplBatches' ),
            identifier: 'mplbatches',
            icon: 'la-calendar-times',
            permissions: [ 'mplbatches.read', 'mplbatches.settings' ],
            childrens: AsideMenu::childrens(
                AsideMenu::subMenu(
                    label: __m( 'Batches', 'MplBatches' ),
                    identifier: 'mplbatches-batches',
                    permissions: [ 'mplbatches.read' ],
                    href: ns()->route( 'mplbatches.batches' ),
                ),
                AsideMenu::subMenu(
                    label: __m( 'Expiry Report', 'MplBatches' ),
                    identifier: 'mplbatches-report',
                    permissions: [ 'mplbatches.read' ],
                    href: ns()->route( 'mplbatches.report' ),
                ),
                AsideMenu::subMenu(
                    label: __m( 'Settings', 'MplBatches' ),
                    identifier: 'mplbatches-settings',
                    permissions: [ 'mplbatches.settings' ],
                    href: ns()->route( 'mplbatches.settings' ),
                ),
            ),
        ) );
    }

    /**
     * Adds a "Batch No." cell to the procurement grid. The core grid edits
     * "text" columns with the numeric keypad, so only digits can be typed there;
     * alphanumeric lot numbers can be corrected on the batch screen.
     */
    public function addProcurementColumn( array $columns ): array
    {
        $batchColumn = [
            'batch_number' => [
                'label' => __m( 'Batch No.', 'MplBatches' ),
                'type' => 'text',
            ],
        ];

        if ( isset( $columns[ 'quantity' ] ) ) {
            return array_insert_after( $columns, 'quantity', $batchColumn );
        }

        return array_merge( $columns, $batchColumn );
    }

    /**
     * When editing a procurement, show the batch number already recorded for each line.
     */
    public function fillProcurementBatchNumbers( array $form ): array
    {
        if ( empty( $form[ 'products' ] ) ) {
            return $form;
        }

        /**
         * @var BatchService $batchService
         */
        $batchService = app()->make( BatchService::class );

        $form[ 'products' ] = collect( $form[ 'products' ] )->map( function ( $product ) use ( $batchService ) {
            if ( is_object( $product ) && is_array( $product->procurement ?? null ) && ! empty( $product->id ) ) {
                $procurement = $product->procurement;
                $procurement[ 'batch_number' ] = $batchService->getProcurementBatchNumber( (int) $product->id ) ?? '';
                $product->procurement = $procurement;
            }

            return $product;
        } );

        return $form;
    }
}
