/**
 * POS-only entry: publishes the live cart to the customer display window and
 * adds the "Customer display" header button.
 *
 * Loaded through RenderFooterEvent on the POS route only. It executes before
 * pos-init.ts, so hooks are registered immediately (nsHooks exists) while the
 * POS subscriptions wait for DOMContentLoaded (window.POS exists by then).
 */
import { markRaw } from 'vue';
import { DisplayChannel } from './shared/channel.ts';
import { buildSnapshot, createThrottle, snapshotFingerprint } from './shared/snapshot.ts';
import type { DisplaySnapshot, SnapshotOptions } from './shared/snapshot.ts';
import { createPosHeaderButton } from './components/PosHeaderButton';

declare const nsHooks: any;

type PosConfig = {
    channel: string;
    windowName: string;
    displayUrl: string;
    canOpen: boolean;
    defaultCustomerId: number | string | false | null;
    throttleMs: number;
};

const HOOK_NAMESPACE = 'mpl-customer-display';
const config: PosConfig | undefined = (window as any).MplCustomerDisplayPos;

class CustomerDisplayPublisher {
    private channel: DisplayChannel;
    private lastFingerprint = '';
    private pendingPaid: DisplaySnapshot | null = null;
    private awaitingSubmit = false;
    private readonly schedule: ReturnType<typeof createThrottle>;
    private readonly pos: any;

    constructor(pos: any, posConfig: PosConfig) {
        this.pos = pos;
        this.channel = new DisplayChannel(posConfig.channel);
        this.schedule = createThrottle(() => this.publishCurrent(), Math.max(50, posConfig.throttleMs || 150));
    }

    start(): void {
        this.pos.order.subscribe(() => this.schedule());
        this.pos.products.subscribe(() => this.schedule());

        this.channel.onMessage((message) => {
            if (message.type === 'hello') {
                this.publishCurrent(true);
            }
        });

        nsHooks.addAction('ns-after-cart-reset', `${HOOK_NAMESPACE}/reset`, () => {
            if (this.awaitingSubmit) {
                return;
            }

            this.lastFingerprint = '';
            this.channel.post({ type: 'reset' });
        }, 30);

        nsHooks.addAction('ns-order-before-submit', `${HOOK_NAMESPACE}/before-submit`, (order: any) => {
            if (!order || order.payment_status === 'hold') {
                return;
            }

            this.pendingPaid = buildSnapshot(order, Array.isArray(order.products) ? order.products : this.pos.products.getValue(), this.options());
            this.awaitingSubmit = true;
        });

        nsHooks.addAction('ns-order-submit-successful', `${HOOK_NAMESPACE}/paid`, () => {
            if (this.pendingPaid) {
                this.channel.post({ type: 'paid', snapshot: this.pendingPaid });
            }

            this.pendingPaid = null;
            this.awaitingSubmit = false;
            this.lastFingerprint = '';
            this.schedule();
        });

        nsHooks.addAction('ns-order-submit-failed', `${HOOK_NAMESPACE}/failed`, () => {
            this.pendingPaid = null;
            this.awaitingSubmit = false;
            this.publishCurrent(true);
        });

        window.addEventListener('pagehide', () => this.channel.post({ type: 'reset' }));

        this.publishCurrent(true);
    }

    private options(): SnapshotOptions {
        const posOptions = this.pos.options?.getValue?.() ?? {};

        return {
            vatMode: posOptions.ns_pos_vat ?? 'disabled',
            preferredPrice: posOptions.ns_pos_prefered_price ?? 'net_prices',
            defaultCustomerId: config?.defaultCustomerId ?? posOptions.ns_customers_default ?? null,
        };
    }

    private publishCurrent(force = false): void {
        // While an order is being submitted, POS.reset() empties the cart before
        // the success hook fires: hold back that transient empty state.
        if (this.awaitingSubmit) {
            return;
        }

        const snapshot = buildSnapshot(this.pos.order.getValue(), this.pos.products.getValue(), this.options());
        const fingerprint = snapshotFingerprint(snapshot);

        if (!force && fingerprint === this.lastFingerprint) {
            return;
        }

        this.lastFingerprint = fingerprint;
        this.channel.post({ type: 'snapshot', snapshot });
    }
}

if (config) {
    if (config.canOpen) {
        const button = markRaw(createPosHeaderButton(config));

        nsHooks.addAction('ns-pos-header', `${HOOK_NAMESPACE}/header-button`, (header: any) => {
            header.buttons.MplcdCustomerDisplayButton = button;
        });
    }

    const boot = () => {
        const pos = (window as any).POS;

        if (!pos?.order?.subscribe || !pos?.products?.subscribe) {
            console.warn('[MplCustomerDisplay] POS is not available; the customer display will not receive updates.');
            return;
        }

        const publisher = new CustomerDisplayPublisher(pos, config);
        publisher.start();
        (window as any).MplCustomerDisplayPublisher = publisher;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
