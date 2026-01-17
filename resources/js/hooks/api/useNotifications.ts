import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiGet, getCsrfToken } from './client';

export interface NotificationItem {
  id: string;
  type: string;
  message: string;
  data: Record<string, unknown>;
  created_at: string;
}

export const notificationKeys = {
  all: ['notifications'] as const,
};

async function fetchNotifications(): Promise<NotificationItem[]> {
  return apiGet<NotificationItem[]>(route('react-api.notifications.index'));
}

async function markAsRead(id: string): Promise<void> {
  await fetch(route('react-api.notifications.read', id), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': getCsrfToken(),
    },
  });
}

export function useNotifications(options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: notificationKeys.all,
    queryFn: fetchNotifications,
    // No polling - we use Inertia shared data for the unread count badge
    // Details are only fetched when user opens the dropdown
    staleTime: 30000, // Consider data fresh for 30 seconds
    ...options,
  });
}

export function useMarkNotificationAsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: markAsRead,
    onMutate: async (id) => {
      await queryClient.cancelQueries({ queryKey: notificationKeys.all });
      const previous = queryClient.getQueryData<NotificationItem[]>(notificationKeys.all);

      queryClient.setQueryData<NotificationItem[]>(notificationKeys.all, (old) =>
        old?.filter((n) => n.id !== id) ?? []
      );

      return { previous };
    },
    onError: (_err, _id, context) => {
      if (context?.previous) {
        queryClient.setQueryData(notificationKeys.all, context.previous);
      }
    },
  });
}
