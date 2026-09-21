export type ViewMode = 'grid' | 'list';

export const VIEW_MODE_COOKIES = {
    home: 'home_view',
    games: 'games_view',
} as const;

/** Home teasers: the grid shows one row of five, the list shows two columns of three. */
export const HOME_GRID_TEASER_LIMIT = 5;
export const HOME_LIST_TEASER_LIMIT = 6;

const VIEW_MODE_MAX_AGE_SECONDS = 31_536_000;

export function parseViewMode(value: unknown): ViewMode {
    return value === 'list' ? 'list' : 'grid';
}

export function writeViewModeCookie(cookieName: string, mode: ViewMode): void {
    if (typeof document === 'undefined') return;

    document.cookie = `${cookieName}=${mode};path=/;max-age=${VIEW_MODE_MAX_AGE_SECONDS};SameSite=Lax`;
}

/** Split a row list into two balanced columns so the grid never has a ragged edge. */
export function splitIntoColumns<T>(items: T[]): [T[], T[]] {
    const half = Math.ceil(items.length / 2);
    return [items.slice(0, half), items.slice(half)];
}
