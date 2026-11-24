const e=globalThis.ns?.vue??globalThis.NexoPOSVue;if(!e)throw new Error("The MyPosLab Vue runtime is unavailable. Ensure resources/ts/vue-runtime.ts is loaded (via the host layout) before module assets.");e.BaseTransition;e.BaseTransitionPropsValidators;e.Comment;e.DeprecationTypes;e.EffectScope;e.ErrorCodes;e.ErrorTypeStrings;e.Fragment;e.KeepAlive;e.ReactiveEffect;e.Static;e.Suspense;e.Teleport;e.Text;e.TrackOpTypes;e.Transition;e.TransitionGroup;e.TriggerOpTypes;e.VueElement;e.__esModule;e.assertNumber;e.callWithAsyncErrorHandling;e.callWithErrorHandling;e.camelize;e.capitalize;e.cloneVNode;e.compatUtils;e.compile;e.computed;e.createApp;e.createBlock;e.createCommentVNode;e.createElementBlock;e.createElementVNode;e.createHydrationRenderer;e.createPropsRestProxy;e.createRenderer;e.createSSRApp;e.createSlots;e.createStaticVNode;e.createTextVNode;e.createVNode;e.customRef;e.defineAsyncComponent;e.defineComponent;e.defineCustomElement;e.defineEmits;e.defineExpose;e.defineModel;e.defineOptions;e.defineProps;e.defineSSRCustomElement;e.defineSlots;e.devtools;e.effect;e.effectScope;e.getCurrentInstance;e.getCurrentScope;e.getCurrentWatcher;e.getTransitionRawChildren;e.guardReactiveProps;e.h;e.handleError;e.hasInjectionContext;e.hydrate;e.hydrateOnIdle;e.hydrateOnInteraction;e.hydrateOnMediaQuery;e.hydrateOnVisible;e.initCustomFormatter;e.initDirectivesForSSR;e.inject;e.isMemoSame;e.isProxy;e.isReactive;e.isReadonly;e.isRef;e.isRuntimeOnly;e.isShallow;e.isVNode;const d=e.markRaw;e.mergeDefaults;e.mergeModels;e.mergeProps;e.nextTick;e.nodeOps;e.normalizeClass;e.normalizeProps;e.normalizeStyle;e.onActivated;e.onBeforeMount;e.onBeforeUnmount;e.onBeforeUpdate;e.onDeactivated;e.onErrorCaptured;e.onMounted;e.onRenderTracked;e.onRenderTriggered;e.onScopeDispose;e.onServerPrefetch;e.onUnmounted;e.onUpdated;e.onWatcherCleanup;e.openBlock;e.patchProp;e.popScopeId;e.provide;e.proxyRefs;e.pushScopeId;e.queuePostFlushCb;e.reactive;e.readonly;e.ref;e.registerRuntimeCompiler;e.render;e.renderList;e.renderSlot;e.resolveComponent;e.resolveDirective;e.resolveDynamicComponent;e.resolveFilter;e.resolveTransitionHooks;e.setBlockTracking;e.setDevtoolsHook;e.setTransitionHooks;e.shallowReactive;e.shallowReadonly;e.shallowRef;e.ssrContextKey;e.ssrUtils;e.stop;e.toDisplayString;e.toHandlerKey;e.toHandlers;e.toRaw;e.toRef;e.toRefs;e.toValue;e.transformVNodeArgs;e.triggerRef;e.unref;e.useAttrs;e.useCssModule;e.useCssVars;e.useHost;e.useId;e.useModel;e.useSSRContext;e.useShadowRoot;e.useSlots;e.useTemplateRef;e.useTransitionState;e.vModelCheckbox;e.vModelDynamic;e.vModelRadio;e.vModelSelect;e.vModelText;e.vShow;e.version;e.warn;e.watch;e.watchEffect;e.watchPostEffect;e.watchSyncEffect;e.withAsyncContext;e.withCtx;e.withDefaults;e.withDirectives;e.withKeys;e.withMemo;e.withModifiers;e.withScopeId;function o(t,a){const s=window.__m;return typeof s=="function"?s(t,a):t}function r(){return window.MplWhatsAppConfig}function l(t,a,s={}){return new Promise((p,c)=>{(t==="get"?nsHttpClient.get(a):nsHttpClient.post(a,s)).subscribe({next:n=>p(n),error:n=>c(n)})})}function i(t,a){if(t?.errors&&typeof t.errors=="object"){const s=Object.values(t.errors).flat().filter(Boolean);if(s.length>0)return s.join(`
`)}return typeof t?.message=="string"&&t.message.length>0?t.message:a}const u={name:"MplWhatsAppSendReceiptPopup",props:["popup"],data(){return{loading:!0,sending:!1,context:null,phone:"",fallbackUrl:""}},computed:{isLinkMode(){return this.context?.mode!=="cloud_api"}},mounted(){const t=this;t.popupCloser(),t.loadContext(),setTimeout(()=>t.$el?.querySelector?.("input")?.focus(),150)},methods:{__m:o,popupCloser(){const t=window.popupCloser;typeof t=="function"&&t.call(this)},async loadContext(){const t=this;try{t.context=await l("get",`${r().apiBase}/orders/${t.popup.params.orderId}`),t.phone=t.context.customer_phone??""}catch(a){nsSnackBar.error(i(a,o("Unable to load the order details.","MplWhatsApp"))),t.popup.close()}finally{t.loading=!1}},close(){this.popup.close()},async send(){const t=this;if(t.sending||!t.context)return;if(String(t.phone).trim()===""){nsSnackBar.error(o("Type the customer WhatsApp number first.","MplWhatsApp"));return}const a=t.isLinkMode?window.open("","_blank"):null;t.sending=!0;try{const s=await l("post",`${r().apiBase}/orders/${t.context.order.id}/send`,{phone:t.phone});s.mode==="link"?a?(a.opener=null,a.location.href=s.url,nsSnackBar.success(s.message),t.close()):(t.fallbackUrl=s.url,nsSnackBar.info(o('Your browser blocked the new tab. Tap "Open WhatsApp" to continue.',"MplWhatsApp"))):(nsSnackBar.success(s.message),t.close())}catch(s){a?.close(),nsSnackBar.error(i(s,o("Unable to send the receipt on WhatsApp.","MplWhatsApp")))}finally{t.sending=!1}}},template:`
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
    `};export{u as S,o as _,r as c,d as m};
