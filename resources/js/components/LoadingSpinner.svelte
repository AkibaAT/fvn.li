<script lang="ts">
    import { announceLoading, setBusy } from '@/utils/accessibility';

    let {
        size = 'md',
        class: className = '',
        label = 'Loading',
        announcement,
        isBusy = true,
    }: {
        size?: 'sm' | 'md' | 'lg';
        class?: string;
        label?: string;
        announcement?: string;
        isBusy?: boolean;
    } = $props();

    const sizeClasses: Record<string, string> = {
        sm: 'h-4 w-4',
        md: 'h-6 w-6',
        lg: 'h-8 w-8',
    };

    let spinnerEl: HTMLDivElement;

    $effect(() => {
        if (spinnerEl) {
            spinnerEl.setAttribute('role', 'status');
            spinnerEl.setAttribute('aria-label', label);

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

<div
    bind:this={spinnerEl}
    class="animate-spin rounded-full border-2 border-gray-300 border-t-blue-600 {sizeClasses[size]} {className}"
    aria-live="polite"
    aria-atomic="true"
>
    <span class="sr-only">{label}</span>
</div>
