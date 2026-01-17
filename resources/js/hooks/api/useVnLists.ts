import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getCsrfToken } from './client';

export interface VnList {
  id: number;
  name: string;
  type: string;
  is_default?: boolean;
  is_public?: boolean;
  user?: {
    id: number;
    name: string;
  };
}

export const vnListKeys = {
  all: ['vn-lists'] as const,
  user: () => [...vnListKeys.all, 'user'] as const,
  gameMembers: (gameId: number) => [...vnListKeys.all, 'game', gameId] as const,
};

async function fetchUserLists(): Promise<VnList[]> {
  const response = await fetch(route('api.vn-lists.index'), {
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
  });
  if (!response.ok) throw new Error('Failed to fetch lists');
  const data = await response.json();
  return data.lists || [];
}

async function fetchUserListsDetailed(): Promise<VnList[]> {
  const response = await window.axios.get('/react-api/user/lists');
  if (!response.data?.success) throw new Error('Failed to fetch lists');
  return response.data.lists || [];
}

async function fetchGameListMemberships(gameId: number): Promise<number[]> {
  const response = await window.axios.get(`/react-api/games/${gameId}/lists`);
  return response.data?.list_ids || [];
}

interface AddToListParams {
  gameId: number;
  listId: number;
}

interface AddToListByTypeParams {
  gameId: number;
  listType: string;
}

interface ToggleCustomListParams {
  listId: number;
  gameId: number;
}

interface CreateListParams {
  name: string;
  isPublic: boolean;
  gameId?: number;
}

async function addGameToList({ gameId, listId }: AddToListParams): Promise<{ success: boolean; message: string }> {
  const response = await fetch(route('api.games.add-to-list', gameId), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
    body: JSON.stringify({ list_id: listId }),
  });
  return response.json();
}

async function addGameToListByType({ gameId, listType }: AddToListByTypeParams): Promise<{ success: boolean; message: string }> {
  const response = await window.axios.post(`/react-api/games/${gameId}/add-to-list`, {
    list_type: listType,
  });
  return response.data;
}

async function toggleCustomList({ listId, gameId }: ToggleCustomListParams): Promise<{ success: boolean; message: string }> {
  const response = await window.axios.post(`/react-api/lists/${listId}/add-game`, {
    game_id: gameId,
  });
  return response.data;
}

async function createList({ name, isPublic, gameId }: CreateListParams): Promise<{ success: boolean; message: string; list: VnList }> {
  const response = await window.axios.post('/react-api/vn-lists', {
    name: name.trim(),
    is_public: isPublic,
    game_id: gameId,
  });
  return response.data;
}

export function useUserLists() {
  return useQuery({
    queryKey: vnListKeys.user(),
    queryFn: fetchUserLists,
  });
}

export function useUserListsDetailed() {
  return useQuery({
    queryKey: [...vnListKeys.user(), 'detailed'],
    queryFn: fetchUserListsDetailed,
  });
}

export function useGameListMemberships(gameId: number) {
  return useQuery({
    queryKey: vnListKeys.gameMembers(gameId),
    queryFn: () => fetchGameListMemberships(gameId),
    enabled: !!gameId,
  });
}

export function useAddGameToList() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: addGameToList,
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: vnListKeys.gameMembers(variables.gameId) });
    },
  });
}

export function useAddGameToListByType() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: addGameToListByType,
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: vnListKeys.gameMembers(variables.gameId) });
    },
  });
}

export function useToggleCustomList() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: toggleCustomList,
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: vnListKeys.gameMembers(variables.gameId) });
    },
  });
}

export function useCreateList() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: createList,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: vnListKeys.user() });
    },
  });
}
