<?php

namespace Modules\MplOwnerApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OwnerPwaController extends Controller
{
    /**
     * Web app manifest for the owner dashboard. It is public because browsers
     * fetch manifests without credentials; it exposes no store data.
     */
    public function manifest(): JsonResponse
    {
        $ownerUrl = ns()->route( 'mplownerapp.owner' );
        $scope = parse_url( $ownerUrl, PHP_URL_PATH ) ?: '/';

        return response()->json( [
            'id' => $scope,
            'name' => 'MyPosLab Owner',
            'short_name' => 'MyPosLab',
            'description' => __m( 'Today\'s sales, profit, stock and registers at a glance.', 'MplOwnerApp' ),
            'start_url' => $ownerUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => '#4F46E5',
            'background_color' => '#0F172A',
            'lang' => app()->getLocale(),
            'dir' => in_array( app()->getLocale(), config( 'nexopos.rtl-languages', [] ), true ) ? 'rtl' : 'ltr',
            'icons' => [
                [
                    'src' => asset( 'images/brand/icon-192.png' ),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset( 'images/brand/icon-512.png' ),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset( 'apple-touch-icon.png' ),
                    'sizes' => '180x180',
                    'type' => 'image/png',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ] );
    }

    /**
     * Serves the owner service worker. It lives under a module path, so the
     * "Service-Worker-Allowed" header lets it control the owner page scope.
     */
    public function serviceWorker(): Response
    {
        $script = (string) file_get_contents( dirname( __DIR__, 2 ) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'pwa' . DIRECTORY_SEPARATOR . 'owner-sw.js' );

        return response( $script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ] );
    }
}
