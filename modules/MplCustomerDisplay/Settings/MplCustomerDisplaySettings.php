<?php

namespace Modules\MplCustomerDisplay\Settings;

use App\Classes\FormInput;
use App\Classes\SettingForm;
use App\Services\Helper;
use App\Services\SettingsPage;
use Closure;
use Illuminate\Http\Request;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;

class MplCustomerDisplaySettings extends SettingsPage
{
    const IDENTIFIER = 'mplcd-settings';

    const AUTOLOAD = true;

    public function __construct()
    {
        $yesNo = Helper::kvToJsOptions( [
            'yes' => __m( 'Yes', 'MplCustomerDisplay' ),
            'no' => __m( 'No', 'MplCustomerDisplay' ),
        ] );

        $this->form = SettingForm::form(
            title: __m( 'Customer Display Settings', 'MplCustomerDisplay' ),
            description: __m( 'Configure the customer-facing second screen that mirrors the POS cart.', 'MplCustomerDisplay' ),
            tabs: SettingForm::tabs(
                SettingForm::tab(
                    identifier: 'general',
                    label: __m( 'General', 'MplCustomerDisplay' ),
                    fields: SettingForm::fields(
                        FormInput::select(
                            label: __m( 'Enable Customer Display', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_ENABLED,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_ENABLED, 'yes' ),
                            options: $yesNo,
                            validation: 'required|in:yes,no',
                            description: __m( 'When disabled, the POS no longer shows the "Customer display" button nor sends cart updates to the second screen.', 'MplCustomerDisplay' ),
                        ),
                        FormInput::select(
                            label: __m( 'Theme', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_THEME,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_THEME, 'dark' ),
                            options: Helper::kvToJsOptions( [
                                'dark' => __m( 'Dark (recommended for bright shops)', 'MplCustomerDisplay' ),
                                'light' => __m( 'Light', 'MplCustomerDisplay' ),
                            ] ),
                            validation: 'required|in:light,dark',
                            description: __m( 'Color scheme of the customer screen, independent from the cashier theme.', 'MplCustomerDisplay' ),
                        ),
                        FormInput::select(
                            label: __m( 'Show Tax Line', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_SHOW_TAX,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_SHOW_TAX, 'yes' ),
                            options: $yesNo,
                            validation: 'required|in:yes,no',
                            description: __m( 'Displays the VAT amount in the totals panel. The total always includes tax.', 'MplCustomerDisplay' ),
                        ),
                        FormInput::number(
                            label: __m( 'Thank-you Screen Duration', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_THANK_YOU_DURATION,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_THANK_YOU_DURATION, CustomerDisplayConfig::DEFAULT_THANK_YOU_DURATION ),
                            validation: 'required|integer|min:2|max:60',
                            description: __m( 'Seconds the "Thank you" screen with the change due stays visible after a sale (2 to 60).', 'MplCustomerDisplay' ),
                        ),
                    )
                ),
                SettingForm::tab(
                    identifier: 'idle',
                    label: __m( 'Welcome Screen', 'MplCustomerDisplay' ),
                    fields: SettingForm::fields(
                        FormInput::text(
                            label: __m( 'Welcome Message (English)', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_WELCOME_EN,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_WELCOME_EN, '' ),
                            validation: 'nullable|string|max:160',
                            description: __m( 'English text shown while the cart is empty. Leave empty to use the default greeting.', 'MplCustomerDisplay' ),
                        ),
                        FormInput::text(
                            label: __m( 'Welcome Message (Arabic)', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_WELCOME_AR,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_WELCOME_AR, '' ),
                            validation: 'nullable|string|max:160',
                            description: __m( 'Arabic text shown right-to-left under the English message. Leave empty to use the default greeting.', 'MplCustomerDisplay' ),
                        ),
                        FormInput::textarea(
                            label: __m( 'Promo Image URLs', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_PROMO_IMAGES,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_PROMO_IMAGES, '' ),
                            validation: 'nullable|string|max:5000',
                            description: __m( 'One image URL per line (https://... or /path). Images rotate as a slideshow on the welcome screen; up to 20 are used.', 'MplCustomerDisplay' ),
                        ),
                        FormInput::number(
                            label: __m( 'Slide Interval', 'MplCustomerDisplay' ),
                            name: CustomerDisplayConfig::OPTION_SLIDE_INTERVAL,
                            value: ns()->option->get( CustomerDisplayConfig::OPTION_SLIDE_INTERVAL, CustomerDisplayConfig::DEFAULT_SLIDE_INTERVAL ),
                            validation: 'required|integer|min:3|max:120',
                            description: __m( 'Seconds each promo image stays on screen before the next one (3 to 120).', 'MplCustomerDisplay' ),
                        ),
                    )
                ),
            )
        );
    }

    /**
     * Adds a per-line URL check to the promo images textarea on top of
     * the descriptor rules (fields are posted under their tab identifier).
     */
    public function validateForm( Request $request )
    {
        $rules = parent::validateForm( $request );
        $key = 'idle.' . CustomerDisplayConfig::OPTION_PROMO_IMAGES;

        $rules[ $key ] = [
            ...( is_string( $rules[ $key ] ?? null ) ? explode( '|', $rules[ $key ] ) : [ 'nullable', 'string' ] ),
            function ( string $attribute, mixed $value, Closure $fail ): void {
                if ( ! is_string( $value ) ) {
                    return;
                }

                foreach ( preg_split( '/\r\n|\r|\n/', $value ) ?: [] as $line ) {
                    $line = trim( $line );

                    if ( $line !== '' && ! CustomerDisplayConfig::isAllowedImageUrl( $line ) ) {
                        $fail( sprintf( __m( 'The promo image "%s" must be an http(s) URL or a path starting with "/".', 'MplCustomerDisplay' ), $line ) );

                        return;
                    }
                }
            },
        ];

        return $rules;
    }

    public function saveForm( Request $request )
    {
        ns()->restrict( [ 'mplcustomerdisplay.settings' ] );

        return parent::saveForm( $request );
    }
}
