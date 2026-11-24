import { nsCreateApp } from 'vue';
import OwnerApp from './components/OwnerApp.vue';
import type { OwnerConfig } from './types';

declare global {
    interface Window {
        MplOwnerAppConfig: OwnerConfig;
    }
}

const config = window.MplOwnerAppConfig;

/**
 * Standalone page (no #dashboard-content): a dedicated app is allowed here.
 */
nsCreateApp(OwnerApp, { config }, {
    registerCoreComponents: false,
    registerExtraComponents: false,
}).mount('#mplownerapp-root');

/**
 * Register the owner service worker, scoped to the owner page only.
 */
if ('serviceWorker' in navigator && window.isSecureContext && config?.swUrl) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register(config.swUrl, { scope: config.swScope })
            .then(() => navigator.serviceWorker.ready)
            .then((registration) => {
                const assetUrls = performance
                    .getEntriesByType('resource')
                    .map((entry) => entry.name)
                    .filter((url) => url.startsWith(window.location.origin) && !url.includes('/api/'));

                registration.active?.postMessage({
                    type: 'mplownerapp:cache-shell',
                    urls: [config.pageUrl, ...assetUrls],
                });
            })
            .catch((error) => console.warn('[MplOwnerApp] Service worker registration failed', error));
    });
}
