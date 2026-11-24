<?php

namespace Modules\MplOwnerApp\Services;

use App\Classes\AsideMenu;

class OwnerMenuService
{
    /**
     * Inject the "Owner App" entry right after the dashboard menu.
     * The entry is only added on a regular store dashboard (where the
     * "orders" anchor exists) to stay off the Multistore root dashboard.
     *
     * @param  array<string, mixed> $menus
     * @return array<string, mixed>
     */
    public static function injectMenu( array $menus ): array
    {
        if ( ! isset( $menus['orders'] ) ) {
            return $menus;
        }

        $ownerMenu = AsideMenu::menu(
            label: __m( 'Owner App', 'MplOwnerApp' ),
            identifier: 'mplownerapp',
            href: ns()->route( 'mplownerapp.connect' ),
            icon: 'la-mobile',
            permissions: [ 'mplownerapp.view' ],
        );

        if ( isset( $menus['dashboard'] ) ) {
            return array_insert_after( $menus, 'dashboard', $ownerMenu );
        }

        return array_merge( $ownerMenu, $menus );
    }
}
