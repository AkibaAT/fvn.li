import type { Game, GameVersion, UserGameProgress } from './game';

export interface ListOwner {
    id: number;
    name: string;
    avatar?: string;
}

/**
 * One entry of a user's list. Public-list payloads only fill id, game and
 * sort_order; the owner-facing list page also fills the progress/notes fields.
 */
export interface VnListEntry {
    id: number;
    game: Game;
    sort_order: number;
    notes?: string;
    private_notes?: string;
    started_at?: string;
    completed_at?: string;
    user_progress?: UserGameProgress;
    personal_notes?: string;
    game_version_id?: number;
    game_version?: GameVersion;
}

export interface VnList {
    id: number;
    name: string;
    description?: string;
    type: string;
    is_default: boolean;
    is_public: boolean;
    created_at: string;
    updated_at?: string;
    entries: VnListEntry[];
    entries_count?: number;
    user: ListOwner;
}

export interface AvailableList {
    id: number;
    name: string;
    type: string;
}

export type { Game };
