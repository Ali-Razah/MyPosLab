<?php

namespace Modules\MplEInvoice\Settings;

use App\Classes\FormInput;
use App\Classes\SettingForm;
use App\Services\Helper;
use App\Services\Options;
use App\Services\SettingsPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Modules\MplEInvoice\Services\EInvoiceSettings;
use Modules\MplEInvoice\Services\TrnValidator;

class EInvoiceSettingsPage extends SettingsPage
{
    const IDENTIFIER = 'mpleinvoice';

    const AUTOLOAD = true;

    public function __construct()
    {
        /**
         * @var EInvoiceSettings $settings
         */
        $settings = app()->make( EInvoiceSettings::class );
        $yesNo = Helper::kvToJsOptions( [
            'yes' => __m( 'Yes', 'MplEInvoice' ),
            'no' => __m( 'No', 'MplEInvoice' ),
        ] );
        $maskedToken = $settings->maskedAspToken();

        $this->form = SettingForm::form(
            title: __m( 'UAE E-Invoicing', 'MplEInvoice' ),
            description: __m( 'Configure the seller tax identity, the invoice numbering and the Accredited Service Provider (ASP) used to exchange PINT-AE e-invoices.', 'MplEInvoice' ),
            tabs: SettingForm::tabs(
                SettingForm::tab(
                    identifier: 'general',
                    label: __m( 'General', 'MplEInvoice' ),
                    fields: [
                        FormInput::switch(
                            label: __m( 'Enable E-Invoicing', 'MplEInvoice' ),
                            name: EInvoiceSettings::ENABLED,
                            options: $yesNo,
                            value: $settings->get( EInvoiceSettings::ENABLED, 'no' ),
                            validation: 'required|in:yes,no',
                            description: __m( 'When enabled, a tax invoice is generated for every paid order and a credit note for every refund.', 'MplEInvoice' ),
                        ),
                        FormInput::number(
                            label: __m( 'Default VAT Rate (%)', 'MplEInvoice' ),
                            name: EInvoiceSettings::VAT_RATE,
                            value: $settings->defaultVatRate(),
                            validation: 'required|numeric|min:0|max:100',
                            description: __m( 'VAT rate applied to products without a tax group and to delivery charges. The UAE standard rate is 5%.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Invoice Number Prefix', 'MplEInvoice' ),
                            name: EInvoiceSettings::INVOICE_PREFIX,
                            value: $settings->invoicePrefix(),
                            validation: 'nullable|max:20|regex:/^[A-Za-z0-9\-\/_]*$/',
                            description: __m( 'Prefix of the sequential tax invoice numbers (e.g. INV-000001). Changing it does not reset the counter.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Credit Note Number Prefix', 'MplEInvoice' ),
                            name: EInvoiceSettings::CREDIT_NOTE_PREFIX,
                            value: $settings->creditNotePrefix(),
                            validation: 'nullable|max:20|regex:/^[A-Za-z0-9\-\/_]*$/',
                            description: __m( 'Prefix of the sequential credit note numbers issued for refunds (e.g. CN-000001).', 'MplEInvoice' ),
                        ),
                    ],
                ),
                SettingForm::tab(
                    identifier: 'seller',
                    label: __m( 'Seller', 'MplEInvoice' ),
                    fields: [
                        FormInput::text(
                            label: __m( 'Legal Name (English)', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_NAME_EN,
                            value: $settings->get( EInvoiceSettings::SELLER_NAME_EN, '' ),
                            validation: 'nullable|max:255',
                            description: __m( 'Registered legal name of the business, as on the trade license.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Legal Name (Arabic)', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_NAME_AR,
                            value: $settings->get( EInvoiceSettings::SELLER_NAME_AR, '' ),
                            validation: 'nullable|max:255',
                            description: __m( 'Arabic legal name printed on the bilingual tax invoice.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Seller TRN', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_TRN,
                            value: $settings->get( EInvoiceSettings::SELLER_TRN, '' ),
                            validation: TrnValidator::RULE,
                            description: __m( 'The 15 digits Tax Registration Number issued by the Federal Tax Authority. Required to submit e-invoices.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Address', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_ADDRESS,
                            value: $settings->get( EInvoiceSettings::SELLER_ADDRESS, '' ),
                            validation: 'nullable|max:255',
                            description: __m( 'Street address of the business printed on invoices.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'City', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_CITY,
                            value: $settings->get( EInvoiceSettings::SELLER_CITY, '' ),
                            validation: 'nullable|max:255',
                            description: __m( 'City of the business address.', 'MplEInvoice' ),
                        ),
                        FormInput::select(
                            label: __m( 'Emirate', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_EMIRATE,
                            options: Helper::kvToJsOptions( EInvoiceSettings::emirates() ),
                            value: $settings->sellerEmirate(),
                            validation: 'required|in:' . implode( ',', array_keys( EInvoiceSettings::emirates() ) ),
                            description: __m( 'Emirate of the business, sent as the address country subdivision.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Country', 'MplEInvoice' ),
                            name: EInvoiceSettings::SELLER_COUNTRY,
                            value: 'AE',
                            disabled: true,
                            validation: 'nullable|in:AE',
                            description: __m( 'Country code of the seller. UAE e-invoicing only applies to businesses established in the UAE (AE).', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Peppol Endpoint ID', 'MplEInvoice' ),
                            name: EInvoiceSettings::PEPPOL_ID,
                            value: $settings->get( EInvoiceSettings::PEPPOL_ID, '' ),
                            validation: 'nullable|max:100',
                            description: __m( 'Peppol participant identifier assigned by your ASP. Leave empty to use the seller TRN.', 'MplEInvoice' ),
                        ),
                        FormInput::text(
                            label: __m( 'Peppol Endpoint Scheme', 'MplEInvoice' ),
                            name: EInvoiceSettings::PEPPOL_SCHEME,
                            value: $settings->peppolScheme(),
                            validation: 'nullable|max:10',
                            description: __m( 'Electronic address scheme (schemeID) of the endpoint identifier, as communicated by your ASP.', 'MplEInvoice' ),
                        ),
                    ],
                ),
                SettingForm::tab(
                    identifier: 'asp',
                    label: __m( 'ASP Integration', 'MplEInvoice' ),
                    fields: [
                        FormInput::text(
                            label: __m( 'ASP Endpoint URL', 'MplEInvoice' ),
                            name: EInvoiceSettings::ASP_ENDPOINT,
                            value: $settings->aspEndpoint(),
                            validation: 'nullable|url:http,https|max:500',
                            description: __m( 'HTTPS URL where the e-invoice XML is posted. Provided by your Accredited Service Provider.', 'MplEInvoice' ),
                        ),
                        FormInput::password(
                            label: __m( 'ASP API Key / Token', 'MplEInvoice' ),
                            name: EInvoiceSettings::ASP_TOKEN,
                            value: '',
                            validation: 'nullable|max:2000',
                            description: $maskedToken !== ''
                                ? sprintf( __m( 'A token is stored (%s). Leave empty to keep it, or type a new one to replace it. It is stored encrypted and sent as a bearer token.', 'MplEInvoice' ), $maskedToken )
                                : __m( 'API key or bearer token provided by your ASP. It is stored encrypted and never displayed again.', 'MplEInvoice' ),
                        ),
                        FormInput::switch(
                            label: __m( 'Auto Submit', 'MplEInvoice' ),
                            name: EInvoiceSettings::AUTO_SUBMIT,
                            options: $yesNo,
                            value: $settings->get( EInvoiceSettings::AUTO_SUBMIT, 'no' ),
                            validation: 'required|in:yes,no',
                            description: __m( 'Automatically queue every generated e-invoice for submission to the ASP. When disabled, submit them manually from the E-Invoices list.', 'MplEInvoice' ),
                        ),
                    ],
                ),
            )
        );
    }

    /**
     * Render the form through the module routes so access is governed by
     * the "mpleinvoice.manage-settings" permission.
     */
    public function getView()
    {
        $form = $this->getForm();

        return View::make( 'MplEInvoice::settings', [
            'title' => $form[ 'title' ],
            'description' => $form[ 'description' ],
            'url' => ns()->url( '/api/mpl-einvoice/settings' ),
        ] );
    }

    /**
     * Save the options, keeping the stored token when the field is left empty
     * and encrypting a new one.
     */
    public function saveForm( Request $request )
    {
        ns()->restrict( 'mpleinvoice.manage-settings' );

        /**
         * @var EInvoiceSettings $settings
         */
        $settings = app()->make( EInvoiceSettings::class );
        $token = $request->input( 'asp.' . EInvoiceSettings::ASP_TOKEN );
        $storedToken = $settings->get( EInvoiceSettings::ASP_TOKEN );

        $request->merge( [
            'asp' => array_diff_key( (array) $request->input( 'asp', [] ), [ EInvoiceSettings::ASP_TOKEN => true ] ),
            'seller' => array_merge( (array) $request->input( 'seller', [] ), [
                EInvoiceSettings::SELLER_TRN => TrnValidator::normalize( $request->input( 'seller.' . EInvoiceSettings::SELLER_TRN ) ),
                EInvoiceSettings::SELLER_COUNTRY => 'AE',
            ] ),
        ] );

        $result = parent::saveForm( $request );

        /**
         * The generic saver deletes options posted empty: restore the stored
         * (encrypted) token unless a new one was typed.
         */
        if ( is_string( $token ) && trim( $token ) !== '' ) {
            $settings->setAspToken( trim( $token ) );
        } elseif ( ! empty( $storedToken ) ) {
            app()->make( Options::class )->set( EInvoiceSettings::ASP_TOKEN, $storedToken );
        }

        return $result;
    }
}
