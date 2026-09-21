<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import type { TagSummary } from '@/types/tags';

    let { tags, metaTags }: { tags: TagSummary[]; metaTags: MetaTags } = $props();

    const popular = $derived([...tags].sort((a, b) => b.games_count - a.games_count).slice(0, 16));

    const groups = $derived.by(() => {
        const byLetter: Record<string, TagSummary[]> = {};
        for (const tag of tags) {
            const first = tag.name.charAt(0).toUpperCase();
            const letter = /[A-Z]/.test(first) ? first : '#';
            (byLetter[letter] ??= []).push(tag);
        }
        return Object.entries(byLetter).sort(([a], [b]) => (a === '#' ? -1 : b === '#' ? 1 : a.localeCompare(b)));
    });
</script>

<SeoHead {metaTags} />

<div class="space-y-8">
    <PageHeader
        title="Tags"
        count="{tags.length.toLocaleString()} tags"
        description="Browse the furry visual novel catalogue by genre, theme and content."
        class="mb-6"
    />

    <section aria-labelledby="popular-tags-heading">
        <h2 id="popular-tags-heading" class="mb-3 text-title font-semibold text-fg">Most used</h2>
        <ul class="flex flex-wrap gap-2">
            {#each popular as tag (tag.slug)}
                <li>
                    <Link
                        href={route('tags.show', tag.slug)}
                        class="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1 text-ui text-fg transition-colors hover:border-border-strong"
                    >
                        {tag.name}
                        <span class="text-fg-faint">{tag.games_count}</span>
                    </Link>
                </li>
            {/each}
        </ul>
    </section>

    <section aria-labelledby="all-tags-heading" class="border-t border-border pt-6">
        <h2 id="all-tags-heading" class="mb-4 text-title font-semibold text-fg">All tags</h2>
        <div class="columns-2 gap-8 sm:columns-3 lg:columns-4">
            {#each groups as [letter, letterTags] (letter)}
                <div class="mb-5 break-inside-avoid">
                    <h3 class="mb-1.5 text-2xs font-semibold tracking-wide text-fg-faint uppercase">{letter}</h3>
                    <ul class="space-y-1 text-ui">
                        {#each letterTags as tag (tag.slug)}
                            <li>
                                <Link href={route('tags.show', tag.slug)} class="text-fg hover:underline">{tag.name}</Link>
                                <span class="text-fg-faint">{tag.games_count}</span>
                            </li>
                        {/each}
                    </ul>
                </div>
            {/each}
        </div>
    </section>
</div>
