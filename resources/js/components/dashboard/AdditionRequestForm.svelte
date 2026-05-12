<script lang="ts">
    import { Button, Card } from '@/components/ui';
    import { submitAdditionRequests, type SubmissionResult } from '@/hooks/api/useAdditionRequests';
    import { toast } from '@/utils/toast';

    let urls = $state('');
    let showSuccessMessage = $state(false);
    let submissionResults = $state<SubmissionResult | null>(null);
    let errors = $state<Record<string, string>>({});
    let isSubmitting = $state(false);

    async function submitRequests(e: SubmitEvent) {
        e.preventDefault();
        errors = {};

        if (!urls.trim()) {
            errors = { urls: 'Please enter at least one game URL.' };
            return;
        }

        isSubmitting = true;

        try {
            const data = await submitAdditionRequests(urls);

            if (data.success) {
                const results = data.result;
                submissionResults = results;
                if (results?.success_count > 0) {
                    showSuccessMessage = true;
                    urls = '';
                    toast.success(`Successfully submitted ${results.success_count} request(s)!`);
                }

                if (results?.errors && results.errors.length > 0) {
                    results.errors.forEach((error: string) => {
                        toast.error(error);
                    });
                }
            } else {
                if (data.errors && typeof data.errors === 'object') {
                    const values = Object.values<string | string[]>(data.errors).flat();
                    if (values.length) {
                        values.forEach((msg) => toast.error(String(msg)));
                    }
                    if (!Array.isArray(data.errors) && 'urls' in data.errors) {
                        errors = {
                            urls: Array.isArray(data.errors.urls) ? data.errors.urls[0] : data.errors.urls,
                        };
                    }
                }
                if (data.message) {
                    toast.error(data.message);
                }
            }
        } catch (error) {
            console.error('Error submitting requests:', error);
            toast.error('An error occurred while submitting requests.');
        } finally {
            isSubmitting = false;
        }
    }

    function clearForm() {
        urls = '';
        showSuccessMessage = false;
        submissionResults = null;
        errors = {};
    }
</script>

<Card variant="glass" padding="none">
    <div class="p-6">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Request Game Additions</h2>

        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Submit game URLs (itch.io, Steam, or other platforms) of visual novels you'd like to see added to the database. You can submit multiple
            URLs, one per line.
        </p>

        {#if showSuccessMessage && submissionResults}
            <div class="mb-4 rounded-lg border border-green-200 bg-green-100 p-4 dark:border-green-800 dark:bg-green-900/20">
                <div class="text-green-700 dark:text-green-300">
                    <p class="font-medium">Requests Submitted Successfully!</p>
                    <ul class="mt-2 space-y-1 text-sm">
                        {#if submissionResults.success_count > 0}
                            <li class="flex items-center gap-1">
                                <i class="icon-check inline" aria-hidden="true"></i>
                                {submissionResults.success_count} new request(s) submitted
                            </li>
                        {/if}
                        {#if submissionResults.duplicate_count > 0}
                            <li class="flex items-center gap-1">
                                <i class="icon-info inline" aria-hidden="true"></i>
                                {submissionResults.duplicate_count} URL(s) already requested by you
                            </li>
                        {/if}
                        {#if submissionResults.already_exists_count && submissionResults.already_exists_count > 0}
                            <li class="flex items-center gap-1">
                                <i class="icon-info inline" aria-hidden="true"></i>
                                {submissionResults.already_exists_count} game(s) already exist on the site
                            </li>
                        {/if}
                        {#if submissionResults.invalid_count > 0}
                            <li class="flex items-center gap-1">
                                <i class="icon-alert inline" aria-hidden="true"></i>
                                {submissionResults.invalid_count} invalid URL(s) skipped
                            </li>
                        {/if}
                    </ul>
                </div>
            </div>
        {/if}

        {#if submissionResults?.errors && submissionResults.errors.length > 0}
            <div class="mb-4 rounded-lg border border-red-200 bg-red-100 p-4 dark:border-red-800 dark:bg-red-900/20">
                <p class="mb-2 font-medium text-red-800 dark:text-red-400">Some errors occurred:</p>
                <ul class="space-y-1 text-sm text-red-700 dark:text-red-300">
                    {#each submissionResults.errors as error, idx (idx)}
                        <li>&bull; {error}</li>
                    {/each}
                </ul>
            </div>
        {/if}

        <form onsubmit={submitRequests} class="space-y-4">
            <div>
                <label for="urls" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"> Game URLs </label>
                <textarea
                    id="urls"
                    bind:value={urls}
                    rows={6}
                    class="w-full rounded-md border bg-white px-3 py-2 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white {errors.urls
                        ? 'border-red-300 dark:border-red-600'
                        : 'border-gray-300 dark:border-gray-600'}"
                    placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/Game_Name/&#10;https://example.com/game&#10;..."
                ></textarea>
                {#if errors.urls}
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {errors.urls}
                    </p>
                {/if}
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Enter one URL per line. Maximum 50 URLs per submission.</p>
            </div>

            <div class="flex gap-3">
                <Button
                    type="submit"
                    disabled={isSubmitting || !urls.trim()}
                    loading={isSubmitting}
                    class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-white shadow-md transition-all duration-200 hover:bg-blue-700 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {#if isSubmitting}
                        <div class="flex items-center justify-center">
                            <div class="mr-2 h-4 w-4 animate-spin rounded-full border-b-2 border-white"></div>
                            Submitting...
                        </div>
                    {:else}
                        Submit Requests
                    {/if}
                </Button>

                <Button
                    type="button"
                    variant="soft"
                    tone="neutral"
                    onclick={clearForm}
                    class="rounded-lg bg-gray-200 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
                >
                    Clear
                </Button>
            </div>
        </form>
    </div>
</Card>
