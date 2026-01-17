import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useNotifications, useMarkNotificationAsRead, NotificationItem } from '@/hooks/api';
import { formatLocalDateTime } from '@/utils/date-formatting';

interface InertiaPageProps {
  indicators?: {
    unread_notifications: number;
  };
}

function getNotificationLink(notification: NotificationItem): string | null {
  switch (notification.data.type) {
    case 'bug_report_reply':
      return route('dashboard') + '?bug_report=' + notification.data.bug_report_id;
    default:
      return null;
  }
}

export default function NotificationsDropdown() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  // Get the unread count from Inertia shared data (loaded with every page)
  const { indicators } = usePage().props as InertiaPageProps;
  const unreadCount = indicators?.unread_notifications ?? 0;

  // Only fetch notification details when dropdown is open
  const { data: items = [], isLoading: loading } = useNotifications({ enabled: open });
  const markAsReadMutation = useMarkNotificationAsRead();

  useEffect(() => {
    const click = (e: MouseEvent) => {
      if (open && ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', click);
    return () => document.removeEventListener('mousedown', click);
  }, [open]);

  const toggleOpen = () => {
    setOpen((prev) => !prev);
  };

  return (
    <div className="relative" ref={ref}>
      <button
        className="relative rounded-md bg-gray-100 p-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        onClick={toggleOpen}
        aria-haspopup="true"
        aria-expanded={open}
        aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ''}`}
      >
        <span>🔔</span>
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 z-50 mt-2 w-96 rounded-lg border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-800">
          <div className="px-2 py-1 text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</div>
          <div className="max-h-80 overflow-y-auto">
            {loading ? (
              <div className="p-4 text-sm text-gray-500 dark:text-gray-400">Loading…</div>
            ) : items.length === 0 ? (
              <div className="p-4 text-sm text-gray-500 dark:text-gray-400">No notifications</div>
            ) : (
              <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                {items.map((n) => {
                  const link = getNotificationLink(n);
                  return (
                    <li key={n.id} className="p-3">
                      {link ? (
                        <a
                          href={link}
                          className="block text-sm text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                          onClick={() => markAsReadMutation.mutate(n.id)}
                        >
                          {n.message}
                        </a>
                      ) : (
                        <div className="text-sm text-gray-900 dark:text-gray-100">{n.message}</div>
                      )}
                      <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{formatLocalDateTime(n.created_at)}</div>
                      <div className="mt-2 text-right">
                        <button className="text-xs text-blue-600 hover:underline dark:text-blue-400 dark:hover:text-blue-300" onClick={() => markAsReadMutation.mutate(n.id)}>Dismiss</button>
                      </div>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

