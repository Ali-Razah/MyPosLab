<?php

namespace Modules\MplBranches\Services;

use App\Exceptions\NotAllowedException;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderRefund;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Models\BranchOrder;
use Throwable;

class BranchReportService
{
    const PERIODS = [ 'today', 'yesterday', 'this_week', 'this_month', 'custom' ];

    /**
     * Payment statuses counted as completed sales. Refunds are reported separately.
     */
    const SALE_STATUSES = [
        Order::PAYMENT_PAID,
        Order::PAYMENT_PARTIALLY_REFUNDED,
        Order::PAYMENT_REFUNDED,
    ];

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolvePeriod( string $period, ?string $start = null, ?string $end = null ): array
    {
        $now = ns()->date->copy();

        switch ( $period ) {
            case 'today':
                return [ $now->copy()->startOfDay(), $now->copy()->endOfDay() ];
            case 'yesterday':
                return [ $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay() ];
            case 'this_week':
                return [ $now->copy()->startOfWeek(), $now->copy()->endOfWeek() ];
            case 'this_month':
                return [ $now->copy()->startOfMonth(), $now->copy()->endOfMonth() ];
            case 'custom':
                try {
                    $startDate = Carbon::parse( (string) $start )->startOfDay();
                    $endDate = Carbon::parse( (string) $end )->endOfDay();
                } catch ( Throwable $exception ) {
                    throw new NotAllowedException( __m( 'Provide a valid start and end date for the custom period.', 'MplBranches' ) );
                }

                if ( empty( $start ) || empty( $end ) || $startDate->greaterThan( $endDate ) ) {
                    throw new NotAllowedException( __m( 'Provide a valid start and end date for the custom period.', 'MplBranches' ) );
                }

                return [ $startDate, $endDate ];
        }

        throw new NotAllowedException( __m( 'Unknown report period.', 'MplBranches' ) );
    }

    /**
     * Per-branch performance between two dates.
     *
     * Gross profit mirrors core ReportService::getSalesSummary():
     * order total - products total_purchase_price - order tax_value - products tax_value.
     */
    public function getPerformance( Carbon $start, Carbon $end ): array
    {
        $ordersTable = ( new Order )->getTable();
        $productsTable = ( new OrderProduct )->getTable();
        $refundsTable = ( new OrderRefund )->getTable();
        $branchOrdersTable = ( new BranchOrder )->getTable();
        $from = $start->toDateTimeString();
        $to = $end->toDateTimeString();

        $sales = DB::table( $ordersTable )
            ->leftJoin( $branchOrdersTable, $branchOrdersTable . '.order_id', '=', $ordersTable . '.id' )
            ->whereIn( $ordersTable . '.payment_status', self::SALE_STATUSES )
            ->whereBetween( $ordersTable . '.created_at', [ $from, $to ] )
            ->groupBy( $branchOrdersTable . '.branch_id' )
            ->select( [
                $branchOrdersTable . '.branch_id as branch_id',
                DB::raw( 'COUNT(' . $this->wrap( $ordersTable . '.id' ) . ') as orders_count' ),
                DB::raw( 'COALESCE(SUM(' . $this->wrap( $ordersTable . '.total' ) . '), 0) as sales_total' ),
                DB::raw( 'COALESCE(SUM(' . $this->wrap( $ordersTable . '.tax_value' ) . '), 0) as order_taxes' ),
            ] )
            ->get()
            ->keyBy( fn ( $row ) => (int) $row->branch_id );

        $products = DB::table( $productsTable )
            ->join( $ordersTable, $ordersTable . '.id', '=', $productsTable . '.order_id' )
            ->leftJoin( $branchOrdersTable, $branchOrdersTable . '.order_id', '=', $ordersTable . '.id' )
            ->whereIn( $ordersTable . '.payment_status', self::SALE_STATUSES )
            ->whereBetween( $ordersTable . '.created_at', [ $from, $to ] )
            ->groupBy( $branchOrdersTable . '.branch_id' )
            ->select( [
                $branchOrdersTable . '.branch_id as branch_id',
                DB::raw( 'COALESCE(SUM(' . $this->wrap( $productsTable . '.total_purchase_price' ) . '), 0) as total_purchase_price' ),
                DB::raw( 'COALESCE(SUM(' . $this->wrap( $productsTable . '.tax_value' ) . '), 0) as product_taxes' ),
            ] )
            ->get()
            ->keyBy( fn ( $row ) => (int) $row->branch_id );

        $refunds = DB::table( $refundsTable )
            ->join( $ordersTable, $ordersTable . '.id', '=', $refundsTable . '.order_id' )
            ->leftJoin( $branchOrdersTable, $branchOrdersTable . '.order_id', '=', $ordersTable . '.id' )
            ->whereBetween( $refundsTable . '.created_at', [ $from, $to ] )
            ->groupBy( $branchOrdersTable . '.branch_id' )
            ->select( [
                $branchOrdersTable . '.branch_id as branch_id',
                DB::raw( 'COALESCE(SUM(' . $this->wrap( $refundsTable . '.total' ) . '), 0) as refunds' ),
            ] )
            ->get()
            ->keyBy( fn ( $row ) => (int) $row->branch_id );

        $rows = Branch::orderByDesc( 'is_default' )->orderBy( 'name' )->get()
            ->map( fn ( Branch $branch ) => $this->buildRow( $branch->id, $branch->name, $branch->code, $branch->active, $sales, $products, $refunds ) );

        /**
         * Orders created before the module was enabled have no branch (branch_id 0 after cast).
         */
        if ( $sales->has( 0 ) || $refunds->has( 0 ) ) {
            $rows->push( $this->buildRow( 0, __m( 'Not assigned', 'MplBranches' ), '', true, $sales, $products, $refunds ) );
        }

        return [
            'start' => $from,
            'end' => $to,
            'rows' => $rows->values()->toArray(),
            'total' => $this->buildTotal( $rows ),
        ];
    }

    protected function buildRow( int $branchId, string $name, string $code, bool $active, Collection $sales, Collection $products, Collection $refunds ): array
    {
        $sale = $sales->get( $branchId );
        $product = $products->get( $branchId );
        $refund = $refunds->get( $branchId );

        $salesTotal = (float) ( $sale->sales_total ?? 0 );
        $ordersCount = (int) ( $sale->orders_count ?? 0 );

        return [
            'branch_id' => $branchId,
            'name' => $name,
            'code' => $code,
            'active' => $active,
            'orders_count' => $ordersCount,
            'sales_total' => ns()->currency->define( $salesTotal )->toFloat(),
            'average_ticket' => $ordersCount > 0 ? ns()->currency->define( $salesTotal )->divideBy( $ordersCount )->toFloat() : 0,
            'gross_profit' => ns()->currency->define( $salesTotal )
                ->subtractBy( (float) ( $product->total_purchase_price ?? 0 ) )
                ->subtractBy( (float) ( $sale->order_taxes ?? 0 ) )
                ->subtractBy( (float) ( $product->product_taxes ?? 0 ) )
                ->toFloat(),
            'refunds' => ns()->currency->define( (float) ( $refund->refunds ?? 0 ) )->toFloat(),
        ];
    }

    protected function buildTotal( Collection $rows ): array
    {
        $ordersCount = (int) $rows->sum( 'orders_count' );
        $salesTotal = (float) $rows->sum( 'sales_total' );

        return [
            'name' => __m( 'Total', 'MplBranches' ),
            'orders_count' => $ordersCount,
            'sales_total' => ns()->currency->define( $salesTotal )->toFloat(),
            'average_ticket' => $ordersCount > 0 ? ns()->currency->define( $salesTotal )->divideBy( $ordersCount )->toFloat() : 0,
            'gross_profit' => ns()->currency->define( (float) $rows->sum( 'gross_profit' ) )->toFloat(),
            'refunds' => ns()->currency->define( (float) $rows->sum( 'refunds' ) )->toFloat(),
        ];
    }

    protected function wrap( string $column ): string
    {
        return DB::getQueryGrammar()->wrap( $column );
    }
}
