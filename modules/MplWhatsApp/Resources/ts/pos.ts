import { markRaw } from 'vue';
import SendReceiptPopup from './components/SendReceiptPopup';
import { createHeaderButton } from './components/HeaderButton';
import { config } from './config';
import { __m } from './i18n';

declare const nsHooks: any;
declare const nsSnackBar: any;
declare const Popup: any;

/**
 * POS integration:
 *  - remembers the last order submitted from this POS session;
 *  - optionally opens the "Send via WhatsApp" popup right after the sale;
 *  - adds a "WhatsApp Receipt" header button to (re)open it for that order.
 */
let lastOrderId: number | null = null;

const popupComponent = markRaw(SendReceiptPopup);

function openPopup(orderId: number): void {
    Popup.show(popupComponent, { orderId });
}

function openForLastOrder(): void {
    if (lastOrderId === null) {
        nsSnackBar.info(__m('Complete a sale first. Older orders can be sent from the Orders list.', 'MplWhatsApp'));
        return;
    }

    openPopup(lastOrderId);
}

const headerButton = markRaw(createHeaderButton(openForLastOrder));

nsHooks.addAction('ns-pos-header', 'mplwhatsapp/header-button', (header: any) => {
    header.buttons.MplWhatsAppHeaderButton = headerButton;
});

nsHooks.addAction('ns-order-submit-successful', 'mplwhatsapp/after-sale', (result: any) => {
    const order = result?.data?.order;

    if (!order?.id) {
        return;
    }

    /**
     * Held / unpaid orders are not receipts yet.
     */
    if (['hold', 'unpaid'].includes(order.payment_status)) {
        return;
    }

    lastOrderId = order.id;

    /**
     * With automatic Cloud API sending the server already messages customers
     * that have a phone; the header button stays available for the others.
     */
    if (config().posPrompt && !(config().autoSend && order.payment_status === 'paid')) {
        /**
         * Let the payment popup close and the cart reset before opening ours.
         */
        setTimeout(() => openPopup(order.id), 400);
    }
});
