<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        isOpen: boolean;
        onClose: () => void;
        title?: string;
        children?: Snippet;
        size?: 'sm' | 'md' | 'lg' | 'xl';
        class?: string;
    }

    let { isOpen, onClose, title, children, size = 'md', class: className = '' }: Props = $props();

    const sizeClasses: Record<string, string> = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
    };

    function handleEscape(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            onClose();
        }
    }

    function handleBackdropClick(event: MouseEvent) {
        if (event.target === event.currentTarget) {
            onClose();
        }
    }

    $effect(() => {
        if (isOpen) {
            document.addEventListener('keydown', handleEscape);
            return () => document.removeEventListener('keydown', handleEscape);
        }
    });
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
            onclick={handleBackdropClick}
            role="presentation"
        ></div>

        <!-- Modal -->
        <div class="flex min-h-screen items-center justify-center p-4">
            <div
                class={`relative w-full rounded-lg bg-white shadow-xl dark:bg-gray-800 ${sizeClasses[size]} ${className}`}
                role="dialog"
                aria-modal="true"
                aria-labelledby={title ? 'modal-title' : undefined}
            >
                <!-- Header -->
                {#if title}
                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3
                            id="modal-title"
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {title}
                        </h3>
                        <button
                            onclick={onClose}
                            class="rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-300"
                            aria-label="Close modal"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                {/if}

                <!-- Content -->
                <div class="px-6 py-4">
                    {@render children?.()}
                </div>

                <!-- Close button when no title -->
                {#if !title}
                    <div class="absolute right-4 top-4">
                        <button
                            onclick={onClose}
                            class="rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-300"
                            aria-label="Close modal"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}
