<?php

namespace Modules\MplBranches\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\MplBranches\Models\BranchOrder;
use Modules\MplBranches\Services\BranchReportService;
use Modules\MplBranches\Services\BranchService;

class BranchesApiController extends Controller
{
    public function __construct(
        protected BranchService $branchService,
        protected BranchReportService $reportService
    ) {
        // ...
    }

    public function myBranches()
    {
        $user = Auth::user();

        return [
            'current' => $this->branchService->getCurrentBranch( $user ),
            'branches' => $this->branchService->getAllowedBranches( $user ),
        ];
    }

    public function switchBranch( Request $request )
    {
        $request->validate( [ 'branch_id' => 'required|integer' ] );

        $branch = $this->branchService->switchBranch( Auth::user(), $request->integer( 'branch_id' ) );

        return [
            'status' => 'success',
            'message' => sprintf( __m( 'You are now working in "%s".', 'MplBranches' ), $branch->name ),
            'data' => [ 'branch' => $branch ],
        ];
    }

    public function performance( Request $request )
    {
        $request->validate( [
            'period' => 'nullable|in:' . implode( ',', BranchReportService::PERIODS ),
            'start' => 'nullable|date',
            'end' => 'nullable|date',
        ] );

        [ $start, $end ] = $this->reportService->resolvePeriod(
            $request->input( 'period', 'today' ),
            $request->input( 'start' ),
            $request->input( 'end' )
        );

        return $this->reportService->getPerformance( $start, $end );
    }

    public function orderBranch( Order $order )
    {
        $branchOrder = BranchOrder::with( 'branch' )->where( 'order_id', $order->id )->first();

        return [
            'order_id' => $order->id,
            'branch' => $branchOrder?->branch,
            'source' => $branchOrder?->source,
        ];
    }
}
