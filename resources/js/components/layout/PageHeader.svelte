<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
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

<header class={twMerge(clsx('mb-8', align === 'center' && 'text-center', className))}>
    {#if backHref}
        <Link
            href={backHref}
            class={clsx('mb-3 inline-flex text-sm font-medium text-fg-muted transition-colors hover:text-fg', align === 'center' && 'justify-center')}
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
                <h1 class="text-[28px] leading-[1.15] font-bold tracking-[-0.025em] text-fg max-sm:text-[22px]">
                    {title}{#if count}<span class="ml-2.5 align-baseline text-[14px] font-normal text-fg-faint">{count}</span>{/if}
                </h1>

                {#if description}
                    <p
                        class={clsx(
                            'mt-2 text-[15px] leading-[1.5] whitespace-pre-line text-fg-muted max-sm:text-[14px]',
                            descriptionWidth === 'readable' && 'max-w-[640px]',
                        )}
                    >
                        {description}
                    </p>
                {/if}

                {#if metadata}
                    <div class="mt-2 text-[13px] text-fg-faint">{@render metadata()}</div>
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
