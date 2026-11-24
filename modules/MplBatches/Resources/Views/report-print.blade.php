<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ ns()->option->get( 'ns_store_name' ) }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111; background: #fff; margin: 24px; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h4 { margin: 0; font-size: 14px; }
        .meta { color: #555; margin-bottom: 16px; }
        .mpl-batch-section { margin-bottom: 20px; page-break-inside: avoid; }
        .ns-box-header { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding: 4px 0; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        .mplb\:text-right { text-align: right; }
        .mplb\:text-center { text-align: center; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <h1>{{ $title }}</h1>
    <div class="meta">{{ ns()->option->get( 'ns_store_name' ) }} &middot; {{ $description }}</div>
    @include( 'MplBatches::partials.report-sections' )
</body>
</html>
