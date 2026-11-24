<?php

namespace Modules\MplEInvoice\Providers;

use App\Classes\AsideMenu;
use App\Classes\Hook;
use App\Crud\CustomerCrud;
use App\Services\ModulesService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\MplEInvoice\Filters\CustomerCrudFilter;
use Modules\MplEInvoice\Services\Asp\AspClient;
use Modules\MplEInvoice\Services\Asp\HttpAspClient;

class MplEInvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /**
         * Swap this binding to integrate an ASP with a specific protocol.
         */
        $this->app->bind( AspClient::class, HttpAspClient::class );
    }

    public function boot(): void
    {
        Hook::addFilter( 'ns-dashboard-menus', [ self::class, 'registerMenus' ], 30 );

        /**
         * A store reset (ns:reset / "wipe") truncates orders and refunds. Documents
         * reference their ids, so they are wiped too; otherwise re-used order ids
         * would look "already invoiced". Number sequences are kept on purpose so an
         * invoice number that was already issued is never handed out again.
         */
        Hook::addFilter( 'ns-wipeable-tables', fn( array $tables ) => array_merge( $tables, [ 'mpl_einv_documents' ] ) );

        CustomerCrud::filterMethod( 'getForm', [ CustomerCrudFilter::class, 'injectTaxTab' ], 10, 2 );
        CustomerCrud::filterMethod( 'filterPostInputs', [ CustomerCrudFilter::class, 'stripModuleInputs' ] );
        CustomerCrud::filterMethod( 'filterPutInputs', [ CustomerCrudFilter::class, 'stripModuleInputs' ] );

        /**
         * Multistore route bridges (no-op when NsMultiStore is not installed).
         */
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreApiRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplEInvoice', 'Routes/api' ) );
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreWebRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplEInvoice', 'Routes/multistore' ) );
    }

    /**
     * @param  array<string, mixed> $menus
     * @return array<string, mixed>
     */
    public static function registerMenus( array $menus ): array
    {
        /**
         * Only add the menu where the store "orders" anchor exists, which keeps it
         * off the Multistore root dashboard.
         */
        if ( ! isset( $menus[ 'orders' ] ) ) {
            return $menus;
        }

        return array_insert_after( $menus, 'orders', AsideMenu::menu(
            label: __m( 'E-Invoicing', 'MplEInvoice' ),
            identifier: 'mpl-einvoice',
            icon: 'la-file-invoice',
            permissions: [ 'mpleinvoice.read', 'mpleinvoice.manage-settings' ],
            childrens: AsideMenu::childrens(
                AsideMenu::subMenu(
                    label: __m( 'E-Invoices', 'MplEInvoice' ),
                    identifier: 'mpl-einvoice-documents',
                    href: ns()->url( '/dashboard/mpl-einvoice/documents' ),
                    permissions: [ 'mpleinvoice.read' ],
                ),
                AsideMenu::subMenu(
                    label: __m( 'Settings', 'MplEInvoice' ),
                    identifier: 'mpl-einvoice-settings',
                    href: ns()->url( '/dashboard/mpl-einvoice/settings' ),
                    permissions: [ 'mpleinvoice.manage-settings' ],
                ),
            ),
        ) );
    }
}
