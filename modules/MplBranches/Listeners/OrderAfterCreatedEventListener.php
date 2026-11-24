<?php

namespace Modules\MplBranches\Listeners;

use App\Events\OrderAfterCreatedEvent;
use Illuminate\Support\Facades\Log;
use Modules\MplBranches\Services\BranchService;
use Throwable;

class OrderAfterCreatedEventListener
{
    /**
     * Records the branch of every new order. Failures are logged, never thrown.
     */
    public function handle( OrderAfterCreatedEvent $event ): void
    {
        try {
            app()->make( BranchService::class )->tagOrder( $event->order );
        } catch ( Throwable $exception ) {
            Log::error( 'MplBranches: order tagging failed: ' . $exception->getMessage() );
        }
    }
}
