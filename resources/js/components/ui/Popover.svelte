<script lang="ts">
    import { cn } from '@/utils/cn';
    import type { Snippet } from 'svelte';

    interface Props {
        open?: boolean;
        onClose?: () => void;
        /** Element to refocus on Escape-close; defaults to the element focused when the popover opened. */
        focusTarget?: HTMLElement | null;
        class?: string;
        children?: Snippet;
    }

    let { open = $bindable(false), onClose, focusTarget, class: className = '', children }: Props = $props();

    let containerEl = $state<HTMLDivElement | null>(null);
    let openerEl: HTMLElement | null = null;

    const wrapperClass = $derived(cn('relative', className));

    $effect(() => {
        if (open) openerEl = document.activeElement as HTMLElement | null;
    });

    $effect(() => {
        if (!open || !containerEl) return;

        const container = containerEl;

        const handlePointerDown = (event: MouseEvent) => {
            if (!container.contains(event.target as Node)) close();
        };

        const handleKeydown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;
            close({ focusOpener: true });
        };

        // window (not document) so Escape reaches us both when it bubbles from
        // the focused element and when dispatched directly on window.
        window.addEventListener('mousedown', handlePointerDown);
        window.addEventListener('keydown', handleKeydown);
        return () => {
            window.removeEventListener('mousedown', handlePointerDown);
            window.removeEventListener('keydown', handleKeydown);
        };
    });

    function close(options?: { focusOpener?: boolean }) {
        open = false;
        onClose?.();
        if (options?.focusOpener) (focusTarget ?? openerEl)?.focus?.();
    }
</script>

<div bind:this={containerEl} class={wrapperClass}>
    {@render children?.()}
</div>
