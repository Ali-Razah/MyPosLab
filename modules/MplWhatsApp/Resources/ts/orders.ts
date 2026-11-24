import { markRaw } from 'vue';
import SendReceiptPopup from './components/SendReceiptPopup';

declare const nsEvent: any;
declare const Popup: any;

/**
 * Orders list: handles the "Send via WhatsApp" row action
 * (identifier "mplwhatsapp.send", added by CrudActionEventListener).
 */
const popupComponent = markRaw(SendReceiptPopup);

document.addEventListener('DOMContentLoaded', () => {
    nsEvent.subject().subscribe((event: any) => {
        if (event?.identifier === 'ns-table-row-action' && event.value?.action?.identifier === 'mplwhatsapp.send') {
            Popup.show(popupComponent, { orderId: event.value.row.id });
        }
    });
});
