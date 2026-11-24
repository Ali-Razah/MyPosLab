<?php

namespace Modules\MplBatches\Listeners;

use App\Events\ProcurementAfterSaveProductEvent;
use Modules\MplBatches\Services\BatchService;

/**
 * Captures the batch number typed in the procurement grid ("batch_number" column).
 */
class ProcurementAfterSaveProductListener
{
    public function __construct( private BatchService $batchService )
    {
        // ...
    }

    public function handle( ProcurementAfterSaveProductEvent $event ): void
    {
        if ( ! array_key_exists( 'batch_number', $event->data ) ) {
            return;
        }

        $this->batchService->rememberProcurementBatchNumber( $event->product, $event->data[ 'batch_number' ] );
    }
}
