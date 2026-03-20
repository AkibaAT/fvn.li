<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLButtonAttributes } from 'svelte/elements';

    interface Props extends HTMLButtonAttributes {
        variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'success';
        size?: 'sm' | 'md' | 'lg';
        loading?: boolean;
        icon?: Snippet;
        iconPosition?: 'left' | 'right';
        children?: Snippet;
        class?: string;
    }

    let {
        variant = 'primary',
        size = 'md',
        loading = false,
        icon,
        iconPosition = 'left',
        children,
        class: className = '',
        disabled,
        ...restProps
    }: Props = $props();

    const baseClasses = 'inline-flex items-center justify-center rounded-md font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

    const variantClasses: Record<string, string> = {
        primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 dark:bg-blue-700 dark:hover:bg-blue-600',
        secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        outline: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        ghost: 'text-gray-700 hover:bg-gray-100 focus:ring-gray-500 dark:text-gray-300 dark:hover:bg-gray-800',
        danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-700 dark:hover:bg-red-600',
        success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 dark:bg-green-700 dark:hover:bg-green-600',
    };

    const sizeClasses: Record<string, string> = {
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base',
    };

    let isDisabled = $derived(disabled || loading);
</script>

<button
    class={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
    disabled={isDisabled}
    aria-disabled={isDisabled}
    {...restProps}
>
    {#if loading}
        <svg
            class="h-4 w-4 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="sr-only">Loading...</span>
    {/if}
    {#if !loading && icon && iconPosition === 'left'}
        <span class="mr-2" aria-hidden="true">{@render icon()}</span>
    {/if}
    {@render children?.()}
    {#if !loading && icon && iconPosition === 'right'}
        <span class="ml-2" aria-hidden="true">{@render icon()}</span>
    {/if}
</button>
