<?php

namespace Modules\MplCustomerDisplay\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;
use Modules\MplCustomerDisplay\Tests\TestCase;

class PosAssetsInjectionTest extends TestCase
{
    use DatabaseTransactions;

    private const POS_SCRIPT = '#modules/mplcustomerdisplay/build/assets/pos-[^"\']+\.js#';

    private const MODULE_STYLE = '#modules/mplcustomerdisplay/build/assets/style-[^"\']+\.css#';

    public function test_pos_page_loads_the_module_script_style_and_config(): void
    {
        ns()->option->set( CustomerDisplayConfig::OPTION_ENABLED, 'yes' );

        $content = $this->actingAs( $this->adminUser() )
            ->get( ns()->route( 'ns.dashboard.pos' ) )
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression( self::POS_SCRIPT, $content );
        $this->assertMatchesRegularExpression( self::MODULE_STYLE, $content );
        $this->assertStringContainsString( 'window.MplCustomerDisplayPos', $content );
        $this->assertStringContainsString( CustomerDisplayConfig::WINDOW_NAME, $content );

        $styleOffset = preg_match( self::MODULE_STYLE, $content, $matches, PREG_OFFSET_CAPTURE ) ? $matches[0][1] : PHP_INT_MAX;
        $this->assertLessThan( strpos( $content, '</head>' ), $styleOffset, 'The module stylesheet must be loaded in the document head.' );
    }

    public function test_other_dashboard_pages_do_not_load_the_pos_script(): void
    {
        $admin = $this->adminUser();

        foreach ( [ ns()->route( 'ns.dashboard.home' ), ns()->route( 'ns.dashboard.orders' ) ] as $url ) {
            $content = $this->actingAs( $admin )->get( $url )->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression( self::POS_SCRIPT, $content, $url );
            $this->assertStringNotContainsString( 'window.MplCustomerDisplayPos', $content, $url );
        }
    }

    public function test_disabled_display_is_not_injected_on_the_pos(): void
    {
        ns()->option->set( CustomerDisplayConfig::OPTION_ENABLED, 'no' );

        $content = $this->actingAs( $this->adminUser() )
            ->get( ns()->route( 'ns.dashboard.pos' ) )
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression( self::POS_SCRIPT, $content );
        $this->assertStringNotContainsString( 'window.MplCustomerDisplayPos', $content );
    }
}
