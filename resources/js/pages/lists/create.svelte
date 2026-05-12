<script lang="ts">
    import { authenticatedFetch, readJsonResponse } from '@/utils/csrf';
    import { router } from '@inertiajs/svelte';
    import { Button, Card, Checkbox, TextInput, Textarea } from '@/components/ui';

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
            const response = await authenticatedFetch(route('api.vn-lists.store'), {
                method: 'POST',
                body: JSON.stringify(formData),
            });

            const data = await readJsonResponse<{ success: boolean; message?: string; list: { id: number } }>(response);

            if (data.success) {
                router.visit(route('lists.show', data.list.id));
            } else {
                alert(data.message || 'Failed to create list');
            }
        } catch (error) {
            console.error('Error creating list:', error);
            alert('An error occurred while creating the list');
        } finally {
            isLoading = false;
        }
    }
</script>

<svelte:head>
    <title>{metaTags?.title || 'Create New List'}</title>
</svelte:head>

<div class="mx-auto max-w-2xl space-y-8">
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-bold text-blue-600">Create New List</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Create a new visual novel list to organize your games</p>
    </div>

    <!-- Form -->
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

            <!-- Submit Buttons -->
            <div class="flex justify-end space-x-3 pt-4">
                <Button href={route('lists.index')} variant="outline" tone="neutral">Cancel</Button>
                <Button type="submit" disabled={isLoading || !formData.name.trim()} loading={isLoading}>
                    {isLoading ? 'Creating...' : 'Create List'}
                </Button>
            </div>
        </form>
    </Card>
</div>
