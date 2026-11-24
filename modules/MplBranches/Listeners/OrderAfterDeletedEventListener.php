<?php

namespace Modules\MplBranches\Listeners;

use App\Events\OrderAfterDeletedEvent;
use Illuminate\Support\Facades\Log;
use Modules\MplBranches\Models\BranchOrder;
use Throwable;

class OrderAfterDeletedEventListener
{
    /**
     * Removes the branch tag of a deleted order (application-managed cleanup).
     */
    public function handle( OrderAfterDeletedEvent $event ): void
    {
        try {
            if ( ! empty( $event->order->id ) ) {
                BranchOrder::where( 'order_id', $event->order->id )->get()->each( fn ( BranchOrder $tag ) => $tag->delete() );
            }
        } catch ( Throwable $exception ) {
            Log::error( 'MplBranches: unable to remove the branch tag of a deleted order: ' . $exception->getMessage() );
        }
    }
}
