<script lang="ts">
    import { untrack } from 'svelte';
    import { notify } from '@/components/Toast.svelte';
    import NotificationHealthPanel from '@/components/NotificationHealthPanel.svelte';
    import { Button, Card, Switch } from '@/components/ui';
    import { updateNotificationPreferences, type NotificationPreferences } from '@/api/user-preferences';
    import { subscribeToPush, unsubscribeFromPush } from '@/utils/push';

    interface Props {
        initialPreferences: NotificationPreferences;
        hasDiscord: boolean;
        vapidPublicKey?: string;
    }

    let { initialPreferences, hasDiscord, vapidPublicKey }: Props = $props();
    let preferences = $state(untrack(() => initialPreferences));
    let saving = $state(false);
    let healthRefresh = $state(0);

    async function save(next: NotificationPreferences): Promise<void> {
        await updateNotificationPreferences(next);
        preferences = next;
        healthRefresh++;
    }

    async function toggleBrowser(): Promise<void> {
        const enable = !preferences.browser_notifications_enabled;
        saving = true;
        try {
            if (enable) {
                if (!vapidPublicKey) throw new Error('Browser push is not configured on the server.');
                await subscribeToPush(vapidPublicKey);
            }

            await save({ ...preferences, browser_notifications_enabled: enable });
            if (!enable) await unsubscribeFromPush();
            notify(
                enable ? 'Browser notifications enabled for this device.' : 'Browser notifications disabled and this device was unsubscribed.',
                'success',
            );
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not update browser notifications', 'error');
        } finally {
            saving = false;
        }
    }

    async function toggleDiscord(): Promise<void> {
        const enable = !preferences.discord_notifications_enabled;
        saving = true;
        try {
            await save({ ...preferences, discord_notifications_enabled: enable });
            notify(enable ? 'Discord DMs enabled. Authorize the app and send a test DM to verify delivery.' : 'Discord DMs disabled.', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not update Discord notifications', 'error');
        } finally {
            saving = false;
        }
    }

    async function updateDigest(value: string): Promise<void> {
        if (value === preferences.notification_digest) return;
        saving = true;
        try {
            await save({ ...preferences, notification_digest: value });
            notify('Notification frequency updated.', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not update notification frequency', 'error');
        } finally {
            saving = false;
        }
    }
</script>

<Card padding="lg">
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Notification Settings</h2>
    <div class="space-y-4">
        {#if vapidPublicKey}
            <div class="flex items-center gap-4">
                <div class="flex-grow">
                    <div class="font-medium text-gray-700 dark:text-gray-300">Browser Push Notifications</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Receive game updates on this browser. Each device has its own subscription.
                    </div>
                </div>
                <Switch
                    checked={preferences.browser_notifications_enabled}
                    onchange={toggleBrowser}
                    disabled={saving}
                    ariaLabel="Enable browser notifications"
                />
            </div>
        {/if}

        {#if hasDiscord}
            <div class="flex items-center gap-4">
                <div class="flex-grow">
                    <div class="font-medium text-gray-700 dark:text-gray-300">Discord Notifications</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Receive direct messages without needing to share a server with the bot.
                    </div>
                </div>
                <Switch
                    checked={preferences.discord_notifications_enabled}
                    onchange={toggleDiscord}
                    disabled={saving}
                    ariaLabel="Enable Discord notifications"
                />
            </div>
        {/if}

        <div>
            <div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Notification Frequency</div>
            <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">Choose how often update notifications are delivered.</div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {#each [{ value: 'asap', label: 'As soon as possible', desc: 'Send each update immediately' }, { value: 'daily', label: 'Daily digest', desc: 'One daily update summary' }, { value: 'weekly', label: 'Weekly digest', desc: 'One weekly update summary' }] as frequency (frequency.value)}
                    <Button
                        type="button"
                        variant={preferences.notification_digest === frequency.value ? 'soft' : 'outline'}
                        tone={preferences.notification_digest === frequency.value ? 'info' : 'neutral'}
                        aria-pressed={preferences.notification_digest === frequency.value}
                        onclick={() => updateDigest(frequency.value)}
                        disabled={saving}
                        class="h-auto flex-col items-start rounded-lg p-3 text-left text-sm"
                    >
                        <span class="font-medium text-gray-900 dark:text-white">{frequency.label}</span>
                        <span class="mt-1 text-xs text-gray-700 dark:text-gray-300">{frequency.desc}</span>
                    </Button>
                {/each}
            </div>
        </div>

        <NotificationHealthPanel {vapidPublicKey} refreshToken={healthRefresh} />
    </div>
</Card>
