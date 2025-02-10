import {ref} from 'vue';
import type {CharacterStats, FileCategory, GameDetailStore, VersionPagination} from '@/types/version';
import axios from "axios";

export function useGameDetailStore(): GameDetailStore {
    const versions = ref<VersionPagination | null>(null);
    const loadingVersions = ref(false);
    const characterStats = ref<CharacterStats | null>(null);
    const fileStats = ref<FileCategory[] | null>(null);
    const loadingStats = ref(false);

    const loadVersions = async (gameId: number, perPage: number = 5, page: number = 1) => {
        if (!gameId) {
            console.error('Game ID is required');
            return;
        }

        loadingVersions.value = true;
        try {
            const response = await axios.get<VersionPagination>(`/api/games/${gameId}/versions`, {
                params: {perPage, page}
            });

            if (response.data) {
                versions.value = response.data;
            } else {
                console.error('No data returned from versions API');
            }
        } catch (error) {
            console.error('Error loading versions:', error);
        } finally {
            loadingVersions.value = false;
        }
    };

    const loadCharacterStats = async (gameId: number, versionId: number) => {
        loadingStats.value = true;
        try {
            const response = await axios.get<CharacterStats>(
                `/api/games/${gameId}/versions/${versionId}/character-stats`
            );
            characterStats.value = response.data;
        } catch (error) {
            console.error('Error loading character stats:', error);
        } finally {
            loadingStats.value = false;
        }
    };

    const loadFileStats = async (gameId: number, versionId: number) => {
        loadingStats.value = true;
        try {
            const response = await axios.get<FileCategory[]>(
                `/api/games/${gameId}/versions/${versionId}/file-stats`
            );
            fileStats.value = response.data;
        } catch (error) {
            console.error('Error loading file stats:', error);
        } finally {
            loadingStats.value = false;
        }
    };

    return {
        versions: {value: versions},
        loadingVersions: {value: loadingVersions},
        loadVersions,
        characterStats: {value: characterStats},
        fileStats: {value: fileStats},
        loadingStats: {value: loadingStats},
        loadCharacterStats,
        loadFileStats,
    };
}
