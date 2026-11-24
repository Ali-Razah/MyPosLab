<?php

namespace Modules\MplBatches\Jobs;

use App\Services\NotificationService;
use App\Traits\NsSerialize;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Services\BatchService;

/**
 * Daily: flags expired batches and sends one grouped expiry notification per day.
 */
class CheckBatchExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NsSerialize, Queueable;

    const NOTIFICATION_PREFIX = 'mplbatches.expiry-alert.';

    public function __construct()
    {
        $this->prepareSerialization();
    }

    public function handle( BatchService $batchService, NotificationService $notificationService ): void
    {
        if ( ! $batchService->isEnabled() ) {
            return;
        }

        $batchService->markExpiredBatches();

        $buckets = $batchService->getExpiryBuckets();
        $expired = $buckets[ 'expired' ];
        $expiring = $buckets[ 'critical' ]->merge( $buckets[ 'soon' ] );

        if ( $expired->isEmpty() && $expiring->isEmpty() ) {
            return;
        }

        $parts = [];

        if ( $expired->isNotEmpty() ) {
            $parts[] = sprintf(
                __m( '%s expired batch(es) still have stock (value at cost: %s).', 'MplBatches' ),
                $expired->count(),
                ns()->currency->define( $batchService->stockValue( $expired ) )->format()
            );
        }

        if ( $expiring->isNotEmpty() ) {
            $parts[] = sprintf(
                __m( '%s batch(es) expire within %s days, %s of them within %s days.', 'MplBatches' ),
                $expiring->count(),
                $buckets[ 'alert_days' ],
                $buckets[ 'critical' ]->count(),
                $buckets[ 'critical_days' ]
            );
        }

        $top = $expired->merge( $expiring )
            ->take( 3 )
            ->map( fn( Batch $batch ) => sprintf(
                '%s (%s, %s)',
                $batch->product->name ?? '#' . $batch->product_id,
                $batch->batch_number,
                $batch->expiry_date
            ) )
            ->join( ', ' );

        if ( $top !== '' ) {
            $parts[] = sprintf( __m( 'First: %s.', 'MplBatches' ), $top );
        }

        /**
         * One identifier per day: running the job again the same day updates
         * the existing notification instead of creating a new one.
         */
        $notificationService->create(
            title: __m( 'Batch expiry alert', 'MplBatches' ),
            description: implode( ' ', $parts ),
            url: ns()->route( 'mplbatches.report' ),
            identifier: self::NOTIFICATION_PREFIX . $buckets[ 'today' ],
            source: 'MplBatches',
        )->dispatchForPermissions( [ 'mplbatches.read' ] );

        ns()->option->set( BatchService::OPTION_LAST_ALERT, $buckets[ 'today' ] );
    }
}
