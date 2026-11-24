<?php
/**
 * Standalone mobile-first layout for the owner dashboard. It keeps the core
 * theme stylesheet, runtime and bootstrap (nsHttpClient, nsSnackBar, nsCurrency)
 * but skips the heavy dashboard chrome (aside menu, header, widgets).
 */
?>
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ $theme }}" class="{{ $theme }}" @if ( $isRtl ) dir="rtl" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $title }} &mdash; {{ $ownerConfig['storeName'] }}</title>
    @include( 'layout._header-injection' )
    <link rel="manifest" href="{{ route( 'mplownerapp.manifest' ) }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MyPosLab">
    @vite([
        'resources/scss/line-awesome/1.3.0/scss/line-awesome.scss',
        'resources/css/fonts.css',
        'resources/css/' . $theme . '.css'
    ])
    @moduleViteAssets('Resources/css/style.css', 'MplOwnerApp')
    @include( 'layout._header-script' )
    @vite([ 'resources/ts/vue-runtime.ts' ])
    @vite([ 'resources/ts/lang-loader.ts' ])
    <script>
        window.MplOwnerAppConfig = @json( $ownerConfig );
    </script>
</head>
<body class="mplowner-body">
    <div id="mplownerapp-root"></div>
    <noscript>{{ __m( 'Please enable JavaScript to use the owner dashboard.', 'MplOwnerApp' ) }}</noscript>
    @include( 'common.footer' )
    @moduleViteAssets('Resources/ts/owner.ts', 'MplOwnerApp')
</body>
</html>
