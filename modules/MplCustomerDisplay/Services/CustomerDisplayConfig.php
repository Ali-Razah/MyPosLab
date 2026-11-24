<?php

namespace Modules\MplCustomerDisplay\Services;

/**
 * Reads the Customer Display options and exposes the sanitized,
 * frontend-safe configuration consumed by the POS publisher and the display page.
 */
class CustomerDisplayConfig
{
    /**
     * Shared channel name used by BroadcastChannel and the localStorage fallback.
     */
    const CHANNEL = 'myposlab-customer-display';

    /**
     * Named window target so repeated clicks reuse the same display window.
     */
    const WINDOW_NAME = 'myposlab-customer-display';

    const OPTION_ENABLED = 'mplcd_enabled';

    const OPTION_THEME = 'mplcd_theme';

    const OPTION_SHOW_TAX = 'mplcd_show_tax';

    const OPTION_THANK_YOU_DURATION = 'mplcd_thankyou_duration';

    const OPTION_WELCOME_EN = 'mplcd_welcome_en';

    const OPTION_WELCOME_AR = 'mplcd_welcome_ar';

    const OPTION_PROMO_IMAGES = 'mplcd_promo_images';

    const OPTION_SLIDE_INTERVAL = 'mplcd_slide_interval';

    const DEFAULT_THANK_YOU_DURATION = 6;

    const DEFAULT_SLIDE_INTERVAL = 8;

    public function isEnabled(): bool
    {
        return ns()->option->get( self::OPTION_ENABLED, 'yes' ) === 'yes';
    }

    public function theme(): string
    {
        $theme = ns()->option->get( self::OPTION_THEME, 'dark' );

        return in_array( $theme, [ 'light', 'dark' ], true ) ? $theme : 'dark';
    }

    public function showTax(): bool
    {
        return ns()->option->get( self::OPTION_SHOW_TAX, 'yes' ) === 'yes';
    }

    public function thankYouDuration(): int
    {
        return $this->boundedInteger( ns()->option->get( self::OPTION_THANK_YOU_DURATION ), self::DEFAULT_THANK_YOU_DURATION, 2, 60 );
    }

    public function slideInterval(): int
    {
        return $this->boundedInteger( ns()->option->get( self::OPTION_SLIDE_INTERVAL ), self::DEFAULT_SLIDE_INTERVAL, 3, 120 );
    }

    public function welcomeEnglish(): string
    {
        return (string) ( ns()->option->get( self::OPTION_WELCOME_EN ) ?: 'Welcome! Your items will appear here.' );
    }

    public function welcomeArabic(): string
    {
        return (string) ( ns()->option->get( self::OPTION_WELCOME_AR ) ?: 'أهلاً وسهلاً! ستظهر مشترياتك هنا.' );
    }

    /**
     * Promo images, one per line. Only absolute http(s) URLs and
     * root-relative paths are kept, so no script URL can reach an <img src>.
     *
     * @return string[]
     */
    public function promoImages(): array
    {
        return self::parseImageUrls( (string) ns()->option->get( self::OPTION_PROMO_IMAGES, '' ) );
    }

    /**
     * @return string[]
     */
    public static function parseImageUrls( string $raw ): array
    {
        return collect( preg_split( '/\r\n|\r|\n/', $raw ) ?: [] )
            ->map( fn( $line ) => trim( $line ) )
            ->filter( fn( $line ) => $line !== '' && self::isAllowedImageUrl( $line ) )
            ->unique()
            ->values()
            ->take( 20 )
            ->all();
    }

    public static function isAllowedImageUrl( string $url ): bool
    {
        if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
            return true;
        }

        if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
            return false;
        }

        return in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true );
    }

    /**
     * Store identity shown in the display header.
     *
     * @return array{name: string, logo: string|null}
     */
    public function store(): array
    {
        $logo = ns()->option->get( 'ns_store_rectangle_logo' ) ?: ns()->option->get( 'ns_store_square_logo' );

        return [
            'name' => (string) ns()->option->get( 'ns_store_name', 'MyPosLab' ),
            'logo' => is_string( $logo ) && $logo !== '' ? $logo : null,
        ];
    }

    /**
     * Channel name scoped to the active (Multistore) store: stores share the
     * browser origin, so a global name would mix the carts of two stores.
     */
    public function channel(): string
    {
        $storeId = ns()->storeID;

        return $storeId === null || $storeId === '' ? self::CHANNEL : self::CHANNEL . '-store-' . $storeId;
    }

    /**
     * Configuration injected on the POS page.
     *
     * @return array<string, mixed>
     */
    public function forPos(): array
    {
        return [
            'channel' => $this->channel(),
            'windowName' => self::WINDOW_NAME,
            'displayUrl' => ns()->route( 'mplcd.display' ),
            'canOpen' => ns()->allowedTo( 'mplcustomerdisplay.view' ),
            'defaultCustomerId' => ns()->option->get( 'ns_customers_default', false ),
            'throttleMs' => 150,
        ];
    }

    /**
     * Configuration injected on the display page.
     *
     * @return array<string, mixed>
     */
    public function forDisplay(): array
    {
        return [
            'channel' => $this->channel(),
            'enabled' => $this->isEnabled(),
            'theme' => $this->theme(),
            'showTax' => $this->showTax(),
            'thankYouDuration' => $this->thankYouDuration(),
            'slideInterval' => $this->slideInterval(),
            'welcome' => [
                'en' => $this->welcomeEnglish(),
                'ar' => $this->welcomeArabic(),
            ],
            'promoImages' => $this->promoImages(),
            'store' => $this->store(),
            'timeZone' => ns()->option->get( 'ns_datetime_timezone', 'UTC' ),
        ];
    }

    private function boundedInteger( mixed $value, int $default, int $min, int $max ): int
    {
        if ( ! is_numeric( $value ) ) {
            return $default;
        }

        return max( $min, min( $max, (int) $value ) );
    }
}
