export type ViewMode = 'grid' | 'list';

export const VIEW_MODE_COOKIES = {
    home: 'home_view',
    games: 'games_view',
} as const;

export const GAME_CARD_GRID_CLASS = 'grid grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-3 sm:gap-x-5 sm:gap-y-6 xl:grid-cols-6';

const VIEW_MODE_MAX_AGE_SECONDS = 31_536_000;

export function parseViewMode(value: unknown): ViewMode {
    return value === 'list' ? 'list' : 'grid';
}

export function writeViewModeCookie(cookieName: string, mode: ViewMode): void {
    if (typeof document === 'undefined') return;

    document.cookie = `${cookieName}=${mode};path=/;max-age=${VIEW_MODE_MAX_AGE_SECONDS};SameSite=Lax`;
}
