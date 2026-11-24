import { __m } from '../i18n';

/**
 * POS header button: reopens the WhatsApp popup for the last completed sale.
 * Options API + string template (compiled by the POS host runtime).
 */
export function createHeaderButton(openForLastOrder: () => void) {
    return {
        name: 'MplWhatsAppHeaderButton',
        methods: {
            __m,
            openForLastOrder,
        },
        template: `
            <div class="ns-button success">
                <button type="button" @click="openForLastOrder()" class="rounded shadow flex-shrink-0 h-12 flex items-center px-2 py-1 text-sm">
                    <i class="mr-1 text-xl lab la-whatsapp"></i>
                    <span>{{ __m( 'WhatsApp Receipt', 'MplWhatsApp' ) }}</span>
                </button>
            </div>
        `,
    };
}
