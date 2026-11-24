<?php

namespace Modules\MplBatches\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Models\BatchAllocation;
use Modules\MplBatches\Services\BatchService;

class BatchesApiController extends Controller
{
    public function __construct( private BatchService $batchService )
    {
        // ...
    }

    /**
     * Writes off the batch. Without "quantity", the whole remaining quantity is removed.
     */
    public function writeOff( Request $request, Batch $batch ): array
    {
        $validated = $request->validate( [
            'quantity' => 'nullable|numeric|gt:0',
            'reason' => 'nullable|string|max:150',
        ] );

        $batch = $this->batchService->writeOff(
            batch: $batch,
            quantity: isset( $validated[ 'quantity' ] ) ? (float) $validated[ 'quantity' ] : null,
            reason: (string) ( $validated[ 'reason' ] ?? '' )
        );

        return [
            'status' => 'success',
            'message' => sprintf( __m( 'The batch %s has been written off.', 'MplBatches' ), $batch->batch_number ),
            'data' => compact( 'batch' ),
        ];
    }

    public function allocations( Batch $batch ): array
    {
        return [
            'batch' => $batch,
            'allocations' => BatchAllocation::where( 'batch_id', $batch->id )->orderByDesc( 'id' )->get(),
        ];
    }

    /**
     * Small summary used by the dashboard widget and integrations.
     */
    public function summary(): array
    {
        $buckets = $this->batchService->getExpiryBuckets();

        return [
            'expired' => $buckets[ 'expired' ]->count(),
            'critical' => $buckets[ 'critical' ]->count(),
            'soon' => $buckets[ 'soon' ]->count(),
            'top' => $buckets[ 'expired' ]->merge( $buckets[ 'critical' ] )->merge( $buckets[ 'soon' ] )
                ->take( 5 )
                ->map( fn( Batch $batch ) => [
                    'id' => $batch->id,
                    'product' => $batch->product->name ?? null,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiry_date,
                    'quantity_remaining' => $batch->quantity_remaining,
                    'days_left' => $this->batchService->daysLeft( $batch ),
                ] )
                ->values(),
        ];
    }
}
