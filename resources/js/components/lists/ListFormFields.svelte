<script lang="ts">
    import type { Snippet } from 'svelte';
    import { Checkbox, TextInput, Textarea } from '@/components/ui';

    interface ListFormData {
        name: string;
        description: string;
        is_public: boolean;
    }

    interface Props {
        form: ListFormData;
        /** Optional content rendered between the name field and the description field. */
        afterName?: Snippet;
    }

    let { form = $bindable(), afterName }: Props = $props();
</script>

<TextInput type="text" id="name" bind:value={form.name} required label="List Name" placeholder="Enter list name..." />

{#if afterName}{@render afterName()}{/if}

<Textarea
    id="description"
    bind:value={form.description}
    rows={4}
    label="Description"
    placeholder="Optional description for your list..."
    help="Describe what this list is for (optional)"
/>

<div>
    <Checkbox bind:checked={form.is_public} label="Make this list public" />
    <p class="mt-1 text-xs text-fg-faint">Public lists can be viewed by anyone, private lists are only visible to you</p>
</div>
