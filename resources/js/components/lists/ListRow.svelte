<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    interface Props extends HTMLAttributes<HTMLElement> {
        /** Optional slot before the media column, e.g. a drag handle. */
        leading?: Snippet;
        /** Optional fixed-width media column, e.g. a compact thumbnail. */
        media?: Snippet;
        /** Flexible main content. */
        body: Snippet;
        /** Optional trailing cluster, e.g. version, dates, actions. */
        aside?: Snippet;
        class?: string;
    }

    let { leading, media, body, aside, class: className = '', ...rest }: Props = $props();
</script>

<article
    {...rest}
    class={twMerge(clsx('flex items-start gap-2.5 border-b border-border py-2.5 last:border-b-0 md:items-center md:gap-3 lg:gap-3.5', className))}
>
    {#if leading}
        <div class="shrink-0 self-center">
            {@render leading()}
        </div>
    {/if}
    {#if media}
        <div class="shrink-0">
            {@render media()}
        </div>
    {/if}
    <div class="min-w-0 flex-1">
        {@render body()}
    </div>
    {#if aside}
        <div class="flex shrink-0 items-center gap-3">
            {@render aside()}
        </div>
    {/if}
</article>
