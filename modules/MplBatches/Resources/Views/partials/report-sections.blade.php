@foreach ( $sections as $section )
<div class="ns-box mplb:rounded mplb:shadow mplb:mb-6 mpl-batch-section">
    <div class="ns-box-header mplb:p-2 mplb:border mplb:border-box-edge-b mplb:flex mplb:justify-between">
        <h4 class="mplb:font-semibold">{{ $section[ 'label' ] }}</h4>
        <span class="mplb:text-sm">
            {{ __m( 'Batches', 'MplBatches' ) }}: {{ $section[ 'batches' ]->count() }}
            &middot; {{ __m( 'Quantity', 'MplBatches' ) }}: {{ $section[ 'quantity' ] }}
            &middot; {{ __m( 'Value at cost', 'MplBatches' ) }}: {{ ns()->currency->define( $section[ 'value' ] )->format() }}
        </span>
    </div>
    <div class="ns-box-body mplb:overflow-x-auto">
        <table class="ns-table mplb:w-full">
            <thead>
                <tr>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Product', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Batch No.', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Expiry', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ __m( 'Days Left', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ __m( 'Remaining', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Unit', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ __m( 'Unit Cost', 'MplBatches' ) }}</th>
                    <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ __m( 'Value at cost', 'MplBatches' ) }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ( $section[ 'batches' ] as $batch )
                <tr>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $batch->product->name ?? '#' . $batch->product_id }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $batch->batch_number }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $batch->expiry_date }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ $batchService->daysLeft( $batch ) }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ $batch->quantity_remaining }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $batch->unit->name ?? '' }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ ns()->currency->define( $batch->cost )->format() }}</td>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ ns()->currency->define( $batch->quantity_remaining * $batch->cost )->format() }}</td>
                </tr>
                @empty
                <tr>
                    <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-center" colspan="8">{{ __m( 'No batch in this category.', 'MplBatches' ) }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endforeach
