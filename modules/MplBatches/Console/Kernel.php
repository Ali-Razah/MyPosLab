<?php

namespace Modules\MplBatches\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Modules\MplBatches\Jobs\CheckBatchExpiryJob;

/**
 * Register Kernel
 **/
class Kernel extends ConsoleKernel
{
    /**
     * Define the module's command schedule.
     */
    public function schedule( Schedule $schedule ): void
    {
        $schedule->job( new CheckBatchExpiryJob )
            ->dailyAt( '06:00' )
            ->withoutOverlapping()
            ->name( 'mplbatches:check-expiry' );
    }
}
