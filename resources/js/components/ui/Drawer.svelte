<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';
    import Button from './Button.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';

    interface Props {
        open: boolean;
        onClose: () => void;
        title: string;
        children?: Snippet;
        actions?: Snippet;
        footer?: Snippet;
        class?: string;
        bodyClass?: string;
        closeLabel?: string;
        labelledBy?: string;
        describedBy?: string;
        id?: string;
    }

    let {
        open,
        onClose,
        title,
        children,
        actions,
        footer,
        class: className = '',
        bodyClass = '',
        closeLabel = 'Close drawer',
        labelledBy,
        describedBy,
        id,
    }: Props = $props();

    let dialogEl = $state<HTMLDialogElement | null>(null);
    let panelEl = $state<HTMLDivElement | null>(null);
    let openerEl: HTMLElement | null = null;

    const titleId = $derived(labelledBy ?? `drawer-title-${title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`);
    const panelClass = $derived(twMerge(clsx('ml-auto flex h-full w-full max-w-md flex-col bg-white shadow-2xl dark:bg-gray-900', className)));

    $effect(() => {
        if (!dialogEl) return;

        if (open && !dialogEl.open) {
            openerEl = document.activeElement as HTMLElement | null;
            dialogEl.showModal();
        } else if (!open && dialogEl.open) {
            dialogEl.close();
        }
    });

    function close() {
        onClose();
        openerEl?.focus?.();
    }
</script>

<dialog
    {id}
    bind:this={dialogEl}
    class="h-full max-h-none w-full max-w-none border-0 bg-transparent p-0 backdrop:bg-black/50 backdrop:backdrop-blur-sm"
    aria-modal="true"
    aria-labelledby={titleId}
    aria-describedby={describedBy}
    onclick={(event) => {
        if (event.target instanceof Node && !panelEl?.contains(event.target)) close();
    }}
    onclose={() => {
        if (open) onClose();
    }}
    oncancel={(event) => {
        event.preventDefault();
        close();
    }}
>
    <div bind:this={panelEl} class={panelClass}>
        <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 id={titleId} class="text-lg font-semibold text-gray-900 dark:text-white">{title}</h2>
            <div class="flex items-center gap-2">
                {@render actions?.()}
                <Button variant="ghost" tone="neutral" size="icon-sm" onclick={close} ariaLabel={closeLabel}>
                    <XMarkIcon class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <div class={twMerge(clsx('flex-1 overflow-y-auto', bodyClass))}>
            {@render children?.()}
        </div>

        {#if footer}
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                {@render footer()}
            </div>
        {/if}
    </div>
</dialog>
