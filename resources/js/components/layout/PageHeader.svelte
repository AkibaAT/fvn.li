<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import clsx from 'clsx';
    import type { Snippet } from 'svelte';

    interface Props {
        title: string;
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

<header class={clsx('mb-8', align === 'center' && 'text-center', className)}>
    {#if backHref}
        <Link
            href={backHref}
            class={clsx(
                'mb-3 inline-flex text-sm font-medium text-gray-600 transition-colors hover:text-gray-950 dark:text-gray-400 dark:hover:text-white',
                align === 'center' && 'justify-center',
            )}
        >
            {backLabel}
        </Link>
    {/if}

    <div class={clsx('flex flex-col gap-4 sm:flex-row sm:justify-between', align === 'center' ? 'sm:items-center' : 'sm:items-start')}>
        <div class={clsx('flex min-w-0 gap-3', align === 'center' && 'mx-auto justify-center')}>
            {#if leading}
                <div class="shrink-0">{@render leading()}</div>
            {/if}

            <div class="min-w-0">
                <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{title}</h1>

                {#if description}
                    <p
                        class={clsx(
                            'mt-2 text-base whitespace-pre-line text-gray-600 dark:text-gray-400',
                            descriptionWidth === 'readable' && 'max-w-3xl',
                        )}
                    >
                        {description}
                    </p>
                {/if}

                {#if metadata}
                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">{@render metadata()}</div>
                {/if}
            </div>
        </div>

        {#if actions}
            <div class={clsx('flex shrink-0 flex-wrap items-center gap-3', align === 'center' && 'justify-center sm:justify-end')}>
                {@render actions()}
            </div>
        {/if}
    </div>
</header>
