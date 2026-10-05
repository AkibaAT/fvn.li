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
    import { getErrorMessage } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { Button, Card, TextInput } from '@/components/ui';
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    let tags = $state<PrivateTag[]>([]);
    let name = $state('');
    let loading = $state(true);
    let selected = $state<number | null>(null);
    let games = $state<PrivateTagGame[]>([]);
    let nextPage = $state<string | null>(null);
    let loadingGames = $state(false);

    const byName = (a: PrivateTag, b: PrivateTag) => a.name.localeCompare(b.name);

    onMount(async () => {
        try {
            tags = await listPrivateTags();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not load private tags'));
        } finally {
            loading = false;
        }
    });

    async function create() {
        if (!name.trim()) return;
        try {
            tags = [...tags, await createPrivateTag(name.trim())].sort(byName);
            name = '';
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not create private tag'));
        }
    }

    async function rename(tag: PrivateTag) {
        const next = prompt('Rename private tag', tag.name)?.trim();
        if (!next || next === tag.name) return;
        try {
            await renamePrivateTag(tag.id, next);
            tags = tags.map((item) => (item.id === tag.id ? { ...item, name: next } : item)).sort(byName);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not rename private tag'));
        }
    }

    async function remove(tag: PrivateTag) {
        if (!confirm(`Delete the private tag “${tag.name}” from all games?`)) return;
        try {
            await deletePrivateTag(tag.id);
            tags = tags.filter((item) => item.id !== tag.id);
            if (selected === tag.id) {
                selected = null;
                games = [];
                nextPage = null;
            }
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not delete private tag'));
        }
    }

    async function showGames(tagId: number, pageUrl?: string) {
        if (loadingGames) return;
        loadingGames = true;
        try {
            const page = await privateTagGames(tagId, pageUrl);
            selected = tagId;
            games = pageUrl ? [...games, ...page.games] : page.games;
            nextPage = page.next;
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not load tagged games'));
        } finally {
            loadingGames = false;
        }
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
        <Button type="submit" disabled={!name.trim()}>Create</Button>
    </form>
    {#if loading}<p class="text-sm text-fg-muted">Loading…</p>{/if}
    <ul class="space-y-2">
        {#each tags as tag (tag.id)}
            <li class="flex items-center gap-3 rounded border border-border p-2">
                <button type="button" class="flex-1 text-left text-sm text-fg hover:underline" onclick={() => showGames(tag.id)}>
                    {tag.name} <span class="text-fg-muted">({tag.games_count})</span>
                </button>
                <button type="button" class="text-sm text-fg-muted hover:text-fg" aria-label={`Rename ${tag.name}`} onclick={() => rename(tag)}
                    >Rename</button
                >
                <button
                    type="button"
                    class="text-sm text-red-600 hover:underline dark:text-red-400"
                    aria-label={`Delete ${tag.name}`}
                    onclick={() => remove(tag)}>Delete</button
                >
            </li>
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
            <Button type="button" size="sm" variant="outline" class="mt-3" disabled={loadingGames} onclick={() => showGames(selected!, nextPage!)}
                >Load more</Button
            >
        {/if}
    {/if}
</Card>
