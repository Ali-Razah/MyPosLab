/* MyPosLab Owner App service worker.
 *
 * Scope: the owner dashboard page only (registered with an explicit scope,
 * allowed by the "Service-Worker-Allowed" header of the module route).
 *
 * - Owner page navigations: network-first, falls back to the cached shell.
 * - Metrics API: network-first, falls back to the last cached payload marked "offline".
 * - Static assets (build, modules, fonts, images, lang): stale-while-revalidate.
 */
const CACHE_VERSION = 'mplownerapp-v1';
const SHELL_CACHE = CACHE_VERSION + '-shell';
const DATA_CACHE = CACHE_VERSION + '-data';
const API_MARKER = '/api/mplownerapp/metrics';
const STATIC_MARKERS = [ '/build/', '/modules/', '/lang/', '/images/', '/fonts/', '/svg/', '/apple-touch-icon.png', '/favicon' ];

/**
 * Responses meaning the session is gone or no longer allowed: the cached
 * owner page and metrics (private data) are purged so they can't be shown
 * offline to the next person using the device.
 */
const DENIED_STATUSES = [ 401, 403, 419 ];

async function purgePrivateCaches() {
    const keys = await caches.keys();

    await Promise.all( keys
        .filter( ( key ) => key.startsWith( 'mplownerapp-' ) )
        .map( ( key ) => caches.delete( key ) ) );
}

self.addEventListener( 'install', ( event ) => {
    event.waitUntil( self.skipWaiting() );
} );

self.addEventListener( 'activate', ( event ) => {
    event.waitUntil( ( async () => {
        const keys = await caches.keys();

        await Promise.all( keys
            .filter( ( key ) => key.startsWith( 'mplownerapp-' ) && ! key.startsWith( CACHE_VERSION ) )
            .map( ( key ) => caches.delete( key ) ) );

        await self.clients.claim();
    } )() );
} );

/**
 * The page posts the URLs of its loaded assets so the shell is complete
 * for offline use even on the very first visit.
 */
self.addEventListener( 'message', ( event ) => {
    const data = event.data || {};

    if ( data.type !== 'mplownerapp:cache-shell' || ! Array.isArray( data.urls ) ) {
        return;
    }

    event.waitUntil( ( async () => {
        const cache = await caches.open( SHELL_CACHE );

        await Promise.all( data.urls
            .filter( ( url ) => typeof url === 'string' && new URL( url, self.location.origin ).origin === self.location.origin )
            .map( async ( url ) => {
                try {
                    const response = await fetch( url, { credentials: 'same-origin' } );

                    if ( response.ok && ! response.redirected ) {
                        await cache.put( url, response );
                    }
                } catch ( error ) {
                    // Ignore assets that cannot be fetched right now.
                }
            } ) );
    } )() );
} );

self.addEventListener( 'fetch', ( event ) => {
    const request = event.request;

    if ( request.method !== 'GET' ) {
        return;
    }

    const url = new URL( request.url );

    if ( url.origin !== self.location.origin ) {
        return;
    }

    if ( url.pathname.includes( API_MARKER ) ) {
        event.respondWith( networkFirstApi( request ) );
        return;
    }

    /**
     * The scope path is a prefix ("/dashboard/owner" also matches "/dashboard/owner/connect"):
     * only the owner page itself is cached.
     */
    if ( request.mode === 'navigate' && url.pathname.replace( /\/+$/, '' ) === new URL( self.registration.scope ).pathname.replace( /\/+$/, '' ) ) {
        event.respondWith( networkFirstPage( request ) );
        return;
    }

    if ( STATIC_MARKERS.some( ( marker ) => url.pathname.includes( marker ) ) ) {
        event.respondWith( staleWhileRevalidate( request ) );
    }
} );

async function networkFirstApi( request ) {
    const cache = await caches.open( DATA_CACHE );

    try {
        const response = await fetch( request );

        if ( DENIED_STATUSES.includes( response.status ) ) {
            await purgePrivateCaches();

            return response;
        }

        if ( response.ok ) {
            await cache.put( request, response.clone() );
        }

        return response;
    } catch ( error ) {
        const cached = await cache.match( request );

        if ( ! cached ) {
            throw error;
        }

        try {
            const payload = await cached.json();
            payload.offline = true;

            return new Response( JSON.stringify( payload ), {
                status: 200,
                headers: { 'Content-Type': 'application/json', 'X-MplOwnerApp-Offline': '1' },
            } );
        } catch ( parseError ) {
            return cached;
        }
    }
}

async function networkFirstPage( request ) {
    const cache = await caches.open( SHELL_CACHE );

    try {
        const response = await fetch( request );

        /**
         * Signed out (redirected to the login page) or denied: forget the private data.
         */
        if ( response.redirected || DENIED_STATUSES.includes( response.status ) ) {
            await purgePrivateCaches();

            return response;
        }

        if ( response.ok ) {
            await cache.put( request, response.clone() );
        }

        return response;
    } catch ( error ) {
        const cached = await cache.match( request, { ignoreSearch: true } );

        if ( cached ) {
            return cached;
        }

        return new Response(
            '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MyPosLab</title>' +
            '<body style="font-family:sans-serif;background:#0F172A;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center">' +
            '<p>MyPosLab Owner &mdash; offline.<br>Connect to the internet and reload.</p></body>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
        );
    }
}

async function staleWhileRevalidate( request ) {
    const cache = await caches.open( SHELL_CACHE );
    const cached = await cache.match( request );

    const network = fetch( request )
        .then( ( response ) => {
            if ( response.ok ) {
                cache.put( request, response.clone() );
            }

            return response;
        } )
        .catch( () => cached || Response.error() );

    return cached || network;
}
