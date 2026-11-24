<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __m( 'Receipt link unavailable', 'MplWhatsApp' ) }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f1f5f9; color: #1e293b; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", Tahoma, sans-serif; padding: 16px; }
        .mwa-card { max-width: 420px; background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); padding: 24px; text-align: center; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { margin: 0; color: #475569; }
    </style>
</head>
<body>
    <div class="mwa-card">
        <h1>{{ __m( 'This receipt link is invalid or has expired', 'MplWhatsApp' ) }}</h1>
        <p>{{ __m( 'Please ask the store to send you a new receipt.', 'MplWhatsApp' ) }}</p>
        <p dir="rtl" lang="ar" style="margin-top: 12px;">رابط الإيصال غير صالح أو منتهي الصلاحية. يرجى طلب إيصال جديد من المتجر.</p>
    </div>
</body>
</html>
