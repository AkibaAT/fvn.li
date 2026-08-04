<script lang="ts">
    import { untrack } from 'svelte';
    import { authenticatedFetch, readJsonResponse } from '@/utils/http';
    import { router } from '@inertiajs/svelte';
    import { Button, Card, Checkbox, TextInput, Textarea } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { formatListType } from '@/components/ui/tones';

    interface VnList {
        id: number;
        name: string;
        description?: string;
        type: string;
        is_default: boolean;
        is_public: boolean;
    }

    interface Props {
        vnList: VnList;
        metaTags?: {
            title?: string;
            description?: string;
        };
    }

    let { vnList, metaTags }: Props = $props();

    let formData = $state(
        untrack(() => ({
            name: vnList.name,
            description: vnList.description || '',
            is_public: vnList.is_public,
        })),
    );
    let isLoading = $state(false);
    let isDeleting = $state(false);

    async function handleSubmit(e: Event) {
        e.preventDefault();
        isLoading = true;

        try {
            const response = await authenticatedFetch(route('api.vn-lists.update', vnList.id), {
                method: 'PUT',
                body: JSON.stringify(formData),
            });

            const data = await readJsonResponse<{ success: boolean; message?: string }>(response);

            if (data.success) {
                router.visit(route('lists.show', vnList.id));
            } else {
                alert(data.message || 'Failed to update list');
            }
        } catch (error) {
            console.error('Error updating list:', error);
            alert('An error occurred while updating the list');
        } finally {
            isLoading = false;
        }
    }

    async function handleDelete() {
        if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) {
            return;
        }

        isDeleting = true;

        try {
            const response = await authenticatedFetch(route('api.vn-lists.destroy', vnList.id), { method: 'DELETE' });

            const data = await readJsonResponse<{ success: boolean; message?: string }>(response);

            if (data.success) {
                router.visit(route('lists.index'));
            } else {
                alert(data.message || 'Failed to delete list');
            }
        } catch (error) {
            console.error('Error deleting list:', error);
            alert('An error occurred while deleting the list');
        } finally {
            isDeleting = false;
        }
    }
</script>

<svelte:head>
    <title>{metaTags?.title || `Edit List - ${vnList.name}`}</title>
</svelte:head>

<div class="mx-auto max-w-2xl space-y-8">
    <PageHeader title="Edit List" backHref={route('lists.show', vnList.id)} backLabel="Back to list" class="mb-0" />

    <Card variant="glass">
        <form onsubmit={handleSubmit} class="space-y-6">
            <TextInput type="text" id="name" bind:value={formData.name} required label="List Name" placeholder="Enter list name..." />

            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">List Type</p>
                <div class="mt-1 rounded-md border border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
                    <span class="text-sm text-gray-900 dark:text-gray-100">
                        {formatListType(vnList.type)}
                        {vnList.is_default ? ' (Default)' : ''}
                    </span>
                </div>
                {#if vnList.is_default}
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Default lists cannot be modified</p>
                {/if}
            </div>

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

            <div class="flex justify-between pt-4">
                <div class="flex space-x-3">
                    <Button href={route('lists.show', vnList.id)} variant="outline" tone="neutral">Cancel</Button>
                    {#if !vnList.is_default}
                        <Button type="button" onclick={handleDelete} disabled={isDeleting} tone="danger" loading={isDeleting}>
                            {isDeleting ? 'Deleting...' : 'Delete List'}
                        </Button>
                    {/if}
                </div>
                <Button type="submit" disabled={isLoading || !formData.name.trim()} loading={isLoading}>
                    {isLoading ? 'Saving...' : 'Save Changes'}
                </Button>
            </div>
        </form>
    </Card>
</div>
