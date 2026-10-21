<script lang="ts">
    import { createPrivateTag, listPrivateTags, setGamePrivateTag, type PrivateTag } from '@/api/private-tags';
    import type { PrivateTagRef } from '@/types/game';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import { Button, Checkbox, Dialog, TextInput } from '@/components/ui';

    let {
        gameId,
        assigned = [],
        onChange,
    }: {
        gameId: number;
        assigned?: PrivateTagRef[];
        onChange?: (tags: PrivateTagRef[]) => void;
    } = $props();
    let open = $state(false);
    let pending = $state<number[]>([]);
    let tags = $state<PrivateTag[]>([]);
    let attached = $state<number[]>([]);
    const hasTags = $derived(assigned.length > 0);
    let newName = $state('');

    const loadAction = useAsyncAction();
    const toggleAction = useAsyncAction();
    const createAction = useAsyncAction();

    async function show() {
        open = true;
        const loaded = await loadAction.run(listPrivateTags, { fallbackError: 'Could not load private tags' });
        if (!loaded) return;
        tags = loaded;
        attached = assigned.map((tag) => tag.id);
    }

    function commit() {
        onChange?.(tags.filter((tag) => attached.includes(tag.id)).map(({ id, name }) => ({ id, name })));
    }

    async function toggle(tag: PrivateTag) {
        if (pending.includes(tag.id)) return;
        const add = !attached.includes(tag.id);
        attached = add ? [...attached, tag.id] : attached.filter((id) => id !== tag.id);
        pending = [...pending, tag.id];
        const saved = await toggleAction.run(
            async () => {
                await setGamePrivateTag(gameId, tag.id, add);
                return true;
            },
            { fallbackError: 'Could not update private tag' },
        );
        pending = pending.filter((id) => id !== tag.id);
        if (saved) commit();
        else attached = add ? attached.filter((id) => id !== tag.id) : [...attached, tag.id];
    }

    async function create() {
        if (!newName.trim() || createAction.isLoading) return;
        const tag = await createAction.run(
            async () => {
                const created = await createPrivateTag(newName.trim());
                await setGamePrivateTag(gameId, created.id, true);
                return created;
            },
            { fallbackError: 'Could not create private tag' },
        );
        if (!tag) return;
        tags = [...tags, tag].sort((a, b) => a.name.localeCompare(b.name));
        attached = [...attached, tag.id];
        newName = '';
        commit();
    }
</script>

<button type="button" onclick={show} class="cursor-pointer text-fg-muted transition-colors hover:text-fg">
    {hasTags ? 'Edit private tags' : 'Private tags'}
</button>

<Dialog {open} onClose={() => (open = false)} title="Private tags" size="sm">
    <p class="mb-3 text-sm text-fg-muted">Only you can see these tags.</p>
    {#if loadAction.isLoading}
        <p class="text-sm text-fg-muted">Loading tags…</p>
    {:else if tags.length === 0}
        <p class="text-sm text-fg-muted">You have no private tags yet. Add one below.</p>
    {/if}
    <div class="flex flex-col gap-2">
        {#each tags as tag (tag.id)}
            <Checkbox
                label={tag.name}
                checked={attached.includes(tag.id)}
                onchange={() => toggle(tag)}
                disabled={loadAction.isLoading || pending.includes(tag.id)}
            />
        {/each}
    </div>
    <form
        class="mt-4 flex gap-2"
        onsubmit={(event) => {
            event.preventDefault();
            void create();
        }}
    >
        <TextInput bind:value={newName} maxlength={64} placeholder="New private tag" aria-label="New private tag" />
        <Button type="submit" disabled={loadAction.isLoading || createAction.isLoading || !newName.trim()}>Add</Button>
    </form>
</Dialog>
