<?php
/**
 * Standalone customer-facing layout: no sidebar, no dashboard header.
 * Uses the core theme stylesheet selected in the module settings
 * (light/dark) and the prefixed module stylesheet.
 *
 * @var string $theme
 * @var array $displayConfig
 */
?>
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="{{ $theme }}" data-theme="{{ $theme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    @include( 'layout._header-injection' )
    @vite([
        'resources/scss/line-awesome/1.3.0/scss/line-awesome.scss',
        'resources/css/fonts.css',
        'resources/css/animations.css',
        'resources/css/' . $theme . '.css',
    ])
    @moduleViteAssets('Resources/css/style.css', 'MplCustomerDisplay')
    @include( 'layout._header-script' )
    @vite([ 'resources/ts/vue-runtime.ts' ])
    @vite([ 'resources/ts/lang-loader.ts' ])
    <script>
    window.MplCustomerDisplay = @json( $displayConfig );
    </script>
</head>
<body class="mplcd-body">
    <div id="mplcd-display-root" class="mplcd:h-dvh mplcd:w-full">
        <div class="mplcd:flex mplcd:h-full mplcd:w-full mplcd:flex-col mplcd:items-center mplcd:justify-center mplcd:gap-4 mplcd:text-fontcolor">
            <p class="mplcd:text-3xl mplcd:font-semibold">{{ $displayConfig[ 'store' ][ 'name' ] }}</p>
            <p class="mplcd:text-xl mplcd:text-fontcolor-soft">{{ __m( 'Loading...', 'MplCustomerDisplay' ) }}</p>
        </div>
    </div>
    @include( 'common.footer' )
    @moduleViteAssets('Resources/ts/display.ts', 'MplCustomerDisplay')
</body>
</html>
