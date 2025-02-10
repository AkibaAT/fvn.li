import axios from 'axios';
import type {Game} from '@/types';
import {useLoadingState} from '@/hooks/useLoadingState';

interface GameListResponse {
    data: Game[];
    links: any[];
    meta: any;
}

export class GameListStore {
    private static instance: GameListStore;
    private state;

    private constructor() {
        this.state = useLoadingState<GameListResponse>();
    }

    public get games() {
        return this.state.current;
    }

    public get loading() {
        return this.state.isLoading;
    }

    public get error() {
        return this.state.error;
    }

    public static getInstance(): GameListStore {
        if (!GameListStore.instance) {
            GameListStore.instance = new GameListStore();
        }
        return GameListStore.instance;
    }

    public async fetchGames(params: {
        search?: string;
        selectedStatuses?: string[];
        selectedEngines?: string[];
        selectedPlatforms?: string[];
        selectedLanguages?: string[];
        nsfw?: boolean;
        sfw?: boolean;
        sortField?: string;
        sortDirection?: string;
        perPage?: number;
        page?: number;
    }): Promise<void> {
        try {
            const response = await axios.get<GameListResponse>('/api/games', {params});

            if (response.data) {
                // If this is the first load, set current directly
                if (!this.state.current.value) {
                    this.state.current.value = response.data;
                } else {
                    // Otherwise, use the loading state management
                    this.state.startLoading(response.data);
                    this.state.finishLoading();
                }
            } else {
                this.state.finishLoading('Failed to load games');
            }
        } catch (error) {
            console.error('Error fetching games:', error);
            this.state.finishLoading('Failed to load games');
        }
    }

    public clearGames(): void {
        this.state.current.value = null;
        this.state.error.value = null;
    }
}

// Export a function to get the singleton instance
export const useGameListStore = () => GameListStore.getInstance();
