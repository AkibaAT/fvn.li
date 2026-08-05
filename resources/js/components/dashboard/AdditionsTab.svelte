<script lang="ts">
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { cancelAdditionRequest, fetchAdditionRequests, submitAdditionRequests, type AdditionRequest, type AdditionSubmissionResult } from '@/api';
    import { toast } from '@/utils/toast';
    import { Alert, Button, Card } from '@/components/ui';

    interface AdditionsTabProps {
        recentRequests: AdditionRequest[];
    }

    let { recentRequests: recentRequestsInitial }: AdditionsTabProps = $props();

    let requestText = $state('');
    let requests = $state<AdditionRequest[]>(untrack(() => recentRequestsInitial || []));
    let _requestsLoading = $state(false);
    let _requestResults: AdditionSubmissionResult | null = $state(null);
    let _showRequestSuccess = $state(false);
    let requestSearch = $state('');
    let requestStatus = $state<'all' | 'pending' | 'processing' | 'approved' | 'rejected'>('all');
    let submittingRequest = $state(false);

    const loadRequests = async (opts?: { status?: string; search?: string }) => {
        _requestsLoading = true;
        try {
            requests = await fetchAdditionRequests({
                status: opts?.status ?? requestStatus,
                search: (opts?.search ?? requestSearch).trim() || undefined,
            });
        } catch {
            /* ignore */
        } finally {
            _requestsLoading = false;
        }
    };

    const submitRequest = async () => {
        const trimmed = requestText.trim();
        if (!trimmed) return;
        submittingRequest = true;
        try {
            const data = await submitAdditionRequests(trimmed);
            if (data.success) {
                const result: AdditionSubmissionResult = data.result;
                _requestResults = result;
                _showRequestSuccess = result?.success_count > 0;
                if (result?.success_count > 0) {
                    toast.success(data.message || `Successfully submitted ${result.success_count} request(s)!`);
                    requestText = '';
                }
                await loadRequests({ status: requestStatus, search: requestSearch });
            } else {
                _requestResults = data?.result ?? { success_count: 0, duplicate_count: 0, invalid_count: 0, errors: [] };
                _showRequestSuccess = false;
            }
        } catch {
            toast.error('An error occurred while submitting requests.');
        } finally {
            submittingRequest = false;
        }
    };

    const cancelRequest = async (id: number) => {
        try {
            await cancelAdditionRequest(id);
            await loadRequests({ status: requestStatus, search: requestSearch });
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to cancel addition request.');
        }
    };

    $effect(() => {
        void requestStatus;
        loadRequests({ status: requestStatus });
    });

    const filteredRequests = $derived(
        requests.filter((request) => {
            const search = requestSearch.trim().toLowerCase();

            if (!search) {
                return true;
            }

            return (
                request.game_url.toLowerCase().includes(search) ||
                request.status.toLowerCase().includes(search) ||
                request.status_label.toLowerCase().includes(search) ||
                (request.game?.name?.toLowerCase() ?? '').includes(search)
            );
        }),
    );

    const getStatusBadgeClasses = (color: string) => {
        switch (color) {
            case 'warning':
            case 'yellow':
                return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
            case 'info':
            case 'blue':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
            case 'success':
            case 'green':
                return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
            case 'danger':
            case 'red':
                return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
        }
    };
</script>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
    <div class="space-y-6 lg:col-span-2">
        <Card padding="lg">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Request VN Addition</h2>
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                Submit URLs for visual novels you'd like to see added to the site. We support itch.io, Steam, and other platforms. You can submit
                multiple URLs at once, one per line.
            </p>
            <div class="space-y-3">
                <div>
                    <label for="game-urls" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Game URLs</label>
                    <textarea
                        id="game-urls"
                        bind:value={requestText}
                        rows={5}
                        placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/game-name&#10;..."
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    ></textarea>
                </div>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        onclick={submitRequest}
                        disabled={submittingRequest || !requestText.trim()}
                        loading={submittingRequest}
                    >
                        {submittingRequest ? 'Submitting...' : 'Submit Requests'}
                    </Button>
                    <Button type="button" variant="soft" tone="neutral" onclick={() => (requestText = '')}>Clear</Button>
                </div>
            </div>
            <Alert title="Guidelines" tone="info" role="status" class="mt-4">
                <ul class="list-inside list-disc space-y-1">
                    <li>Supported platforms: itch.io, Steam, and other game storefronts</li>
                    <li>Submit one URL per line for bulk requests</li>
                    <li>Maximum 50 URLs per submission</li>
                    <li>Games already on the site will be automatically filtered out</li>
                    <li>Duplicate requests are automatically handled</li>
                </ul>
            </Alert>
        </Card>
    </div>

    <div class="space-y-6 lg:col-span-3">
        <Card padding="lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My Requests</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">{filteredRequests.length} request(s)</span>
            </div>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row">
                <div class="flex-1">
                    <input
                        placeholder="Search by URL or status..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        type="text"
                        bind:value={requestSearch}
                    />
                </div>
                <div>
                    <select
                        aria-label="Filter addition requests by status"
                        bind:value={requestStatus}
                        class="rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="all">All Requests</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            {#if filteredRequests.length > 0}
                <div class="space-y-2">
                    {#each filteredRequests as req (req.id)}
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-700/50">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {req.game?.name || req.game_url}
                                </div>
                                {#if req.game}
                                    <a
                                        href={req.game_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 block truncate text-xs text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        {req.game_url}
                                    </a>
                                {/if}
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {getStatusBadgeClasses(req.status_color)}"
                                    >{req.status_label}</span
                                >
                            </div>
                            <div class="ml-3 flex items-center gap-3">
                                {#if req.status === 'approved' && req.game}
                                    <Link href={route('games.show', req.game.slug)} class="text-xs text-blue-600 hover:underline dark:text-blue-400"
                                        >View entry</Link
                                    >
                                {/if}
                                {#if req.status === 'pending' || req.status === 'processing'}
                                    <Button type="button" variant="link" tone="danger" onclick={() => cancelRequest(req.id)}>Cancel</Button>
                                {/if}
                            </div>
                        </div>
                    {/each}
                </div>
            {:else}
                <div class="py-8 text-center">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">No requests found</div>
                    <div class="text-xs text-gray-400 dark:text-gray-500">You haven't submitted any addition requests yet.</div>
                </div>
            {/if}
        </Card>
    </div>
</div>
