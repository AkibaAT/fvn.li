<script lang="ts">
    import { announceLoading, setBusy } from '@/utils/accessibility';
    import { twMerge } from 'tailwind-merge';

    let {
        size = 'md',
        class: className = '',
        label = 'Loading',
        announcement,
        isBusy = true,
        currentColor = false,
    }: {
        size?: 'sm' | 'md' | 'lg';
        class?: string;
        label?: string;
        announcement?: string;
        isBusy?: boolean;
        currentColor?: boolean;
    } = $props();

    const sizeClasses: Record<string, string> = {
        sm: 'h-4 w-4',
        md: 'h-6 w-6',
        lg: 'h-8 w-8',
    };

    let spinnerEl: HTMLDivElement;

    let classes = $derived(
        twMerge(
            'animate-spin rounded-full border-2',
            currentColor ? 'border-current border-t-transparent' : 'border-gray-300 border-t-blue-600',
            sizeClasses[size],
            className,
        ),
    );

    $effect(() => {
        if (spinnerEl) {
            if (isBusy) {
                setBusy(spinnerEl, true);
            }

            if (announcement) {
                announceLoading(announcement);
            }
        }

        return () => {
            if (spinnerEl && isBusy) {
                setBusy(spinnerEl, false);
            }
        };
    });
</script>

<div bind:this={spinnerEl} class={classes} role="status" aria-label={label} aria-live="polite" aria-atomic="true">
    <span class="sr-only">{label}</span>
</div>
