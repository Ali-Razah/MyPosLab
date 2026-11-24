import { __m } from '../i18n';
import { config } from '../config';
import { messageFrom, request } from '../http';

declare const nsSnackBar: any;


/**
 * "Send via WhatsApp" popup.
 *
 * Options API + string template on purpose: it is rendered by the POS and the
 * dashboard popup hosts, which compile templates with their own Vue runtime.
 *
 * Params: { orderId: number }
 */
export default {
    name: 'MplWhatsAppSendReceiptPopup',
    props: ['popup'],
    data() {
        return {
            loading: true,
            sending: false,
            context: null as any,
            phone: '',
            fallbackUrl: '',
        };
    },
    computed: {
        isLinkMode(): boolean {
            return (this as any).context?.mode !== 'cloud_api';
        },
    },
    mounted() {
        const self = this as any;

        self.popupCloser();
        self.loadContext();

        setTimeout(() => self.$el?.querySelector?.('input')?.focus(), 150);
    },
    methods: {
        __m,
        popupCloser() {
            const closer = (window as any).popupCloser;

            if (typeof closer === 'function') {
                closer.call(this);
            }
        },

        async loadContext() {
            const self = this as any;

            try {
                self.context = await request('get', `${config().apiBase}/orders/${self.popup.params.orderId}`);
                self.phone = self.context.customer_phone ?? '';
            } catch (error) {
                nsSnackBar.error(messageFrom(error, __m('Unable to load the order details.', 'MplWhatsApp')));
                self.popup.close();
            } finally {
                self.loading = false;
            }
        },

        close() {
            (this as any).popup.close();
        },

        async send() {
            const self = this as any;

            if (self.sending || !self.context) {
                return;
            }

            if (String(self.phone).trim() === '') {
                nsSnackBar.error(__m('Type the customer WhatsApp number first.', 'MplWhatsApp'));
                return;
            }

            /**
             * Browsers only allow new tabs opened directly from the click:
             * open it now and point it to WhatsApp once the server answers.
             */
            const target = self.isLinkMode ? window.open('', '_blank') : null;
            self.sending = true;

            try {
                const result: any = await request('post', `${config().apiBase}/orders/${self.context.order.id}/send`, {
                    phone: self.phone,
                });

                if (result.mode === 'link') {
                    if (target) {
                        target.opener = null;
                        target.location.href = result.url;
                        nsSnackBar.success(result.message);
                        self.close();
                    } else {
                        self.fallbackUrl = result.url;
                        nsSnackBar.info(__m('Your browser blocked the new tab. Tap "Open WhatsApp" to continue.', 'MplWhatsApp'));
                    }
                } else {
                    nsSnackBar.success(result.message);
                    self.close();
                }
            } catch (error) {
                target?.close();
                nsSnackBar.error(messageFrom(error, __m('Unable to send the receipt on WhatsApp.', 'MplWhatsApp')));
            } finally {
                self.sending = false;
            }
        },
    },
    template: `
        <div class="ns-box mwa:flex mwa:w-[95vw] mwa:max-w-md mwa:flex-col mwa:overflow-hidden mwa:rounded-lg mwa:shadow-lg">
            <div class="mwa:flex mwa:items-center mwa:justify-between mwa:border-b mwa:border-box-edge mwa:p-3">
                <h3 class="mwa:flex mwa:items-center mwa:gap-2 mwa:text-lg mwa:font-semibold mwa:text-fontcolor">
                    <i class="lab la-whatsapp mwa:text-2xl mwa:text-success-tertiary"></i>
                    <span>{{ __m( 'Send via WhatsApp', 'MplWhatsApp' ) }}</span>
                </h3>
                <div class="ns-button default">
                    <button type="button" class="mwa:rounded mwa:px-2 mwa:py-1" @click="close()" :aria-label="__m( 'Close', 'MplWhatsApp' )">
                        <i class="las la-times"></i>
                    </button>
                </div>
            </div>

            <div v-if="loading" class="mwa:flex mwa:min-h-40 mwa:flex-col mwa:items-center mwa:justify-center mwa:gap-2 mwa:p-6">
                <ns-spinner size="10" border="4"></ns-spinner>
                <span class="mwa:text-sm mwa:text-fontcolor-soft">{{ __m( 'Loading…', 'MplWhatsApp' ) }}</span>
            </div>

            <div v-else-if="context" class="mwa:flex mwa:flex-col mwa:gap-3 mwa:p-4">
                <div class="mwa:text-sm mwa:text-fontcolor">
                    <div class="mwa:flex mwa:justify-between mwa:gap-2">
                        <span class="mwa:text-fontcolor-soft">{{ __m( 'Order', 'MplWhatsApp' ) }}</span>
                        <strong>{{ context.order.code }}</strong>
                    </div>
                    <div class="mwa:flex mwa:justify-between mwa:gap-2">
                        <span class="mwa:text-fontcolor-soft">{{ __m( 'Customer', 'MplWhatsApp' ) }}</span>
                        <span>{{ context.order.customer_name }}</span>
                    </div>
                    <div class="mwa:flex mwa:justify-between mwa:gap-2">
                        <span class="mwa:text-fontcolor-soft">{{ __m( 'Total', 'MplWhatsApp' ) }}</span>
                        <span>{{ context.order.total }}</span>
                    </div>
                </div>

                <label class="mwa:flex mwa:flex-col mwa:gap-1">
                    <span class="mwa:text-sm mwa:font-semibold mwa:text-fontcolor">{{ __m( 'WhatsApp number', 'MplWhatsApp' ) }}</span>
                    <div class="ns-input">
                        <input type="tel" inputmode="tel" autocomplete="off" v-model="phone" @keyup.enter="send()"
                            class="mwa:w-full mwa:rounded mwa:border-2 mwa:p-2 mwa:text-lg"
                            :placeholder="'05X XXX XXXX'"/>
                    </div>
                    <span class="mwa:text-xs mwa:text-fontcolor-soft">
                        {{ __m( 'Local numbers get the default country code', 'MplWhatsApp' ) }} (+{{ context.country_code }}).
                        <template v-if="! context.customer_phone">{{ __m( 'The customer has no phone number saved.', 'MplWhatsApp' ) }}</template>
                    </span>
                </label>

                <p v-if="context.last_message" class="mwa:text-xs mwa:text-fontcolor-soft">
                    {{ __m( 'Last attempt', 'MplWhatsApp' ) }}: {{ context.last_message.status }} — +{{ context.last_message.phone }}
                    <span v-if="context.last_message.error" class="mwa:block mwa:text-error-tertiary">{{ context.last_message.error }}</span>
                </p>

                <div v-if="fallbackUrl" class="ns-button success">
                    <a :href="fallbackUrl" target="_blank" rel="noopener noreferrer" class="mwa:flex mwa:items-center mwa:justify-center mwa:gap-2 mwa:rounded mwa:px-4 mwa:py-2">
                        <i class="lab la-whatsapp"></i>
                        <span>{{ __m( 'Open WhatsApp', 'MplWhatsApp' ) }}</span>
                    </a>
                </div>
            </div>

            <div v-if="context" class="mwa:flex mwa:justify-end mwa:gap-2 mwa:border-t mwa:border-box-edge mwa:p-3">
                <div class="ns-button default">
                    <button type="button" class="mwa:rounded mwa:px-4 mwa:py-2" @click="close()">{{ __m( 'Cancel', 'MplWhatsApp' ) }}</button>
                </div>
                <div class="ns-button success">
                    <button type="button" class="mwa:flex mwa:items-center mwa:gap-2 mwa:rounded mwa:px-4 mwa:py-2" :disabled="sending" @click="send()">
                        <ns-spinner v-if="sending" size="6" border="2"></ns-spinner>
                        <i v-else class="lab la-whatsapp"></i>
                        <span>{{ isLinkMode ? __m( 'Open in WhatsApp', 'MplWhatsApp' ) : __m( 'Send Receipt', 'MplWhatsApp' ) }}</span>
                    </button>
                </div>
            </div>
        </div>
    `,
};
