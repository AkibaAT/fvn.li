<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ArrowLeftIcon from '@/components/icons/ArrowLeft.svelte';
    import DynamicPathIcon from '@/components/icons/DynamicPath.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    interface NotificationHistory {
        id: number;
        type: string;
        title: string;
        message: string;
        created_at: string;
        read_at?: string;
        data?: Record<string, unknown>;
    }

    interface Props {
        date: string;
        formattedDate: string;
        notifications: NotificationHistory[];
        hasNotifications: boolean;
        hasAnyNotifications: boolean;
        metaTags?: {
            title?: string;
        };
    }

    let { date: _date, formattedDate, notifications, hasNotifications, hasAnyNotifications, metaTags }: Props = $props();

    function getNotificationIconPath(type: string): { d: string; color: string } {
        switch (type) {
            case 'game_update':
                return {
                    d: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                    color: 'text-blue-500',
                };
            case 'new_game':
                return { d: 'M12 6v6m0 0v6m0-6h6m-6 0H6', color: 'text-green-500' };
            case 'system':
                return { d: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-yellow-500' };
            default:
                return { d: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-gray-500' };
        }
    }

    function formatNotificationTime(timestamp: string): string {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
</script>

<SeoHead {metaTags} title="Notification Digest" />

<div class="space-y-8">
    <PageHeader
        title="Notification Digest"
        description={`Notifications for ${formattedDate}`}
        backHref={route('dashboard')}
        backLabel="Back to Dashboard"
        class="mb-0"
    />

    <Card variant="glass" padding="lg" class="shadow-none">
        {#if hasNotifications}
            <div class="space-y-4">
                <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Your Notifications</h2>
                {#each notifications as notification (notification.id)}
                    {@const iconInfo = getNotificationIconPath(notification.type)}
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="flex items-start space-x-3">
                            <div class="mt-0.5 flex-shrink-0">
                                <DynamicPathIcon class="h-5 w-5 {iconInfo.color}" path={iconInfo.d} />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        {notification.title}
                                    </h3>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {formatNotificationTime(notification.created_at)}
                                    </span>
                                </div>
                                <p class="mt-1 text-gray-600 dark:text-gray-400">
                                    {notification.message}
                                </p>
                                {#if notification.data}
                                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-500">Additional details available</div>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {:else}
            <div class="py-12 text-center">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Notifications</h3>
                <p class="mt-1 text-gray-500 dark:text-gray-400">
                    {hasAnyNotifications ? "You don't have any notifications for this date." : 'There are no notifications available for this date.'}
                </p>
            </div>
        {/if}
    </Card>

    <div class="flex items-center justify-between">
        <Link href={route('dashboard')} class="inline-flex items-center space-x-2 text-blue-600 transition-colors hover:text-blue-700">
            <ArrowLeftIcon class="h-5 w-5" />
            <span>Back to Dashboard</span>
        </Link>
    </div>
</div>
