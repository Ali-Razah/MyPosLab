<?php

namespace Modules\MplBranches\Filters;

use App\Classes\AsideMenu;
use App\Classes\Menu;
use Illuminate\Support\Facades\Auth;
use Modules\MplBranches\Services\BranchService;
use Throwable;

class MenusFilter
{
    /**
     * Adds the "Branches" menu after "orders". Skipped when the store anchor is
     * absent (e.g. MultiStore root dashboard).
     */
    public static function injectDashboardMenus( array $menus ): array
    {
        if ( ! isset( $menus[ 'orders' ] ) ) {
            return $menus;
        }

        $menus = array_insert_after( $menus, 'orders', AsideMenu::menu(
            label: __m( 'Branches', 'MplBranches' ),
            identifier: 'mplbranches',
            icon: 'la-store-alt',
            permissions: [ 'mplbranches.manage', 'mplbranches.reports', 'mplbranches.switch' ],
            childrens: AsideMenu::childrens(
                AsideMenu::subMenu(
                    label: __m( 'Branches List', 'MplBranches' ),
                    identifier: 'mplbranches-list',
                    href: ns()->route( 'mplbranches.dashboard.branches' ),
                    permissions: [ 'mplbranches.manage' ],
                ),
                AsideMenu::subMenu(
                    label: __m( 'Create Branch', 'MplBranches' ),
                    identifier: 'mplbranches-create',
                    href: ns()->route( 'mplbranches.dashboard.branches.create' ),
                    permissions: [ 'mplbranches.manage' ],
                ),
                AsideMenu::subMenu(
                    label: __m( 'Branch Performance', 'MplBranches' ),
                    identifier: 'mplbranches-performance',
                    href: ns()->route( 'mplbranches.dashboard.reports.performance' ),
                    permissions: [ 'mplbranches.reports' ],
                ),
                AsideMenu::subMenu(
                    label: __m( 'Switch Branch', 'MplBranches' ),
                    identifier: 'mplbranches-switch',
                    href: ns()->route( 'mplbranches.dashboard.switch' ),
                    permissions: [ 'mplbranches.switch' ],
                ),
            ),
        ) );

        if ( isset( $menus[ 'reports' ][ 'childrens' ] ) ) {
            $menus[ 'reports' ][ 'childrens' ] = array_merge( $menus[ 'reports' ][ 'childrens' ], AsideMenu::subMenu(
                label: __m( 'Branch Performance', 'MplBranches' ),
                identifier: 'mplbranches-performance-report',
                href: ns()->route( 'mplbranches.dashboard.reports.performance' ),
                permissions: [ 'mplbranches.reports' ],
            ) );
        }

        return $menus;
    }

    /**
     * Shows the current branch in the avatar dropdown of the dashboard header.
     */
    public static function injectAccountMenus( array $menus ): array
    {
        try {
            $user = Auth::user();

            if ( ! $user ) {
                return $menus;
            }

            $branch = app()->make( BranchService::class )->getCurrentBranch( $user );

            return array_merge( Menu::item(
                label: sprintf( __m( 'Branch: %s', 'MplBranches' ), $branch->name ),
                identifier: 'mplbranches-current',
                icon: 'la-store-alt',
                href: ns()->route( 'mplbranches.dashboard.switch' ),
                permissions: [ 'mplbranches.switch' ],
            ), $menus );
        } catch ( Throwable $exception ) {
            return $menus;
        }
    }
}
