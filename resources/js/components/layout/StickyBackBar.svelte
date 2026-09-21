<script lang="ts" module>
    export interface StickyBackBarSection {
        id: string;
        label: string;
    }
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronLeftIcon from '@/components/icons/ChevronLeft.svelte';
    import { cn } from '@/utils/cn';

    interface Props {
        href: string;
        label: string;
        /** In-page anchors listed beside the back link, in document order; the one currently scrolled to is highlighted. */
        sections?: StickyBackBarSection[];
    }

    let { href, label, sections = [] }: Props = $props();

    let barEl = $state<HTMLElement | null>(null);
    let activeId = $state<string | null>(null);

    $effect(() => {
        const ids = sections.map((section) => section.id);
        if (ids.length === 0 || !barEl) return;
        const bar = barEl;
        let frame = 0;

        const update = () => {
            frame = 0;
            const threshold = bar.getBoundingClientRect().bottom + 24;
            const present = ids.map((id) => document.getElementById(id)).filter((el): el is HTMLElement => el !== null);
            let current: string | null = null;
            for (const el of present) {
                if (el.getBoundingClientRect().top <= threshold) current = el.id;
            }
            const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
            if (atBottom && present.length > 0 && current !== null) current = present[present.length - 1].id;
            activeId = current;
        };

        const schedule = () => {
            if (!frame) frame = requestAnimationFrame(update);
        };

        update();
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);
        return () => {
            if (frame) cancelAnimationFrame(frame);
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
        };
    });
</script>

<div bind:this={barEl} class="sticky top-14 z-40 -mt-2 mb-3 bg-page py-2">
    <div class="flex h-10 items-center gap-2 rounded-lg border border-border bg-surface pr-1.5 pl-2">
        <Link
            {href}
            class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-1 text-ui font-medium text-fg-muted transition-colors hover:text-fg"
            aria-label={sections.length > 0 ? label : undefined}
        >
            <ChevronLeftIcon class="h-4 w-4" />
            <span class={sections.length > 0 ? 'hidden sm:inline' : undefined}>{label}</span>
        </Link>
        {#if sections.length > 0}
            <span class="h-4 w-px shrink-0 bg-border" aria-hidden="true"></span>
            <nav aria-label="Page sections" class="min-w-0 flex-1 scrollbar-none overflow-x-auto">
                <ul class="flex items-center gap-0.5 whitespace-nowrap">
                    {#each sections as section (section.id)}
                        <li>
                            <a
                                href="#{section.id}"
                                aria-current={activeId === section.id ? 'location' : undefined}
                                class={cn(
                                    'inline-flex items-center rounded-md px-2 py-1 text-ui transition-colors',
                                    activeId === section.id ? 'bg-surface-alt font-medium text-fg' : 'text-fg-muted hover:text-fg',
                                )}
                            >
                                {section.label}
                            </a>
                        </li>
                    {/each}
                </ul>
            </nav>
        {/if}
    </div>
</div>
