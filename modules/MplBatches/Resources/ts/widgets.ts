import { defineAsyncComponent } from 'vue';

(window as any)['mplBatchesExpiringSoonWidget'] = defineAsyncComponent(
    () => import('./widgets/MplBatchesExpiringSoonWidget.vue')
);
