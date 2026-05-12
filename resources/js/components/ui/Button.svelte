<script lang="ts" module>
    export type ButtonVariant = 'solid' | 'soft' | 'outline' | 'ghost' | 'link';
    export type ButtonTone = 'primary' | 'neutral' | 'danger' | 'success' | 'warning' | 'info';
    export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'icon-sm' | 'icon-md' | 'icon-lg';
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';
    import type { Attachment } from 'svelte/attachments';
    import type { HTMLAnchorAttributes, HTMLButtonAttributes } from 'svelte/elements';

    type ButtonLikeProps = HTMLButtonAttributes & HTMLAnchorAttributes;
    type ButtonAction = (node: HTMLElement) => void | { destroy?: () => void };

    interface Props extends ButtonLikeProps {
        variant?: ButtonVariant | 'primary' | 'secondary' | 'danger' | 'success';
        tone?: ButtonTone;
        size?: ButtonSize;
        loading?: boolean;
        icon?: Snippet;
        iconPosition?: 'left' | 'right';
        children?: Snippet;
        class?: string;
        href?: string;
        external?: boolean;
        inertia?: boolean;
        ariaLabel?: string;
        ref?: HTMLElement | null;
        attachment?: Attachment<HTMLElement>;
        action?: ButtonAction;
    }

    let {
        variant = 'solid',
        tone,
        size = 'md',
        loading = false,
        icon,
        iconPosition = 'left',
        children,
        class: className = '',
        disabled,
        href,
        external = false,
        inertia = true,
        ariaLabel,
        ref = $bindable(null),
        attachment,
        action,
        ...restProps
    }: Props = $props();

    const normalizedVariant = $derived(
        variant === 'primary' || variant === 'danger' || variant === 'success'
            ? 'solid'
            : variant === 'secondary'
              ? 'soft'
              : variant,
    );
    const normalizedTone = $derived(tone ?? (variant === 'danger' || variant === 'success' ? variant : variant === 'secondary' ? 'neutral' : 'primary'));

    const baseClasses =
        'inline-flex shrink-0 items-center justify-center gap-2 rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50 dark:focus:ring-offset-gray-900';

    const toneClasses: Record<ButtonTone, Record<ButtonVariant, string>> = {
        primary: {
            solid: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 dark:bg-blue-600 dark:hover:bg-blue-500',
            soft: 'bg-blue-50 text-blue-700 hover:bg-blue-100 focus:ring-blue-500 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/60',
            outline:
                'border border-blue-200 bg-white text-blue-700 hover:bg-blue-50 focus:ring-blue-500 dark:border-blue-700/60 dark:bg-gray-900 dark:text-blue-300 dark:hover:bg-blue-950/50',
            ghost: 'text-blue-700 hover:bg-blue-50 focus:ring-blue-500 dark:text-blue-300 dark:hover:bg-blue-950/50',
            link: 'rounded-none p-0 text-blue-600 hover:text-blue-700 hover:underline focus:ring-blue-500 dark:text-blue-400 dark:hover:text-blue-300',
        },
        neutral: {
            solid: 'bg-gray-900 text-white hover:bg-gray-800 focus:ring-gray-500 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-white',
            soft: 'bg-gray-100 text-gray-800 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
            outline:
                'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
            ghost: 'text-gray-700 hover:bg-gray-100 focus:ring-gray-500 dark:text-gray-300 dark:hover:bg-gray-800',
            link: 'rounded-none p-0 text-gray-700 hover:text-gray-950 hover:underline focus:ring-gray-500 dark:text-gray-300 dark:hover:text-white',
        },
        danger: {
            solid: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-600 dark:hover:bg-red-500',
            soft: 'bg-red-50 text-red-700 hover:bg-red-100 focus:ring-red-500 dark:bg-red-950/50 dark:text-red-300 dark:hover:bg-red-900/60',
            outline:
                'border border-red-200 bg-white text-red-700 hover:bg-red-50 focus:ring-red-500 dark:border-red-700/60 dark:bg-gray-900 dark:text-red-300 dark:hover:bg-red-950/50',
            ghost: 'text-red-700 hover:bg-red-50 focus:ring-red-500 dark:text-red-300 dark:hover:bg-red-950/50',
            link: 'rounded-none p-0 text-red-600 hover:text-red-700 hover:underline focus:ring-red-500 dark:text-red-400 dark:hover:text-red-300',
        },
        success: {
            solid: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 dark:bg-green-600 dark:hover:bg-green-500',
            soft: 'bg-green-50 text-green-700 hover:bg-green-100 focus:ring-green-500 dark:bg-green-950/50 dark:text-green-300 dark:hover:bg-green-900/60',
            outline:
                'border border-green-200 bg-white text-green-700 hover:bg-green-50 focus:ring-green-500 dark:border-green-700/60 dark:bg-gray-900 dark:text-green-300 dark:hover:bg-green-950/50',
            ghost: 'text-green-700 hover:bg-green-50 focus:ring-green-500 dark:text-green-300 dark:hover:bg-green-950/50',
            link: 'rounded-none p-0 text-green-600 hover:text-green-700 hover:underline focus:ring-green-500 dark:text-green-400 dark:hover:text-green-300',
        },
        warning: {
            solid: 'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-500 dark:bg-amber-600 dark:hover:bg-amber-500',
            soft: 'bg-amber-50 text-amber-800 hover:bg-amber-100 focus:ring-amber-500 dark:bg-amber-950/50 dark:text-amber-300 dark:hover:bg-amber-900/60',
            outline:
                'border border-amber-200 bg-white text-amber-800 hover:bg-amber-50 focus:ring-amber-500 dark:border-amber-700/60 dark:bg-gray-900 dark:text-amber-300 dark:hover:bg-amber-950/50',
            ghost: 'text-amber-800 hover:bg-amber-50 focus:ring-amber-500 dark:text-amber-300 dark:hover:bg-amber-950/50',
            link: 'rounded-none p-0 text-amber-700 hover:text-amber-800 hover:underline focus:ring-amber-500 dark:text-amber-400 dark:hover:text-amber-300',
        },
        info: {
            solid: 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500 dark:bg-indigo-600 dark:hover:bg-indigo-500',
            soft: 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 focus:ring-indigo-500 dark:bg-indigo-950/50 dark:text-indigo-300 dark:hover:bg-indigo-900/60',
            outline:
                'border border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50 focus:ring-indigo-500 dark:border-indigo-700/60 dark:bg-gray-900 dark:text-indigo-300 dark:hover:bg-indigo-950/50',
            ghost: 'text-indigo-700 hover:bg-indigo-50 focus:ring-indigo-500 dark:text-indigo-300 dark:hover:bg-indigo-950/50',
            link: 'rounded-none p-0 text-indigo-600 hover:text-indigo-700 hover:underline focus:ring-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300',
        },
    };

    const sizeClasses: Record<ButtonSize, string> = {
        xs: 'min-h-7 px-2.5 py-1 text-xs',
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-sm leading-5',
        lg: 'px-5 py-2.5 text-base',
        'icon-sm': 'h-8 w-8 p-0',
        'icon-md': 'h-10 w-10 p-0',
        'icon-lg': 'h-12 w-12 p-0',
    };

    let isDisabled = $derived(disabled || loading);
    let classes = $derived(twMerge(clsx(baseClasses, toneClasses[normalizedTone][normalizedVariant], sizeClasses[size], className)));

    $effect(() => {
        if (!ref || !action) return;
        const result = action(ref);
        return () => result?.destroy?.();
    });
</script>

{#snippet content()}
    {#if loading}
        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
        </svg>
        <span class="sr-only">Loading...</span>
    {/if}
    {#if !loading && icon && iconPosition === 'left'}
        <span aria-hidden="true">{@render icon()}</span>
    {/if}
    {@render children?.()}
    {#if !loading && icon && iconPosition === 'right'}
        <span aria-hidden="true">{@render icon()}</span>
    {/if}
{/snippet}

{#if href && !external && inertia}
    <Link href={href} class={classes} aria-disabled={isDisabled} aria-label={ariaLabel} {...restProps}>
        {@render content()}
    </Link>
{:else if href}
    <a
        {@attach attachment}
        bind:this={ref}
        href={href}
        class={classes}
        aria-disabled={isDisabled}
        aria-label={ariaLabel}
        target={external ? '_blank' : undefined}
        rel={external ? 'noopener' : undefined}
        {...restProps}
    >
        {@render content()}
    </a>
{:else}
    <button {@attach attachment} bind:this={ref} class={classes} disabled={isDisabled} aria-disabled={isDisabled} aria-label={ariaLabel} {...restProps}>
        {@render content()}
    </button>
{/if}
