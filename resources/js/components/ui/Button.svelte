<script lang="ts" module>
    export type ButtonVariant = 'solid' | 'soft' | 'outline' | 'ghost' | 'link';
    export type ButtonTone = 'primary' | 'neutral' | 'danger' | 'success' | 'warning' | 'info';
    export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg' | 'icon-sm' | 'icon-md' | 'icon-lg';
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';
    import type { Attachment } from 'svelte/attachments';
    import type { HTMLAnchorAttributes, HTMLButtonAttributes } from 'svelte/elements';

    type ButtonLikeProps = HTMLButtonAttributes & HTMLAnchorAttributes;
    type ButtonAction = (node: HTMLElement) => void | { destroy?: () => void };

    interface Props extends ButtonLikeProps {
        variant?: ButtonVariant;
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
        tone = 'primary',
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

    const baseClasses =
        'inline-flex shrink-0 items-center justify-center gap-2 rounded-md font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50';

    const accentSolid = 'bg-accent text-on-accent hover:opacity-90';
    const neutralSolid = 'bg-fg text-surface hover:opacity-90';
    const quietSoft = 'bg-surface-alt text-fg-muted hover:text-fg';
    const quietOutline = 'border border-border-strong bg-surface text-fg hover:border-fg';
    const quietGhost = 'text-fg-muted hover:bg-surface-alt hover:text-fg';
    const quietLink = 'rounded-none p-0 text-fg-muted hover:text-fg hover:underline';

    const toneClasses: Record<ButtonTone, Record<ButtonVariant, string>> = {
        primary: {
            solid: accentSolid,
            soft: quietSoft,
            outline: quietOutline,
            ghost: quietGhost,
            link: quietLink,
        },
        neutral: {
            solid: neutralSolid,
            soft: quietSoft,
            outline: quietOutline,
            ghost: quietGhost,
            link: quietLink,
        },
        danger: {
            solid: 'bg-red-700 text-white hover:bg-red-800 dark:hover:bg-red-600',
            soft: 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300 dark:hover:bg-red-900/50',
            outline: 'border border-red-300 bg-surface text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40',
            ghost: 'text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/40',
            link: 'rounded-none p-0 text-red-700 hover:text-red-800 hover:underline dark:text-red-300 dark:hover:text-red-200',
        },
        success: { solid: neutralSolid, soft: quietSoft, outline: quietOutline, ghost: quietGhost, link: quietLink },
        warning: { solid: neutralSolid, soft: quietSoft, outline: quietOutline, ghost: quietGhost, link: quietLink },
        info: { solid: neutralSolid, soft: quietSoft, outline: quietOutline, ghost: quietGhost, link: quietLink },
    };

    const sizeClasses: Record<ButtonSize, string> = {
        xs: 'min-h-7 px-2.5 py-1 text-xs',
        sm: 'px-3 py-1.5 text-[13px]',
        md: 'px-4 py-2 text-sm leading-5',
        lg: 'px-5 py-2.5 text-base',
        'icon-sm': 'h-8 w-8 p-0',
        'icon-md': 'h-10 w-10 p-0',
        'icon-lg': 'h-12 w-12 p-0',
    };

    let isDisabled = $derived(disabled || loading);
    let classes = $derived(twMerge(clsx(baseClasses, toneClasses[tone][variant], sizeClasses[size], className)));
    let linkProps = $derived(restProps as any);

    $effect(() => {
        if (!ref || !action) return;
        const result = action(ref);
        return () => result?.destroy?.();
    });
</script>

{#snippet content()}
    {#if loading}
        <LoadingSpinner size="sm" currentColor isBusy={false} />
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
    <Link href={href} class={classes} aria-disabled={isDisabled} aria-label={ariaLabel} {...linkProps}>
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
