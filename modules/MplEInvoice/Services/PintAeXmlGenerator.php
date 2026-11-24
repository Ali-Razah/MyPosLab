<?php

namespace Modules\MplEInvoice\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;

/**
 * Serialises an e-invoice breakdown into a UBL 2.1 Invoice / CreditNote
 * following the Peppol PINT-AE (UAE) specialisation.
 *
 * IMPORTANT: this generator implements the core PINT-AE structure (identifiers,
 * parties, VAT breakdown, monetary totals and lines). Final conformance MUST be
 * validated by the shop's chosen Accredited Service Provider (ASP) against the
 * official PINT-AE specification, schematron rules and code lists published for
 * the UAE e-invoicing programme (Ministerial Decisions 243 & 244 of 2025), as the
 * ASP may require additional UAE specific fields (e.g. transaction type flags).
 */
class PintAeXmlGenerator
{
    const CUSTOMIZATION_ID = 'urn:peppol:pint:billing-1@ae-1';

    const PROFILE_ID = 'urn:peppol:bis:billing';

    const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';

    const NS_CREDIT_NOTE = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';

    const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';

    const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    const TYPE_CODE_INVOICE = '380';

    const TYPE_CODE_CREDIT_NOTE = '381';

    private DOMDocument $dom;

    private string $currency = 'AED';

    /**
     * @param array{
     *     number: string,
     *     uuid: string,
     *     issued_at: string,
     *     is_credit_note: bool,
     *     is_simplified: bool,
     *     currency: string,
     *     seller: array<string, string>,
     *     buyer: array<string, string|null>,
     *     payment_means: array<int, array{code: string, name: string}>,
     *     billing_reference: array{number: string, issued_at: string}|null,
     *     order_reference: string|null,
     *     summary: array<string, mixed>
     * } $document
     */
    public function generate( array $document ): string
    {
        $this->dom = new DOMDocument( '1.0', 'UTF-8' );
        $this->dom->formatOutput = true;
        $this->currency = $document[ 'currency' ] ?: 'AED';

        $isCreditNote = $document[ 'is_credit_note' ];
        $summary = $document[ 'summary' ];
        $issuedAt = Carbon::parse( $document[ 'issued_at' ] );

        $root = $this->dom->createElementNS( $isCreditNote ? self::NS_CREDIT_NOTE : self::NS_INVOICE, $isCreditNote ? 'CreditNote' : 'Invoice' );
        $root->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC );
        $root->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC );
        $this->dom->appendChild( $root );

        $this->cbc( $root, 'CustomizationID', self::CUSTOMIZATION_ID );
        $this->cbc( $root, 'ProfileID', self::PROFILE_ID );
        $this->cbc( $root, 'ID', $document[ 'number' ] );
        $this->cbc( $root, 'UUID', $document[ 'uuid' ] );
        $this->cbc( $root, 'IssueDate', $issuedAt->format( 'Y-m-d' ) );
        $this->cbc( $root, 'IssueTime', $issuedAt->format( 'H:i:s' ) );
        $this->cbc( $root, $isCreditNote ? 'CreditNoteTypeCode' : 'InvoiceTypeCode', $isCreditNote ? self::TYPE_CODE_CREDIT_NOTE : self::TYPE_CODE_INVOICE );

        if ( $document[ 'is_simplified' ] ) {
            $this->cbc( $root, 'Note', 'Simplified Tax Invoice' );
        }

        $this->cbc( $root, 'DocumentCurrencyCode', $this->currency );

        if ( ! empty( $document[ 'order_reference' ] ) ) {
            $orderReference = $this->cac( $root, 'OrderReference' );
            $this->cbc( $orderReference, 'ID', $document[ 'order_reference' ] );
        }

        if ( ! empty( $document[ 'billing_reference' ] ) ) {
            $billingReference = $this->cac( $root, 'BillingReference' );
            $invoiceReference = $this->cac( $billingReference, 'InvoiceDocumentReference' );
            $this->cbc( $invoiceReference, 'ID', $document[ 'billing_reference' ][ 'number' ] );
            $this->cbc( $invoiceReference, 'IssueDate', Carbon::parse( $document[ 'billing_reference' ][ 'issued_at' ] )->format( 'Y-m-d' ) );
        }

        $this->party( $this->cac( $root, 'AccountingSupplierParty' ), $document[ 'seller' ] );
        $this->party( $this->cac( $root, 'AccountingCustomerParty' ), $document[ 'buyer' ] );

        foreach ( $document[ 'payment_means' ] as $paymentMeans ) {
            $means = $this->cac( $root, 'PaymentMeans' );
            $code = $this->cbc( $means, 'PaymentMeansCode', $paymentMeans[ 'code' ] );

            if ( ! empty( $paymentMeans[ 'name' ] ) ) {
                $code->setAttribute( 'name', $paymentMeans[ 'name' ] );
            }
        }

        foreach ( $summary[ 'allowances' ] as $allowance ) {
            $this->allowanceCharge( $root, false, $allowance );
        }

        foreach ( $summary[ 'charges' ] as $charge ) {
            $this->allowanceCharge( $root, true, $charge );
        }

        $taxTotal = $this->cac( $root, 'TaxTotal' );
        $this->amount( $taxTotal, 'TaxAmount', $summary[ 'totals' ][ 'tax' ] );

        foreach ( $summary[ 'tax_subtotals' ] as $subtotal ) {
            $taxSubtotal = $this->cac( $taxTotal, 'TaxSubtotal' );
            $this->amount( $taxSubtotal, 'TaxableAmount', $subtotal[ 'taxable' ] );
            $this->amount( $taxSubtotal, 'TaxAmount', $subtotal[ 'tax' ] );
            $this->taxCategory( $taxSubtotal, 'TaxCategory', $subtotal[ 'category' ], $subtotal[ 'rate' ], $subtotal[ 'exemption_reason' ] ?? null );
        }

        $totals = $summary[ 'totals' ];
        $monetaryTotal = $this->cac( $root, 'LegalMonetaryTotal' );
        $this->amount( $monetaryTotal, 'LineExtensionAmount', $totals[ 'line_extension' ] );
        $this->amount( $monetaryTotal, 'TaxExclusiveAmount', $totals[ 'tax_exclusive' ] );
        $this->amount( $monetaryTotal, 'TaxInclusiveAmount', $totals[ 'tax_inclusive' ] );

        if ( (float) $totals[ 'allowance_total' ] !== 0.0 ) {
            $this->amount( $monetaryTotal, 'AllowanceTotalAmount', $totals[ 'allowance_total' ] );
        }

        if ( (float) $totals[ 'charge_total' ] !== 0.0 ) {
            $this->amount( $monetaryTotal, 'ChargeTotalAmount', $totals[ 'charge_total' ] );
        }

        if ( (float) $totals[ 'rounding' ] !== 0.0 ) {
            $this->amount( $monetaryTotal, 'PayableRoundingAmount', $totals[ 'rounding' ] );
        }

        $this->amount( $monetaryTotal, 'PayableAmount', $totals[ 'payable' ] );

        foreach ( $summary[ 'lines' ] as $line ) {
            $this->line( $root, $line, $isCreditNote );
        }

        return $this->dom->saveXML();
    }

    /**
     * @param array<string, string|null> $details
     */
    private function party( DOMElement $wrapper, array $details ): void
    {
        $party = $this->cac( $wrapper, 'Party' );

        if ( ! empty( $details[ 'endpoint_id' ] ) ) {
            $endpoint = $this->cbc( $party, 'EndpointID', $details[ 'endpoint_id' ] );
            $endpoint->setAttribute( 'schemeID', $details[ 'endpoint_scheme' ] ?: EInvoiceSettings::DEFAULT_PEPPOL_SCHEME );
        }

        if ( ! empty( $details[ 'name' ] ) ) {
            $partyName = $this->cac( $party, 'PartyName' );
            $this->cbc( $partyName, 'Name', $details[ 'name' ] );
        }

        $address = $this->cac( $party, 'PostalAddress' );

        if ( ! empty( $details[ 'address' ] ) ) {
            $this->cbc( $address, 'StreetName', $details[ 'address' ] );
        }

        if ( ! empty( $details[ 'city' ] ) ) {
            $this->cbc( $address, 'CityName', $details[ 'city' ] );
        }

        if ( ! empty( $details[ 'emirate' ] ) ) {
            $this->cbc( $address, 'CountrySubentity', $details[ 'emirate' ] );
        }

        $country = $this->cac( $address, 'Country' );
        $this->cbc( $country, 'IdentificationCode', $details[ 'country' ] ?: 'AE' );

        if ( ! empty( $details[ 'trn' ] ) ) {
            $taxScheme = $this->cac( $party, 'PartyTaxScheme' );
            $this->cbc( $taxScheme, 'CompanyID', $details[ 'trn' ] );
            $scheme = $this->cac( $taxScheme, 'TaxScheme' );
            $this->cbc( $scheme, 'ID', 'VAT' );
        }

        $legalEntity = $this->cac( $party, 'PartyLegalEntity' );
        $this->cbc( $legalEntity, 'RegistrationName', $details[ 'legal_name' ] ?: ( $details[ 'name' ] ?: '-' ) );
    }

    /**
     * @param array<string, mixed> $details
     */
    private function allowanceCharge( DOMElement $root, bool $isCharge, array $details ): void
    {
        $node = $this->cac( $root, 'AllowanceCharge' );
        $this->cbc( $node, 'ChargeIndicator', $isCharge ? 'true' : 'false' );

        if ( ! empty( $details[ 'reason_code' ] ) ) {
            $this->cbc( $node, 'AllowanceChargeReasonCode', $details[ 'reason_code' ] );
        }

        $this->cbc( $node, 'AllowanceChargeReason', $details[ 'reason' ] );
        $this->amount( $node, 'Amount', $details[ 'amount' ] );
        $this->taxCategory( $node, 'TaxCategory', $details[ 'category' ], $details[ 'rate' ], null );
    }

    /**
     * @param array<string, mixed> $line
     */
    private function line( DOMElement $root, array $line, bool $isCreditNote ): void
    {
        $node = $this->cac( $root, $isCreditNote ? 'CreditNoteLine' : 'InvoiceLine' );
        $this->cbc( $node, 'ID', $line[ 'id' ] );

        $quantity = $this->cbc( $node, $isCreditNote ? 'CreditedQuantity' : 'InvoicedQuantity', self::quantity( $line[ 'quantity' ] ) );
        $quantity->setAttribute( 'unitCode', $line[ 'unit_code' ] ?: 'C62' );

        $this->amount( $node, 'LineExtensionAmount', $line[ 'net_amount' ] );

        $item = $this->cac( $node, 'Item' );
        $this->cbc( $item, 'Name', $line[ 'name' ] !== '' ? $line[ 'name' ] : '-' );
        $this->taxCategory( $item, 'ClassifiedTaxCategory', $line[ 'category' ], $line[ 'rate' ], null );

        $price = $this->cac( $node, 'Price' );
        $priceAmount = $this->cbc( $price, 'PriceAmount', self::price( $line[ 'unit_price_net' ] ) );
        $priceAmount->setAttribute( 'currencyID', $this->currency );
    }

    private function taxCategory( DOMElement $parent, string $name, string $category, float $rate, ?string $exemptionReason ): void
    {
        $node = $this->cac( $parent, $name );
        $this->cbc( $node, 'ID', $category );
        $this->cbc( $node, 'Percent', self::money( $rate ) );

        if ( $exemptionReason !== null && $exemptionReason !== '' ) {
            $this->cbc( $node, 'TaxExemptionReason', $exemptionReason );
        }

        $scheme = $this->cac( $node, 'TaxScheme' );
        $this->cbc( $scheme, 'ID', 'VAT' );
    }

    private function amount( DOMElement $parent, string $name, float|int $value ): DOMElement
    {
        $node = $this->cbc( $parent, $name, self::money( $value ) );
        $node->setAttribute( 'currencyID', $this->currency );

        return $node;
    }

    private function cbc( DOMElement $parent, string $name, string $value ): DOMElement
    {
        $node = $this->dom->createElementNS( self::NS_CBC, 'cbc:' . $name );
        $node->appendChild( $this->dom->createTextNode( self::xmlText( $value ) ) );
        $parent->appendChild( $node );

        return $node;
    }

    private function cac( DOMElement $parent, string $name ): DOMElement
    {
        $node = $this->dom->createElementNS( self::NS_CAC, 'cac:' . $name );
        $parent->appendChild( $node );

        return $node;
    }

    /**
     * Drop invalid UTF-8 sequences and the characters XML 1.0 forbids (control
     * characters), which DOMDocument would otherwise serialise as-is.
     */
    public static function xmlText( string $value ): string
    {
        return (string) preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub( $value, 'UTF-8' ) );
    }

    public static function money( float|int $value ): string
    {
        return number_format( round( (float) $value, 2 ), 2, '.', '' );
    }

    /**
     * Unit prices keep 2 decimals unless more precision is needed to stay exact.
     */
    public static function price( float $value ): string
    {
        if ( abs( round( $value, 2 ) - $value ) < 0.0000001 ) {
            return self::money( $value );
        }

        return rtrim( number_format( $value, 6, '.', '' ), '0' );
    }

    public static function quantity( float $value ): string
    {
        if ( floor( $value ) === $value ) {
            return number_format( $value, 0, '.', '' );
        }

        return rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );
    }
}
