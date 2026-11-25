<?php

namespace Modules\MplEInvoice\Tests;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderProductRefund;
use App\Models\ProductUnitQuantity;
use App\Models\Tax;
use App\Models\TaxGroup;
use App\Models\User;
use App\Services\ModulesService;
use Modules\MplEInvoice\Models\EInvoiceCustomerProfile;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceService;
use Modules\MplEInvoice\Services\EInvoiceSettings;
use Tests\TestCase;
use Tests\Traits\WithAuthentication;

/**
 * Enables and migrates the module on the isolated test database, then
 * provides helpers creating real orders through the NexoPOS API.
 */
abstract class EInvoiceTestCase extends TestCase
{
    use WithAuthentication;

    const MODULE = 'MplEInvoice';

    protected function setUp(): void
    {
        parent::setUp();

        /**
         * @var ModulesService $modules
         */
        $modules = app()->make( ModulesService::class );
        $module = $modules->get( self::MODULE );

        $this->assertIsArray( $module, 'The MplEInvoice module was not discovered.' );

        if ( ! $module[ 'enabled' ] ) {
            $enabled = (array) ns()->option->get( 'enabled_modules', [] );
            $enabled[] = self::MODULE;
            ns()->option->set( 'enabled_modules', array_values( array_unique( $enabled ) ) );

            /**
             * Reboot the application so that the module providers,
             * routes, listeners, CRUD and settings are discovered.
             */
            $this->refreshApplication();
            $modules = app()->make( ModulesService::class );
            $modules->runAllMigration( self::MODULE );

            /**
             * Permissions are registered as gates when the application boots,
             * so boot again to pick up the ones the migrations just created.
             */
            $this->refreshApplication();
            $modules = app()->make( ModulesService::class );
        }

        $modules->runAllMigration( self::MODULE );

        $this->configureModule();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function configureModule( array $overrides = [] ): void
    {
        $defaults = [
            EInvoiceSettings::ENABLED => 'yes',
            EInvoiceSettings::SELLER_NAME_EN => 'MyPosLab Trading LLC',
            EInvoiceSettings::SELLER_NAME_AR => 'ماي بوس لاب للتجارة ذ.م.م',
            EInvoiceSettings::SELLER_TRN => '100123456700003',
            EInvoiceSettings::SELLER_ADDRESS => 'Sheikh Zayed Road',
            EInvoiceSettings::SELLER_CITY => 'Dubai',
            EInvoiceSettings::SELLER_EMIRATE => 'DU',
            EInvoiceSettings::VAT_RATE => 5,
            EInvoiceSettings::INVOICE_PREFIX => 'INV-',
            EInvoiceSettings::CREDIT_NOTE_PREFIX => 'CN-',
            EInvoiceSettings::AUTO_SUBMIT => 'no',
            EInvoiceSettings::ASP_ENDPOINT => '',
        ];

        foreach ( array_merge( $defaults, $overrides ) as $key => $value ) {
            ns()->option->set( $key, $value );
        }
    }

    protected function taxGroup( float $rate, string $name ): TaxGroup
    {
        $group = TaxGroup::where( 'name', $name )->first();

        if ( $group instanceof TaxGroup ) {
            return $group;
        }

        $group = new TaxGroup;
        $group->name = $name;
        $group->author_id = User::first()->id;
        $group->save();

        $tax = new Tax;
        $tax->name = $name;
        $tax->rate = $rate;
        $tax->tax_group_id = $group->id;
        $tax->author_id = $group->author_id;
        $tax->save();

        return $group;
    }

    protected function createCustomer( ?string $trn = null ): Customer
    {
        $customer = Customer::factory()->create( [
            'group_id' => CustomerGroup::first()->id,
        ] );

        if ( $trn !== null ) {
            EInvoiceCustomerProfile::create( [
                'customer_id' => $customer->id,
                'trn' => $trn,
                'legal_name' => 'Gulf Buyer Trading LLC',
                'address' => 'Al Wahda Street',
                'city' => 'Sharjah',
                'emirate' => 'SH',
            ] );
        }

        return $customer;
    }

    /**
     * Build a POS-like quick product line with VAT-inclusive pricing.
     *
     * @return array<string, mixed>
     */
    protected function line( string $name, float $quantity, float $grossUnitPrice, TaxGroup $group, float $rate ): array
    {
        $unitQuantity = ProductUnitQuantity::first();
        $net = round( $grossUnitPrice / ( 1 + $rate / 100 ), 2 );

        return [
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => $grossUnitPrice,
            'price_gross' => $grossUnitPrice,
            'price_net' => $net,
            'tax_group_id' => $group->id,
            'tax_type' => 'inclusive',
            'unit_quantity_id' => $unitQuantity->id,
            'unit_id' => $unitQuantity->unit_id,
        ];
    }

    /**
     * Place a fully paid order through the NexoPOS API.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    protected function placePaidOrder( Customer $customer, array $lines, float $discount = 0, float $taxValue = 0 ): Order
    {
        $subtotal = collect( $lines )->sum( fn( $line ) => $line[ 'unit_price' ] * $line[ 'quantity' ] );

        $response = $this->withSession( $this->app[ 'session' ]->all() )->json( 'POST', 'api/orders', [
            'customer_id' => $customer->id,
            'type' => [ 'identifier' => 'takeaway' ],
            'discount_type' => $discount > 0 ? 'flat' : null,
            'discount' => $discount,
            'discount_percentage' => 0,
            'shipping' => 0,
            'tax_value' => $taxValue,
            'subtotal' => $subtotal,
            'products' => $lines,
            'payments' => [
                [
                    'identifier' => OrderPayment::PAYMENT_CASH,
                    'value' => $subtotal - $discount,
                ],
            ],
        ] );

        $response->assertOk()->assertJsonPath( 'status', 'success' );

        $order = Order::findOrFail( $response->json( 'data.order.id' ) );
        $this->assertSame( Order::PAYMENT_PAID, $order->payment_status );

        return $order;
    }

    protected function refundFirstProduct( Order $order, float $quantity ): array
    {
        $orderProduct = $order->products()->first();

        $response = $this->withSession( $this->app[ 'session' ]->all() )->json( 'POST', 'api/orders/' . $order->id . '/refund', [
            'payment' => [ 'identifier' => OrderPayment::PAYMENT_CASH ],
            'refund_shipping' => false,
            'products' => [
                [
                    'id' => $orderProduct->id,
                    'quantity' => $quantity,
                    'unit_price' => $orderProduct->unit_price,
                    'condition' => OrderProductRefund::CONDITION_UNSPOILED,
                    'description' => 'Customer returned the item',
                ],
            ],
        ] );

        $response->assertOk()->assertJsonPath( 'status', 'success' );

        return $response->json( 'data' );
    }

    protected function documentForOrder( Order $order ): ?EInvoiceDocument
    {
        return EInvoiceDocument::where( 'source_key', EInvoiceService::orderSourceKey( $order->id ) )->first();
    }

    protected function xml( EInvoiceDocument $document ): \DOMXPath
    {
        $dom = new \DOMDocument;
        $this->assertTrue( $dom->loadXML( $document->xml ), 'The generated XML is not well-formed.' );

        $xpath = new \DOMXPath( $dom );
        $xpath->registerNamespace( 'cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2' );
        $xpath->registerNamespace( 'cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2' );
        $xpath->registerNamespace( 'inv', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' );
        $xpath->registerNamespace( 'cn', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2' );

        return $xpath;
    }

    protected function value( \DOMXPath $xpath, string $query ): string
    {
        $nodes = $xpath->query( $query );
        $this->assertGreaterThan( 0, $nodes->length, 'Missing XML node: ' . $query );

        return trim( $nodes->item( 0 )->textContent );
    }
}
