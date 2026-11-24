<?php

namespace Modules\MplOwnerApp\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderProduct;
use App\Models\OrderRefund;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\Register;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Computes the owner mobile dashboard metrics.
 *
 * Sales follow the core dashboard definition (ReportService::computePaidOrders
 * and computePartiallyPaidOrders): the sum of `total` for orders whose payment
 * status is "paid" or "partially_paid", filtered on `created_at` in the store
 * timezone. Profit mirrors ReportService::getSalesSummary() and the core profit
 * report (paid orders only): total - order tax - order products tax - order
 * products total_purchase_price.
 */
class OwnerMetricsService
{
    public const PERIODS = [ 'today', 'yesterday', '7d', '30d', 'month' ];

    /**
     * Payment statuses counted as sales by the core dashboard report.
     */
    public const SALES_STATUSES = [ Order::PAYMENT_PAID, Order::PAYMENT_PARTIALLY ];

    /**
     * Resolve the current and previous equal ranges for a period, in the store timezone.
     *
     * @return array{current: array{start: CarbonInterface, end: CarbonInterface}, previous: array{start: CarbonInterface, end: CarbonInterface}, granularity: string}
     */
    public function resolveRanges( string $period, ?CarbonInterface $now = null ): array
    {
        $now = $now ?: ns()->date->getNow();

        switch ( $period ) {
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $previous = [ 'start' => $start->copy()->subDay(), 'end' => $end->copy()->subDay() ];
                $granularity = 'hour';
                break;
            case '7d':
                $start = $now->copy()->subDays( 6 )->startOfDay();
                $end = $now->copy()->endOfDay();
                $previous = [ 'start' => $start->copy()->subDays( 7 ), 'end' => $end->copy()->subDays( 7 ) ];
                $granularity = 'day';
                break;
            case '30d':
                $start = $now->copy()->subDays( 29 )->startOfDay();
                $end = $now->copy()->endOfDay();
                $previous = [ 'start' => $start->copy()->subDays( 30 ), 'end' => $end->copy()->subDays( 30 ) ];
                $granularity = 'day';
                break;
            case 'month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $previousMonth = $now->copy()->startOfMonth()->subMonthNoOverflow();
                $previous = [ 'start' => $previousMonth->copy()->startOfMonth(), 'end' => $previousMonth->copy()->endOfMonth() ];
                $granularity = 'day';
                break;
            case 'today':
            default:
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $previous = [ 'start' => $start->copy()->subDay(), 'end' => $end->copy()->subDay() ];
                $granularity = 'hour';
                break;
        }

        return [
            'current' => [ 'start' => $start, 'end' => $end ],
            'previous' => $previous,
            'granularity' => $granularity,
        ];
    }

    /**
     * Build the full metrics payload for the owner dashboard.
     *
     * @return array<string, mixed>
     */
    public function getMetrics( string $period = 'today' ): array
    {
        $now = ns()->date->getNow();
        $ranges = $this->resolveRanges( $period, $now );

        $current = $this->getSummary( $ranges['current']['start'], $ranges['current']['end'] );
        $previous = $this->getSummary( $ranges['previous']['start'], $ranges['previous']['end'] );

        return [
            'period' => $period,
            'store_name' => ns()->option->get( 'ns_store_name', config( 'app.name' ) ),
            'generated_at' => $now->toDateTimeString(),
            'timezone' => ns()->option->get( 'ns_datetime_timezone', 'Europe/London' ),
            'range' => [
                'start' => $ranges['current']['start']->toDateTimeString(),
                'end' => $ranges['current']['end']->toDateTimeString(),
            ],
            'previous_range' => [
                'start' => $ranges['previous']['start']->toDateTimeString(),
                'end' => $ranges['previous']['end']->toDateTimeString(),
            ],
            'summary' => $current,
            'previous' => $previous,
            'comparison' => [
                'sales' => $this->percentChange( $current['sales'], $previous['sales'] ),
                'orders' => $this->percentChange( $current['orders'], $previous['orders'] ),
                'average_ticket' => $this->percentChange( $current['average_ticket'], $previous['average_ticket'] ),
                'profit' => $this->percentChange( $current['profit'], $previous['profit'] ),
                'refunds' => $this->percentChange( $current['refunds'], $previous['refunds'] ),
            ],
            'timeline' => $this->getTimeline( $ranges['current']['start'], $ranges['current']['end'], $ranges['granularity'] ),
            'payments' => $this->getPaymentsBreakdown( $ranges['current']['start'], $ranges['current']['end'] ),
            'top_products' => $this->getTopProducts( $ranges['current']['start'], $ranges['current']['end'] ),
            'low_stock' => $this->getLowStock(),
            'registers' => $this->getRegisters(),
            'recent_orders' => $this->getRecentOrders(),
        ];
    }

    /**
     * Sales, orders, average ticket, profit and refunds for a range.
     *
     * @return array{sales: float, orders: int, average_ticket: float, cost: float, taxes: float, profit: float, refunds: float}
     */
    public function getSummary( CarbonInterface $start, CarbonInterface $end ): array
    {
        $orderTable = ( new Order )->getTable();
        $productTable = ( new OrderProduct )->getTable();

        $ordersQuery = $this->salesOrdersQuery( $start, $end );
        $grammar = $ordersQuery->getQuery()->getGrammar();

        $paidOnly = 'CASE WHEN ' . $grammar->wrap( $orderTable . '.payment_status' ) . ' = ? THEN %s ELSE 0 END';

        $orders = $ordersQuery
            ->selectRaw( 'COUNT(*) as orders_count' )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( $orderTable . '.total' ) . '), 0) as sales_total' )
            ->selectRaw( 'COALESCE(SUM(' . sprintf( $paidOnly, $grammar->wrap( $orderTable . '.total' ) ) . '), 0) as paid_total', [ Order::PAYMENT_PAID ] )
            ->selectRaw( 'COALESCE(SUM(' . sprintf( $paidOnly, $grammar->wrap( $orderTable . '.tax_value' ) ) . '), 0) as order_taxes', [ Order::PAYMENT_PAID ] )
            ->toBase()
            ->first();

        /**
         * Cost, taxes and profit follow the core profit report
         * (OrdersService::getSoldStock, ReportService::getSalesSummary): paid orders only.
         */
        $products = OrderProduct::query()
            ->join( $orderTable, $orderTable . '.id', '=', $productTable . '.order_id' )
            ->where( $orderTable . '.payment_status', Order::PAYMENT_PAID )
            ->where( $orderTable . '.created_at', '>=', $start->toDateTimeString() )
            ->where( $orderTable . '.created_at', '<=', $end->toDateTimeString() )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( $productTable . '.total_purchase_price' ) . '), 0) as total_cost' )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( $productTable . '.tax_value' ) . '), 0) as product_taxes' )
            ->toBase()
            ->first();

        $refunds = (float) OrderRefund::query()
            ->where( 'created_at', '>=', $start->toDateTimeString() )
            ->where( 'created_at', '<=', $end->toDateTimeString() )
            ->sum( 'total' );

        $sales = $this->money( $orders->sales_total ?? 0 );
        $count = (int) ( $orders->orders_count ?? 0 );
        $cost = $this->money( $products->total_cost ?? 0 );
        $taxes = $this->money( (float) ( $orders->order_taxes ?? 0 ) + (float) ( $products->product_taxes ?? 0 ) );

        return [
            'sales' => $sales,
            'orders' => $count,
            'average_ticket' => $count > 0 ? $this->money( $sales / $count ) : 0.0,
            'cost' => $cost,
            'taxes' => $taxes,
            'profit' => $this->money( (float) ( $orders->paid_total ?? 0 ) - $taxes - $cost ),
            'refunds' => $this->money( $refunds ),
        ];
    }

    /**
     * Sales grouped by hour (single day) or by day (longer periods).
     *
     * @return array{granularity: string, labels: array<int, string>, values: array<int, float>}
     */
    public function getTimeline( CarbonInterface $start, CarbonInterface $end, string $granularity ): array
    {
        $driver = DB::connection()->getDriverName();
        $grammar = Order::query()->getQuery()->getGrammar();
        $column = $grammar->wrap( 'created_at' );

        $bucketExpression = match ( true ) {
            $granularity === 'hour' && $driver === 'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            $granularity === 'hour' && $driver === 'pgsql' => "CAST(EXTRACT(HOUR FROM {$column}) AS INTEGER)",
            $granularity === 'hour' && $driver === 'sqlsrv' => "DATEPART(HOUR, {$column})",
            $granularity === 'hour' => "HOUR({$column})",
            $driver === 'sqlite' => "date({$column})",
            $driver === 'sqlsrv' => "CAST({$column} AS DATE)",
            default => "DATE({$column})",
        };

        $rows = $this->salesOrdersQuery( $start, $end )
            ->selectRaw( "{$bucketExpression} as bucket" )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( 'total' ) . '), 0) as sales_total' )
            ->groupByRaw( $bucketExpression )
            ->toBase()
            ->get()
            ->mapWithKeys( fn( $row ) => [ (string) ( $granularity === 'hour' ? (int) $row->bucket : substr( (string) $row->bucket, 0, 10 ) ) => (float) $row->sales_total ] );

        $labels = [];
        $values = [];

        if ( $granularity === 'hour' ) {
            for ( $hour = 0; $hour < 24; $hour++ ) {
                $labels[] = str_pad( (string) $hour, 2, '0', STR_PAD_LEFT );
                $values[] = $this->money( $rows->get( (string) $hour, 0 ) );
            }
        } else {
            $cursor = $start->copy()->startOfDay();

            while ( $cursor->lessThanOrEqualTo( $end ) ) {
                $key = $cursor->toDateString();
                $labels[] = $key;
                $values[] = $this->money( $rows->get( $key, 0 ) );
                $cursor->addDay();
            }
        }

        return compact( 'granularity', 'labels', 'values' );
    }

    /**
     * Payments received on sales orders created within the range, grouped by payment type.
     *
     * @return array<int, array{identifier: string, label: string, total: float}>
     */
    public function getPaymentsBreakdown( CarbonInterface $start, CarbonInterface $end ): array
    {
        $paymentTable = ( new OrderPayment )->getTable();
        $orderTable = ( new Order )->getTable();
        $typeTable = ( new PaymentType )->getTable();

        $query = OrderPayment::query();
        $grammar = $query->getQuery()->getGrammar();

        return $query
            ->join( $orderTable, $orderTable . '.id', '=', $paymentTable . '.order_id' )
            ->leftJoin( $typeTable, $typeTable . '.identifier', '=', $paymentTable . '.identifier' )
            ->whereIn( $orderTable . '.payment_status', self::SALES_STATUSES )
            ->where( $orderTable . '.created_at', '>=', $start->toDateTimeString() )
            ->where( $orderTable . '.created_at', '<=', $end->toDateTimeString() )
            ->groupBy( $paymentTable . '.identifier', $typeTable . '.label' )
            ->select( $paymentTable . '.identifier', $typeTable . '.label' )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( $paymentTable . '.value' ) . '), 0) as total' )
            ->orderByDesc( 'total' )
            ->toBase()
            ->get()
            ->map( fn( $row ) => [
                'identifier' => (string) $row->identifier,
                'label' => (string) ( $row->label ?: $row->identifier ),
                'total' => $this->money( $row->total ),
            ] )
            ->values()
            ->all();
    }

    /**
     * Top products by revenue for sales orders within the range.
     *
     * @return array<int, array{product_id: int, name: string, quantity: float, revenue: float}>
     */
    public function getTopProducts( CarbonInterface $start, CarbonInterface $end, int $limit = 5 ): array
    {
        $productTable = ( new OrderProduct )->getTable();
        $orderTable = ( new Order )->getTable();

        $query = OrderProduct::query();
        $grammar = $query->getQuery()->getGrammar();

        return $query
            ->join( $orderTable, $orderTable . '.id', '=', $productTable . '.order_id' )
            ->whereIn( $orderTable . '.payment_status', self::SALES_STATUSES )
            ->where( $orderTable . '.created_at', '>=', $start->toDateTimeString() )
            ->where( $orderTable . '.created_at', '<=', $end->toDateTimeString() )
            ->groupBy( $productTable . '.product_id', $productTable . '.name' )
            ->select( $productTable . '.product_id', $productTable . '.name' )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( $productTable . '.quantity' ) . '), 0) as quantity' )
            ->selectRaw( 'COALESCE(SUM(' . $grammar->wrap( $productTable . '.total_price' ) . '), 0) as revenue' )
            ->orderByDesc( 'revenue' )
            ->limit( $limit )
            ->toBase()
            ->get()
            ->map( fn( $row ) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'quantity' => (float) $row->quantity,
                'revenue' => $this->money( $row->revenue ),
            ] )
            ->values()
            ->all();
    }

    /**
     * Products whose unit quantity fell below the low-quantity threshold while the
     * stock alert is enabled. Mirrors ReportService::getLowStockProducts() and
     * DetectLowStockProductsJob (low_quantity > quantity).
     *
     * @return array<int, array{product_id: int, name: string, unit: string, quantity: float, low_quantity: float}>
     */
    public function getLowStock( int $limit = 20 ): array
    {
        $unitQuantityTable = ( new ProductUnitQuantity )->getTable();
        $productTable = ( new Product )->getTable();
        $unitTable = ( new Unit )->getTable();

        $query = ProductUnitQuantity::query();
        $grammar = $query->getQuery()->getGrammar();

        return $query
            ->join( $productTable, $productTable . '.id', '=', $unitQuantityTable . '.product_id' )
            ->leftJoin( $unitTable, $unitTable . '.id', '=', $unitQuantityTable . '.unit_id' )
            ->where( $unitQuantityTable . '.stock_alert_enabled', 1 )
            ->whereRaw( $grammar->wrap( $unitQuantityTable . '.quantity' ) . ' < ' . $grammar->wrap( $unitQuantityTable . '.low_quantity' ) )
            ->select( [
                $unitQuantityTable . '.id as unit_quantity_id',
                $unitQuantityTable . '.product_id',
                $productTable . '.name as product_name',
                $unitTable . '.name as unit_name',
                $unitQuantityTable . '.quantity',
                $unitQuantityTable . '.low_quantity',
            ] )
            ->orderBy( $unitQuantityTable . '.quantity' )
            ->limit( $limit )
            ->toBase()
            ->get()
            ->map( fn( $row ) => [
                'unit_quantity_id' => (int) $row->unit_quantity_id,
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->product_name,
                'unit' => (string) ( $row->unit_name ?? '' ),
                'quantity' => (float) $row->quantity,
                'low_quantity' => (float) $row->low_quantity,
            ] )
            ->values()
            ->all();
    }

    /**
     * Cash registers with their status, balance and current cashier.
     *
     * @return array<int, array{id: int, name: string, status: string, is_open: bool, balance: float, cashier: string|null}>
     */
    public function getRegisters(): array
    {
        $registerTable = ( new Register )->getTable();
        $userTable = ( new User )->getTable();

        return Register::query()
            ->leftJoin( $userTable, $userTable . '.id', '=', $registerTable . '.used_by' )
            ->select( [
                $registerTable . '.id',
                $registerTable . '.name',
                $registerTable . '.status',
                $registerTable . '.balance',
                $userTable . '.username as cashier',
            ] )
            ->orderBy( $registerTable . '.name' )
            ->limit( 50 )
            ->toBase()
            ->get()
            ->map( fn( $row ) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'status' => (string) $row->status,
                'is_open' => in_array( $row->status, [ Register::STATUS_OPENED, Register::STATUS_INUSE ], true ),
                'balance' => $this->money( $row->balance ),
                'cashier' => $row->cashier,
            ] )
            ->values()
            ->all();
    }

    /**
     * The latest orders, whatever their payment status.
     *
     * @return array<int, array{id: int, code: string, total: float, payment_status: string, customer: string, created_at: string}>
     */
    public function getRecentOrders( int $limit = 5 ): array
    {
        $orderTable = ( new Order )->getTable();
        $userTable = ( new User )->getTable();

        return Order::query()
            ->leftJoin( $userTable, $userTable . '.id', '=', $orderTable . '.customer_id' )
            ->select( [
                $orderTable . '.id',
                $orderTable . '.code',
                $orderTable . '.total',
                $orderTable . '.payment_status',
                $orderTable . '.created_at',
                $userTable . '.first_name',
                $userTable . '.last_name',
                $userTable . '.username',
            ] )
            ->orderByDesc( $orderTable . '.created_at' )
            ->orderByDesc( $orderTable . '.id' )
            ->limit( $limit )
            ->toBase()
            ->get()
            ->map( fn( $row ) => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'total' => $this->money( $row->total ),
                'payment_status' => (string) $row->payment_status,
                'customer' => trim( ( $row->first_name ?? '' ) . ' ' . ( $row->last_name ?? '' ) ) ?: (string) ( $row->username ?? '' ),
                'created_at' => (string) $row->created_at,
            ] )
            ->values()
            ->all();
    }

    /**
     * Percent change between two values. Returns null when there is no
     * previous value to compare with (the change is not measurable).
     */
    public function percentChange( float|int $current, float|int $previous ): ?float
    {
        if ( (float) $previous == 0.0 ) {
            return (float) $current == 0.0 ? 0.0 : null;
        }

        return round( ( ( $current - $previous ) / abs( $previous ) ) * 100, 2 );
    }

    private function salesOrdersQuery( CarbonInterface $start, CarbonInterface $end ): Builder
    {
        return Order::query()
            ->whereIn( ( new Order )->getTable() . '.payment_status', self::SALES_STATUSES )
            ->where( ( new Order )->getTable() . '.created_at', '>=', $start->toDateTimeString() )
            ->where( ( new Order )->getTable() . '.created_at', '<=', $end->toDateTimeString() );
    }

    private function money( float|int|string|null $value ): float
    {
        return ns()->currency->define( (float) ( $value ?? 0 ) )->toFloat();
    }
}
