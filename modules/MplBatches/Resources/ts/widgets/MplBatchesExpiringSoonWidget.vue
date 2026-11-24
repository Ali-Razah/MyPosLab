<template>
    <div class="ns-box mplb:flex mplb:h-full mplb:flex-auto mplb:flex-col mplb:overflow-hidden mplb:rounded-lg mplb:shadow">
        <div class="ns-box-header mplb:flex mplb:items-center mplb:justify-between mplb:border-b mplb:p-2">
            <h5 class="mplb:font-semibold mplb:text-fontcolor">{{ __m( 'Expiring Soon', 'MplBatches' ) }}</h5>
            <div class="mplb:flex mplb:items-center mplb:gap-1">
                <ns-widget-layout-selector :widget="widget"></ns-widget-layout-selector>
                <ns-icon-button class="widget-handle" className="la-expand-arrows-alt"></ns-icon-button>
                <ns-close-button @click="$emit( 'onRemove' )"></ns-close-button>
            </div>
        </div>
        <div class="mplb:flex mplb:min-h-40 mplb:flex-auto mplb:flex-col">
            <div v-if="loading" class="mplb:flex mplb:flex-auto mplb:items-center mplb:justify-center">
                <ns-spinner size="8" border="4"></ns-spinner>
            </div>
            <template v-else>
                <div class="mplb:grid mplb:grid-cols-3 mplb:gap-2 mplb:p-2">
                    <a :href="reportUrl" class="mplb:rounded mplb:border mplb:border-box-edge mplb:p-2 mplb:text-center">
                        <div class="mplb:text-2xl mplb:font-bold mplb:text-error-primary">{{ summary.expired }}</div>
                        <div class="mplb:text-xs mplb:text-fontcolor-soft">{{ __m( 'Expired', 'MplBatches' ) }}</div>
                    </a>
                    <a :href="reportUrl" class="mplb:rounded mplb:border mplb:border-box-edge mplb:p-2 mplb:text-center">
                        <div class="mplb:text-2xl mplb:font-bold mplb:text-warning-primary">{{ summary.critical }}</div>
                        <div class="mplb:text-xs mplb:text-fontcolor-soft">{{ __m( 'Critical', 'MplBatches' ) }}</div>
                    </a>
                    <a :href="reportUrl" class="mplb:rounded mplb:border mplb:border-box-edge mplb:p-2 mplb:text-center">
                        <div class="mplb:text-2xl mplb:font-bold mplb:text-info-primary">{{ summary.soon }}</div>
                        <div class="mplb:text-xs mplb:text-fontcolor-soft">{{ __m( 'Soon', 'MplBatches' ) }}</div>
                    </a>
                </div>
                <ul v-if="summary.top.length > 0" class="mplb:flex-auto mplb:overflow-y-auto">
                    <li v-for="batch of summary.top" :key="batch.id" class="mplb:flex mplb:justify-between mplb:gap-2 mplb:border-t mplb:border-box-edge mplb:px-2 mplb:py-1 mplb:text-sm">
                        <div class="mplb:min-w-0">
                            <div class="mplb:truncate mplb:text-fontcolor">{{ batch.product }}</div>
                            <div class="mplb:text-xs mplb:text-fontcolor-soft">{{ batch.batch_number }} · {{ batch.expiry_date }}</div>
                        </div>
                        <div class="mplb:text-right mplb:text-xs mplb:whitespace-nowrap" :class="batch.days_left <= 0 ? 'mplb:text-error-primary' : 'mplb:text-fontcolor-soft'">
                            <div>{{ daysLabel( batch.days_left ) }}</div>
                            <div>{{ __m( 'Qty', 'MplBatches' ) }}: {{ batch.quantity_remaining }}</div>
                        </div>
                    </li>
                </ul>
                <div v-else class="mplb:flex mplb:flex-auto mplb:items-center mplb:justify-center mplb:p-4 mplb:text-center mplb:text-sm mplb:text-fontcolor-soft">
                    {{ __m( 'No batch expires within the alert window.', 'MplBatches' ) }}
                </div>
            </template>
        </div>
    </div>
</template>
<script>
import { __m } from '../i18n';

export default {
    name: 'mpl-batches-expiring-soon-widget',
    props: [ 'widget' ],
    emits: [ 'onRemove' ],
    data() {
        return {
            loading: true,
            reportUrl: this.widget?.data?.reportUrl || '#',
            summary: { expired: 0, critical: 0, soon: 0, top: [] },
        };
    },
    methods: {
        __m,
        daysLabel( days ) {
            if ( days === null || days === undefined ) {
                return '';
            }

            return days <= 0 ? __m( 'Expired', 'MplBatches' ) : days + ' ' + __m( 'days left', 'MplBatches' );
        },
        load() {
            nsHttpClient.get( '/api/mplbatches/summary' ).subscribe({
                next: summary => {
                    this.summary = summary;
                    this.loading = false;
                },
                error: error => {
                    this.loading = false;
                    nsSnackBar.error( error.message || __m( 'Unable to load the expiring batches.', 'MplBatches' ) );
                },
            });
        },
    },
    mounted() {
        this.load();
    },
};
</script>
