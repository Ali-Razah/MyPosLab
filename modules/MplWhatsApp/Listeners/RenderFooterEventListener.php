<?php

namespace Modules\MplWhatsApp\Listeners;

use App\Events\RenderFooterEvent;
use Modules\MplWhatsApp\Services\WhatsAppConfig;

/**
 * Injects the POS / Orders list scripts on their routes only.
 */
class RenderFooterEventListener
{
    public function __construct( private WhatsAppConfig $config ) {}

    public static function isPosRoute( ?string $routeName ): bool
    {
        return $routeName !== null && $routeName === ns()->routeName( 'ns.dashboard.pos' );
    }

    public static function isOrdersRoute( ?string $routeName ): bool
    {
        return $routeName !== null && $routeName === ns()->routeName( 'ns.dashboard.orders' );
    }

    public static function isTargetRoute( ?string $routeName ): bool
    {
        return self::isPosRoute( $routeName ) || self::isOrdersRoute( $routeName );
    }

    public function handle( RenderFooterEvent $event ): void
    {
        if ( ! self::isTargetRoute( $event->routeName ) || ! $this->config->isEnabled() || ! ns()->allowedTo( 'mplwhatsapp.send' ) ) {
            return;
        }

        $event->output->addView( self::isPosRoute( $event->routeName ) ? 'MplWhatsApp::pos.footer' : 'MplWhatsApp::orders.footer', [
            'whatsAppConfig' => [
                'mode' => $this->config->mode(),
                'posPrompt' => $this->config->shouldPromptOnPos(),
                'autoSend' => $this->config->shouldAutoSend(),
                'apiBase' => ns()->url( '/api/mplwhatsapp' ),
            ],
        ] );
    }
}
