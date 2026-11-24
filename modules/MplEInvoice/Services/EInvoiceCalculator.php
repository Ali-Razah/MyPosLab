<?php

namespace Modules\MplEInvoice\Services;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductRefund;
use App\Models\OrderRefund;
use App\Models\TaxGroup;
use Illuminate\Support\Str;

/**
 * Turns NexoPOS orders and refunds into a VAT breakdown that reconciles
 * exactly with the amounts recorded on the order.
 *
 * All the arithmetic is performed in integer fils (1/100 AED) so that
 * the totals written to the XML and the printed invoice add up exactly.
 *
 * - "inclusive" pricing (the norm for UAE retail): line amounts already
 *   include VAT, VAT is extracted per tax category from the amount paid.
 * - "exclusive" pricing: NexoPOS added the VAT on top of the line amounts.
 *
 * The payable amount always equals the order (or refund) total. Any
 * difference between that total and "net + VAT" (e.g. NexoPOS computing
 * VAT before an order discount) is exposed as a payable rounding amount.
 */
class EInvoiceCalculator
{
    const CATEGORY_STANDARD = 'S';

    const CATEGORY_ZERO = 'Z';

    const CATEGORY_EXEMPT = 'E';

    /**
     * @var array<int, array{rate: float, category: string}>
     */
    private array $taxGroupCache = [];

    public function __construct( private EInvoiceSettings $settings ) {}

    /**
     * Build the breakdown of a paid order.
     *
     * @return array<string, mixed>
     */
    public function fromOrder( Order $order ): array
    {
        $order->loadMissing( [ 'products', 'taxes' ] );

        $products = $order->products
            ->filter( fn( OrderProduct $product ) => (float) $product->quantity > 0 )
            ->values();

        /**
         * Detect the pricing on the unrounded amounts: line totals may carry more
         * than 2 decimals (weighed items), so the rounded order total can exceed
         * the sum of the rounded lines by a fils without any VAT added on top.
         */
        $extra = (float) $order->total - (
            $products->sum( fn( OrderProduct $product ) => (float) $product->total_price )
            - (float) $order->discount
            - (float) $order->total_coupons
            + (float) $order->shipping
        );

        $lines = $products
            ->map( function ( OrderProduct $product ) use ( $order ) {
                $tax = $this->resolveTax( (int) $product->tax_group_id, $order );

                return [
                    'name' => (string) $product->name,
                    'quantity' => (float) $product->quantity,
                    'unit_name' => (string) $product->unit_name,
                    'amount' => self::toFils( $product->total_price ),
                    'rate' => $tax[ 'rate' ],
                    'category' => $tax[ 'category' ],
                ];
            } )
            ->all();

        return $this->compute(
            lines: $lines,
            discount: self::toFils( (float) $order->discount + (float) $order->total_coupons ),
            shipping: self::toFils( $order->shipping ),
            gross: self::toFils( $order->total ),
            pricing: round( $extra * 100, 6 ) > 0.5 ? 'exclusive' : 'inclusive'
        );
    }

    /**
     * Build the breakdown of a refund (credit note).
     *
     * @return array<string, mixed>
     */
    public function fromRefund( OrderRefund $refund, ?string $originalPricing = null ): array
    {
        $refund->loadMissing( [ 'refunded_products.orderProduct', 'order.taxes' ] );

        $lines = $refund->refunded_products
            ->map( function ( OrderProductRefund $refundedProduct ) use ( $refund ) {
                $orderProduct = $refundedProduct->orderProduct;
                $tax = $this->resolveTax( (int) ( $orderProduct->tax_group_id ?? 0 ), $refund->order );

                return [
                    'name' => (string) ( $orderProduct->name ?? __m( 'Refunded item', 'MplEInvoice' ) ),
                    'quantity' => (float) $refundedProduct->quantity,
                    'unit_name' => (string) ( $orderProduct->unit_name ?? '' ),
                    'amount' => self::toFils( $refundedProduct->total_price ),
                    'rate' => $tax[ 'rate' ],
                    'category' => $tax[ 'category' ],
                ];
            } )
            ->values()
            ->all();

        $shipping = self::toFils( $refund->shipping );

        return $this->compute(
            lines: $lines,
            discount: 0,
            shipping: $shipping,
            gross: $originalPricing === 'exclusive' ? null : self::toFils( $refund->total ),
            pricing: $originalPricing === 'exclusive' ? 'exclusive' : 'inclusive'
        );
    }

    /**
     * Resolve the VAT rate and the UAE tax category of a line.
     *
     * @return array{rate: float, category: string}
     */
    public function resolveTax( int $taxGroupId, ?Order $order ): array
    {
        if ( $taxGroupId > 0 ) {
            if ( ! isset( $this->taxGroupCache[ $taxGroupId ] ) ) {
                $group = TaxGroup::with( 'taxes' )->find( $taxGroupId );

                if ( $group instanceof TaxGroup ) {
                    $rate = (float) $group->taxes->sum( 'rate' );
                    $labels = collect( [ $group->name ] )->merge( $group->taxes->pluck( 'name' ) )->join( ' ' );
                    $this->taxGroupCache[ $taxGroupId ] = [
                        'rate' => $rate,
                        'category' => $this->categoryFor( $rate, $labels ),
                    ];
                }
            }

            if ( isset( $this->taxGroupCache[ $taxGroupId ] ) ) {
                return $this->taxGroupCache[ $taxGroupId ];
            }
        }

        if ( $order instanceof Order && $order->taxes->count() > 0 ) {
            $rate = (float) $order->taxes->sum( 'rate' );

            return [
                'rate' => $rate,
                'category' => $this->categoryFor( $rate, $order->taxes->pluck( 'tax_name' )->join( ' ' ) ),
            ];
        }

        $rate = $this->settings->defaultVatRate();

        return [
            'rate' => $rate,
            'category' => $this->categoryFor( $rate, '' ),
        ];
    }

    private function categoryFor( float $rate, string $labels ): string
    {
        if ( $rate <= 0 && Str::contains( Str::lower( $labels ), [ 'exempt', 'معفى', 'معفاة' ] ) ) {
            return self::CATEGORY_EXEMPT;
        }

        return $rate > 0 ? self::CATEGORY_STANDARD : self::CATEGORY_ZERO;
    }

    /**
     * @param  array<int, array{name: string, quantity: float, unit_name: string, amount: int, rate: float, category: string}> $lines
     * @param  int|null                                                                                                        $gross   amount paid in fils, null to derive it
     * @param  string|null                                                                                                     $pricing "inclusive", "exclusive" or null to detect it
     * @return array<string, mixed>
     */
    public function compute( array $lines, int $discount, int $shipping, ?int $gross, ?string $pricing ): array
    {
        $linesTotal = array_sum( array_column( $lines, 'amount' ) );

        if ( $pricing === null ) {
            $extra = (int) $gross - ( $linesTotal - $discount + $shipping );
            $pricing = $extra > 0 ? 'exclusive' : 'inclusive';
        }

        $defaultRate = $this->settings->defaultVatRate();
        $defaultKey = $this->categoryKey( $this->categoryFor( $defaultRate, '' ), $defaultRate );

        /**
         * Group the lines per tax category (category + rate).
         */
        $groups = [];

        foreach ( $lines as $line ) {
            $key = $this->categoryKey( $line[ 'category' ], $line[ 'rate' ] );
            $groups[ $key ] ??= [ 'category' => $line[ 'category' ], 'rate' => $line[ 'rate' ], 'base' => 0, 'discount' => 0, 'shipping' => 0 ];
            $groups[ $key ][ 'base' ] += $line[ 'amount' ];
        }

        if ( $shipping !== 0 ) {
            $groups[ $defaultKey ] ??= [ 'category' => $this->categoryFor( $defaultRate, '' ), 'rate' => $defaultRate, 'base' => 0, 'discount' => 0, 'shipping' => 0 ];
            $groups[ $defaultKey ][ 'shipping' ] = $shipping;
        }

        /**
         * Spread the order discount across the categories proportionally.
         */
        $shares = $this->allocate( $discount, array_map( fn( $group ) => $group[ 'base' ], $groups ) );

        foreach ( $shares as $key => $share ) {
            $groups[ $key ][ 'discount' ] = $share;
        }

        $outputLines = [];
        $lineNets = [];

        foreach ( array_values( $lines ) as $index => $line ) {
            $key = $this->categoryKey( $line[ 'category' ], $line[ 'rate' ] );

            if ( $pricing === 'inclusive' ) {
                $vat = self::extractVat( $line[ 'amount' ], $line[ 'rate' ] );
                $net = $line[ 'amount' ] - $vat;
                $lineGross = $line[ 'amount' ];
            } else {
                $net = $line[ 'amount' ];
                $vat = self::applyVat( $net, $line[ 'rate' ] );
                $lineGross = $net + $vat;
            }

            $lineNets[ $key ] = ( $lineNets[ $key ] ?? 0 ) + $net;
            $quantity = $line[ 'quantity' ] > 0 ? $line[ 'quantity' ] : 1;

            $outputLines[] = [
                'id' => (string) ( $index + 1 ),
                'name' => $line[ 'name' ],
                'quantity' => $line[ 'quantity' ],
                'unit_name' => $line[ 'unit_name' ],
                'unit_code' => 'C62',
                'net_amount' => self::fromFils( $net ),
                'vat_amount' => self::fromFils( $vat ),
                'gross_amount' => self::fromFils( $lineGross ),
                'unit_price_net' => round( $net / 100 / $quantity, 6 ),
                'unit_price_gross' => round( $lineGross / 100 / $quantity, 6 ),
                'rate' => $line[ 'rate' ],
                'category' => $line[ 'category' ],
            ];
        }

        $allowances = [];
        $charges = [];
        $subtotals = [];

        foreach ( $groups as $key => $group ) {
            $linesNet = $lineNets[ $key ] ?? 0;

            if ( $pricing === 'inclusive' ) {
                $categoryGross = $group[ 'base' ] - $group[ 'discount' ] + $group[ 'shipping' ];
                $vat = self::extractVat( $categoryGross, $group[ 'rate' ] );
                $taxable = $categoryGross - $vat;
                $shippingNet = $group[ 'shipping' ] - self::extractVat( $group[ 'shipping' ], $group[ 'rate' ] );
                $adjustment = $linesNet + $shippingNet - $taxable;
            } else {
                $shippingNet = $group[ 'shipping' ];
                $taxable = $linesNet - $group[ 'discount' ] + $shippingNet;
                $vat = self::applyVat( $taxable, $group[ 'rate' ] );
                $adjustment = $group[ 'discount' ];
            }

            if ( $shippingNet !== 0 ) {
                $charges[] = $this->allowanceCharge( $shippingNet, $group, __m( 'Delivery charge', 'MplEInvoice' ), 'DL' );
            }

            if ( $adjustment > 0 ) {
                $allowances[] = $this->allowanceCharge(
                    $adjustment,
                    $group,
                    $group[ 'discount' ] > 0 ? __m( 'Discount', 'MplEInvoice' ) : __m( 'Rounding adjustment', 'MplEInvoice' ),
                    $group[ 'discount' ] > 0 ? '95' : '104'
                );
            } elseif ( $adjustment < 0 ) {
                $charges[] = $this->allowanceCharge( -$adjustment, $group, __m( 'Rounding adjustment', 'MplEInvoice' ), 'ZZZ' );
            }

            $subtotals[] = [
                'category' => $group[ 'category' ],
                'rate' => $group[ 'rate' ],
                'taxable' => self::fromFils( $taxable ),
                'tax' => self::fromFils( $vat ),
                'exemption_reason' => $group[ 'category' ] === self::CATEGORY_EXEMPT ? __m( 'Exempt supply', 'MplEInvoice' ) : null,
                '_taxable' => $taxable,
                '_tax' => $vat,
            ];
        }

        $lineExtension = array_sum( $lineNets );
        $allowanceTotal = array_sum( array_column( $allowances, '_amount' ) );
        $chargeTotal = array_sum( array_column( $charges, '_amount' ) );
        $taxExclusive = array_sum( array_column( $subtotals, '_taxable' ) );
        $taxTotal = array_sum( array_column( $subtotals, '_tax' ) );
        $taxInclusive = $taxExclusive + $taxTotal;
        $payable = $gross ?? $taxInclusive;

        $strip = fn( array $rows ) => array_map( function ( $row ) {
            return array_filter( $row, fn( $key ) => ! str_starts_with( $key, '_' ), ARRAY_FILTER_USE_KEY );
        }, $rows );

        return [
            'pricing' => $pricing,
            'currency' => 'AED',
            'lines' => $outputLines,
            'allowances' => $strip( $allowances ),
            'charges' => $strip( $charges ),
            'tax_subtotals' => $strip( $subtotals ),
            'totals' => [
                'line_extension' => self::fromFils( $lineExtension ),
                'allowance_total' => self::fromFils( $allowanceTotal ),
                'charge_total' => self::fromFils( $chargeTotal ),
                'tax_exclusive' => self::fromFils( $taxExclusive ),
                'tax' => self::fromFils( $taxTotal ),
                'tax_inclusive' => self::fromFils( $taxInclusive ),
                'rounding' => self::fromFils( $payable - $taxInclusive ),
                'payable' => self::fromFils( $payable ),
            ],
        ];
    }

    /**
     * @param  array{category: string, rate: float} $group
     * @return array<string, mixed>
     */
    private function allowanceCharge( int $amount, array $group, string $reason, string $code ): array
    {
        return [
            'amount' => self::fromFils( $amount ),
            'category' => $group[ 'category' ],
            'rate' => $group[ 'rate' ],
            'reason' => $reason,
            'reason_code' => $code,
            '_amount' => $amount,
        ];
    }

    private function categoryKey( string $category, float $rate ): string
    {
        return $category . '|' . number_format( $rate, 2, '.', '' );
    }

    /**
     * Split an amount proportionally to the weights using the largest remainder method.
     *
     * @param  array<string, int> $weights
     * @return array<string, int>
     */
    private function allocate( int $amount, array $weights ): array
    {
        $total = array_sum( $weights );

        if ( $amount === 0 || $total <= 0 ) {
            return array_map( fn() => 0, $weights );
        }

        $shares = [];
        $remainders = [];

        foreach ( $weights as $key => $weight ) {
            $exact = $amount * $weight / $total;
            $shares[ $key ] = (int) floor( $exact );
            $remainders[ $key ] = $exact - $shares[ $key ];
        }

        $left = $amount - array_sum( $shares );
        arsort( $remainders );

        foreach ( array_keys( $remainders ) as $key ) {
            if ( $left <= 0 ) {
                break;
            }

            $shares[ $key ]++;
            $left--;
        }

        return $shares;
    }

    public static function extractVat( int $grossFils, float $rate ): int
    {
        if ( $rate <= 0 ) {
            return 0;
        }

        return (int) round( $grossFils * $rate / ( 100 + $rate ) );
    }

    public static function applyVat( int $netFils, float $rate ): int
    {
        if ( $rate <= 0 ) {
            return 0;
        }

        return (int) round( $netFils * $rate / 100 );
    }

    public static function toFils( mixed $amount ): int
    {
        return (int) round( (float) $amount * 100 );
    }

    public static function fromFils( int $fils ): float
    {
        return round( $fils / 100, 2 );
    }
}
