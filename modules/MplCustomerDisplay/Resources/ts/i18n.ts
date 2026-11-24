/**
 * Runtime bridge to the core `window.__m` so Vue templates get a real binding.
 * Call sites keep the literal `__m('Text', 'MplCustomerDisplay')` form for the scanner.
 */
export function __m(text: string, namespace: string): string {
    const translate = (window as any).__m;

    return typeof translate === 'function' ? translate(text, namespace) : text;
}
