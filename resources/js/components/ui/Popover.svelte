<script lang="ts">
    import { cn } from '@/utils/cn';
    import type { Snippet } from 'svelte';

    interface Props {
        open?: boolean;
        onClose?: () => void;
        focusTarget?: HTMLElement | null;
        class?: string;
        children?: Snippet;
    }

    let { open = $bindable(false), onClose, focusTarget, class: className = '', children }: Props = $props();

    const PANEL_SELECTOR = '[role="dialog"], [role="menu"]';
    const FOCUSABLE_SELECTOR = 'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]';

    let containerEl = $state<HTMLDivElement | null>(null);
    let openerEl: HTMLElement | null = null;
    let wasOpen = false;

    const wrapperClass = $derived(cn('relative', className));

    function focusPanel(container: HTMLElement) {
        const panel = container.querySelector<HTMLElement>(PANEL_SELECTOR);
        if (!panel || panel.contains(document.activeElement)) return;
        const first = Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).find((el) => el.tabIndex >= 0);
        if (first) {
            first.focus();
        } else {
            panel.tabIndex = -1;
            panel.focus();
        }
    }

    function restoreFocus(container: HTMLElement | null) {
        const active = document.activeElement;
        const focusLost = !active || active === document.body || !active.isConnected;
        if (focusLost || container?.contains(active)) (focusTarget ?? openerEl)?.focus?.();
    }

    $effect.pre(() => {
        if (open && !wasOpen) openerEl = document.activeElement as HTMLElement | null;
    });

    $effect(() => {
        if (open && !wasOpen) {
            wasOpen = true;
            if (containerEl) focusPanel(containerEl);
        } else if (!open && wasOpen) {
            wasOpen = false;
            restoreFocus(containerEl);
        }
    });

    $effect(() => {
        if (!open || !containerEl) return;

        const container = containerEl;

        const handlePointerDown = (event: MouseEvent) => {
            if (!container.contains(event.target as Node)) close();
        };

        const handleKeydown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') close();
        };

        const handleFocusout = (event: FocusEvent) => {
            const next = event.relatedTarget as Node | null;
            if (next && !container.contains(next)) close();
        };

        window.addEventListener('mousedown', handlePointerDown);
        window.addEventListener('keydown', handleKeydown);
        container.addEventListener('focusout', handleFocusout);
        return () => {
            window.removeEventListener('mousedown', handlePointerDown);
            window.removeEventListener('keydown', handleKeydown);
            container.removeEventListener('focusout', handleFocusout);
        };
    });

    function close() {
        open = false;
        onClose?.();
    }
</script>

<div bind:this={containerEl} class={wrapperClass}>
    {@render children?.()}
</div>
