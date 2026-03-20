<script lang="ts" module>
    // Re-export types for consumers
    export type FormMessageType = 'error' | 'success' | 'warning' | 'info';
</script>

<script lang="ts">
    // FormError component - the primary use case
    // For other form element components (FormSuccess, FormWarning, FormInfo,
    // AccessibleInput, AccessibleLink, AccessibleButton), import them directly.

    let {
        component = 'error',
        error,
        message,
        show = true,
        class: className = '',
    }: {
        component?: 'error' | 'success' | 'warning' | 'info';
        error?: string;
        message?: string;
        show?: boolean;
        class?: string;
    } = $props();

    const text = $derived(error || message);

    const typeConfig = {
        error: {
            classes: 'text-red-600 dark:text-red-400',
            role: 'alert' as const,
            iconClass: 'icon-cross-circle',
            iconLabel: 'Error',
        },
        success: {
            classes: 'text-green-600 dark:text-green-400',
            role: 'status' as const,
            iconClass: 'icon-check-circle',
            iconLabel: 'Success',
        },
        warning: {
            classes: 'text-yellow-600 dark:text-yellow-400',
            role: 'alert' as const,
            iconClass: 'icon-alert',
            iconLabel: 'Warning',
        },
        info: {
            classes: 'text-blue-600 dark:text-blue-400',
            role: 'status' as const,
            iconClass: 'icon-info',
            iconLabel: 'Information',
        },
    };

    const config = $derived(typeConfig[component]);
</script>

{#if text && show}
    <div class="mt-1 flex items-center gap-1 text-xs {config.classes} {className}" role={config.role} aria-live="polite">
        <span class="flex-shrink-0" role="img" aria-label={config.iconLabel}><i class={config.iconClass} aria-hidden="true"></i></span>
        <span>{text}</span>
    </div>
{/if}
