<script lang="ts">
    import {
        createPrivateTag,
        deletePrivateTag,
        listPrivateTags,
        privateTagGames,
        renamePrivateTag,
        type PrivateTag,
        type PrivateTagGame,
    } from '@/api/private-tags';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import { Button, Card, TextInput } from '@/components/ui';
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    let tags = $state<PrivateTag[]>([]);
    let name = $state('');
    let selected = $state<number | null>(null);
    let games = $state<PrivateTagGame[]>([]);
    let nextPage = $state<string | null>(null);
    let hasLoaded = $state(false);

    const loadAction = useAsyncAction();
    const tagAction = useAsyncAction();
    const gamesAction = useAsyncAction();

    const byName = (a: PrivateTag, b: PrivateTag) => a.name.localeCompare(b.name);

    onMount(async () => {
        const loaded = await loadAction.run(listPrivateTags, { fallbackError: 'Could not load private tags' });
        tags = loaded ?? [];
        hasLoaded = loaded !== undefined;
    });

    async function create() {
        if (!name.trim() || tagAction.isLoading) return;
        const created = await tagAction.run(() => createPrivateTag(name.trim()), { fallbackError: 'Could not create private tag' });
        if (!created) return;
        tags = [...tags, created].sort(byName);
        name = '';
    }

    async function rename(tag: PrivateTag) {
        if (tagAction.isLoading) return;
        const next = prompt('Rename private tag', tag.name)?.trim();
        if (!next || next === tag.name) return;
        const renamed = await tagAction.run(
            async () => {
                await renamePrivateTag(tag.id, next);
                return true;
            },
            { fallbackError: 'Could not rename private tag' },
        );
        if (!renamed) return;
        tags = tags.map((item) => (item.id === tag.id ? { ...item, name: next } : item)).sort(byName);
    }

    async function remove(tag: PrivateTag) {
        if (tagAction.isLoading || !confirm(`Delete the private tag “${tag.name}” from all games?`)) return;
        const deleted = await tagAction.run(
            async () => {
                await deletePrivateTag(tag.id);
                return true;
            },
            { fallbackError: 'Could not delete private tag' },
        );
        if (!deleted) return;
        tags = tags.filter((item) => item.id !== tag.id);
        if (selected === tag.id) {
            selected = null;
            games = [];
            nextPage = null;
        }
    }

    async function showGames(tagId: number, pageUrl?: string) {
        if (gamesAction.isLoading) return;
        const page = await gamesAction.run(() => privateTagGames(tagId, pageUrl), { fallbackError: 'Could not load tagged games' });
        if (!page) return;
        selected = tagId;
        games = pageUrl ? [...games, ...page.games] : page.games;
        nextPage = page.next;
    }
</script>

<Card variant="flat" padding="lg">
    <h2 class="mb-2 text-title font-semibold text-fg">Private tags</h2>
    <p class="mb-4 text-sm text-fg-muted">Organize any visible game. These labels are visible only to you.</p>
    <form
        class="mb-4 flex gap-2"
        onsubmit={(event) => {
            event.preventDefault();
            void create();
        }}
    >
        <TextInput bind:value={name} maxlength={64} placeholder="Tag name" aria-label="Tag name" />
        <Button type="submit" disabled={tagAction.isLoading || !name.trim()}>Create</Button>
    </form>
    {#if loadAction.isLoading}<p class="text-sm text-fg-muted">Loading…</p>{/if}
    <ul class="space-y-2">
        {#each tags as tag (tag.id)}
            <li class="flex items-center gap-3 rounded border border-border p-2">
                <button type="button" class="flex-1 text-left text-sm text-fg hover:underline" onclick={() => showGames(tag.id)}>
                    {tag.name} <span class="text-fg-muted">({tag.games_count})</span>
                </button>
                <button
                    type="button"
                    class="text-sm text-fg-muted hover:text-fg"
                    aria-label={`Rename ${tag.name}`}
                    disabled={tagAction.isLoading}
                    onclick={() => rename(tag)}>Rename</button
                >
                <button
                    type="button"
                    class="text-sm text-red-600 hover:underline dark:text-red-400"
                    aria-label={`Delete ${tag.name}`}
                    disabled={tagAction.isLoading}
                    onclick={() => remove(tag)}>Delete</button
                >
            </li>
        {:else}
            {#if hasLoaded}
                <li class="text-sm text-fg-muted">No private tags yet. Create one above, or tag a game from its page.</li>
            {/if}
        {/each}
    </ul>
    {#if selected !== null}
        <h3 class="mt-5 mb-2 font-semibold text-fg">Tagged games</h3>
        <ul class="space-y-1">
            {#each games as game (game.id)}
                <li><Link class="text-sm text-fg hover:underline" href={route('games.show', game.slug)}>{game.name}</Link></li>
            {/each}
        </ul>
        {#if nextPage}
            <Button
                type="button"
                size="sm"
                variant="outline"
                class="mt-3"
                disabled={gamesAction.isLoading}
                onclick={() => showGames(selected!, nextPage!)}>Load more</Button
            >
        {/if}
    {/if}
</Card>
