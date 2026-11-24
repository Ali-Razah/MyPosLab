<?php

namespace Modules\MplCustomerDisplay\Tests\Feature;

use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;
use Modules\MplCustomerDisplay\Tests\TestCase;

/**
 * MyPosLab ships a light interface by default; the customer screen follows
 * unless the shop explicitly picks the dark theme.
 */
final class DefaultThemeTest extends TestCase
{
    public function test_display_defaults_to_the_light_theme(): void
    {
        ns()->option->delete( CustomerDisplayConfig::OPTION_THEME );

        $this->assertSame( 'light', app( CustomerDisplayConfig::class )->theme() );
    }

    public function test_an_unknown_theme_falls_back_to_light(): void
    {
        ns()->option->set( CustomerDisplayConfig::OPTION_THEME, 'neon' );

        $this->assertSame( 'light', app( CustomerDisplayConfig::class )->theme() );
    }
}
