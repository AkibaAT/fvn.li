<script lang="ts" module>
    import { toastStore, type ToastType } from '@/utils/toast';

    export function notify(message: string, type: ToastType = 'info') {
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
    import type { Component } from 'svelte';
    import { alertToneClasses, type AlertTone } from '@/components/ui/Alert.svelte';

    const toastTones: Record<ToastType, AlertTone> = {
        success: 'success',
        error: 'danger',
        warning: 'warning',
        info: 'info',
    };

    const toastIcons: Record<ToastType, Component<{ class?: string }>> = {
        success: CheckCircleIcon,
        error: ExclamationCircleIcon,
        warning: WarningTriangleIcon,
        info: InformationCircleIcon,
    };
</script>

<div class="fixed right-4 bottom-4 z-50 space-y-2">
    {#each $toastStore as notification (notification.id)}
        {@const Icon = toastIcons[notification.type]}
        {@const tone = alertToneClasses[toastTones[notification.type]]}
        <div
            class="pointer-events-auto w-96 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border {tone.box}"
            role="alert"
            aria-label="{notification.type} notification: {notification.message}"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <Icon class="h-6 w-6 {tone.icon}" />
                    </div>
                    <div class="ml-3 flex-1 pt-0.5">
                        <p class="text-sm font-medium {tone.title}">
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
