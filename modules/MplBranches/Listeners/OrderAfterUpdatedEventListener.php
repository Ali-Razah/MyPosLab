<?php

namespace Modules\MplBranches\Listeners;

use App\Events\OrderAfterUpdatedEvent;
use Illuminate\Support\Facades\Log;
use Modules\MplBranches\Services\BranchService;
use Throwable;

class OrderAfterUpdatedEventListener
{
    /**
     * The core assigns the cash register while saving payments, after the order
     * is created: the register branch then replaces the provisional branch.
     */
    public function handle( OrderAfterUpdatedEvent $event ): void
    {
        try {
            app()->make( BranchService::class )->retagOrderFromRegister( $event->order );
        } catch ( Throwable $exception ) {
            Log::error( 'MplBranches: order retagging failed: ' . $exception->getMessage() );
        }
    }
}
