<?php

namespace Modules\MplEInvoice\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceSettings;
use Modules\MplEInvoice\Settings\EInvoiceSettingsPage;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

class AspSubmissionTest extends EInvoiceTestCase
{
    const ENDPOINT = 'https://asp.example.test/v1/einvoices';

    private function generatedDocument(): EInvoiceDocument
    {
        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );

        return $this->documentForOrder( $this->placePaidOrder(
            customer: $this->createCustomer( '100222333400003' ),
            lines: [ $this->line( 'Printer', 1, 840, $vat, 5 ) ],
            taxValue: 40
        ) );
    }

    private function configureAsp( string $autoSubmit = 'no' ): void
    {
        $this->configureModule( [
            EInvoiceSettings::ASP_ENDPOINT => self::ENDPOINT,
            EInvoiceSettings::AUTO_SUBMIT => $autoSubmit,
        ] );

        app()->make( EInvoiceSettings::class )->setAspToken( 'secret-asp-token-1234' );
    }

    public function test_manual_submission_success_marks_the_document_accepted(): void
    {
        $this->attemptAuthenticate();
        $this->configureAsp();

        Http::fake( [
            self::ENDPOINT => Http::response( [ 'status' => 'accepted', 'reference' => 'ASP-REF-001' ], 200 ),
        ] );

        $document = $this->generatedDocument();
        $this->assertSame( EInvoiceDocument::STATUS_GENERATED, $document->status );

        $this->json( 'POST', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )
            ->assertOk()
            ->assertJsonPath( 'status', 'success' );

        $document->refresh();
        $this->assertSame( EInvoiceDocument::STATUS_ACCEPTED, $document->status );
        $this->assertSame( 'ASP-REF-001', $document->asp_reference );
        $this->assertNotNull( $document->submitted_at );
        $this->assertSame( 1, $document->attempts );
        $this->assertNull( $document->error );

        Http::assertSent( function ( Request $request ) use ( $document ) {
            return $request->url() === self::ENDPOINT
                && $request->method() === 'POST'
                && $request->hasHeader( 'Authorization', 'Bearer secret-asp-token-1234' )
                && str_contains( $request->header( 'Content-Type' )[0] ?? '', 'application/xml' )
                && $request->body() === $document->xml;
        } );
    }

    public function test_failed_submission_keeps_the_xml_and_can_be_retried(): void
    {
        $this->attemptAuthenticate();
        $this->configureAsp();

        $document = $this->generatedDocument();
        $xml = $document->xml;

        Http::fakeSequence( self::ENDPOINT )
            ->push( [ 'message' => 'Service unavailable' ], 503 )
            ->push( [ 'status' => 'received', 'id' => 'ASP-REF-002' ], 202 );

        $this->json( 'GET', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )
            ->assertStatus( 422 )
            ->assertJsonPath( 'status', 'error' );

        $document->refresh();
        $this->assertSame( EInvoiceDocument::STATUS_FAILED, $document->status );
        $this->assertSame( 'Service unavailable', $document->error );
        $this->assertSame( $xml, $document->xml );
        $this->assertNull( $document->submitted_at );

        $this->json( 'GET', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )
            ->assertOk()
            ->assertJsonPath( 'status', 'success' );

        $document->refresh();
        $this->assertSame( EInvoiceDocument::STATUS_SUBMITTED, $document->status );
        $this->assertSame( 'ASP-REF-002', $document->asp_reference );
        $this->assertSame( 2, $document->attempts );
        $this->assertSame( $xml, $document->xml );

        /**
         * A document already handed to the ASP cannot be resubmitted.
         */
        $this->json( 'GET', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )->assertForbidden();
    }

    public function test_rejection_is_recorded_with_the_asp_errors(): void
    {
        $this->attemptAuthenticate();
        $this->configureAsp();

        Http::fake( [
            self::ENDPOINT => Http::response( [ 'status' => 'rejected', 'errors' => [ 'ibr-001: Buyer TRN unknown' ] ], 200 ),
        ] );

        $document = $this->generatedDocument();

        $this->json( 'POST', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )->assertStatus( 422 );

        $document->refresh();
        $this->assertSame( EInvoiceDocument::STATUS_REJECTED, $document->status );
        $this->assertSame( 'ibr-001: Buyer TRN unknown', $document->error );
        $this->assertStringContainsString( 'rejected', $document->response_payload );
    }

    public function test_auto_submit_sends_new_documents_through_the_queue(): void
    {
        $this->attemptAuthenticate();
        $this->configureAsp( 'yes' );

        Http::fake( [
            self::ENDPOINT => Http::response( [ 'status' => 'accepted', 'reference' => 'ASP-AUTO' ], 200 ),
        ] );

        $document = $this->generatedDocument();

        $this->assertSame( EInvoiceDocument::STATUS_ACCEPTED, $document->fresh()->status );
        $this->assertSame( 'ASP-AUTO', $document->fresh()->asp_reference );
        Http::assertSentCount( 1 );
    }

    public function test_only_http_and_https_asp_endpoints_are_accepted(): void
    {
        $this->attemptAuthenticate();
        $this->configureAsp();
        Http::fake();

        $document = $this->generatedDocument();
        ns()->option->set( EInvoiceSettings::ASP_ENDPOINT, 'ftp://asp.example.test/inbox' );

        $this->json( 'POST', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )->assertStatus( 422 );

        $this->assertSame( EInvoiceDocument::STATUS_FAILED, $document->fresh()->status );
        Http::assertNothingSent();

        $rules = ( new EInvoiceSettingsPage )->validateForm( request() );
        $key = 'asp.' . EInvoiceSettings::ASP_ENDPOINT;
        $validate = fn( string $url ) => Validator::make( [ 'asp' => [ EInvoiceSettings::ASP_ENDPOINT => $url ] ], [ $key => $rules[ $key ] ] )->passes();

        $this->assertTrue( $validate( self::ENDPOINT ) );
        $this->assertFalse( $validate( 'ftp://asp.example.test/inbox' ) );
        $this->assertFalse( $validate( 'file:///etc/passwd' ) );
    }

    public function test_submission_requires_a_valid_seller_trn(): void
    {
        $this->attemptAuthenticate();
        $this->configureAsp();
        Http::fake();

        $document = $this->generatedDocument();
        ns()->option->set( EInvoiceSettings::SELLER_TRN, '' );

        $this->json( 'POST', 'api/mpl-einvoice/documents/' . $document->id . '/submit' )->assertStatus( 422 );

        $this->assertSame( EInvoiceDocument::STATUS_FAILED, $document->fresh()->status );
        Http::assertNothingSent();
    }
}
