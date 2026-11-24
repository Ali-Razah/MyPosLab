<?php

namespace Modules\MplEInvoice\Filters;

use App\Classes\CrudForm;
use App\Classes\FormInput;
use App\Crud\CustomerCrud;
use App\Models\Customer;
use App\Services\Helper;
use Illuminate\Support\Facades\Auth;
use Modules\MplEInvoice\Models\EInvoiceCustomerProfile;
use Modules\MplEInvoice\Services\EInvoiceSettings;
use Modules\MplEInvoice\Services\TrnValidator;

/**
 * Extends the core customer CRUD form with a "Tax Details (UAE)" tab used
 * to store the buyer TRN and legal identity required for B2B tax invoices.
 */
class CustomerCrudFilter
{
    const TAB = 'mpl_einvoice';

    /**
     * Form field name => profile attribute.
     */
    const FIELDS = [
        'mpl_einv_trn' => 'trn',
        'mpl_einv_legal_name' => 'legal_name',
        'mpl_einv_address' => 'address',
        'mpl_einv_city' => 'city',
        'mpl_einv_emirate' => 'emirate',
        'mpl_einv_peppol_id' => 'peppol_id',
    ];

    /**
     * @param  array<string, mixed> $form
     * @param  array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function injectTaxTab( array $form, array $context = [] ): array
    {
        $entry = $context[ 'model' ] ?? null;
        $profile = $entry instanceof Customer
            ? EInvoiceCustomerProfile::where( 'customer_id', $entry->id )->first()
            : null;

        $form[ 'tabs' ][ self::TAB ] = CrudForm::tab(
            identifier: self::TAB,
            label: __m( 'Tax Details (UAE)', 'MplEInvoice' ),
            fields: [
                FormInput::text(
                    label: __m( 'Buyer TRN', 'MplEInvoice' ),
                    name: 'mpl_einv_trn',
                    value: $profile->trn ?? '',
                    validation: TrnValidator::RULE,
                    description: __m( 'The 15 digits Tax Registration Number of a VAT registered business. When provided, sales to this customer produce a full (B2B) tax invoice instead of a simplified one.', 'MplEInvoice' ),
                ),
                FormInput::text(
                    label: __m( 'Legal Name', 'MplEInvoice' ),
                    name: 'mpl_einv_legal_name',
                    value: $profile->legal_name ?? '',
                    validation: 'nullable|max:255',
                    description: __m( 'Registered legal name of the buyer as it must appear on tax invoices.', 'MplEInvoice' ),
                ),
                FormInput::text(
                    label: __m( 'Address', 'MplEInvoice' ),
                    name: 'mpl_einv_address',
                    value: $profile->address ?? '',
                    validation: 'nullable|max:255',
                    description: __m( 'Street address of the buyer printed on the tax invoice and sent to the ASP.', 'MplEInvoice' ),
                ),
                FormInput::text(
                    label: __m( 'City', 'MplEInvoice' ),
                    name: 'mpl_einv_city',
                    value: $profile->city ?? '',
                    validation: 'nullable|max:255',
                    description: __m( 'City of the buyer address.', 'MplEInvoice' ),
                ),
                FormInput::select(
                    label: __m( 'Emirate', 'MplEInvoice' ),
                    name: 'mpl_einv_emirate',
                    value: $profile->emirate ?? '',
                    options: Helper::kvToJsOptions( array_merge( [ '' => __m( 'Not defined', 'MplEInvoice' ) ], EInvoiceSettings::emirates() ) ),
                    description: __m( 'Emirate of the buyer, used as the address country subdivision.', 'MplEInvoice' ),
                ),
                FormInput::text(
                    label: __m( 'Peppol Endpoint ID', 'MplEInvoice' ),
                    name: 'mpl_einv_peppol_id',
                    value: $profile->peppol_id ?? '',
                    validation: 'nullable|max:100',
                    description: __m( 'Peppol participant identifier of the buyer. Leave empty to use the buyer TRN.', 'MplEInvoice' ),
                ),
            ],
        );

        return $form;
    }

    /**
     * Prevent the module fields from being written on the customer model.
     *
     * @param  array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    public static function stripModuleInputs( array $inputs ): array
    {
        return array_diff_key( $inputs, self::FIELDS, [ self::TAB => true ] );
    }

    /**
     * @param array<string, mixed> $inputs
     */
    public static function saveProfile( mixed $resource, array $inputs, mixed $entry ): void
    {
        if ( ! $resource instanceof CustomerCrud || ! $entry instanceof Customer ) {
            return;
        }

        $values = [];

        foreach ( self::FIELDS as $field => $attribute ) {
            $value = $inputs[ $field ] ?? data_get( $inputs, self::TAB . '.' . $field );

            if ( $value !== null || array_key_exists( $field, $inputs ) ) {
                $values[ $attribute ] = is_string( $value ) && trim( $value ) !== '' ? trim( $value ) : null;
            }
        }

        if ( empty( $values ) ) {
            return;
        }

        if ( array_key_exists( 'trn', $values ) ) {
            $values[ 'trn' ] = TrnValidator::normalize( $values[ 'trn' ] );
        }

        $profile = EInvoiceCustomerProfile::firstOrNew( [ 'customer_id' => $entry->id ] );
        $profile->fill( $values );
        $profile->country_code = $profile->country_code ?: 'AE';
        $profile->author_id = Auth::id();
        $profile->save();
    }
}
