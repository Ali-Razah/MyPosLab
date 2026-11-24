<?php

namespace Modules\MplBatches\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View as ContractView;
use Illuminate\Support\Facades\View;
use Modules\MplBatches\Crud\BatchCrud;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Models\BatchAllocation;
use Modules\MplBatches\Services\BatchService;
use Modules\MplBatches\Settings\MplBatchesSettings;

class BatchesController extends Controller
{
    public function __construct( private BatchService $batchService )
    {
        // ...
    }

    public function listBatches(): ContractView
    {
        return BatchCrud::table();
    }

    public function editBatch( Batch $batch ): ContractView
    {
        return BatchCrud::form( $batch, [
            'returnUrl' => ns()->route( 'mplbatches.batches' ),
            'submitUrl' => ns()->url( '/api/crud/' . BatchCrud::IDENTIFIER . '/' . $batch->id ),
            'submitMethod' => 'PUT',
        ] );
    }

    public function batchAllocations( Batch $batch ): ContractView
    {
        $batch->load( [ 'product:id,name,sku', 'unit:id,name', 'procurement:id,name' ] );

        $allocations = BatchAllocation::with( [ 'order:id,code' ] )
            ->where( 'batch_id', $batch->id )
            ->orderByDesc( 'id' )
            ->get();

        return View::make( 'MplBatches::allocations', [
            'title' => sprintf( __m( 'Batch %s — Sales & Movements', 'MplBatches' ), e( $batch->batch_number ) ),
            'description' => __m( 'Every sale, refund and adjustment that used this batch. Use it to contact customers during a recall.', 'MplBatches' ),
            'batch' => $batch,
            'allocations' => $allocations,
            'typeLabels' => [
                BatchAllocation::TYPE_SALE => __m( 'Sale', 'MplBatches' ),
                BatchAllocation::TYPE_REFUND => __m( 'Refund', 'MplBatches' ),
                BatchAllocation::TYPE_ADJUSTMENT => __m( 'Adjustment', 'MplBatches' ),
            ],
        ] );
    }

    public function expiryReport(): ContractView
    {
        return View::make( 'MplBatches::report', $this->getReportData() );
    }

    public function printExpiryReport(): ContractView
    {
        return View::make( 'MplBatches::report-print', $this->getReportData() );
    }

    public function settings()
    {
        return MplBatchesSettings::renderForm();
    }

    private function getReportData(): array
    {
        $buckets = $this->batchService->getExpiryBuckets();

        $sections = [
            [
                'key' => 'expired',
                'label' => __m( 'Expired (stock remaining)', 'MplBatches' ),
                'batches' => $buckets[ 'expired' ],
            ],
            [
                'key' => 'critical',
                'label' => sprintf( __m( 'Expiring within %s days', 'MplBatches' ), $buckets[ 'critical_days' ] ),
                'batches' => $buckets[ 'critical' ],
            ],
            [
                'key' => 'soon',
                'label' => sprintf( __m( 'Expiring within %s days', 'MplBatches' ), max( $buckets[ 'alert_days' ], $buckets[ 'critical_days' ] ) ),
                'batches' => $buckets[ 'soon' ],
            ],
        ];

        foreach ( $sections as &$section ) {
            $section[ 'quantity' ] = round( $section[ 'batches' ]->sum( 'quantity_remaining' ), 5 );
            $section[ 'value' ] = $this->batchService->stockValue( $section[ 'batches' ] );
        }

        return [
            'title' => __m( 'Expiry Report', 'MplBatches' ),
            'description' => sprintf( __m( 'Batches with stock that are expired or expire soon, valued at cost. Generated on %s.', 'MplBatches' ), $buckets[ 'today' ] ),
            'sections' => $sections,
            'today' => $buckets[ 'today' ],
            'batchService' => $this->batchService,
        ];
    }
}
