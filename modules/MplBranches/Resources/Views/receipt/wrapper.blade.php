<?php
use Modules\MplBranches\Filters\ReceiptFilter;
use Modules\MplBranches\Services\BranchService;

try {
    $mplBranch = app()->make( BranchService::class )->getReceiptData( $order );
} catch ( \Throwable $exception ) {
    $mplBranch = [ 'branch_name' => '' ];
}
?>
@if ( ! empty( $mplBranch[ 'branch_name' ] ) )
<div class="w-full" id="mplbranches-receipt-header">
    <div class="w-full md:w-1/2 lg:w-1/3 bg-white px-2 pt-2 mx-auto text-center text-sm text-gray-800">
        <div class="font-bold text-base">{{ $mplBranch[ 'branch_name' ] }}</div>
        @if ( ! empty( $mplBranch[ 'branch_address' ] ) )
        <div>{{ $mplBranch[ 'branch_address' ] }}</div>
        @endif
        @if ( ! empty( $mplBranch[ 'branch_phone' ] ) )
        <div>{{ __m( 'Phone', 'MplBranches' ) }}: {{ $mplBranch[ 'branch_phone' ] }}</div>
        @endif
        @if ( ! empty( $mplBranch[ 'branch_trn' ] ) )
        <div>{{ __m( 'TRN', 'MplBranches' ) }}: {{ $mplBranch[ 'branch_trn' ] }}</div>
        @endif
        @if ( ! empty( $mplBranch[ 'branch_receipt_header' ] ) )
        <div class="pt-1">{!! nl2br( e( $mplBranch[ 'branch_receipt_header' ] ) ) !!}</div>
        @endif
    </div>
</div>
@endif
@include( ReceiptFilter::$innerTemplate )
@if ( ! empty( $mplBranch[ 'branch_receipt_footer' ] ) )
<div class="w-full" id="mplbranches-receipt-footer">
    <div class="w-full md:w-1/2 lg:w-1/3 bg-white px-2 pb-2 mx-auto text-center text-sm text-gray-800">
        {!! nl2br( e( $mplBranch[ 'branch_receipt_footer' ] ) ) !!}
    </div>
</div>
@endif
