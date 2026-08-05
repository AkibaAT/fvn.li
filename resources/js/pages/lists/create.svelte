<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { storeVnList } from '@/api/lists';
    import { router } from '@inertiajs/svelte';
    import { Button, Card, Checkbox, TextInput, Textarea } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    interface Props {
        metaTags?: {
            title?: string;
            description?: string;
        };
    }

    let { metaTags }: Props = $props();

    let formData = $state({
        name: '',
        description: '',
        is_public: false,
    });
    let isLoading = $state(false);

    async function handleSubmit(e: Event) {
        e.preventDefault();
        isLoading = true;

        try {
            const data = await storeVnList(formData);
            router.visit(route('lists.show', data.list.id));
        } catch (error) {
            console.error('Error creating list:', error);
            alert(error instanceof Error ? error.message : 'Failed to create list');
        } finally {
            isLoading = false;
        }
    }
</script>

<SeoHead {metaTags} title="Create New List" />

<div class="mx-auto max-w-2xl space-y-8">
    <PageHeader title="Create New List" class="mb-0" />

    <Card variant="glass">
        <form onsubmit={handleSubmit} class="space-y-6">
            <TextInput type="text" id="name" bind:value={formData.name} required label="List Name" placeholder="Enter list name..." />

            <Textarea
                id="description"
                bind:value={formData.description}
                rows={4}
                label="Description"
                placeholder="Optional description for your list..."
                help="Describe what this list is for (optional)"
            />

            <div>
                <Checkbox bind:checked={formData.is_public} label="Make this list public" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Public lists can be viewed by anyone, private lists are only visible to you
                </p>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
                <Button href={route('lists.index')} variant="outline" tone="neutral">Cancel</Button>
                <Button type="submit" disabled={isLoading || !formData.name.trim()} loading={isLoading}>
                    {isLoading ? 'Creating...' : 'Create List'}
                </Button>
            </div>
        </form>
    </Card>
</div>
