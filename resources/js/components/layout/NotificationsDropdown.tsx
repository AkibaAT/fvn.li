import React, { useEffect, useRef, useState, useCallback } from 'react';
import { formatLocalDateTime } from '@/utils/date-formatting';

interface NotificationItem {
  id: string;
  type: string;
  message: string;
  data: Record<string, unknown>;
  created_at: string;
}

function getNotificationLink(notification: NotificationItem): string | null {
  switch (notification.data.type) {
    case 'bug_report_reply':
      // Link to dashboard where bug reports section is shown
      return route('dashboard') + '?bug_report=' + notification.data.bug_report_id;
    default:
      return null;
  }
}

export default function NotificationsDropdown() {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [items, setItems] = useState<NotificationItem[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const ref = useRef<HTMLDivElement>(null);

  const fetchNotifications = useCallback(async () => {
    setLoading(true);
    try {
      const resp = await fetch(route('react-api.notifications.index'));
      const data = await resp.json();
      if (data?.success) {
        setItems(data.data);
        setUnreadCount(data.data.length);
      }
    } catch (e) {
      console.error('Failed to load notifications', e);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const click = (e: MouseEvent) => {
      if (open && ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', click);
    return () => document.removeEventListener('mousedown', click);
  }, [open]);

  // Fetch notification count on mount and periodically
  useEffect(() => {
    void fetchNotifications();
    const interval = setInterval(() => {
      void fetchNotifications();
    }, 60000); // Check every minute
    return () => clearInterval(interval);
  }, [fetchNotifications]);

  const markAsRead = async (id: string) => {
    try {
      await fetch(route('react-api.notifications.read', id), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      });
      setItems((prev) => prev.filter((n) => n.id !== id));
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (e) {
      console.error('Failed to mark notification as read', e);
    }
  };

  const toggleOpen = () => {
    const next = !open;
    setOpen(next);
    if (next) void fetchNotifications();
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
                          onClick={() => markAsRead(n.id)}
                        >
                          {n.message}
                        </a>
                      ) : (
                        <div className="text-sm text-gray-900 dark:text-gray-100">{n.message}</div>
                      )}
                      <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{formatLocalDateTime(n.created_at)}</div>
                      <div className="mt-2 text-right">
                        <button className="text-xs text-blue-600 hover:underline dark:text-blue-400 dark:hover:text-blue-300" onClick={() => markAsRead(n.id)}>Dismiss</button>
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

