<?php

namespace Modules\MplBranches\Tests;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Services\ModulesService;
use App\Services\UsersService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Services\BranchService;
use Tests\TestCase;

abstract class MplBranchesTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureModuleIsReady();
    }

    /**
     * Enables the module and runs its migrations on the isolated test database.
     * The application is refreshed afterwards so listeners, routes, hooks and
     * permission gates of the module are booted like in production.
     */
    protected function ensureModuleIsReady(): void
    {
        /**
         * @var ModulesService $modules
         */
        $modules = app()->make( ModulesService::class );
        $module = $modules->get( 'MplBranches' );
        $mustRefresh = false;

        if ( ! $module[ 'enabled' ] ) {
            $result = $modules->enable( 'MplBranches' );
            $this->assertIsArray( $result, 'The module MplBranches could not be enabled.' );
            $mustRefresh = true;
        }

        if ( ! empty( $modules->getMigrations( 'MplBranches' ) ) ) {
            $modules->runAllMigration( 'MplBranches' );
            $mustRefresh = true;
        }

        if ( $mustRefresh ) {
            $this->refreshApplication();
        }

        $this->assertTrue( (bool) app()->make( ModulesService::class )->get( 'MplBranches' )[ 'enabled' ] );
    }

    protected function branchService(): BranchService
    {
        return app()->make( BranchService::class );
    }

    protected function actingAsUser( User $user ): void
    {
        Sanctum::actingAs( $user, [ '*' ] );
    }

    protected function admin(): User
    {
        return User::whereHas( 'roles', fn ( $query ) => $query->where( 'namespace', Role::ADMIN ) )->firstOrFail();
    }

    protected function createStaff( string $role = Role::STORECASHIER ): User
    {
        $user = new User;
        $user->username = 'mplbr-' . Str::lower( Str::random( 10 ) );
        $user->email = $user->username . '@example.test';
        $user->password = bcrypt( Str::random( 16 ) );
        $user->active = true;
        $user->author_id = $this->admin()->id;
        $user->save();
        $user->assignRole( $role );
        app()->make( UsersService::class )->createAttribute( $user );

        return $user->fresh();
    }

    protected function createBranch( array $attributes = [] ): Branch
    {
        $branch = new Branch;
        $branch->fill( array_merge( [
            'name' => 'Branch ' . Str::upper( Str::random( 5 ) ),
            'code' => 'T-' . Str::upper( Str::random( 8 ) ),
            'city' => 'Dubai',
            'emirate' => 'Dubai',
            'is_default' => false,
            'active' => true,
        ], $attributes ) );
        $branch->save();

        return $branch;
    }

    protected function createRegister(): Register
    {
        $register = new Register;
        $register->name = 'MplBr Register ' . Str::random( 5 );
        $register->status = Register::STATUS_CLOSED;
        $register->author_id = $this->admin()->id;
        $register->save();

        return $register;
    }

    /**
     * Creates a paid order through the core POS API (api/orders).
     */
    protected function createPaidOrder( array $extra = [], int $quantity = 2 ): Order
    {
        $product = Product::withStockEnabled()
            ->notGrouped()
            ->whereHas( 'unit_quantities', fn ( $query ) => $query->where( 'sale_price', '>', 0 ) )
            ->with( 'unit_quantities' )
            ->firstOrFail();

        /**
         * @var ProductUnitQuantity $unitQuantity
         */
        $unitQuantity = $product->unit_quantities->first( fn ( $unitQuantity ) => $unitQuantity->sale_price > 0 );
        $unitQuantity->quantity = 100000;
        $unitQuantity->save();

        $subtotal = $unitQuantity->sale_price * $quantity;

        $payload = array_merge( [
            'customer_id' => Customer::firstOrFail()->id,
            'type' => [ 'identifier' => 'takeaway' ],
            'subtotal' => $subtotal,
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitQuantity->sale_price,
                    'unit_quantity_id' => $unitQuantity->id,
                ],
            ],
            'payments' => [
                [
                    'identifier' => 'cash-payment',
                    'value' => $subtotal * 2,
                ],
            ],
        ], $extra );

        if ( isset( $extra[ 'register_id' ] ) ) {
            $payload[ 'payments' ][ 0 ][ 'register_id' ] = $extra[ 'register_id' ];
        }

        $response = $this->withSession( $this->app[ 'session' ]->all() )
            ->json( 'POST', 'api/orders', $payload );

        $response->assertOk();

        return Order::findOrFail( $response->json( 'data.order.id' ) );
    }
}
