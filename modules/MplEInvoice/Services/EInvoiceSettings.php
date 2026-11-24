<?php

namespace Modules\MplEInvoice\Services;

use App\Services\Options;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed access to the module options (all keys are prefixed with "mpl_einv_").
 */
class EInvoiceSettings
{
    const ENABLED = 'mpl_einv_enabled';

    const SELLER_NAME_EN = 'mpl_einv_seller_name_en';

    const SELLER_NAME_AR = 'mpl_einv_seller_name_ar';

    const SELLER_TRN = 'mpl_einv_seller_trn';

    const SELLER_ADDRESS = 'mpl_einv_seller_address';

    const SELLER_CITY = 'mpl_einv_seller_city';

    const SELLER_EMIRATE = 'mpl_einv_seller_emirate';

    const SELLER_COUNTRY = 'mpl_einv_seller_country';

    const PEPPOL_ID = 'mpl_einv_peppol_id';

    const PEPPOL_SCHEME = 'mpl_einv_peppol_scheme';

    const VAT_RATE = 'mpl_einv_vat_rate';

    const INVOICE_PREFIX = 'mpl_einv_invoice_prefix';

    const CREDIT_NOTE_PREFIX = 'mpl_einv_credit_note_prefix';

    const ASP_ENDPOINT = 'mpl_einv_asp_endpoint';

    const ASP_TOKEN = 'mpl_einv_asp_token';

    const AUTO_SUBMIT = 'mpl_einv_auto_submit';

    /**
     * Default Peppol Electronic Address Scheme used for UAE TIN/TRN based endpoints.
     */
    const DEFAULT_PEPPOL_SCHEME = '0235';

    public function __construct( private Options $options ) {}

    /**
     * Emirate codes (ISO 3166-2:AE) mapped to their English names.
     *
     * @return array<string, string>
     */
    public static function emirates(): array
    {
        return [
            'AZ' => 'Abu Dhabi',
            'DU' => 'Dubai',
            'SH' => 'Sharjah',
            'AJ' => 'Ajman',
            'UQ' => 'Umm Al Quwain',
            'RK' => 'Ras Al Khaimah',
            'FU' => 'Fujairah',
        ];
    }

    public static function emirateName( ?string $code ): string
    {
        return self::emirates()[ $code ] ?? (string) $code;
    }

    public function isEnabled(): bool
    {
        return $this->options->get( self::ENABLED, 'no' ) === 'yes';
    }

    public function autoSubmit(): bool
    {
        return $this->options->get( self::AUTO_SUBMIT, 'no' ) === 'yes';
    }

    public function get( string $key, mixed $default = null ): mixed
    {
        return $this->options->get( $key, $default );
    }

    public function sellerNameEn(): string
    {
        return (string) ( $this->options->get( self::SELLER_NAME_EN ) ?: $this->options->get( 'ns_store_name', '' ) );
    }

    public function sellerNameAr(): string
    {
        return (string) $this->options->get( self::SELLER_NAME_AR, '' );
    }

    public function sellerTrn(): string
    {
        return (string) $this->options->get( self::SELLER_TRN, '' );
    }

    public function sellerAddress(): string
    {
        return (string) ( $this->options->get( self::SELLER_ADDRESS ) ?: $this->options->get( 'ns_store_address', '' ) );
    }

    public function sellerCity(): string
    {
        return (string) ( $this->options->get( self::SELLER_CITY ) ?: $this->options->get( 'ns_store_city', '' ) );
    }

    public function sellerEmirate(): string
    {
        return (string) $this->options->get( self::SELLER_EMIRATE, 'DU' );
    }

    public function sellerCountry(): string
    {
        return 'AE';
    }

    public function peppolId(): string
    {
        return (string) ( $this->options->get( self::PEPPOL_ID ) ?: $this->sellerTrn() );
    }

    public function peppolScheme(): string
    {
        return (string) ( $this->options->get( self::PEPPOL_SCHEME ) ?: self::DEFAULT_PEPPOL_SCHEME );
    }

    public function defaultVatRate(): float
    {
        $rate = $this->options->get( self::VAT_RATE, 5 );

        return is_numeric( $rate ) ? (float) $rate : 5.0;
    }

    public function invoicePrefix(): string
    {
        return (string) $this->options->get( self::INVOICE_PREFIX, 'INV-' );
    }

    public function creditNotePrefix(): string
    {
        return (string) $this->options->get( self::CREDIT_NOTE_PREFIX, 'CN-' );
    }

    public function aspEndpoint(): string
    {
        return trim( (string) $this->options->get( self::ASP_ENDPOINT, '' ) );
    }

    /**
     * Returns the decrypted ASP API token.
     */
    public function aspToken(): string
    {
        $stored = (string) $this->options->get( self::ASP_TOKEN, '' );

        if ( $stored === '' ) {
            return '';
        }

        try {
            return Crypt::decryptString( $stored );
        } catch ( DecryptException $exception ) {
            return '';
        }
    }

    public function setAspToken( string $token ): void
    {
        $this->options->set( self::ASP_TOKEN, Crypt::encryptString( $token ) );
    }

    /**
     * A masked representation of the stored token, safe to display.
     */
    public function maskedAspToken(): string
    {
        $token = $this->aspToken();

        if ( $token === '' ) {
            return '';
        }

        return str_repeat( '•', 8 ) . mb_substr( $token, -4 );
    }
}
