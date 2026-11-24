<?php

namespace Modules\MplCustomerDisplay\Http\Controllers;

use App\Http\Controllers\DashboardController;
use Illuminate\Contracts\View\View;
use Modules\MplCustomerDisplay\Services\CustomerDisplayConfig;
use Modules\MplCustomerDisplay\Settings\MplCustomerDisplaySettings;

class CustomerDisplayController extends DashboardController
{
    /**
     * Full-screen customer-facing page. It renders no dashboard chrome and
     * receives the cart from the POS window through BroadcastChannel.
     */
    public function display( CustomerDisplayConfig $config ): View
    {
        return view( 'MplCustomerDisplay::display', [
            'title' => __m( 'Customer Display', 'MplCustomerDisplay' ),
            'theme' => $config->theme(),
            'displayConfig' => $config->forDisplay(),
        ] );
    }

    public function settings()
    {
        return MplCustomerDisplaySettings::renderForm();
    }
}
