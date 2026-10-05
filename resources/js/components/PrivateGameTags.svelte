<script lang="ts">
    import { createPrivateTag, gamePrivateTags, listPrivateTags, setGamePrivateTag, type PrivateTag } from '@/api/private-tags';
    import { getErrorMessage } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { Button, Checkbox, Dialog, TextInput } from '@/components/ui';

    let { gameId }: { gameId: number } = $props();
    let open = $state(false);
    let loading = $state(false);
    let creating = $state(false);
    let pending = $state<number[]>([]);
    let tags = $state<PrivateTag[]>([]);
    let attached = $state<number[]>([]);
    let newName = $state('');

    async function show() {
        open = true;
        loading = true;
        try {
            const [all, assigned] = await Promise.all([listPrivateTags(), gamePrivateTags(gameId)]);
            tags = all;
            attached = assigned.map((tag) => tag.id);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not load private tags'));
        } finally {
            loading = false;
        }
    }

    async function toggle(tag: PrivateTag) {
        const previous = attached;
        const add = !attached.includes(tag.id);
        attached = add ? [...attached, tag.id] : attached.filter((id) => id !== tag.id);
        pending = [...pending, tag.id];
        try {
            await setGamePrivateTag(gameId, tag.id, add);
        } catch (error) {
            attached = previous;
            toast.error(getErrorMessage(error, 'Could not update private tag'));
        } finally {
            pending = pending.filter((id) => id !== tag.id);
        }
    }

    async function create() {
        if (!newName.trim() || creating) return;
        creating = true;
        try {
            const tag = await createPrivateTag(newName.trim());
            await setGamePrivateTag(gameId, tag.id, true);
            tags = [...tags, tag].sort((a, b) => a.name.localeCompare(b.name));
            attached = [...attached, tag.id];
            newName = '';
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not create private tag'));
        } finally {
            creating = false;
        }
    }
</script>

<button type="button" onclick={show} class="cursor-pointer text-fg-muted transition-colors hover:text-fg">Private tags</button>

<Dialog {open} onClose={() => (open = false)} title="Private tags" size="sm">
    <p class="mb-3 text-sm text-fg-muted">Only you can see these tags.</p>
    {#if loading}
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
                disabled={loading || pending.includes(tag.id)}
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
        <Button type="submit" disabled={loading || creating || !newName.trim()}>Add</Button>
    </form>
</Dialog>
