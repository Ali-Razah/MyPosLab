import { createMessageId, parseMessage } from './snapshot.ts';
import type { DisplayMessage } from './snapshot.ts';

type Listener = (message: DisplayMessage) => void;

type OutgoingMessage =
    | { type: 'snapshot' | 'paid'; snapshot: any }
    | { type: 'reset' | 'hello' };

/**
 * Same-browser transport between the POS window and the customer display.
 *
 * Uses BroadcastChannel when available; otherwise falls back to writing the
 * message into localStorage, which fires a `storage` event in every other
 * window of the same origin. No server round-trip is involved.
 */
export class DisplayChannel {
    private readonly name: string;
    private readonly storageKey: string;
    private broadcast: BroadcastChannel | null = null;
    private listeners: Listener[] = [];
    private seen: string[] = [];
    private readonly onStorage: (event: StorageEvent) => void;

    constructor(name: string) {
        this.name = name;
        this.storageKey = `${name}:message`;
        this.onStorage = (event: StorageEvent) => {
            if (event.key === this.storageKey && event.newValue) {
                this.dispatch(event.newValue);
            }
        };

        if (typeof BroadcastChannel !== 'undefined') {
            try {
                this.broadcast = new BroadcastChannel(this.name);
                this.broadcast.onmessage = (event: MessageEvent) => this.dispatch(event.data);
            } catch {
                this.broadcast = null;
            }
        }

        if (this.broadcast === null) {
            window.addEventListener('storage', this.onStorage);
        }
    }

    get transport(): 'broadcast-channel' | 'local-storage' {
        return this.broadcast ? 'broadcast-channel' : 'local-storage';
    }

    onMessage(listener: Listener): () => void {
        this.listeners.push(listener);

        return () => {
            this.listeners = this.listeners.filter((entry) => entry !== listener);
        };
    }

    post(message: OutgoingMessage): void {
        const payload = { ...message, id: createMessageId(), sentAt: Date.now() };

        if (this.broadcast) {
            this.broadcast.postMessage(payload);
            return;
        }

        try {
            window.localStorage.setItem(this.storageKey, JSON.stringify(payload));
        } catch {
            // Storage unavailable (private mode / quota): nothing else we can do locally.
        }
    }

    close(): void {
        this.broadcast?.close();
        this.broadcast = null;
        window.removeEventListener('storage', this.onStorage);
        this.listeners = [];
    }

    private dispatch(raw: unknown): void {
        const message = parseMessage(raw);

        if (message === null || this.seen.includes(message.id)) {
            return;
        }

        this.seen = [...this.seen.slice(-49), message.id];
        this.listeners.forEach((listener) => listener(message));
    }
}
