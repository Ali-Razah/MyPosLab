import { __m } from '../i18n';

declare const nsSnackBar: any;
declare const nsNotice: any;

type PosDisplayConfig = {
    displayUrl: string;
    windowName: string;
};

/**
 * Opens (or re-focuses) the customer display window. A named target is used
 * so repeated clicks reuse the same window instead of opening new ones, and
 * an already-open display is not reloaded.
 */
export function openCustomerDisplay(config: PosDisplayConfig): Window | null {
    const features = 'popup=yes,width=1280,height=800';
    const displayWindow = window.open('', config.windowName, features);

    if (!displayWindow) {
        nsSnackBar.error(
            __m('The browser blocked the customer display window. Allow pop-ups for this site, then try again.', 'MplCustomerDisplay')
        );

        return null;
    }

    let mustNavigate = true;

    try {
        const current = displayWindow.location.href;
        const target = new URL(config.displayUrl, window.location.href);

        mustNavigate = current === 'about:blank' || new URL(current).pathname !== target.pathname;
    } catch {
        mustNavigate = true;
    }

    if (mustNavigate) {
        displayWindow.location.href = config.displayUrl;
    }

    displayWindow.focus();

    nsNotice.info(
        __m('Customer display opened', 'MplCustomerDisplay'),
        __m('Drag the new window onto the customer-facing monitor, click inside it and press F11 for full screen. Keep both windows open: the cart updates live.', 'MplCustomerDisplay'),
        { duration: 10000 }
    );

    return displayWindow;
}

/**
 * POS header button. Options API + string template so it is compiled by the
 * POS host Vue (dual-Vue safe).
 */
export function createPosHeaderButton(config: PosDisplayConfig) {
    return {
        name: 'MplcdPosHeaderButton',
        methods: {
            __m,
            openDisplay() {
                openCustomerDisplay(config);
            },
        },
        template: `
        <div class="ns-button default">
            <button type="button" @click="openDisplay()" :title="__m( 'Open the customer-facing second screen', 'MplCustomerDisplay' )" class="mplcd:flex mplcd:h-12 mplcd:shrink-0 mplcd:items-center mplcd:rounded mplcd:px-2 mplcd:py-1 mplcd:text-sm mplcd:shadow">
                <i class="las la-desktop mplcd:mr-1 mplcd:text-xl"></i>
                <span>{{ __m( 'Customer display', 'MplCustomerDisplay' ) }}</span>
            </button>
        </div>`,
    };
}
