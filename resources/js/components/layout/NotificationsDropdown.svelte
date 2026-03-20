<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { fetchNotifications, markNotificationAsRead, type NotificationItem } from '@/hooks/api';
    import { formatLocalDateTime } from '@/utils/date-formatting';


    let open = $state(false);
    let dropdownRef: HTMLDivElement | undefined = $state();
    let items = $state<NotificationItem[]>([]);
    let loading = $state(false);

    const unreadCount = $derived(
        (($page.props as any).indicators?.unread_notifications ?? 0) as number
    );

    function getNotificationLink(notification: NotificationItem): string | null {
        switch ((notification.data as any).type) {
            case 'bug_report_reply':
                return route('dashboard') + '?bug_report=' + (notification.data as any).bug_report_id;
            default:
                return null;
        }
    }

    async function toggleOpen() {
        open = !open;
        if (open && items.length === 0) {
            loading = true;
            try {
                items = await fetchNotifications();
            } catch {
                items = [];
            } finally {
                loading = false;
            }
        }
    }

    async function handleMarkAsRead(id: string) {
        try {
            await markNotificationAsRead(id);
            items = items.filter(n => n.id !== id);
        } catch {
            // silently fail
        }
    }

    $effect(() => {
        const click = (e: MouseEvent) => {
            if (open && dropdownRef && !dropdownRef.contains(e.target as Node)) {
                open = false;
            }
        };
        document.addEventListener('mousedown', click);
        return () => document.removeEventListener('mousedown', click);
    });
</script>

<div class="relative" bind:this={dropdownRef}>
    <button
        class="relative rounded-md bg-gray-100 p-2 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        onclick={toggleOpen}
        aria-haspopup="true"
        aria-expanded={open}
        aria-label={`Notifications${unreadCount > 0 ? ` (${unreadCount} unread)` : ''}`}
    >
        <i class="icon-bell" aria-hidden="true"></i>
        {#if unreadCount > 0}
            <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                {unreadCount > 9 ? '9+' : unreadCount}
            </span>
        {/if}
    </button>
    {#if open}
        <div class="absolute right-0 z-50 mt-2 w-96 rounded-lg border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-800">
            <div class="px-2 py-1 text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</div>
            <div class="max-h-80 overflow-y-auto">
                {#if loading}
                    <div class="p-4 text-sm text-gray-500 dark:text-gray-400">Loading...</div>
                {:else if items.length === 0}
                    <div class="p-4 text-sm text-gray-500 dark:text-gray-400">No notifications</div>
                {:else}
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        {#each items as n (n.id)}
                            {@const link = getNotificationLink(n)}
                            <li class="p-3">
                                {#if link}
                                    <a
                                        href={link}
                                        class="block text-sm text-gray-900 hover:text-blue-600 dark:text-gray-100 dark:hover:text-blue-400"
                                        onclick={() => handleMarkAsRead(n.id)}
                                    >
                                        {n.message}
                                    </a>
                                {:else}
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{n.message}</div>
                                {/if}
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{formatLocalDateTime(n.created_at)}</div>
                                <div class="mt-2 text-right">
                                    <button class="text-xs text-blue-600 hover:underline dark:text-blue-400 dark:hover:text-blue-300" onclick={() => handleMarkAsRead(n.id)}>Dismiss</button>
                                </div>
                            </li>
                        {/each}
                    </ul>
                {/if}
            </div>
        </div>
    {/if}
</div>
