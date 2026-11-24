<?php

/**
 * Authenticated dashboard routes. Loaded under "/dashboard" by Routes/web.php
 * and inside the store dashboard group by NsMultiStore when it is installed.
 */

use App\Http\Middleware\NsRestrictMiddleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\MplEInvoice\Http\Controllers\EInvoiceController;
use Modules\MplEInvoice\Http\Controllers\EInvoiceSettingsController;

Route::middleware( [
    SubstituteBindings::class,
] )->prefix( 'mpl-einvoice' )->group( function (): void {
    Route::get( '/documents', [ EInvoiceController::class, 'listDocuments' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.read' ) )
        ->name( ns()->routeName( 'mpleinvoice.dashboard.documents' ) );

    Route::get( '/documents/{document}/xml', [ EInvoiceController::class, 'downloadXml' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.read' ) )
        ->name( ns()->routeName( 'mpleinvoice.dashboard.documents-xml' ) );

    Route::get( '/documents/{document}/print', [ EInvoiceController::class, 'printDocument' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.read' ) )
        ->name( ns()->routeName( 'mpleinvoice.dashboard.documents-print' ) );

    Route::get( '/settings', [ EInvoiceSettingsController::class, 'show' ] )
        ->middleware( NsRestrictMiddleware::arguments( 'mpleinvoice.manage-settings' ) )
        ->name( ns()->routeName( 'mpleinvoice.dashboard.settings' ) );
} );
