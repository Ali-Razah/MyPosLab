<?php

namespace Modules\MplBatches\Listeners;

use App\Events\ProductHistoryAfterCreatedEvent;
use App\Models\ProductHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MplBatches\Services\BatchService;
use Throwable;

/**
 * Procurement stocking only records a "procured" history (no stock adjustment
 * event), so batches are created from that history.
 */
class ProductHistoryAfterCreatedListener
{
    public function __construct( private BatchService $batchService )
    {
        // ...
    }

    public function handle( ProductHistoryAfterCreatedEvent $event ): void
    {
        $history = $event->productHistory;

        if (
            $history->operation_type !== ProductHistory::ACTION_STOCKED ||
            empty( $history->procurement_product_id ) ||
            ! $this->batchService->isEnabled()
        ) {
            return;
        }

        try {
            DB::transaction( fn() => $this->batchService->createFromStockedHistory( $history ) );
        } catch ( Throwable $exception ) {
            /**
             * Batch tracking must never block receiving stock.
             */
            Log::error( 'MplBatches: unable to create a batch for procurement product #' . $history->procurement_product_id . ': ' . $exception->getMessage() );
        }
    }
}
