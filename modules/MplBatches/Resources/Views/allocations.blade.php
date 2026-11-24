@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div class="mplb:flex-auto mplb:flex mplb:flex-col">
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div class="mplb:flex-auto mplb:flex mplb:flex-col mplb:px-4" id="dashboard-content">
        @include( 'common.dashboard.title' )
        <div v-pre>
            <div class="ns-box mplb:rounded mplb:shadow mplb:mb-4 mplb:p-2">
                <p><strong>{{ __m( 'Product', 'MplBatches' ) }}:</strong> {{ $batch->product->name ?? '#' . $batch->product_id }} ({{ $batch->unit->name ?? '' }})</p>
                <p><strong>{{ __m( 'Expiry', 'MplBatches' ) }}:</strong> {{ $batch->expiry_date ?: __m( 'No expiry', 'MplBatches' ) }}</p>
                <p><strong>{{ __m( 'Received', 'MplBatches' ) }}:</strong> {{ $batch->quantity_received }} &middot; <strong>{{ __m( 'Remaining', 'MplBatches' ) }}:</strong> {{ $batch->quantity_remaining }}</p>
                <p><strong>{{ __m( 'Procurement', 'MplBatches' ) }}:</strong> {{ $batch->procurement->name ?? __m( 'N/A', 'MplBatches' ) }}</p>
            </div>
            <div class="ns-box mplb:rounded mplb:shadow mplb:overflow-x-auto">
                <table class="ns-table mplb:w-full">
                    <thead>
                        <tr>
                            <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Date', 'MplBatches' ) }}</th>
                            <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Type', 'MplBatches' ) }}</th>
                            <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Order', 'MplBatches' ) }}</th>
                            <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ __m( 'Quantity', 'MplBatches' ) }}</th>
                            <th class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-left">{{ __m( 'Note', 'MplBatches' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ( $allocations as $allocation )
                        <tr>
                            <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $allocation->created_at }}</td>
                            <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $typeLabels[ $allocation->type ] ?? $allocation->type }}</td>
                            <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $allocation->order->code ?? ( $allocation->order_id ? '#' . $allocation->order_id : '—' ) }}</td>
                            <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-right">{{ $allocation->quantity }}</td>
                            <td class="mplb:p-2 mplb:border mplb:border-box-edge">{{ $allocation->note }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td class="mplb:p-2 mplb:border mplb:border-box-edge mplb:text-center" colspan="5">{{ __m( 'This batch has not been used yet.', 'MplBatches' ) }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
