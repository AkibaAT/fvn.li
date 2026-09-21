<script lang="ts">
    import BarsIcon from '@/components/icons/Bars.svelte';
    import { Button } from '@/components/ui';
    import type { Attachment } from 'svelte/attachments';

    interface Props {
        disabled?: boolean;
        class?: string;
        size?: 'sm' | 'md' | 'lg';
        attachment?: Attachment<HTMLElement>;
    }

    let { disabled = false, class: className = '', size = 'md', attachment }: Props = $props();

    const sizeClasses: Record<string, string> = {
        sm: 'h-3 w-3',
        md: 'h-4 w-4',
        lg: 'h-5 w-5',
    };

    const containerSizeClasses: Record<string, string> = {
        sm: 'p-1',
        md: 'p-2',
        lg: 'p-2',
    };
</script>

{#if disabled}
    <div {@attach attachment} class="rounded-md bg-surface-alt {containerSizeClasses[size]} {className}">
        <BarsIcon class="{sizeClasses[size]} text-fg-faint" />
    </div>
{:else}
    <Button
        type="button"
        {attachment}
        variant="soft"
        tone="neutral"
        size="icon-sm"
        class="cursor-move rounded-md {containerSizeClasses[size]} {className}"
        aria-label="Drag to reorder"
        title="Drag to reorder"
        {disabled}
    >
        <BarsIcon class={sizeClasses[size]} />
    </Button>
{/if}
