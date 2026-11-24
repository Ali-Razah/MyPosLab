<?php

namespace Modules\MplEInvoice\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceService;

/**
 * Submits an e-invoice to the configured Accredited Service Provider.
 */
class SubmitEInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct( public int $documentId ) {}

    public function handle( EInvoiceService $service ): void
    {
        $document = EInvoiceDocument::find( $this->documentId );

        if ( $document instanceof EInvoiceDocument && $document->isSubmittable() ) {
            $service->submit( $document );
        }
    }
}
