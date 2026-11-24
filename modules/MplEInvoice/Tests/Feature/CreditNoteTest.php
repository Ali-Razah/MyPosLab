<?php

namespace Modules\MplEInvoice\Tests\Feature;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceService;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

class CreditNoteTest extends EInvoiceTestCase
{
    public function test_refund_generates_a_credit_note_referencing_the_original_invoice(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $order = $this->placePaidOrder(
            customer: $this->createCustomer( '100555666700003' ),
            lines: [ $this->line( 'Laptop bag', 3, 63, $vat, 5 ) ],
            taxValue: 9
        );

        $invoice = $this->documentForOrder( $order );
        $this->assertSame( EInvoiceDocument::TYPE_TAX_INVOICE, $invoice->type );

        $data = $this->refundFirstProduct( $order, 1 );
        $refund = OrderRefund::findOrFail( $data[ 'orderRefund' ][ 'id' ] );

        $creditNote = EInvoiceDocument::where( 'source_key', EInvoiceService::refundSourceKey( $refund->id ) )->first();

        $this->assertInstanceOf( EInvoiceDocument::class, $creditNote );
        $this->assertSame( EInvoiceDocument::TYPE_CREDIT_NOTE, $creditNote->type );
        $this->assertSame( $invoice->id, $creditNote->original_document_id );
        $this->assertSame( $refund->id, $creditNote->order_refund_id );
        $this->assertStringStartsWith( 'CN-', $creditNote->number );
        $this->assertSame( 63.0, $creditNote->total_gross );
        $this->assertSame( 3.0, $creditNote->total_vat );

        $xpath = $this->xml( $creditNote );
        $this->assertSame( 'urn:peppol:pint:billing-1@ae-1', $this->value( $xpath, '/cn:CreditNote/cbc:CustomizationID' ) );
        $this->assertSame( '381', $this->value( $xpath, '/cn:CreditNote/cbc:CreditNoteTypeCode' ) );
        $this->assertSame( $invoice->number, $this->value( $xpath, '/cn:CreditNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID' ) );
        $this->assertSame( '1', $this->value( $xpath, '/cn:CreditNote/cac:CreditNoteLine/cbc:CreditedQuantity' ) );
        $this->assertSame( number_format( (float) $refund->total, 2, '.', '' ), $this->value( $xpath, '/cn:CreditNote/cac:LegalMonetaryTotal/cbc:PayableAmount' ) );
        $this->assertSame( '60.00', $this->value( $xpath, '/cn:CreditNote/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount' ) );
        $this->assertSame( '3.00', $this->value( $xpath, '/cn:CreditNote/cac:TaxTotal/cbc:TaxAmount' ) );

        /**
         * The original invoice is left untouched and the credit note is unique.
         */
        $this->assertSame( $invoice->xml, $invoice->fresh()->xml );
        $again = app()->make( EInvoiceService::class )->generateCreditNote( $refund );
        $this->assertSame( $creditNote->id, $again->id );
        $this->assertSame( 1, EInvoiceDocument::where( 'order_refund_id', $refund->id )->count() );
    }

    /**
     * A partially paid order never received a tax invoice: refunding part of it
     * must not issue a credit note crediting VAT that was never invoiced.
     */
    public function test_refund_of_a_partially_paid_order_without_invoice_issues_no_credit_note(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $customer = $this->createCustomer();

        $response = $this->withSession( $this->app[ 'session' ]->all() )->json( 'POST', 'api/orders', [
            'customer_id' => $customer->id,
            'type' => [ 'identifier' => 'takeaway' ],
            'shipping' => 0,
            'subtotal' => 210,
            'products' => [ $this->line( 'Prayer rug', 2, 105, $vat, 5 ) ],
            'payments' => [ [ 'identifier' => OrderPayment::PAYMENT_CASH, 'value' => 50 ] ],
        ] );

        $response->assertOk();
        $order = Order::findOrFail( $response->json( 'data.order.id' ) );
        $this->assertSame( Order::PAYMENT_PARTIALLY, $order->payment_status );
        $this->assertNull( $this->documentForOrder( $order ) );

        $data = $this->refundFirstProduct( $order, 1 );

        $this->assertSame( 0, EInvoiceDocument::where( 'order_refund_id', $data[ 'orderRefund' ][ 'id' ] )->count() );
        $this->assertSame( 0, EInvoiceDocument::where( 'order_id', $order->id )->count() );
    }
}
