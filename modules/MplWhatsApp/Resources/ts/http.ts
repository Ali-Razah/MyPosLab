declare const nsHttpClient: any;

/**
 * Promise wrapper around the core nsHttpClient (avoids bundling a second RxJS).
 */
export function request<T = any>(method: 'get' | 'post', url: string, data: Record<string, unknown> = {}): Promise<T> {
    return new Promise((resolve, reject) => {
        const observable = method === 'get' ? nsHttpClient.get(url) : nsHttpClient.post(url, data);

        observable.subscribe({
            next: (result: T) => resolve(result),
            error: (error: any) => reject(error),
        });
    });
}

/**
 * Extracts a human readable message from the different error shapes nsHttpClient yields.
 */
export function messageFrom(error: any, fallback: string): string {
    if (error?.errors && typeof error.errors === 'object') {
        const messages = Object.values(error.errors).flat().filter(Boolean);

        if (messages.length > 0) {
            return messages.join('\n');
        }
    }

    if (typeof error?.message === 'string' && error.message.length > 0) {
        return error.message;
    }

    return fallback;
}
