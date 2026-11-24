<?php

/**
 * Mounted under "/api" by NexoPOS.
 */

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplEInvoice\Http\Controllers\EInvoiceController;
use Modules\MplEInvoice\Http\Controllers\EInvoiceSettingsController;

Route::middleware( [
    'auth:sanctum',
    SubstituteBindings::class,
] )->prefix( 'mpl-einvoice' )->group( function (): void {
    Route::get( '/settings', [ EInvoiceSettingsController::class, 'getForm' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.manage-settings' ) );

    Route::post( '/settings', [ EInvoiceSettingsController::class, 'save' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.manage-settings' ) );

    Route::get( '/documents/{document}', [ EInvoiceController::class, 'showDocument' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.read' ) );

    Route::get( '/documents/{document}/xml', [ EInvoiceController::class, 'downloadXml' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.read' ) );

    Route::match( [ 'get', 'post' ], '/documents/{document}/submit', [ EInvoiceController::class, 'submitDocument' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.submit' ) );

    Route::post( '/orders/{order}/generate', [ EInvoiceController::class, 'generateForOrder' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.submit' ) );
} );
