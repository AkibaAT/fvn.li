export function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export function setCsrfToken(token: string): void {
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
}
