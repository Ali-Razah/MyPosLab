@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div>
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div id="dashboard-content" class="px-4">
        @include( 'common.dashboard.title' )
        <div v-pre id="mplbranches-report">
            <form method="GET" action="{{ ns()->route( 'mplbranches.dashboard.reports.performance' ) }}" class="mplbranches-no-print flex flex-wrap items-end gap-2 mb-4">
                <div class="flex flex-col">
                    <label for="mplbranches-period" class="text-fontcolor mb-1">{{ __m( 'Period', 'MplBranches' ) }}</label>
                    <select id="mplbranches-period" name="period" class="ns-input border rounded p-2">
                        @foreach( $periods as $key => $label )
                        <option value="{{ $key }}" @selected( $period === $key )>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col">
                    <label for="mplbranches-start" class="text-fontcolor mb-1">{{ __m( 'From', 'MplBranches' ) }}</label>
                    <input id="mplbranches-start" type="date" name="start" value="{{ $start }}" class="ns-input border rounded p-2">
                </div>
                <div class="flex flex-col">
                    <label for="mplbranches-end" class="text-fontcolor mb-1">{{ __m( 'To', 'MplBranches' ) }}</label>
                    <input id="mplbranches-end" type="date" name="end" value="{{ $end }}" class="ns-input border rounded p-2">
                </div>
                <div class="ns-button info">
                    <button type="submit" class="rounded px-4 py-2">{{ __m( 'Load', 'MplBranches' ) }}</button>
                </div>
                <div class="ns-button default">
                    <button type="button" class="rounded px-4 py-2" onclick="window.print()">{{ __m( 'Print', 'MplBranches' ) }}</button>
                </div>
                <p class="w-full text-xs text-fontcolor-soft">{{ __m( 'From and To are used only with the custom range.', 'MplBranches' ) }}</p>
            </form>
            @if ( $error )
            <p class="text-error-tertiary mb-2">{{ $error }}</p>
            @endif
            <div class="ns-box rounded shadow">
                <div class="ns-box-header p-2 border-b text-fontcolor">
                    <strong>{{ __m( 'Branch Performance', 'MplBranches' ) }}</strong>
                    — {{ ns()->date->getFormatted( $report[ 'start' ] ) }} → {{ ns()->date->getFormatted( $report[ 'end' ] ) }}
                </div>
                <div class="ns-box-body overflow-x-auto">
                    <table class="ns-table w-full">
                        <thead>
                            <tr>
                                <th class="p-2 border text-left">{{ __m( 'Branch', 'MplBranches' ) }}</th>
                                <th class="p-2 border text-right">{{ __m( 'Orders', 'MplBranches' ) }}</th>
                                <th class="p-2 border text-right">{{ __m( 'Sales', 'MplBranches' ) }}</th>
                                <th class="p-2 border text-right">{{ __m( 'Average Ticket', 'MplBranches' ) }}</th>
                                <th class="p-2 border text-right">{{ __m( 'Gross Profit', 'MplBranches' ) }}</th>
                                <th class="p-2 border text-right">{{ __m( 'Refunds', 'MplBranches' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach( $report[ 'rows' ] as $row )
                            <tr>
                                <td class="p-2 border">
                                    {{ $row[ 'name' ] }}
                                    @if ( $row[ 'code' ] )<span class="text-fontcolor-soft text-xs">({{ $row[ 'code' ] }})</span>@endif
                                    @if ( ! $row[ 'active' ] )<span class="text-fontcolor-soft text-xs">— {{ __m( 'inactive', 'MplBranches' ) }}</span>@endif
                                </td>
                                <td class="p-2 border text-right">{{ $row[ 'orders_count' ] }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $row[ 'sales_total' ] ) }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $row[ 'average_ticket' ] ) }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $row[ 'gross_profit' ] ) }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $row[ 'refunds' ] ) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-bold">
                                <td class="p-2 border">{{ $report[ 'total' ][ 'name' ] }}</td>
                                <td class="p-2 border text-right">{{ $report[ 'total' ][ 'orders_count' ] }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $report[ 'total' ][ 'sales_total' ] ) }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $report[ 'total' ][ 'average_ticket' ] ) }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $report[ 'total' ][ 'gross_profit' ] ) }}</td>
                                <td class="p-2 border text-right">{{ ns()->currency->define( $report[ 'total' ][ 'refunds' ] ) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <p class="text-xs text-fontcolor-soft mt-2">{{ __m( 'Sales include paid, partially refunded and refunded orders created in the period. Gross profit = sales - cost of goods - taxes. Refunds are those issued in the period. Stock is shared across branches.', 'MplBranches' ) }}</p>
        </div>
    </div>
</div>
@endsection

@section( 'layout.dashboard.header' )
@parent
<style>
@media print {
    #dashboard-aside, #dashboard-header, .mplbranches-no-print { display: none !important; }
}
</style>
@endsection
