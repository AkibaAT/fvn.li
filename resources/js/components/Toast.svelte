<script lang="ts" module>
    import { toastStore } from '@/utils/toast';

    export function notify(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
        toastStore.add(message, type);
    }
</script>

<script lang="ts">
    import CheckCircleIcon from '@/components/icons/CheckCircle.svelte';
    import ExclamationCircleIcon from '@/components/icons/ExclamationCircle.svelte';
    import InformationCircleIcon from '@/components/icons/InformationCircle.svelte';
    import WarningTriangleIcon from '@/components/icons/WarningTriangle.svelte';
    import XMarkSolidIcon from '@/components/icons/XMarkSolid.svelte';
    import { Button } from '@/components/ui';

    const getNotificationClasses = (type: 'success' | 'error' | 'info' | 'warning') => {
        switch (type) {
            case 'success':
                return 'border border-green-600/40 bg-green-50 dark:border-green-800/60 dark:bg-green-950/30';
            case 'error':
                return 'border border-red-600/40 bg-red-50 dark:border-red-800/60 dark:bg-red-950/30';
            case 'warning':
                return 'border border-amber-600/40 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/30';
            case 'info':
            default:
                return 'border border-border bg-surface-alt';
        }
    };

    const getTextClasses = (type: string) => {
        switch (type) {
            case 'success':
                return 'text-green-900 dark:text-green-200';
            case 'error':
                return 'text-red-900 dark:text-red-200';
            case 'warning':
                return 'text-amber-900 dark:text-amber-200';
            default:
                return 'text-fg';
        }
    };
</script>

<div class="fixed right-4 bottom-4 z-50 space-y-2">
    {#each $toastStore as notification (notification.id)}
        <div
            class="pointer-events-auto w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg {getNotificationClasses(notification.type)}"
            role="alert"
            aria-label="{notification.type} notification: {notification.message}"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        {#if notification.type === 'success'}
                            <CheckCircleIcon class="h-6 w-6 text-green-600 dark:text-green-400" />
                        {:else if notification.type === 'error'}
                            <ExclamationCircleIcon class="h-6 w-6 text-red-600 dark:text-red-400" />
                        {:else if notification.type === 'warning'}
                            <WarningTriangleIcon class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                        {:else}
                            <InformationCircleIcon class="h-6 w-6 text-fg-muted" />
                        {/if}
                    </div>
                    <div class="ml-3 flex-1 pt-0.5">
                        <p class="text-sm font-medium {getTextClasses(notification.type)}">
                            {notification.message}
                        </p>
                    </div>
                    <div class="ml-4 flex flex-shrink-0">
                        <Button
                            type="button"
                            variant="ghost"
                            tone="neutral"
                            size="icon-sm"
                            onclick={() => toastStore.dismiss(notification.id)}
                            class="text-fg-muted hover:text-fg"
                            ariaLabel="Close {notification.type} notification"
                        >
                            <XMarkSolidIcon class="h-5 w-5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    {/each}
</div>
