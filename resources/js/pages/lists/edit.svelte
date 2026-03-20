<script lang="ts">
    import { untrack } from 'svelte';
    import { authenticatedFetch } from '@/utils/csrf';
    import { Link, router } from '@inertiajs/svelte';

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

            const data = await response.json();

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

            const data = await response.json();

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
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-bold text-blue-600">Edit List</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Update your visual novel list settings</p>
    </div>

    <!-- Form -->
    <div class="rounded-xl bg-white/70 p-6 shadow-lg backdrop-blur-xl dark:bg-gray-800/70">
        <form onsubmit={handleSubmit} class="space-y-6">
            <!-- List Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> List Name * </label>
                <input
                    type="text"
                    id="name"
                    bind:value={formData.name}
                    required
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Enter list name..."
                />
            </div>

            <!-- List Type (Read-only for default lists) -->
            <div>
                <p class="block text-sm font-medium text-gray-700 dark:text-gray-300">List Type</p>
                <div class="mt-1 rounded-md border border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
                    <span class="text-sm text-gray-900 dark:text-gray-100">
                        {vnList.type.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase())}
                        {vnList.is_default ? ' (Default)' : ''}
                    </span>
                </div>
                {#if vnList.is_default}
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Default lists cannot be modified</p>
                {/if}
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300"> Description </label>
                <textarea
                    id="description"
                    bind:value={formData.description}
                    rows={4}
                    class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    placeholder="Optional description for your list..."
                ></textarea>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Describe what this list is for (optional)</p>
            </div>

            <!-- Public/Private Toggle -->
            <div>
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        bind:checked={formData.is_public}
                        class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                    />
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300"> Make this list public </span>
                </label>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Public lists can be viewed by anyone, private lists are only visible to you
                </p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-between pt-4">
                <div class="flex space-x-3">
                    <Link
                        href={route('lists.show', vnList.id)}
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </Link>
                    {#if !vnList.is_default}
                        <button
                            type="button"
                            onclick={handleDelete}
                            disabled={isDeleting}
                            class="inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {isDeleting ? 'Deleting...' : 'Delete List'}
                        </button>
                    {/if}
                </div>
                <button
                    type="submit"
                    disabled={isLoading || !formData.name.trim()}
                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {isLoading ? 'Saving...' : 'Save Changes'}
                </button>
            </div>
        </form>
    </div>
</div>
