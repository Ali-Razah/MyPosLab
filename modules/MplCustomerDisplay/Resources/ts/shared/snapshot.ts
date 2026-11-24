/**
 * Pure helpers shared by the POS publisher and the customer display.
 *
 * Keep this file free of DOM / NexoPOS globals and use only erasable
 * TypeScript syntax so it can be unit-tested with plain Node
 * (`node --test modules/MplCustomerDisplay/Tests/js`).
 */

export const PROTOCOL_VERSION = 1;

export type DisplayLine = {
    key: string;
    name: string;
    unit: string | null;
    quantity: number;
    unitPrice: number;
    discount: number;
    discountLabel: string | null;
    lineExtra: number;
    total: number;
};

export type DisplayTax = {
    amount: number;
    included: boolean;
};

export type DisplaySnapshot = {
    v: number;
    state: 'cart' | 'idle';
    lines: DisplayLine[];
    itemsCount: number;
    subtotal: number;
    discount: number;
    shipping: number;
    tax: DisplayTax | null;
    total: number;
    paid: number;
    change: number;
    due: number;
    customerName: string | null;
};

export type DisplayMessage =
    | { type: 'snapshot'; snapshot: DisplaySnapshot; id: string; sentAt: number }
    | { type: 'paid'; snapshot: DisplaySnapshot; id: string; sentAt: number }
    | { type: 'reset'; id: string; sentAt: number }
    | { type: 'hello'; id: string; sentAt: number };

export type SnapshotOptions = {
    /** POS option `ns_pos_vat`: disabled | products_vat | flat_vat | variable_vat */
    vatMode?: string | null;
    /** POS option `ns_pos_prefered_price`: net_prices | gross_prices */
    preferredPrice?: string | null;
    /** Default (walk-in) customer id: its name is never shown. */
    defaultCustomerId?: number | string | false | null;
};

const MAX_NAME_LENGTH = 80;
const MAX_CUSTOMER_LENGTH = 40;

export function toAmount(value: unknown): number {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return 0;
    }

    // Remove floating-point noise (0.1 + 0.2) without changing visible precision.
    return Math.round(amount * 1e6) / 1e6;
}

function truncate(value: unknown, length: number): string {
    const text = typeof value === 'string' ? value.trim() : value == null ? '' : String(value).trim();

    return text.length > length ? `${text.slice(0, length - 1)}…` : text;
}

function lineName(product: any): string {
    const name = product?.name ?? product?.product?.name ?? '';

    return truncate(name, MAX_NAME_LENGTH);
}

function buildLine(product: any, index: number): DisplayLine {
    const discount = toAmount(product?.discount);
    let discountLabel: string | null = null;

    if (discount > 0) {
        discountLabel = product?.discount_type === 'percentage' && toAmount(product?.discount_percentage) > 0
            ? `-${toAmount(product.discount_percentage)}%`
            : '';
    }

    return {
        key: `${product?.product_id ?? product?.id ?? 'p'}-${product?.unit_quantity_id ?? product?.unit_id ?? 'u'}-${index}`,
        name: lineName(product),
        unit: product?.unit_name ? truncate(product.unit_name, 24) : null,
        quantity: toAmount(product?.quantity),
        unitPrice: toAmount(product?.unit_price),
        discount,
        discountLabel,
        lineExtra: toAmount(product?.line_extra),
        total: toAmount(product?.total_price),
    };
}

function customerFirstName(order: any, defaultCustomerId: SnapshotOptions['defaultCustomerId']): string | null {
    const customer = order?.customer;

    if (!customer || typeof customer !== 'object') {
        return null;
    }

    if (
        defaultCustomerId !== undefined
        && defaultCustomerId !== null
        && defaultCustomerId !== false
        && String(customer.id) === String(defaultCustomerId)
    ) {
        return null;
    }

    const firstName = truncate(customer.first_name, MAX_CUSTOMER_LENGTH);

    return firstName.length > 0 ? firstName : null;
}

function computeTax(order: any, options: SnapshotOptions): DisplayTax | null {
    const vatMode = options.vatMode ?? 'disabled';

    if (!vatMode || vatMode === 'disabled') {
        return null;
    }

    if (vatMode === 'products_vat') {
        // The core POS folds product taxes into order.subtotal (net prices) or
        // they are embedded in the prices (gross prices); order.total never adds
        // them on top, so they are always part of the displayed subtotal/total.
        return {
            amount: toAmount(order?.products_tax_value),
            included: true,
        };
    }

    return {
        amount: toAmount(order?.tax_value),
        included: order?.tax_type === 'inclusive',
    };
}

/**
 * Build a compact, serializable snapshot of the live POS cart.
 * Only the customer first name is kept from personal data.
 */
export function buildSnapshot(order: any, products?: any[] | null, options: SnapshotOptions = {}): DisplaySnapshot {
    const source = Array.isArray(products) ? products : Array.isArray(order?.products) ? order.products : [];
    const lines = source
        .filter((product: any) => product && typeof product === 'object')
        .map((product: any, index: number) => buildLine(product, index));

    const total = toAmount(order?.total);
    const paid = toAmount(order?.tendered);

    return {
        v: PROTOCOL_VERSION,
        state: lines.length > 0 ? 'cart' : 'idle',
        lines,
        itemsCount: toAmount(lines.reduce((sum: number, line: DisplayLine) => sum + line.quantity, 0)),
        subtotal: toAmount(order?.subtotal),
        discount: toAmount(toAmount(order?.discount) + toAmount(order?.total_coupons)),
        shipping: toAmount(order?.shipping),
        tax: computeTax(order, options),
        total,
        paid,
        change: paid > total ? toAmount(paid - total) : 0,
        due: total > paid ? toAmount(total - paid) : 0,
        customerName: customerFirstName(order, options.defaultCustomerId),
    };
}

/**
 * Stable fingerprint used to skip publishing identical consecutive snapshots.
 */
export function snapshotFingerprint(snapshot: DisplaySnapshot): string {
    return JSON.stringify(snapshot);
}

let messageCounter = 0;

export function createMessageId(): string {
    messageCounter = (messageCounter + 1) % 1e9;

    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}-${messageCounter}`;
}

function isSnapshot(value: any): value is DisplaySnapshot {
    return !!value
        && typeof value === 'object'
        && value.v === PROTOCOL_VERSION
        && (value.state === 'cart' || value.state === 'idle')
        && Array.isArray(value.lines);
}

/**
 * Validate a message received from BroadcastChannel or the storage fallback.
 * Returns null for anything that does not match the protocol.
 */
export function parseMessage(raw: unknown): DisplayMessage | null {
    let value: any = raw;

    if (typeof raw === 'string') {
        try {
            value = JSON.parse(raw);
        } catch {
            return null;
        }
    }

    if (!value || typeof value !== 'object' || typeof value.type !== 'string' || typeof value.id !== 'string') {
        return null;
    }

    switch (value.type) {
        case 'snapshot':
        case 'paid':
            return isSnapshot(value.snapshot) ? value as DisplayMessage : null;
        case 'reset':
        case 'hello':
            return value as DisplayMessage;
        default:
            return null;
    }
}

/**
 * Leading + trailing throttle: the first call runs immediately, calls during
 * the wait window collapse into one trailing call.
 */
export function createThrottle(callback: () => void, wait: number): { (): void; cancel(): void } {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let pending = false;
    let last = 0;

    const run = () => {
        last = Date.now();
        pending = false;
        callback();
    };

    const throttled = () => {
        const remaining = wait - (Date.now() - last);

        if (remaining <= 0 && timer === null) {
            run();
            return;
        }

        pending = true;

        if (timer === null) {
            timer = setTimeout(() => {
                timer = null;

                if (pending) {
                    run();
                }
            }, Math.max(0, remaining));
        }
    };

    throttled.cancel = () => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        pending = false;
    };

    return throttled;
}
