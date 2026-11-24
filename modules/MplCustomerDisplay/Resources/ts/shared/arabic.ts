/**
 * Fixed Arabic labels shown alongside the (translatable) English labels on the
 * customer display. They are intentionally not passed through __m: the screen
 * is bilingual for every customer regardless of the cashier's UI language.
 */
export const ARABIC_LABELS = {
    items: 'الأصناف',
    subtotal: 'المجموع الفرعي',
    discount: 'الخصم',
    shipping: 'التوصيل',
    vat: 'ضريبة القيمة المضافة',
    vatIncluded: 'شامل ضريبة القيمة المضافة',
    total: 'الإجمالي',
    paid: 'المدفوع',
    change: 'الباقي',
    due: 'المتبقي',
    customer: 'العميل',
    welcome: 'أهلاً وسهلاً',
    thankYou: 'شكراً لتسوقكم معنا',
    changeDue: 'المبلغ المتبقي لكم',
    totalPaid: 'إجمالي المدفوع',
    disabled: 'شاشة العميل غير مفعلة',
    emptyCart: 'السلة فارغة',
} as const;
