import { apiGet, getCsrfToken } from './client';

export interface NotificationItem {
  id: string;
  type: string;
  message: string;
  data: Record<string, unknown>;
  created_at: string;
}

export async function fetchNotifications(): Promise<NotificationItem[]> {
  return apiGet<NotificationItem[]>(route('react-api.notifications.index'));
}

export async function markNotificationAsRead(id: string): Promise<void> {
  await fetch(route('react-api.notifications.read', id), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': getCsrfToken(),
    },
  });
}
