<script lang="ts">
    import { cn } from '@/utils/cn';
    import { Link } from '@inertiajs/svelte';
    import clsx from 'clsx';
    import type { Snippet } from 'svelte';

    interface Props {
        title: string;
        /** Optional count rendered inline after the title, at baseline. */
        count?: string;
        description?: string;
        backHref?: string;
        backLabel?: string;
        leading?: Snippet;
        metadata?: Snippet;
        actions?: Snippet;
        align?: 'start' | 'center';
        descriptionWidth?: 'readable' | 'full';
        class?: string;
    }

    let {
        title,
        count,
        description,
        backHref,
        backLabel = 'Back',
        leading,
        metadata,
        actions,
        align = 'start',
        descriptionWidth = 'readable',
        class: className,
    }: Props = $props();
</script>

<header class={cn('mb-8', align === 'center' && 'text-center', className)}>
    {#if backHref}
        <Link
            href={backHref}
            class={clsx('mb-3 inline-flex text-sm font-medium text-fg-muted transition-colors hover:text-fg', align === 'center' && 'justify-center')}
        >
            {backLabel}
        </Link>
    {/if}

    <div class={clsx('flex gap-x-4 gap-y-3', align === 'center' ? 'flex-col items-center' : 'flex-wrap items-start justify-between')}>
        <div class={clsx('flex min-w-0 gap-3', align === 'center' && 'mx-auto justify-center')}>
            {#if leading}
                <div class="shrink-0">{@render leading()}</div>
            {/if}

            <div class="min-w-0">
                <h1 class="text-display font-bold text-fg max-sm:text-2xl">
                    {title}{#if count}<span class="ml-2.5 align-baseline text-sm font-normal text-fg-faint">{count}</span>{/if}
                </h1>

                {#if description}
                    <p
                        class={clsx(
                            'mt-2 text-md leading-normal whitespace-pre-line text-fg-muted max-sm:text-sm',
                            descriptionWidth === 'readable' && 'max-w-160',
                        )}
                    >
                        {description}
                    </p>
                {/if}

                {#if metadata}
                    <div class="mt-2 text-ui text-fg-faint">{@render metadata()}</div>
                {/if}
            </div>
        </div>

        {#if actions}
            <div class={clsx('flex shrink-0 flex-wrap items-center gap-3', align === 'center' && 'justify-center')}>
                {@render actions()}
            </div>
        {/if}
    </div>
</header>
