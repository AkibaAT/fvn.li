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
                return 'border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20 text-green-900 dark:text-green-200';
            case 'error':
                return 'border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 text-red-900 dark:text-red-200';
            case 'warning':
                return 'border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-900 dark:text-yellow-200';
            case 'info':
            default:
                return 'border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20 text-blue-900 dark:text-blue-200';
        }
    };

    const getTextClasses = (type: string) => {
        switch (type) {
            case 'success':
                return 'text-green-800 dark:text-green-200';
            case 'error':
                return 'text-red-800 dark:text-red-200';
            case 'warning':
                return 'text-yellow-800 dark:text-yellow-200';
            default:
                return 'text-blue-800 dark:text-blue-200';
        }
    };
</script>

<div class="fixed right-4 bottom-4 z-50 space-y-2">
    {#each $toastStore as notification (notification.id)}
        <div
            class="ring-opacity-5 pointer-events-auto w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg shadow-lg ring-1 ring-black {getNotificationClasses(
                notification.type,
            )}"
            role="alert"
            aria-label="{notification.type} notification: {notification.message}"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        {#if notification.type === 'success'}
                            <CheckCircleIcon class="h-6 w-6 text-green-400" />
                        {:else if notification.type === 'error'}
                            <ExclamationCircleIcon class="h-6 w-6 text-red-400" />
                        {:else if notification.type === 'warning'}
                            <WarningTriangleIcon class="h-6 w-6 text-yellow-400" />
                        {:else}
                            <InformationCircleIcon class="h-6 w-6 text-blue-400" />
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
                            class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none dark:bg-gray-800"
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
