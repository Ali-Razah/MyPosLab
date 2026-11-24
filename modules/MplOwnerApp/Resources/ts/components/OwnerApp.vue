<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { __, __m } from '../i18n';
import type { OwnerConfig, OwnerMetrics, OwnerPeriod } from '../types';

declare const nsHttpClient: any;
declare const nsSnackBar: any;
declare const nsCurrency: (value: number, format?: string) => string;

const props = defineProps<{ config: OwnerConfig }>();

const periods: Array<{ value: OwnerPeriod; label: string }> = [
    { value: 'today', label: __m('Today', 'MplOwnerApp') },
    { value: 'yesterday', label: __m('Yesterday', 'MplOwnerApp') },
    { value: '7d', label: __m('7 days', 'MplOwnerApp') },
    { value: '30d', label: __m('30 days', 'MplOwnerApp') },
    { value: 'month', label: __m('This month', 'MplOwnerApp') },
];

const STORAGE_KEY = 'mplownerapp.period';

function initialPeriod(): OwnerPeriod {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY) as OwnerPeriod | null;

        if (stored && periods.some((period) => period.value === stored)) {
            return stored;
        }
    } catch (error) {
        // Storage can be unavailable (private mode); fall back to today.
    }

    return 'today';
}

const period = ref<OwnerPeriod>(initialPeriod());
const metrics = ref<OwnerMetrics | null>(null);
const loading = ref(false);
const pullDistance = ref(0);
const isOnline = ref(navigator.onLine);

let refreshTimer: number | undefined;
let requestSequence = 0;
let touchStartY: number | null = null;

const storeName = computed(() => metrics.value?.store_name || props.config.storeName);
const hasData = computed(() => metrics.value !== null);
const isOfflineData = computed(() => metrics.value?.offline === true || !isOnline.value);
const timelineMax = computed(() => Math.max(1, ...(metrics.value?.timeline.values ?? [0])));
const paymentsTotal = computed(() => (metrics.value?.payments ?? []).reduce((sum, payment) => sum + payment.total, 0));
const openRegisters = computed(() => (metrics.value?.registers ?? []).filter((register) => register.is_open).length);
const currentHour = computed(() => {
    const generatedAt = metrics.value?.generated_at;

    return generatedAt ? parseInt(generatedAt.substring(11, 13), 10) : -1;
});

const kpis = computed(() => {
    if (!metrics.value) {
        return [];
    }

    const { summary, comparison } = metrics.value;

    return [
        { key: 'sales', label: __m('Sales', 'MplOwnerApp'), value: money(summary.sales), change: comparison.sales, icon: 'la-coins' },
        { key: 'profit', label: __m('Gross profit', 'MplOwnerApp'), value: money(summary.profit), change: comparison.profit, icon: 'la-chart-line' },
        { key: 'orders', label: __m('Orders', 'MplOwnerApp'), value: number(summary.orders), change: comparison.orders, icon: 'la-receipt' },
        { key: 'average_ticket', label: __m('Avg ticket', 'MplOwnerApp'), value: money(summary.average_ticket), change: comparison.average_ticket, icon: 'la-shopping-basket' },
    ];
});

const comparisonLabel = computed(() => {
    switch (period.value) {
        case 'today': return __m('vs yesterday', 'MplOwnerApp');
        case 'yesterday': return __m('vs the day before', 'MplOwnerApp');
        case 'month': return __m('vs last month', 'MplOwnerApp');
        default: return __m('vs previous period', 'MplOwnerApp');
    }
});

const timelineTitle = computed(() => metrics.value?.timeline.granularity === 'hour'
    ? __m('Sales by hour', 'MplOwnerApp')
    : __m('Sales by day', 'MplOwnerApp'));

function money(value: number): string {
    return typeof nsCurrency === 'function' ? nsCurrency(value) : String(value);
}

function number(value: number): string {
    try {
        return new Intl.NumberFormat(props.config.language || undefined, { maximumFractionDigits: 2 }).format(value);
    } catch (error) {
        return String(value);
    }
}

function changeText(change: number | null): string {
    if (change === null) {
        return __m('New', 'MplOwnerApp');
    }

    const arrow = change > 0 ? '▲' : change < 0 ? '▼' : '•';

    return `${arrow} ${number(Math.abs(change))}%`;
}

function changeClass(change: number | null): string {
    if (change === null || change > 0) {
        return 'oa:text-success-tertiary';
    }

    if (change < 0) {
        return 'oa:text-error-tertiary';
    }

    return 'oa:text-fontcolor-soft';
}

function timeOf(dateTime: string): string {
    return dateTime ? dateTime.substring(11, 16) : '';
}

function shortDate(dateTime: string): string {
    if (!dateTime) {
        return '';
    }

    const sameDay = metrics.value?.generated_at?.substring(0, 10) === dateTime.substring(0, 10);

    return sameDay ? timeOf(dateTime) : `${dateTime.substring(5, 10)} ${timeOf(dateTime)}`;
}

function paymentStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        paid: __('Paid'),
        partially_paid: __('Partially Paid'),
        unpaid: __('Unpaid'),
        hold: __('Hold'),
        order_void: __('Void'),
        refunded: __('Refunded'),
        partially_refunded: __('Partially Refunded'),
    };

    return labels[status] ?? status;
}

function paymentStatusClass(status: string): string {
    switch (status) {
        case 'paid': return 'oa:bg-success-primary oa:text-white';
        case 'partially_paid': return 'oa:bg-warning-primary oa:text-white';
        case 'refunded':
        case 'partially_refunded':
        case 'order_void': return 'oa:bg-error-primary oa:text-white';
        default: return 'oa:bg-box-edge oa:text-fontcolor';
    }
}

function barHeight(value: number): number {
    return value > 0 ? Math.max(4, Math.round((value / timelineMax.value) * 100)) : 2;
}

function timelineTick(label: string, index: number, total: number): string {
    if (metrics.value?.timeline.granularity === 'hour') {
        return index % 6 === 0 || index === total - 1 ? label : '';
    }

    const step = Math.max(1, Math.ceil(total / 6));

    return index % step === 0 || index === total - 1 ? label.substring(5) : '';
}

function messageFrom(error: any): string {
    if (typeof error?.message === 'string' && error.message.length > 0) {
        return error.message;
    }

    if (error?.errors && typeof error.errors === 'object') {
        return Object.values(error.errors).flat().join('\n');
    }

    return __m('Unable to load the owner dashboard. Check your connection.', 'MplOwnerApp');
}

function request(url: string): Promise<OwnerMetrics> {
    return new Promise((resolve, reject) => {
        nsHttpClient.get(url).subscribe({ next: resolve, error: reject });
    });
}

async function load(): Promise<void> {
    const sequence = ++requestSequence;
    const query = new URLSearchParams({ period: period.value });

    loading.value = true;

    try {
        const result = await request(`${props.config.apiUrl}?${query.toString()}`);

        if (sequence === requestSequence) {
            metrics.value = result;
        }
    } catch (error) {
        if (sequence === requestSequence) {
            nsSnackBar.error(messageFrom(error));
        }
    } finally {
        if (sequence === requestSequence) {
            loading.value = false;
        }
    }
}

function selectPeriod(value: OwnerPeriod): void {
    if (period.value === value) {
        return;
    }

    period.value = value;

    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch (error) {
        // Preference persistence is optional.
    }

    load();
    scheduleRefresh();
}

function refresh(): void {
    load();
    scheduleRefresh();
}

function scheduleRefresh(): void {
    window.clearInterval(refreshTimer);

    refreshTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            load();
        }
    }, Math.max(15, props.config.refreshInterval || 60) * 1000);
}

function onVisibilityChange(): void {
    if (document.visibilityState === 'visible') {
        refresh();
    }
}

function onConnectivityChange(): void {
    isOnline.value = navigator.onLine;

    if (isOnline.value) {
        refresh();
    }
}

/**
 * Lightweight pull-to-refresh: pulling down from the very top of the page.
 */
function onTouchStart(event: TouchEvent): void {
    touchStartY = window.scrollY <= 0 ? event.touches[0].clientY : null;
}

function onTouchMove(event: TouchEvent): void {
    if (touchStartY === null) {
        return;
    }

    const distance = event.touches[0].clientY - touchStartY;
    pullDistance.value = distance > 0 ? Math.min(distance * 0.5, 80) : 0;
}

function onTouchEnd(): void {
    if (pullDistance.value >= 60 && !loading.value) {
        refresh();
    }

    pullDistance.value = 0;
    touchStartY = null;
}

onMounted(() => {
    load();
    scheduleRefresh();
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('online', onConnectivityChange);
    window.addEventListener('offline', onConnectivityChange);
});

onBeforeUnmount(() => {
    window.clearInterval(refreshTimer);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('online', onConnectivityChange);
    window.removeEventListener('offline', onConnectivityChange);
});
</script>

<template>
    <div
        class="oa:mx-auto oa:w-full oa:max-w-xl oa:min-h-screen oa:text-fontcolor"
        @touchstart.passive="onTouchStart"
        @touchmove.passive="onTouchMove"
        @touchend="onTouchEnd"
        @touchcancel="onTouchEnd"
    >
        <!-- Pull-to-refresh indicator -->
        <div
            class="oa:flex oa:items-end oa:justify-center oa:overflow-hidden oa:transition-all oa:duration-150"
            :style="{ height: pullDistance + 'px' }"
            aria-hidden="true"
        >
            <i class="las la-sync oa:text-2xl oa:text-fontcolor-soft" :class="{ 'oa:animate-spin': pullDistance >= 60 }"></i>
        </div>

        <!-- Header -->
        <header class="mplowner-safe-top oa:sticky oa:top-0 oa:z-10 oa:bg-surface oa:px-4 oa:pb-2">
            <div class="oa:flex oa:items-center oa:justify-between oa:gap-3">
                <div class="oa:min-w-0">
                    <h1 class="oa:truncate oa:text-xl oa:font-bold oa:text-fontcolor">{{ storeName }}</h1>
                    <p class="oa:flex oa:items-center oa:gap-1 oa:text-xs oa:text-fontcolor-soft">
                        <span v-if="isOfflineData" class="oa:inline-flex oa:items-center oa:gap-1 oa:rounded-full oa:bg-warning-primary oa:px-2 oa:py-0.5 oa:text-white">
                            <i class="las la-wifi"></i>{{ __m('Offline', 'MplOwnerApp') }}
                        </span>
                        <span v-if="metrics">{{ __m('Updated', 'MplOwnerApp') }} {{ timeOf(metrics.generated_at) }}</span>
                        <span v-else>{{ __m('Loading…', 'MplOwnerApp') }}</span>
                    </p>
                </div>
                <div class="oa:flex oa:shrink-0 oa:items-center oa:gap-2">
                    <button
                        type="button"
                        class="oa:flex oa:h-10 oa:w-10 oa:items-center oa:justify-center oa:rounded-full oa:border oa:border-box-edge oa:bg-box-background oa:text-fontcolor oa:hover:bg-box-elevation-hover"
                        :aria-label="__m('Refresh', 'MplOwnerApp')"
                        :aria-busy="loading"
                        @click="refresh"
                    >
                        <i class="las la-sync oa:text-xl" :class="{ 'oa:animate-spin': loading }"></i>
                    </button>
                    <a
                        :href="config.dashboardUrl"
                        class="oa:flex oa:h-10 oa:w-10 oa:items-center oa:justify-center oa:rounded-full oa:border oa:border-box-edge oa:bg-box-background oa:text-fontcolor oa:hover:bg-box-elevation-hover"
                        :aria-label="__m('Open full dashboard', 'MplOwnerApp')"
                    >
                        <i class="las la-th-large oa:text-xl"></i>
                    </a>
                </div>
            </div>

            <!-- Period chips -->
            <nav class="mplowner-chips oa:-mx-4 oa:mt-3 oa:flex oa:gap-2 oa:overflow-x-auto oa:px-4" :aria-label="__m('Period', 'MplOwnerApp')">
                <button
                    v-for="item in periods"
                    :key="item.value"
                    type="button"
                    class="oa:shrink-0 oa:rounded-full oa:border oa:px-4 oa:py-1.5 oa:text-sm oa:font-medium oa:transition-colors"
                    :class="period === item.value
                        ? 'oa:border-info-secondary oa:bg-info-secondary oa:text-white'
                        : 'oa:border-box-edge oa:bg-box-background oa:text-fontcolor oa:hover:bg-box-elevation-hover'"
                    :aria-pressed="period === item.value"
                    @click="selectPeriod(item.value)"
                >
                    {{ item.label }}
                </button>
            </nav>
        </header>

        <main class="mplowner-safe-bottom oa:flex oa:flex-col oa:gap-4 oa:px-4 oa:pt-2">
            <!-- Skeleton (first load only) -->
            <template v-if="!hasData && loading">
                <div class="oa:grid oa:grid-cols-2 oa:gap-3" aria-hidden="true">
                    <div v-for="index in 4" :key="index" class="mplowner-skeleton oa:h-24 oa:rounded-xl"></div>
                </div>
                <div class="mplowner-skeleton oa:h-40 oa:rounded-xl" aria-hidden="true"></div>
                <div class="mplowner-skeleton oa:h-32 oa:rounded-xl" aria-hidden="true"></div>
                <div class="mplowner-skeleton oa:h-48 oa:rounded-xl" aria-hidden="true"></div>
            </template>

            <!-- Initial load failed: keep a stable region with a retry control (the snackbar explains the error). -->
            <div v-else-if="!hasData" class="oa:flex oa:min-h-96 oa:flex-col oa:items-center oa:justify-center oa:gap-3 oa:text-center">
                <i class="las la-cloud oa:text-5xl oa:text-fontcolor-soft"></i>
                <p class="oa:text-sm oa:text-fontcolor-soft">{{ __m('The dashboard could not be loaded yet.', 'MplOwnerApp') }}</p>
                <button
                    type="button"
                    class="oa:rounded-full oa:bg-info-secondary oa:px-5 oa:py-2 oa:text-sm oa:font-medium oa:text-white oa:hover:bg-info-primary"
                    @click="refresh"
                >
                    {{ __m('Try again', 'MplOwnerApp') }}
                </button>
            </div>

            <template v-else-if="metrics">
                <!-- KPI cards -->
                <section class="oa:grid oa:grid-cols-2 oa:gap-3" :aria-label="__m('Key figures', 'MplOwnerApp')">
                    <article
                        v-for="kpi in kpis"
                        :key="kpi.key"
                        class="oa:flex oa:min-w-0 oa:flex-col oa:gap-1 oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3"
                    >
                        <div class="oa:flex oa:items-center oa:justify-between oa:gap-2 oa:text-xs oa:text-fontcolor-soft">
                            <span class="oa:truncate">{{ kpi.label }}</span>
                            <i class="las oa:text-base" :class="kpi.icon"></i>
                        </div>
                        <div class="oa:truncate oa:text-lg oa:font-bold oa:text-fontcolor" dir="ltr">{{ kpi.value }}</div>
                        <div class="oa:flex oa:flex-wrap oa:items-center oa:gap-1 oa:text-xs">
                            <span class="oa:font-semibold" :class="changeClass(kpi.change)" dir="ltr">{{ changeText(kpi.change) }}</span>
                            <span class="oa:text-fontcolor-soft">{{ comparisonLabel }}</span>
                        </div>
                    </article>
                </section>

                <div class="oa:flex oa:items-center oa:justify-between oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:px-3 oa:py-2 oa:text-sm">
                    <span class="oa:text-fontcolor-soft"><i class="las la-undo"></i> {{ __m('Refunds', 'MplOwnerApp') }}</span>
                    <span class="oa:font-semibold" :class="metrics.summary.refunds > 0 ? 'oa:text-error-tertiary' : 'oa:text-fontcolor'" dir="ltr">{{ money(metrics.summary.refunds) }}</span>
                </div>

                <!-- Sales timeline -->
                <section class="oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3">
                    <h2 class="oa:mb-2 oa:text-sm oa:font-semibold oa:text-fontcolor">{{ timelineTitle }}</h2>
                    <div class="oa:flex oa:h-28 oa:items-end oa:gap-0.5" dir="ltr" role="img" :aria-label="timelineTitle">
                        <div
                            v-for="(value, index) in metrics.timeline.values"
                            :key="index"
                            class="oa:flex-1 oa:rounded-t-sm"
                            :class="metrics.timeline.granularity === 'hour' && index === currentHour && period === 'today' ? 'oa:bg-info-secondary' : 'oa:bg-info-primary'"
                            :style="{ height: barHeight(value) + '%', opacity: value > 0 ? 1 : 0.35 }"
                            :title="`${metrics.timeline.labels[index]} · ${money(value)}`"
                        ></div>
                    </div>
                    <div class="oa:mt-1 oa:flex oa:gap-0.5 oa:text-[10px] oa:text-fontcolor-soft" dir="ltr" aria-hidden="true">
                        <span v-for="(label, index) in metrics.timeline.labels" :key="index" class="oa:flex-1 oa:overflow-visible oa:whitespace-nowrap">
                            {{ timelineTick(label, index, metrics.timeline.labels.length) }}
                        </span>
                    </div>
                </section>

                <!-- Payment types -->
                <section class="oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3">
                    <h2 class="oa:mb-2 oa:text-sm oa:font-semibold oa:text-fontcolor">{{ __m('Payments', 'MplOwnerApp') }}</h2>
                    <p v-if="metrics.payments.length === 0" class="oa:text-sm oa:text-fontcolor-soft">{{ __m('No payment for this period.', 'MplOwnerApp') }}</p>
                    <ul v-else class="oa:flex oa:flex-col oa:gap-2">
                        <li v-for="payment in metrics.payments" :key="payment.identifier">
                            <div class="oa:flex oa:items-center oa:justify-between oa:gap-2 oa:text-sm">
                                <span class="oa:truncate">{{ payment.label }}</span>
                                <span class="oa:font-semibold" dir="ltr">{{ money(payment.total) }}</span>
                            </div>
                            <div class="oa:mt-1 oa:h-1.5 oa:overflow-hidden oa:rounded-full oa:bg-box-edge">
                                <div class="oa:h-full oa:rounded-full oa:bg-info-primary" :style="{ width: (paymentsTotal > 0 ? Math.round((payment.total / paymentsTotal) * 100) : 0) + '%' }"></div>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Top products -->
                <section class="oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3">
                    <h2 class="oa:mb-2 oa:text-sm oa:font-semibold oa:text-fontcolor">{{ __m('Top products', 'MplOwnerApp') }}</h2>
                    <p v-if="metrics.top_products.length === 0" class="oa:text-sm oa:text-fontcolor-soft">{{ __m('No product sold for this period.', 'MplOwnerApp') }}</p>
                    <ol v-else class="oa:flex oa:flex-col oa:divide-y oa:divide-box-edge">
                        <li v-for="(product, index) in metrics.top_products" :key="product.product_id + '-' + product.name" class="oa:flex oa:items-center oa:gap-3 oa:py-2">
                            <span class="oa:flex oa:h-6 oa:w-6 oa:shrink-0 oa:items-center oa:justify-center oa:rounded-full oa:bg-info-primary oa:text-xs oa:font-bold oa:text-white">{{ index + 1 }}</span>
                            <div class="oa:min-w-0 oa:flex-1">
                                <div class="oa:truncate oa:text-sm">{{ product.name }}</div>
                                <div class="oa:text-xs oa:text-fontcolor-soft">{{ __m('Qty', 'MplOwnerApp') }} {{ number(product.quantity) }}</div>
                            </div>
                            <span class="oa:text-sm oa:font-semibold" dir="ltr">{{ money(product.revenue) }}</span>
                        </li>
                    </ol>
                </section>

                <!-- Low stock -->
                <section class="oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3">
                    <h2 class="oa:mb-2 oa:flex oa:items-center oa:gap-2 oa:text-sm oa:font-semibold oa:text-fontcolor">
                        {{ __m('Low stock', 'MplOwnerApp') }}
                        <span v-if="metrics.low_stock.length > 0" class="oa:rounded-full oa:bg-error-primary oa:px-2 oa:text-xs oa:text-white">{{ metrics.low_stock.length }}</span>
                    </h2>
                    <p v-if="metrics.low_stock.length === 0" class="oa:text-sm oa:text-fontcolor-soft">{{ __m('All products are above their alert threshold.', 'MplOwnerApp') }}</p>
                    <ul v-else class="oa:flex oa:flex-col oa:divide-y oa:divide-box-edge">
                        <li v-for="item in metrics.low_stock" :key="item.unit_quantity_id" class="oa:flex oa:items-center oa:justify-between oa:gap-3 oa:py-2">
                            <div class="oa:min-w-0">
                                <div class="oa:truncate oa:text-sm">{{ item.name }}</div>
                                <div class="oa:text-xs oa:text-fontcolor-soft">{{ item.unit }} · {{ __m('Alert at', 'MplOwnerApp') }} {{ number(item.low_quantity) }}</div>
                            </div>
                            <span class="oa:shrink-0 oa:rounded-full oa:bg-error-secondary oa:px-2.5 oa:py-0.5 oa:text-xs oa:font-bold oa:text-white" dir="ltr">{{ number(item.quantity) }}</span>
                        </li>
                    </ul>
                </section>

                <!-- Registers -->
                <section class="oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3">
                    <h2 class="oa:mb-2 oa:flex oa:items-center oa:justify-between oa:text-sm oa:font-semibold oa:text-fontcolor">
                        <span>{{ __m('Cash registers', 'MplOwnerApp') }}</span>
                        <span v-if="metrics.registers.length > 0" class="oa:text-xs oa:font-normal oa:text-fontcolor-soft">{{ openRegisters }} / {{ metrics.registers.length }} {{ __m('open', 'MplOwnerApp') }}</span>
                    </h2>
                    <p v-if="metrics.registers.length === 0" class="oa:text-sm oa:text-fontcolor-soft">{{ __m('No cash register configured.', 'MplOwnerApp') }}</p>
                    <ul v-else class="oa:flex oa:flex-col oa:divide-y oa:divide-box-edge">
                        <li v-for="register in metrics.registers" :key="register.id" class="oa:flex oa:items-center oa:justify-between oa:gap-3 oa:py-2">
                            <div class="oa:min-w-0">
                                <div class="oa:truncate oa:text-sm">{{ register.name }}</div>
                                <div class="oa:text-xs oa:text-fontcolor-soft">
                                    <span :class="register.is_open ? 'oa:text-success-tertiary' : 'oa:text-fontcolor-soft'">
                                        ● {{ register.is_open ? __m('Open', 'MplOwnerApp') : __m('Closed', 'MplOwnerApp') }}
                                    </span>
                                    <span v-if="register.cashier"> · {{ register.cashier }}</span>
                                </div>
                            </div>
                            <span class="oa:text-sm oa:font-semibold" dir="ltr">{{ money(register.balance) }}</span>
                        </li>
                    </ul>
                </section>

                <!-- Recent orders -->
                <section class="oa:rounded-xl oa:border oa:border-box-edge oa:bg-box-background oa:p-3">
                    <h2 class="oa:mb-2 oa:text-sm oa:font-semibold oa:text-fontcolor">{{ __m('Recent orders', 'MplOwnerApp') }}</h2>
                    <p v-if="metrics.recent_orders.length === 0" class="oa:text-sm oa:text-fontcolor-soft">{{ __m('No order yet.', 'MplOwnerApp') }}</p>
                    <ul v-else class="oa:flex oa:flex-col oa:divide-y oa:divide-box-edge">
                        <li v-for="order in metrics.recent_orders" :key="order.id" class="oa:flex oa:items-center oa:justify-between oa:gap-3 oa:py-2">
                            <div class="oa:min-w-0">
                                <div class="oa:truncate oa:text-sm oa:font-medium">{{ order.code }}</div>
                                <div class="oa:truncate oa:text-xs oa:text-fontcolor-soft">{{ shortDate(order.created_at) }}<span v-if="order.customer"> · {{ order.customer }}</span></div>
                            </div>
                            <div class="oa:flex oa:shrink-0 oa:flex-col oa:items-end oa:gap-1">
                                <span class="oa:text-sm oa:font-semibold" dir="ltr">{{ money(order.total) }}</span>
                                <span class="oa:rounded-full oa:px-2 oa:text-[10px] oa:font-medium" :class="paymentStatusClass(order.payment_status)">{{ paymentStatusLabel(order.payment_status) }}</span>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </main>
    </div>
</template>
