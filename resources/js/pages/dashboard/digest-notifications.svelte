<script lang="ts">
    import { formatCalendarDate, formatDateTimeWithTimezone } from '@/utils/date-formatting';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import ArrowLeftIcon from '@/components/icons/ArrowLeft.svelte';
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import { Link } from '@inertiajs/svelte';
    import { Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    interface NotificationHistory {
        id: number;
        title: string;
        message: string;
        created_at: string;
        url: string | null;
    }

    interface Props {
        date: string;
        notifications: NotificationHistory[];
        hasNotifications: boolean;
        hasAnyNotifications: boolean;
        metaTags?: {
            title?: string;
        };
    }

    let { date, notifications, hasNotifications, hasAnyNotifications, metaTags }: Props = $props();
</script>

<SeoHead {metaTags} title="Notification Digest" />

<div class="space-y-8">
    <PageHeader
        title="Notification Digest"
        description={`Notifications for ${formatCalendarDate(date)} (UTC)`}
        backHref={route('dashboard')}
        backLabel="Back to Dashboard"
    />

    <Card variant="glass" padding="lg" class="shadow-none">
        {#if hasNotifications}
            <div class="space-y-4">
                <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">Your Notifications</h2>
                {#each notifications as notification (notification.id)}
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                        <div class="flex items-start space-x-3">
                            <div class="mt-0.5 flex-shrink-0">
                                <InformationCircleIcon class="h-5 w-5 text-gray-500" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                        {#if notification.url}
                                            <Link href={notification.url} class="text-blue-600 hover:underline dark:text-blue-400"
                                                >{notification.title}</Link
                                            >
                                        {:else}
                                            {notification.title}
                                        {/if}
                                    </h3>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {formatDateTimeWithTimezone(notification.created_at)}
                                    </span>
                                </div>
                                <p class="mt-1 text-gray-600 dark:text-gray-400">
                                    {notification.message}
                                </p>
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
