import React, { useEffect, useRef, useState } from 'react';
import { formatLocalDateTime } from '@/utils/date-formatting';

interface NotificationItem {
  id: string;
  type: string;
  message: string;
  data: Record<string, unknown>;
  created_at: string;
}

const BellIcon = () => (
  <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
    <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
  </svg>
);

export default function NotificationsDropdown() {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [items, setItems] = useState<NotificationItem[]>([]);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const click = (e: MouseEvent) => {
      if (open && ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', click);
    return () => document.removeEventListener('mousedown', click);
  }, [open]);

  const fetchNotifications = async () => {
    setLoading(true);
    try {
      const resp = await fetch(route('react-api.notifications.index'));
      const data = await resp.json();
      if (data?.success) setItems(data.data);
    } catch (e) {
      console.error('Failed to load notifications', e);
    } finally {
      setLoading(false);
    }
  };

  const markAsRead = async (id: string) => {
    try {
      await fetch(route('react-api.notifications.read', id), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
      });
      setItems((prev) => prev.filter((n) => n.id !== id));
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
        className="relative flex h-9 w-9 items-center justify-center rounded-lg bg-stone-100 text-stone-600 transition-colors duration-200 hover:bg-stone-200 hover:text-stone-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
        onClick={toggleOpen}
        aria-haspopup="true"
        aria-expanded={open}
        aria-label="Notifications"
      >
        <BellIcon />
        {items.length > 0 && (
          <span className="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-orange-500 text-[10px] font-bold text-white">
            {items.length > 9 ? '9+' : items.length}
          </span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 z-50 mt-2 w-96 rounded-xl border border-stone-200 bg-white p-1.5 shadow-xl shadow-stone-200/50 dark:border-gray-700 dark:bg-gray-800 dark:shadow-black/20">
          <div className="flex items-center justify-between px-3 py-2">
            <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</span>
            {items.length > 0 && (
              <span className="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-700 dark:bg-teal-900/30 dark:text-teal-300">
                {items.length} new
              </span>
            )}
          </div>
          <div className="max-h-80 overflow-y-auto">
            {loading ? (
              <div className="flex items-center justify-center p-6">
                <div className="h-5 w-5 animate-spin rounded-full border-2 border-teal-500 border-t-transparent" />
              </div>
            ) : items.length === 0 ? (
              <div className="p-6 text-center">
                <div className="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-stone-100 text-stone-400 dark:bg-gray-700 dark:text-gray-500">
                  <BellIcon />
                </div>
                <p className="text-sm text-gray-500 dark:text-gray-400">No notifications</p>
              </div>
            ) : (
              <ul className="divide-y divide-stone-100 dark:divide-gray-700">
                {items.map((n) => (
                  <li key={n.id} className="rounded-lg p-3 transition-colors hover:bg-stone-50 dark:hover:bg-gray-700/50">
                    <div className="text-sm text-gray-900 dark:text-gray-100">{n.message}</div>
                    <div className="mt-1.5 flex items-center justify-between">
                      <span className="text-xs text-gray-500 dark:text-gray-400">{formatLocalDateTime(n.created_at)}</span>
                      <button
                        className="text-xs font-medium text-teal-600 transition-colors hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
                        onClick={() => markAsRead(n.id)}
                      >
                        Dismiss
                      </button>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

