import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getCsrfToken } from './client';

export interface AdditionRequest {
  id: number;
  game_url: string;
  platform?: string;
  status: string;
  status_label: string;
  submitted_at: string;
  processed_at?: string;
  admin_notes?: string;
}

export interface SubmissionResult {
  success_count: number;
  duplicate_count: number;
  invalid_count: number;
  already_exists_count?: number;
  errors: string[];
}

export const additionRequestKeys = {
  all: ['addition-requests'] as const,
  list: (filters: { status?: string; search?: string; page?: number }) =>
    [...additionRequestKeys.all, 'list', filters] as const,
};

interface FetchRequestsParams {
  status?: string;
  search?: string;
  page?: number;
}

async function fetchAdditionRequests({ status, search, page = 1 }: FetchRequestsParams): Promise<AdditionRequest[]> {
  const params = new URLSearchParams({
    status: status === 'all' ? '' : (status || ''),
    search: search || '',
    page: page.toString(),
  });

  const response = await fetch(
    route('react-api.dashboard.addition-requests.index') + `?${params}`
  );
  const data = await response.json();

  if (!data.success) throw new Error('Failed to fetch requests');
  return data.requests;
}

async function cancelAdditionRequest(requestId: number): Promise<{ success: boolean; message: string }> {
  const response = await fetch(
    route('react-api.dashboard.addition-requests.cancel', { request: requestId }),
    {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
      },
    }
  );
  return response.json();
}

async function submitAdditionRequests(urls: string): Promise<{ success: boolean; result: SubmissionResult; message?: string; errors?: Record<string, string[]> }> {
  const response = await fetch(
    route('react-api.dashboard.addition-requests.submit'),
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({ urls }),
    }
  );
  return response.json();
}

export function useAdditionRequests(filters: FetchRequestsParams = {}) {
  return useQuery({
    queryKey: additionRequestKeys.list(filters),
    queryFn: () => fetchAdditionRequests(filters),
  });
}

export function useCancelAdditionRequest() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: cancelAdditionRequest,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: additionRequestKeys.all });
    },
  });
}

export function useSubmitAdditionRequests() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: submitAdditionRequests,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: additionRequestKeys.all });
    },
  });
}
