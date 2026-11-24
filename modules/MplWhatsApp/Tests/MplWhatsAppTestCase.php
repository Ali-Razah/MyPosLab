<?php

namespace Modules\MplWhatsApp\Tests;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Models\Role;
use App\Models\User;
use App\Services\ModulesService;
use App\Services\Options;
use App\Services\OrdersService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use RuntimeException;
use Tests\TestCase;

/**
 * Enables the module (and runs its migrations) in the isolated module test
 * database, then reboots the application so module routes, listeners and
 * providers are registered exactly like in production.
 */
abstract class MplWhatsAppTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /**
         * @var ModulesService $modules
         */
        $modules = app()->make( ModulesService::class );

        if ( ! $modules->getIfEnabled( 'MplWhatsApp' ) || ! empty( $modules->getMigrations( 'MplWhatsApp' ) ) ) {
            if ( ! $modules->getIfEnabled( 'MplWhatsApp' ) ) {
                $result = $modules->enable( 'MplWhatsApp' );

                if ( ! is_array( $result ) || ( $result[ 'status' ] ?? null ) !== 'success' ) {
                    throw new RuntimeException( 'Unable to enable MplWhatsApp: ' . json_encode( is_array( $result ) ? $result : $result->getData() ) );
                }
            }

            $modules->runAllMigration( 'MplWhatsApp' );
            $this->refreshApplication();
        }

        $this->configure( [
            WhatsAppConfig::ENABLED => 'yes',
            WhatsAppConfig::COUNTRY_CODE => '971',
            WhatsAppConfig::MODE => WhatsAppMessage::MODE_LINK,
            WhatsAppConfig::MESSAGE_TEMPLATE => null,
            WhatsAppConfig::LINK_VALIDITY_DAYS => 30,
            WhatsAppConfig::AUTO_SEND => 'no',
            WhatsAppConfig::CLOUD_PHONE_NUMBER_ID => null,
            WhatsAppConfig::CLOUD_ACCESS_TOKEN => null,
            WhatsAppConfig::CLOUD_TEMPLATE_NAME => null,
            WhatsAppConfig::CLOUD_TEMPLATE_LANGUAGE => null,
            WhatsAppConfig::CLOUD_TEMPLATE_PARAMS => null,
            WhatsAppConfig::CLOUD_API_VERSION => null,
        ] );
    }

    /**
     * @param array<string, mixed> $values null deletes the option
     */
    protected function configure( array $values ): void
    {
        $options = app()->make( Options::class );

        foreach ( $values as $key => $value ) {
            $value === null ? $options->delete( $key ) : $options->set( $key, $value );
        }
    }

    protected function configureCloudApi( bool $autoSend = false ): void
    {
        $this->configure( [
            WhatsAppConfig::MODE => WhatsAppMessage::MODE_CLOUD_API,
            WhatsAppConfig::CLOUD_PHONE_NUMBER_ID => '1234567890',
            WhatsAppConfig::CLOUD_TEMPLATE_NAME => 'order_receipt',
            WhatsAppConfig::CLOUD_TEMPLATE_LANGUAGE => 'en',
            WhatsAppConfig::CLOUD_TEMPLATE_PARAMS => '{customer},{order},{link}',
            WhatsAppConfig::AUTO_SEND => $autoSend ? 'yes' : 'no',
        ] );

        app()->make( WhatsAppConfig::class )->setCloudAccessToken( 'test-permanent-token' );
    }

    protected function admin(): User
    {
        return User::whereHas( 'roles', fn( $query ) => $query->where( 'namespace', Role::ADMIN ) )->firstOrFail();
    }

    protected function actingAsAdmin(): User
    {
        $admin = $this->admin();
        Sanctum::actingAs( $admin, [ '*' ] );

        return $admin;
    }

    protected function userWithRole( string $roleNamespace ): User
    {
        $user = new User;
        $user->username = 'mplwa_' . Str::lower( Str::random( 10 ) );
        $user->email = $user->username . '@example.test';
        $user->password = Hash::make( Str::random( 16 ) );
        $user->active = true;
        $user->save();
        $user->assignRole( $roleNamespace );

        return $user->fresh();
    }

    protected function createCustomer( ?string $phone = '0501234567' ): Customer
    {
        return Customer::factory()->create( [
            'first_name' => 'Layla',
            'last_name' => 'Hassan',
            'phone' => $phone,
        ] );
    }

    /**
     * Creates a real, fully paid order through the core OrdersService.
     */
    protected function createPaidOrder( ?Customer $customer = null ): Order
    {
        $customer = $customer ?: $this->createCustomer();

        $product = Product::withStockEnabled()
            ->notGrouped()
            ->with( 'unit_quantities' )
            ->firstOrFail();

        $product->unit_quantities->each( function ( ProductUnitQuantity $unitQuantity ) {
            $unitQuantity->quantity = 10000;
            $unitQuantity->save();
        } );

        $unitQuantity = $product->unit_quantities->first();
        $quantity = 2;
        $total = $unitQuantity->sale_price * $quantity;

        /**
         * OrdersService checks the current user permissions (payments, discounts...),
         * so the order is created as the administrator, then the previous user is restored.
         */
        $previousUser = Auth::user();
        Auth::setUser( $this->admin() );

        try {
            $result = app()->make( OrdersService::class )->create( [
                'customer_id' => $customer->id,
                'author_id' => $this->admin()->id,
                'type' => [ 'identifier' => 'takeaway' ],
                'products' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitQuantity->sale_price,
                        'unit_quantity_id' => $unitQuantity->id,
                    ],
                ],
                'payments' => [
                    [ 'identifier' => 'cash-payment', 'value' => $total + 5 ],
                ],
            ] );
        } finally {
            $previousUser ? Auth::setUser( $previousUser ) : Auth::forgetGuards();
        }

        /**
         * @var Order $order
         */
        $order = $result[ 'data' ][ 'order' ];

        return $order->fresh();
    }
}
