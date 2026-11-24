export type OwnerPeriod = 'today' | 'yesterday' | '7d' | '30d' | 'month';

export interface OwnerSummary {
    sales: number;
    orders: number;
    average_ticket: number;
    cost: number;
    taxes: number;
    profit: number;
    refunds: number;
}

export interface OwnerMetrics {
    period: OwnerPeriod;
    store_name: string;
    generated_at: string;
    timezone: string;
    range: { start: string; end: string };
    previous_range: { start: string; end: string };
    summary: OwnerSummary;
    previous: OwnerSummary;
    comparison: Record<'sales' | 'orders' | 'average_ticket' | 'profit' | 'refunds', number | null>;
    timeline: { granularity: 'hour' | 'day'; labels: string[]; values: number[] };
    payments: Array<{ identifier: string; label: string; total: number }>;
    top_products: Array<{ product_id: number; name: string; quantity: number; revenue: number }>;
    low_stock: Array<{ unit_quantity_id: number; product_id: number; name: string; unit: string; quantity: number; low_quantity: number }>;
    registers: Array<{ id: number; name: string; status: string; is_open: boolean; balance: number; cashier: string | null }>;
    recent_orders: Array<{ id: number; code: string; total: number; payment_status: string; customer: string; created_at: string }>;
    offline?: boolean;
}

export interface OwnerConfig {
    apiUrl: string;
    pageUrl: string;
    swUrl: string;
    swScope: string;
    dashboardUrl: string;
    storeName: string;
    refreshInterval: number;
    language: string;
}
