<?php

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplWhatsApp\Http\Controllers\WhatsAppReceiptController;
use Modules\MplWhatsApp\Http\Controllers\WhatsAppSettingsController;

Route::middleware( [
    SubstituteBindings::class,
    'auth:sanctum',
] )->prefix( 'mplwhatsapp' )->group( function (): void {
    Route::get( 'orders/{order}', [ WhatsAppReceiptController::class, 'context' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplwhatsapp.send' ) );

    /**
     * Per-user limit (own key prefix, so it is not shared with the core "api" throttle):
     * the phone number is free input, so this endpoint must not become a bulk sender.
     */
    Route::post( 'orders/{order}/send', [ WhatsAppReceiptController::class, 'send' ] )
        ->middleware( [
            NsRestrictMiddleware::arguments( 'mplwhatsapp.send' ),
            'throttle:10,1,mplwhatsapp-send',
        ] );

    Route::get( 'settings', [ WhatsAppSettingsController::class, 'getForm' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplwhatsapp.settings' ) );

    Route::post( 'settings', [ WhatsAppSettingsController::class, 'save' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mplwhatsapp.settings' ) );
} );
