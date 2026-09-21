/**
 * Shared types for the "My Games" (owned itch.io games) surfaces:
 * the My Games page, the dashboard My Games tab, and the dashboard page props.
 * Mirrors what `OwnedGameSummaryService` / `MyGamesController` return to Inertia.
 */
export interface GameSummary {
    id: number;
    name: string;
    slug: string;
    thumb_url?: string | null;
    has_additional_links?: boolean;
    platform?: 'itch_io' | 'steam' | 'other';
}

interface GameClickStats {
    page_views_total: number;
    page_views_unique: number;
    external_project_total: number;
    external_project_unique: number;
    custom_link_clicks_total: number;
    custom_link_clicks_unique: number;
}

/** Click stats keyed by game id (as string, matching Inertia JSON object keys). */
export type GameClickStatsMap = { [gameId: string]: GameClickStats };
