<?php

namespace Modules\MplWhatsApp\Providers;

use App\Classes\AsideMenu;
use App\Classes\Hook;
use App\Services\ModulesService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\MplWhatsApp\Services\PhoneNormalizer;
use Modules\MplWhatsApp\Services\ReceiptLinkService;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Services\WhatsAppReceiptService;

class MplWhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton( WhatsAppConfig::class );
        $this->app->singleton( PhoneNormalizer::class );
        $this->app->singleton( ReceiptLinkService::class );
        $this->app->singleton( WhatsAppReceiptService::class );
    }

    public function boot(): void
    {
        Hook::addFilter( 'ns-dashboard-menus', [ $this, 'registerMenu' ], 30 );

        /**
         * A store reset (ns:reset / "wipe") truncates orders; the message log
         * references order ids, so it must be wiped as well.
         */
        Hook::addFilter( 'ns-wipeable-tables', fn( array $tables ) => array_merge( $tables, [ 'mpl_wa_messages' ] ) );

        /**
         * Multistore bridges (no-op when Multistore is not installed).
         */
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreApiRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplWhatsApp', 'Routes/api' ) );
        Event::listen( 'Modules\NsMultiStore\Events\MultiStoreWebRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplWhatsApp', 'Routes/multistore' ) );
        Event::listen( 'Modules\NsMultiStore\Events\MultiStorePublicRoutesLoadedEvent', fn() => ModulesService::loadModuleFile( 'MplWhatsApp', 'Routes/public' ) );
    }

    /**
     * Adds "WhatsApp" under the core Settings menu, when that menu exists
     * (it is absent on the Multistore root dashboard).
     *
     * @param  array<string, mixed> $menus
     * @return array<string, mixed>
     */
    public function registerMenu( array $menus ): array
    {
        if ( ! isset( $menus[ 'settings' ] ) || ! ns()->allowedTo( 'mplwhatsapp.settings' ) ) {
            return $menus;
        }

        $menus[ 'settings' ][ 'childrens' ] = array_merge(
            $menus[ 'settings' ][ 'childrens' ] ?? [],
            AsideMenu::subMenu(
                label: __m( 'WhatsApp', 'MplWhatsApp' ),
                identifier: 'mplwhatsapp',
                href: ns()->url( '/dashboard/mplwhatsapp/settings' ),
                permissions: [ 'mplwhatsapp.settings' ],
            )
        );

        return $menus;
    }
}
