@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
<div class="mplb:flex-auto mplb:flex mplb:flex-col">
    @include( Hook::filter( 'ns-dashboard-header-file', '../common/dashboard-header' ) )
    <div class="mplb:flex-auto mplb:flex mplb:flex-col mplb:px-4" id="dashboard-content">
        @include( 'common.dashboard.title' )
        {{-- v-pre: server rendered content (batch numbers are user input) must not be compiled by Vue. --}}
        <div v-pre>
            <div class="mplb:flex mplb:justify-end mplb:mb-4">
                <div class="ns-button info">
                    <a class="mplb:px-4 mplb:py-2 mplb:block" target="_blank" rel="noopener" href="{{ ns()->route( 'mplbatches.report.print' ) }}">{{ __m( 'Print', 'MplBatches' ) }}</a>
                </div>
            </div>
            @include( 'MplBatches::partials.report-sections' )
        </div>
    </div>
</div>
@endsection
