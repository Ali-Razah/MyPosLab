<?php

namespace Modules\MplCustomerDisplay\Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;
use Modules\MplCustomerDisplay\Settings\MplCustomerDisplaySettings;
use Modules\MplCustomerDisplay\Tests\TestCase;

class CustomerDisplaySettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function settingsUrl(): string
    {
        return '/api/settings/' . MplCustomerDisplaySettings::IDENTIFIER;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function validPayload(): array
    {
        return [
            'general' => [
                CustomerDisplayConfig::OPTION_ENABLED => 'yes',
                CustomerDisplayConfig::OPTION_THEME => 'light',
                CustomerDisplayConfig::OPTION_SHOW_TAX => 'no',
                CustomerDisplayConfig::OPTION_THANK_YOU_DURATION => 9,
            ],
            'idle' => [
                CustomerDisplayConfig::OPTION_WELCOME_EN => 'Welcome to Al Noor Grocery',
                CustomerDisplayConfig::OPTION_WELCOME_AR => 'مرحباً بكم في بقالة النور',
                CustomerDisplayConfig::OPTION_PROMO_IMAGES => "https://cdn.example.test/promo-1.jpg\n/storage/promo-2.png",
                CustomerDisplayConfig::OPTION_SLIDE_INTERVAL => 12,
            ],
        ];
    }

    public function test_settings_form_exposes_described_fields(): void
    {
        Sanctum::actingAs( $this->adminUser(), [ '*' ] );

        $response = $this->getJson( $this->settingsUrl() )->assertOk();

        $tabs = $response->json( 'tabs' );

        $this->assertArrayHasKey( 'general', $tabs );
        $this->assertArrayHasKey( 'idle', $tabs );

        $names = [];

        foreach ( $tabs as $tab ) {
            foreach ( $tab[ 'fields' ] as $field ) {
                $names[] = $field[ 'name' ];
                $this->assertNotEmpty( $field[ 'description' ] ?? null, sprintf( 'The field "%s" must have a description.', $field[ 'name' ] ) );
            }
        }

        $this->assertEqualsCanonicalizing( [
            CustomerDisplayConfig::OPTION_ENABLED,
            CustomerDisplayConfig::OPTION_THEME,
            CustomerDisplayConfig::OPTION_SHOW_TAX,
            CustomerDisplayConfig::OPTION_THANK_YOU_DURATION,
            CustomerDisplayConfig::OPTION_WELCOME_EN,
            CustomerDisplayConfig::OPTION_WELCOME_AR,
            CustomerDisplayConfig::OPTION_PROMO_IMAGES,
            CustomerDisplayConfig::OPTION_SLIDE_INTERVAL,
        ], $names );
    }

    public function test_settings_can_be_saved(): void
    {
        Sanctum::actingAs( $this->adminUser(), [ '*' ] );

        $this->postJson( $this->settingsUrl(), $this->validPayload() )
            ->assertOk()
            ->assertJson( [ 'status' => 'success' ] );

        $config = app( CustomerDisplayConfig::class );

        $this->assertSame( 'light', $config->theme() );
        $this->assertFalse( $config->showTax() );
        $this->assertSame( 9, $config->thankYouDuration() );
        $this->assertSame( 12, $config->slideInterval() );
        $this->assertSame( 'Welcome to Al Noor Grocery', $config->welcomeEnglish() );
        $this->assertSame( 'مرحباً بكم في بقالة النور', $config->welcomeArabic() );
        $this->assertSame( [ 'https://cdn.example.test/promo-1.jpg', '/storage/promo-2.png' ], $config->promoImages() );
    }

    public function test_settings_validation_rejects_invalid_values(): void
    {
        Sanctum::actingAs( $this->adminUser(), [ '*' ] );

        $payload = $this->validPayload();
        $payload[ 'general' ][ CustomerDisplayConfig::OPTION_THEME ] = 'neon';
        $payload[ 'general' ][ CustomerDisplayConfig::OPTION_THANK_YOU_DURATION ] = 600;
        $payload[ 'idle' ][ CustomerDisplayConfig::OPTION_SLIDE_INTERVAL ] = 1;
        $payload[ 'idle' ][ CustomerDisplayConfig::OPTION_PROMO_IMAGES ] = "https://cdn.example.test/ok.jpg\njavascript:alert(1)";

        $this->postJson( $this->settingsUrl(), $payload )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( [
                'general.' . CustomerDisplayConfig::OPTION_THEME,
                'general.' . CustomerDisplayConfig::OPTION_THANK_YOU_DURATION,
                'idle.' . CustomerDisplayConfig::OPTION_SLIDE_INTERVAL,
                'idle.' . CustomerDisplayConfig::OPTION_PROMO_IMAGES,
            ] );

        $this->assertNotSame( 'neon', ns()->option->get( CustomerDisplayConfig::OPTION_THEME ) );
    }

    public function test_settings_page_requires_the_settings_permission(): void
    {
        $this->actingAs( $this->adminUser() )
            ->get( ns()->route( 'mplcd.settings' ) )
            ->assertOk()
            ->assertSee( '/api/settings/' . MplCustomerDisplaySettings::IDENTIFIER, false );

        $this->actingAs( $this->createUserWithRole( Role::STORECASHIER ) )
            ->get( ns()->route( 'mplcd.settings' ) )
            ->assertForbidden();
    }

    public function test_promo_image_parser_only_keeps_safe_urls(): void
    {
        $this->assertSame(
            [ 'https://a.test/1.png', '/local/2.png', 'http://b.test/3.jpg' ],
            CustomerDisplayConfig::parseImageUrls( "https://a.test/1.png\r\n\n  /local/2.png  \njavascript:alert(1)\n//evil.test/x.png\ndata:image/png;base64,AAA\nhttp://b.test/3.jpg\nhttps://a.test/1.png" )
        );
    }
}
