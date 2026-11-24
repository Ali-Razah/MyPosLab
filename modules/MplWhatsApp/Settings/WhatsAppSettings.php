<?php

namespace Modules\MplWhatsApp\Settings;

use App\Classes\FormInput;
use App\Classes\Hook;
use App\Events\SettingsSavedEvent;
use App\Services\Options;
use App\Services\SettingsPage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\WhatsAppConfig;

/**
 * WhatsApp receipts settings.
 *
 * Not autoloaded on the core "/dashboard/settings/{id}" route (which requires
 * "manage.options"): the module serves it on its own routes, protected by
 * "mplwhatsapp.settings".
 */
class WhatsAppSettings extends SettingsPage
{
    const IDENTIFIER = 'mplwhatsapp';

    const AUTOLOAD = false;

    public $form;

    public function __construct()
    {
        /**
         * @var WhatsAppConfig $config
         */
        $config = app()->make( WhatsAppConfig::class );
        $yesNo = [
            [ 'label' => __m( 'Yes', 'MplWhatsApp' ), 'value' => 'yes' ],
            [ 'label' => __m( 'No', 'MplWhatsApp' ), 'value' => 'no' ],
        ];

        $tokenDescription = $config->hasCloudAccessToken()
            ? sprintf(
                __m( 'A token is saved (%s). It is never displayed. Leave this field empty to keep it, or paste a new permanent token to replace it.', 'MplWhatsApp' ),
                $config->maskedCloudAccessToken()
            )
            : __m( 'Permanent access token of a Meta system user with the whatsapp_business_messaging permission. Stored encrypted and never displayed again.', 'MplWhatsApp' );

        $this->form = [
            'title' => __m( 'WhatsApp Receipts', 'MplWhatsApp' ),
            'description' => __m( 'Send order receipts to customers on WhatsApp instead of printing them.', 'MplWhatsApp' ),
            'tabs' => [
                'general' => [
                    'label' => __m( 'General', 'MplWhatsApp' ),
                    'fields' => [
                        FormInput::switch(
                            label: __m( 'Enable WhatsApp Receipts', 'MplWhatsApp' ),
                            name: WhatsAppConfig::ENABLED,
                            options: $yesNo,
                            value: ns()->option->get( WhatsAppConfig::ENABLED, 'no' ),
                            validation: 'required|in:yes,no',
                            description: __m( 'Shows the "Send via WhatsApp" actions on the POS and the Orders list, and enables the public receipt links.', 'MplWhatsApp' ),
                        ),
                        FormInput::text(
                            label: __m( 'Default Country Code', 'MplWhatsApp' ),
                            name: WhatsAppConfig::COUNTRY_CODE,
                            value: ns()->option->get( WhatsAppConfig::COUNTRY_CODE, WhatsAppConfig::DEFAULT_COUNTRY_CODE ),
                            validation: [ 'required', 'regex:/^\+?[1-9][0-9]{0,3}$/' ],
                            description: __m( 'Digits only, without "+" or "00" (971 for the UAE). Added to local numbers such as 0501234567.', 'MplWhatsApp' ),
                        ),
                        FormInput::select(
                            label: __m( 'Sending Mode', 'MplWhatsApp' ),
                            name: WhatsAppConfig::MODE,
                            options: [
                                [ 'label' => __m( 'WhatsApp link (free, opens WhatsApp on the cashier device)', 'MplWhatsApp' ), 'value' => WhatsAppMessage::MODE_LINK ],
                                [ 'label' => __m( 'WhatsApp Cloud API (Meta, sent automatically)', 'MplWhatsApp' ), 'value' => WhatsAppMessage::MODE_CLOUD_API ],
                            ],
                            value: ns()->option->get( WhatsAppConfig::MODE, WhatsAppMessage::MODE_LINK ),
                            validation: [ 'required', Rule::in( [ WhatsAppMessage::MODE_LINK, WhatsAppMessage::MODE_CLOUD_API ] ) ],
                            description: __m( 'Link mode opens wa.me with a prefilled message the cashier sends manually. Cloud API mode sends an approved template message from your WhatsApp Business number (Meta charges per conversation).', 'MplWhatsApp' ),
                        ),
                        FormInput::textarea(
                            label: __m( 'Message Template', 'MplWhatsApp' ),
                            name: WhatsAppConfig::MESSAGE_TEMPLATE,
                            value: $config->messageTemplate(),
                            validation: 'required|string|max:1000',
                            description: __m( 'Text used in link mode. Placeholders: {customer}, {store}, {order}, {total}, {link} (the receipt link). Cloud API mode uses your approved Meta template instead.', 'MplWhatsApp' ),
                        ),
                        FormInput::number(
                            label: __m( 'Receipt Link Validity (days)', 'MplWhatsApp' ),
                            name: WhatsAppConfig::LINK_VALIDITY_DAYS,
                            value: ns()->option->get( WhatsAppConfig::LINK_VALIDITY_DAYS, WhatsAppConfig::DEFAULT_LINK_VALIDITY_DAYS ),
                            validation: 'required|integer|min:1|max:365',
                            description: __m( 'Number of days the public receipt link stays valid. After that, the customer gets an "expired link" page.', 'MplWhatsApp' ),
                        ),
                        FormInput::switch(
                            label: __m( 'Prompt After Sale on POS', 'MplWhatsApp' ),
                            name: WhatsAppConfig::POS_PROMPT,
                            options: $yesNo,
                            value: ns()->option->get( WhatsAppConfig::POS_PROMPT, 'yes' ),
                            validation: 'required|in:yes,no',
                            description: __m( 'Opens the "Send via WhatsApp" popup right after a sale is completed on the POS. The POS header button stays available either way.', 'MplWhatsApp' ),
                        ),
                    ],
                ],
                'cloud' => [
                    'label' => __m( 'Cloud API', 'MplWhatsApp' ),
                    'fields' => [
                        FormInput::text(
                            label: __m( 'Phone Number ID', 'MplWhatsApp' ),
                            name: WhatsAppConfig::CLOUD_PHONE_NUMBER_ID,
                            value: ns()->option->get( WhatsAppConfig::CLOUD_PHONE_NUMBER_ID, '' ),
                            validation: [ 'nullable', 'regex:/^[0-9]+$/' ],
                            description: __m( 'Numeric ID of the sending number, from Meta WhatsApp Manager > API Setup (not the phone number itself).', 'MplWhatsApp' ),
                        ),
                        FormInput::password(
                            label: __m( 'Permanent Access Token', 'MplWhatsApp' ),
                            name: WhatsAppConfig::CLOUD_ACCESS_TOKEN,
                            value: '',
                            validation: 'nullable|string|max:1024',
                            description: $tokenDescription,
                        ),
                        FormInput::text(
                            label: __m( 'Template Name', 'MplWhatsApp' ),
                            name: WhatsAppConfig::CLOUD_TEMPLATE_NAME,
                            value: ns()->option->get( WhatsAppConfig::CLOUD_TEMPLATE_NAME, '' ),
                            validation: [ 'nullable', 'regex:/^[a-z0-9_]+$/' ],
                            description: __m( 'Name of the approved "utility" template, e.g. order_receipt. Lowercase letters, digits and underscores.', 'MplWhatsApp' ),
                        ),
                        FormInput::text(
                            label: __m( 'Template Language Code', 'MplWhatsApp' ),
                            name: WhatsAppConfig::CLOUD_TEMPLATE_LANGUAGE,
                            value: ns()->option->get( WhatsAppConfig::CLOUD_TEMPLATE_LANGUAGE, 'en' ),
                            validation: [ 'nullable', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/' ],
                            description: __m( 'Language the template was approved in, e.g. en, en_US or ar.', 'MplWhatsApp' ),
                        ),
                        FormInput::text(
                            label: __m( 'Template Body Parameters', 'MplWhatsApp' ),
                            name: WhatsAppConfig::CLOUD_TEMPLATE_PARAMS,
                            value: ns()->option->get( WhatsAppConfig::CLOUD_TEMPLATE_PARAMS, WhatsAppConfig::DEFAULT_TEMPLATE_PARAMS ),
                            validation: 'nullable|string|max:255',
                            description: __m( 'Comma separated values for the template variables {{1}}, {{2}}… in order. Use the placeholders {customer}, {store}, {order}, {total}, {link}. Default: {link}.', 'MplWhatsApp' ),
                        ),
                        FormInput::text(
                            label: __m( 'Graph API Version', 'MplWhatsApp' ),
                            name: WhatsAppConfig::CLOUD_API_VERSION,
                            value: ns()->option->get( WhatsAppConfig::CLOUD_API_VERSION, WhatsAppConfig::DEFAULT_API_VERSION ),
                            validation: [ 'nullable', 'regex:/^v[0-9]+(\.[0-9]+)?$/' ],
                            description: __m( 'Meta Graph API version used in the request URL, e.g. v21.0.', 'MplWhatsApp' ),
                        ),
                        FormInput::switch(
                            label: __m( 'Auto-send After Payment', 'MplWhatsApp' ),
                            name: WhatsAppConfig::AUTO_SEND,
                            options: $yesNo,
                            value: ns()->option->get( WhatsAppConfig::AUTO_SEND, 'no' ),
                            validation: 'required|in:yes,no',
                            description: __m( 'Cloud API mode only. Sends the receipt automatically, in the background, when an order becomes paid and the customer has a valid phone number.', 'MplWhatsApp' ),
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * Keeps the stored token when the masked field is left empty
     * and encrypts a new one.
     */
    public function saveForm( Request $request )
    {
        /**
         * @var WhatsAppConfig $config
         */
        $config = app()->make( WhatsAppConfig::class );
        $options = app()->make( Options::class );
        $token = trim( (string) $request->input( 'cloud.' . WhatsAppConfig::CLOUD_ACCESS_TOKEN, '' ) );
        $inputs = Hook::filter( SettingsPage::method( 'saveForm' ), $this->getPlainData( $request ) );

        /**
         * The token field is always rendered empty: an empty submission
         * must never delete the stored token.
         */
        unset( $inputs[ WhatsAppConfig::CLOUD_ACCESS_TOKEN ] );

        if ( isset( $inputs[ WhatsAppConfig::COUNTRY_CODE ] ) ) {
            $inputs[ WhatsAppConfig::COUNTRY_CODE ] = ltrim( (string) $inputs[ WhatsAppConfig::COUNTRY_CODE ], '+' );
        }

        $data = [];

        foreach ( $inputs as $key => $value ) {
            if ( $value === null ) {
                $options->delete( $key );
            } else {
                $options->set( $key, $value );
                $data[ $key ] = $value;
            }
        }

        if ( $token !== '' ) {
            $config->setCloudAccessToken( $token );
        }

        event( new SettingsSavedEvent(
            data: $data,
            settingsClass: get_class( $this )
        ) );

        return [
            'status' => 'success',
            'message' => __m( 'The WhatsApp settings have been saved.', 'MplWhatsApp' ),
        ];
    }
}
