<?php

namespace Modules\MplWhatsApp\Http\Controllers;

use App\Http\Controllers\DashboardController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use Modules\MplWhatsApp\Settings\WhatsAppSettings;

class WhatsAppSettingsController extends DashboardController
{
    public function page(): View
    {
        $settings = new WhatsAppSettings;
        $form = $settings->getForm();

        return ViewFactory::make( 'MplWhatsApp::settings', [
            'title' => $form[ 'title' ],
            'description' => $form[ 'description' ],
            'settingsUrl' => ns()->url( '/api/mplwhatsapp/settings' ),
        ] );
    }

    /**
     * @return array<string, mixed>
     */
    public function getForm(): array
    {
        return ( new WhatsAppSettings )->getForm();
    }

    /**
     * @return array{status: string, message: string}
     */
    public function save( Request $request ): array
    {
        $settings = new WhatsAppSettings;
        $request->validate( $settings->validateForm( $request ) );

        return $settings->saveForm( $request );
    }
}
