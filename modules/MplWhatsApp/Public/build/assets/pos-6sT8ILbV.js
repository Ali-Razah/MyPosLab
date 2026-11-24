import{_ as n,m as o,S as r,c as a}from"./SendReceiptPopup-Cat_Ljx1.js";function d(t){return{name:"MplWhatsAppHeaderButton",methods:{__m:n,openForLastOrder:t},template:`
            <div class="ns-button success">
                <button type="button" @click="openForLastOrder()" class="rounded shadow flex-shrink-0 h-12 flex items-center px-2 py-1 text-sm">
                    <i class="mr-1 text-xl lab la-whatsapp"></i>
                    <span>{{ __m( 'WhatsApp Receipt', 'MplWhatsApp' ) }}</span>
                </button>
            </div>
        `}}let s=null;const u=o(r);function p(t){Popup.show(u,{orderId:t})}function i(){if(s===null){nsSnackBar.info(n("Complete a sale first. Older orders can be sent from the Orders list.","MplWhatsApp"));return}p(s)}const l=o(d(i));nsHooks.addAction("ns-pos-header","mplwhatsapp/header-button",t=>{t.buttons.MplWhatsAppHeaderButton=l});nsHooks.addAction("ns-order-submit-successful","mplwhatsapp/after-sale",t=>{const e=t?.data?.order;e?.id&&(["hold","unpaid"].includes(e.payment_status)||(s=e.id,a().posPrompt&&!(a().autoSend&&e.payment_status==="paid")&&setTimeout(()=>p(e.id),400)))});
