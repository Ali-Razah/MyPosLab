<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use App\Models\Role;
use App\Services\Options;
use Laravel\Sanctum\Sanctum;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;

final class SettingsTest extends MplWhatsAppTestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function payload( array $cloud = [] ): array
    {
        return [
            'general' => [
                WhatsAppConfig::ENABLED => 'yes',
                WhatsAppConfig::COUNTRY_CODE => '+971',
                WhatsAppConfig::MODE => 'cloud_api',
                WhatsAppConfig::MESSAGE_TEMPLATE => 'Receipt {order}: {link}',
                WhatsAppConfig::LINK_VALIDITY_DAYS => 15,
                WhatsAppConfig::POS_PROMPT => 'no',
            ],
            'cloud' => array_merge( [
                WhatsAppConfig::CLOUD_PHONE_NUMBER_ID => '1098765432',
                WhatsAppConfig::CLOUD_ACCESS_TOKEN => '',
                WhatsAppConfig::CLOUD_TEMPLATE_NAME => 'order_receipt',
                WhatsAppConfig::CLOUD_TEMPLATE_LANGUAGE => 'ar',
                WhatsAppConfig::CLOUD_TEMPLATE_PARAMS => '{link}',
                WhatsAppConfig::CLOUD_API_VERSION => 'v21.0',
                WhatsAppConfig::AUTO_SEND => 'yes',
            ], $cloud ),
        ];
    }

    public function test_admin_can_read_the_settings_form_and_the_token_is_never_exposed(): void
    {
        app( WhatsAppConfig::class )->setCloudAccessToken( 'EAAG-secret-token-9876' );
        $this->actingAsAdmin();

        $response = $this->getJson( '/api/mplwhatsapp/settings' );
        $response->assertOk();
        $this->assertEquals( '971', $response->json( 'tabs.general.fields.1.value' ) );

        $this->assertStringNotContainsString( 'EAAG-secret-token', $response->getContent() );
        $this->assertStringContainsString( '9876', $response->getContent(), 'The masked suffix helps identify the saved token.' );

        foreach ( $response->json( 'tabs' ) as $tab ) {
            foreach ( $tab[ 'fields' ] as $field ) {
                $this->assertNotEmpty( $field[ 'description' ], $field[ 'name' ] . ' needs a description.' );
            }
        }
    }

    public function test_admin_saves_settings_and_an_empty_token_keeps_the_stored_one(): void
    {
        app( WhatsAppConfig::class )->setCloudAccessToken( 'first-token' );
        $this->actingAsAdmin();

        $this->postJson( '/api/mplwhatsapp/settings', $this->payload() )->assertOk()->assertJsonPath( 'status', 'success' );

        $config = app( WhatsAppConfig::class );
        $this->assertSame( 'first-token', $config->cloudAccessToken() );
        $this->assertSame( '971', $config->countryCode() );
        $this->assertSame( 'cloud_api', $config->mode() );
        $this->assertSame( 15, $config->linkValidityDays() );
        $this->assertSame( 'Receipt {order}: {link}', $config->messageTemplate() );
        $this->assertTrue( $config->shouldAutoSend() );

        $this->postJson( '/api/mplwhatsapp/settings', $this->payload( [ WhatsAppConfig::CLOUD_ACCESS_TOKEN => 'second-token' ] ) )->assertOk();

        $this->assertSame( 'second-token', app( WhatsAppConfig::class )->cloudAccessToken() );
        $this->assertNotSame( 'second-token', app( Options::class )->get( WhatsAppConfig::CLOUD_ACCESS_TOKEN ), 'The token is stored encrypted.' );
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingAsAdmin();

        $payload = $this->payload();
        $payload[ 'general' ][ WhatsAppConfig::MODE ] = 'sms';
        $payload[ 'general' ][ WhatsAppConfig::LINK_VALIDITY_DAYS ] = 0;

        $this->postJson( '/api/mplwhatsapp/settings', $payload )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( [ 'general.' . WhatsAppConfig::MODE, 'general.' . WhatsAppConfig::LINK_VALIDITY_DAYS ] );
    }

    public function test_cashiers_cannot_manage_settings(): void
    {
        Sanctum::actingAs( $this->userWithRole( Role::STORECASHIER ), [ '*' ] );

        $this->getJson( '/api/mplwhatsapp/settings' )->assertForbidden();
        $this->postJson( '/api/mplwhatsapp/settings', $this->payload() )->assertForbidden();
    }

    public function test_settings_page_renders_for_admin(): void
    {
        $this->actingAs( $this->admin() );

        $this->get( '/dashboard/mplwhatsapp/settings' )
            ->assertOk()
            ->assertSee( '/api/mplwhatsapp/settings', false );
    }
}
