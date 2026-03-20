<script lang="ts">
    import { toast } from '@/utils/toast';

    let isDeleting = $state(false);

    async function handleDeleteAccount(e: SubmitEvent) {
        e.preventDefault();

        if (
            !confirm(
                'Are you sure you want to delete your account? This action cannot be undone.',
            )
        ) {
            return;
        }

        if (
            !confirm(
                'This will permanently delete all your data, including your lists, progress, and account information. Are you absolutely sure?',
            )
        ) {
            return;
        }

        isDeleting = true;

        try {
            const response = await fetch(route('user.delete'), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
            });

            if (response.ok) {
                window.location.href = '/';
            } else {
                const data = await response.json();
                toast.error(data.message || 'Failed to delete account.');
            }
        } catch (error) {
            console.error('Error deleting account:', error);
            toast.error('An error occurred while deleting your account.');
        } finally {
            isDeleting = false;
        }
    }
</script>

<div class="rounded-2xl border border-red-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-red-800/50 dark:bg-gray-800/70">
    <div class="p-6">
        <h2 class="mb-4 text-lg font-semibold text-red-600 dark:text-red-500">
            Danger Zone
        </h2>

        <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <h3 class="mb-2 font-medium text-red-800 dark:text-red-400">
                Delete Account
            </h3>
            <p class="mb-4 text-sm text-red-700 dark:text-red-300">
                Once you delete your account, there is no going back.
                Please be certain.
            </p>

            <form onsubmit={handleDeleteAccount}>
                <button
                    type="submit"
                    disabled={isDeleting}
                    class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {#if isDeleting}
                        <div class="h-4 w-4 animate-spin rounded-full border-b-2 border-white"></div>
                        Deleting...
                    {:else}
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                        Delete Account
                    {/if}
                </button>
            </form>
        </div>
    </div>
</div>
