<?php

namespace Modules\MplEInvoice\Services;

use App\Models\Customer;
use App\Models\CustomerBillingAddress;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\MplEInvoice\Jobs\SubmitEInvoiceJob;
use Modules\MplEInvoice\Models\EInvoiceCustomerProfile;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Models\EInvoiceSequence;
use Modules\MplEInvoice\Services\Asp\AspClient;

/**
 * Owns the lifecycle of the e-invoice documents: generation from orders and
 * refunds (idempotent, gapless numbering) and submission to the ASP.
 */
class EInvoiceService
{
    const SERIES_INVOICE = 'invoice';

    const SERIES_CREDIT_NOTE = 'credit_note';

    const NUMBER_PADDING = 6;

    public function __construct(
        private EInvoiceSettings $settings,
        private EInvoiceCalculator $calculator,
        private PintAeXmlGenerator $generator,
        private AspClient $aspClient,
    ) {}

    public static function orderSourceKey( int $orderId ): string
    {
        return 'order:' . $orderId;
    }

    public static function refundSourceKey( int $refundId ): string
    {
        return 'refund:' . $refundId;
    }

    /**
     * Generate the tax invoice (B2B) or simplified tax invoice (B2C) of a paid order.
     * Returns the existing document when one was already generated.
     */
    public function generateForOrder( Order $order ): ?EInvoiceDocument
    {
        if ( ! $this->settings->isEnabled() || $order->payment_status !== Order::PAYMENT_PAID ) {
            return null;
        }

        $sourceKey = self::orderSourceKey( $order->id );

        $document = DB::transaction( function () use ( $order, $sourceKey ) {
            $existing = $this->lockSeries( self::SERIES_INVOICE, $sourceKey );

            if ( $existing instanceof EInvoiceDocument ) {
                return $existing;
            }

            $order->load( [ 'products', 'taxes', 'payments', 'customer' ] );

            $buyer = $this->buyerFor( $order->customer );
            $type = $buyer[ 'is_business' ] ? EInvoiceDocument::TYPE_TAX_INVOICE : EInvoiceDocument::TYPE_SIMPLIFIED;
            $summary = $this->calculator->fromOrder( $order );

            return $this->persist(
                series: self::SERIES_INVOICE,
                prefix: $this->settings->invoicePrefix(),
                sourceKey: $sourceKey,
                type: $type,
                order: $order,
                refund: null,
                original: null,
                buyer: $buyer,
                summary: $summary,
                paymentMeans: $this->paymentMeans( $order->payments->pluck( 'identifier' )->all() ),
            );
        } );

        $this->afterGenerated( $document );

        return $document;
    }

    /**
     * Generate the credit note of a refund, referencing the original invoice.
     */
    public function generateCreditNote( OrderRefund $refund ): ?EInvoiceDocument
    {
        if ( ! $this->settings->isEnabled() ) {
            return null;
        }

        $sourceKey = self::refundSourceKey( $refund->id );

        $document = DB::transaction( function () use ( $refund, $sourceKey ) {
            $existing = $this->lockSeries( self::SERIES_CREDIT_NOTE, $sourceKey );

            if ( $existing instanceof EInvoiceDocument ) {
                return $existing;
            }

            $refund->load( [ 'refunded_products.orderProduct', 'order.taxes', 'order.customer' ] );
            $order = $refund->order;

            $original = EInvoiceDocument::where( 'source_key', self::orderSourceKey( $order->id ) )->first();
            $buyer = $this->buyerFor( $order->customer );
            $summary = $this->calculator->fromRefund( $refund, $original?->summary[ 'pricing' ] ?? null );

            return $this->persist(
                series: self::SERIES_CREDIT_NOTE,
                prefix: $this->settings->creditNotePrefix(),
                sourceKey: $sourceKey,
                type: EInvoiceDocument::TYPE_CREDIT_NOTE,
                order: $order,
                refund: $refund,
                original: $original,
                buyer: $buyer,
                summary: $summary,
                paymentMeans: $this->paymentMeans( [ $refund->payment_method ] ),
            );
        } );

        $this->afterGenerated( $document );

        return $document;
    }

    /**
     * Submit a document to the ASP and record the outcome.
     * The XML is never modified nor discarded, whatever the outcome.
     */
    public function submit( EInvoiceDocument $document ): EInvoiceDocument
    {
        if ( ! TrnValidator::isValid( $this->settings->sellerTrn() ) ) {
            $document->status = EInvoiceDocument::STATUS_FAILED;
            $document->error = __m( 'The seller TRN is missing or invalid. Configure a 15 digits TRN in the e-invoicing settings.', 'MplEInvoice' );
            $document->save();

            return $document;
        }

        $result = $this->aspClient->submit( $document );

        $document->attempts = $document->attempts + 1;
        $document->status = $result->status;
        $document->response_payload = $result->payload;
        $document->asp_reference = $result->reference ?? $document->asp_reference;
        $document->error = $result->error;

        if ( $result->isSuccessful() ) {
            $document->submitted_at = self::now();
        }

        $document->save();

        return $document;
    }

    /**
     * Lock the number series for the duration of the transaction and return
     * the document already generated for the source, if any.
     */
    private function lockSeries( string $series, string $sourceKey ): ?EInvoiceDocument
    {
        $sequence = EInvoiceSequence::where( 'series', $series )->lockForUpdate()->first();

        if ( ! $sequence instanceof EInvoiceSequence ) {
            $sequence = new EInvoiceSequence;
            $sequence->series = $series;
            $sequence->last_number = 0;
            $sequence->save();
        }

        return EInvoiceDocument::where( 'source_key', $sourceKey )->first();
    }

    /**
     * @param array<string, mixed>                          $buyer
     * @param array<string, mixed>                          $summary
     * @param array<int, array{code: string, name: string}> $paymentMeans
     */
    private function persist( string $series, string $prefix, string $sourceKey, string $type, Order $order, ?OrderRefund $refund, ?EInvoiceDocument $original, array $buyer, array $summary, array $paymentMeans ): EInvoiceDocument
    {
        $sequence = EInvoiceSequence::where( 'series', $series )->lockForUpdate()->firstOrFail();
        $sequence->last_number = $sequence->last_number + 1;
        $sequence->save();

        $issuedAt = self::now();
        $number = $prefix . str_pad( (string) $sequence->last_number, self::NUMBER_PADDING, '0', STR_PAD_LEFT );
        $uuid = (string) Str::uuid();

        $billingReference = null;

        if ( $type === EInvoiceDocument::TYPE_CREDIT_NOTE ) {
            $billingReference = $original instanceof EInvoiceDocument
                ? [ 'number' => $original->number, 'issued_at' => $original->issued_at ]
                : [ 'number' => $order->code, 'issued_at' => (string) $order->created_at ];
        }

        $xml = $this->generator->generate( [
            'number' => $number,
            'uuid' => $uuid,
            'issued_at' => $issuedAt,
            'is_credit_note' => $type === EInvoiceDocument::TYPE_CREDIT_NOTE,
            'is_simplified' => $type === EInvoiceDocument::TYPE_SIMPLIFIED,
            'currency' => 'AED',
            'seller' => $this->seller(),
            'buyer' => $buyer,
            'payment_means' => $paymentMeans,
            'billing_reference' => $billingReference,
            'order_reference' => $order->code,
            'summary' => $summary,
        ] );

        $document = new EInvoiceDocument;
        $document->source_key = $sourceKey;
        $document->order_id = $order->id;
        $document->order_refund_id = $refund?->id;
        $document->original_document_id = $original?->id;
        $document->customer_id = $order->customer_id;
        $document->type = $type;
        $document->series = $series;
        $document->sequence_number = $sequence->last_number;
        $document->number = $number;
        $document->uuid = $uuid;
        $document->issued_at = $issuedAt;
        $document->currency = 'AED';
        $document->total_net = $summary[ 'totals' ][ 'tax_exclusive' ];
        $document->total_vat = $summary[ 'totals' ][ 'tax' ];
        $document->total_gross = $summary[ 'totals' ][ 'payable' ];
        $document->buyer_name = $buyer[ 'legal_name' ] ?: $buyer[ 'name' ];
        $document->buyer_trn = $buyer[ 'trn' ];
        $document->xml = $xml;
        $document->summary = array_merge( $summary, [
            'seller' => $this->seller(),
            'buyer' => $buyer,
            'billing_reference' => $billingReference,
            'order_code' => $order->code,
        ] );
        $document->status = EInvoiceDocument::STATUS_GENERATED;
        $document->author_id = Auth::id();
        $document->save();

        return $document;
    }

    private function afterGenerated( EInvoiceDocument $document ): void
    {
        if ( $document->wasRecentlyCreated && $this->settings->autoSubmit() && $this->settings->aspEndpoint() !== '' ) {
            SubmitEInvoiceJob::dispatch( $document->id )->afterCommit();
        }
    }

    /**
     * @return array<string, string>
     */
    public function seller(): array
    {
        $emirate = $this->settings->sellerEmirate();

        return [
            'name' => $this->settings->sellerNameEn(),
            'name_ar' => $this->settings->sellerNameAr(),
            'legal_name' => $this->settings->sellerNameEn(),
            'trn' => $this->settings->sellerTrn(),
            'address' => $this->settings->sellerAddress(),
            'city' => $this->settings->sellerCity(),
            'emirate' => EInvoiceSettings::emirateName( $emirate ),
            'emirate_code' => $emirate,
            'country' => $this->settings->sellerCountry(),
            'endpoint_id' => $this->settings->peppolId(),
            'endpoint_scheme' => $this->settings->peppolScheme(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buyerFor( ?Customer $customer ): array
    {
        $profile = $customer instanceof Customer
            ? EInvoiceCustomerProfile::where( 'customer_id', $customer->id )->first()
            : null;

        $billing = $customer instanceof Customer
            ? CustomerBillingAddress::where( 'customer_id', $customer->id )->first()
            : null;

        $name = $customer instanceof Customer
            ? trim( $customer->first_name . ' ' . $customer->last_name )
            : __m( 'Walk-in customer', 'MplEInvoice' );

        $isBusiness = $profile instanceof EInvoiceCustomerProfile && $profile->isBusiness();

        return [
            'is_business' => $isBusiness,
            'name' => $name,
            'legal_name' => $isBusiness ? ( $profile->legal_name ?: $name ) : null,
            'trn' => $isBusiness ? $profile->trn : null,
            'address' => $profile?->address ?: ( $billing->address_1 ?? null ),
            'city' => $profile?->city ?: ( $billing->city ?? null ),
            'emirate' => $profile?->emirate ? EInvoiceSettings::emirateName( $profile->emirate ) : null,
            'country' => $profile?->country_code ?: 'AE',
            'endpoint_id' => $isBusiness ? ( $profile->peppol_id ?: $profile->trn ) : null,
            'endpoint_scheme' => $this->settings->peppolScheme(),
        ];
    }

    /**
     * Map NexoPOS payment identifiers to UNCL4461 payment means codes.
     *
     * @param  array<int, string|null>                       $identifiers
     * @return array<int, array{code: string, name: string}>
     */
    public function paymentMeans( array $identifiers ): array
    {
        $means = collect( $identifiers )
            ->filter()
            ->unique()
            ->map( fn( string $identifier ) => match ( $identifier ) {
                OrderPayment::PAYMENT_CASH => [ 'code' => '10', 'name' => 'Cash' ],
                OrderPayment::PAYMENT_BANK => [ 'code' => '48', 'name' => 'Bank card' ],
                OrderPayment::PAYMENT_ACCOUNT => [ 'code' => '97', 'name' => 'Customer account' ],
                default => [ 'code' => 'ZZZ', 'name' => Str::headline( $identifier ) ],
            } )
            ->values()
            ->all();

        return empty( $means ) ? [ [ 'code' => '10', 'name' => 'Cash' ] ] : $means;
    }

    /**
     * Human readable label of a document type.
     */
    public static function typeLabel( string $type ): string
    {
        return match ( $type ) {
            EInvoiceDocument::TYPE_TAX_INVOICE => __m( 'Tax Invoice', 'MplEInvoice' ),
            EInvoiceDocument::TYPE_SIMPLIFIED => __m( 'Simplified Tax Invoice', 'MplEInvoice' ),
            EInvoiceDocument::TYPE_CREDIT_NOTE => __m( 'Credit Note', 'MplEInvoice' ),
            default => $type,
        };
    }

    public static function statusLabel( string $status ): string
    {
        return match ( $status ) {
            EInvoiceDocument::STATUS_GENERATED => __m( 'Generated', 'MplEInvoice' ),
            EInvoiceDocument::STATUS_SUBMITTED => __m( 'Submitted', 'MplEInvoice' ),
            EInvoiceDocument::STATUS_ACCEPTED => __m( 'Accepted', 'MplEInvoice' ),
            EInvoiceDocument::STATUS_REJECTED => __m( 'Rejected', 'MplEInvoice' ),
            EInvoiceDocument::STATUS_FAILED => __m( 'Failed', 'MplEInvoice' ),
            default => $status,
        };
    }

    /**
     * Format a datetime string for display in the store timezone.
     */
    public static function formatDate( ?string $date ): string
    {
        return $date ? Carbon::parse( $date )->format( 'Y-m-d H:i' ) : '';
    }

    /**
     * Current datetime in the store timezone (ns()->date is frozen at boot).
     */
    public static function now(): string
    {
        return Carbon::now( ns()->date->getTimezone() )->toDateTimeString();
    }
}
