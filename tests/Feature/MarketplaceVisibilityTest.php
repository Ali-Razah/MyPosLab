<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Services\MenuService;
use Tests\TestCase;

class MarketplaceVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs( Role::namespace( 'admin' )->users->first() );
    }

    public function test_marketplace_is_disabled_by_default(): void
    {
        $this->assertFalse( config( 'nexopos.marketplace' ) );
    }

    public function test_marketplace_menu_is_hidden_by_default(): void
    {
        $modulesMenu = app( MenuService::class )->getMenus()[ 'modules' ];

        $this->assertArrayHasKey( 'modules', $modulesMenu[ 'childrens' ] );
        $this->assertArrayHasKey( 'upload-module', $modulesMenu[ 'childrens' ] );
        $this->assertArrayNotHasKey( 'marketplace', $modulesMenu[ 'childrens' ] );
    }

    public function test_marketplace_menu_is_shown_when_enabled(): void
    {
        config( [ 'nexopos.marketplace' => true ] );

        $modulesMenu = app( MenuService::class )->getMenus()[ 'modules' ];

        $this->assertArrayHasKey( 'marketplace', $modulesMenu[ 'childrens' ] );
    }

    public function test_marketplace_page_redirects_to_modules_list_when_disabled(): void
    {
        $this->get( ns()->route( 'ns.dashboard.modules-marketplace' ) )
            ->assertRedirect( ns()->route( 'ns.dashboard.modules-list' ) );
    }

    public function test_modules_page_tells_the_vue_component_the_marketplace_is_off(): void
    {
        $this->get( ns()->route( 'ns.dashboard.modules-list' ) )
            ->assertOk()
            ->assertSee( ':marketplace="false"', false );
    }
}
