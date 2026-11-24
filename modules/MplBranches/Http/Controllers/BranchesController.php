<?php

namespace Modules\MplBranches\Http\Controllers;

use App\Exceptions\NotAllowedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Modules\MplBranches\Crud\BranchCrud;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Services\BranchReportService;
use Modules\MplBranches\Services\BranchService;

class BranchesController extends Controller
{
    public function __construct(
        protected BranchService $branchService,
        protected BranchReportService $reportService
    ) {
        // ...
    }

    public function listBranches()
    {
        return BranchCrud::table();
    }

    public function createBranch()
    {
        return BranchCrud::form();
    }

    public function editBranch( Branch $branch )
    {
        return BranchCrud::form( $branch );
    }

    public function switchPage()
    {
        $user = Auth::user();

        return View::make( 'MplBranches::switch', [
            'title' => __m( 'Switch Branch', 'MplBranches' ),
            'description' => __m( 'Choose the branch you are working in. New sales without a cash register are recorded for this branch.', 'MplBranches' ),
            'branches' => $this->branchService->getAllowedBranches( $user ),
            'current' => $this->branchService->getCurrentBranch( $user ),
        ] );
    }

    public function switchBranch( Request $request )
    {
        try {
            $branch = $this->branchService->switchBranch( Auth::user(), $request->integer( 'branch_id' ) );
        } catch ( NotAllowedException $exception ) {
            return redirect( ns()->route( 'mplbranches.dashboard.switch' ) )->with( 'errorMessage', $exception->getMessage() );
        }

        return redirect( ns()->route( 'mplbranches.dashboard.switch' ) )
            ->with( 'message', sprintf( __m( 'You are now working in "%s".', 'MplBranches' ), $branch->name ) );
    }

    public function performanceReport( Request $request )
    {
        $period = in_array( $request->query( 'period' ), BranchReportService::PERIODS, true ) ? $request->query( 'period' ) : 'today';
        $error = null;

        try {
            [ $start, $end ] = $this->reportService->resolvePeriod( $period, $request->query( 'start' ), $request->query( 'end' ) );
        } catch ( NotAllowedException $exception ) {
            $error = $exception->getMessage();
            $period = 'today';
            [ $start, $end ] = $this->reportService->resolvePeriod( $period );
        }

        return View::make( 'MplBranches::report', [
            'title' => __m( 'Branch Performance', 'MplBranches' ),
            'description' => __m( 'Compare sales, orders, average ticket, gross profit and refunds per branch. Stock is shared across branches and is not part of this report.', 'MplBranches' ),
            'report' => $this->reportService->getPerformance( $start, $end ),
            'period' => $period,
            'start' => $request->query( 'start', $start->toDateString() ),
            'end' => $request->query( 'end', $end->toDateString() ),
            'error' => $error,
            'periods' => [
                'today' => __m( 'Today', 'MplBranches' ),
                'yesterday' => __m( 'Yesterday', 'MplBranches' ),
                'this_week' => __m( 'This Week', 'MplBranches' ),
                'this_month' => __m( 'This Month', 'MplBranches' ),
                'custom' => __m( 'Custom Range', 'MplBranches' ),
            ],
        ] );
    }
}
