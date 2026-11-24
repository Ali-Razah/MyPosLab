<?php

namespace Modules\MplBatches\Tests;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderProductRefund;
use App\Models\Procurement;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\Provider;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\ModulesService;
use App\Services\Options;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\MplBatches\Providers\MplBatchesServiceProvider;
use Modules\MplBatches\Services\BatchService;
use Tests\TestCase as ApplicationTestCase;
use Tests\Traits\WithAuthentication;

abstract class MplBatchesTestCase extends ApplicationTestCase
{
    use WithAuthentication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureModuleIsEnabled();

        ns()->option->set( BatchService::OPTION_ENABLED, 'yes' );
        ns()->option->set( BatchService::OPTION_ALERT_DAYS, 30 );
        ns()->option->set( BatchService::OPTION_CRITICAL_DAYS, 7 );
        ns()->option->set( BatchService::OPTION_BLOCK_EXPIRED, BatchService::BLOCK_FOLLOW_PRODUCT );

        $this->attemptAuthenticate();
    }

    /**
     * Enables the module (option + migrations) on the isolated test database, then
     * reboots the application so listeners, routes and providers are discovered.
     * The symlink step of ModulesService::enable() is skipped on purpose: it
     * touches the shared public/ directory.
     */
    private function ensureModuleIsEnabled(): void
    {
        /**
         * @var ModulesService $modules
         */
        $modules = app()->make( ModulesService::class );
        $options = app()->make( Options::class );
        $enabled = $options->get( 'enabled_modules', [] ) ?: [];
        $mustRefresh = false;

        if ( ! in_array( 'MplBatches', $enabled, true ) ) {
            $enabled[] = 'MplBatches';
            $options->set( 'enabled_modules', array_values( $enabled ) );
            $mustRefresh = true;
        }

        if ( ! empty( $modules->getMigrations( 'MplBatches' ) ) ) {
            $modules->runAllMigration( 'MplBatches' );
            $mustRefresh = true;
        }

        if ( $mustRefresh || ! class_exists( MplBatchesServiceProvider::class, false ) ) {
            $this->refreshApplication();
        }
    }

    protected function baseUnit(): Unit
    {
        return Unit::where( 'base_unit', true )->orderBy( 'id' )->firstOrFail();
    }

    /**
     * A fresh product with no stock, so every test controls its own batches.
     */
    protected function createProduct( bool $expires = true, string $onExpiration = Product::EXPIRES_PREVENT_SALES ): Product
    {
        $unit = $this->baseUnit();
        $template = Product::where( 'type', Product::TYPE_MATERIALIZED )
            ->where( 'unit_group', $unit->group_id )
            ->whereHas( 'unit_quantities', fn( $query ) => $query->where( 'unit_id', $unit->id ) )
            ->firstOrFail();

        $code = strtoupper( Str::random( 10 ) );

        $product = $template->replicate( [ 'uuid' ] );
        $product->name = 'MplBatch Test ' . $code;
        $product->sku = 'MPLB-' . $code;
        $product->barcode = 'MPLB' . $code;
        $product->stock_management = Product::STOCK_MANAGEMENT_ENABLED;
        $product->accurate_tracking = false;
        $product->expires = $expires;
        $product->on_expiration = $onExpiration;
        $product->status = 'available';
        $product->save();

        $templateQuantity = ProductUnitQuantity::where( 'product_id', $template->id )->where( 'unit_id', $unit->id )->firstOrFail();
        $unitQuantity = $templateQuantity->replicate( [ 'uuid', 'barcode', 'scale_plu' ] );
        $unitQuantity->product_id = $product->id;
        $unitQuantity->quantity = 0;
        $unitQuantity->sale_price = 10;
        $unitQuantity->sale_price_edit = 10;
        $unitQuantity->sale_price_net = 10;
        $unitQuantity->sale_price_gross = 10;
        $unitQuantity->sale_price_tax = 0;
        $unitQuantity->barcode = 'MPLBU' . $code;
        $unitQuantity->save();

        return $product->fresh();
    }

    protected function unitQuantity( Product $product ): ProductUnitQuantity
    {
        return ProductUnitQuantity::where( 'product_id', $product->id )->where( 'unit_id', $this->baseUnit()->id )->firstOrFail();
    }

    /**
     * Receives stock through the procurement API (same payload as the procurement screen).
     */
    protected function procure( Product $product, float $quantity, ?string $expiry, ?string $batchNumber = null, float $cost = 4, string $deliveryStatus = Procurement::DELIVERED ): array
    {
        $line = [
            'product_id' => $product->id,
            'unit_id' => $this->baseUnit()->id,
            'quantity' => $quantity,
            'gross_purchase_price' => $cost,
            'net_purchase_price' => $cost,
            'purchase_price' => $cost,
            'total_purchase_price' => $cost * $quantity,
            'tax_group_id' => null,
            'tax_type' => 'inclusive',
            'tax_value' => 0,
            'expiration_date' => $expiry,
        ];

        if ( $batchNumber !== null ) {
            $line[ 'batch_number' ] = $batchNumber;
        }

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/procurements', [
                'name' => 'MplBatches procurement ' . Str::random( 5 ),
                'general' => [
                    'provider_id' => Provider::firstOrFail()->id,
                    'payment_status' => Procurement::PAYMENT_PAID,
                    'delivery_status' => $deliveryStatus,
                    'automatic_approval' => 1,
                ],
                'products' => [ $line ],
            ] );

        $response->assertOk();

        return $response->json( 'data' );
    }

    protected function sell( Product $product, float $quantity ): TestResponse
    {
        $total = 10 * $quantity;

        return $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/orders', [
                'customer_id' => $this->anyCustomerId(),
                'type' => [ 'identifier' => 'takeaway' ],
                'shipping' => 0,
                'discount' => 0,
                'discount_percentage' => 0,
                'products' => [
                    [
                        'product_id' => $product->id,
                        'unit_quantity_id' => $this->unitQuantity( $product )->id,
                        'unit_id' => $this->baseUnit()->id,
                        'quantity' => $quantity,
                        'unit_price' => 10,
                    ],
                ],
                'payments' => [
                    [
                        'identifier' => OrderPayment::PAYMENT_CASH,
                        'value' => $total,
                    ],
                ],
            ] );
    }

    protected function refund( array $order, float $quantity, string $condition = OrderProductRefund::CONDITION_UNSPOILED ): TestResponse
    {
        $orderProduct = $order[ 'products' ][ 0 ];

        return $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/orders/' . $order[ 'id' ] . '/refund', [
                'payment' => [
                    'identifier' => OrderPayment::PAYMENT_CASH,
                ],
                'refund_shipping' => false,
                'total' => 10 * $quantity,
                'products' => [
                    [
                        'id' => $orderProduct[ 'id' ],
                        'quantity' => $quantity,
                        'unit_price' => 10,
                        'condition' => $condition,
                        'description' => 'MplBatches test refund',
                    ],
                ],
            ] );
    }

    protected function anyCustomerId(): int
    {
        $customer = Customer::first();

        if ( $customer instanceof Customer ) {
            return $customer->id;
        }

        return User::whereHas( 'roles', fn( $query ) => $query->where( 'namespace', Role::STORECUSTOMER ) )->firstOrFail()->id;
    }

    protected function actAsUserWithoutBatchPermissions(): User
    {
        $user = User::whereHas( 'roles', fn( $query ) => $query->where( 'namespace', 'user' ) )->first();

        if ( ! $user instanceof User ) {
            $user = User::whereDoesntHave( 'roles', fn( $query ) => $query->whereIn( 'namespace', [ Role::ADMIN, Role::STOREADMIN ] ) )->firstOrFail();
        }

        $this->assertFalse( $user->allowedTo( [ 'mplbatches.read' ] ), 'The selected user unexpectedly has the batch permission.' );

        Sanctum::actingAs( $user, [ '*' ] );

        return $user;
    }

    protected function today(): string
    {
        return app()->make( BatchService::class )->today();
    }

    protected function daysFromToday( int $days ): string
    {
        return now( ns()->date->getTimezone() )->addDays( $days )->toDateString();
    }

    protected function assertOrderSucceeded( TestResponse $response ): array
    {
        $this->assertSame( 200, $response->status(), (string) json_encode( $response->json( 'message' ) ?? $response->getContent() ) );
        $response->assertJsonPath( 'status', 'success' );

        $order = $response->json( 'data.order' );
        $this->assertNotEquals( Order::PAYMENT_HOLD, $order[ 'payment_status' ] );

        return $order;
    }
}
