import { router } from '@inertiajs/svelte';

const VISITOR_KEY = 'fvn.visitor';
const VISITOR_TTL_MS = 2 * 60 * 60 * 1000;

let previousUrl: string | null = null;

type StoredVisitor = { id: string; expiresAt: number };

// randomUUID requires a secure context; getRandomValues does not.
function uuidV4(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function visitorId(now = Date.now()): string {
    const fresh = uuidV4();

    try {
        const stored = JSON.parse(localStorage.getItem(VISITOR_KEY) ?? 'null') as StoredVisitor | null;
        const id = stored && typeof stored.id === 'string' && stored.expiresAt > now ? stored.id : fresh;
        localStorage.setItem(VISITOR_KEY, JSON.stringify({ id, expiresAt: now + VISITOR_TTL_MS } satisfies StoredVisitor));

        return id;
    } catch {
        return fresh;
    }
}

export function trackPreviousUrl(): void {
    router.on('start', () => {
        previousUrl = window.location.href;
    });
}

export function recordGamePageView(gameId: number): void {
    const referrer = previousUrl ?? (document.referrer || null);

    fetch(route('api.games.view', gameId), {
        method: 'POST',
        keepalive: true,
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ visitor_id: visitorId(), referrer }),
    }).catch(() => {});
}
