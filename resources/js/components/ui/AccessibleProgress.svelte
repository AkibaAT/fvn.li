<script lang="ts">
    import { onMount } from 'svelte';
    import { useProgressTracking } from '@/hooks/useAccessibility.svelte';
    import { createProgressBar } from '@/utils/accessibility';

    interface Props {
        value: number;
        min?: number;
        max?: number;
        message?: string;
        showVisual?: boolean;
        class?: string;
        announceChanges?: boolean;
    }

    let {
        value,
        min = 0,
        max = 100,
        message = 'Progress',
        showVisual = true,
        class: className = '',
        announceChanges = true,
    }: Props = $props();

    let progressEl: HTMLDivElement;
    let progressBarRef: ReturnType<typeof createProgressBar> | null = null;
    const { startProgress, updateProgress, completeProgress } = useProgressTracking();

    let percentage = $derived(Math.round(((value - min) / (max - min)) * 100));

    onMount(() => {
        if (progressEl) {
            progressBarRef = createProgressBar(progressEl, min, max);
            startProgress(min, max, message);
        }

        return () => {
            if (progressBarRef) {
                progressBarRef.remove();
            }
        };
    });

    $effect(() => {
        if (announceChanges) {
            updateProgress(value, message);
        }

        if (progressBarRef) {
            progressBarRef.update(value, message);
        }
    });

    $effect(() => {
        if (value >= max) {
            completeProgress(`${message} completed`);
        }
    });
</script>

<div bind:this={progressEl} class={`relative ${className}`}>
    <!-- Visual progress bar (optional) -->
    {#if showVisual}
        <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
            <div
                class="bg-blue-600 h-2.5 rounded-full transition-all duration-300 ease-in-out"
                style="width: {percentage}%"
                role="progressbar"
                aria-valuenow={value}
                aria-valuemin={min}
                aria-valuemax={max}
                aria-label={message}
            ></div>
        </div>
    {/if}

    <!-- Screen reader only progress info -->
    <div class="sr-only" aria-live="polite" aria-atomic="true">
        {message}: {percentage}%
    </div>
</div>
