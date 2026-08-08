<script lang="ts">
    import { onMount } from 'svelte';
    import { fetchNotificationHealth, testNotificationChannel, type NotificationHealth } from '@/api/notifications';
    import { notify } from '@/components/Toast.svelte';
    import { Badge, Button } from '@/components/ui';
    import { waitForDiscordTest } from '@/utils/discord-test-polling';
    import { computeChannelStatus, type ChannelStatus } from '@/utils/notification-health';
    import { localPushSubscription, subscribeToPush } from '@/utils/push';

    interface Props {
        vapidPublicKey?: string;
        refreshToken?: number;
    }

    let { vapidPublicKey, refreshToken = 0 }: Props = $props();
    let health = $state<NotificationHealth | null>(null);
    let loading = $state(true);
    let testing = $state<'browser' | 'discord' | null>(null);
    let browserSubscribed = $state(false);
    let seenRefreshToken = $state(0);

    async function refresh(): Promise<NotificationHealth> {
        const [nextHealth, subscription] = await Promise.all([
            fetchNotificationHealth(),
            vapidPublicKey ? localPushSubscription() : Promise.resolve(null),
        ]);
        health = nextHealth;
        browserSubscribed = !!subscription;
        loading = false;

        return nextHealth;
    }

    onMount(() => {
        refresh().catch((error) => {
            loading = false;
            notify(error instanceof Error ? error.message : 'Failed to load notification health', 'error');
        });
    });

    $effect(() => {
        if (refreshToken === seenRefreshToken) return;
        seenRefreshToken = refreshToken;
        refresh().catch(() => {});
    });

    function label(status: ChannelStatus): string {
        return status === 'working' ? 'Working' : status === 'action-needed' ? 'Action needed' : 'Disabled';
    }

    function tone(status: ChannelStatus): 'success' | 'warning' | 'neutral' {
        return status === 'working' ? 'success' : status === 'action-needed' ? 'warning' : 'neutral';
    }

    async function testChannel(channel: 'browser' | 'discord'): Promise<void> {
        testing = channel;
        try {
            const { notificationId } = await testNotificationChannel(channel);

            if (channel === 'discord') {
                if (notificationId === undefined) throw new Error('The Discord test did not return a notification ID.');
                await waitForDiscordTest(notificationId, async () => (await refresh()).discord.lastTest ?? null);
            }

            await refresh();
            notify(channel === 'browser' ? 'Test notification sent to this browser.' : 'Discord test DM delivered.', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Notification test failed', 'error');
        } finally {
            testing = null;
        }
    }

    async function resubscribe(): Promise<void> {
        if (!vapidPublicKey) {
            notify('Browser push is not configured on the server.', 'error');
            return;
        }
        try {
            await subscribeToPush(vapidPublicKey);
            await refresh();
            notify('This browser is subscribed to notifications.', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not subscribe this browser', 'error');
        }
    }
</script>

{#if loading}
    <p class="text-sm text-gray-500 dark:text-gray-400">Checking notification health…</p>
{:else if health}
    {@const discordStatus = computeChannelStatus('discord', health.discord)}
    <div class="space-y-4 border-t border-gray-200 pt-4 dark:border-gray-700">
        {#if vapidPublicKey}
            {@const browserStatus = computeChannelStatus('browser', health.browser, {
                permission: typeof Notification === 'undefined' ? 'default' : Notification.permission,
                subscribed: browserSubscribed,
            })}
            <section class="space-y-3" aria-labelledby="browser-notification-status">
                <div>
                    <div class="flex items-center gap-2">
                        <span id="browser-notification-status" class="font-medium text-gray-900 dark:text-white">Browser notification status</span>
                        <Badge tone={tone(browserStatus)}>{label(browserStatus)}</Badge>
                    </div>
                    {#if browserStatus === 'disabled'}
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Switch on browser notifications above to use this channel.</p>
                    {/if}
                    {#if browserStatus === 'action-needed'}
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                            {#if typeof Notification !== 'undefined' && Notification.permission === 'denied'}
                                <li>Allow notifications for this site in your browser settings.</li>
                            {/if}
                            {#if !browserSubscribed}<li>Resubscribe this device; subscriptions are stored separately per browser.</li>{/if}
                        </ul>
                    {/if}
                </div>
                {#if browserStatus !== 'disabled'}
                    <div class="flex flex-wrap gap-2">
                        {#if browserStatus === 'action-needed'}
                            <Button type="button" variant="outline" tone="neutral" onclick={resubscribe}>Resubscribe this device</Button>
                        {/if}
                        <Button type="button" variant="solid" tone="primary" disabled={testing !== null} onclick={() => testChannel('browser')}
                            >Send test notification</Button
                        >
                    </div>
                {/if}
            </section>
        {/if}

        <section
            class="space-y-3 {vapidPublicKey ? 'border-t border-gray-200 pt-4 dark:border-gray-700' : ''}"
            aria-labelledby="discord-notification-status"
        >
            <div>
                <div class="flex items-center gap-2">
                    <span id="discord-notification-status" class="font-medium text-gray-900 dark:text-white">Discord notification status</span>
                    <Badge tone={tone(discordStatus)}>{label(discordStatus)}</Badge>
                    {#if health.discord.botOnline === false}<Badge tone="warning">Bot offline</Badge>{/if}
                </div>
                {#if discordStatus === 'disabled'}
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {health.discord.linked
                            ? 'Switch on Discord notifications above to use this channel.'
                            : 'Link your Discord account to use this channel.'}
                    </p>
                {/if}
                {#if discordStatus === 'action-needed'}
                    {#if health.discord.dmStatus === 'undeliverable'}
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {health.discord.dmStatusReason === 'account_missing'
                                ? 'The linked Discord account no longer exists.'
                                : `Discord refused the last direct message${
                                      health.discord.dmStatusReason === 'cannot_dm'
                                          ? ' because it will not accept messages from this app yet'
                                          : ' because the app is no longer authorized'
                                  }.`}
                        </p>
                    {/if}
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                        {#if health.discord.dmStatusReason === 'account_missing'}
                            <li>Unlink the old Discord account, then link your current account.</li>
                        {:else if !health.discord.linked}<li>Link your Discord account.</li>{/if}
                        {#if health.discord.userInstallUrl && !health.discord.userInstalledAt}<li>
                                <a class="text-indigo-600 underline dark:text-indigo-400" href={health.discord.userInstallUrl}
                                    >Add the FVN.li app to your Discord account</a
                                >, then choose &ldquo;Add to my apps&rdquo;. No shared server is needed.
                            </li>{/if}
                        {#if health.discord.userInstalledAt}
                            <li>Allow direct messages from the app and unblock it, then run the test again.</li>
                        {:else}
                            <li>Run the test again once the app is added.</li>
                        {/if}
                    </ol>
                {/if}
            </div>
            {#if discordStatus !== 'disabled' && health.discord.linked}
                <div class="space-y-2">
                    <Button type="button" variant="solid" tone="primary" disabled={testing !== null} onclick={() => testChannel('discord')}
                        >{testing === 'discord' ? 'Waiting for Discord…' : 'Send test DM'}</Button
                    >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        The bot checks for queued notifications once a minute, so a test may take up to 75 seconds.
                    </p>
                </div>
            {/if}
        </section>
    </div>
{/if}
