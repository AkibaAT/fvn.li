<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import { notify } from '@/components/Toast.svelte';
    import NotificationHealthPanel from '@/components/NotificationHealthPanel.svelte';
    import { Button, Card, Switch } from '@/components/ui';
    import { updateNotificationPreferences, type NotificationPreferences } from '@/api/user-preferences';
    import { subscribeToPush, unsubscribeFromPush } from '@/utils/push';

    interface Props {
        preferences: NotificationPreferences;
        hasDiscord: boolean;
        vapidPublicKey?: string;
    }

    let { preferences, hasDiscord, vapidPublicKey }: Props = $props();
    let saving = $state(false);

    async function save(next: NotificationPreferences): Promise<boolean> {
        await updateNotificationPreferences(next);
        return refreshPage(['notificationPreferences']);
    }

    async function toggleBrowser(): Promise<void> {
        const enable = !preferences.browser_notifications_enabled;
        saving = true;
        try {
            if (enable) {
                if (!vapidPublicKey) throw new Error('Browser push is not configured on the server.');
                await subscribeToPush(vapidPublicKey);
            }

            if (!(await save({ ...preferences, browser_notifications_enabled: enable }))) return;
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
            if (!(await save({ ...preferences, discord_notifications_enabled: enable }))) return;
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
            if (!(await save({ ...preferences, notification_digest: value }))) return;
            notify('Notification frequency updated.', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Could not update notification frequency', 'error');
        } finally {
            saving = false;
        }
    }
</script>

<Card variant="flat" padding="lg">
    <h2 class="mb-4 text-lg font-semibold text-fg">Notification Settings</h2>
    <div class="space-y-4">
        {#if vapidPublicKey}
            <div class="flex items-center gap-4">
                <div class="flex-grow">
                    <div class="font-medium text-fg">Browser Push Notifications</div>
                    <div class="mt-1 text-sm text-fg-muted">Receive game updates on this browser. Each device has its own subscription.</div>
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
                    <div class="font-medium text-fg">Discord Notifications</div>
                    <div class="mt-1 text-sm text-fg-muted">Receive direct messages without needing to share a server with the bot.</div>
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
            <div class="mb-2 text-sm font-medium text-fg-muted">Notification Frequency</div>
            <div class="mb-3 text-xs text-fg-muted">Choose how often update notifications are delivered.</div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {#each [{ value: 'asap', label: 'As soon as possible', desc: 'Send each update immediately' }, { value: 'daily', label: 'Daily digest', desc: 'One daily update summary' }, { value: 'weekly', label: 'Weekly digest', desc: 'One weekly update summary' }] as frequency (frequency.value)}
                    <Button
                        type="button"
                        variant={preferences.notification_digest === frequency.value ? 'soft' : 'outline'}
                        tone={preferences.notification_digest === frequency.value ? 'info' : 'neutral'}
                        aria-pressed={preferences.notification_digest === frequency.value}
                        onclick={() => updateDigest(frequency.value)}
                        disabled={saving}
                        class="h-auto flex-col items-start rounded-md p-3 text-left text-sm"
                    >
                        <span class="font-medium text-fg">{frequency.label}</span>
                        <span class="mt-1 text-xs text-fg-muted">{frequency.desc}</span>
                    </Button>
                {/each}
            </div>
        </div>

        <NotificationHealthPanel
            {vapidPublicKey}
            refreshToken={`${hasDiscord}:${preferences.browser_notifications_enabled}:${preferences.discord_notifications_enabled}:${preferences.notification_digest}`}
        />
    </div>
</Card>
