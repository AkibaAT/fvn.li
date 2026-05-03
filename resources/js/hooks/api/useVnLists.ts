import { getCsrfToken } from './client';
import http from '@/utils/http';

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

export async function fetchUserLists(): Promise<VnList[]> {
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

export async function fetchUserListsDetailed(): Promise<VnList[]> {
  const response = await http.get('/browser-api/user/lists');
  if (!response.data?.success) throw new Error('Failed to fetch lists');
  return response.data.lists || [];
}

export async function fetchGameListMemberships(gameId: number): Promise<number[]> {
  const response = await http.get(`/browser-api/games/${gameId}/lists`);
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

export async function addGameToList({ gameId, listId }: AddToListParams): Promise<{ success: boolean; message: string }> {
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

export async function addGameToListByType({ gameId, listType }: AddToListByTypeParams): Promise<{ success: boolean; message: string }> {
  const response = await http.post(`/browser-api/games/${gameId}/add-to-list`, {
    list_type: listType,
  });
  return response.data;
}

export async function toggleCustomList({ listId, gameId }: ToggleCustomListParams): Promise<{ success: boolean; message: string }> {
  const response = await http.post(`/browser-api/lists/${listId}/add-game`, {
    game_id: gameId,
  });
  return response.data;
}

export async function createList({ name, isPublic, gameId }: CreateListParams): Promise<{ success: boolean; message: string; list: VnList }> {
  const response = await http.post('/browser-api/vn-lists', {
    name: name.trim(),
    is_public: isPublic,
    game_id: gameId,
  });
  return response.data;
}
