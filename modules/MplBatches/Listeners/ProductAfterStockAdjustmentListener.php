<?php

namespace Modules\MplBatches\Listeners;

use App\Events\ProductAfterStockAdjustmentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\MplBatches\Services\BatchService;
use Throwable;

/**
 * Sales, returns and manual adjustments: allocate (FEFO) or restore batch quantities.
 */
class ProductAfterStockAdjustmentListener
{
    public function __construct( private BatchService $batchService )
    {
        // ...
    }

    public function handle( ProductAfterStockAdjustmentEvent $event ): void
    {
        if ( ! $this->batchService->isEnabled() ) {
            return;
        }

        try {
            /**
             * Savepoint: if anything fails, every batch change made for this
             * history is rolled back while the surrounding sale carries on.
             */
            DB::transaction( fn() => $this->batchService->handleStockAdjustment( $event->history ) );
        } catch ( Throwable $exception ) {
            /**
             * A batch bookkeeping issue must never break a sale or a refund.
             */
            Log::error( 'MplBatches: unable to update batches for product history #' . $event->history->id . ': ' . $exception->getMessage() );
        }
    }
}
