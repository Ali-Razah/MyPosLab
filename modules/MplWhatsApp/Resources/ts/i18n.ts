/**
 * Runtime bridge to the core module translator. Call sites keep the literal
 * `__m('Text', 'MplWhatsApp')` form so the NexoPOS language scanner finds them.
 */
export function __m(text: string, namespace: string): string {
    const translator = (window as any).__m;

    return typeof translator === 'function' ? translator(text, namespace) : text;
}
