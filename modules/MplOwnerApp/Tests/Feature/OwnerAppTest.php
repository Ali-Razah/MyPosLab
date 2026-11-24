<?php

namespace Modules\MplOwnerApp\Tests\Feature;

use App\Classes\Hook;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\Role;
use App\Models\User;
use App\Services\ModulesService;
use App\Services\UsersService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\MplOwnerApp\Services\OwnerMenuService;
use Modules\MplOwnerApp\Services\OwnerMetricsService;
use Tests\TestCase;

class OwnerAppTest extends TestCase
{
    private const MODULE = 'MplOwnerApp';

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureModuleIsEnabled();

        /**
         * Every scenario runs inside a transaction that is rolled back, so the
         * orders and stock changes never leak into the shared test database.
         */
        DB::beginTransaction();

        /**
         * A random far-future day guarantees no pre-existing order falls in
         * "today" or "yesterday".
         */
        $this->now = Carbon::create( 2031, 1, 1, 15, 30, 0 )->addDays( random_int( 0, 3000 ) );
        $this->travelTo( $this->now );
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        while ( DB::transactionLevel() > 0 ) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_metrics_api_returns_totals_for_today_compared_to_yesterday(): void
    {
        $admin = $this->getUserFromRole( Role::ADMIN );
        Sanctum::actingAs( $admin, [ '*' ] );

        $unitQuantity = $this->prepareProduct( cogs: 4 );

        // Yesterday: one paid order of 1 x 10.
        $this->travelTo( $this->now->copy()->subDay()->setTime( 10, 15 ) );
        $yesterdayOrder = $this->postPaidOrder( $unitQuantity, quantity: 1, unitPrice: 10 );

        // Today: two paid orders (2 x 10 and 3 x 10) and a hold order which must be ignored.
        $this->travelTo( $this->now->copy()->setTime( 9, 5 ) );
        $firstOrder = $this->postPaidOrder( $unitQuantity, quantity: 2, unitPrice: 10 );
        $this->travelTo( $this->now->copy()->setTime( 11, 40 ) );
        $secondOrder = $this->postPaidOrder( $unitQuantity, quantity: 3, unitPrice: 10 );
        $this->postHoldOrder( $unitQuantity, quantity: 5, unitPrice: 10 );
        $this->travelTo( $this->now );

        $this->assertSame( 10.0, (float) $yesterdayOrder->total );
        $this->assertSame( 20.0, (float) $firstOrder->total );
        $this->assertSame( 30.0, (float) $secondOrder->total );

        $orderIds = [ $firstOrder->id, $secondOrder->id ];
        $expectedCost = (float) OrderProduct::whereIn( 'order_id', $orderIds )->sum( 'total_purchase_price' );
        $this->assertSame( 20.0, $expectedCost, 'COGS is 4 per unit for 5 units sold today.' );

        $response = $this->getJson( 'api/mplownerapp/metrics' );
        $response->assertOk();

        $response->assertJsonPath( 'period', 'today' );
        $this->assertEquals( 50, $response->json( 'summary.sales' ) );
        $this->assertEquals( 2, $response->json( 'summary.orders' ) );
        $this->assertEquals( 25, $response->json( 'summary.average_ticket' ) );
        $this->assertEquals( 20, $response->json( 'summary.cost' ) );
        $this->assertEquals( 30, $response->json( 'summary.profit' ) );
        $this->assertEquals( 0, $response->json( 'summary.refunds' ) );

        $this->assertEquals( 10, $response->json( 'previous.sales' ) );
        $this->assertEquals( 1, $response->json( 'previous.orders' ) );
        $this->assertEquals( 6, $response->json( 'previous.profit' ) );

        $this->assertGreaterThan( 0, $response->json( 'comparison.sales' ) );
        $this->assertEquals( 400, $response->json( 'comparison.sales' ) );
        $this->assertEquals( 100, $response->json( 'comparison.orders' ) );
        $this->assertGreaterThan( 0, $response->json( 'comparison.profit' ) );

        // Hourly sparkline: 24 buckets with sales at 09h and 11h.
        $this->assertSame( 'hour', $response->json( 'timeline.granularity' ) );
        $this->assertCount( 24, $response->json( 'timeline.values' ) );
        $this->assertEquals( 20, $response->json( 'timeline.values.9' ) );
        $this->assertEquals( 30, $response->json( 'timeline.values.11' ) );
        $this->assertEquals( 50, array_sum( $response->json( 'timeline.values' ) ) );

        // Payment types, top products and latest orders.
        $this->assertEquals( 50, collect( $response->json( 'payments' ) )->firstWhere( 'identifier', 'cash-payment' )['total'] );
        $this->assertSame( $unitQuantity->product_id, $response->json( 'top_products.0.product_id' ) );
        $this->assertEquals( 5, $response->json( 'top_products.0.quantity' ) );
        $this->assertEquals( 50, $response->json( 'top_products.0.revenue' ) );
        $this->assertCount( 5, $response->json( 'recent_orders' ) );
        $this->assertIsArray( $response->json( 'registers' ) );

        // Yesterday as the selected period compares negatively with today.
        $yesterday = $this->getJson( 'api/mplownerapp/metrics?period=yesterday' )->assertOk();
        $this->assertEquals( 10, $yesterday->json( 'summary.sales' ) );
        $this->assertEquals( 10, $yesterday->json( 'summary.average_ticket' ) );
        $this->assertNull( $yesterday->json( 'comparison.sales' ), 'No sales the day before yesterday: change is not measurable.' );

        $week = $this->getJson( 'api/mplownerapp/metrics?period=7d' )->assertOk();
        $this->assertEquals( 60, $week->json( 'summary.sales' ) );
        $this->assertEquals( 3, $week->json( 'summary.orders' ) );
        $this->assertSame( 'day', $week->json( 'timeline.granularity' ) );
        $this->assertCount( 7, $week->json( 'timeline.values' ) );
    }

    public function test_low_stock_item_appears_when_quantity_reaches_threshold(): void
    {
        Sanctum::actingAs( $this->getUserFromRole( Role::ADMIN ), [ '*' ] );

        $unitQuantity = $this->prepareProduct( cogs: 1 );
        $unitQuantity->stock_alert_enabled = true;
        $unitQuantity->low_quantity = 5;
        $unitQuantity->quantity = 5;
        $unitQuantity->save();

        /**
         * Core (ReportService::getLowStockProducts, DetectLowStockProductsJob) uses
         * "low_quantity > quantity": reaching the threshold is not yet low stock.
         */
        $ids = collect( app( OwnerMetricsService::class )->getLowStock( 1000 ) )->pluck( 'unit_quantity_id' );
        $this->assertNotContains( $unitQuantity->id, $ids, 'Quantity equal to the threshold is not low stock in core.' );

        $unitQuantity->quantity = 4;
        $unitQuantity->save();

        $ids = collect( $this->getJson( 'api/mplownerapp/metrics' )->assertOk()->json( 'low_stock' ) )->pluck( 'unit_quantity_id' );
        $this->assertContains( $unitQuantity->id, $ids, 'Quantity below the threshold is low stock.' );

        $unitQuantity->quantity = 6;
        $unitQuantity->save();

        $ids = collect( app( OwnerMetricsService::class )->getLowStock( 1000 ) )->pluck( 'unit_quantity_id' );
        $this->assertNotContains( $unitQuantity->id, $ids, 'Quantity above the threshold is not low stock.' );

        $unitQuantity->quantity = 2;
        $unitQuantity->stock_alert_enabled = false;
        $unitQuantity->save();

        $ids = collect( app( OwnerMetricsService::class )->getLowStock( 1000 ) )->pluck( 'unit_quantity_id' );
        $this->assertNotContains( $unitQuantity->id, $ids, 'Products without stock alert are ignored.' );
    }

    public function test_profit_only_counts_paid_orders_like_the_core_profit_report(): void
    {
        Sanctum::actingAs( $this->getUserFromRole( Role::ADMIN ), [ '*' ] );

        $unitQuantity = $this->prepareProduct( cogs: 4 );

        $this->travelTo( $this->now->copy()->setTime( 9, 0 ) );
        $this->postPaidOrder( $unitQuantity, quantity: 2, unitPrice: 10 );
        $partial = $this->postOrder( $unitQuantity, 3, 10, [
            'payments' => [
                [ 'identifier' => 'cash-payment', 'value' => 5 ],
            ],
        ] );
        $this->travelTo( $this->now );

        $this->assertSame( Order::PAYMENT_PARTIALLY, $partial->payment_status );

        $response = $this->getJson( 'api/mplownerapp/metrics' )->assertOk();

        // Sales keep the dashboard definition (paid + partially paid).
        $this->assertEquals( 50, $response->json( 'summary.sales' ) );
        $this->assertEquals( 2, $response->json( 'summary.orders' ) );

        // Profit follows OrdersService::getSoldStock / ReportService::getSalesSummary: paid orders only.
        $this->assertEquals( 8, $response->json( 'summary.cost' ) );
        $this->assertEquals( 12, $response->json( 'summary.profit' ), 'The partially paid order (3 x 10, COGS 12) must not count in the profit.' );
    }

    public function test_owner_caches_are_purged_after_sign_out_and_on_denied_requests(): void
    {
        $signIn = $this->get( 'sign-in' );
        $signIn->assertOk();
        $signIn->assertSee( 'mplownerapp-', false );
        $signIn->assertSee( 'caches.delete', false );

        $worker = $this->get( 'mplownerapp/owner-sw.js' )->getContent();
        $this->assertStringContainsString( 'purgePrivateCaches', $worker );
        $this->assertMatchesRegularExpression( '/\[\s*401,\s*403,\s*419\s*\]/', $worker );
    }

    public function test_period_parameter_is_validated(): void
    {
        Sanctum::actingAs( $this->getUserFromRole( Role::ADMIN ), [ '*' ] );

        $this->getJson( 'api/mplownerapp/metrics?period=week' )
            ->assertStatus( 422 )
            ->assertJsonValidationErrors( [ 'period' ] );

        foreach ( OwnerMetricsService::PERIODS as $period ) {
            $this->getJson( 'api/mplownerapp/metrics?period=' . $period )
                ->assertOk()
                ->assertJsonPath( 'period', $period );
        }
    }

    public function test_period_ranges_are_equal_and_consecutive(): void
    {
        $service = app( OwnerMetricsService::class );
        $now = Carbon::parse( '2031-03-15 13:00:00' );

        $today = $service->resolveRanges( 'today', $now );
        $this->assertSame( '2031-03-15 00:00:00', $today['current']['start']->toDateTimeString() );
        $this->assertSame( '2031-03-14 23:59:59', $today['previous']['end']->toDateTimeString() );

        $week = $service->resolveRanges( '7d', $now );
        $this->assertSame( '2031-03-09 00:00:00', $week['current']['start']->toDateTimeString() );
        $this->assertSame( '2031-03-02 00:00:00', $week['previous']['start']->toDateTimeString() );
        $this->assertSame( '2031-03-08 23:59:59', $week['previous']['end']->toDateTimeString() );

        $month = $service->resolveRanges( 'month', $now );
        $this->assertSame( '2031-03-01 00:00:00', $month['current']['start']->toDateTimeString() );
        $this->assertSame( '2031-02-01 00:00:00', $month['previous']['start']->toDateTimeString() );
        $this->assertSame( '2031-02-28 23:59:59', $month['previous']['end']->toDateTimeString() );

        $this->assertSame( 50.0, $service->percentChange( 150, 100 ) );
        $this->assertSame( -25.0, $service->percentChange( 75, 100 ) );
        $this->assertNull( $service->percentChange( 10, 0 ) );
        $this->assertSame( 0.0, $service->percentChange( 0, 0 ) );
    }

    public function test_cashier_is_denied(): void
    {
        $cashier = $this->createUserWithRole( Role::STORECASHIER );

        $this->assertFalse( $cashier->allowedTo( [ 'mplownerapp.view' ] ) );

        Sanctum::actingAs( $cashier, [ '*' ] );

        $this->getJson( 'api/mplownerapp/metrics' )->assertForbidden();
        $this->get( 'dashboard/owner' )->assertForbidden();
    }

    public function test_store_administrator_is_allowed(): void
    {
        $storeAdmin = $this->createUserWithRole( Role::STOREADMIN );

        $this->assertTrue( $storeAdmin->allowedTo( [ 'mplownerapp.view' ] ) );

        Sanctum::actingAs( $storeAdmin, [ '*' ] );

        $this->getJson( 'api/mplownerapp/metrics' )->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson( 'api/mplownerapp/metrics' )->assertUnauthorized();
        $this->get( 'dashboard/owner' )->assertRedirect();
    }

    public function test_manifest_is_valid_json_with_icons(): void
    {
        $response = $this->get( 'mplownerapp/manifest.webmanifest' );

        $response->assertOk();
        $this->assertStringContainsString( 'application/manifest+json', $response->headers->get( 'Content-Type' ) );

        $manifest = json_decode( $response->getContent(), true, flags: JSON_THROW_ON_ERROR );

        $this->assertSame( 'MyPosLab Owner', $manifest['name'] );
        $this->assertSame( 'MyPosLab', $manifest['short_name'] );
        $this->assertSame( 'standalone', $manifest['display'] );
        $this->assertSame( '#4F46E5', $manifest['theme_color'] );
        $this->assertSame( '#0F172A', $manifest['background_color'] );
        $this->assertStringEndsWith( '/dashboard/owner', $manifest['start_url'] );

        $icons = collect( $manifest['icons'] );
        $this->assertTrue( $icons->contains( fn( $icon ) => str_ends_with( $icon['src'], '/images/brand/icon-192.png' ) && $icon['sizes'] === '192x192' ) );
        $this->assertTrue( $icons->contains( fn( $icon ) => str_ends_with( $icon['src'], '/images/brand/icon-512.png' ) && $icon['sizes'] === '512x512' ) );
        $this->assertTrue( $icons->contains( fn( $icon ) => str_ends_with( $icon['src'], '/apple-touch-icon.png' ) ) );
    }

    public function test_service_worker_is_served_with_scope_header(): void
    {
        $response = $this->get( 'mplownerapp/owner-sw.js' );

        $response->assertOk();
        $response->assertHeader( 'Service-Worker-Allowed', '/' );
        $this->assertStringContainsString( 'javascript', $response->headers->get( 'Content-Type' ) );
        $this->assertStringContainsString( "addEventListener( 'fetch'", $response->getContent() );
    }

    public function test_owner_page_and_connect_page_render_for_admin(): void
    {
        $admin = $this->getUserFromRole( Role::ADMIN );
        $this->actingAs( $admin );

        $page = $this->get( 'dashboard/owner' );
        $page->assertOk();
        $page->assertSee( 'rel="manifest"', false );
        $page->assertSee( 'apple-mobile-web-app-capable', false );
        $page->assertSee( 'mplownerapp-root', false );
        $page->assertSee( 'MplOwnerAppConfig', false );
        $page->assertSee( 'api\/mplownerapp\/metrics', false );

        $connect = $this->get( 'dashboard/owner/connect' );
        $connect->assertOk();
        $connect->assertSee( 'data:image/svg+xml;base64,', false );
        $connect->assertSee( url( '/dashboard/owner' ), false );
    }

    public function test_menu_entry_is_injected_for_admin(): void
    {
        $this->actingAs( $this->getUserFromRole( Role::ADMIN ) );

        $menus = OwnerMenuService::injectMenu( [
            'dashboard' => [ 'label' => 'Dashboard' ],
            'orders' => [ 'label' => 'Orders' ],
        ] );

        $this->assertSame( [ 'dashboard', 'mplownerapp', 'orders' ], array_keys( $menus ) );
        $this->assertSame( [ 'mplownerapp.view' ], $menus['mplownerapp']['permissions'] );
        $this->assertStringEndsWith( '/dashboard/owner/connect', $menus['mplownerapp']['href'] );

        $rootMenus = OwnerMenuService::injectMenu( [ 'dashboard' => [ 'label' => 'Dashboard' ] ] );
        $this->assertArrayNotHasKey( 'mplownerapp', $rootMenus, 'No menu without the store "orders" anchor (Multistore root).' );

        $filtered = Hook::filter( 'ns-dashboard-menus', [
            'dashboard' => [ 'label' => 'Dashboard' ],
            'orders' => [ 'label' => 'Orders' ],
        ] );
        $this->assertArrayHasKey( 'mplownerapp', $filtered, 'The provider registers the menu filter.' );
    }

    /**
     * Enables the module and runs its migrations through the core ModulesService.
     * Module routes and permission gates are registered at boot, so the
     * application is refreshed when anything changed.
     */
    private function ensureModuleIsEnabled(): void
    {
        $modulesService = app( ModulesService::class );
        $module = $modulesService->get( self::MODULE );

        $this->assertIsArray( $module, 'The MplOwnerApp module must be discoverable.' );

        $changed = false;

        if ( ! $module['enabled'] ) {
            $result = $modulesService->enable( self::MODULE );
            $this->assertIsArray( $result );
            $this->assertSame( 'success', $result['status'] );
            $changed = true;
        }

        if ( count( $modulesService->getMigrations( self::MODULE ) ) > 0 ) {
            $modulesService->runAllMigration( self::MODULE );
            $changed = true;
        }

        if ( $changed ) {
            $this->refreshApplication();
        }
    }

    private function getUserFromRole( string $roleNamespace ): User
    {
        $user = User::whereHas( 'roles', fn( $query ) => $query->where( 'namespace', $roleNamespace ) )->first();

        return $user instanceof User ? $user : $this->createUserWithRole( $roleNamespace );
    }

    private function createUserWithRole( string $roleNamespace ): User
    {
        $role = Role::namespace( $roleNamespace );
        $this->assertInstanceOf( Role::class, $role );

        $user = new User;
        $user->username = 'mplowner_' . Str::lower( Str::random( 10 ) );
        $user->email = $user->username . '@myposlab-test.invalid';
        $user->password = bcrypt( Str::random( 16 ) );
        $user->active = true;
        $user->save();

        app( UsersService::class )->setUserRole( $user, [ $role->id ] );

        return $user->fresh();
    }

    /**
     * Picks a simple stock-managed product, removes its taxes and gives it a
     * known COGS and enough stock (inside the test transaction).
     */
    private function prepareProduct( float $cogs ): ProductUnitQuantity
    {
        $product = Product::withStockEnabled()
            ->notGrouped()
            ->notInGroup()
            ->whereHas( 'unit_quantities' )
            ->firstOrFail();

        $product->tax_type = 'disabled';
        $product->tax_group_id = null;
        $product->save();

        $unitQuantity = $product->unit_quantities()->firstOrFail();
        $unitQuantity->quantity = 10000;
        $unitQuantity->cogs = $cogs;
        $unitQuantity->stock_alert_enabled = false;
        $unitQuantity->save();

        return $unitQuantity->fresh();
    }

    private function postPaidOrder( ProductUnitQuantity $unitQuantity, int $quantity, float $unitPrice ): Order
    {
        $total = $quantity * $unitPrice;

        return $this->postOrder( $unitQuantity, $quantity, $unitPrice, [
            'payments' => [
                [ 'identifier' => 'cash-payment', 'value' => $total ],
            ],
        ] );
    }

    private function postHoldOrder( ProductUnitQuantity $unitQuantity, int $quantity, float $unitPrice ): Order
    {
        return $this->postOrder( $unitQuantity, $quantity, $unitPrice, [
            'payment_status' => Order::PAYMENT_HOLD,
        ] );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function postOrder( ProductUnitQuantity $unitQuantity, int $quantity, float $unitPrice, array $overrides ): Order
    {
        $payload = array_merge( [
            'customer_id' => Customer::firstOrFail()->id,
            'type' => [ 'identifier' => 'takeaway' ],
            'shipping' => 0,
            'discount' => 0,
            'discount_type' => 'flat',
            'discount_percentage' => 0,
            'subtotal' => $quantity * $unitPrice,
            'products' => [
                [
                    'product_id' => $unitQuantity->product_id,
                    'unit_quantity_id' => $unitQuantity->id,
                    'unit_id' => $unitQuantity->unit_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => 0,
                    'discount_percentage' => 0,
                ],
            ],
        ], $overrides );

        $response = $this->postJson( 'api/orders', $payload );
        $response->assertOk();

        return Order::findOrFail( $response->json( 'data.order.id' ) );
    }
}
