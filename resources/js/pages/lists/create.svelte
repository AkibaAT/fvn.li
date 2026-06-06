<script lang="ts">
    import { authenticatedFetch, readJsonResponse } from '@/utils/csrf';
    import { Link } from '@inertiajs/svelte';
    import { router } from '@inertiajs/svelte';

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
            <div class="flex justify-end space-x-3 pt-4">
                <Link
                    href={route('lists.index')}
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    disabled={isLoading || !formData.name.trim()}
                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {isLoading ? 'Creating...' : 'Create List'}
                </button>
            </div>
        </form>
    </div>
</div>
