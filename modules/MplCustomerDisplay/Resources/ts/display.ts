/**
 * Standalone customer display entry. The page has its own minimal layout
 * (no #dashboard-content), so mounting a root app here is allowed. It uses
 * the shared NexoPOS Vue runtime through defineNexoPOSModuleConfig.
 */
import { createApp } from 'vue';
import CustomerDisplay from './components/CustomerDisplay.vue';

const mount = () => {
    const root = document.getElementById('mplcd-display-root');

    if (!root) {
        return;
    }

    createApp(CustomerDisplay, {
        config: (window as any).MplCustomerDisplay ?? {},
    }).mount(root);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
} else {
    mount();
}
