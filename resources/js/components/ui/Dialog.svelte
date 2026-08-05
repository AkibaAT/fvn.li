<script lang="ts" module>
    export type DialogSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';
</script>

<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';
    import Button from './Button.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import { isDialogBackdropClick } from '@/utils/dialog';

    interface Props {
        open: boolean;
        onClose: () => void;
        title?: string;
        size?: DialogSize;
        children?: Snippet;
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
        size = 'md',
        children,
        footer,
        class: className = '',
        bodyClass = '',
        closeLabel = 'Close dialog',
        labelledBy,
        describedBy,
        id,
    }: Props = $props();

    let dialogEl = $state<HTMLDialogElement | null>(null);
    let openerEl: HTMLElement | null = null;

    const sizeClasses: Record<DialogSize, string> = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
        full: 'max-w-6xl',
    };

    const titleId = $derived(labelledBy ?? (title ? `dialog-title-${title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}` : undefined));
    const shellClass = $derived(
        twMerge(
            clsx(
                'm-auto max-h-[90vh] w-[calc(100%-2rem)] overflow-hidden rounded-lg border border-gray-200 bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100',
                sizeClasses[size],
                className,
            ),
        ),
    );

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
    class={shellClass}
    aria-labelledby={titleId}
    aria-describedby={describedBy}
    onclick={(event) => {
        if (isDialogBackdropClick(dialogEl, event)) close();
    }}
    onclose={() => {
        if (open) onClose();
    }}
    oncancel={(event) => {
        event.preventDefault();
        close();
    }}
>
    {#if title}
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h2 id={titleId} class="text-lg font-semibold text-gray-900 dark:text-white">{title}</h2>
            <Button variant="ghost" tone="neutral" size="icon-sm" onclick={close} ariaLabel={closeLabel}>
                <XMarkIcon class="h-4 w-4" />
            </Button>
        </div>
    {/if}

    <div class={twMerge(clsx('max-h-[calc(90vh-8rem)] overflow-y-auto px-6 py-4', bodyClass))}>
        {@render children?.()}
    </div>

    {#if footer}
        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-700">
            {@render footer()}
        </div>
    {/if}
</dialog>
