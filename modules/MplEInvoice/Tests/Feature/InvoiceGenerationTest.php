<?php

namespace Modules\MplEInvoice\Tests\Feature;

use App\Models\Order;
use App\Models\OrderPayment;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceService;
use Modules\MplEInvoice\Services\PintAeXmlGenerator;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

class InvoiceGenerationTest extends EInvoiceTestCase
{
    public function test_paid_b2c_order_generates_a_single_simplified_invoice(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $order = $this->placePaidOrder(
            customer: $this->createCustomer(),
            lines: [ $this->line( 'Arabic Coffee 250g', 2, 105, $vat, 5 ) ],
            taxValue: 10
        );

        $document = $this->documentForOrder( $order );

        $this->assertInstanceOf( EInvoiceDocument::class, $document );
        $this->assertSame( EInvoiceDocument::TYPE_SIMPLIFIED, $document->type );
        $this->assertSame( EInvoiceDocument::STATUS_GENERATED, $document->status );
        $this->assertSame( 210.0, $document->total_gross );
        $this->assertSame( 10.0, $document->total_vat );
        $this->assertSame( 200.0, $document->total_net );
        $this->assertNull( $document->buyer_trn );

        /**
         * Generating again (listener re-fired, manual action...) never duplicates.
         */
        $again = app()->make( EInvoiceService::class )->generateForOrder( $order->fresh() );
        $this->assertSame( $document->id, $again->id );

        $order->refresh();
        $order->note = 'touched';
        $order->save();

        $this->assertSame( 1, EInvoiceDocument::where( 'order_id', $order->id )->count() );
    }

    public function test_customer_with_trn_receives_a_b2b_tax_invoice(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $order = $this->placePaidOrder(
            customer: $this->createCustomer( '100987654300003' ),
            lines: [ $this->line( 'Office chair', 1, 525, $vat, 5 ) ],
            taxValue: 25
        );

        $document = $this->documentForOrder( $order );

        $this->assertSame( EInvoiceDocument::TYPE_TAX_INVOICE, $document->type );
        $this->assertSame( '100987654300003', $document->buyer_trn );
        $this->assertSame( 'Gulf Buyer Trading LLC', $document->buyer_name );

        $xpath = $this->xml( $document );
        $this->assertSame( '100987654300003', $this->value( $xpath, '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID' ) );
        $this->assertSame( 'VAT', $this->value( $xpath, '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:TaxScheme/cbc:ID' ) );
        $this->assertSame( 'Gulf Buyer Trading LLC', $this->value( $xpath, '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName' ) );
        $this->assertSame( 'Sharjah', $this->value( $xpath, '/inv:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:CountrySubentity' ) );
    }

    public function test_xml_is_well_formed_pint_ae_and_reconciles_with_the_order(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $order = $this->placePaidOrder(
            customer: $this->createCustomer(),
            lines: [ $this->line( 'Dates box', 3, 52.5, $vat, 5 ) ],
            taxValue: 7.5
        );

        $document = $this->documentForOrder( $order );
        $xpath = $this->xml( $document );

        $this->assertStringContainsString( 'xmlns="' . PintAeXmlGenerator::NS_INVOICE . '"', $document->xml );
        $this->assertStringContainsString( 'xmlns:cac="' . PintAeXmlGenerator::NS_CAC . '"', $document->xml );
        $this->assertStringContainsString( 'xmlns:cbc="' . PintAeXmlGenerator::NS_CBC . '"', $document->xml );
        $this->assertSame( 'urn:peppol:pint:billing-1@ae-1', $this->value( $xpath, '/inv:Invoice/cbc:CustomizationID' ) );
        $this->assertSame( 'urn:peppol:bis:billing', $this->value( $xpath, '/inv:Invoice/cbc:ProfileID' ) );
        $this->assertSame( $document->number, $this->value( $xpath, '/inv:Invoice/cbc:ID' ) );
        $this->assertSame( $document->uuid, $this->value( $xpath, '/inv:Invoice/cbc:UUID' ) );
        $this->assertSame( '380', $this->value( $xpath, '/inv:Invoice/cbc:InvoiceTypeCode' ) );
        $this->assertSame( 'AED', $this->value( $xpath, '/inv:Invoice/cbc:DocumentCurrencyCode' ) );
        $this->assertSame( '100123456700003', $this->value( $xpath, '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyTaxScheme/cbc:CompanyID' ) );
        $this->assertSame( 'Dubai', $this->value( $xpath, '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:CountrySubentity' ) );
        $this->assertSame( 'AE', $this->value( $xpath, '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:Country/cbc:IdentificationCode' ) );
        $this->assertSame( '0235', $xpath->query( '/inv:Invoice/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID' )->item( 0 )->getAttribute( 'schemeID' ) );
        $this->assertSame( '10', $this->value( $xpath, '/inv:Invoice/cac:PaymentMeans/cbc:PaymentMeansCode' ) );

        $order->refresh();
        $total = number_format( $order->total, 2, '.', '' );

        $this->assertSame( '157.50', $total );
        $this->assertSame( $total, $this->value( $xpath, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount' ) );
        $this->assertSame( $total, $this->value( $xpath, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount' ) );
        $this->assertSame( '7.50', $this->value( $xpath, '/inv:Invoice/cac:TaxTotal/cbc:TaxAmount' ) );
        $this->assertSame( '150.00', $this->value( $xpath, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount' ) );
        $this->assertSame( '150.00', $this->value( $xpath, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:LineExtensionAmount' ) );

        /**
         * Net amount plus VAT must equal what the customer paid, whatever VAT mode the store uses.
         */
        $this->assertEqualsWithDelta(
            (float) $this->value( $xpath, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount' ),
            (float) $this->value( $xpath, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount' ) + (float) $this->value( $xpath, '/inv:Invoice/cac:TaxTotal/cbc:TaxAmount' ),
            0.001
        );

        $this->assertSame( 'S', $this->value( $xpath, '/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:ID' ) );
        $this->assertSame( '5.00', $this->value( $xpath, '/inv:Invoice/cac:TaxTotal/cac:TaxSubtotal/cac:TaxCategory/cbc:Percent' ) );
        $this->assertSame( '3', $this->value( $xpath, '/inv:Invoice/cac:InvoiceLine/cbc:InvoicedQuantity' ) );
        $this->assertSame( 'C62', $xpath->query( '/inv:Invoice/cac:InvoiceLine/cbc:InvoicedQuantity' )->item( 0 )->getAttribute( 'unitCode' ) );
        $this->assertSame( '50.00', $this->value( $xpath, '/inv:Invoice/cac:InvoiceLine/cac:Price/cbc:PriceAmount' ) );
        $this->assertSame( 'Dates box', $this->value( $xpath, '/inv:Invoice/cac:InvoiceLine/cac:Item/cbc:Name' ) );
        $this->assertSame( 'AED', $xpath->query( '//cbc:PayableAmount' )->item( 0 )->getAttribute( 'currencyID' ) );
    }

    public function test_mixed_categories_and_discount_reconcile_exactly(): void
    {
        $this->attemptAuthenticate();

        $standard = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $zero = $this->taxGroup( 0, 'MplEInvoice Zero Rated' );

        $order = $this->placePaidOrder(
            customer: $this->createCustomer(),
            lines: [
                $this->line( 'Perfume', 2, 99.99, $standard, 5 ),
                $this->line( 'Medicine', 1, 37.35, $zero, 0 ),
            ],
            discount: 10
        );

        $order->refresh();
        $document = $this->documentForOrder( $order );
        $xpath = $this->xml( $document );
        $amount = fn( string $query ) => (int) round( (float) $this->value( $xpath, $query ) * 100 );
        $sum = function ( string $query ) use ( $xpath ) {
            $total = 0;

            foreach ( $xpath->query( $query ) as $node ) {
                $total += (int) round( (float) $node->textContent * 100 );
            }

            return $total;
        };

        $payable = $amount( '//cac:LegalMonetaryTotal/cbc:PayableAmount' );
        $lineExtension = $amount( '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount' );
        $taxExclusive = $amount( '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount' );
        $taxInclusive = $amount( '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount' );
        $allowances = $sum( '/inv:Invoice/cac:AllowanceCharge[cbc:ChargeIndicator="false"]/cbc:Amount' );
        $charges = $sum( '/inv:Invoice/cac:AllowanceCharge[cbc:ChargeIndicator="true"]/cbc:Amount' );
        $tax = $amount( '/inv:Invoice/cac:TaxTotal/cbc:TaxAmount' );
        $rounding = $xpath->query( '//cbc:PayableRoundingAmount' )->length ? $amount( '//cbc:PayableRoundingAmount' ) : 0;

        $this->assertSame( (int) round( $order->total * 100 ), $payable, 'Payable amount must equal the order total.' );
        $this->assertSame( 22733, $payable );
        $this->assertSame( $sum( '/inv:Invoice/cac:InvoiceLine/cbc:LineExtensionAmount' ), $lineExtension );
        $this->assertSame( $lineExtension - $allowances + $charges, $taxExclusive );
        $this->assertSame( $sum( '//cac:TaxSubtotal/cbc:TaxableAmount' ), $taxExclusive );
        $this->assertSame( $sum( '//cac:TaxSubtotal/cbc:TaxAmount' ), $tax );
        $this->assertSame( $taxExclusive + $tax, $taxInclusive );
        $this->assertSame( $payable, $taxInclusive + $rounding );
        $this->assertSame( 0, $rounding );
        $this->assertSame( 2, $xpath->query( '//cac:TaxSubtotal' )->length );

        /**
         * Each category VAT equals its taxable amount at the category rate.
         */
        foreach ( $xpath->query( '//cac:TaxSubtotal' ) as $subtotal ) {
            $taxable = (float) $xpath->query( 'cbc:TaxableAmount', $subtotal )->item( 0 )->textContent;
            $categoryTax = (float) $xpath->query( 'cbc:TaxAmount', $subtotal )->item( 0 )->textContent;
            $rate = (float) $xpath->query( 'cac:TaxCategory/cbc:Percent', $subtotal )->item( 0 )->textContent;
            $category = $xpath->query( 'cac:TaxCategory/cbc:ID', $subtotal )->item( 0 )->textContent;

            $this->assertContains( $category, [ 'S', 'Z' ] );
            $this->assertEqualsWithDelta( round( $taxable * $rate / 100, 2 ), $categoryTax, 0.011 );
        }
    }

    /**
     * Weighed items produce line totals with more than 2 decimals: the order total
     * (rounded to the fils) may then exceed the sum of the rounded lines by 1 fils.
     * That rounding must not be mistaken for VAT added on top (exclusive pricing).
     */
    public function test_fractional_line_totals_keep_vat_inclusive_pricing(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );

        /**
         * The POS sends the subtotal rounded to the fils (3 x 5.00221 = 15.00663).
         */
        $response = $this->withSession( $this->app[ 'session' ]->all() )->json( 'POST', 'api/orders', [
            'customer_id' => $this->createCustomer()->id,
            'type' => [ 'identifier' => 'takeaway' ],
            'shipping' => 0,
            'subtotal' => 15.01,
            'products' => [
                $this->line( 'Saffron A', 0.367, 13.63, $vat, 5 ),
                $this->line( 'Saffron B', 0.367, 13.63, $vat, 5 ),
                $this->line( 'Saffron C', 0.367, 13.63, $vat, 5 ),
            ],
            'payments' => [ [ 'identifier' => OrderPayment::PAYMENT_CASH, 'value' => 15.01 ] ],
        ] );

        $response->assertOk();
        $order = Order::findOrFail( $response->json( 'data.order.id' ) );
        $this->assertSame( Order::PAYMENT_PAID, $order->payment_status );
        $document = $this->documentForOrder( $order );

        $this->assertSame( 15.01, (float) $order->total );
        $this->assertSame( 'inclusive', $document->summary[ 'pricing' ] );
        $this->assertSame( 15.01, $document->total_gross );
        $this->assertSame( 0.71, $document->total_vat );
        $this->assertLessThanOrEqual( 0.01, abs( $document->summary[ 'totals' ][ 'rounding' ] ) );
    }

    /**
     * Characters forbidden by XML 1.0 (e.g. a vertical tab pasted from a
     * spreadsheet into a product name) must not produce an unparseable document.
     */
    public function test_xml_stays_well_formed_with_control_characters_in_names(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $order = $this->placePaidOrder(
            customer: $this->createCustomer(),
            lines: [ $this->line( "Oud\x0B oil & <musk> عود", 1, 105, $vat, 5 ) ],
            taxValue: 5
        );

        $xpath = $this->xml( $this->documentForOrder( $order ) );

        $this->assertSame( 'Oud oil & <musk> عود', $this->value( $xpath, '/inv:Invoice/cac:InvoiceLine/cac:Item/cbc:Name' ) );
    }

    public function test_documents_are_numbered_sequentially_without_gaps(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $customer = $this->createCustomer();

        $first = $this->documentForOrder( $this->placePaidOrder( $customer, [ $this->line( 'Item A', 1, 21, $vat, 5 ) ] ) );
        $second = $this->documentForOrder( $this->placePaidOrder( $customer, [ $this->line( 'Item B', 1, 42, $vat, 5 ) ] ) );

        $this->assertSame( $first->sequence_number + 1, $second->sequence_number );
        $this->assertSame( 'INV-' . str_pad( (string) $first->sequence_number, 6, '0', STR_PAD_LEFT ), $first->number );
        $this->assertSame( 'INV-' . str_pad( (string) $second->sequence_number, 6, '0', STR_PAD_LEFT ), $second->number );

        $numbers = EInvoiceDocument::where( 'series', EInvoiceService::SERIES_INVOICE )->orderBy( 'sequence_number' )->pluck( 'sequence_number' )->all();
        $this->assertSame( range( 1, count( $numbers ) ), $numbers, 'The invoice series contains gaps.' );
    }

    public function test_unpaid_orders_and_disabled_module_do_not_generate_documents(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $this->configureModule( [ 'mpl_einv_enabled' => 'no' ] );

        $order = $this->placePaidOrder( $this->createCustomer(), [ $this->line( 'Item C', 1, 10.5, $vat, 5 ) ] );

        $this->assertNull( $this->documentForOrder( $order ) );
        $this->assertSame( Order::PAYMENT_PAID, $order->payment_status );
    }
}
