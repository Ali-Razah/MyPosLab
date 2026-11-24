<?php

namespace Modules\MplEInvoice\Tests\Feature;

use App\Models\CustomerGroup;
use Illuminate\Support\Facades\Crypt;
use Modules\MplEInvoice\Models\EInvoiceCustomerProfile;
use Modules\MplEInvoice\Services\EInvoiceSettings;
use Modules\MplEInvoice\Services\TrnValidator;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

class TrnValidationTest extends EInvoiceTestCase
{
    public function test_trn_validator_accepts_exactly_fifteen_digits(): void
    {
        $this->assertTrue( TrnValidator::isValid( '100123456700003' ) );
        $this->assertFalse( TrnValidator::isValid( '10012345670000' ) );
        $this->assertFalse( TrnValidator::isValid( '1001234567000031' ) );
        $this->assertFalse( TrnValidator::isValid( '10012345670000A' ) );
        $this->assertFalse( TrnValidator::isValid( '100-123-456-700' ) );
        $this->assertFalse( TrnValidator::isValid( '' ) );
        $this->assertFalse( TrnValidator::isValid( null ) );
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload( string $trn, string $token = '' ): array
    {
        return [
            'general' => [
                EInvoiceSettings::ENABLED => 'yes',
                EInvoiceSettings::VAT_RATE => 5,
                EInvoiceSettings::INVOICE_PREFIX => 'INV-',
                EInvoiceSettings::CREDIT_NOTE_PREFIX => 'CN-',
            ],
            'seller' => [
                EInvoiceSettings::SELLER_NAME_EN => 'MyPosLab Trading LLC',
                EInvoiceSettings::SELLER_NAME_AR => 'ماي بوس لاب',
                EInvoiceSettings::SELLER_TRN => $trn,
                EInvoiceSettings::SELLER_EMIRATE => 'AZ',
            ],
            'asp' => [
                EInvoiceSettings::ASP_ENDPOINT => 'https://asp.example.test/submit',
                EInvoiceSettings::ASP_TOKEN => $token,
                EInvoiceSettings::AUTO_SUBMIT => 'no',
            ],
        ];
    }

    public function test_settings_reject_an_invalid_seller_trn(): void
    {
        $this->attemptAuthenticate();

        foreach ( [ '12345', '1001234567000031', '10012345670000X' ] as $invalid ) {
            $this->json( 'POST', 'api/mpl-einvoice/settings', $this->settingsPayload( $invalid ) )
                ->assertStatus( 422 )
                ->assertJsonValidationErrors( [ 'seller.' . EInvoiceSettings::SELLER_TRN ] );
        }

        $this->json( 'POST', 'api/mpl-einvoice/settings', $this->settingsPayload( '100765432100003', 'my-new-token-9876' ) )
            ->assertOk()
            ->assertJsonPath( 'status', 'success' );

        $this->assertSame( '100765432100003', app()->make( EInvoiceSettings::class )->sellerTrn() );
        $this->assertSame( 'AZ', ns()->option->get( EInvoiceSettings::SELLER_EMIRATE ) );
    }

    public function test_asp_token_is_encrypted_masked_and_kept_when_left_empty(): void
    {
        $this->attemptAuthenticate();

        $this->json( 'POST', 'api/mpl-einvoice/settings', $this->settingsPayload( '100765432100003', 'my-new-token-9876' ) )->assertOk();

        $stored = ns()->option->get( EInvoiceSettings::ASP_TOKEN );
        $this->assertNotSame( 'my-new-token-9876', $stored );
        $this->assertSame( 'my-new-token-9876', Crypt::decryptString( $stored ) );

        $form = $this->json( 'GET', 'api/mpl-einvoice/settings' )->assertOk();
        $this->assertStringNotContainsString( 'my-new-token-9876', $form->getContent() );
        $this->assertStringContainsString( '9876', $form->getContent() );

        $this->json( 'POST', 'api/mpl-einvoice/settings', $this->settingsPayload( '100765432100003', '' ) )->assertOk();
        $this->assertSame( 'my-new-token-9876', app()->make( EInvoiceSettings::class )->aspToken() );
    }

    public function test_customer_form_exposes_and_validates_the_buyer_trn(): void
    {
        $this->attemptAuthenticate();

        $form = $this->json( 'GET', 'api/crud/ns.customers/form-config' );
        $form->assertOk();
        $this->assertArrayHasKey( 'mpl_einvoice', $form->json( 'form.tabs' ) );

        $payload = fn( string $trn ) => [
            'first_name' => 'Gulf Buyer',
            'general' => [
                'group_id' => CustomerGroup::first()->id,
                'email' => 'buyer-' . uniqid() . '@example.test',
            ],
            'mpl_einvoice' => [
                'mpl_einv_trn' => $trn,
                'mpl_einv_legal_name' => 'Gulf Buyer Trading LLC',
                'mpl_einv_city' => 'Abu Dhabi',
                'mpl_einv_emirate' => 'AZ',
            ],
        ];

        $this->json( 'POST', 'api/crud/ns.customers', $payload( '1234' ) )
            ->assertStatus( 422 );

        $response = $this->json( 'POST', 'api/crud/ns.customers', $payload( '100111222300003' ) );
        $response->assertOk()->assertJsonPath( 'status', 'success' );

        $customerId = $response->json( 'data.entry.id' );
        $profile = EInvoiceCustomerProfile::where( 'customer_id', $customerId )->first();

        $this->assertInstanceOf( EInvoiceCustomerProfile::class, $profile );
        $this->assertSame( '100111222300003', $profile->trn );
        $this->assertSame( 'Gulf Buyer Trading LLC', $profile->legal_name );
        $this->assertSame( 'AZ', $profile->emirate );
        $this->assertTrue( $profile->isBusiness() );

        $edit = $payload( '' );
        $edit[ 'general' ][ 'email' ] = 'buyer-' . uniqid() . '@example.test';
        $this->json( 'PUT', 'api/crud/ns.customers/' . $customerId, $edit )->assertOk();

        $this->assertNull( $profile->fresh()->trn );
        $this->assertFalse( $profile->fresh()->isBusiness() );
    }
}
