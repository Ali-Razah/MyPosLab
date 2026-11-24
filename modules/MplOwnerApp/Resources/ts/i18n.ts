/**
 * Runtime bridges to the core localization helpers so Vue templates can call
 * them. Call sites keep the literal `__m('Text', 'MplOwnerApp')` form for the
 * NexoPOS language scanner.
 */
export function __m(text: string, namespace: string): string {
    const translate = (window as any).__m;

    return typeof translate === 'function' ? translate(text, namespace) : text;
}

/**
 * Core-owned strings (payment statuses...) are translated with the core helper.
 */
export function __(text: string): string {
    const translate = (window as any).__;

    return typeof translate === 'function' ? translate(text) : text;
}
