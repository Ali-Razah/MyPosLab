<?php

namespace Modules\MplWhatsApp\Services;

use App\Services\Options;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Modules\MplWhatsApp\Models\WhatsAppMessage;

/**
 * Typed access to the module options (all keys are prefixed "mplwhatsapp_").
 */
class WhatsAppConfig
{
    public const ENABLED = 'mplwhatsapp_enabled';

    public const COUNTRY_CODE = 'mplwhatsapp_country_code';

    public const MODE = 'mplwhatsapp_mode';

    public const MESSAGE_TEMPLATE = 'mplwhatsapp_message_template';

    public const LINK_VALIDITY_DAYS = 'mplwhatsapp_link_validity_days';

    public const POS_PROMPT = 'mplwhatsapp_pos_prompt';

    public const AUTO_SEND = 'mplwhatsapp_auto_send';

    public const CLOUD_PHONE_NUMBER_ID = 'mplwhatsapp_cloud_phone_number_id';

    public const CLOUD_ACCESS_TOKEN = 'mplwhatsapp_cloud_access_token';

    public const CLOUD_TEMPLATE_NAME = 'mplwhatsapp_cloud_template_name';

    public const CLOUD_TEMPLATE_LANGUAGE = 'mplwhatsapp_cloud_template_language';

    public const CLOUD_TEMPLATE_PARAMS = 'mplwhatsapp_cloud_template_params';

    public const CLOUD_API_VERSION = 'mplwhatsapp_cloud_api_version';

    public const DEFAULT_COUNTRY_CODE = '971';

    public const DEFAULT_LINK_VALIDITY_DAYS = 30;

    public const DEFAULT_API_VERSION = 'v21.0';

    public const DEFAULT_TEMPLATE_PARAMS = '{link}';

    public const PLACEHOLDERS = [ '{customer}', '{store}', '{order}', '{total}', '{link}' ];

    public function __construct( private Options $options ) {}

    public static function defaultMessageTemplate(): string
    {
        return "Hello {customer}, thank you for shopping at {store}.\n"
            . "Your receipt for order {order} ({total}): {link}\n\n"
            . "مرحباً {customer}، شكراً لتسوقك من {store}.\n"
            . 'إيصال طلبك {order} ({total}): {link}';
    }

    public function isEnabled(): bool
    {
        return $this->options->get( self::ENABLED, 'no' ) === 'yes';
    }

    public function countryCode(): string
    {
        $code = preg_replace( '/\D+/', '', (string) $this->options->get( self::COUNTRY_CODE, self::DEFAULT_COUNTRY_CODE ) );

        return $code !== '' ? $code : self::DEFAULT_COUNTRY_CODE;
    }

    public function mode(): string
    {
        $mode = $this->options->get( self::MODE, WhatsAppMessage::MODE_LINK );

        return in_array( $mode, [ WhatsAppMessage::MODE_LINK, WhatsAppMessage::MODE_CLOUD_API ], true ) ? $mode : WhatsAppMessage::MODE_LINK;
    }

    public function messageTemplate(): string
    {
        $template = (string) $this->options->get( self::MESSAGE_TEMPLATE, '' );

        return trim( $template ) !== '' ? $template : self::defaultMessageTemplate();
    }

    public function linkValidityDays(): int
    {
        $days = (int) $this->options->get( self::LINK_VALIDITY_DAYS, self::DEFAULT_LINK_VALIDITY_DAYS );

        return $days > 0 ? $days : self::DEFAULT_LINK_VALIDITY_DAYS;
    }

    public function shouldPromptOnPos(): bool
    {
        return $this->options->get( self::POS_PROMPT, 'yes' ) === 'yes';
    }

    /**
     * Auto-send only exists for the Cloud API: the free link mode needs
     * a cashier device to open WhatsApp.
     */
    public function shouldAutoSend(): bool
    {
        return $this->isEnabled()
            && $this->mode() === WhatsAppMessage::MODE_CLOUD_API
            && $this->options->get( self::AUTO_SEND, 'no' ) === 'yes';
    }

    public function cloudPhoneNumberId(): string
    {
        return trim( (string) $this->options->get( self::CLOUD_PHONE_NUMBER_ID, '' ) );
    }

    public function cloudTemplateName(): string
    {
        return trim( (string) $this->options->get( self::CLOUD_TEMPLATE_NAME, '' ) );
    }

    public function cloudTemplateLanguage(): string
    {
        $language = trim( (string) $this->options->get( self::CLOUD_TEMPLATE_LANGUAGE, 'en' ) );

        return $language !== '' ? $language : 'en';
    }

    /**
     * @return array<int, string> placeholders sent as template body parameters, in order.
     */
    public function cloudTemplateParameters(): array
    {
        $raw = (string) $this->options->get( self::CLOUD_TEMPLATE_PARAMS, self::DEFAULT_TEMPLATE_PARAMS );

        $parameters = collect( explode( ',', $raw ) )
            ->map( fn( $parameter ) => trim( $parameter ) )
            ->filter( fn( $parameter ) => $parameter !== '' )
            ->values()
            ->all();

        return $parameters ?: [ self::DEFAULT_TEMPLATE_PARAMS ];
    }

    public function cloudApiVersion(): string
    {
        $version = trim( (string) $this->options->get( self::CLOUD_API_VERSION, self::DEFAULT_API_VERSION ) );

        return preg_match( '/^v\d+(\.\d+)?$/', $version ) ? $version : self::DEFAULT_API_VERSION;
    }

    /**
     * The access token is stored encrypted with the application key.
     */
    public function cloudAccessToken(): string
    {
        $stored = (string) $this->options->get( self::CLOUD_ACCESS_TOKEN, '' );

        if ( $stored === '' ) {
            return '';
        }

        try {
            return Crypt::decryptString( $stored );
        } catch ( DecryptException $exception ) {
            /**
             * A token saved before encryption (or with another APP_KEY)
             * is used as-is.
             */
            return $stored;
        }
    }

    public function setCloudAccessToken( string $token ): void
    {
        $this->options->set( self::CLOUD_ACCESS_TOKEN, Crypt::encryptString( trim( $token ) ) );
    }

    public function hasCloudAccessToken(): bool
    {
        return $this->cloudAccessToken() !== '';
    }

    public function maskedCloudAccessToken(): string
    {
        $token = $this->cloudAccessToken();

        return $token === '' ? '' : '••••' . substr( $token, -4 );
    }

    /**
     * @return array<int, string> the human readable list of missing Cloud API settings.
     */
    public function missingCloudSettings(): array
    {
        return array_values( array_filter( [
            $this->cloudPhoneNumberId() === '' ? __m( 'Phone Number ID', 'MplWhatsApp' ) : null,
            ! $this->hasCloudAccessToken() ? __m( 'Access Token', 'MplWhatsApp' ) : null,
            $this->cloudTemplateName() === '' ? __m( 'Template Name', 'MplWhatsApp' ) : null,
        ] ) );
    }
}
