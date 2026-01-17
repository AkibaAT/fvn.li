import { useQuery, useMutation } from '@tanstack/react-query';
import { getCsrfToken } from './client';

export interface GameStats {
  itchioUsername?: string;
  ownedGamesCount: number;
  gamesWithLinksCount: number;
}

export interface GameVersion {
  id: number;
  version: string;
  published_at: string;
}

export interface Language {
  id: string;
  name: string;
  flag: string;
}

export interface CharacterStats {
  from: number;
  to: number;
  diff: number;
}

export interface FileStats {
  count: number;
  size: number;
}

export interface FileCategory {
  category: string;
  from: FileStats;
  to: FileStats;
  diff: FileStats;
  fileTypes: {
    [extension: string]: {
      from: FileStats;
      to: FileStats;
      diff: FileStats;
    };
  };
}

export interface VersionComparisonData {
  fromVersion: GameVersion;
  toVersion: GameVersion;
  characters: string[];
  languages: Language[];
  characterDiffs: {
    [character: string]: {
      [languageId: string]: CharacterStats;
    };
  };
  languageTotals: {
    from: { [languageId: string]: number };
    to: { [languageId: string]: number };
    diff: { [languageId: string]: number };
  };
  fileCategories: FileCategory[];
}

export const gameDataKeys = {
  all: ['game-data'] as const,
  stats: () => [...gameDataKeys.all, 'stats'] as const,
  versionComparison: (gameId: number, fromVersionId?: number, toVersionId?: number) =>
    [...gameDataKeys.all, 'version-comparison', gameId, fromVersionId, toVersionId] as const,
  userProgress: (gameId: number) => [...gameDataKeys.all, 'user-progress', gameId] as const,
};

async function fetchGameStats(): Promise<GameStats> {
  const response = await fetch(route('react-api.dashboard.game-stats'));
  const data = await response.json();
  if (!data.success) throw new Error('Failed to fetch game stats');
  return data.stats;
}

interface VersionComparisonParams {
  gameId: number;
  fromVersionId?: number;
  toVersionId?: number;
}

async function fetchVersionComparison({
  gameId,
  fromVersionId,
  toVersionId,
}: VersionComparisonParams): Promise<VersionComparisonData> {
  const response = await fetch(
    route('api.games.compare-versions', {
      game: gameId,
      fromVersionId,
      toVersionId,
    }),
    {
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
      },
    }
  );

  if (!response.ok) throw new Error('Failed to fetch comparison data');
  return response.json();
}

interface ToggleNotificationsParams {
  gameId: number;
  receiveUpdates: boolean;
}

async function toggleGameNotifications({
  gameId,
  receiveUpdates,
}: ToggleNotificationsParams): Promise<{ success: boolean; receive_updates: boolean }> {
  const response = await window.axios.patch(`/react-api/user-progress/${gameId}/toggle-updates`, {
    receive_updates: receiveUpdates,
  });
  return response.data;
}

export function useGameStats() {
  return useQuery({
    queryKey: gameDataKeys.stats(),
    queryFn: fetchGameStats,
  });
}

export function useVersionComparison(
  gameId: number,
  fromVersionId?: number,
  toVersionId?: number,
  options?: { enabled?: boolean }
) {
  return useQuery({
    queryKey: gameDataKeys.versionComparison(gameId, fromVersionId, toVersionId),
    queryFn: () => fetchVersionComparison({ gameId, fromVersionId, toVersionId }),
    enabled: options?.enabled ?? (!!gameId && !!fromVersionId && !!toVersionId),
  });
}

export function useToggleGameNotifications() {
  return useMutation({
    mutationFn: toggleGameNotifications,
  });
}
