<script lang="ts">
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
    <div {@attach attachment} class="rounded-lg bg-gray-100 {containerSizeClasses[size]} dark:bg-gray-700 {className}">
        <svg class="{sizeClasses[size]} text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
        </svg>
    </div>
{:else}
    <button
        {@attach attachment}
        class="cursor-move rounded-lg bg-gray-200 {containerSizeClasses[
            size
        ]} text-gray-700 transition-colors hover:bg-gray-300 disabled:opacity-50 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 {className}"
        aria-label="Drag to reorder"
        title="Drag to reorder"
        {disabled}
    >
        <svg class={sizeClasses[size]} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
        </svg>
    </button>
{/if}
