<?php

namespace Modules\MplOwnerApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\MplOwnerApp\Http\Requests\OwnerMetricsRequest;
use Modules\MplOwnerApp\Services\OwnerMetricsService;

class OwnerMetricsController extends Controller
{
    public function __construct( protected OwnerMetricsService $metricsService )
    {
        // ...
    }

    public function index( OwnerMetricsRequest $request ): JsonResponse
    {
        return response()
            ->json( $this->metricsService->getMetrics( $request->period() ) )
            ->header( 'Cache-Control', 'no-store, private' );
    }
}
