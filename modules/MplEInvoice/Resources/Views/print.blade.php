@php
    use Modules\MplEInvoice\Models\EInvoiceDocument;

    $seller = $summary['seller'] ?? [];
    $buyer = $summary['buyer'] ?? [];
    $totals = $summary['totals'] ?? [];
    $lines = $summary['lines'] ?? [];
    $subtotals = $summary['tax_subtotals'] ?? [];
    $isCreditNote = $document->type === EInvoiceDocument::TYPE_CREDIT_NOTE;
    $isSimplified = $document->type === EInvoiceDocument::TYPE_SIMPLIFIED;

    if ( $isCreditNote ) {
        $titleEn = __m( 'Tax Credit Note', 'MplEInvoice' );
        $titleAr = 'إشعار دائن ضريبي';
    } elseif ( $isSimplified ) {
        $titleEn = __m( 'Simplified Tax Invoice', 'MplEInvoice' );
        $titleAr = 'فاتورة ضريبية مبسطة';
    } else {
        $titleEn = __m( 'Tax Invoice', 'MplEInvoice' );
        $titleAr = 'فاتورة ضريبية';
    }

    $money = fn( $value ) => number_format( (float) $value, 2, '.', ',' );
    $quantity = fn( $value ) => rtrim( rtrim( number_format( (float) $value, 3, '.', '' ), '0' ), '.' );
    $currency = $document->currency ?: 'AED';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titleEn }} {{ $document->number }}</title>
    <style>
        :root { --ink: #111827; --muted: #4b5563; --line: #d1d5db; --soft: #f3f4f6; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: var(--ink); font-family: "Segoe UI", Tahoma, Arial, "Noto Naskh Arabic", sans-serif; font-size: 13px; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm; }
        .ar { direction: rtl; text-align: right; font-family: Tahoma, "Noto Naskh Arabic", "Arabic Typesetting", sans-serif; }
        .row { display: flex; justify-content: space-between; gap: 16px; }
        .title { text-align: center; border-bottom: 2px solid var(--ink); padding-bottom: 8px; margin-bottom: 12px; }
        .title h1 { margin: 0; font-size: 22px; }
        .title .ar { text-align: center; font-size: 20px; }
        .box { border: 1px solid var(--line); padding: 8px 10px; border-radius: 4px; flex: 1; }
        .box h3 { margin: 0 0 6px; font-size: 12px; text-transform: uppercase; color: var(--muted); display: flex; justify-content: space-between; }
        .label { color: var(--muted); }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid var(--line); padding: 5px 6px; vertical-align: top; }
        th { background: var(--soft); font-size: 11px; font-weight: 600; }
        th span.ar { display: block; font-weight: normal; text-align: center; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: 60%; }
        .totals td:first-child { width: 65%; }
        .grand td { font-weight: 700; font-size: 15px; background: var(--soft); }
        .meta div { margin-bottom: 2px; }
        .footer { margin-top: 18px; text-align: center; color: var(--muted); font-size: 11px; }
        .actions { text-align: center; margin: 12px; }
        .actions button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; width: auto; min-height: auto; padding: 8mm; }
            .actions { display: none; }
        }
        /* 80mm thermal receipt */
        @media print and (max-width: 90mm) {
            .sheet { padding: 2mm; font-size: 11px; }
        }
        @media (max-width: 90mm), print and (max-width: 90mm) {
            .row { display: block; }
            .box { margin-bottom: 6px; }
            .totals { width: 100%; }
            th, td { padding: 3px; font-size: 10px; }
            .hide-narrow { display: none; }
        }
        @media screen and (max-width: 800px) {
            .sheet { width: auto; min-height: auto; margin: 0; padding: 12px; }
        }
    </style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">{{ __m( 'Print', 'MplEInvoice' ) }} / طباعة</button></div>
<div class="sheet">
    <div class="title">
        <h1>{{ $titleEn }}</h1>
        <div class="ar" dir="rtl" lang="ar">{{ $titleAr }}</div>
    </div>

    <div class="row">
        <div class="box">
            <h3><span>{{ __m( 'Seller', 'MplEInvoice' ) }}</span><span class="ar" dir="rtl" lang="ar">البائع</span></h3>
            <div><strong>{{ $seller['name'] ?? '' }}</strong></div>
            @if ( ! empty( $seller['name_ar'] ) )
                <div class="ar" dir="rtl" lang="ar"><strong>{{ $seller['name_ar'] }}</strong></div>
            @endif
            <div>{{ $seller['address'] ?? '' }}</div>
            <div>{{ collect( [ $seller['city'] ?? null, $seller['emirate'] ?? null, __m( 'United Arab Emirates', 'MplEInvoice' ) ] )->filter()->join( ', ' ) }}</div>
            <div><span class="label">{{ __m( 'TRN', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">الرقم الضريبي</span>:</span> <strong>{{ $seller['trn'] ?? '' }}</strong></div>
        </div>
        <div class="box meta">
            <h3><span>{{ __m( 'Invoice Details', 'MplEInvoice' ) }}</span><span class="ar" dir="rtl" lang="ar">تفاصيل الفاتورة</span></h3>
            <div><span class="label">{{ __m( 'Number', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">رقم الفاتورة</span>:</span> <strong>{{ $document->number }}</strong></div>
            <div><span class="label">{{ __m( 'Date', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">التاريخ</span>:</span> {{ \Carbon\Carbon::parse( $document->issued_at )->format( 'Y-m-d H:i' ) }}</div>
            <div><span class="label">{{ __m( 'Order', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">الطلب</span>:</span> {{ $summary['order_code'] ?? '' }}</div>
            @if ( $isCreditNote && ! empty( $summary['billing_reference'] ) )
                <div><span class="label">{{ __m( 'Original Invoice', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">الفاتورة الأصلية</span>:</span> {{ $summary['billing_reference']['number'] }}</div>
            @endif
            <div><span class="label">{{ __m( 'Currency', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">العملة</span>:</span> {{ $currency }}</div>
        </div>
    </div>

    <div class="row" style="margin-top: 10px;">
        <div class="box">
            <h3><span>{{ __m( 'Buyer', 'MplEInvoice' ) }}</span><span class="ar" dir="rtl" lang="ar">المشتري</span></h3>
            <div><strong>{{ ( $buyer['legal_name'] ?? null ) ?: ( $buyer['name'] ?? '' ) }}</strong></div>
            @if ( ! empty( $buyer['address'] ) || ! empty( $buyer['city'] ) )
                <div>{{ collect( [ $buyer['address'] ?? null, $buyer['city'] ?? null, $buyer['emirate'] ?? null ] )->filter()->join( ', ' ) }}</div>
            @endif
            @if ( ! empty( $buyer['trn'] ) )
                <div><span class="label">{{ __m( 'Buyer TRN', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">الرقم الضريبي للمشتري</span>:</span> <strong>{{ $buyer['trn'] }}</strong></div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __m( 'Description', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">الوصف</span></th>
                <th class="num">{{ __m( 'Qty', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">الكمية</span></th>
                <th class="num">{{ __m( 'Unit Price', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">سعر الوحدة</span></th>
                <th class="num hide-narrow">{{ __m( 'Taxable Amount', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">المبلغ الخاضع للضريبة</span></th>
                <th class="num">{{ __m( 'VAT %', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">نسبة الضريبة</span></th>
                <th class="num">{{ __m( 'VAT', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">مبلغ الضريبة</span></th>
                <th class="num">{{ __m( 'Total', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">الإجمالي</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ( $lines as $line )
                <tr>
                    <td>{{ $line['id'] }}</td>
                    <td>{{ $line['name'] }}</td>
                    <td class="num">{{ $quantity( $line['quantity'] ) }}</td>
                    <td class="num">{{ $money( $line['unit_price_net'] ) }}</td>
                    <td class="num hide-narrow">{{ $money( $line['net_amount'] ) }}</td>
                    <td class="num">{{ rtrim( rtrim( number_format( (float) $line['rate'], 2 ), '0' ), '.' ) }}%</td>
                    <td class="num">{{ $money( $line['vat_amount'] ) }}</td>
                    <td class="num">{{ $money( $line['gross_amount'] ) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr><td>{{ __m( 'Subtotal (excl. VAT)', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">المجموع الفرعي غير شامل الضريبة</span></td><td class="num">{{ $money( $totals['line_extension'] ?? 0 ) }} {{ $currency }}</td></tr>
            @if ( (float) ( $totals['allowance_total'] ?? 0 ) !== 0.0 )
                <tr><td>{{ __m( 'Discount', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">الخصم</span></td><td class="num">-{{ $money( $totals['allowance_total'] ) }} {{ $currency }}</td></tr>
            @endif
            @if ( (float) ( $totals['charge_total'] ?? 0 ) !== 0.0 )
                <tr><td>{{ __m( 'Charges', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">رسوم إضافية</span></td><td class="num">{{ $money( $totals['charge_total'] ) }} {{ $currency }}</td></tr>
            @endif
            <tr><td>{{ __m( 'Taxable Amount', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">المبلغ الخاضع للضريبة</span></td><td class="num">{{ $money( $totals['tax_exclusive'] ?? 0 ) }} {{ $currency }}</td></tr>
            <tr><td>{{ __m( 'Total VAT', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">إجمالي ضريبة القيمة المضافة</span></td><td class="num">{{ $money( $totals['tax'] ?? 0 ) }} {{ $currency }}</td></tr>
            @if ( (float) ( $totals['rounding'] ?? 0 ) !== 0.0 )
                <tr><td>{{ __m( 'Rounding', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">التقريب</span></td><td class="num">{{ $money( $totals['rounding'] ) }} {{ $currency }}</td></tr>
            @endif
            <tr class="grand"><td>{{ $isCreditNote ? __m( 'Total Credited', 'MplEInvoice' ) : __m( 'Total Amount Due', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">{{ $isCreditNote ? 'إجمالي المبلغ الدائن' : 'المبلغ الإجمالي المستحق' }}</span></td><td class="num">{{ $money( $totals['payable'] ?? 0 ) }} {{ $currency }}</td></tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>{{ __m( 'VAT Summary', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">ملخص الضريبة</span></th>
                <th class="num">{{ __m( 'Rate', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">النسبة</span></th>
                <th class="num">{{ __m( 'Taxable Amount', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">المبلغ الخاضع للضريبة</span></th>
                <th class="num">{{ __m( 'VAT', 'MplEInvoice' ) }}<span class="ar" dir="rtl" lang="ar">الضريبة</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ( $subtotals as $subtotal )
                <tr>
                    <td>
                        @switch( $subtotal['category'] )
                            @case( 'S' ) {{ __m( 'Standard rated', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">خاضع للنسبة الأساسية</span> @break
                            @case( 'Z' ) {{ __m( 'Zero rated', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">خاضع لنسبة الصفر</span> @break
                            @case( 'E' ) {{ __m( 'Exempt', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">معفى</span> @break
                            @default {{ $subtotal['category'] }}
                        @endswitch
                    </td>
                    <td class="num">{{ rtrim( rtrim( number_format( (float) $subtotal['rate'], 2 ), '0' ), '.' ) }}%</td>
                    <td class="num">{{ $money( $subtotal['taxable'] ) }} {{ $currency }}</td>
                    <td class="num">{{ $money( $subtotal['tax'] ) }} {{ $currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div>{{ __m( 'UUID', 'MplEInvoice' ) }}: {{ $document->uuid }}</div>
        <div>{{ __m( 'Amounts in UAE Dirham (AED). VAT registered supplier.', 'MplEInvoice' ) }} / <span dir="rtl" lang="ar">المبالغ بالدرهم الإماراتي</span></div>
    </div>
</div>
</body>
</html>
