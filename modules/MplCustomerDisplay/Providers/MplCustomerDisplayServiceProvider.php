<?php

namespace Modules\MplCustomerDisplay\Providers;

use App\Classes\AsideMenu;
use App\Classes\Hook;
use App\Services\ModulesService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MplCustomerDisplayServiceProvider extends ServiceProvider
{
    /**
     * Multistore route events (string names: the NsMultiStore module may be absent).
     */
    const MULTISTORE_WEB_ROUTES_EVENT = 'Modules\NsMultiStore\Events\MultiStoreWebRoutesLoadedEvent';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen( self::MULTISTORE_WEB_ROUTES_EVENT, fn() => ModulesService::loadModuleFile( 'MplCustomerDisplay', 'Routes/multistore' ) );

        Hook::addFilter( 'ns-dashboard-menus', [ $this, 'registerMenus' ], 30 );
    }

    /**
     * Adds "Customer Display" under Settings when the settings anchor exists
     * (absent on the Multistore root dashboard).
     *
     * @param  array<string, mixed> $menus
     * @return array<string, mixed>
     */
    public function registerMenus( $menus )
    {
        if ( ! is_array( $menus ) || ! isset( $menus[ 'settings' ][ 'childrens' ] ) ) {
            return $menus;
        }

        $menus[ 'settings' ][ 'childrens' ] = [
            ...$menus[ 'settings' ][ 'childrens' ],
            ...AsideMenu::subMenu(
                label: __m( 'Customer Display', 'MplCustomerDisplay' ),
                identifier: 'mplcd-settings',
                href: ns()->route( 'mplcd.settings' ),
                permissions: [ 'mplcustomerdisplay.settings' ],
            ),
        ];

        return $menus;
    }
}
