<?php

namespace Modules\MplWhatsApp\Services;

use App\Classes\Hook;
use App\Exceptions\NotAllowedException;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Throwable;

/**
 * Builds WhatsApp receipt messages and delivers them (wa.me link or Meta Cloud API).
 */
class WhatsAppReceiptService
{
    public const GRAPH_URL = 'https://graph.facebook.com';

    public function __construct(
        private WhatsAppConfig $config,
        private PhoneNormalizer $phoneNormalizer,
        private ReceiptLinkService $receiptLinkService,
    ) {}

    /**
     * Raw phone stored on the order customer, if any.
     */
    public function customerPhone( Order $order ): ?string
    {
        $phone = trim( (string) ( $order->customer?->phone ?? '' ) );

        return $phone !== '' ? $phone : null;
    }

    public function customerName( Order $order ): string
    {
        $customer = $order->customer;
        $name = trim( ( $customer?->first_name ?? '' ) . ' ' . ( $customer?->last_name ?? '' ) );

        if ( $name === '' ) {
            $name = trim( (string) ( $customer?->username ?? '' ) );
        }

        return $name !== '' ? $name : __m( 'Customer', 'MplWhatsApp' );
    }

    /**
     * Resolves the destination: a typed number wins over the customer's phone.
     *
     * @throws InvalidArgumentException
     */
    public function resolvePhone( Order $order, ?string $phone = null ): string
    {
        $candidate = trim( (string) $phone ) !== '' ? $phone : $this->customerPhone( $order );

        if ( $candidate === null ) {
            throw new InvalidArgumentException( __m( 'The customer has no phone number. Please type the WhatsApp number to use.', 'MplWhatsApp' ) );
        }

        return $this->phoneNormalizer->normalize( $candidate, $this->config->countryCode() );
    }

    /**
     * @return array{customer: string, store: string, order: string, total: string, link: string}
     */
    public function placeholders( Order $order, string $link ): array
    {
        return Hook::filter( 'mplwhatsapp-placeholders', [
            'customer' => $this->customerName( $order ),
            'store' => (string) ns()->option->get( 'ns_store_name', config( 'app.name' ) ),
            'order' => (string) $order->code,
            'total' => (string) ns()->currency->define( $order->total ),
            'link' => $link,
        ], $order );
    }

    /**
     * @param array<string, string> $placeholders
     */
    public function fill( string $template, array $placeholders ): string
    {
        return strtr( $template, collect( $placeholders )->mapWithKeys( fn( $value, $key ) => [ '{' . $key . '}' => $value ] )->all() );
    }

    public function buildMessage( Order $order, string $link ): string
    {
        return $this->fill( $this->config->messageTemplate(), $this->placeholders( $order, $link ) );
    }

    public function waMeUrl( string $phone, string $message ): string
    {
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $message );
    }

    /**
     * Sends (or prepares) the receipt for an order using the configured mode.
     *
     * @return array{status: string, mode: string, phone: string, message: string, url?: string, message_id?: string|null, log_id: int}
     *
     * @throws NotAllowedException      when the module is disabled
     * @throws InvalidArgumentException when the phone number is missing or invalid
     */
    public function send( Order $order, ?string $phone = null, ?int $userId = null, string $trigger = WhatsAppMessage::TRIGGER_MANUAL ): array
    {
        if ( ! $this->config->isEnabled() ) {
            throw new NotAllowedException( __m( 'WhatsApp receipts are disabled. Enable them from the WhatsApp settings.', 'MplWhatsApp' ) );
        }

        $order->loadMissing( 'customer' );
        $destination = $this->resolvePhone( $order, $phone );
        $link = $this->receiptLinkService->make( $order );

        if ( $this->config->mode() === WhatsAppMessage::MODE_CLOUD_API ) {
            return $this->sendThroughCloudApi( $order, $destination, $link, $userId, $trigger );
        }

        $message = $this->buildMessage( $order, $link );

        $log = WhatsAppMessage::create( [
            'order_id' => $order->id,
            'phone' => $destination,
            'mode' => WhatsAppMessage::MODE_LINK,
            'trigger' => $trigger,
            'status' => WhatsAppMessage::STATUS_LINK_CREATED,
            'user_id' => $userId,
        ] );

        return [
            'status' => 'success',
            'mode' => WhatsAppMessage::MODE_LINK,
            'phone' => $destination,
            'url' => $this->waMeUrl( $destination, $message ),
            'message' => __m( 'WhatsApp is opening with the receipt message. Tap send in WhatsApp to deliver it.', 'MplWhatsApp' ),
            'log_id' => $log->id,
        ];
    }

    /**
     * @return array{status: string, mode: string, phone: string, message: string, message_id: string|null, log_id: int}
     */
    private function sendThroughCloudApi( Order $order, string $phone, string $link, ?int $userId, string $trigger ): array
    {
        $log = WhatsAppMessage::create( [
            'order_id' => $order->id,
            'phone' => $phone,
            'mode' => WhatsAppMessage::MODE_CLOUD_API,
            'trigger' => $trigger,
            'status' => WhatsAppMessage::STATUS_PENDING,
            'user_id' => $userId,
        ] );

        $missing = $this->config->missingCloudSettings();

        if ( ! empty( $missing ) ) {
            return $this->markFailed( $log, sprintf(
                __m( 'The WhatsApp Cloud API is not configured. Missing: %s.', 'MplWhatsApp' ),
                implode( ', ', $missing )
            ) );
        }

        $placeholders = $this->placeholders( $order, $link );

        $parameters = collect( $this->config->cloudTemplateParameters() )
            ->map( fn( string $parameter ) => [
                'type' => 'text',
                'text' => $this->fill( $parameter, $placeholders ),
            ] )
            ->values()
            ->all();

        $payload = Hook::filter( 'mplwhatsapp-cloud-payload', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $this->config->cloudTemplateName(),
                'language' => [ 'code' => $this->config->cloudTemplateLanguage() ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ],
                ],
            ],
        ], $order );

        $endpoint = sprintf(
            '%s/%s/%s/messages',
            self::GRAPH_URL,
            $this->config->cloudApiVersion(),
            rawurlencode( $this->config->cloudPhoneNumberId() )
        );

        try {
            $response = Http::withToken( $this->config->cloudAccessToken() )
                ->acceptJson()
                ->timeout( 20 )
                ->post( $endpoint, $payload );
        } catch ( ConnectionException $exception ) {
            return $this->markFailed( $log, sprintf(
                __m( 'Unable to reach the WhatsApp Cloud API: %s', 'MplWhatsApp' ),
                $exception->getMessage()
            ) );
        } catch ( Throwable $exception ) {
            report( $exception );

            return $this->markFailed( $log, $exception->getMessage() );
        }

        if ( $response->failed() ) {
            return $this->markFailed( $log, $this->providerError( $response ) );
        }

        $messageId = $response->json( 'messages.0.id' );

        $log->status = WhatsAppMessage::STATUS_SENT;
        $log->provider_message_id = is_string( $messageId ) ? $messageId : null;
        $log->error = null;
        $log->save();

        return [
            'status' => 'success',
            'mode' => WhatsAppMessage::MODE_CLOUD_API,
            'phone' => $phone,
            'message_id' => $log->provider_message_id,
            'message' => __m( 'The receipt has been sent on WhatsApp.', 'MplWhatsApp' ),
            'log_id' => $log->id,
        ];
    }

    private function providerError( Response $response ): string
    {
        $error = $response->json( 'error' );

        if ( is_array( $error ) ) {
            $details = $error[ 'error_data' ][ 'details' ] ?? null;

            return trim( sprintf(
                '%s%s%s',
                $error[ 'message' ] ?? __m( 'Unknown WhatsApp error.', 'MplWhatsApp' ),
                isset( $error[ 'code' ] ) ? ' (code ' . $error[ 'code' ] . ')' : '',
                is_string( $details ) ? ' — ' . $details : ''
            ) );
        }

        return sprintf( __m( 'The WhatsApp Cloud API answered with HTTP %s.', 'MplWhatsApp' ), $response->status() );
    }

    /**
     * @return array{status: string, mode: string, phone: string, message: string, message_id: null, log_id: int}
     */
    private function markFailed( WhatsAppMessage $log, string $error ): array
    {
        $log->status = WhatsAppMessage::STATUS_FAILED;
        $log->error = $error;
        $log->save();

        Log::warning( 'MplWhatsApp: receipt delivery failed.', [
            'order_id' => $log->order_id,
            'phone' => $log->phone,
            'log_id' => $log->id,
            'error' => $error,
        ] );

        return [
            'status' => 'error',
            'mode' => WhatsAppMessage::MODE_CLOUD_API,
            'phone' => $log->phone,
            'message_id' => null,
            'message' => $error,
            'log_id' => $log->id,
        ];
    }
}
