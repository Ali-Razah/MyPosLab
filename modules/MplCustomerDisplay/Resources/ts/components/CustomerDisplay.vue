<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import BiLabel from './BiLabel.vue';
import { __m } from '../i18n';
import { ARABIC_LABELS as AR } from '../shared/arabic.ts';
import { DisplayChannel } from '../shared/channel.ts';
import type { DisplayMessage, DisplaySnapshot } from '../shared/snapshot.ts';

type DisplayConfig = {
    channel?: string;
    enabled?: boolean;
    theme?: string;
    showTax?: boolean;
    thankYouDuration?: number;
    slideInterval?: number;
    welcome?: { en?: string; ar?: string };
    promoImages?: string[];
    store?: { name?: string; logo?: string | null };
    timeZone?: string;
};

const props = defineProps<{ config: DisplayConfig }>();

const enabled = computed(() => props.config.enabled !== false);
const storeName = computed(() => props.config.store?.name || 'MyPosLab');
const storeLogo = computed(() => props.config.store?.logo || null);
const welcomeEn = computed(() => props.config.welcome?.en || '');
const welcomeAr = computed(() => props.config.welcome?.ar || '');
const showTax = computed(() => props.config.showTax !== false);

const snapshot = ref<DisplaySnapshot | null>(null);
const thanks = ref<DisplaySnapshot | null>(null);
const linked = ref(false);
const now = ref(new Date());
const slideIndex = ref(0);
const failedImages = ref<string[]>([]);
const linesContainer = ref<HTMLElement | null>(null);

let channel: DisplayChannel | null = null;
let thanksTimer: ReturnType<typeof setTimeout> | null = null;
let clockTimer: ReturnType<typeof setInterval> | null = null;
let slideTimer: ReturnType<typeof setInterval> | null = null;

const mode = computed<'disabled' | 'thanks' | 'cart' | 'idle'>(() => {
    if (!enabled.value) {
        return 'disabled';
    }

    if (thanks.value) {
        return 'thanks';
    }

    return snapshot.value?.state === 'cart' ? 'cart' : 'idle';
});

const images = computed(() => (props.config.promoImages ?? []).filter((url) => !failedImages.value.includes(url)));

function money(value: number): string {
    const format = (window as any).nsCurrency;

    return typeof format === 'function' ? format(value) : Number(value || 0).toFixed(2);
}

function quantity(value: number): string {
    return Number.isInteger(value) ? String(value) : String(Math.round(value * 1000) / 1000);
}

function formatDate(date: Date, locale: string, options: Intl.DateTimeFormatOptions): string {
    try {
        return new Intl.DateTimeFormat(locale, { ...options, timeZone: props.config.timeZone || undefined }).format(date);
    } catch {
        return new Intl.DateTimeFormat(locale, options).format(date);
    }
}

const clockTime = computed(() => formatDate(now.value, 'en-GB', { hour: '2-digit', minute: '2-digit' }));
const clockDateEn = computed(() => formatDate(now.value, 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' }));
const clockDateAr = computed(() => formatDate(now.value, 'ar-AE', { weekday: 'long', day: 'numeric', month: 'long' }));

function clearThanks(): void {
    if (thanksTimer !== null) {
        clearTimeout(thanksTimer);
        thanksTimer = null;
    }

    thanks.value = null;
}

function showThanks(paid: DisplaySnapshot): void {
    clearThanks();
    thanks.value = paid;
    snapshot.value = null;

    const seconds = Math.max(2, Math.min(60, Number(props.config.thankYouDuration) || 6));
    thanksTimer = setTimeout(() => {
        thanksTimer = null;
        thanks.value = null;
    }, seconds * 1000);
}

function handleMessage(message: DisplayMessage): void {
    if (message.type === 'hello') {
        return;
    }

    linked.value = true;

    switch (message.type) {
        case 'snapshot':
            // A new customer's cart interrupts the thank-you screen; an empty cart does not.
            if (thanks.value && message.snapshot.state === 'cart') {
                clearThanks();
            }

            snapshot.value = message.snapshot;
            break;
        case 'paid':
            showThanks(message.snapshot);
            break;
        case 'reset':
            snapshot.value = null;
            break;
    }
}

function sayHello(): void {
    channel?.post({ type: 'hello' });
}

function onVisibility(): void {
    if (document.visibilityState === 'visible') {
        sayHello();
    }
}

function restartSlideshow(): void {
    if (slideTimer !== null) {
        clearInterval(slideTimer);
        slideTimer = null;
    }

    slideIndex.value = 0;

    if (images.value.length > 1) {
        const seconds = Math.max(3, Math.min(120, Number(props.config.slideInterval) || 8));
        slideTimer = setInterval(() => {
            slideIndex.value = (slideIndex.value + 1) % Math.max(1, images.value.length);
        }, seconds * 1000);
    }
}

function onImageError(url: string): void {
    if (!failedImages.value.includes(url)) {
        failedImages.value = [...failedImages.value, url];
    }
}

watch(() => images.value.length, restartSlideshow);

watch(
    () => snapshot.value?.lines.length ?? 0,
    async (count, previous) => {
        if (count > (previous ?? 0)) {
            await nextTick();
            linesContainer.value?.scrollTo({ top: linesContainer.value.scrollHeight });
        }
    }
);

onMounted(() => {
    if (!enabled.value) {
        return;
    }

    channel = new DisplayChannel(props.config.channel || 'myposlab-customer-display');
    channel.onMessage(handleMessage);
    sayHello();

    document.addEventListener('visibilitychange', onVisibility);
    clockTimer = setInterval(() => {
        now.value = new Date();
    }, 1000);

    restartSlideshow();
});

onBeforeUnmount(() => {
    clearThanks();

    if (clockTimer !== null) {
        clearInterval(clockTimer);
    }

    if (slideTimer !== null) {
        clearInterval(slideTimer);
    }

    document.removeEventListener('visibilitychange', onVisibility);
    channel?.close();
});
</script>

<template>
    <div class="mplcd:flex mplcd:h-dvh mplcd:w-full mplcd:flex-col mplcd:overflow-hidden mplcd:bg-surface mplcd:text-fontcolor mplcd:select-none">
        <!-- Header: store identity, customer, clock -->
        <header class="mplcd:flex mplcd:shrink-0 mplcd:items-center mplcd:justify-between mplcd:gap-4 mplcd:border-b mplcd:border-box-edge mplcd:bg-box-background mplcd:px-6 mplcd:py-3">
            <div class="mplcd:flex mplcd:min-w-0 mplcd:items-center mplcd:gap-4">
                <img v-if="storeLogo" :src="storeLogo" :alt="storeName" class="mplcd:h-12 mplcd:max-w-48 mplcd:object-contain">
                <span class="mplcd:truncate mplcd:text-2xl mplcd:font-bold">{{ storeName }}</span>
            </div>
            <div v-if="mode === 'cart' && snapshot?.customerName" class="mplcd:flex mplcd:min-w-0 mplcd:items-center mplcd:gap-3 mplcd:text-xl">
                <i class="las la-user mplcd:text-3xl mplcd:text-info-tertiary"></i>
                <span class="mplcd:truncate mplcd:font-semibold">{{ snapshot.customerName }}</span>
            </div>
            <div class="mplcd:flex mplcd:shrink-0 mplcd:items-center mplcd:gap-3">
                <span
                    class="mplcd:inline-block mplcd:h-3 mplcd:w-3 mplcd:rounded-full"
                    :class="linked ? 'mplcd:bg-success-secondary' : 'mplcd:bg-warning-secondary'"
                    :title="linked ? __m( 'Connected to the POS', 'MplCustomerDisplay' ) : __m( 'Waiting for the POS window', 'MplCustomerDisplay' )"
                ></span>
                <span class="mplcd:text-2xl mplcd:font-semibold mplcd:tabular-nums">{{ clockTime }}</span>
            </div>
        </header>

        <main class="mplcd:relative mplcd:min-h-0 mplcd:flex-1">
            <!-- Disabled -->
            <section v-if="mode === 'disabled'" class="mplcd:flex mplcd:h-full mplcd:flex-col mplcd:items-center mplcd:justify-center mplcd:gap-4 mplcd:p-8 mplcd:text-center">
                <i class="las la-desktop mplcd:text-7xl mplcd:text-fontcolor-soft"></i>
                <p class="mplcd:text-3xl mplcd:font-semibold">{{ __m( 'The customer display is disabled.', 'MplCustomerDisplay' ) }}</p>
                <p class="mplcd-arabic mplcd:text-3xl" dir="rtl" lang="ar">{{ AR.disabled }}</p>
                <p class="mplcd:text-lg mplcd:text-fontcolor-soft">{{ __m( 'An administrator can enable it from Settings > Customer Display.', 'MplCustomerDisplay' ) }}</p>
            </section>

            <!-- Thank you -->
            <section v-else-if="mode === 'thanks' && thanks" class="mplcd-fade mplcd:flex mplcd:h-full mplcd:flex-col mplcd:items-center mplcd:justify-center mplcd:gap-6 mplcd:p-8 mplcd:text-center">
                <i class="las la-check-circle mplcd:text-[clamp(4rem,12vmin,9rem)] mplcd:text-success-tertiary"></i>
                <h1 class="mplcd:text-[clamp(2.5rem,7vmin,5.5rem)] mplcd:font-bold mplcd:leading-tight">{{ __m( 'Thank you!', 'MplCustomerDisplay' ) }}</h1>
                <p class="mplcd-arabic mplcd:text-[clamp(2rem,6vmin,4.5rem)] mplcd:font-bold" dir="rtl" lang="ar">{{ AR.thankYou }}</p>
                <div class="mplcd:mt-4 mplcd:grid mplcd:w-full mplcd:max-w-3xl mplcd:gap-4 mplcd:sm:grid-cols-2">
                    <div class="mplcd:rounded-2xl mplcd:border mplcd:border-box-edge mplcd:bg-box-background mplcd:p-6">
                        <BiLabel class="mplcd:text-xl mplcd:text-fontcolor-soft" :en="__m( 'Total paid', 'MplCustomerDisplay' )" :ar="AR.totalPaid" />
                        <p class="mplcd:mt-2 mplcd:text-[clamp(2rem,5vmin,3.5rem)] mplcd:font-bold mplcd:tabular-nums">{{ money( thanks.paid ) }}</p>
                    </div>
                    <div class="mplcd:rounded-2xl mplcd:bg-success-secondary mplcd:p-6 mplcd:text-white">
                        <BiLabel class="mplcd:text-xl" :en="__m( 'Your change', 'MplCustomerDisplay' )" :ar="AR.changeDue" />
                        <p class="mplcd:mt-2 mplcd:text-[clamp(2.5rem,7vmin,5rem)] mplcd:font-extrabold mplcd:tabular-nums">{{ money( thanks.change ) }}</p>
                    </div>
                </div>
            </section>

            <!-- Live cart -->
            <section v-else-if="mode === 'cart' && snapshot" class="mplcd:flex mplcd:h-full mplcd:flex-col mplcd:landscape:flex-row">
                <div class="mplcd:flex mplcd:min-h-0 mplcd:flex-1 mplcd:flex-col">
                    <div class="mplcd:flex mplcd:shrink-0 mplcd:items-center mplcd:justify-between mplcd:px-6 mplcd:pt-4 mplcd:pb-2 mplcd:text-lg mplcd:text-fontcolor-soft">
                        <BiLabel :stacked="false" :en="__m( 'Items', 'MplCustomerDisplay' )" :ar="AR.items" />
                        <span class="mplcd:rounded-full mplcd:bg-info-secondary mplcd:px-4 mplcd:py-1 mplcd:text-lg mplcd:font-bold mplcd:text-white mplcd:tabular-nums">{{ quantity( snapshot.itemsCount ) }}</span>
                    </div>
                    <ul ref="linesContainer" class="mplcd-lines mplcd:min-h-0 mplcd:flex-1 mplcd:overflow-y-auto mplcd:px-4 mplcd:pb-4">
                        <li
                            v-for="line of snapshot.lines"
                            :key="line.key"
                            class="mplcd-line-enter mplcd:mb-2 mplcd:flex mplcd:items-center mplcd:gap-4 mplcd:rounded-xl mplcd:border mplcd:border-box-edge mplcd:bg-box-background mplcd:px-4 mplcd:py-3"
                        >
                            <div class="mplcd:min-w-0 mplcd:flex-1">
                                <p class="mplcd:text-[clamp(1.25rem,2.6vmin,2rem)] mplcd:font-semibold mplcd:leading-snug mplcd:break-words" dir="auto">{{ line.name }}</p>
                                <p class="mplcd:mt-1 mplcd:flex mplcd:flex-wrap mplcd:items-center mplcd:gap-x-3 mplcd:gap-y-1 mplcd:text-[clamp(1rem,2vmin,1.4rem)] mplcd:text-fontcolor-soft mplcd:tabular-nums">
                                    <span>{{ quantity( line.quantity ) }} × {{ money( line.unitPrice ) }}</span>
                                    <span v-if="line.unit" dir="auto">{{ line.unit }}</span>
                                    <span v-if="line.discount > 0" class="mplcd:rounded-md mplcd:bg-warning-secondary mplcd:px-2 mplcd:py-0.5 mplcd:text-base mplcd:font-semibold mplcd:text-white">
                                        <i class="las la-tag"></i>
                                        {{ line.discountLabel || __m( 'Discount', 'MplCustomerDisplay' ) }} −{{ money( line.discount ) }}
                                    </span>
                                    <span v-if="line.lineExtra > 0">+ {{ money( line.lineExtra ) }}</span>
                                </p>
                            </div>
                            <p class="mplcd:shrink-0 mplcd:text-[clamp(1.4rem,3vmin,2.25rem)] mplcd:font-bold mplcd:tabular-nums">{{ money( line.total ) }}</p>
                        </li>
                    </ul>
                </div>

                <aside class="mplcd:flex mplcd:shrink-0 mplcd:flex-col mplcd:gap-3 mplcd:border-t mplcd:border-box-edge mplcd:bg-box-background mplcd:p-5 mplcd:landscape:w-[40%] mplcd:landscape:max-w-xl mplcd:landscape:border-t-0 mplcd:landscape:border-l">
                    <dl class="mplcd:flex mplcd:flex-col mplcd:gap-2 mplcd:text-[clamp(1.1rem,2.3vmin,1.6rem)]">
                        <div class="mplcd:flex mplcd:items-center mplcd:justify-between mplcd:gap-4">
                            <dt><BiLabel :en="__m( 'Subtotal', 'MplCustomerDisplay' )" :ar="AR.subtotal" /></dt>
                            <dd class="mplcd:font-semibold mplcd:tabular-nums">{{ money( snapshot.subtotal ) }}</dd>
                        </div>
                        <div v-if="snapshot.discount > 0" class="mplcd:flex mplcd:items-center mplcd:justify-between mplcd:gap-4 mplcd:text-warning-tertiary">
                            <dt><BiLabel :en="__m( 'Discount', 'MplCustomerDisplay' )" :ar="AR.discount" /></dt>
                            <dd class="mplcd:font-semibold mplcd:tabular-nums">−{{ money( snapshot.discount ) }}</dd>
                        </div>
                        <div v-if="snapshot.shipping > 0" class="mplcd:flex mplcd:items-center mplcd:justify-between mplcd:gap-4">
                            <dt><BiLabel :en="__m( 'Delivery', 'MplCustomerDisplay' )" :ar="AR.shipping" /></dt>
                            <dd class="mplcd:font-semibold mplcd:tabular-nums">{{ money( snapshot.shipping ) }}</dd>
                        </div>
                        <div v-if="showTax && snapshot.tax" class="mplcd:flex mplcd:items-center mplcd:justify-between mplcd:gap-4">
                            <dt>
                                <BiLabel
                                    :en="snapshot.tax.included ? __m( 'VAT (included)', 'MplCustomerDisplay' ) : __m( 'VAT', 'MplCustomerDisplay' )"
                                    :ar="snapshot.tax.included ? AR.vatIncluded : AR.vat"
                                />
                            </dt>
                            <dd class="mplcd:font-semibold mplcd:tabular-nums">{{ money( snapshot.tax.amount ) }}</dd>
                        </div>
                    </dl>

                    <div class="mplcd:mt-auto mplcd:rounded-2xl mplcd:bg-info-secondary mplcd:px-5 mplcd:py-4 mplcd:text-white">
                        <BiLabel class="mplcd:text-[clamp(1.4rem,3vmin,2.2rem)] mplcd:font-bold mplcd:uppercase" :stacked="false" :en="__m( 'Total', 'MplCustomerDisplay' )" :ar="AR.total" />
                        <p class="mplcd:text-right mplcd:text-[clamp(3rem,9vmin,7rem)] mplcd:font-extrabold mplcd:leading-none mplcd:tabular-nums">{{ money( snapshot.total ) }}</p>
                    </div>

                    <dl v-if="snapshot.paid > 0" class="mplcd:grid mplcd:grid-cols-2 mplcd:gap-3 mplcd:text-[clamp(1.1rem,2.3vmin,1.6rem)]">
                        <div class="mplcd:rounded-xl mplcd:border mplcd:border-box-edge mplcd:p-3">
                            <dt><BiLabel :en="__m( 'Paid', 'MplCustomerDisplay' )" :ar="AR.paid" /></dt>
                            <dd class="mplcd:mt-1 mplcd:font-bold mplcd:tabular-nums">{{ money( snapshot.paid ) }}</dd>
                        </div>
                        <div v-if="snapshot.change > 0" class="mplcd:rounded-xl mplcd:bg-success-secondary mplcd:p-3 mplcd:text-white">
                            <dt><BiLabel :en="__m( 'Change', 'MplCustomerDisplay' )" :ar="AR.change" /></dt>
                            <dd class="mplcd:mt-1 mplcd:font-bold mplcd:tabular-nums">{{ money( snapshot.change ) }}</dd>
                        </div>
                        <div v-else class="mplcd:rounded-xl mplcd:border mplcd:border-warning-tertiary mplcd:p-3 mplcd:text-warning-tertiary">
                            <dt><BiLabel :en="__m( 'Remaining', 'MplCustomerDisplay' )" :ar="AR.due" /></dt>
                            <dd class="mplcd:mt-1 mplcd:font-bold mplcd:tabular-nums">{{ money( snapshot.due ) }}</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <!-- Idle / welcome -->
            <section v-else class="mplcd-fade mplcd:relative mplcd:h-full mplcd:w-full mplcd:overflow-hidden">
                <template v-if="images.length > 0">
                    <img
                        v-for="(url, index) of images"
                        :key="url"
                        :src="url"
                        alt=""
                        class="mplcd-slide mplcd:absolute mplcd:inset-0 mplcd:h-full mplcd:w-full mplcd:object-contain"
                        :class="index === slideIndex ? 'mplcd:opacity-100' : 'mplcd:opacity-0'"
                        @error="onImageError( url )"
                    >
                </template>
                <div
                    class="mplcd:absolute mplcd:inset-x-0 mplcd:flex mplcd:flex-col mplcd:items-center mplcd:gap-3 mplcd:p-8 mplcd:text-center"
                    :class="images.length > 0 ? 'mplcd:bottom-0 mplcd:bg-box-background/85' : 'mplcd:inset-y-0 mplcd:justify-center'"
                >
                    <p v-if="images.length === 0" class="mplcd:text-[clamp(4rem,14vmin,10rem)] mplcd:font-bold mplcd:leading-none mplcd:tabular-nums">{{ clockTime }}</p>
                    <p v-if="images.length === 0" class="mplcd:flex mplcd:flex-wrap mplcd:justify-center mplcd:gap-x-6 mplcd:text-[clamp(1.1rem,2.6vmin,1.8rem)] mplcd:text-fontcolor-soft">
                        <span>{{ clockDateEn }}</span>
                        <span class="mplcd-arabic" dir="rtl" lang="ar">{{ clockDateAr }}</span>
                    </p>
                    <p class="mplcd:mt-4 mplcd:text-[clamp(1.8rem,5vmin,3.75rem)] mplcd:font-bold mplcd:leading-tight" dir="auto">{{ welcomeEn }}</p>
                    <p class="mplcd-arabic mplcd:text-[clamp(1.8rem,5vmin,3.75rem)] mplcd:font-bold mplcd:leading-tight" dir="rtl" lang="ar">{{ welcomeAr }}</p>
                </div>
            </section>
        </main>
    </div>
</template>
