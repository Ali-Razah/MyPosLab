<?php
use App\Models\Order;

/**
 * @var Order $order
 */
$money = fn( $value ) => (string) ns()->currency->define( $value );
$showSubtotal = ns()->option->get( 'ns_invoice_show_subtotal', 'yes' ) === 'yes';
$showPayments = ns()->option->get( 'ns_invoice_show_payment_rows', 'yes' ) === 'yes';
$showChange = ns()->option->get( 'ns_invoice_show_change_due', 'yes' ) !== 'no';
$showUnit = ns()->option->get( 'ns_invoice_show_product_unit', 'yes' ) !== 'no';
?>
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ sprintf( __m( 'Receipt %s — %s', 'MplWhatsApp' ), $order->code, $storeName ) }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #1e293b; font: 15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", Tahoma, sans-serif; }
        .mwa-receipt { max-width: 480px; margin: 0 auto; padding: 16px; }
        .mwa-card { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); padding: 20px; }
        .mwa-brand { text-align: center; margin-bottom: 12px; }
        .mwa-brand img { max-width: 180px; max-height: 90px; }
        .mwa-brand h1 { font-size: 22px; margin: 0; }
        .mwa-meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 4px 16px; font-size: 13px; color: #475569; border-bottom: 1px dashed #cbd5e1; padding-bottom: 12px; margin-bottom: 8px; }
        .mwa-meta strong { color: #1e293b; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 7px 0; vertical-align: top; }
        td.mwa-amount { text-align: end; white-space: nowrap; padding-inline-start: 12px; }
        .mwa-lines td { border-bottom: 1px solid #f1f5f9; }
        .mwa-sub { display: block; font-size: 12px; color: #64748b; }
        .mwa-totals { margin-top: 8px; border-top: 1px dashed #cbd5e1; }
        .mwa-totals td { font-size: 14px; }
        .mwa-grand td { font-size: 18px; font-weight: 700; border-top: 1px solid #e2e8f0; }
        .mwa-note, .mwa-footer { text-align: center; font-size: 13px; color: #475569; margin-top: 16px; white-space: pre-line; }
        .mwa-print { display: block; width: 100%; margin-top: 16px; padding: 12px; border: 0; border-radius: 10px; background: #1e293b; color: #fff; font-size: 15px; cursor: pointer; }
        @media print { body { background: #fff; } .mwa-card { box-shadow: none; } .mwa-print { display: none; } }
    </style>
</head>
<body>
    <main class="mwa-receipt">
        <div class="mwa-card">
            <div class="mwa-brand">
                @if ( ! empty( $logo ) )
                    <img src="{{ $logo }}" alt="{{ $storeName }}">
                @else
                    <h1>{{ $storeName }}</h1>
                @endif
            </div>

            <div class="mwa-meta">
                <span>{{ __m( 'Order', 'MplWhatsApp' ) }}: <strong>{{ $order->code }}</strong></span>
                <span>{{ ns()->date->getFormatted( (string) $order->created_at ) }}</span>
                @if ( $order->customer )
                    <span>{{ __m( 'Customer', 'MplWhatsApp' ) }}: <strong>{{ trim( $order->customer->first_name . ' ' . $order->customer->last_name ) ?: $order->customer->username }}</strong></span>
                @endif
            </div>

            <table class="mwa-lines">
                <tbody>
                    @foreach ( $products as $product )
                        <tr>
                            <td>
                                {{ $product[ 'name' ] ?? $product->name }} × {{ (float) ( $product[ 'quantity' ] ?? $product->quantity ) }}
                                @if ( $showUnit && ! empty( data_get( $product, 'unit.name' ) ) )
                                    <span class="mwa-sub">{{ data_get( $product, 'unit.name' ) }}</span>
                                @endif
                            </td>
                            <td class="mwa-amount">{{ $money( $product[ 'total_price' ] ?? $product->total_price ) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="mwa-totals">
                <tbody>
                    @if ( $showSubtotal )
                        <tr><td>{{ __m( 'Sub Total', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( $order->subtotal ) }}</td></tr>
                    @endif
                    @if ( $order->discount > 0 )
                        <tr>
                            <td>{{ __m( 'Discount', 'MplWhatsApp' ) }}@if ( $order->discount_type === 'percentage' ) ({{ (float) $order->discount_percentage }}%)@endif</td>
                            <td class="mwa-amount">-{{ $money( $order->discount ) }}</td>
                        </tr>
                    @endif
                    @if ( $order->total_coupons > 0 )
                        <tr><td>{{ __m( 'Coupons', 'MplWhatsApp' ) }}</td><td class="mwa-amount">-{{ $money( $order->total_coupons ) }}</td></tr>
                    @endif
                    @foreach ( $order->taxes as $tax )
                        <tr>
                            <td>{{ $tax->tax_name }} ({{ $order->tax_type === 'inclusive' ? __m( 'Inclusive', 'MplWhatsApp' ) : __m( 'Exclusive', 'MplWhatsApp' ) }})</td>
                            <td class="mwa-amount">{{ $money( $tax->tax_value ) }}</td>
                        </tr>
                    @endforeach
                    @if ( $order->products_tax_value > 0 )
                        <tr><td>{{ __m( 'Product Taxes', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( $order->products_tax_value ) }}</td></tr>
                    @endif
                    @if ( $order->shipping > 0 )
                        <tr><td>{{ __m( 'Shipping', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( $order->shipping ) }}</td></tr>
                    @endif
                    <tr class="mwa-grand"><td>{{ __m( 'Total', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( $order->total ) }}</td></tr>
                    @if ( $showPayments )
                        @foreach ( $order->payments as $payment )
                            <tr>
                                <td>{{ $paymentTypes[ $payment->identifier ] ?? __m( 'Payment', 'MplWhatsApp' ) }}</td>
                                <td class="mwa-amount">{{ $money( $payment->value ) }}</td>
                            </tr>
                        @endforeach
                    @endif
                    <tr><td>{{ __m( 'Paid', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( $order->tendered ) }}</td></tr>
                    @foreach ( $order->refunds as $refund )
                        <tr><td>{{ __m( 'Refunded', 'MplWhatsApp' ) }}</td><td class="mwa-amount">-{{ $money( $refund->total ) }}</td></tr>
                    @endforeach
                    @if ( $showChange )
                        @if ( $order->payment_status === Order::PAYMENT_PAID )
                            <tr><td>{{ __m( 'Change', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( $order->change ) }}</td></tr>
                        @elseif ( $order->payment_status === Order::PAYMENT_PARTIALLY )
                            <tr><td>{{ __m( 'Due', 'MplWhatsApp' ) }}</td><td class="mwa-amount">{{ $money( abs( $order->change ) ) }}</td></tr>
                        @endif
                    @endif
                </tbody>
            </table>

            @if ( $order->note_visibility === 'visible' && ! empty( $order->note ) )
                <div class="mwa-note"><strong>{{ __m( 'Note:', 'MplWhatsApp' ) }}</strong> {{ $order->note }}</div>
            @endif

            @if ( ! empty( $footer ) )
                <div class="mwa-footer">{{ $footer }}</div>
            @endif

            <button type="button" class="mwa-print" onclick="window.print()">{{ __m( 'Print or save as PDF', 'MplWhatsApp' ) }}</button>
        </div>
    </main>
</body>
</html>
