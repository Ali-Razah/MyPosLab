<?php

namespace Modules\MplOwnerApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserAttribute;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Generator;

class OwnerAppController extends Controller
{
    /**
     * Standalone, mobile-first owner dashboard.
     */
    public function page(): View
    {
        $ownerUrl = ns()->route( 'mplownerapp.owner' );

        return view( 'MplOwnerApp::owner', [
            'title' => __m( 'Owner Dashboard', 'MplOwnerApp' ),
            'theme' => $this->resolveTheme(),
            'isRtl' => in_array( app()->getLocale(), config( 'nexopos.rtl-languages', [] ), true ),
            'ownerConfig' => [
                'apiUrl' => ns()->route( 'mplownerapp.api.metrics' ),
                'pageUrl' => $ownerUrl,
                'swUrl' => route( 'mplownerapp.sw' ),
                'swScope' => parse_url( $ownerUrl, PHP_URL_PATH ) ?: '/',
                'dashboardUrl' => ns()->route( 'ns.dashboard.home' ),
                'storeName' => ns()->option->get( 'ns_store_name', config( 'app.name' ) ),
                'refreshInterval' => 60,
                'language' => app()->getLocale(),
            ],
        ] );
    }

    /**
     * Desktop page reachable from the dashboard menu, showing a QR code
     * the owner scans with a phone to open the owner dashboard.
     */
    public function connect(): View
    {
        $ownerUrl = ns()->route( 'mplownerapp.owner' );

        $svg = ( new Generator )
            ->format( 'svg' )
            ->size( 260 )
            ->margin( 1 )
            ->errorCorrection( 'M' )
            ->generate( $ownerUrl );

        return view( 'MplOwnerApp::connect', [
            'title' => __m( 'Owner App', 'MplOwnerApp' ),
            'description' => __m( 'Scan the QR code with your phone to open the owner dashboard, then add it to your home screen.', 'MplOwnerApp' ),
            'ownerUrl' => $ownerUrl,
            'qrCodeDataUri' => 'data:image/svg+xml;base64,' . base64_encode( (string) $svg ),
        ] );
    }

    private function resolveTheme(): string
    {
        $user = Auth::user();

        if ( $user && $user->attribute instanceof UserAttribute && ! empty( $user->attribute->theme ) ) {
            return $user->attribute->theme;
        }

        return ns()->option->get( 'ns_default_theme', 'light' );
    }
}
