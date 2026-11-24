/**
 * Pure tests for the snapshot builder (no dependencies).
 * Run: node --test modules/MplCustomerDisplay/Tests/js/
 * Requires Node >= 22.18 / 23.6 (native TypeScript type stripping).
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    buildSnapshot,
    createThrottle,
    parseMessage,
    PROTOCOL_VERSION,
} from '../../Resources/ts/shared/snapshot.ts';

const order = {
    subtotal: 47.5,
    discount: 2.5,
    total_coupons: 1,
    shipping: 0,
    tax_value: 2.1,
    products_tax_value: 0,
    tax_type: 'inclusive',
    total: 44,
    tendered: 50,
    customer: {
        id: 12,
        first_name: 'Fatima',
        last_name: 'Al Mansoori',
        email: 'fatima@example.test',
        phone: '+971500000000',
    },
};

const products = [
    {
        product_id: 1,
        unit_quantity_id: 4,
        name: 'Laban Up 1L',
        unit_name: 'Bottle',
        quantity: 2,
        unit_price: 7.5,
        discount: 1.5,
        discount_type: 'percentage',
        discount_percentage: 10,
        total_price: 13.5,
        $quantities: () => ({}),
    },
    {
        product_id: 2,
        unit_quantity_id: 5,
        name: 'Basmati Rice 5kg',
        quantity: 1,
        unit_price: 34,
        discount: 0,
        total_price: 34,
    },
];

test('builds a compact cart snapshot', () => {
    const snapshot = buildSnapshot(order, products, { vatMode: 'flat_vat', defaultCustomerId: 1 });

    assert.equal(snapshot.v, PROTOCOL_VERSION);
    assert.equal(snapshot.state, 'cart');
    assert.equal(snapshot.lines.length, 2);
    assert.equal(snapshot.itemsCount, 3);
    assert.deepEqual(snapshot.lines[0], {
        key: '1-4-0',
        name: 'Laban Up 1L',
        unit: 'Bottle',
        quantity: 2,
        unitPrice: 7.5,
        discount: 1.5,
        discountLabel: '-10%',
        lineExtra: 0,
        total: 13.5,
    });
    assert.equal(snapshot.lines[1].discountLabel, null);
    assert.equal(snapshot.discount, 3.5);
    assert.deepEqual(snapshot.tax, { amount: 2.1, included: true });
    assert.equal(snapshot.total, 44);
    assert.equal(snapshot.paid, 50);
    assert.equal(snapshot.change, 6);
    assert.equal(snapshot.due, 0);
});

test('keeps only the customer first name and is serializable', () => {
    const snapshot = buildSnapshot(order, products, { vatMode: 'disabled' });
    const json = JSON.stringify(snapshot);

    assert.equal(snapshot.customerName, 'Fatima');
    assert.equal(snapshot.tax, null);
    assert.ok(!json.includes('Mansoori'));
    assert.ok(!json.includes('fatima@example.test'));
    assert.ok(!json.includes('+971'));
    assert.deepEqual(JSON.parse(json), snapshot);
});

test('hides the default walk-in customer and reports idle for an empty cart', () => {
    const snapshot = buildSnapshot({ ...order, total: 0, tendered: 0 }, [], { defaultCustomerId: '12' });

    assert.equal(snapshot.state, 'idle');
    assert.equal(snapshot.customerName, null);
    assert.equal(snapshot.lines.length, 0);
});

test('uses product taxes in products_vat mode and computes the amount due', () => {
    const snapshot = buildSnapshot(
        { ...order, products_tax_value: 4.2, tendered: 20 },
        products,
        { vatMode: 'products_vat', preferredPrice: 'net_prices' }
    );

    // POS.sumProductsTaxes() adds products_tax_value into order.subtotal for net
    // prices (and gross prices already embed it), and order.total never adds it
    // again: the VAT line is always "included" in products_vat mode.
    assert.deepEqual(snapshot.tax, { amount: 4.2, included: true });
    assert.equal(snapshot.change, 0);
    assert.equal(snapshot.due, 24);
});

test('products_vat tax is reported as included for gross prices too', () => {
    const snapshot = buildSnapshot(
        { ...order, products_tax_value: 4.2 },
        products,
        { vatMode: 'products_vat', preferredPrice: 'gross_prices' }
    );

    assert.deepEqual(snapshot.tax, { amount: 4.2, included: true });
});

test('falls back to order.products and tolerates garbage values', () => {
    const snapshot = buildSnapshot({ products: [{ name: 'X', quantity: 'abc', total_price: null }, null] }, null);

    assert.equal(snapshot.lines.length, 1);
    assert.equal(snapshot.lines[0].quantity, 0);
    assert.equal(snapshot.total, 0);
});

test('parseMessage validates the protocol', () => {
    const snapshot = buildSnapshot(order, products);

    assert.equal(parseMessage('not json'), null);
    assert.equal(parseMessage({ type: 'snapshot', id: 'a', snapshot: { v: 999 } }), null);
    assert.equal(parseMessage({ type: 'evil', id: 'a' }), null);
    assert.equal(parseMessage(JSON.stringify({ type: 'hello', id: 'h' })).type, 'hello');
    assert.equal(parseMessage({ type: 'paid', id: 'p', snapshot }).type, 'paid');
});

test('throttle runs leading and one trailing call', async () => {
    let calls = 0;
    const throttled = createThrottle(() => calls++, 30);

    throttled();
    throttled();
    throttled();
    assert.equal(calls, 1);

    await new Promise((resolve) => setTimeout(resolve, 60));
    assert.equal(calls, 2);
    throttled.cancel();
});
